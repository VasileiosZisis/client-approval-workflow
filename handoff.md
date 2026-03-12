# Handoff

## Current state

- Plugin: `SignoffFlow`
- Package: `client-approval-workflow`
- Main file: `client-approval-workflow.php`
- Text domain: `client-approval-workflow`
- Namespace: `ClientApprovalWorkflow\`
- Prefix: `cliapwo`
- Current release version: `0.2.0`

## Milestones completed

- M1 through M9 are implemented
- Recent fixes include:
  - release packaging/version bump to `0.2.0`
  - `CHANGELOG.md` and `RELEASE.md`
  - explicit `uninstall.php`
  - request email docs updates
  - file upload sanitization tightened for Plugin Check
  - approvals submenu hidden unless Pro is active

## Current worktree focus

- Release/readme/docs are updated for `0.2.0`
- Remaining work is now centered on known issues and post-release hardening
- `AGENTS.md` is user-modified in the worktree and should be treated as off-limits unless explicitly requested

## Completed in M9

1. Prepared a release-ready build
2. Bumped plugin version to `0.2.0`
3. Added/updated changelog and release docs
4. Ran available syntax/lint validation
5. Documented final checklist and known issues

## Validation completed

- PHP lint across plugin PHP files passed
- `composer lint` passed
- Version/readme consistency was checked
- Live browser/wp-admin smoke testing was not run from this environment

## Known issues to tackle next

1. Protected files still rely on Media Library storage
   - Risk: a raw attachment URL may still be reachable if someone knows it
   - Recommended next step: move protected file storage out of the public uploads path or add server-level protection for a dedicated subdirectory
   - Likely files: `includes/class-files.php`, `README.md`, `readme.txt`

2. Email delivery depends on the site mail transport
   - Risk: `wp_mail()` may succeed logically but not deliver in local/staging environments
   - Recommended next step: add a small diagnostics section on the settings page or a documented test flow for SMTP/Postmark/Mailpit, without adding tracking or external calls automatically
   - Likely files: `includes/class-settings.php`, `README.md`, `readme.txt`

3. No generated POT file is shipped yet
   - Risk: translation scaffolding exists, but translators do not have an extracted source catalog yet
   - Recommended next step: generate `languages/client-approval-workflow.pot` and document the command in the repo
   - Likely files: `languages/client-approval-workflow.pot` (new), `README.md`, possibly `composer.json`

4. End-to-end live WordPress smoke tests are still manual and not recorded
   - Risk: packaging is validated, but release confidence still depends on local manual verification
   - Recommended next step: run the smoke test list from `RELEASE.md` on a clean WP install and capture results in a short QA note
   - Likely files: `RELEASE.md`, `README.md`, optional `QA.md` (new)

## Recommended order of work

1. Harden file storage/downloads first. This is the highest-impact security limitation.
2. Generate and ship the POT file. This is low risk and finishes the i18n packaging work.
3. Improve mail diagnostics/documentation for local and staging verification.
4. Run and document live smoke tests on a clean WordPress install.

## Recommended next action

Proceed with the protected file storage hardening pass first, then finish i18n packaging with a generated POT file, then run documented live smoke tests.
