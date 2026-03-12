# Release Checklist

## Version

- Current plugin version: `0.2.0`
- Main plugin file: `client-approval-workflow.php`
- WordPress.org readme stable tag: `0.2.0`

## Pre-release validation

- Run `composer lint`
- Run PHP lint across all plugin PHP files
- Confirm `uninstall.php` exists and is non-destructive by default
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
9. Verify Event Log entries are created.
10. If mail transport is configured, verify request, update, and file notifications.

## Known issues

- Files are still stored as normal Media Library attachments, so direct raw attachment URLs may still be reachable if someone knows them.
- `wp_mail()` behavior still depends on the site’s mail transport; local environments may only show Event Log and admin debug notices.
- This release pass does not include a generated POT file yet.
