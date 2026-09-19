# SignoffFlow Free Plugin Roadmap

**Product:** SignoffFlow – Client Approval Workflow & Client Portal  
**Roadmap scope:** Current released **Free** plugin only  
**Baseline:** v1.6.0  
**Updated:** September 2026

Sources used as the baseline:

- GitHub: https://github.com/VasileiosZisis/client-approval-workflow
- WordPress.org: https://wordpress.org/plugins/signoffflow-client-approval-workflow/

---

## 1. Roadmap objective

The Free plugin should become the strongest possible version of this core promise:

> **Give service businesses a simple private WordPress portal where clients can see what needs attention, receive relevant files, provide requested material, and leave an explicit recorded approval decision.**

The Free plugin should **not** try to become a CRM, project-management suite, invoicing system, help desk, or communication platform.

Its job is to make the core workflow complete and trustworthy:

```text
Staff creates request
        ↓
Client understands what is needed
        ↓
Client can access the relevant material
        ↓
Client provides any requested material
        ↓
Client records a clear decision
        ↓
Decision and evidence are preserved
```

The roadmap below focuses on making that loop complete before work begins on Pro.

---

# 2. Current Free baseline

As of v1.6.0, SignoffFlow already has a solid foundation.

### Client portal

- Private portal based on normal WordPress users
- Client account assignment
- Multiple users can belong to a client account
- Dedicated client-facing portal canvas
- Responsive portal navigation
- Portal branding
- Staff-only portal management
- Client users kept away from normal `wp-admin`

### Client communication

- Updates timeline
- Protected file sharing
- Email notifications for updates, files, and requests
- Event logging

### Approval workflow

- Approval requests
- Open state
- Approved outcome
- Changes Requested outcome
- Rejected outcome
- Blocked outcome
- Optional note for approval
- Required explanatory note for negative/non-final outcomes
- Responder identity
- Response timestamp
- Staff reopening
- Immutable request activity history
- Admin status badges
- Status filtering

### Onboarding

- State-aware first-run setup
- Setup milestones
- Sample client/update/request workflow
- Staff-only sample preview
- Exact sample cleanup

This means the Free version no longer needs broad feature expansion. It needs to **close the remaining gaps in the approval workflow**.

---

# 3. Product rules for the Free version

Every Free feature should pass at least one of these tests:

1. Does it make the core approval workflow easier to complete?
2. Does it make client access safer or more reliable?
3. Does it reduce confusion for the client?
4. Does it reduce routine administration for the agency/freelancer?
5. Does it increase trust in the recorded approval history?
6. Does it improve adoption, compatibility, accessibility, or data ownership?
7. Does it create a clean extension point that the separate Pro plugin can build on?

If a feature instead adds business automation, advanced workflow logic, white-label commercialization, integrations, reporting, or team-scale management, it should usually be reserved for Pro.

---

# 4. Recommended release sequence

## v1.6.x — Stabilization and release hygiene

**Goal:** Make the current v1.6 feature set boringly reliable before adding another workflow concept.

**Status:** Implemented in v1.6.1, pending release.

This should be a maintenance track rather than a feature release.

### Add / improve

- Keep `readme.txt`, GitHub documentation, WordPress.org changelog, screenshots, and plugin version metadata synchronized.
- Add regression tests around the full request lifecycle:
  - request creation
  - client response
  - response-note validation
  - reopen
  - second response
  - immutable event history
- Add regression tests for cross-client authorization.
- Add regression tests for protected file downloads.
- Verify migrations from all previously published Free versions.
- Confirm all admin actions use capability checks and nonces.
- Test PHP and WordPress versions across the declared compatibility range.
- Audit portal keyboard navigation, focus states, reduced-motion behavior, and responsive layouts.
- Audit translation readiness for long labels and right-to-left layouts.

### Definition of done

A release upgrade must not:

- lose previous approval history;
- change existing outcomes;
- make protected files public;
- allow one client account to access another;
- duplicate migration events;
- resend historical notifications unexpectedly.

---

# 5. v1.7.0 — Due Dates and Attention

**Priority: P0**

This should be the next meaningful Free feature.

The current workflow tells clients **what** needs attention, but not **when** it is needed.

## Feature: Request due date

Add an optional due date to approval requests.

A due date should be a property of the request, not a new approval status.

For example:

```text
Homepage Design Approval
Due: 24 September 2026
Status: Open
```

When overdue:

```text
Homepage Design Approval
Overdue by 2 days
Status: Open
```

### Admin requirements

- Optional due-date field on requests
- Due date visible in the Requests list
- Sort by due date
- Filter:
  - All
  - Due soon
  - Overdue
  - No due date
- Overdue styling that is noticeable but not alarmist
- Preserve due-date changes in useful audit/event metadata where appropriate

### Portal requirements

The **Action required** area should prioritize:

