# Maintenance Guide — HXFE — Code-First Forms

This file is for developers who want to maintain, modify, or fork this plugin independently.

HXFE is intentionally simple. There are no compiled assets, no build pipeline, and no external services required for core functionality. A developer comfortable with PHP and WordPress can maintain it without help from the original author.

---

## Plugin Architecture

```
hxfe-code-first-forms/
├── hxfe-code-first-forms.php   # Entry point. Defines constants, loads includes, registers assets.
├── includes/
│   ├── schema.php              # Schema registration and validation (hxfe_schemas filter)
│   ├── shortcode.php           # [hxfe_form] shortcode handler
│   ├── renderer.php            # HTML output for standard forms
│   ├── step-renderer.php       # HTML output for step/one-by-one mode
│   ├── chatbot.php             # HTML output for chatbot mode
│   ├── ajax-handlers.php       # AJAX submission handling
│   ├── mailer.php              # wp_mail() wrapper and to_rules routing
│   ├── sanitizers.php          # Field value sanitization
│   ├── conditions.php          # show_if / required_if / skip_if logic
│   ├── fields/                 # One file per field type (text.php, select.php, etc.)
│   ├── access-control.php      # IP restriction and password auth
│   ├── recaptcha.php           # reCAPTCHA v2/v3 verification
│   ├── honeypot.php            # Spam trap logic
│   ├── file-upload.php         # File attachment handling
│   ├── webhook.php             # Webhook dispatch
│   ├── smtp.php                # SMTP settings (wp_mail override)
│   ├── iframe.php              # iframe embed / CORS origin control
│   ├── logger.php              # Error log writer (hxfe-logs/)
│   ├── privacy.php             # Privacy policy field handling
│   └── settings-page.php       # Admin settings UI
├── assets/
│   ├── js/
│   │   ├── htmx.min.js         # Bundled htmx (see "Updating htmx" below)
│   │   ├── htmx-LICENSE.txt    # htmx license file (keep alongside htmx.min.js)
│   │   ├── hxfe-front.js       # Form behavior (submit, confirm screen, file preview)
│   │   ├── hxfe-conditions.js  # show_if / required_if runtime logic
│   │   └── hxfe-chatbot.js     # Chatbot UI behavior
│   └── css/
│       └── hxfe-forms.css      # All styles (CSS custom properties, see DESIGN.md)
├── languages/                  # i18n .pot file
├── ai-reference.md             # AI agent schema reference
├── llms.txt                    # LLM entry point
├── CLAUDE.md                   # Claude-specific agentic coding instructions
├── DESIGN.md                   # CSS custom property reference
├── MAINTENANCE.md              # This file
├── SECURITY.md                 # Security policy
├── readme.txt                  # WordPress.org readme
└── uninstall.php               # Cleanup on uninstall
```

---

## How a Form Submission Works

1. User submits the form → htmx sends a POST request to `admin-ajax.php`
2. `ajax-handlers.php` receives the request, identifies the form by `form_id`
3. `schema.php` loads the registered schema for that form ID
4. `sanitizers.php` sanitizes each field value
5. `conditions.php` evaluates `show_if` / `required_if` rules
6. Field-level validation runs (pattern, minlength, reCAPTCHA, etc.)
7. `hxfe_validate_form` filter runs (cross-field validation)
8. On success: `mailer.php` sends email, `webhook.php` dispatches webhooks
9. htmx swaps the response HTML (confirmation screen or complete screen)

---

## Updating htmx

htmx is bundled at `assets/js/htmx.min.js`. The pinned version is defined in the entry point:

```php
// hxfe-code-first-forms.php
define( 'HXFE_HTMX_VERSION', '2.0.10' );
```

**Steps to update:**

1. Download the new `htmx.min.js` from https://unpkg.com/htmx.org/dist/htmx.min.js
2. Replace `assets/js/htmx.min.js`
3. Update `HXFE_HTMX_VERSION` in `hxfe-code-first-forms.php`
4. Update the version note in `readme.txt` changelog
5. Test: submit a form, verify the AJAX request succeeds

HXFE uses only core htmx attributes (`hx-post`, `hx-target`, `hx-swap`, `hx-indicator`). No htmx extensions are used, so major version upgrades are unlikely to break anything — but always test after updating.

The script handle is `hx-htmx` (shared with HXSE). If both plugins are active, only one copy of htmx loads.

---

## Adding or Modifying a Field Type

Each field type lives in `includes/fields/`. For example, to modify how `text` fields render:

1. Edit `includes/fields/text.php`
2. The file returns an HTML string for the field input
3. Sanitization for the field type is in `includes/sanitizers.php`
4. Validation (pattern, required, etc.) is in `includes/ajax-handlers.php`

To add a new field type:

1. Create `includes/fields/your-type.php`
2. Add the type to the `$supported_types` array in `includes/schema.php`
3. Add sanitization logic in `includes/sanitizers.php`
4. Add any server-side validation in `includes/ajax-handlers.php`
5. Add the type to the `## Field types` table in `ai-reference.md`

---

## Modifying Email Behavior

Email sending is handled entirely in `includes/mailer.php`.

- Recipient routing (`to_rules`) is resolved in `mailer.php`
- Auto-reply logic is in `mailer.php`
- SMTP override (wp_mail filter) is in `includes/smtp.php`

No custom mail library is used — everything goes through `wp_mail()`.

---

## Modifying Styles

All styles use CSS custom properties defined at the `:root` level. See `DESIGN.md` for the full variable reference.

To change default styles without forking:

```css
/* In your theme's style.css or a custom CSS block */
:root {
  --hxfe-color-primary: #your-color;
  --hxfe-radius: 4px;
}
```

To disable built-in CSS entirely, set `'disable_default_css' => true` in the schema.

---

## Forking This Plugin

GPLv2 allows you to fork and redistribute freely. Notes for a successful fork:

1. **Rename the plugin slug** — Change the directory name and the `Plugin Name:` header in the entry point to avoid conflicts with the original.
2. **Update the text domain** — Search and replace `hxfe` in i18n function calls if you rename the plugin.
3. **Keep `uninstall.php`** — It cleans up the options and log directory on uninstall.
4. **No build step needed** — There is no webpack, no npm, no Sass. Edit PHP and CSS directly.
5. **No database schema** — There are no custom tables to migrate. The plugin uses only WordPress options (prefixed `hxfe_`).

The filter hook `hxfe_schemas` is the main integration point. As long as that hook exists and the schema structure is preserved, existing user code will continue to work.

---

## Release Checklist

Files that must be updated on every release:

- `hxfe-code-first-forms.php` — `Version:` header and `HXFE_VERSION` constant
- `readme.txt` — `Stable tag:` and `== Changelog ==`
- `llms.txt` — `Current version:` and changelog entry
- `ai-reference.md` — `**Current version:**`
- `HXFE-manual.md` — Version, last updated date, changelog

---

## Common Modification Patterns

### Change the confirmation screen layout
Edit the HTML returned in `includes/renderer.php` around the `hxfe-confirm` section.

### Add a custom hook after submission
```php
// In your theme's functions.php
add_action( 'hxfe_after_submit', function( $values, $schema ) {
    // $values = sanitized field values
    // $schema = full schema definition
}, 10, 2 );
```

### Change log retention period
In `includes/logger.php`, find the `purge_old_logs()` function and change the `30` (days) to your preferred value.

---

*Last updated: 2026-06-19*
