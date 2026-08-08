# SignoffFlow Free Plugin Roadmap Handover

## Document Purpose

This is the living product handover and note-keeping document for the recommended improvements to the free SignoffFlow plugin. It records what has been released, what is implemented but not yet released, what is partially available, and what remains planned.

- Intended audience: product owner, maintainers, developers, QA, and release owners.
- Source strategy: [SignoffFlow Free Version Strategy](./deep-research-report.md).
- Last reviewed: 2026-08-01.
- Repository snapshot: `main` branch, working version `1.6.0`.
- Compatibility snapshot: WordPress 6.0+, tested through WordPress 7.0, PHP 7.4+.

The research report describes the product at an earlier point in time. When it conflicts with this document, verify the current repository and update this handover rather than relying on the report's old implementation snapshot.

## How To Maintain This Document

1. Update the last-reviewed date whenever roadmap status or behavior changes.
2. Change a feature to **Released** only after implementation, manual QA, version metadata, changelog, commit, and release are complete.
3. Add a dated handover note when a material product or compatibility decision changes.
4. Keep acceptance criteria synchronized with the actual manual test checklist.
5. Keep Pro-only functionality out of the free repository unless it becomes fully usable in free.
6. Link to current implementation files rather than copying large code excerpts.

## Status Legend

| Status | Meaning |
|---|---|
| **Released** | Implemented, verified, documented, and associated with a completed plugin release. |
| **Implemented, pending release** | Code exists in the current worktree, but manual verification, commit, packaging, or release remains. |
| **Partially implemented** | A useful foundation exists, but the recommended user workflow is incomplete. |
| **Planned** | Product behavior is defined at handover level, but implementation has not started. |
| **Deferred pending separate add-on** | Work must wait until a real separately distributed add-on exists. |

## Roadmap Tracker

| # | Feature | Status | Release or target | User value | Next action |
|---:|---|---|---|---|---|
| 1 | Richer approval outcomes | **Released** | 1.1.0 | Turns requests into a real approval workflow. | Preserve compatibility and include in regression testing. |
| 2 | Client response note | **Released** | 1.2.0 | Captures the reason behind a client's decision. | Keep the latest-response summary aligned with immutable history. |
| 3 | Better request status UI | **Released** | 1.3.0 | Makes request state easier to scan, filter, and act on. | Preserve filtering, legacy-status compatibility, and responsive action styling in regression testing. |
| 4 | Per-request activity/history | **Released** | 1.4.0 | Provides a readable record of how each approval progressed. | Preserve immutable-event, migration, privacy, and timeline behavior in regression testing. |
| 5 | Improved first-run onboarding | **Implemented, pending release** | 1.5.0 | Gets a new user to the first successful approval faster. | Complete manual QA, packaging, commit, and release. |
| 6 | Sample/demo content | **Implemented, pending release** | 1.6.0 | Lets users understand the workflow before entering real client data. | Complete manual QA, packaging, commit, and release. |
| 7 | Contextual upgrade prompts | **Deferred pending separate add-on** | After add-on launch | Introduces relevant paid capabilities without degrading free. | Wait for real add-on functionality, documentation, and destination URLs. |
| 8 | Activation and trust materials | **Partially implemented** | Ongoing | Improves discovery, credibility, and activation. | Complete external listing assets, demo, GitHub metadata, and releases. |

## 1. Richer Approval Outcomes

**Status:** Released in 1.1.0.

### Goal And User Value

Replace binary task completion with approval language that matches how agencies and clients actually make decisions. A client should be able to communicate a clear outcome without moving the discussion back to email.

### Current State

Implemented in [Requests](../includes/class-requests.php) and the [client portal](../includes/class-portal.php). Requests support:

- `Open`
- `Approved`
- `Changes requested`
- `Rejected`
- `Blocked`

Legacy `complete` values remain accepted and are displayed as `Approved`. New approvals are stored as `approved`.

### Delivered Behavior

- Assigned client users can respond only while a request is open.
- All four client outcomes resolve the current request.
- Staff can reopen any resolved request.
- The portal's Action required count includes only open requests.
- Staff can override the status from the request edit screen.
- Existing completed requests require no migration.

### Acceptance Criteria And Manual QA

- Every outcome can be submitted by an assigned client user.
- Resolved requests no longer display client outcome actions.
- Staff can reopen every resolved outcome.
- Invalid status values, invalid nonces, and unassigned users are rejected.
- Legacy `complete` requests display and behave as Approved.
- Open-request counts remain correct.

