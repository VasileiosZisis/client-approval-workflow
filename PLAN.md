# SignoffFlow — Milestone Plan (Agent-Ready)

> Purpose: Build a WordPress plugin that provides a **client-facing portal** for service businesses (initially **agencies/freelancers**) with a **freemium** core and clear Pro upgrade path (**Approvals**, automation, white‑label, reporting).

---

## A) Naming, Prefix, and WordPress.org SEO Decisions

### A1) Product naming

- **Brand name:** SignoffFlow
- **Short descriptor:** Client Approval Workflow & Client Portal
- **Recommended WordPress.org listing title (readme.txt Title):**
  - **Preferred:** `Client Approval Workflow & Client Portal for WordPress – SignoffFlow`
  - **Alternate (shorter):** `Client Approval Workflow & Client Portal – SignoffFlow`

> Agent note: On WP.org, the **readme.txt Title** heavily influences search ranking, so keep the key terms in the title and the first paragraph.

### A2) Keyword targets (use naturally, avoid stuffing)

Primary:

- client approval workflow
- client portal
- approvals / sign-off

Secondary:

- agency client portal
- deliverables / handoff
- requests / tasks
- audit trail / activity log

### A3) Plugin package naming, text domain, namespace, and code prefix

- **Plugin folder/package name:** `client-approval-workflow`
- **Main plugin file:** `client-approval-workflow.php`
- **Desired plugin slug (WP.org):** `client-approval-workflow`
  - Alternatives if taken: `client-approval-workflow-portal`, `client-approval-workflow-signoffflow`
