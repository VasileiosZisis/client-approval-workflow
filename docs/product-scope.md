# SignoffFlow: Product Scope

This is the authoritative feature-placement document for SignoffFlow — Client
Approval Workflow & Client Portal. It defines what belongs in Free and a
separate Pro add-on, including planned capabilities. Placement is not an
availability claim. The [Free roadmap](free-roadmap.md) tracks the implemented
baseline and upcoming Free work; the [Pro roadmap](pro-roadmap.md) describes
the planned paid catalog and delivery dependencies.

## Purpose and audience

SignoffFlow gives service businesses a focused private WordPress portal for
getting client decisions, requested material, and approval evidence out of email.

- Clients need to understand what needs attention, when it is needed, which
  material to review, and how to record a clear decision.
- Freelancers and agencies need protected sharing, understandable response
  notes, and a reliable history of each approval cycle.
- Staff need practical ways to find outstanding work without adopting a full
  project-management suite.
- Developers need supported extension points for real Free workflows.
- Businesses needing automation, advanced approvals, external integrations, or
  agency-scale operations are the intended audience for a separate paid add-on.

Free must be a complete client approval product, not a demonstration with
locked local functionality. Its intended core loop is: create a client, publish
a request, provide relevant material, optionally set a deadline, let the assigned
client respond, and preserve the decision and its context. Request-linked
attachments and client uploads are still planned; they are not available merely
because this document places them in Free.

## Feature placement

The current repository implementation baseline is **1.7.0**. Release publication
is tracked separately from implementation in the [Free roadmap](free-roadmap.md).
All Pro functionality remains planned.

| Capability | Placement | Reason |
|---|---|---|
| Private portal, WordPress login, client accounts, assigned users, protected downloads, updates, and basic notifications | Free — implemented | These are the complete portal foundation. |
| Approved, Changes requested, Rejected, and Blocked outcomes; response notes; staff reopening and overrides | Free — implemented | Explicit client decisions are the product's core promise. |
| Latest-response summaries and immutable per-request lifecycle history | Free — implemented | Trustworthy decisions must not depend on a paid add-on. |
| Status badges and filters, optional deadlines, due-state filters, due-date sorting, portal urgency counts, and attention ordering | Free — implemented | Users need to know what requires attention and when. |
| State-aware onboarding, optional sample content, safe staff preview, exact sample cleanup, and scoped dismissible release notices | Free — implemented | Adoption, recovery, and maintenance serve every installation. |
| Logo/color branding, portal styling hooks, responsive layouts, accessibility, translation readiness, and regression protection | Free — implemented foundation | Ordinary presentation and reliability are baseline responsibilities. |
| Protected staff files attached directly to a request and protected client response uploads inside that request | Free — planned | The requested material should stay with the request that asked for it. |
| Needs Attention admin view, combined client/status/due/date/search filters, request duplication, and deliberate manual resend | Free — planned | These reduce routine administration without introducing automation. |
| Authorized client-account switching for users assigned to multiple accounts | Free — planned | The portal should correctly expose its existing multi-account data model. |
| WordPress Privacy API integration and basic client/account CSV or JSON exports | Free — planned | Data ownership and ordinary portability are not premium evidence products. |
| Site Health checks, actionable hosting diagnostics, public hooks for functional Free behavior, and compatible migrations | Free — planned expansion | Recovery and a trustworthy extension foundation benefit all users. |
| Scheduled reminders, recurring overdue nudges, escalation, and configurable digests | Pro — planned | Automation saves repeated chasing and adds scheduling/delivery operations. |
| Reusable request/workflow templates, recurring requests, dependencies, multi-step or sequential approvals, and conditional workflows | Pro — planned | Advanced coordination goes beyond the simple Free decision cycle. |
| Granular team permissions, internal approval stages, and specialized status-rank sorting | Pro — planned | These serve organization-specific policy and operational scale; ordinary Free filtering and attention ordering remain available. |
| Guest approval, expiring magic links, and external stakeholder review without a WordPress login | Pro — planned | Account-free collaboration requires a distinct reviewed authorization model. |
| White-label presentation, branded email templates, sender profiles, multiple portal brands, and agency presets | Pro — planned | Agency presentation extends Free's existing logo, color, and styling controls. |
| Signed webhooks, Zapier/Make, Slack/Teams, CRM/project-management/cloud-storage connections, and advanced delivery-provider integrations | Pro — planned | These connect approval work to external business systems. |
| Turnaround/client/team analytics, advanced activity exports, PDF approval certificates, and packaged evidence reports | Pro — planned | Composed reporting and evidence delivery extend ordinary history viewing and data export. |
| Multisite workflow administration, reusable agency configurations, and cross-site operational reporting | Pro — planned | Managed operations across installations are a separate scale layer. |

