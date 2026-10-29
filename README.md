# symfony-activity

Version: 3.0.7

What happens to a subject — a user, an organization — kept as a history, recorded by category, each category enabled, kept and purged as the application decides

## Table of Contents

- [Installation](#installation)

## Installation

```bash
composer require wexample/symfony-activity
```

```php
// config/bundles.php
DH\AuditorBundle\DHAuditorBundle::class => ['all' => true],
Wexample\SymfonySecurity\WexampleSymfonySecurityBundle::class => ['all' => true],
Wexample\SymfonyActivity\WexampleSymfonyActivityBundle::class => ['all' => true],
```

`damienharper/auditor-bundle` comes with the package and records the field changes: its bundle has to be registered too — Flex adds it on install, a kernel without it fails to boot saying so. symfony-activity configures it (`dh_auditor`: UTC, the signed-in user as the author of a change): an application's own `dh_auditor` configuration is no longer needed, and still wins where it says something.

The bundle maps its `activity` table, and auditor adds one `<table>_audit` table per entity marked `#[Auditable]`: generate a migration after installing, and again after marking an entity.

```bash
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Nothing is recorded until categories are declared, and no field change until an entity is marked — see the usage page:

```yaml
# config/packages/wexample_symfony_activity.yaml
wexample_symfony_activity:
    categories:
        security: ~
```

Where a category has a retention, schedule the purge once a day:

```bash
bin/console activity:purge
```

symfony-security comes with the package: its `#[RateLimit]` guards the endpoint browsers report to, and what it refuses is recorded here.

symfony-user writes its security facts here by itself once both bundles are enabled; symfony-activity-ds draws the history and the journal.