1. overdue requests;
2. requests due soon;
3. other open requests.

Each request should clearly show its due date.

Do not create a new stored status such as `overdue`. Overdue should be computed from:

```text
request is open
AND
due date < current site date
```

This avoids mixing workflow state with scheduling state.

### Email requirements

Existing request notification emails should include the due date when one exists.

### Keep out of Free for now

Do **not** add automatic reminder scheduling in this release.

Free should expose urgency.

Pro can later automate chasing.

---

# 6. v1.8.0 — Request-Linked Files and Client Uploads

**Priority: P0**

This is the biggest remaining functional gap in the current core loop.

SignoffFlow can currently ask:

> Please provide the signed agreement.

But the client should be able to satisfy that request **inside the request itself**.

## Feature A: Staff request attachments

Allow staff to attach protected files to a request.

Example:

```text
Request:
Approve homepage design

Attached:
homepage-v4.pdf
```

The client should not have to leave the request and search the general Files section.

### Requirements

- Attach one or more SignoffFlow-protected files to a request
- Display attachments directly inside the request
- Reuse the existing authorization layer
- Never expose the physical upload path
- Removing an attachment from a request must not silently delete a file used elsewhere

---

## Feature B: Client response attachments

Allow an assigned client user to upload files as part of a request response.

Example:

```text
Request:
Send the signed agreement

Client response:
[ signed-agreement.pdf ]

Outcome:
Approved / Submitted
```

### Recommended Free scope

Keep this deliberately narrow:

- uploads happen **inside a request**;
- uploaded files belong to the same client account as the request;
- uploads are protected by the same download authorization system;
- safe file types only;
- configurable or sensible file-size limit;
- generated/sanitized server-side filename;
- no executable file types;
- attachment event recorded in request history;
- staff can see who uploaded the file and when.

Do not initially build a general Dropbox replacement.

The value is:

> **The requested material stays attached to the request that asked for it.**

### Important data-model rule

A response attachment should not be stored merely as arbitrary post meta containing a public Media Library URL.

It should use the same protected-storage principles as existing SignoffFlow files.

---

# 7. v1.9.0 — Admin Workflow Efficiency

**Priority: P1**

Once real users have multiple clients and requests, the administrative experience will matter more than additional portal modules.

## Feature A: Needs Attention screen

Add a focused administrative view showing items staff are likely to act on.

Possible groups:

```text
Needs attention
├── Overdue open requests
├── Changes requested
├── Rejected
├── Blocked
└── Recently responded
```

This can be a plugin dashboard or the default Requests view.

Avoid building a general analytics dashboard.

The purpose is operational triage.

---

## Feature B: Better request filtering

Support combinations of:

- client
- request status
- due state
- date range
- search term

Preserve filters in the URL so staff can bookmark useful views.

---

## Feature C: Duplicate request

Allow staff to duplicate an existing request into a new draft.

Duplicate:

- title
- content/instructions
- due-date structure if desired
- staff attachments if appropriate

Do **not** duplicate:

- client response
- responder
- response timestamp
- event history
- old outcome

This gives users a simple productivity tool without turning Free into a template engine.

---

## Feature D: Manual resend

Add a deliberate:

> **Resend request email**

action for an open request.

Requirements:

- explicit staff action;
- nonce/capability protection;
- event-log entry;
- success/failure feedback;
- no scheduled automation.

This gives Free users a way to chase a client manually while leaving automated reminders as a clear future Pro feature.

---

# 8. v1.10.0 — Multi-Account Client Experience

**Priority: P1**

The data model already supports a WordPress user being associated with multiple client accounts.

The portal should handle that state explicitly.

## Feature: Client account switcher

When a portal user belongs to more than one client account, show a simple workspace/account selector.

Example:

```text
Viewing:
[ Acme Ltd ▼ ]

Other accounts:
- Acme Europe
- Personal Project
```

### Requirements

- Do not show a switcher for users with only one account
- Verify authorization on every account switch
- Never trust a client/account ID from the query string by itself
- Preserve the selected account while navigating portal sections
- Make the current account obvious
- Ensure notifications/deep links open the correct authorized account where practical
- Provide useful empty states for accounts with no requests/files/updates

This closes the mismatch between the multi-account data model and the current portal experience.

---

# 9. v1.11.0 — Data Ownership, Privacy, and Export

**Priority: P1**

A plugin that stores private client activity and approval evidence should make data ownership predictable.

## Feature A: WordPress Privacy API integration

Integrate relevant SignoffFlow data with WordPress's personal-data tools where appropriate.

Review:

- client-user association
- request responses
- response notes
- event actor references
- uploaded response files
- notification/event metadata

Be conservative with deletion when records form part of an approval audit trail.

Where erasure would damage a legitimate historical record, anonymization may be preferable to silent deletion.

