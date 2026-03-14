# SignoffFlow

SignoffFlow is a WordPress plugin for running a client approval workflow and client portal inside WordPress.

This repository contains the plugin source for the Git project. It is not the same as the future WordPress.org `readme.txt`; this file is intended for developers, collaborators, and anyone browsing the repository.

## Current Status

Current package name: `client-approval-workflow`

Current public/admin brand: `SignoffFlow`

Current release version: `0.2.0`

Implemented so far:

- Milestone 1: plugin bootstrap, settings page, capabilities
- Milestone 2: client CPT, client-user assignment, access helpers
- Milestone 3: portal shortcode, updates CPT, client updates timeline
- Milestone 4: files CPT, staff uploads, protected client downloads
- Milestone 5: requests/tasks module with client completion and staff reopen/override
- Milestone 6: event log and email notifications for updates/files
- Milestone 7: security hardening, portal UX polish, i18n scaffolding, WP.org readme draft
- Milestone 8: approvals extension hooks, Pro detection helper, and minimal placeholder UI
- Milestone 9: release packaging docs, changelog, uninstall handler, and validation pass

Planned next:

- release candidate validation on a live WordPress install

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Local Development

1. Place this repository in `wp-content/plugins/client-approval-workflow`.
2. Run `composer install`.
3. Activate the plugin in WordPress.
4. Create a WordPress page for the portal.
5. In `SignoffFlow > Settings`, select that page as the portal base page.
6. Add the shortcode `[cliapwo_portal]` to the page content.

## Development Commands

- `composer lint`
- `composer lint:fix`

## Release Docs

- Release checklist: `RELEASE.md`
- Changelog: `CHANGELOG.md`

## Current Plugin Flow

1. Activate the plugin.
2. Configure the portal page in `SignoffFlow > Settings`.
3. Create a client in `SignoffFlow > Clients`.
4. Assign one or more WordPress users to that client.
5. Create updates in `SignoffFlow > Updates`.
6. Upload downloadable files in `SignoffFlow > Files`.
7. Create client requests/tasks in `SignoffFlow > Requests`.
8. Visit the portal page as an assigned client user to view updates, files, and requests.
9. Let client users mark requests complete from the portal; staff can reopen or override statuses.
10. Review `SignoffFlow > Event Log` for update/file events and email attempt records.

## Repository Conventions

- Public brand/UI naming uses `SignoffFlow`.
- Package-level identifiers use `client-approval-workflow`.
- Code-facing identifiers use the `cliapwo` prefix.
- PHP namespace uses `ClientApprovalWorkflow\`.

## Protected File Storage

Files are stored in a dedicated `cliapwo-private` uploads subdirectory and are served only through the protected SignoffFlow download handler.

The protected endpoint enforces:

- logged-in access
- nonce verification
- client assignment checks

Apache hardening files are created automatically for that storage directory. On Nginx-based hosts, equivalent server rules may still need to be added manually because Nginx does not honor `.htaccess`.

## Notifications and Local Testing

Request, update, and file notifications use `wp_mail()` and are controlled by the `Request emails`, `Update emails`, and `File emails` settings in `SignoffFlow > Settings`.

Emails are sent to all WordPress users assigned to the related client record.

For local environments where outbound mail is not configured:

- `SignoffFlow > Event Log` records the update/file event and a separate `Email attempt` entry with the targeted recipients
- the triggering staff user also gets a one-time admin notice after the save redirect showing the `wp_mail()` attempt result and recipients

This makes it possible to verify notification flow locally even when no real message is delivered.

## Security Notes

- All privileged admin mutations should use capability checks and nonces.
- Portal access is based on client-user assignment plus `cliapwo_view_portal`.
- Staff/admin actions use `cliapwo_manage_portal`.

## License

GPL v2 or later.

## Planning

The milestone plan lives in `PLAN.md`.
