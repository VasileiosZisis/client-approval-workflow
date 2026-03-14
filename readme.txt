=== Client Approval Workflow & Client Portal for WordPress - SignoffFlow ===
Contributors: vzisis
Tags: client approval workflow, client portal, approvals, agency client portal, file sharing
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SignoffFlow is a client approval workflow and client portal plugin for WordPress that gives service businesses a private place to share updates, files, and requests with each client.

== Description ==

SignoffFlow helps agencies, freelancers, and service teams keep client communication organized inside WordPress.

Core features in the current free plugin:

* Private client portal access tied to assigned WordPress users
* Client updates timeline
* Protected file downloads through a permission-checked endpoint
* Client requests/tasks with completion tracking
* Event log for update, file, and email-attempt activity
* Email notifications for new requests, updates, and uploaded files
* Basic branding settings for logo and primary color

This repository build keeps the admin experience WordPress-native and focuses on secure access control, capability checks, nonces, and minimal theme-compatible portal output.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it as a zip in WordPress.
2. Activate **SignoffFlow** through the WordPress Plugins screen.
3. Go to `SignoffFlow > Settings`.
4. Choose a portal page and place the `[cliapwo_portal]` shortcode on that page.
5. Create a client in `SignoffFlow > Clients`.
6. Assign one or more WordPress users to that client.
7. Add updates, files, and requests for that client.

== Frequently Asked Questions ==

= Who can see a client portal? =

Only WordPress users assigned to that client, plus staff users with the `cliapwo_manage_portal` capability.

= How are files protected? =

Clients receive protected download links that go through an access-checked endpoint. Files are stored in a dedicated `cliapwo-private` uploads subdirectory instead of standard public Media Library URLs, and the portal UI does not expose raw file paths.

Apache hardening files are created automatically for that directory. Nginx hosts may still need an equivalent deny rule added at the server level.

= Does the plugin send email notifications? =

Yes. SignoffFlow can send request, update, and file notifications with `wp_mail()` to all WordPress users assigned to the related client. Notification types can be toggled in `SignoffFlow > Settings`.

= Can I test notifications on a local site? =

Yes, but local mail delivery depends on your environment. SignoffFlow also records `Email attempt` entries in the Event Log and shows a one-time admin debug notice after a notification attempt so you can verify the flow locally.

The Notifications settings screen also includes an Email delivery help section with a simple test flow and recommendations for Mailpit, MailHog, SMTP, Postmark, and Mailtrap.

== Screenshots ==

1. Client portal dashboard with Waiting on you summary
2. Updates timeline inside the client portal
3. Files area with protected client downloads
4. Requests checklist with client completion actions
5. SignoffFlow settings and notification toggles
6. Event Log showing audit and email-attempt entries

== Changelog ==

= 0.2.0 =

* Prepared the first release-ready package for SignoffFlow
* Added release packaging docs and uninstall handling
* Added request, update, and file notifications with event-log visibility
* Added Pro extension hooks, approvals schema, and Pro detection helper

= 0.1.0 =

* Initial public milestone build of SignoffFlow
* Added plugin settings, capabilities, client management, portal rendering, updates, files, requests, event log, and notifications for requests, updates, and files

== Upgrade Notice ==

= 0.2.0 =

Release-ready packaging update with final milestone features, uninstall handling, and documentation improvements.

= 0.1.0 =

Initial release.
