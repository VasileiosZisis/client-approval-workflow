# Handoff

## Current repo state

- Plugin name: `SignoffFlow`
- Package slug: `client-approval-workflow`
- Main plugin file: `client-approval-workflow.php`
- Text domain: `client-approval-workflow`
- Namespace: `ClientApprovalWorkflow\`
- Code prefix: `cliapwo`

## Completed milestones

- M1: bootstrap, settings, capabilities
- M2: clients and assignment helpers
- M3: portal shortcode and updates
- M4: files with protected download endpoint
- M5: requests/tasks with portal completion flow
- M6: event log and email notifications
- M7: security/UX/i18n/readme pass
- M8: Pro extension hooks, Pro detection helper, approvals schema/placeholder

## Recent important changes

- Removed manual `load_plugin_textdomain()` from [includes/class-plugin.php](C:/Users/gonea/repos/client-approval-workflow/includes/class-plugin.php)
- Tightened file upload sanitization in [includes/class-files.php](C:/Users/gonea/repos/client-approval-workflow/includes/class-files.php)
- Added request-created emails through the existing event/notification system
- Hid the Approvals submenu unless `cliapwo_is_pro_active()` returns `true`

## Current audit outcome

Audit was done using:

- primary policy: [AGENTS.md](C:/Users/gonea/repos/client-approval-workflow/AGENTS.md)
- supplementary guidance: local skill `C:\Users\gonea\.agents\skills\wp-plugin-development\SKILL.md`

### Real fixes still pending

1. Add explicit uninstall handling

- Reason:
  - skill guidance says preferred approaches are `uninstall.php` or `register_uninstall_hook()`
  - repo currently has activation/deactivation hooks but no uninstall entrypoint
- Smallest valid fix:
  - add `uninstall.php`
  - guard with `if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }`
  - do not delete data by default; document that uninstall is intentionally non-destructive

2. Update readmes for request email support

- Reason:
  - code now supports request-created emails and a `Request emails` setting
  - docs still describe notifications as update/file only
- Files to update:
  - [readme.txt](C:/Users/gonea/repos/client-approval-workflow/readme.txt)
  - [README.md](C:/Users/gonea/repos/client-approval-workflow/README.md)
- Smallest valid fix:
  - update feature lists, FAQ/notification sections, and any wording that says only updates/files

## Useful file references for the next pass

- [client-approval-workflow.php](C:/Users/gonea/repos/client-approval-workflow/client-approval-workflow.php)
- [includes/class-plugin.php](C:/Users/gonea/repos/client-approval-workflow/includes/class-plugin.php)
- [includes/class-settings.php](C:/Users/gonea/repos/client-approval-workflow/includes/class-settings.php)
- [includes/class-events.php](C:/Users/gonea/repos/client-approval-workflow/includes/class-events.php)
- [includes/class-requests.php](C:/Users/gonea/repos/client-approval-workflow/includes/class-requests.php)
- [includes/class-files.php](C:/Users/gonea/repos/client-approval-workflow/includes/class-files.php)
- [readme.txt](C:/Users/gonea/repos/client-approval-workflow/readme.txt)
- [README.md](C:/Users/gonea/repos/client-approval-workflow/README.md)

## Validation status before handoff

Most recent relevant validations completed successfully:

- `php -l includes/class-files.php`
- `php -l includes/class-plugin.php`
- `vendor\bin\phpcs --standard=phpcs.xml.dist includes/class-files.php`
- `vendor\bin\phpcs --standard=phpcs.xml.dist includes/class-plugin.php`

## Next recommended action

Implement the two pending fixes in this order:

1. add `uninstall.php`
2. update `readme.txt` and `README.md` for request email support
