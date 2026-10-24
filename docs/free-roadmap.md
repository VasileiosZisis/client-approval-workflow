# SignoffFlow: Free Roadmap

This document tracks implementation status and delivery order for the Free
plugin. [Product scope](product-scope.md) owns feature placement; the
[Pro roadmap](pro-roadmap.md) covers the separate planned paid add-on.

The current repository implementation baseline is **1.7.0**. Request-linked
attachments, reviewed-material/version references, client response uploads,
additional admin efficiency tools, an account switcher, privacy/export tools,
and expanded health/API support remain
**planned, not shipped**. No new future release numbers, dates, prices, or effort
estimates are assigned here. The earlier suggested 1.8.0–1.12.0 sequence is
replaced by the delivery order below, not a commitment to those release numbers.

## Completed milestones

The [listing changelog](../readme.txt) and repository implementation establish
these milestones. The historical handover recorded releases through 1.6.0;
1.6.1 and 1.7.0 were recorded as implemented, pending release. This documentation
reorganization does not independently verify or change their publication status.
Update that status only after the release owner confirms QA, packaging, commit,
and publication are complete; version metadata alone is not proof of deployment.

| Milestone | Implemented capabilities | Recorded release status |
|---|---|---|
| 1.0.0: Portal foundation | Private client accounts and assigned WordPress users, updates, protected files/downloads, requests, basic notification emails, branding, and event logging. | Released |
| 1.1.0: Approval outcomes | Approved, Changes requested, Rejected, and Blocked; staff reopen/override; legacy `complete` reads as Approved. | Released |
| 1.2.0: Response notes and portal workspace | Optional approval note; required explanation for other outcomes; latest response outcome/note/responder/time; preserved response on reopening; focused responsive portal canvas and section navigation. | Released |
| 1.3.0: Status UI | Request badges, status filtering, legacy/empty-status compatibility, clearer client actions, keyboard focus, and translated/mobile wrapping. | Released |
| 1.4.0: Request activity | Immutable creation, response, reopen, and status-change events; client-safe collapsed history; full admin timeline; bounded reliable latest-response backfill; serialized transitions and no-change protection. | Released |
| 1.5.0: State-aware onboarding | Five real setup milestones, next-step actions, per-administrator visibility, persisted completion, portal repair, and exclusion of sample content. | Released |
| 1.6.0: Sample content | Explicit sample client/update/request and marked events, signed exact staff preview, idempotent repair, and marker/type-validated cleanup without users, files, notifications, or onboarding progress. | Released |
| 1.6.1: Stabilization | Local WordPress integration tests, PHP compatibility checks, disposable WordPress/PHP matrix, lifecycle/migration/isolation/file regressions, and accessibility/release audits. | Implemented, pending release confirmation |
| 1.7.0: Due dates and attention | Optional deadlines, due-state filters and sorting, portal urgency counts/attention order, immutable deadline history, initial-request email dates, and an upgrade-only per-administrator/site dismissible announcement. | Implemented, pending release confirmation |

### Current deadline and approval behavior

- Deadlines use the WordPress site timezone and calendar. Due soon includes today
  through seven calendar days ahead. An open request becomes overdue after its
  due day ends; overdue is computed, not stored as an approval outcome.
- Portal counts cover the client's published requests. Attention ordering
  happens before the request display limit: overdue open requests, due-soon open
  requests, other open requests, then resolved requests. Undated requests remain
  supported; older overdue deadlines receive attention first.
- Due-date sorting and due-state/status filters are available in the Requests
  list. Specialized status-rank sorting is not part of the implemented Free UI.
- Resolving or reopening preserves the deadline. Staff deadline edits append
  history; ordinary/no-change saves must not create duplicate lifecycle events.
- Initial request emails include a deadline when one exists. Date edits do not
  send extra notifications. Scheduled reminders and digests are not implemented.
- Assigned clients can respond only to their own published open requests. All
  four outcomes resolve the current cycle; staff may reopen or override it.
