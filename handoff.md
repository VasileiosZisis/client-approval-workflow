# Handoff

## Current task

Implement the SignoffFlow v1.1 portal styling extensibility pass.

This is a follow-up to the v1 portal styling foundation. The goal is to keep the default portal UI polished while making it easier for site owners and developers to customize safely.

## Scope for this pass

- keep the existing default portal styling in the free plugin
- avoid page-builder complexity
- avoid large settings UI additions
- keep styling scoped to the portal wrapper only
- improve customization ergonomics with a small, stable API surface

## Implementation targets

1. Review and stabilize the current portal CSS classes and CSS variables
2. Add a minimal set of practical filters/hooks, likely around:
   - portal wrapper classes
   - portal inline style variables
   - major section classes
3. Add concise customization documentation for:
   - root wrapper class
   - major section classes
   - supported CSS variables
   - any new filters/hooks
4. Keep any future design-control ideas as recommendations only unless they are truly tiny

## Likely files to touch

- `includes/class-portal.php`
- `assets/css/portal.css`
- `README.md` only if a short repo-facing note becomes useful
- a new concise styling reference file if needed

## Constraints to preserve

- text domain remains `client-approval-workflow`
- plugin branding remains `SignoffFlow`
- use the `cliapwo` prefix for hooks/filters/classes/handles where applicable
- do not add a custom CSS field in this pass
- do not add white-label or per-client branding in this pass

## Deliverables expected

- small code-level extensibility improvements
- a documented customization API surface
- Free vs Pro recommendations for styling-related features
- manual test steps
- suggested commit messages

## Notes

- The current portal styling foundation is already implemented with `assets/css/portal.css`
- The portal currently uses CSS variables on the root wrapper and scoped card/grid/button/status classes
- This pass should refine and expose that structure, not redesign it from scratch