Document the behavior clearly.

---

## Feature B: Client/account export

Provide a simple administrative export of SignoffFlow data for one client account.

Recommended Free formats:

- CSV for request/status history where practical
- JSON for structured account data

Include enough context to make the data understandable:

- client
- request title
- outcome
- responder
- response date
- response note
- due date
- history timestamps

Protected file binaries do not need to be bundled into the first version of the export.

This is primarily a **data ownership and portability** feature, not advanced reporting.

---

## Feature C: Explicit uninstall behavior

Make the uninstall policy clear.

Recommended default:

> Deactivating or deleting the plugin does not unexpectedly destroy client records.

If destructive cleanup is supported, require an explicit administrator choice and clearly explain its consequences.

Never make cleanup a hidden side effect.

---

# 10. v1.12.0 — Health Checks, Compatibility, and Extension Foundation

**Priority: P1**

Before serious Pro development begins, make Free a stable platform that a second plugin can extend without copying internal implementation details.

## Feature A: SignoffFlow Site Health checks

Add useful checks for conditions that commonly break the product.

Examples:

- portal page is configured and published;
- portal shortcode is present where expected;
- protected-storage directory is writable;
- private-file hardening status can be explained;
- site can create/write required data;
- notification configuration is available;
- potentially dangerous configuration is surfaced to administrators.

Do not claim a server is secure merely because one heuristic passes.

For Nginx or unusual hosting, provide actionable documentation rather than false certainty.

---

## Feature B: Stable extension hooks for Pro

Create and document a small public extension surface.

Useful actions could exist around:

```text
client created
request created
request status transitioned
request reopened
client response recorded
response attachment uploaded
file shared
notification attempted
portal account resolved
```

Useful filters could exist around:

```text
allowed request outcomes
portal navigation
portal request presentation
notification recipients
notification subject/body
supported response attachment types
portal account switcher labels
```

The exact API names can follow the current `cliapwo` prefix.

The important rule is:

> **Pro should call supported Free APIs and hooks instead of directly depending on undocumented post meta or private class internals.**

---

## Feature C: Schema/version discipline

Add or formalize:

- plugin database/schema version;
- idempotent upgrade routines;
- bounded migrations;
- migration tests;
- backward-compatible status handling;
- documented deprecation policy for public hooks.

This matters more once Free and Pro evolve independently.

---

# 11. Features that should NOT be added to Free yet

These features may be valuable, but adding them to the current Free plugin would either blur the product or remove strong reasons for a future Pro product.

## Reserve for Pro

### Automation

- automatic reminders
- repeated overdue reminders
- configurable reminder schedules
- reminder escalation
- daily/weekly client digests

### Advanced approval workflows

- reusable request templates
- workflow templates
- multi-step approvals
- sequential approvers
- internal approval stages
- conditional workflows
- recurring requests
- request dependencies

### Frictionless external approvals

- guest approvals
- magic-link approval without WordPress login
- expiring secure approval links
- external stakeholder approval

These require especially careful security design and make sense as premium functionality.

### Agency commercialization

- full white-label portal
- remove SignoffFlow branding
- branded email templates
- custom email sender profiles
- multiple portal brands
- agency presets

### Integrations

- webhooks
- Zapier / Make integration
- Slack / Teams notifications
- CRM integrations
- project-management integrations
- cloud storage providers
- advanced SMTP/provider integration

### Reporting and evidence

- approval analytics
- turnaround-time reports
- client responsiveness reports
- team reporting
- PDF approval certificates
- polished evidence packages
- scheduled reports

### Larger workspace model

Do not add Projects merely because client portals commonly have them.

Only introduce a Project/Workspace entity after real Free users demonstrate that this is a repeated limitation.

A project hierarchy could easily pull SignoffFlow toward general project management.

### Communication

Avoid:

- real-time chat
- full discussion threads
- internal messaging
- support tickets
- Slack-style channels

The existing response note should remain purposeful and approval-specific.

### Business software

Do not add:

- CRM
- sales pipelines
- proposals
- accounting
- invoice generation
- subscriptions
- time tracking
- Kanban boards
- employee/resource management

Integrate with those categories later rather than reproducing them.

---

# 12. Recommended priority order

If development capacity is limited, implement in this order:

| Priority | Feature | Why |
|---|---|---|
| **P0** | Due dates + overdue awareness | Completes the “what needs attention and when?” experience |
| **P0** | Request-linked staff attachments | Keeps approval context together |
| **P0** | Client response uploads | Lets clients actually provide requested material |
| **P1** | Needs Attention admin view | Makes the plugin usable with more real clients |
| **P1** | Client/status/due filters | Reduces admin friction |
| **P1** | Manual notification resend | Solves chasing without giving Free full automation |
| **P1** | Multi-account switcher | Aligns portal UX with the existing data model |
| **P1** | Privacy/export tools | Improves trust and ownership |
| **P1** | Public extension hooks | Prevents future Pro from coupling to internals |
| **P1** | Site Health / diagnostics | Reduces support and hosting confusion |
| **P2** | Request duplication | Useful productivity without building templates |
| **P2** | Additional UX/accessibility polish | Continuous improvement |

