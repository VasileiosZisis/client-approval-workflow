# Release Checklist

## Version

- Current plugin version: `0.2.0`
- Main plugin file: `client-approval-workflow.php`
- WordPress.org readme stable tag: `0.2.0`

## Pre-release validation

- Run `composer lint`
- Run PHP lint across all plugin PHP files
- Confirm `uninstall.php` exists and is non-destructive by default unless the opt-in cleanup setting is enabled
- Confirm `readme.txt` title, stable tag, and changelog match the plugin version

## Manual smoke test

1. Activate the plugin on a clean WordPress install.
2. Open `SignoffFlow > Settings` and save settings successfully.
3. Create a client and assign a WordPress user.
4. Create a portal page with `[cliapwo_portal]`.
5. Create a request, update, and file for that client.
6. Verify the assigned user can view the portal and only their own portal.
7. Verify request completion works from the portal.
8. Verify file download access control works.
9. Verify uploaded files land under `wp-content/uploads/cliapwo-private/`.
10. Verify Event Log entries are created.
11. If mail transport is configured, verify request, update, and file notifications.

## Known issues

- Protected files now live in `wp-content/uploads/cliapwo-private/`, but Nginx hosts may still require a matching deny rule because Nginx does not honor `.htaccess`.
- `wp_mail()` behavior still depends on the site's mail transport; local environments may only show Event Log and admin debug notices.
- This release pass does not include a generated POT file yet.