- Earlier response notes remain in history. Reassignment must not expose a
  prior client's latest response or events. Reliable legacy backfill reconstructs
  only the single response supported by stored metadata, not imagined cycles.
- Onboarding accepts any valid real client response, including reliable history
  with a deleted responder. Completed workflow milestones stay complete; the
  portal-page milestone is always checked live.
- Sample records remain excluded from onboarding. Creation is opt-in,
  non-notifying, and repeatable; cleanup skips unmarked or mismatched records and
  deletes edited records only while they remain valid marked sample items.
- The 1.7.0 announcement is separate from onboarding, limited to Settings and
  the Requests list, and suppressed on fresh activation. Patch updates and
  reactivation do not reset each administrator's site-specific dismissal.

## Planned delivery order

1. Protected staff attachments linked directly to requests, with the Free
   foundation for identifying the material/version reviewed.
2. Protected client response uploads inside requests.
3. Admin workflow efficiency: Needs Attention view, combined filters, manual
   resend, and simple draft duplication.
4. Authorized client-account switching.
5. Privacy integration and basic client/account data export.
6. Site Health diagnostics, supported extension contracts, and schema hardening.

Due dates and overdue visibility are high-priority Free capabilities already
implemented in 1.7.0. Protected staff attachments are the next high-priority
addition, with reviewed-material/version context delivered alongside them. The
two attachment features remain the next core priority. Within admin efficiency,
triage, filters, and manual resend take precedence over duplication. Continuous
security/compatibility maintenance and justified accessibility corrections do
not have to wait for the next feature milestone.

### 1. Protected staff attachments on requests

**Status:** Planned; high priority and next Free feature.

**Purpose:** Keep review material with the approval request instead of making
the client search the general Files section.

**Scope and dependencies:** Reuse existing SignoffFlow-protected files, client
relationships, and download authorization. Allow staff to associate one or more
eligible files with a request and show those attachments inside the request.
Include the reviewed-material/version foundation below rather than reserving
basic approval context for Pro evidence packages.

**Acceptance criteria:**

- Staff can add/remove links with capability and nonce protection, and only
  files belonging to the request's client are eligible.
- Rendering and downloading revalidate authorization; guessed or stale IDs,
  reassignment, unpublished records, and deleted files fail safely.
- Do not expose physical upload paths or substitute public attachment URLs.
- Removing a link must not silently delete a protected file used elsewhere.
- Preserve existing requests, general file sharing, response semantics, and
  immutable history. Define association-change/history behavior before coding.

#### Reviewed material/version references

**Status:** Planned; Free foundation delivered with request-linked review material.

**Purpose:** Make each decision understandable by recording what the client was
asked to review, including an identifiable version where applicable.

**Scope and dependencies:** Use the request's review context and protected-file
associations. Support a human-readable material reference and optional explicit
version/revision label. A mutable filename or live URL alone must not be treated
as proof of which version was reviewed. Full document version control, content
diffs, permanent binary archives, and legal certification are not required.

**Acceptance criteria:**

- Show the relevant material reference and version label, when supplied, beside
  the request before the client records a decision.
- Capture the reference/version context associated with each response as an
  immutable history snapshot, not merely a lookup of the request's latest values.
- Later request edits, renamed/replaced files, and new review cycles must not
  rewrite earlier decision context. Define material-change and in-flight-response
  handling before implementation; do not silently attribute a response to a
  version different from the one presented for review.
- Apply the same client authorization as the request and protected downloads.
  References never expose physical paths or grant access to removed/reassigned
  material merely because a historical record mentions it.
- Preserve existing approval outcomes and history. Leave unavailable historical
  context explicitly unknown rather than fabricating labels during migration.
- Include recorded references in the planned basic Free client/account export.
  Pro may package that context, but its basic recording and viewing remain Free.

### 2. Protected client response uploads

**Status:** Planned; follows request-linked staff attachments.

**Purpose:** Let a client provide requested material inside the request that
asked for it, rather than returning to email.