### Remaining Work And Dependencies

No feature work remains. Continue compatibility testing whenever request storage, filtering, history, or reporting changes.

### Handover Notes

- 2026-05: Approved the compatibility approach of reading legacy `complete` without a bulk migration.
- 2026-06: Confirmed richer outcomes are the free product's core approval layer, not a paid gate.

## 2. Client Response Note

**Status:** Released in 1.2.0.

### Goal And User Value

Capture a concise explanation with the client's outcome so staff understand what was approved, why changes are needed, or what is blocking progress.

### Current State

Implemented in [Requests](../includes/class-requests.php), the [client portal](../includes/class-portal.php), and [portal styling](../assets/css/portal.css).

### Delivered Behavior

- Maximum note length is 500 characters.
- A note is optional for Approved.
- A note is required for Changes requested, Rejected, and Blocked.
- The server validates length and required-note rules even if browser validation is bypassed.
- The latest response stores outcome, note, responder user ID, and UTC timestamp.
- Portal and request admin views show the latest response, responder, and localized date/time.
- Reopening preserves the response and labels it as the previous client response.
- A later client response overwrites the previous latest-response metadata.
- Approving without a note after reopening removes the old note while recording the new outcome, responder, and timestamp.

### Acceptance Criteria And Manual QA

- Approved succeeds with or without a note.
- Exception outcomes reject empty or whitespace-only notes.
- A 500-character note succeeds; a longer note is rejected server-side.
- Multiline text is preserved and escaped safely.
- Reopening preserves the previous response.
- A second response replaces all latest-response metadata.
- Admin status overrides do not rewrite client response metadata.

### Remaining Work And Dependencies

The latest-response fields remain the quick summary. The released 1.4.0 activity history snapshots every new response note as an immutable event so multiple approval cycles can be reviewed reliably.

### Handover Notes

- 2026-06: Chose latest-response storage rather than building a comments system.
- 2026-06: Deferred Event Log entries until the activity/history feature can define one consistent event model.

## 3. Better Request Status UI

**Status:** Released in 1.3.0.

### Goal And User Value

Make request status immediately scannable and let staff narrow the Requests screen to the work that needs attention.

### Current State

Released in 1.3.0 through [Requests](../includes/class-requests.php), [portal actions](../includes/class-portal.php), [request admin styling](../assets/css/cliapwo-requests-admin.css), and [portal styling](../assets/css/portal.css).

### Delivered Behavior

- Admin list and request edit screens display text-labeled status badges.
- Admin users can filter requests by Open, Approved, Changes requested, Rejected, or Blocked.
- The Approved filter includes legacy `complete` values.
- The Open filter includes explicit `open` values and requests without stored status metadata.
- Filter input is capability checked, nonce verified, sanitized, and allow-listed.
- Existing admin search and query conditions are preserved.
- Portal buttons use distinct semantic treatments for approve, request changes, reject, block, and reopen.
- Labels remain visible, wrap when translated, show keyboard focus, and become full-width on mobile.
- `.cliapwo-status` uses a `600` font weight.
- Status sorting is not implemented or scaffolded in free.

### Acceptance Criteria And Manual QA

- Every status uses the correct label and badge in the list and edit screens.
- Each status filter returns only matching requests.
- Clearing the filter restores all requests.
- Search, pagination, and status filtering work together.
- Invalid values or invalid nonces are ignored safely.
- Filters do not appear on other SignoffFlow post types.
- Legacy completed and missing-status records appear under the correct filters.
- Portal actions retain existing status and response-note behavior.
- Desktop, tablet, mobile, keyboard, focus, contrast, and long-label checks pass.
- The Status column is not sortable.

### Remaining Work And Dependencies

No release-blocking work remains. Keep the manual test matrix in regression coverage, especially status filtering with search and pagination, legacy status values, translated labels, keyboard focus, and mobile action layouts.

### Handover Notes

- 2026-06-27: Kept status filtering fully functional in free.
- 2026-06-27: Reserved sorting for a separate paid add-on and intentionally added no free-plugin placeholder, hook, rank metadata, or upsell.
- 2026-07-18: Marked the complete status UI as released in version 1.3.0.

## 4. Per-request Activity/History

**Status:** Released in 1.4.0.

### Goal And User Value

Provide a human-readable, immutable timeline showing how one request moved from creation through client responses, staff reopen actions, and later decisions.

### Current State

Implemented through the private [Event Log](../includes/class-events.php), centralized transitions and admin history in [Requests](../includes/class-requests.php), and the client-safe timeline in the [portal](../includes/class-portal.php). Latest-response metadata remains available for quick scanning while immutable events preserve earlier approval cycles.

