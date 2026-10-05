=== SignoffFlow – Client Approval Workflow & Client Portal ===
Contributors: vzisis
Tags: client portal, approval, agency, file sharing, workflow
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Client portal and approval workflow for agencies. Share files and updates, request approvals, and track every client sign-off.

== Description ==

SignoffFlow is a WordPress client portal and client approval workflow plugin for agencies, freelancers, and service teams.

Create a private workspace for each client where you can share project updates, provide protected file downloads, send approval requests, collect client feedback, and keep a clear history of every approval decision.

Instead of chasing approvals through scattered email threads, SignoffFlow gives your team and clients one place to see what needs attention, what has been approved, and what still needs changes.

= A private client portal inside WordPress =

Create a dedicated portal workspace for each client account.

Clients can use their private workspace to:

* View project updates.
* Download protected files.
* Review approval requests.
* Approve work.
* Request changes.
* Reject a request.
* Mark a request as blocked.
* Add response notes.
* Review their request activity.
* See request deadlines and which open requests are overdue or due soon.

Portal access is restricted to WordPress users assigned to the relevant client account, plus staff users with the SignoffFlow management capability.

Each client only gets access to the workspace assigned to them.

= Stop chasing client approvals through email =

SignoffFlow turns client approval into a structured workflow.

Create an approval request when you need a client to review a deliverable, decision, task, or project milestone.

The client can respond with one of four outcomes:

* Approved.
* Changes requested.
* Rejected.
* Blocked.

A response note is optional when approving and required when the client chooses Changes requested, Rejected, or Blocked.

This helps your team understand not only whether something was approved, but also what needs to happen next.

= Keep a complete client approval history =

Every real approval status transition is preserved in an immutable per-request activity history.

The request history can include:

* Request creation.
* Client approval responses.
* Response notes.
* Response timestamps.
* The responding user.
* Staff reopen actions.
* Staff status changes.
* Staff due-date changes, including previous and new dates.
* Earlier response-note snapshots from previous approval cycles.

This gives agencies and service teams a clearer record of how each client approval progressed over time.

The latest response is displayed prominently, while earlier activity remains available in the request history.

= Track approval status from WordPress admin =

The Requests screen gives staff a central view of client approval activity.

Requests include clear status badges and can be filtered by:

* Open.
* Approved.
* Changes requested.
* Rejected.
* Blocked.

Use the filters to quickly find requests that still need action or review previously completed client approvals.

= Request due dates and attention =

Set an optional due date in Request Details. Deadlines use the WordPress site timezone and remain separate from approval status.

Open requests become overdue after their due day has ended. Due soon includes today through seven calendar days ahead. The portal shows urgency counts and places overdue requests first, followed by due-soon requests and other open requests.

Staff can sort the Requests list by due date and combine the existing status filter with Due soon, Overdue, or No due date. Undated requests remain visible and sort last when ordering by date.

Due-date edits are preserved in Activity History. Resolving or reopening a request keeps its deadline. New-request notification emails include the due date when present; changing a date does not send another email or schedule a reminder.

= Share project updates with clients =

Each client portal includes an updates timeline.

Use project updates to keep clients informed about:

* Project progress.
* Completed work.
* Important milestones.
* Upcoming actions.
* Delivery information.
* Other client-facing announcements.

Keeping updates inside the client portal reduces the need to reconstruct project history from long email threads.

= Private client file sharing =

SignoffFlow includes protected file sharing for client accounts.

Files are stored in a dedicated private uploads subdirectory rather than being exposed as normal public WordPress Media Library URLs.

Client downloads pass through an access-checked endpoint so SignoffFlow can verify that the current user is allowed to access the file.

The portal interface does not expose raw private file paths.

Apache hardening files are created automatically for the private directory.

Nginx servers may require an equivalent deny rule at the server configuration level.

= Built for agencies and freelancers =

SignoffFlow is designed for businesses that regularly need clients to review work, download deliverables, or provide formal approval feedback.

Typical uses include:

**Web design and development agencies**