**Scope and dependencies:** Build on the protected-storage and request-linking
foundation. Keep the first implementation request-scoped, within the same client
account, using the existing authorization model. Define submission/response
atomicity before implementation; this plan does not introduce a new outcome.

**Acceptance criteria:**

- Only an assigned, authorized user can upload to an eligible published request.
  Submitted request/client IDs never establish permission by themselves.
- Validate file type/content, size, upload errors, and allowed extensions; reject
  executable files and generate sanitized server-side storage filenames.
- Store files privately and apply the same ownership/download checks as other
  protected files, including after reassignment or user removal.
- Record uploader, time, and attachment context in request activity without
  rewriting prior responses or leaking staff-only event data to clients.
- Failed uploads, failed responses, retries, and concurrent submissions must not
  create misleading outcomes, orphan files, or duplicate history/notifications.
- Document removal/retention behavior, sensible limits, and administrative
  controls. Do not build a general cloud file-sharing service.

### 3. Admin workflow efficiency

**Status:** Planned; builds on current statuses, deadlines, and activity history.

**Purpose:** Help staff act on outstanding approvals across more clients without
turning the plugin into a general analytics or project-management dashboard.

**Scope and dependencies:** Provide a focused Needs Attention screen or Requests
view, combined filters, manual resend, and simple duplication into a new draft.

**Acceptance criteria:**

- Triage overdue open requests, Changes requested, Rejected, Blocked, and recent
  responses with useful links back to the intended client and request.
- Combine client, request status, due state, date range, and search. Preserve
  valid filters in the URL for bookmarking and across pagination, without
  weakening capabilities or existing legacy-status behavior.
- A deliberate resend is available only for an eligible open request, requires
  capability and nonce checks, logs the attempt, and gives honest success/failure
  feedback. Do not introduce scheduled chasing or imply inbox delivery.
- Duplication creates a new draft with selected title/content/instructions and
  explicitly defined deadline/attachment handling. Never copy response metadata,
  responder, response time, old outcome, or lifecycle history.
- Use bounded queries, accessible controls, clear empty states, and predictable
  filter combinations. Existing date/status filters remain Free.

### 4. Multi-account client experience

**Status:** Planned; the relationship model already permits multiple accounts.

**Purpose:** Make the active client account explicit for a user assigned to more
than one account, instead of relying on the first assigned client.

**Scope and dependencies:** Add a simple account/workspace selector only when
more than one authorized account exists. This is not a new Project post type.

**Acceptance criteria:**

- Verify published-client assignment on every resolution/switch and subsequent
  request. Never trust a query-string client ID alone.
- Show the active account clearly, preserve valid selection across portal
  sections, and avoid a redundant switcher for single-account users.
- Notifications/deep links should select the intended authorized account where
  practical; unauthorized accounts never become a fallback context.
- Keep updates, files, requests, counts, response summaries, and history isolated
  to the selected account, with useful empty states.
- Preserve the exact signed sample staff-preview path and its capability checks.

### 5. Data ownership, privacy, and export

**Status:** Planned expansion; explicit opt-in uninstall cleanup already exists.

**Purpose:** Make private client data accessible to its owner and clarify
retention without damaging legitimate approval records.

**Scope and dependencies:** Integrate appropriate records with WordPress personal
data tools, provide basic per-client CSV/JSON export, and document existing
deactivation/uninstall behavior. Review response attachments when delivered.

**Acceptance criteria:**

- Inventory associations, responses/notes, actor references, files, and relevant
  event/notification metadata; distinguish personal from shared client data.
- Define erasure/anonymization carefully where deleting one person's data would
  rewrite another person's decision history. Document any retained information
  and limitations without claiming guaranteed legal compliance.
- Export understandable client/request titles, outcomes, response context, notes,
  due dates, recorded material/version references, and history timestamps with
  strict administrator authorization.
- Escape exports appropriately, including spreadsheet formula-injection risks
  for CSV. Do not include credentials, internal paths, unrelated client data,
  or unrestricted internal metadata.
