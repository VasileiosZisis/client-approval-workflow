# SignoffFlow

SignoffFlow is a WordPress plugin for running a private client portal inside WordPress.

It is built for agencies, freelancers, and service businesses that need a simple place to share updates, files, and requests with clients without relying on scattered email threads.

## What The Plugin Does

The plugin includes:

- private client portal access using standard WordPress users
- client account management inside WordPress admin
- updates timeline for client-facing progress posts
- protected file sharing with access-checked downloads
- requests/tasks that clients can approve, reject, block, or request changes for with a short response note
- latest client response details in the portal and WordPress admin, including the responder and timestamp
- immutable per-request activity histories that preserve earlier approval outcomes and response notes
- clear status badges and free admin filtering for approval requests
- state-aware first-run setup that tracks progress through the first real client response
- focused, responsive portal workspace with section navigation and accessible motion
- event logging for key actions
- email notifications for requests, updates, and files
- branding options for the portal experience

## How It Works

1. Install and activate the plugin in WordPress.
2. Follow the state-aware setup progress in `SignoffFlow > Settings`.
3. Create or choose a published portal page and place the `[cliapwo_portal]` shortcode on it.
4. Create a client account in `SignoffFlow > Clients` and assign a portal user.
5. Publish an approval request for that client account.
6. The assigned user logs in with normal WordPress authentication and records a response from the portal.

The page selected in SignoffFlow settings uses the plugin's focused portal canvas, without the active theme's public header and footer. Shortcodes placed on other pages remain embedded in the theme as normal.

## Repository Notes

This repository contains the plugin source code.

Repository/package details:

- plugin folder: `signoffflow-client-approval-workflow`
- main plugin file: `client-approval-workflow.php`
- text domain: `signoffflow-client-approval-workflow`
- namespace: `Vzisis\\ClientApprovalWorkflow\\`
- code prefix: `cliapwo`

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Local Development

1. Place this repository in `wp-content/plugins/signoffflow-client-approval-workflow`.
2. Run `composer install`.
3. Activate the plugin in WordPress.
4. Configure the portal page in `SignoffFlow > Settings`.

Useful commands:

- `composer run lint`
- `composer run lint:fix`

## Notes

- Protected files are stored in a dedicated `cliapwo-private` uploads directory and are served through the plugin download handler.
- Email delivery depends on the site's WordPress mail transport or SMTP configuration.

## License

GPL v2 or later.
