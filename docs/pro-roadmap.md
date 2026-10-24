# SignoffFlow: Pro Roadmap

All functionality in this document is **planned, not available in the current
Free plugin**. It records the paid catalog and delivery dependencies without
promising release dates, future version numbers, prices, effort estimates, or
commercial tier names.

[Product scope](product-scope.md) owns feature placement. The
[Free roadmap](free-roadmap.md) records the implemented **1.7.0** baseline and
upcoming Free additions. Pro is planned as a separately distributed add-on;
no paid implementation or locked Pro scaffolding belongs in this repository.

## Relationship to Free

Pro should extend a complete client approval workflow into **automation,
advanced coordination, branded delivery, integrations, and agency scale**.

Free already includes rich decisions, response notes, immutable lifecycle
history, protected staff file sharing, basic branding/notifications, and deadline
attention. Request-linked staff attachments and client response uploads,
ordinary admin triage/filtering, manual resend, request duplication, account
switching, basic data export, and expanded diagnostics/API support belong to
the Free roadmap. Pro must not take those features away or repackage their
essential safety as a paid upgrade.

Historical research placed all client uploads and activity exports in paid
tiers. That boundary is superseded: request-scoped protected uploads and basic
CSV/JSON ownership exports belong to Free. Pro owns advanced reporting,
composed evidence, automation, and scale. Old Solo/Studio/Agency labels and
proposed package identities are not approved release or pricing commitments.

## Planned delivery order

1. Separate add-on foundation, compatibility, and commercial lifecycle.
2. Scheduled reminders, overdue nudges, and digest delivery.
3. Composed evidence packages and advanced activity export.
4. Advanced permissions and white-label/branded communications.
5. Reusable request/workflow templates and operational reporting.
6. Guest/magic-link approval, after a separate security design and review.
7. Signed webhooks and selected business integrations.
8. Multi-step/conditional workflows, recurring work, and agency/multisite scale.

This order prioritizes less chasing and useful evidence over broad feature
count. Validate repeated user demand before finalizing individual delivery
milestones. Advanced features can change order when their dependencies or
support costs justify it; the catalog is not a commitment to build everything.

### 1. Separate add-on foundation and lifecycle

**Status:** Planned; prerequisite to paid features and contextual Pro prompts.

**Purpose:** Establish an independently installable product that extends
compatible Free versions without making Free depend on commercial systems.

**Scope and dependencies:** Finalize the Pro plugin/repository slug, namespace,
text domain, prefix, package name, minimum Free version, and distribution model.
Use supported Free hooks for functional behavior; do not couple the add-on to
undocumented private storage merely because classes or metadata are accessible.

**Acceptance criteria:**

- Missing or incompatible Free yields recoverable, scoped guidance without a
  fatal error, redirect loop, change to client access, or loss of existing data.
- Activation, additive upgrades, deactivation, and uninstall preserve Free
  clients, files, requests, decisions, history, branding, and configuration.
- Pro owns its capabilities, settings, schemas, assets, scheduled work, routes,
  and identifiers in the separate package. No trialware, dormant Pro contracts,
  locked controls, or license-dependent Free behavior ships on WordPress.org.
- Any commercial licensing/update behavior stays in Pro, with explicit service,
  data, terms, and privacy disclosures. Define outage/expiry behavior honestly;
  Free operation must remain unaffected.
- Deactivation stops Pro background work. Data retention/uninstall choices are
  explicit, site-aware, and limited to owned data; removal does not destroy the
  Free approval record or silently drop pending client work.
- Contextual upgrade links may be considered only once a real add-on and valid
  documentation/destinations exist. Keep prompts sparse, dismissible where
  appropriate, on relevant plugin-admin screens, without referral tracking or
  fake locked local functionality. Building prompts is not a current Free task.

### 2. Reminder automation and digests

**Status:** Planned; first paid feature family after the foundation.

**Purpose:** Reduce manual chasing while keeping Free's urgency indicators and
manual notification actions useful on their own.

**Scope and dependencies:** Build on Free's deadlines, semantic request status,
client assignment, notification transport, and history. Support configurable
reminder schedules, recurring overdue nudges, escalation, and daily/weekly
digests for explicitly configured audiences.

**Acceptance criteria:**

- Administrators deliberately enable schedules, recipients, and event choices;
  existing requests never begin sending automated mail merely after activation.
- Use the site timezone, define daylight-saving/late-run behavior, and prevent
  duplicate attempts with bounded claims, retries, and clear cancellation rules.
