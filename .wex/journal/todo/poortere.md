# Poortere / LDP production planning: phase 2 — one robust logging bundle

Opened: 2026-10-07
Updated: 2026-10-07
Author: agent:app:main

## Context

Phase 1 is done: you checked the platform checklist of LDP production planning (Poortere, for Mojoe) —
`/home/weeger/Desktop/WIP/WEB/MOJOE/local/poortere/ldp-production-planning/.wex/knowledge/specifications/platform-features.md.j2`
— and wrote `.wex/checklist-feedback/symfony-activity.md` beside it. This todo is phase 2: building
what is missing in your package, under weeger's supervision. It covers `symfony-activity-ds` too.

**weeger's decision:** the suite has one logging bundle, and it is this one. No optional dependency:
either the suite has a robust logging bundle or it does not. So `symfony-activity` takes
`damienharper/auditor-bundle` as a **hard dependency** and becomes the place where everything that is
logged about the application is recorded and read — the events it already records through `record()`,
and the field-level changes (who, when, old → new per field) that auditor records on the entities an
application declares.

## To build

1. **auditor-bundle as a hard dependency.** Require it, configure it with safe defaults from the bundle
   (storage, retention aligned with your categories' retention where it makes sense, the current user as
   the actor), so that an application only has to declare which entities it audits. Nothing is audited
   until an entity is declared. Do not rebuild what auditor does: no Doctrine listener of your own for
   field diffs. Sapiens already runs it — `/home/weeger/Desktop/WIP/WEB/HOME_HABILIS/local/sapiens/config/packages/dh_auditor.yaml`
   — read it before choosing the defaults; Sapiens should end up with less configuration, not more.
2. **One place to read both.** In `symfony-activity-ds`, the history of a subject shows its events and
   its field changes together, and a page or partial reads them. Whether auditor's own viewer is reused,
   wrapped or replaced is your call — say why.
3. **An audit journal across all subjects** (from your phase 1 list): a query by category, type, actor
   and period, paged, and a page or partial in `symfony-activity-ds` with those filters. Field changes
   belong in it.
4. **Export of a person's data includes what they did as actor** (from your phase 1 list):
   `ActivitySubjectDataService::export()` misses the entries the person made in someone else's history,
   where `erase()` already reaches them. Field changes they made count too.

Then tick §16 « History of parameter changes » in the checklist with the lead naming your package, and
update your feedback file.

Not yours: the GDPR collection across packages (§20) is `symfony-user`'s — a contract each package
holding personal data implements, and one service collecting them. Your half is the `export()` / `erase()`
you already have, which must not depend on `symfony-user`.

## How

- **Stay in your package** and `symfony-activity-ds`. Other agents work at the same time in their own
  packages and in the same checklist: edit only your lines, re-read the file before writing it.
- **The suite's conventions** (`wex ai::design/rules`, shapes, naming, documentation), and reuse before
  writing: `data-table`, `filter-bar`, `pagination`, `marker`, `timeline` for the screens.
- **Commit as you go**, only the files you changed, with the `commit` tool; never `git add -A`.
- **A new table is a migration for every application.** The design-system app answered 500 on login the
  last time a package mapped a column and nothing ran the migration: say plainly in your answer what each
  application has to run.
- To see it in the Mojoe design system, run `wex app::setup/install --env local` in
  `/home/weeger/Desktop/WIP/WEB/MOJOE/local/design-system`, or leave it to weeger at the end.
- End by telling weeger briefly what is done, what each application must do, and what is left.
