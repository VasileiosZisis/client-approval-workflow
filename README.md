# SignoffFlow

SignoffFlow is a WordPress plugin for running a client approval workflow and client portal inside WordPress.

This repository contains the plugin source for the Git project. It is not the same as the future WordPress.org `readme.txt`; this file is intended for developers, collaborators, and anyone browsing the repository.

## Current Status

Current package name: `client-approval-workflow`

Current public/admin brand: `SignoffFlow`

Implemented so far:

- Milestone 1: plugin bootstrap, settings page, capabilities
- Milestone 2: client CPT, client-user assignment, access helpers
- Milestone 3: portal shortcode, updates CPT, client updates timeline
- Milestone 4: files CPT, staff uploads, protected client downloads

Planned next:

- Requests/tasks
- notifications and event log
- UX polish and WP.org packaging
- Pro extension points

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

## Current Plugin Flow

1. Activate the plugin.
2. Configure the portal page in `SignoffFlow > Settings`.
3. Create a client in `SignoffFlow > Clients`.
4. Assign one or more WordPress users to that client.
5. Create updates in `SignoffFlow > Updates`.
6. Upload downloadable files in `SignoffFlow > Files`.
7. Visit the portal page as an assigned client user to view updates and files.

## Repository Conventions

- Public brand/UI naming uses `SignoffFlow`.
- Package-level identifiers use `client-approval-workflow`.
- Code-facing identifiers use the `cliapwo` prefix.
- PHP namespace uses `ClientApprovalWorkflow\`.

## Known Limitation

Files are currently stored as WordPress Media Library attachments and exposed to clients through a protected download endpoint.

The protected endpoint enforces:

- logged-in access
- nonce verification
- client assignment checks

However, direct attachment URLs may still be accessible if someone knows the raw media URL.

Planned mitigation:

- move protected files to a non-public or server-protected storage path in a later milestone

## Security Notes

- All privileged admin mutations should use capability checks and nonces.
- Portal access is based on client-user assignment plus `cliapwo_view_portal`.
- Staff/admin actions use `cliapwo_manage_portal`.

## License

GPL v2 or later.

## Planning

The milestone plan lives in `PLAN.md`.