- Resolving, reopening, reassigning, unpublishing, or deleting a request safely
  reconciles pending work without notifying the wrong client or replaying old
  decisions. Date changes must not silently multiply reminders.
- Provide pause/disable, next-run and outcome feedback, useful recovery actions,
  and bounded non-sensitive delivery logs. Document cron and transport limits;
  successful `wp_mail()` handoff is not proof of inbox delivery.
- Digests expose only the recipient's authorized account/request context; do not
  send sample content, credentials, private filesystem paths, or internal logs.
- Disabling, deactivation, and uninstall stop or remove owned scheduled work as
  documented without breaking ordinary Free request emails.

### 3. Evidence packages and advanced activity exports

**Status:** Planned; second paid value layer.

**Purpose:** Package existing decisions and their context into useful client or
agency records without charging for the underlying trustworthy history.

**Scope and dependencies:** Reuse immutable lifecycle records and delivered
Free account export foundations where appropriate. Plan polished PDF approval
certificates, evidence bundles, advanced activity exports, and selected reporting
formats. Client response attachments can be included only after their Free
storage/authorization and retention behavior is delivered.

**Acceptance criteria:**

- Export only accounts/requests the initiating user is authorized to access.
  Preserve source outcomes, notes, actor context, timestamps, and deadline
  changes; identify missing historical data instead of inventing evidence.
- Define selected content, format/version, generation time, included files, and
  retention/expiry. Keep generated assets protected with access-checked retrieval
  and owned-file cleanup; guessed URLs cannot disclose private material.
- Escape all formats appropriately, handle long/translated text and pagination,
  and bundle only license-verified GPL-compatible dependencies/assets.
- Failure must not rewrite original history or leave publicly accessible files.
  Ordinary Free history and planned basic account CSV/JSON export stay available
  without Pro.
- Explain that recorded approvals and packaged reports are not guarantees of
  legal compliance, evidentiary admissibility, or a particular legal outcome.

### 4. Advanced permissions and branded communications

**Status:** Planned; follows the initial automation/evidence foundation.

**Purpose:** Support internal responsibility and agency presentation beyond the
simple staff/client model and basic Free branding.

**Scope and dependencies:** Define granular team roles and client-scoped
management permissions, internal review stages, specialized status-rank sorting,
white-label portal options, branded email templates, sender profiles, multiple
portal brands, and reusable agency presets.

**Acceptance criteria:**

- Establish an explicit permission matrix. Enforce authorization server-side on
  every view/action/download, independently of hidden UI or submitted IDs.
- Preserve assigned-client isolation and distinguish internal actors/history
  from client-safe presentation. Pro removal must not accidentally grant staff
  privileges to client users or orphan their ordinary Free access.
- Scope styling/naming to supported plugin surfaces and validate branding
  assets/settings. Keep accessible contrast, focus, translation, and mobile use.
- Preserve Free logo/color controls and portal styling hooks. Do not inject
  unwanted public attribution or claim core branding can only be configured
  through a paid add-on.
- Validate sender/recipient configuration, explain mail-transport limits, and
  retain parity between preview and actual messages without cross-client leaks.

### 5. Templates and operational reporting

**Status:** Planned; builds on stable request and notification semantics.

**Purpose:** Reuse business-specific approval processes and understand recurring
workflow bottlenecks without turning SignoffFlow into a general BI suite.

**Scope and dependencies:** Reusable request/workflow templates, agency presets,
approval turnaround analysis, client responsiveness, team reporting, scheduled
reports, and optional branded reporting/evidence delivery.

**Acceptance criteria:**

- Templates contain reusable instructions/configuration, never old responses,
  responder identities, decision timestamps, or another request's history.
- Preserve Free's simple draft duplication. Distinguish template management and
  reusable workflow logic from copying an individual request.
- Define reporting metrics, cycle/reopen handling, timezone boundaries, data
  windows, and missing-data behavior. Do not mislabel inferred durations as
  recorded facts or expose private cross-client information.
- Authorize configuration, previews, exports, and scheduled recipients. Preserve
  source records and document retention plus delivery/format limitations.

### 6. Guest approvals and magic links

**Status:** Planned; later and security-sensitive.

**Purpose:** Permit selected external stakeholders to review without a normal
WordPress account, using a deliberately different authorization model.

