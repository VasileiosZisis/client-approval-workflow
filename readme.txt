=== SignoffFlow - Client Approval Workflow & Client Portal  ===
Contributors: vzisis
Tags: client-portal, agency, file-sharing, project-management, client-communication
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Client portal for agencies: share updates, files, and client requests privately.

== Description ==

SignoffFlow is a **client portal** plugin for agencies, freelancers, and service teams.

Create a private portal workspace per client account where you can:
* Share **project updates** (timeline)
* Share **files** with protected downloads
* Send **client requests/tasks** and track completion

Portal access is restricted to WordPress users assigned to a client account, plus staff users with management capability.

This plugin only outputs front-end content on the portal page via the `[cliapwo_portal]` shortcode.

=== Use cases ===
* Collect client confirmations on deliverables and tasks
* Keep client communication out of email threads
* Share files securely with per-client access control
* Provide a branded agency client portal experience

== Getting started ==
1. Go to `SignoffFlow > Settings`.
2. Use the `Quick setup` panel to create a sample portal page, or create a page manually and add `[cliapwo_portal]`.
3. Confirm that page is selected as the portal page in SignoffFlow settings.
4. Create a client account in `SignoffFlow > Clients` and assign one or more WordPress portal users.
5. Add updates, files, and requests for that client account.
6. Log in as an assigned portal user to view the portal and complete requests.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it as a zip in WordPress.
2. Activate **SignoffFlow** through the WordPress Plugins screen.
3. Go to `SignoffFlow > Settings`.
4. Use the optional `Quick setup` panel to create a sample portal page automatically, or create your own page and add the `[cliapwo_portal]` shortcode.
5. Confirm the portal page is selected in SignoffFlow settings.
6. Create a client account in `SignoffFlow > Clients`.
7. Assign one or more WordPress portal users to that client account.
8. Add updates, files, and requests for that client account.

== Frequently Asked Questions ==

= Who can see a client portal? =

Only WordPress users assigned to that client account, plus staff users with the `cliapwo_manage_portal` capability.

= How are files protected? =

Clients receive protected download links that go through an access-checked endpoint. Files are stored in a dedicated `cliapwo-private` uploads subdirectory instead of standard public Media Library URLs, and the portal UI does not expose raw file paths.

Apache hardening files are created automatically for that directory. Nginx hosts may still need an equivalent deny rule added at the server level.

= Does the plugin send email notifications? =

Yes. SignoffFlow can send request, update, and file notifications with `wp_mail()` to all WordPress users assigned to the related client account. Notification types can be toggled in `SignoffFlow > Settings`.

= Can I test notifications on a local site? =

Yes, but local mail delivery depends on your environment. SignoffFlow records `Email attempt` entries in the Event Log for each notification. If WordPress cannot confirm delivery, SignoffFlow also shows a dismissible admin notice on its own screens so you can check the Event Log and review your mail transport.

The Notifications settings screen also includes an Email delivery help section with a simple test flow and recommendations for Mailpit, MailHog, SMTP, Postmark, and Mailtrap.

= Can developers customize the portal styling? =

Yes. The portal uses a stable root wrapper (`.cliapwo-portal`), documented CSS variables, and a small set of filters for wrapper classes, section classes, and inline style variables.

For installed sites, see the Portal styling help note in `SignoffFlow > Settings`. Customizations should be added from a theme or site-specific plugin rather than by editing SignoffFlow directly.

== Screenshots ==

1. Client portal dashboard with Action required
2. Updates timeline inside the client portal
3. Files area with protected client downloads
4. Requests checklist with client completion actions
5. SignoffFlow settings and notification toggles
6. Event Log showing audit and email-attempt entries

== Changelog ==

= 1.0.0 =

Initial release.