Send designs, development milestones, staging reviews, or launch decisions to clients for approval.

**Marketing agencies**

Share campaign deliverables, creative work, reports, content, and other materials that require client feedback.

**Freelancers**

Give clients a focused workspace instead of managing deliverables and approvals across multiple email conversations.

**Creative and service teams**

Request approval for files, project stages, tasks, deliverables, or other work that needs a documented client response.

= Account-based client access =

SignoffFlow uses WordPress user accounts for portal access.

To give someone access:

1. Create or use a WordPress user.
2. Create a client account in SignoffFlow.
3. Assign the WordPress user to that client account.
4. Give the client the portal page URL.
5. The user logs in and sees the workspace assigned to their account.

Staff users with the appropriate SignoffFlow capability can manage client portal content.

This account-based approach keeps client workspaces separate and access controlled.

= Email notifications =

SignoffFlow can send WordPress email notifications when relevant client activity is created.

Notification types include:

* Approval requests.
* Project updates.
* Client files.

Notifications are sent with WordPress `wp_mail()` to users assigned to the related client account.

Individual notification types can be enabled or disabled under SignoffFlow > Settings.

SignoffFlow also records email-attempt entries in its Event Log so administrators can review notification activity.

= Email delivery troubleshooting =

Email delivery depends on the WordPress site's mail configuration and hosting environment.

If WordPress cannot confirm that a notification was handed off successfully, SignoffFlow displays a dismissible admin notice on its own screens and records the attempt in the Event Log.

The Notifications settings include email-delivery guidance for local development and production environments.

= Guided setup and onboarding =

SignoffFlow includes a state-aware onboarding panel that detects setup progress automatically.

The onboarding flow guides administrators through milestones such as:

* Creating the client portal page.
* Creating a client account.
* Assigning a portal user.
* Adding client-facing content.
* Receiving the first real client response.

The setup panel provides direct next-step actions based on the current site state.

Onboarding can be dismissed per administrator and reopened later.

= Optional sample workflow =

Want to see how SignoffFlow works before creating real client content?

Administrators can create an optional sample workflow containing clearly labeled example content.

The sample workflow includes:

* A sample client.
* A sample project update.
* A sample approval request.
* Example request-history activity.
* A signed staff-only preview.

The sample workflow does not create a demo WordPress user, upload a file, send email notifications, trigger tracking, or count toward real onboarding progress.

Sample content can be repaired if necessary and permanently removed using marker-validated cleanup.

= A focused client portal experience =

The page selected as the SignoffFlow portal uses a focused, responsive portal canvas rather than displaying the active theme's normal public header and footer.

This gives clients a workspace designed around portal actions and content.

If you place SignoffFlow shortcodes on other WordPress pages, those pages continue to use the normal active-theme layout.

= Customize portal styling =

Developers can customize the client portal without editing SignoffFlow directly.

The portal provides:

* A stable `.cliapwo-portal` root wrapper.
* Documented CSS variables.
* Filters for wrapper classes.
* Filters for section classes.
* Filters for inline style variables.

Site-specific customizations should be added through a theme or custom plugin so they are not lost when SignoffFlow is updated.

= Key features =

* WordPress client portal.
* Client approval workflow.
* Private workspace per client account.
* WordPress user-based client access.
* Project update timeline.
* Protected client file sharing.
* Access-checked file downloads.
* Client approval requests.
* Approved status.
* Changes requested status.
* Rejected status.
* Blocked status.
* Client response notes.
* Required notes for non-approval outcomes.
* Latest response details.
* Immutable approval activity history.
* Client-safe request timeline.
* Complete request history for staff.
* Approval status badges.
* Approval status filtering.
* WordPress email notifications.
* Configurable notification types.
* Email-attempt Event Log.
* State-aware onboarding.
* Per-administrator onboarding dismissal.
* Optional sample workflow.
* Staff-only sample preview.
* Exact sample-content cleanup.
* Responsive portal layout.
* Developer styling hooks and CSS variables.

= What SignoffFlow does not do =