## Material, portability, and evidence boundaries

Basic request-linked uploads belong in Free. Their initial scope is deliberately
narrow: protected files inside a request, belonging to the same client account,
with safe types and sizes, generated server-side filenames, authorization on
every download, and an activity record. A general file-sync service or cloud
storage replacement is not part of that scope.

Basic Free export means an authorized administrator can obtain understandable
CSV or JSON for one client account, including request outcomes, response notes,
dates, and relevant history. Schema/format safety, authorization, and clear
feedback are Free requirements. Bundling protected file binaries is not required
for the first export implementation.

Pro may compose polished PDF evidence packages, certificates, advanced reports,
and scheduled delivery from authorized records. That does not make the existing
history, its integrity, or ordinary client/account export paid features. Neither
Free nor Pro should claim guaranteed legal compliance or legal validity merely
because it records an approval.

Basic logo/color controls and the existing portal styling hooks remain Free.
Paid white-labeling must not remove those capabilities or introduce unwanted
public attribution as an upgrade incentive.

## Product and implementation principles

- Preserve all existing Free features and data when Pro is installed, missing,
  removed, incompatible, expired, or unable to contact commercial infrastructure.
- Deliver paid implementations entirely in a separate add-on. The WordPress.org
  plugin must contain no inaccessible Pro implementations, future-only paid
  schemas or scaffolding, license gates, trials, or quotas on built-in behavior.
- Extend hooks only where they support functional Free behavior. A proposed
  event or API name is not an implemented contract; preserve existing public
  functions, hooks, statuses, identifiers, and stored data through compatible work.
- Keep the Free slug/text domain `signoffflow-client-approval-workflow`, the
  `cliapwo_` / `CLIAPWO_` identifiers, and its declared WordPress 6.0 / PHP 7.4
  compatibility floor unless a separate compatibility decision changes them.
- Require capabilities and intent validation for privileged changes; validate
  ownership and client assignment independently of submitted IDs. Sanitize and
  validate inputs, escape outputs, and process changes only in hooked handlers.
- Append lifecycle history rather than overwriting earlier decisions. Preserve
  response-note snapshots, responder context, timestamps, and legacy `complete`
  compatibility; do not invent historical approval cycles during migration.
- Keep protected files private. A public Media Library URL is not a substitute
  for access-checked storage. Removing a request attachment must not silently
  delete material used elsewhere.
- Keep deadline state separate from approval state. Compute urgency using the
  site calendar; a due date is not a new stored approval outcome.
- Preserve opt-in sample isolation using `cliapwo_sample_content = 1`. Sample
  creation must not send mail, create user accounts, or advance real onboarding.
- Distinguish mail handoff from confirmed delivery. Historical migration,
  ordinary saves, and deadline edits must not resend notifications unexpectedly.
- Require informed configuration for external communication and document the
  service, transmitted data, purpose, terms, and privacy behavior. Add no hidden
  telemetry, tracking, or unsolicited external requests.
- Use GPL-compatible locally bundled assets and WordPress-provided libraries.
  Do not add public attribution without intentional opt-in. Keep notices sparse,
  contextual, dismissible where appropriate, and separate from onboarding.
- Preserve data by default on deactivation and uninstall. Destructive cleanup
  requires the existing explicit administrator choice and clear consequences.

## Exclusions and documentation priorities

SignoffFlow is not a CRM, sales pipeline, invoicing/accounting suite, help desk,
time tracker, employee/resource manager, or general project-management tool.
Real-time chat, full discussion threads, support tickets, generic Kanban boards,
and Slack-style channels are outside the focused approval product. Do not add a
Project/Workspace entity solely because other portals have one; revisit it only
if repeated real usage demonstrates a limitation. The response note should stay
purposeful and approval-specific.

Free answers “Can I run a professional client approval workflow in WordPress?”
Pro should answer “Can I automate and scale that workflow for my business?”
Prioritize clarity, completion, trust, repeat usage, and then extensibility—not
feature count. Validate demand through support, voluntary feedback/interviews,
reviews, and public adoption signals rather than invasive telemetry. Build Pro
against repeated needs, not one-off requests or assumed commercial tiers.

Keep the README, WordPress.org listing, screenshots, demos, FAQs, changelog, and
release metadata aligned with actual functionality. Marketing and release
checklists are delivery work, not proof that an external asset was published.
Contextual Pro prompts remain deferred until a real separately distributed
add-on and valid documentation/destination URLs exist; no such prompt system is
introduced by these documents.

This document and the two roadmaps replace the historical research report,
numbered handover, and version-numbered Free plan. Their committed versions
remain recoverable through Git. Do not maintain parallel recommendation lists
or treat old pricing, install counts, competitor observations, or package names
as current verified facts.