### Delivered Behavior

- Record request creation, client response, staff reopen, and staff status-change events.
- Store the actor, timestamp, previous status, new status, and response-note snapshot where applicable.
- Do not create duplicate events for saves that do not change status.
- Show a compact, client-safe timeline per request in the portal.
- Show a complete timeline on the request admin screen.
- Exclude emails, user IDs, capabilities, and internal-only details from the client view.
- Preserve each note in its event rather than reading the overwriteable latest-response meta.
- Query histories efficiently rather than issuing one database query per request.
- Display the full portal history inside a collapsed native disclosure while retaining the latest-response summary.
- Identify staff as "Your team" in the client portal while retaining staff display-name snapshots in admin.
- Backfill one reliable pre-1.4 response in bounded batches and immediately before a new response can overwrite legacy metadata.
- Serialize transitions with a short-lived request lock so concurrent submissions cannot create duplicate lifecycle events.

### Acceptance Criteria And Manual QA

- Every real transition creates exactly one event.
- Repeated no-change saves create no event.
- Response notes remain available after later responses.
- Deleted users do not break timeline rendering.
- Client users see history only for requests assigned to their client account.
- Staff see complete request history in admin.
- Existing request creation events continue to display correctly.
- Exports and evidence packs are not exposed in free.

### Remaining Work And Dependencies

No release-blocking work remains. Keep repeated approval cycles, migration retries, deleted users, client reassignment, concurrent submissions, accessibility, and responsive layouts in regression coverage. Evidence-pack and export work remains in the separate add-on.

### Handover Notes

- Use the existing private event post type unless performance measurements justify a different store.
- Treat the timeline as audit context, not as a threaded conversation system.
- 2026-07-18: Chose a collapsed full portal history, retained the latest-response summary, and used a generic staff label in the client view.
- 2026-07-18: Chose a one-event backfill from reliable latest-response metadata rather than fabricating unavailable earlier cycles.
- 2026-07-18: Marked the complete per-request activity history as released in version 1.4.0.

## 5. Improved First-run Onboarding

**Status:** Implemented, pending release in 1.5.0.

### Goal And User Value

Guide a new administrator from activation to the first successful client approval with minimal setup uncertainty.

### Current State

The onboarding service and [admin module](../includes/class-admin.php) now detect five setup milestones from existing plugin data, present the next incomplete action, preserve per-administrator dismissal, and record historical completion after the first real client response. Fresh installations open the checklist automatically, while existing installations receive a compact progress prompt.

### Recommended Behavior

- Detect completion of these milestones:
  1. Portal page configured.
  2. Client account created.
  3. At least one portal user assigned.
  4. Approval request created.
  5. Client response recorded.
- Mark completed steps automatically from repository data rather than manual checkboxes.
- Link each incomplete step directly to the relevant SignoffFlow screen.
- Show a clear completion state after the first successful approval.
- Allow the panel to be dismissed or revisited from the plugin settings page.
- Keep onboarding within SignoffFlow pages and avoid site-wide notices or dashboard widgets.
- Do not send data externally or require a service connection.

### Acceptance Criteria And Manual QA

- A fresh install shows the correct first incomplete step.
- Each completed action updates progress without duplicate records.
- Existing installations do not get forced through completed steps.
- Missing or deleted portal pages return the setup to the correct state.
- Users without the management capability cannot run setup actions.
- The panel can be dismissed and later reopened.
- Completion removes unnecessary prompts without hiding normal settings.

### Remaining Work And Dependencies

- Complete WordPress 7.0 manual QA and release packaging.
- Require item 6 sample records to use the reserved `cliapwo_sample_content` marker.

### Handover Notes

- The current portal-page action is a useful foundation and should be extended rather than replaced.
- Onboarding quality is an activation feature, not an advertising surface.
- 2026-08-01: Chose any valid client response as activation proof, per-administrator dismissal, and compact prompting for upgraded installations.
- 2026-08-01: Made successful onboarding historically sticky while continuing to validate the configured portal page on every settings view.
- 2026-08-01: Reserved `cliapwo_sample_content` so future sample records cannot complete real onboarding progress.

## 6. Sample/Demo Content

**Status:** Implemented, pending release in 1.6.0.

### Goal And User Value

Give new users a safe, immediate example of the client portal and approval workflow before they enter real client data.

### Current State

