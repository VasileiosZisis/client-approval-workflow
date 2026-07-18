=== SignoffFlow - Client Approval Workflow & Client Portal ===
Contributors: vzisis
Tags: client-portal, approvals, workflow, agency, file-sharing
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Client portal for agencies: share updates, files, and approval requests privately.

== Description ==

SignoffFlow is a client portal and approval workflow plugin for agencies, freelancers, and service teams.

Create a private portal workspace per client account where you can:
* Share project updates (timeline)
* Share files with protected downloads
* Send client requests/tasks, track approval outcomes, and collect short response notes
* Review an immutable activity history for every approval request
* Scan and filter approval requests by status in WordPress admin

Portal access is restricted to WordPress users assigned to a client account, plus staff users with management capability.

The page selected in SignoffFlow settings uses a focused, responsive portal canvas without the active theme's public header and footer. Shortcodes placed on other pages remain embedded in the theme as normal.

= Use cases =
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
6. Log in as an assigned portal user to view the portal and respond to requests.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it as a zip in WordPress.
2. Activate SignoffFlow through the WordPress Plugins screen.
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
Yes, but local mail delivery depends on your environment. SignoffFlow records Email attempt entries in the Event Log for each notification. If WordPress cannot confirm delivery, SignoffFlow also shows a dismissible admin notice on its own screens so you can check the Event Log and review your mail transport.

The Notifications settings screen also includes an Email delivery help section with a simple test flow and recommendations for Mailpit, MailHog, SMTP, Postmark, and Mailtrap.

= Can developers customize the portal styling? =
Yes. The portal uses a stable root wrapper (`.cliapwo-portal`), documented CSS variables, and a small set of filters for wrapper classes, section classes, and inline style variables.

For installed sites, see the Portal styling help note in `SignoffFlow > Settings`. Customizations should be added from a theme or site-specific plugin rather than by editing SignoffFlow directly.

= How do client response notes work? =
Clients can add a response note of up to 500 characters when choosing an approval outcome. A note is optional for Approved and required for Changes requested, Rejected, and Blocked.

The latest response, responder, and response time are shown in the client portal and request admin screen. Each real status transition is also preserved in an immutable per-request activity history, including response-note snapshots from earlier approval cycles.

= Can I filter requests by approval status? =
Yes. The Requests admin screen includes status badges and filters for Open, Approved, Changes requested, Rejected, and Blocked requests.

== Screenshots ==

1. Client portal dashboard with Action required
2. Updates timeline inside the client portal
3. Files area with protected client downloads
4. Requests checklist with client approval outcomes, response notes, and clear status actions
5. SignoffFlow settings and notification toggles
6. Event Log showing audit and email-attempt entries

== Changelog ==

= 1.4.0 =
* Added immutable per-request activity histories for request creation, client responses, staff reopen actions, and staff status changes.
* Added a collapsed client-safe portal timeline and a complete request history in WordPress admin.
* Added a bounded migration that preserves one reliable response from existing latest-response metadata without duplicating events.
* Centralized and serialized status transitions so unchanged saves do not create history entries.

= 1.3.0 =
* Added color-coded request status badges to the Requests list and request edit screen.
* Added admin filtering for Open, Approved, Changes requested, Rejected, and Blocked requests.
* Included legacy completed requests in the Approved filter and requests without stored status in the Open filter.
* Improved portal action hierarchy, keyboard focus, translated-label wrapping, and mobile button layout.

= 1.2.0 =
* Revised the client portal page with a focused, responsive workspace, section navigation, account controls, and accessible motion.
* Added short client response notes to approval requests.
* Required an explanatory note for Changes requested, Rejected, and Blocked outcomes while keeping notes optional for Approved.
* Added the latest response outcome, note, responder, and timestamp to the client portal and request admin screen.
* Preserved the previous client response when staff reopen a request.

= 1.1.0 =
* Added richer approval outcomes for requests: Approved, Changes requested, Rejected, and Blocked.
* Existing completed requests remain compatible and are shown as Approved for clearer approval workflow language.
* Tested with WordPress 7.0.

= 1.0.0 =
Initial release.

== Upgrade Notice ==

= 1.4.0 =
Requests now preserve each approval cycle in an immutable activity history. One reliable existing latest response is migrated automatically in small batches.

= 1.3.0 =
Requests now include clearer status badges, admin status filtering, and improved portal action styling. Existing request data remains compatible.

= 1.2.0 =
Clients can now include short response notes with approval outcomes. Existing requests remain compatible and require no migration.

= 1.1.0 =
Requests now support richer approval outcomes. Existing completed requests remain compatible and are shown as Approved.

= 1.0.0 =
Initial release.
