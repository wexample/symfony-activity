# symfony-activity

Version: 3.0.3

## What it keeps

The suite's one logging bundle: everything logged about an application is recorded and read here.

- **Events** — what happens to a subject, a user, an organization, anything with an id: one `Entity\Activity` row per fact, with its category, a stable type code (`security.login.succeeded`, `support.contacted`), a JSON context, who did it when it was someone else, and the UTC time.
- **Field changes** — who changed which field of an entity, when, old → new: recorded by `damienharper/auditor-bundle`, a dependency of the package, for the entities marked `#[Auditable]`, in its own `<table>_audit` tables. They read as entries of the `change` category.

Nothing is kept until the application says so. A category it did not declare, or declared disabled, records nothing, and a call stays harmless in the code of a package whatever the application decided:

```yaml
# config/packages/wexample_symfony_activity.yaml
wexample_symfony_activity:
    categories:
        security: ~                 # kept for good
        page:
            retention_days: 7       # then deleted by activity:purge
        support:
            retention_days: 1095
            on_expiry: anonymize    # then kept, counted, with neither subject, actor nor context
        billing:
            enabled: false          # declared, recording nothing
        server:
            retention_days: 90
            merge_within: 1 hour    # a repeat within the hour counts on the first entry
```

No retention is imposed, and no protection either: what to keep and when to forget it is a rule of the market an application serves. Each one is there to switch on — a retention per category, anonymizing rather than deleting, a copy and an erasure per person.

## Recording

```php
$recorder->record($user, 'support', 'support.contacted', ['channel' => 'chat']);
$recorder->record($account, 'support', 'support.answered', actor: $agent);

if ($recorder->records('billing')) {
    $recorder->record($customer, 'billing', 'billing.invoice_paid', $this->expensiveContext());
}
```

A subject is named by its type and id: an entity by its mapped class and identifier, needing nothing; anything else by implementing `Interface\ActivitySubjectInterface`; a caller holding only an id builds a `Class\ActivitySubject` itself. `ActivitySubjectService::supports()` says whether an object can have a history at all.

The row is inserted through the connection, not persisted: an entry is often written from inside someone else's unit of work, and a flush would carry along whatever the caller has pending.

Keep the context to what the entry needs to be read later: no secret, and as little about people as the application can do with — the less it holds, the less there is to anonymize, export or erase.

## The suite's own categories

`Enum\ActivityCategory` names them:

- `security` — symfony-user, where it is installed: every `SecurityEvent` naming an account becomes an entry of its history, `security.<type>` with the method, the cause of a failure, the IP and the user agent. The login history of an account is this category of it.
- `page` — `EventSubscriber\PageViewSubscriber`: each page a signed-in user reads, as `page.viewed` with its route and its path without the query. A page is a GET answered with HTML, not under `/_`, not an error. A user that is neither an entity nor an `ActivitySubjectInterface` is skipped. The heaviest category by far: give it a short retention.
- `mail` — reserved for the mails sent to a subject.
- `change` — the field changes, below. Never recorded by hand: `record()` refuses it.
- `server` — `EventSubscriber\ServerErrorActivitySubscriber`: each request the server could not answer, a 5xx, as `server.error` with the exception's class, message, file and line, the route and the request id. The log file keeps them too: when the database is what failed, the log is where it is told.
- `client` and `ui` — what browsers report, below.

`security` also receives `security.rate_limit.exceeded`, each request a `#[RateLimit]` refused, and `security.activity_report.refused`, the reported entries refused and why.

## Whose history

What a request does is recorded against `ActivityVisitorService::current()`: the signed-in user, when the package can name them; otherwise a `visitor`, named by a keyed hash of their session — of their address and browser when they have none. Nothing goes unrecorded for want of someone signed in, and the id gives nobody back.

## Repeats

A category with `merge_within` counts a repeat instead of writing it: an entry of the same subject and fingerprint whose last occurrence is within the interval takes it, `getOccurrences()` then saying how many times and `getLastOccurredAt()` when last. The fingerprint is the caller's, a hash of what makes two entries the same:

```php
$recorder->record($subject, 'server', 'server.error', $context, fingerprint: hash('sha256', $class.'|'.$file.'|'.$line));
```

Without `merge_within`, or without a fingerprint, every entry is written.

## Browsers reporting

`POST /_activity/report` takes what a browser reports, a batch of `{ category, type, context, fingerprint }` — symfony-activity-ds sends its errors and what its interface tells. Only the categories in `client_categories` are accepted, and recorded only when enabled:

```yaml
wexample_symfony_activity:
    client_categories: [client, ui]
    categories:
        client: { retention_days: 30, merge_within: 1 hour }
        ui: { retention_days: 30 }
```

Anyone may post there, so it is bounded: JSON only, which another site's page cannot send without asking first; 20 entries a batch; a type in dotted lowercase, a flat context of at most 30 scalars, strings cut at 1000 characters, a hexadecimal fingerprint; 60 batches a minute per address. What is refused is counted in `security`, never dropped unseen. Each answer lists, in `X-Activity-Categories`, the categories still recorded, so that a browser stops sending the rest.

## Field changes

An entity is audited by its mark, and by nothing else:

```php
use DH\Auditor\Provider\Doctrine\Auditing\Annotation\Auditable;

#[ORM\Entity]
#[Auditable]
class Parameter extends AbstractEntity
```

Each insert, update, removal and relation added or removed is then kept with the fields it touched, the signed-in user who made it and their address — auditor's work, configured by the package: UTC, and the author named by the id symfony-activity names an actor with, so that the journal finds what someone did in both. `#[Ignore]` on a field leaves it out. Marking an entity adds its audit table: a migration.