SignoffFlow Settings now includes an always-available sample-content card that creates one marked client, update, open approval request, and non-notifying event records. Administrators can preview the exact sample client through a signed staff-only URL, repair partial sets, and permanently remove only recorded posts whose marker and post type still match.

### Recommended Behavior

- Make sample content explicitly opt-in from the SignoffFlow onboarding panel.
- Create clearly labeled sample records for one client, one update, and one open approval request.
- Do not create a demo WordPress user automatically.
- Do not assign real users, send emails, contact external services, or trigger tracking.
- Do not create sample files unless a locally bundled GPL-compatible asset has been reviewed and approved.
- Mark every generated record with prefixed metadata and store generated IDs for exact cleanup.
- Make creation idempotent so repeated clicks do not duplicate content.
- Provide one intentional cleanup action that removes only plugin-created sample records.
- Link directly to staff preview after successful creation.

### Acceptance Criteria And Manual QA

- Sample records are created only after explicit admin action.
- Repeating creation reuses or reports the existing sample set.
- No email is sent during sample creation.
- Staff preview displays all sample records correctly.
- Cleanup removes only marked sample records and leaves real content untouched.
- Permission, nonce, sanitization, and deletion checks pass.
- Uninstall behavior remains consistent with the plugin's delete-data setting.

### Remaining Work And Dependencies

- Complete WordPress 7.0 manual QA and release packaging.

### Handover Notes

- Prefer a small representative workflow over broad project-management demo content.
- The sample portal page alone is not sufficient to demonstrate approval value.
- 2026-08-08: Chose version 1.6.0, an always-visible Settings card, and cleanup of edited records while the reserved sample marker remains present.
- 2026-08-08: Implemented stored-ID idempotency, marker-and-type-validated cleanup, direct non-notifying sample events, and a signed staff-preview selector.

## 7. Contextual Upgrade Prompts

**Status:** Deferred pending separate add-on.

### Goal And User Value

Explain relevant paid capabilities only when a user reaches a workflow that the separate add-on genuinely supports, without making the free plugin feel restricted or deceptive.

### Current State

The free repository contains no active Pro gating, locked feature implementation, status sorting scaffold, or upgrade-prompt system. This is the correct state until a real separate add-on exists.

### Recommended Behavior After Add-on Launch

- Show sparse prompts only on SignoffFlow-specific admin screens.
- Mention only capabilities that are fully implemented in the separate add-on.
- Link directly to clear documentation or the add-on product page.
- Use plain language such as "Available in the separate SignoffFlow add-on."
- Keep prompts contextual to reminders, exports, white-labeling, guest access, integrations, or other real add-on workflows.
- Allow dismissing any notice-style prompt.
- Keep all public portal output free of attribution and promotion.

### Prohibited Behavior

- Do not ship disabled controls for unavailable local features.
- Do not ship Pro-only schemas, post types, hooks, contracts, or partial feature code in free.
- Do not use license checks to unlock code already present in free.
- Do not add site-wide notices, dashboard hijacking, referral tracking, hidden redirects, quotas, or trials.
- Do not contact external services without explicit informed opt-in and documentation.

### Acceptance Criteria And Manual QA

- Every prompt points to a capability that exists outside the free plugin.
- Removing or deactivating the add-on does not break free workflows.
- Free features remain fully usable without a key, payment, quota, or service connection.
- Prompts appear only in their intended plugin-admin context.
- WordPress.org readme and privacy disclosures remain accurate.

### Remaining Work And Dependencies

Do not implement until the separate add-on, documentation, destination URLs, licensing model, and WordPress.org compliance review are ready.

### Handover Notes

- Contextual prompts are last in the free roadmap dependency order.
- Sorting remains absent from free; do not add a free placeholder merely to advertise it.

## 8. Activation And Trust Materials

**Status:** Partially implemented and ongoing.

### Goal And User Value

Improve discovery, confidence, and successful activation by accurately showing what the plugin does and proving that the workflow is maintained and usable.

### Current State

Repository-facing work includes updated [README](../README.md), [WordPress.org readme](../readme.txt), versioned changelogs, tested-version metadata, clearer feature descriptions, screenshot captions, and release-oriented plugin metadata. The repository does not contain final WordPress.org screenshot/banner assets or a documented live demo. No git tags are present in the inspected local repository snapshot.

### Remaining External Checklist