SignoffFlow focuses on private client communication, files, and approval workflows.

It does not require clients to interact with the WordPress admin area to review their portal content.

It does not create public Media Library links for protected SignoffFlow files.

It does not treat an unchanged request save as a new approval-history event.

It does not automatically create demo users or send demo emails when sample content is created.

== Screenshots ==

1. Private client portal dashboard showing items that require the client's attention.
2. Client-facing project updates timeline for keeping communication and progress information in one place.
3. Protected client file area with access-controlled downloads.
4. Approval requests with Approved, Changes requested, Rejected, and Blocked outcomes, response notes, and activity history.
5. State-aware onboarding checklist showing detected setup progress and the next recommended action.
6. Event Log showing approval activity.
7. Optional sample workflow with staff preview, repair tools, and exact sample-content cleanup.

== Installation ==

1. Install SignoffFlow from Plugins > Add New, or upload the plugin folder to `/wp-content/plugins/`.
2. Activate SignoffFlow through the WordPress Plugins screen.
3. Go to SignoffFlow > Settings.
4. Use the state-aware setup panel to create a portal page automatically, or create a page manually and add the `[cliapwo_portal]` shortcode.
5. Confirm that the page is selected as the portal page in SignoffFlow settings.
6. Create a client account under SignoffFlow > Clients.
7. Assign one or more WordPress users to the client account.
8. Add project updates, protected files, and approval requests for the client.
9. Log in as an assigned portal user to view the client workspace and respond to requests.

You can optionally create the sample workflow from the setup screen to preview the approval experience before adding real client content.

== Frequently Asked Questions ==

= What is a client approval portal? =

A client approval portal gives clients a private place to review work, access project information, and respond to approval requests.

SignoffFlow creates these client workspaces inside WordPress.

= Can clients approve work directly through WordPress? =

Yes.

Assigned client users can log in to their private SignoffFlow portal and respond to approval requests.

They do not need to manage requests through the WordPress admin area.

= What approval options can clients choose? =

Clients can respond with:

* Approved.
* Changes requested.
* Rejected.
* Blocked.

A short response note is optional for Approved and required for Changes requested, Rejected, and Blocked.

= Does SignoffFlow keep a history of client approvals? =

Yes.

Each real approval status transition is stored in an immutable per-request activity history.

This can include request creation, client responses, response-note snapshots, staff reopen actions, and staff status changes.

= Can I see who approved a request and when? =

Yes.

The latest response, responding user, response time, and response note are displayed in the portal and request administration screen.

Earlier approval activity remains available in the request history.

= Can clients request changes instead of approving? =

Yes.

Clients can choose Changes requested and provide a required response note explaining what needs to change.

They can also choose Rejected or Blocked when those outcomes better describe the situation.

= Is SignoffFlow suitable for agencies and freelancers? =

Yes.

SignoffFlow is designed for agencies, freelancers, and service teams that need to share work with clients and collect clear approval decisions.

It can be used for websites, design work, marketing deliverables, project milestones, content, files, and other client-facing work.

= Can I use SignoffFlow as a WordPress client portal? =

Yes.

Each client account gets a private portal workspace containing relevant updates, files, and approval requests.

Access is restricted to WordPress users assigned to that client account and authorized staff.

= Who can see a client portal? =

Only WordPress users assigned to the relevant client account, plus staff users with the `cliapwo_manage_portal` capability.

= Does each client only see their own information? =

Portal access is tied to the client account assigned to the logged-in WordPress user.

Users only receive access to the client workspace they are authorized to view, while authorized staff can manage portal content.

= Do clients need a WordPress user account? =

Yes.

SignoffFlow uses account-based access. A client portal user must have a WordPress user account assigned to the relevant SignoffFlow client account.

= Can I securely share files with clients? =

Yes.

SignoffFlow stores client files in a dedicated private uploads subdirectory and serves downloads through an access-checked endpoint.

The portal interface does not expose raw private file paths.

= Are SignoffFlow files stored in the normal public Media Library? =

