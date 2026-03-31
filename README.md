# SignoffFlow

SignoffFlow is a WordPress plugin for running a private client portal inside WordPress.

It is built for agencies, freelancers, and service businesses that need a simple place to share updates, files, and requests with clients without relying on scattered email threads.

## What The Plugin Does

The plugin includes:

- private client portal access using standard WordPress users
- client account management inside WordPress admin
- updates timeline for client-facing progress posts
- protected file sharing with access-checked downloads
- requests/tasks that clients can complete from the portal
- event logging for key actions
- email notifications for requests, updates, and files
- branding options for the portal experience

## How It Works

1. Install and activate the plugin in WordPress.
2. Create or choose a portal page and place the `[cliapwo_portal]` shortcode on it.
3. Create a client account in `SignoffFlow > Clients`.
4. Assign one or more WordPress users as portal users for that client account.
5. Add updates, files, and requests for that client account.
6. Assigned users log in with normal WordPress authentication and access only their portal experience.

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

- `composer lint`
- `composer lint:fix`

## Notes

- Protected files are stored in a dedicated `cliapwo-private` uploads directory and are served through the plugin download handler.
- Email delivery depends on the site's WordPress mail transport or SMTP configuration.

## License

GPL v2 or later.