- Capture current screenshots after each major UI release.
- Ensure captions match the exact current screen and workflow.
- Prepare WordPress.org icon, banner, and screenshot assets using only owned or GPL-compatible material.
- Tighten WordPress.org short description and feature copy after screenshots are final.
- Publish and maintain a live demo only if it can be kept secure and current.
- Add accurate GitHub description, topics, and homepage metadata.
- Create version tags and human-readable GitHub releases for shipped versions.
- Verify release packages contain distributable plugin files and no development-only material.
- Invite honest feedback without review manipulation, incentives, or admin spam.
- Recheck external links, privacy disclosures, and service disclosures before each release.

### Acceptance Criteria And Manual QA

- Public copy matches the current free feature set and does not advertise unavailable local functionality.
- Screenshots show real inspectable UI rather than mockups.
- Plugin version, stable tag, changelog, tested version, and release package agree.
- GitHub and WordPress.org release information point to the same current release.
- All bundled visual assets have verified compatible licensing.
- No affiliate cloaking, review manipulation, or default public attribution is introduced.

### Remaining Work And Dependencies

Capture final status-UI screenshots from the released 1.3.0 experience. Refresh assets again after onboarding and sample content materially change first-use screens.

### Handover Notes

- Treat this as a recurring release discipline rather than a one-time feature.
- External platform state must be verified directly before marking checklist items complete.

## Recommended Dependency Order

1. Upgrade onboarding to a state-aware first-approval checklist.
2. Add opt-in sample/demo content that integrates with onboarding safely.
3. Refresh activation and trust materials after the first-use workflow is stable.
4. Add contextual upgrade prompts only after the separate add-on exists and passes compliance review.

## Pro-only Boundary Appendix

The free plugin must remain fully functional as distributed through WordPress.org. The following capabilities are reserved for a separately distributed add-on and must not be partially shipped, locked, or represented by dormant local code in free.

| Pro-only capability | Why it remains paid | Free-repository rule |
|---|---|---|
| Reminders, overdue nudges, and digest emails | Recurring automation saves ongoing staff time and adds mail/cron support cost. | Do not ship inactive schedulers, quotas, or license-gated reminder code. |
| Client file uploads | Adds storage, MIME validation, abuse, and permission complexity. | Keep client-upload handlers and UI out of free. Existing staff-to-client protected files remain free. |
| Evidence packs and activity exports | Provides formal proof, packaging, and reporting value. | Free may display history but must not include locked PDF/CSV export code. |
| Advanced permissions | Supports larger teams and role separation. | Do not include inaccessible permission schemas or controls in free. |
| White-labeling and branded communications | Provides agency presentation value beyond basic logo/color branding. | Basic branding remains free; add-on-only branding code stays outside free. |
| Templates, reporting, and multisite workflows | Supports repeatability and organization-level scale. | Keep these implementations and storage contracts in the add-on. |
| Guest approvals and magic links | Introduces a separate token, expiry, revocation, and audit security model. | Do not expose unauthenticated approval routes in free. |
| Webhooks and integrations | Connects the approval workflow to agency toolchains. | Do not ship inactive endpoints, credentials, or integration settings in free. |
| Request status sorting | Adds workflow prioritization as part of the paid administration layer. | Free includes status badges and filtering only; no rank metadata, sortable hook, or locked column behavior. |

## Decision And Change Log

| Date | Decision or change | Result |
|---|---|---|
| 2026-05 | Move real approval outcomes into free. | Released in 1.1.0. |
| 2026-06 | Add latest client response notes without building immutable history yet. | Released in 1.2.0. |
| 2026-06-27 | Keep admin status filtering in free and reserve sorting for the separate add-on. | Implemented in the pending 1.3.0 worktree. |
| 2026-06-27 | Use this file as the canonical free-roadmap handover. | Update after each material product or release decision. |
| 2026-07-18 | Release the better request status UI. | Released in 1.3.0; filtering remains free and sorting remains reserved for a separate add-on. |
| 2026-07-18 | Implement immutable per-request activity history. | Released in 1.4.0 with a collapsed full portal timeline and one reliable legacy-response backfill. |

## Next Review Checklist

- [x] Complete the 1.3.0 manual test matrix on WordPress 7.0.
- [x] Commit and release the status UI work.
- [x] Change feature 3 from pending release to Released after release completion.
- [x] Decide activity-history event limits and backfill behavior.
- [x] Complete the 1.4.0 activity-history manual test matrix on WordPress 7.0.
- [x] Package and release the 1.4.0 activity-history work.
- [x] Define onboarding completion and sample-content isolation rules.
- [ ] Recheck external WordPress.org and GitHub trust assets.
- [ ] Confirm no Pro-only code or locked controls have entered the free repository.
