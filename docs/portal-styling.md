# SignoffFlow Portal Styling Reference

This document describes the stable styling surface for the free SignoffFlow portal.

It is intended for theme developers, implementers, and site owners who want to customize the portal with targeted CSS or lightweight hooks.

## Root wrapper

Portal output is wrapped in:

- `.cliapwo-portal`

Possible state class:

- `.cliapwo-portal--staff-preview`

## Major section classes

Stable section-level classes:

- `.cliapwo-portal__header`
- `.cliapwo-portal__summary`
- `.cliapwo-portal__updates`
- `.cliapwo-portal__requests`
- `.cliapwo-portal__files`
- `.cliapwo-portal__section`

Reusable layout and UI classes:

- `.cliapwo-card`
- `.cliapwo-card--accent`
- `.cliapwo-list`
- `.cliapwo-empty`
- `.cliapwo-button`
- `.cliapwo-button--secondary`
- `.cliapwo-status`
- `.cliapwo-status--open`
- `.cliapwo-status--complete`
- `.cliapwo-status--preview`

## CSS custom properties

These variables are printed inline on the portal root and are intended to remain stable:

- `--cliapwo-primary`
- `--cliapwo-primary-soft`
- `--cliapwo-primary-border`
- `--cliapwo-text`
- `--cliapwo-text-soft`
- `--cliapwo-text-muted`
- `--cliapwo-bg`
- `--cliapwo-card-bg`
- `--cliapwo-border`
- `--cliapwo-max-width`
- `--cliapwo-radius-xl`
- `--cliapwo-radius-lg`
- `--cliapwo-radius-md`
- `--cliapwo-shadow-lg`
- `--cliapwo-shadow-md`

## Filters

### `cliapwo_portal_wrapper_classes`

Filter the root portal wrapper classes.

Parameters:

- `$classes`
- `$client_id`
- `$current_user_id`
- `$is_staff_preview`

### `cliapwo_portal_style_vars`

Filter the CSS custom properties printed on the root portal wrapper.

Parameters:

- `$variables`
- `$settings`
- `$client_id`
- `$current_user_id`
- `$is_staff_preview`

### `cliapwo_portal_section_classes`

Filter the class list for major portal sections.

Parameters:

- `$classes`
- `$section`
- `$client_id`
- `$current_user_id`

Section values currently used:

- `header`
- `summary`
- `updates`
- `requests`
- `files`

## Example

```php
add_filter(
	'cliapwo_portal_style_vars',
	function ( $variables ) {
		$variables['--cliapwo-primary'] = '#0f766e';
		$variables['--cliapwo-max-width'] = '1280px';

		return $variables;
	}
);
```

## Notes

- This styling API is meant for targeted customization, not for building arbitrary layouts.
- The plugin keeps styling scoped to `.cliapwo-portal` to reduce theme conflicts.
- White-label features, per-client branding, custom CSS fields, and style presets are not part of this free styling API.