---

# 13. Suggested Free / Pro boundary

A useful way to think about the split is:

## Free answers

> **Can I run a professional client approval workflow in WordPress?**

Free should make the answer clearly **yes**.

A Free user should be able to:

```text
Create client
    ↓
Create request
    ↓
Attach material
    ↓
Set due date
    ↓
Notify client
    ↓
Client uploads requested material if needed
    ↓
Client approves / requests changes / rejects / blocks
    ↓
Staff receives the result
    ↓
Full history remains available
```

That is a complete product.

## Pro answers

> **Can SignoffFlow automate and scale this workflow for my business?**

That is where paid value can live:

```text
automation
templates
guest access
white label
integrations
advanced reporting
multi-step workflows
scheduled reminders
large-scale agency features
```

This creates a healthier upgrade boundary than intentionally crippling the Free plugin.

---

# 14. Validation gates before starting Pro

Because the current WordPress.org plugin is still early in adoption, do not use feature count as the signal for beginning Pro.

After the Free roadmap reaches a strong core state, look for behavioral evidence.

Useful validation signals include:

### Activation quality

- people complete portal setup;
- they create a real client rather than only sample content;
- they publish at least one real request.

### Workflow completion

- clients log in;
- clients respond to requests;
- users reopen and reuse the workflow;
- response history becomes useful in real client work.

### Repeat usage

- the same installation creates requests for multiple clients;
- users create new requests in later weeks;
- protected files and responses are used repeatedly.

### Support signals

Repeated requests for the same missing capability are especially valuable.

Examples:

> “Can it remind my client automatically?”

That strongly supports a Pro reminder feature.

> “Can I save this request as a reusable workflow?”

That supports Pro templates.

> “Can my client approve without creating a WordPress password?”

That supports Pro guest/magic-link approvals.

> “Can this send the approval to Zapier/Make/Slack?”

That supports Pro integrations.

Do not implement every one-off request.

Look for repeated patterns.

---

# 15. Metrics worth tracking without invasive telemetry

SignoffFlow does not need hidden usage tracking.

You can validate the product through privacy-respecting channels such as:

- WordPress.org active installation growth
- support forum topics
- GitHub issues
- opt-in feedback form
- plugin reviews
- direct interviews with agencies/freelancers
- voluntary beta group
- feature-request voting
- anonymized telemetry only if it is genuinely optional and clearly disclosed

The most important question is not:

> How many people downloaded it?

It is:

> **How many people used SignoffFlow for a real client approval and then used it again?**

---

# 16. Recommended release map

```text
v1.6.x
Stability, compatibility, regression coverage, release hygiene
    ↓
v1.7.0
Due dates + overdue awareness
    ↓
v1.8.0
Request attachments + client response uploads
    ↓
v1.9.0
Admin triage + filters + manual resend + request duplication
    ↓
v1.10.0
Multi-account client switcher
    ↓
v1.11.0
Privacy + data export + uninstall/data ownership rules
    ↓
v1.12.0
Site Health + public extension API + schema hardening
    ↓
FREE CORE MATURE
    ↓
Begin Pro based on observed demand
```

The numbering is a planning suggestion, not a requirement. If a security or compatibility issue appears, ship a patch immediately rather than waiting for the roadmap sequence.

---

# 17. What I would build next

If only three meaningful features are added before spending more time on market validation, they should be:

## 1. Request due dates

This immediately strengthens the portal's **Action required** concept.

It changes:

> You have an open request.

into:

> This approval is needed by Thursday.

---

## 2. Request-linked client uploads

This makes SignoffFlow capable of handling requests where the client must provide something, not only click a decision button.

It changes:

> Please send us your signed document.

from an instruction that pushes the client back to email into a workflow that can actually be completed inside SignoffFlow.

---

## 3. Manual resend + Needs Attention admin view

Together these make the product useful in normal agency operations without introducing premium-grade automation.

Staff can see:

```text
3 overdue
2 changes requested
1 blocked
```

and deliberately resend a request when necessary.

---

# 18. Final product direction

The Free plugin should not become:

> **A complete client-management system for WordPress.**

It should become:

> **The best focused WordPress workflow for getting client decisions, requested material, and approval evidence out of email.**

That is a narrow enough product to understand quickly, useful enough to install for real clients, and extensible enough to support a separate Pro plugin later.

The roadmap should therefore optimize for:

```text
clarity
→ completion
→ trust
→ repeat usage
→ extensibility
```

—not feature count.