Read with the rest, a change is an entry of the `change` category, typed `change.insert`, `change.update`, `change.remove`, `change.associate` or `change.dissociate`, its fields in `getChanges()` as `{ field, old, new }`, each value worded — a related entity by its label, a JSON field key by key. Its author is known by id only: the id of a signed-in user.

`categories.change` sets their retention like any category's; `on_expiry: anonymize` empties the author and the address, the change itself is kept. auditor's own viewer is left off: symfony-activity-ds shows the changes with the events.

## Reading

`Service\ActivityJournalService` reads everything as one journal, newest first: the events and the changes, merged by one SQL union and paged by the database. A `Class\ActivityJournalFilter` narrows it — a subject, a category, a type, an actor, a period:

```php
$journal->paginate(new ActivityJournalFilter(category: 'change', from: $monday), $page, 50);
$journal->findRecent(new ActivityJournalFilter(actor: $subjects->of($user)), 10);
```

The actor is who did it: the actor of an event, or its subject when it names none, and the author of a change. `findCategories()` and `findTypes()` give what a filter offers, `count()` how many there are. Each entry is a `Class\ActivityEntry`, answering to the getters of an `Activity`, plus `getChanges()` and, on a page, `getSubjectLabel()` and `getActorLabel()` — the entity's own string, a user's identifier, its short type and id otherwise.

Time is kept to the second in both tables: within one second, the changes come before the events, in a fixed order.

`Repository\ActivityRepository` reads the history of one subject through it: `findRecent()`, `paginate()`, `findSubjectCategories()`. symfony-activity-ds draws them: `partials/recent.html.twig` as a timeline, `partials/table.html.twig` as a paged table, and the journal page.

## Forgetting

`bin/console activity:purge` applies the retention of every category: past it, an entry is deleted or anonymized as its `on_expiry` says. Schedule it once a day; a category without retention keeps everything.

`ActivitySubjectDataService` answers what a person may ask where the law gives them the right to, and only when the application calls it:

- `export($subject)`: every entry of its history and of what it did elsewhere, oldest first, as plain arrays — the events and the changes, `by_someone_else` on its own history when another did it, `about_someone_else` naming the type of what an entry elsewhere was about, never whom;
- `erase($subject)`: removes its history — events and changes made to it —, and takes its name off what it did elsewhere: those entries stay, by nobody.

A change knows its author by id, so the changes someone made are only found for a subject that signs in — a Symfony security user.

## Schema

The bundle maps `Entity\Activity` to an `activity` table, indexed by subject and by category, each with the time; auditor adds a `<table>_audit` table per entity marked `#[Auditable]`, on the same connection. An application generates a migration after adding the package, and again after marking an entity.

## Table of Contents

- [What it keeps](#what-it-keeps)
- [Recording](#recording)
- [The suite's own categories](#the-suites-own-categories)
- [Whose history](#whose-history)
- [Repeats](#repeats)
- [Browsers reporting](#browsers-reporting)
- [Field changes](#field-changes)
- [Reading](#reading)
- [Forgetting](#forgetting)
- [Schema](#schema)
- [Integration in the Suite](#integration-in-the-suite)
- [Dependencies](#dependencies)
- [Versioning & Compatibility Policy](#versioning--compatibility-policy)
- [License](#license)
- [About us](#about-us)
- [Migration Notes](#migration-notes)

## Integration in the Suite

This package is part of the Wexample Suite — a collection of high-quality, modular tools designed to work seamlessly together across multiple languages and environments.

### Related Packages

The suite includes packages for configuration management, file handling, prompts, and more. Each package can be used independently or as part of the integrated suite.

## Dependencies

- php: >=8.5
- doctrine/orm: ^3.0
- doctrine/doctrine-bundle: ^2.13 || ^3.0
- symfony/console: ^7.4 || ^8.0
- symfony/http-kernel: ^7.4 || ^8.0
- symfony/routing: ^7.4 || ^8.0
- symfony/security-core: ^7.4 || ^8.0
- wexample/symfony-helpers: >=17.0.0
- wexample/symfony-security: >=4.0.0
- symfony/doctrine-bridge: ^7.4 || ^8.0
- symfony/uid: ^7.4 || ^8.0
- symfony/event-dispatcher: ^7.4 || ^8.0
- damienharper/auditor-bundle: ^6.3

## Versioning & Compatibility Policy

Wexample packages follow **Semantic Versioning** (SemVer):

- **MAJOR**: Breaking changes
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes, backward compatible

We maintain backward compatibility within major versions and provide clear migration guides for breaking changes.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Free to use in both personal and commercial projects.

## About us

[Wexample](https://wexample.com) stands as a cornerstone of the digital ecosystem — a collective of seasoned engineers, researchers, and creators driven by a relentless pursuit of technological excellence. More than a media platform, it has grown into a vibrant community where innovation meets craftsmanship, and where every line of code reflects a commitment to clarity, durability, and shared intelligence.

This packages suite embodies this spirit. Trusted by professionals and enthusiasts alike, it delivers a consistent, high-quality foundation for modern development — open, elegant, and battle-tested. Its reputation is built on years of collaboration, refinement, and rigorous attention to detail, making it a natural choice for those who demand both robustness and beauty in their tools.

Wexample cultivates a culture of mastery. Each package, each contribution carries the mark of a community that values precision, ethics, and innovation — a community proud to shape the future of digital craftsmanship.

Built and maintained by [Romain Weeger](https://weeger.io).

## Migration Notes

When upgrading between major versions, refer to the migration guides in the documentation.

Breaking changes are clearly documented with upgrade paths and examples.