**Scope and dependencies:** A separately reviewed token lifecycle, explicit
invitation, scope, expiry, revocation, abuse protection, and trustworthy actor
mapping. Do not add unauthenticated approval endpoints to Free in anticipation
of this work.

**Acceptance criteria:**

- Links grant the narrowest intended account/request/action access, expire and
  revoke predictably, and cannot become general portal/download credentials.
- Define replay, forwarding, token storage, logging/redaction, brute-force, and
  state-change protections; a token must not silently authorize unrelated data.
- Match each recorded response to the invited actor and request while honestly
  distinguishing account-based from guest identity assurance.
- Handle concurrent responses, reopen cycles, reassignment, and revocation
  without contradictory outcomes or weakening logged-in Free authorization.
- Provide administrator visibility and recovery without exposing usable secrets.

### 7. Webhooks and selected integrations

**Status:** Planned; after core automation/evidence value is validated.

**Purpose:** Connect deliberate workflow events to agency systems instead of
rebuilding those systems inside the portal.

**Scope and dependencies:** Signed outbound webhooks, Zapier/Make, Slack/Teams,
selected CRM/project-management and cloud-storage providers, and advanced
SMTP/delivery-provider integration when justified. Prioritize repeated demand
rather than committing to every named service.

**Acceptance criteria:**

- Require explicit administrator configuration, capability checks, informed
  service/data disclosures, and relevant terms/privacy links before sending data.
- Sign deliveries with replay protection, validate destinations against unsafe
  outbound access, and support credential rotation/revocation and disable tools.
- Minimize payloads to the intended account/workflow event. Never send tokens,
  internal paths, unrelated client records, or private notes outside the chosen
  integration scope.
- Use idempotent event identifiers, bounded retries, redacted outcome logs, and
  recoverable failure feedback. External failures must not roll back a valid
  Free approval or duplicate its lifecycle event.
- External file storage must not turn existing protected assets into public
  links or weaken the original account/download checks.

### 8. Advanced workflows and agency scale

**Status:** Planned; later than the focused paid approval/delivery core.

**Purpose:** Coordinate recurring, multi-person, or cross-site operations when
real usage justifies their added complexity.

**Scope and dependencies:** Multi-step and sequential approvers, internal stages,
conditional workflows, recurring requests, dependencies, network administration,
reusable managed configurations, and cross-site reporting. These require stable
template, permission, notification, and history foundations.

**Acceptance criteria:**

- Define stage transitions, ownership, cancellation, rejection/reopen behavior,
  and concurrency before adding a workflow designer. Preserve distinct approval
  outcomes and immutable records across cycles.
- Recurring work creates new request instances; it must not overwrite an earlier
  client's response/history or unintentionally replay notifications.
- Isolate accounts, users, storage, logs, schedules, exports, and settings by
  site as well as client. Network access requires explicit network authority;
  it is not established by a client or site ID parameter.
- Use bounded queries/jobs for network operations, explain inheritance and
  overrides, and make configuration transfer explicit and versioned.
- Additive migrations and Pro removal preserve ordinary Free operation. Do not
  promise a general CRM, project manager, collaboration suite, or fleet product
  merely because individual workflow features support larger agencies.

## Shared delivery and documentation requirements

Use supported Free APIs only for behavior they actually provide; additions to
Free must remain useful without Pro. Preserve all existing identifiers,
client/request associations, decisions, response notes, and history. Do not
present public-looking methods or proposed hook names as a stable API before
their contracts are documented and verified.

Every privileged action needs capability/ownership checks and appropriate
nonce/intent validation. Sanitize and validate inputs, escape outputs, use safe
redirects and protected storage, and retain keyboard/screen-reader operation,
scoped assets, responsive layouts, translation, and reduced-motion behavior.

Test the declared Free/Pro compatibility matrix, fresh/legacy data, installation
and removal, unauthorized/cross-client access, scheduling/timezone boundaries,
concurrency/retries, service outages, cleanup, notification correctness, and
output rendering for each shipped capability. Sample data must not become real
client work, reports, or automated delivery.

Document service communication, licensing/update outages, retention/removal,
cron/mail limitations, supported integrations, and format/identity-assurance
limits. Keep listings, FAQs, screenshots, release notes, and contextual prompts
truthful. None of this planned catalog is an available Pro product or a feature
of the current Free release.

Follow the exclusions in [product scope](product-scope.md). Commercial prices,
tiers, add-on identifiers, and any future pack packaging require a separate
product decision; this roadmap does not introduce them.