Protected SignoffFlow files use a dedicated private uploads location instead of normal public Media Library URLs.

Apache hardening files are created automatically for that directory.

Nginx environments may require an equivalent server-level deny rule.

= Can I send email notifications to clients? =

Yes.

SignoffFlow can send request, update, and file notifications through WordPress `wp_mail()` to users assigned to the relevant client account.

Notification types can be toggled under SignoffFlow > Settings.

= Can I test notifications on a local WordPress site? =

Yes, but actual email delivery depends on the local development environment.

SignoffFlow records email-attempt entries in the Event Log and includes email-delivery guidance in the Notifications settings.

Tools such as Mailpit, MailHog, SMTP services, Postmark, or Mailtrap can be used depending on your development setup.

= Can I filter approval requests by status? =

Yes.

The Requests administration screen includes status filters for:

* Open.
* Approved.
* Changes requested.
* Rejected.
* Blocked.

= What happens when an approved request needs to be reopened? =

Staff can reopen a request.

The previous client response remains preserved in the request activity history rather than being discarded.

= Can I preview SignoffFlow before setting up a real client? =

Yes.

Administrators can explicitly create an optional sample workflow and preview it as staff.

The sample workflow does not create a demo user, upload files, send emails, trigger tracking, or falsely complete real onboarding milestones.

It can later be removed using the built-in cleanup controls.

= Does SignoffFlow send data to an external SignoffFlow service? =

SignoffFlow operates within your WordPress installation.

Email notifications use your WordPress site's configured `wp_mail()` delivery mechanism.

= Can developers customize the portal design? =

Yes.

The portal exposes a stable `.cliapwo-portal` wrapper, documented CSS variables, and filters for wrapper classes, section classes, and inline style variables.

Add customizations through your theme or a site-specific plugin rather than editing SignoffFlow directly.

= Can I use the portal shortcode on another page? =

Yes.

The configured SignoffFlow portal page uses the focused portal canvas.

Shortcodes added to other WordPress pages remain embedded in the site's normal active-theme layout.

== Changelog ==

= 1.7.0 =

* Show a compact, per-administrator dismissible due-date release notice on SignoffFlow Settings and the Requests list for upgraded installations; fresh installations retain first-run onboarding.
* Added optional request due dates using the WordPress site calendar and timezone.
* Added due-date sorting and combined due-state/status filtering in the Requests list.
* Added portal urgency counts and attention ordering that prioritizes older overdue requests before the display limit.
* Added immutable due-date-change events and initial-date snapshots in request history.
* Included due dates in new-request emails without adding reminders or date-change notifications.

= 1.6.1 =

* Added a locally runnable WordPress integration-test suite covering request lifecycles, immutable history, legacy response migration, cross-client authorization, protected file paths, onboarding, and sample content.
* Added PHP 7.4 compatibility analysis and a disposable WordPress/PHP test matrix for WordPress 6.0, WordPress 7.0, and the latest maintained release.
* Audited privileged handlers, portal accessibility, responsive layouts, translation behavior, and release metadata without changing existing workflows or stored data.

= 1.6.0 =

* Added explicitly opt-in sample client, update, and approval-request content with a signed staff preview.
* Added idempotent repair and marker-validated permanent cleanup without creating users, files, emails, tracking, or onboarding progress.
* Added non-notifying sample event entries so the example request includes its creation history.

= 1.5.0 =

* Replaced the static quick-setup checklist with five automatically detected onboarding milestones.
* Added direct next-step actions, accessible progress presentation, and a concise completion state after the first client response.
* Added per-administrator dismissal and reopening without site-wide notices or external tracking.
* Reserved a sample-content marker so future demo records cannot falsely complete real onboarding progress.

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
* Existing completed requests remain compatible and are shown as Approved for clearer approval-workflow language.

= 1.0.0 =

* Initial release.

== Upgrade Notice ==

= 1.7.0 =

Add optional request deadlines, urgency ordering, due-state filters, and immutable due-date history. Existing requests remain undated until staff set a deadline. No migration or scheduled reminders are required.