- Protected binary bundles are not required initially. Polished evidence packs,
  advanced analytics, and scheduled report delivery remain planned Pro work.
- Deactivation preserves configuration and records. Uninstall preserves data by
  default; opt-in deletion removes only owned data and respects site boundaries.

### 6. Health checks, compatibility, and extension foundation

**Status:** Planned expansion of existing lifecycle/version and hook behavior.

**Purpose:** Reduce hosting/support ambiguity and provide a dependable extension
surface for real Free workflows before serious Pro implementation.

**Scope and dependencies:** Add useful Site Health checks and document a small
supported API surface. Existing examples include request-created/status-transition
actions and portal presentation hooks; proposed future hooks are not yet APIs.

**Acceptance criteria:**

- Check portal publication/shortcode configuration, protected-storage
  writability/hardening, required data writes, and notification configuration.
- Give actionable guidance for Nginx/unusual hosting and dangerous configurations;
  one heuristic passing must not be presented as proof that a server is secure.
- Document actual supported hooks, payloads, authorization assumptions, version
  support, and deprecation policy. Add hooks only around implemented usable Free
  behavior, not dormant paid-only contracts or schemas.
- Maintain idempotent, bounded migrations, explicit data/schema versions where
  needed, legacy-status compatibility, and retries that do not duplicate events
  or resend historical notifications.
- Add tests for meaningful changes while preserving existing public functions,
  identifiers, URLs, stored metadata, and workflows.

## Shared delivery and regression requirements

Every change must preserve cross-client isolation, protected file access,
approval outcomes, latest-response/history consistency, existing settings, and
site-specific ownership. Require nonces as well as capabilities where relevant;
sanitize and validate input, escape output, and use safe redirects. Permission
checks must remain effective without frontend JavaScript.

Maintain keyboard operation, visible focus, native disclosure behavior,
screen-reader clarity, responsive/long-label layouts, right-to-left support,
reduced motion, translated text, and scoped assets. Sample records must never
advance onboarding or enter real notification/reporting workflows.

Retain the existing local integration-test infrastructure. The PowerShell matrix
uses ignored downloaded tooling and uniquely named disposable `cliapwo_test_*`
databases, not existing LocalWP site databases. Do not add credentials, portable
binaries, caches, or test tooling to the WordPress.org distribution. No GitHub
Actions workflow is required by this roadmap.

For relevant changes, verify request creation, all outcomes and note validation,
reopen/second-cycle history, same-status/autosave behavior, transition failure
rollback, concurrent/stale locks, old metadata migrations, deleted responders,
reassigned clients, protected-file paths/deletion, onboarding persistence,
sample repair/exact cleanup, activation, and opt-in uninstall behavior.

## Documentation and release verification

These are continuing delivery tasks, not additional shipped features:

- Keep the README/current-feature list, listing copy, screenshots/captions,
  demos, FAQs, changelog, upgrade guidance, and version metadata synchronized.
- Verify screenshots for actual portal outcomes, latest response/history,
  onboarding, sample create/preview/repair/cleanup, and due-date attention states.
  Do not mark externally hosted assets, demos, metadata, or releases complete
  merely because local files or copy are ready.
- Explain planned versus implemented functionality and Free versus paid scope
  truthfully. Historical commercial prompts remain deferred until a real add-on
  exists; basic release announcements are not paid upgrade prompts.
- Run Composer PHPCS, PHPCompatibility, PHP syntax checks, POT validation,
  `git diff --check`, the relevant integration matrix, and manual WordPress/browser
  regression checks in proportion to the change. Check the distributable with
  Plugin Check before publication.
- Back up development data before integration writes, use identifiable fixtures,
  prevent unintended notification emails, clean only owned test records, and
  restore settings/content. Keep destructive install/reset tests disposable.
- Mark a release published only after the release owner confirms verification,
  packaging, commit, and WordPress.org/SVN publication. Security or compatibility
  fixes may ship independently of the feature delivery sequence.