- **Text domain:** `client-approval-workflow` (match the actual plugin folder/package)
- **POT file:** `languages/client-approval-workflow.pot`
- **PHP namespace:** `ClientApprovalWorkflow\` (package-based; readable and consistent with filesystem naming)
- **Required code prefix (functions, constants, meta keys, option keys, hooks, CPT slugs, nonces, capability names):** `cliapwo`
  - Function examples: `cliapwo_get_client()`, `cliapwo_render_portal()`, `cliapwo_register_cpts()`
  - Option keys: `cliapwo_settings`
  - Meta keys: `cliapwo_client_id`, `cliapwo_assigned_users`
  - Hooks: `cliapwo_before_render_portal`, `cliapwo_after_update_created`
  - CPT slugs: `cliapwo_client`, `cliapwo_update`, `cliapwo_request`, `cliapwo_event`
  - Capability examples: `cliapwo_manage_portal`, `cliapwo_view_portal`
  - Nonce/action examples: `cliapwo_update_request_status`, `cliapwo_download_file`

### A4) Readme SEO structure (WP.org)

Agent should ensure `readme.txt` includes:

- Title line matching the chosen listing title.
- First paragraph naturally includes: **“client approval workflow”** and **“client portal”**.
- Headings that contain keywords naturally (Portal, Approvals, Workflow, Files, Requests).
- Screenshot captions aligned to the most important selling points:
  1. Client Portal dashboard (“Waiting on you”)
  2. Approvals queue / sign-off (Pro)
  3. Updates timeline
  4. Files library
  5. Requests checklist
  6. Branding / white-label (Pro)

### A5) Messaging consistency (UI copy)

Use **“Sign-off”** in sentence copy for readability, but keep **SignoffFlow** as the brand.
Standardize nouns (everywhere):

- **Approvals**
- **Requests**
- **Updates**
- **Files**
- **Activity log**

---

## 0) Scope

### In Scope (MVP → v1)

**Free (WordPress.org)**

- Private client portal pages (client-only access)
- Client “Updates” timeline (internal posts updates visible to the client)
- Basic file sharing per client (upload + download)
- Basic “Requests/Tasks” checklist per client (things needed from client)
- Client can mark requests complete
- Basic email notifications (new update, new file)
- Basic branding: logo + primary color
- Audit-lite: record key events (file upload, update posted) (can be simple logs)

**Pro (paid)**

- Approvals (request approval, approve/reject, notes, timestamps)
- Reminders & digests (scheduled emails)
- Advanced permissions (roles, override rules)
- Multi-projects per client (portals grouped by project/retainer)
- Client file uploads
- White-label (remove plugin branding, custom emails, templates)
- Reports dashboard (overdue approvals, response time, portal activity)
- Slack/Teams/webhook notifications (optional later)

### Out of Scope (Explicitly Not Building in v1)

- Full CRM (pipelines, deal stages)
- Invoicing/payment processing engine
- Time tracking
- Full project management (kanban, sprints)
- Real-time chat system
- Complex doc collaboration/editor
- WooCommerce-specific features/integrations
- Client comments on updates/files in v1

---

## 1) Product Goals

- Reduce client communication chaos by centralizing updates, files, and requests.
- Provide a clear free-to-pro upgrade path (approval workflow + automation + branding).
- Keep UX simple and WordPress-native (Gutenberg blocks, WP admin patterns).
- Be secure (capabilities, nonces, upload restrictions, access control).

---

## 2) Success Criteria (Testable)

### Functional

- [ ] Admin can create a Client and assign one or more WP users as client users.
- [ ] Client users can only view their own portal content.
- [ ] Admin/staff can post an Update that appears in client portal timeline.
- [ ] Staff can upload a file to a client portal; client can download it.
- [ ] Staff can add “Requests” items; client can mark items complete.
- [ ] Notification emails send for new updates/files (configurable).
- [ ] Plugin uses secure capability checks and nonces for all mutations.

### Non-functional

- [ ] No fatal errors on activation/deactivation.
- [ ] Works on latest WP stable + current LTS PHP version(s) (agent to confirm exact targets).
- [ ] Follows WP coding standards (PHPCS) where practical.
- [ ] Minimal performance impact; no heavy queries on frontend.

### WP.org SEO + Packaging (additional)

- [ ] `readme.txt` Title uses the selected SEO-friendly name.
- [ ] First readme paragraph contains “client approval workflow” + “client portal” naturally.
- [ ] Consistent text domain: `client-approval-workflow` across all translated strings.
- [ ] Screenshot captions reflect the keyword strategy (Approvals, Portal, Workflow).
- [ ] Main plugin file is `client-approval-workflow.php`.
- [ ] Plugin folder/package is `client-approval-workflow`.

---

## 3) Default Decisions (Do Not Re-ask Unless A Change Is Needed)

These are the default implementation decisions. The agent should proceed with them and only ask if a later change is truly needed.

1. **Multiple projects:** Pro only
   - Free/MVP assumes one portal per client.
2. **Client file uploads:** Pro only
   - Free/MVP is staff-upload to client; client upload is deferred to Pro.
3. **Request completion:** Clients can mark requests complete
   - Staff can reopen/override if needed.
4. **Storage model:** CPT-based
   - Prioritize speed of delivery and WordPress-native implementation.
5. **Portal rendering:** Shortcode first
   - Use shortcode now; Gutenberg block can be added later as a wrapper.
6. **Branding constraints**
   - Public plugin/brand name: `SignoffFlow`
   - Plugin folder/package: `client-approval-workflow`
   - Main plugin file: `client-approval-workflow.php`
   - Text domain: `client-approval-workflow`
   - Namespace: `ClientApprovalWorkflow\`
   - Prefix: `cliapwo`
   - Design style: clean, minimal, agency/B2B, neutral colors
7. **Client comments:** Not in v1
   - Avoid drifting toward helpdesk/ticketing.

---

## 4) Key Decisions (Agent must ask only if deviating from defaults)

Agent should confirm/ask before implementing only if it wants to deviate from the defaults above:

1. **Data model**
   - CPT-based (default) vs custom tables.
2. **Roles/capabilities**
   - Use built-in roles/caps or create custom caps like `cliapwo_manage_portal`, `cliapwo_view_portal`.
3. **File storage**
   - Use Media Library attachments with per-client access rules vs custom upload directory.
4. **UI approach**
   - Shortcode first (default) vs Gutenberg block first.
5. **Portal URL structure**
   - Single “Portal” page with dynamic content vs separate CPT archives.

> If any of these are unclear, agent should pause and ask you.

---

## 5) Milestones Overview

- **M1**: Plugin skeleton + settings foundation + capabilities
- **M2**: Client entity + assignments + portal access control
- **M3**: Portal frontend (dashboard) + Updates timeline
- **M4**: Files module (upload/download + access restrictions)
- **M5**: Requests/Tasks module (checklist)
- **M6**: Notifications (email) + event log
- **M7**: UX polish + security hardening + WP.org readiness (SEO included)
- **M8 (Pro stub)**: Approvals module scaffolding + upgrade hooks (no paid logic shipped in free)
- **M9**: QA, packaging, release

Each milestone includes:

- Deliverables
- Technical tasks
- Tests
- Suggested commits & messages
- Prompts for you to use with the coding agent

---

# M1 — Plugin Skeleton + Settings + Capabilities

## Deliverables

- Activatable plugin with admin menu + settings page
- Defined capabilities (staff vs client view)
- Basic configuration stored in options
- Naming/SEO foundations in place (plugin name, text domain, prefixes)

## Technical Tasks

- Create plugin base structure (namespacing, autoload or simple includes)
- Ensure:
  - Public plugin name displayed in WP admin: **SignoffFlow**
  - Main plugin file: `client-approval-workflow.php`
  - Plugin folder/package: `client-approval-workflow`
  - Text domain: `client-approval-workflow`
  - Namespace: `ClientApprovalWorkflow\`
  - Code prefix: `cliapwo` for functions/constants/meta/options/hooks/CPT slugs/nonces/caps
- Register admin menu:
  - “SignoffFlow” top-level
  - Settings submenu
- Create settings:
  - Portal base page selection (optional for now)
  - Branding: logo URL/media ID, primary color
  - Email toggles: update notifications, file notifications (store even if not used yet)
- Define and register capabilities (on activation):
  - `cliapwo_manage_portal` (admin/staff)
  - `cliapwo_view_portal` (client users)
  - Map to roles (administrator default gets manage; a “Client” role optional gets view)
- Add deactivation cleanup strategy (do not delete data by default)

## Tests (manual)

- [ ] Activate plugin without errors
- [ ] Admin menu appears
- [ ] Settings save and persist
- [ ] Capability assignment works (admin has manage cap)
- [ ] Plugin main file name is `client-approval-workflow.php`
- [ ] Text domain loads as `client-approval-workflow`

## Suggested Git Commits

1. `chore: initialize client-approval-workflow plugin structure and bootstrap`
2. `feat: add admin menu and settings page`
3. `feat: add capabilities and activation hooks`

## Agent Prompt (copy/paste)

> Build Milestone M1 of SignoffFlow per the plan. Create a clean plugin skeleton with main file `client-approval-workflow.php`, plugin folder/package `client-approval-workflow`, admin menu + settings page, and activation hook to add capabilities (`cliapwo_manage_portal`, `cliapwo_view_portal`). Use text domain `client-approval-workflow`, namespace `ClientApprovalWorkflow\`, and prefix `cliapwo` for functions, constants, meta keys, option keys, hooks, CPT slugs, nonce actions, and capability names. Include basic security (nonces, capability checks). Provide manual test steps and note any decisions you need me to confirm.

---

# M2 — Client Entity + Assignments + Access Control

## Deliverables

- “Clients” admin screen
- Create/edit Client records
- Assign WP users to a Client
- Access control layer that limits portal content to assigned users

## Technical Tasks

- Implement Client as CPT: `cliapwo_client`
  - title = client name
  - meta: assigned user IDs (array), optional contact email, notes
- Admin UI:
  - client list table, add/edit screen
  - meta box to assign existing WP users
- Add helper functions:
  - get client by current user
  - check if user can view a client
- Ensure clients cannot access other clients’ portal data

## Tests (manual)

- [ ] Admin can create a client and assign a WP user
- [ ] Assigned user is recognized as “client user”
- [ ] Unassigned user cannot view that client (403 or redirect)
- [ ] Data persists

## Suggested Git Commits

1. `feat: add Client CPT and admin management`
2. `feat: implement client-user assignment and access checks`

## Agent Prompt

> Implement Milestone M2. Add a `cliapwo_client` CPT with admin UI and meta box to assign WP users to clients. Implement access control helpers: get client(s) for current user and verify access. Ensure all reads/writes are protected by capability checks and nonces. Provide manual test checklist.

---

# M3 — Portal Frontend (Dashboard) + Updates Timeline

## Deliverables

- A portal page clients can visit to see:
  - Welcome header + client name
  - “Waiting on you” area (can be simple summary at first)
  - Updates timeline (staff-posted)
- Admin/staff can create updates linked to a client

## Technical Tasks

- Portal rendering approach:
  - **Default:** shortcode `[cliapwo_portal]`
  - Gutenberg block can come later as a wrapper around the same rendering logic
- Create Updates as CPT: `cliapwo_update`
  - meta: client_id, visibility (default client-only), author, created date
- Admin UI:
  - “Updates” submenu
  - Create update: pick client, title/body
- Frontend portal:
  - If not logged in: redirect to login
  - If logged in but not assigned: show “No portal assigned”
  - If assigned: show updates list (paged)
- Styling: minimal, theme-friendly (no heavy CSS)

## Tests (manual)

- [ ] Client sees only their updates
- [ ] Staff can create updates for a client
- [ ] Unauthorized user cannot see updates

## Suggested Git Commits

1. `feat: add portal shortcode and basic frontend layout`
2. `feat: add Updates CPT and admin UI`
3. `feat: render client updates timeline with access control`

## Agent Prompt

> Implement Milestone M3. Add portal shortcode `[cliapwo_portal]` that renders a client dashboard and updates timeline. Add `cliapwo_update` CPT with admin creation UI linked to a client via meta. Enforce strict access control so clients only see their own updates. Keep frontend styling minimal and theme-compatible.

---

# M4 — Files Module (Upload/Download + Access Restrictions)

## Deliverables

- Staff can upload files to a client portal
- Client can download files from their portal
- File access is protected (no direct URL leak without permission)

## Technical Tasks

- Decide storage approach (ask if unclear):
  - Option A: Media Library attachments + `cliapwo_client_id` meta + protected download endpoint
  - Option B: Custom directory + protected PHP download handler
- Implement file entity as:
  - attachment post type OR CPT referencing attachment ID
  - If CPT: `cliapwo_file`
  - meta: client_id, original filename, mime, size
- Upload UI:
  - staff-only upload from admin or portal staff view
- Frontend:
  - File list for client with download links
- Security:
  - Download via endpoint that checks access then streams file
  - Nonce/cap checks on upload/delete
  - Restrict allowed mime types (use WP allowed types)

## Tests (manual)

- [ ] Staff uploads file linked to a client
- [ ] Client downloads file successfully
- [ ] Another client cannot download (blocked)
- [ ] Direct attachment URL does not bypass access (or clearly documented limitation + mitigate via endpoint-only links)

## Suggested Git Commits

1. `feat: add file model and storage strategy`
2. `feat: implement protected download endpoint with access checks`
3. `feat: add file upload UI and portal file listing`

## Agent Prompt

> Implement Milestone M4. Add a Files module with staff uploads and client downloads. Use a protected download endpoint that enforces client assignment checks. Document any limitations (e.g., direct media URL access) and propose mitigations. Include nonces, capability checks, and mime restrictions.

---

# M5 — Requests/Tasks Module (Checklist)

## Deliverables

- Staff can add “requests” items for a client (e.g., “Send logo”, “Approve copy”)
- Client can mark items complete
- Staff can reopen/override a request status
- Display requests in portal

## Technical Tasks

- Implement requests as:
  - CPT `cliapwo_request` OR client meta storing list
  - Fields: client_id, title, status, due date (optional), created_by
- Portal UI:
  - list items with status
  - action for client to mark complete (nonce + access checks)
  - action for staff to reopen/override
- Admin UI:
  - manage requests for each client
  - bulk add (optional)

## Tests (manual)

- [ ] Staff adds a request
- [ ] Client sees it
- [ ] Client marks complete
- [ ] Staff can reopen/override
- [ ] Other clients cannot access/modify

## Suggested Git Commits

1. `feat: add Requests module data model`
2. `feat: render requests list in portal with status actions`
3. `feat: add admin UI to manage client requests`

## Agent Prompt

> Implement Milestone M5. Add Requests/Tasks tied to a client with secure status updates from the portal. Clients should be able to mark items complete. Staff should be able to reopen or override status. Provide admin UI and portal rendering.

---

# M6 — Notifications + Event Log

## Deliverables

- Email notifications on:
  - new update posted
  - new file uploaded
- Basic event log (for debugging + future reporting)
- Settings to toggle notifications

## Technical Tasks

- Add event dispatcher functions:
  - `cliapwo_event( 'update_created', $payload )`
  - `cliapwo_event( 'file_uploaded', $payload )`
- Store events:
  - lightweight custom table OR CPT `cliapwo_event` (agent choose and justify)
- Email templates:
  - use `wp_mail`
  - include portal link
  - allow admin to set “From name” (optional) and email footer
- Respect settings toggles

## Tests (manual)

- [ ] Create update triggers email to assigned client users
- [ ] Upload file triggers email
- [ ] Toggling off disables sending
- [ ] Event log entry created

## Suggested Git Commits

1. `feat: add event logging and event dispatcher`
2. `feat: send email notifications for updates and files`
3. `chore: add notification settings and template helpers`

## Agent Prompt

> Implement Milestone M6. Add event logging and email notifications for update creation and file uploads. Use wp_mail with simple templates and include a portal link. Respect notification settings toggles. Ensure emails go to all client-assigned users.

---

# M7 — UX Polish + Security Hardening + WP.org Readiness (SEO included)

## Deliverables

- Cleaner admin UX (meta boxes, notices, empty states)
- Strong access control everywhere
- Sanitization/escaping pass
- Basic i18n readiness
- Readme + plugin header + screenshots list (SEO-aware)

## Technical Tasks

- Review all endpoints/forms for:
  - capability checks
  - nonces
  - sanitization/validation
  - escaping in output
- Improve empty states:
  - “No updates yet”
  - “No files yet”
  - “No requests yet”
- Add settings for:
  - portal page selection and generated portal page helper (optional)
- Add translations scaffolding:
  - text domain, `load_plugin_textdomain` (text domain = `client-approval-workflow`)
- Write WP.org compliant `readme.txt` (agent draft) using the selected title:
  - Include “client approval workflow” + “client portal” in the opening paragraph naturally
  - Screenshot captions aligned to keyword strategy
- Confirm free version is WP.org-friendly (no aggressive upsells)

## Tests (manual)

- [ ] Run through portal as client with no data (no warnings)
- [ ] Attempt unauthorized actions (blocked)
- [ ] Settings persist and UI is intuitive
- [ ] Plugin passes basic WP.org sniff tests (best effort)

## Suggested Git Commits

1. `refactor: harden security checks and sanitize/escape outputs`
2. `feat: improve portal UX with empty states and notices`
3. `chore: add i18n scaffolding and WP.org readme`

## Agent Prompt

> Implement Milestone M7. Do a security/sanitization/escaping sweep, improve portal UX (empty states), add i18n scaffolding (text domain `client-approval-workflow`), and draft a WP.org `readme.txt` using the final listing title. Include the keywords “client approval workflow” and “client portal” naturally in the first paragraph. List any remaining risks/limitations and how you’d address them.

---

# M8 (Pro Stub) — Approvals Scaffolding + Upgrade Hooks (No paid logic shipped in free build)

## Deliverables

- Free plugin includes **hooks/filters** and placeholder UI spots for Pro features
- Approvals module designed but not fully implemented in free
- Clean extension points for Pro add-on plugin

## Technical Tasks

- Add action hooks like:
  - `do_action( 'cliapwo_before_render_portal', $client_id )`
  - `do_action( 'cliapwo_after_update_created', $update_id, $client_id )`
- Add feature flags:
  - `cliapwo_is_pro_active()` checks for Pro add-on presence (by class/function)
- Add “Approvals” section placeholder:
  - visible but shows “Upgrade to Pro to enable approvals” (must be WP.org compliant—no spammy nags)
- Define data structures for approvals:
  - approval request entity fields: subject, type (file/update/page), status, requested_by, decided_by, note, timestamps
- Design for future Pro-only multi-project and client-upload support

## Tests (manual)

- [ ] Free plugin runs without Pro installed
- [ ] If Pro installed (stub), hooks are called
- [ ] Upgrade prompts are minimal and compliant

## Suggested Git Commits

1. `feat: add extension hooks and pro detection helper`
2. `feat: add approvals scaffolding and upgrade placeholder UI`

## Agent Prompt

> Implement Milestone M8. Add extension hooks and a Pro detection helper (`cliapwo_is_pro_active`). Add an Approvals placeholder section that is WP.org compliant (minimal). Define the approvals data model/interfaces so a separate Pro add-on plugin can implement it cleanly. Do not add licensing code to the free plugin.

---

# M9 — QA, Packaging, Release

## Deliverables

- Tagged release build
- Changelog
- Basic docs for users
- Optional: sample portal page + onboarding wizard (if time)

## Technical Tasks

- Versioning:
  - define semantic versioning
- Build checklist:
  - remove dev-only assets
  - confirm text domain (`client-approval-workflow`)
  - confirm uninstall.php behavior (probably minimal)
  - confirm prefix consistency (`cliapwo_*`)
  - confirm main plugin file is `client-approval-workflow.php`
  - confirm plugin folder/package is `client-approval-workflow`
- Documentation:
  - quick start steps
  - FAQ
  - “Security & permissions” notes
- Smoke test on fresh WP install
- Final naming consistency pass across:
  - plugin header/public name (SignoffFlow)
  - admin menu (SignoffFlow)
  - readme title (chosen SEO title)
  - folder/package (`client-approval-workflow`)
  - main plugin file (`client-approval-workflow.php`)
  - text domain (`client-approval-workflow`)

## Tests (manual)

- [ ] Install from zip on clean WP works
- [ ] Activate + create client + assign user + create update + upload file + send notification works end-to-end
- [ ] No PHP warnings in debug

## Suggested Git Commits

1. `chore: prepare release assets and documentation`
2. `chore: bump version to x.y.z and update changelog`

## Agent Prompt

> Implement Milestone M9. Prepare a release-ready build: version bump, changelog, docs, and run end-to-end smoke tests. Provide a final checklist and any known issues.

---

## Suggestions / Optional Enhancements (Post-v1)

- Client “viewed” tracking for updates/files (activity timestamps)
- Client upload portal (Pro)
- Commenting on updates (Pro, with moderation)
- Webhooks/Zapier integration (Pro)
- Templates: “Website build”, “SEO monthly”, “Branding” request sets (Pro)
- Reports dashboard (Pro)
- White-label email templates (Pro)

---

## Agent Operating Notes

- If any decision affects data model or security (CPT vs table, file storage), **ask first**.
- Prefer incremental PR/commits per milestone with small, testable changes.
- Every mutation endpoint must have:
  - capability check
  - nonce verification
  - validation + sanitization
- Frontend must escape all outputs and never expose private IDs without access checks.
- Use the `cliapwo` prefix consistently in code-facing identifiers.
- Use `client-approval-workflow` consistently for package-level identifiers (folder, main file, text domain, POT file).

---

## Questions the Agent Should Ask You Up Front (Only if a change from defaults is needed)

1. Do you want to override the default of **multiple projects being Pro only**?
2. Do you want to override the default of **client uploads being Pro only**?
3. Do you want to override the default of **clients being able to mark requests complete**?
4. Do you want to override the default of **CPT-based storage**?
5. Do you want to override the default of **shortcode-first portal rendering**?
6. Do you want to override the current branding/package constraints (public name, folder/package, text domain, namespace, design style)?
