=== HXFE — Code-First Forms ===
Contributors: youheiokubo
Tags: contact form, form builder, htmx, chatbot, ajax
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.3.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Define forms as PHP arrays. Contact forms, step forms, chatbots, and surveys — all from one schema, no GUI required.

== Description ==

**HXFE — Code-First Forms** is a code-first WordPress form plugin. Instead of building forms in a GUI, you define them as PHP arrays and place a shortcode anywhere.

This means your forms live in your codebase — version-controlled with Git, automatically deployed, and free from database migration issues.

= Why code-first? =

* **Git history for free** — Every change to your form shows up in `git diff`
* **Deploy without fear** — Forms are code, so they deploy with your theme. No more "the form disappeared on production"
* **Dynamic options** — Pull select options from `get_posts()`, taxonomies, or any PHP source. No manual updates
* **One schema, four UIs** — Add `step_mode: chatbot` or `one_by_one` to transform the same fields into a completely different interface

= Four UI modes from one schema =

* **Normal form** — Classic input → confirm → complete flow (default)
* **Step form** — Multi-page with progress bar (`steps` array)
* **One-by-one** — Question-at-a-time survey style (`step_mode: 'one_by_one'`)
* **Chatbot** — Chat bubble interface with typing animation, header, and timestamps (`step_mode: 'chatbot'`)

= Key features =

* 15 field types: text, email, tel, url, textarea, select, radio, checkbox, checkbox_group, number, date, file, honeypot, reCAPTCHA, privacy
* Conditional logic: show_if, required_if, skip_if (hide_if deprecated) — works in chatbot mode too
* Dynamic routing: to_rules, subject_rules, complete_redirect_rules, complete_html_rules based on submitted values
* Diagnosis mode: use `complete_html_rules` without `to` — show results without sending email
* Download after submit: `download_url` shows a download button on the complete screen (document request forms)
* Form availability window: `available_from` / `available_until` with custom before/after HTML
* Custom validation: `pattern`, `minlength`, `maxlength`, `error_message` schema keys + `hxfe_validate_field` and `hxfe_validate_form` filter hooks
* Field HTML injection: `before_html` / `after_html` schema keys
* Page slug tracking: subject auto-appended with `[form-id@page-slug]` for per-page aggregation
* Webhook support: send to Zapier, Make, Slack, or any HTTP endpoint
* SMTP built-in: Gmail, SendGrid, Mailgun, or custom SMTP
* File upload: attached to email, auto-deleted after send
* IP restriction and password-protected forms
* iframe embedding with per-form CORS control (`allowed_origins`)
* Zero cookies: GDPR/EU cookie-compliant by design
* Clean default styles: CSS custom properties (design tokens) for easy theme integration, responsive at 768px
* Schema examples panel in admin: 11 copy-paste samples to get started fast
* AI-friendly: ships with `llms.txt` and `ai-reference.md` for agentic coding tools
* Source code: available on [GitHub](https://github.com/okuboyouhei/hxfe-code-first-forms)

= Minimum example =

`
add_filter( 'hxfe_schemas', function( $schemas ) {
    $schemas['contact'] = [
        'id'      => 'contact',
        'to'      => 'admin@example.com',
        'subject' => 'Contact: {name}',
        'fields'  => [
            [ 'key' => 'name',  'type' => 'text',     'label' => 'Name',    'required' => true ],
            [ 'key' => 'email', 'type' => 'email',    'label' => 'Email',   'required' => true ],
            [ 'key' => 'body',  'type' => 'textarea', 'label' => 'Message', 'required' => true ],
            [ 'key' => 'hp',    'type' => 'honeypot' ],
        ],
    ];
    return $schemas;
} );
`

Shortcode: `[hxfe_form id="contact"]`

= Chatbot example =

`
$schemas['support'] = [
    'id'        => 'support',
    'to'        => 'admin@example.com',
    'step_mode' => 'chatbot',
    'bot_name'  => 'Support Bot',
    'bot_icon'  => '🤖',
    'greeting'  => 'Hi! How can I help you today?',
    'fields'    => [
        [ 'key' => 'name',  'type' => 'text',  'label' => 'Name',
          'bot_message' => 'What is your name?' ],
        [ 'key' => 'email', 'type' => 'email', 'label' => 'Email',
          'bot_message' => 'Thanks {name}! What is your email?' ],
        [ 'key' => 'hp', 'type' => 'honeypot' ],
    ],
];
`

= Diagnosis chatbot (no email) =

`
$schemas['diagnosis'] = [
    'id'        => 'diagnosis',
    // No 'to' — result shown without sending email
    'step_mode' => 'chatbot',
    'complete_html_rules' => [
        [ 'when' => ['plan', '==', 'basic'],
          'html' => '<h3>Basic plan recommended</h3><p>Hi {name}!</p>' ],
        [ 'when' => 'default',
          'html' => '<p>Thank you, {name}. We will be in touch.</p>' ],
    ],
    'fields' => [ ... ],
];
`

= Organize schemas in separate files =

`
// functions.php — one line
require_once get_template_directory() . '/inc/hxfe-forms.php';
`

Or use HXFE as a standalone plugin with `glob()` auto-loading.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate in **Plugins → Installed Plugins**
3. Add schemas via the `hxfe_schemas` filter in `functions.php`
4. Place `[hxfe_form id="your-id"]` in any page or post
5. View all registered forms at **Settings → Form Engine — Forms**

== Frequently Asked Questions ==

= Does HXFE save submissions to the database? =

No. HXFE sends email only. This is intentional — forms defined in code stay lightweight and free of database dependencies. Use Webhook support to send data to external services like Google Sheets or a CRM.

= Can I use HXFE without writing PHP? =

HXFE is designed for developers who want code-first form management. If you need a GUI builder, plugins like WPForms or Fluent Forms may be a better fit.

= Does the chatbot mode work with conditional logic? =

Yes. `show_if` conditions work in chatbot mode — hidden fields are automatically skipped. You can also use `{field_key}` placeholders in `bot_message` to reference previous answers.

= Is HXFE GDPR / EU cookie compliant? =

Yes. HXFE uses zero cookies. Form state is preserved via hidden JSON fields on the server side, not browser storage.

= Can I embed forms on external domains? =

Yes. Use `[hxfe_iframe id="contact" site="https://your-site.com"]` and configure allowed origins in **Settings → Form Engine → iframe / CORS Settings**.

= How do I connect to Zapier or Make? =

Add a `webhooks` array to your schema with the target URL. HXFE will POST form data as JSON after each successful submission. Webhook failures do not block form submission.

= Can I skip the confirmation screen? =

Yes. Add `'confirm' => false` to your schema. Works for normal forms and step forms.

= Can I restrict a form to specific IP addresses? =

Yes. Add `allowed_ips` to your schema with a list of IPs or CIDR ranges. Visitors outside the whitelist see a blocked message, which you can customize with `ip_blocked_html`.

`allowed_ips` => [ '192.168.1.0/24', '203.0.113.5' ],
`ip_blocked_html` => '<p>This form is only available on the campus network.</p>',

= Can I require a password to access a form? =

Yes. Add an `auth` key to your schema. To keep passwords out of Git, define them as constants in `wp-config.php` and reference them in the schema.

In `wp-config.php`: `define( 'HXFE_STAFF_PASS', 'your-secret-password' );`

In your schema:
`'auth' => [ 'users' => [ [ 'id' => 'staff', 'password' => defined('HXFE_STAFF_PASS') ? HXFE_STAFF_PASS : '' ] ] ]`

Brute-force protection is built in — access is locked for 15 minutes after 5 failed attempts.

= Can I prevent PHP files from being edited via the WordPress admin? =

Yes. Add this to `wp-config.php`:

`define( 'DISALLOW_FILE_EDIT', true );`

This disables the theme and plugin editors in the WordPress admin. It pairs well with HXFE's code-first approach — forms and code are managed via deployment, not the admin UI.

= Can I save uploaded files somewhere other than email attachments? =

HXFE deletes uploaded files from the server immediately after sending the email. This is intentional — keeping files on the server increases security risk and GDPR responsibility.

For permanent storage, the recommended approach is to use Gmail + Google Apps Script (GAS): set up a time-based trigger that reads incoming form emails and saves attachments to a designated Google Drive folder. This keeps files off your WordPress server entirely and lets you manage access through Google's permission system.

== External Services ==

This plugin optionally connects to Google reCAPTCHA when the `recaptcha` field type is enabled in a form schema.

**What the service is and what it is used for:**
Google reCAPTCHA is a spam-prevention service. When enabled, it loads a script from Google's servers and verifies the user's response server-side to determine whether the form submission is from a human or a bot.

**What data is sent and when:**
When a page containing an HXFE form with reCAPTCHA is loaded, the visitor's browser loads the reCAPTCHA script from `google.com`. On form submission, the reCAPTCHA token generated in the visitor's browser is sent to Google's verification endpoint (`https://www.google.com/recaptcha/api/siteverify`) along with your site key. No other form field data is transmitted to Google.

reCAPTCHA is **disabled by default**. It is only active when a site administrator adds a `recaptcha` field to a form schema and configures valid API keys in the plugin settings.

**Links:**
* Google reCAPTCHA Terms of Service: https://policies.google.com/terms
* Google Privacy Policy: https://policies.google.com/privacy

== Changelog ==

= 1.3.8 =
* Security: IP access control (`allowed_ips`) now uses REMOTE_ADDR only by default, ignoring forgeable proxy headers (X-Forwarded-For, etc.). Sites behind a trusted reverse proxy (e.g. Cloudflare) can opt in with `define( 'HXFE_TRUST_PROXY', true );` in wp-config.php, or scope it precisely via the `hxfe_trusted_proxy_headers` filter. **Action required** only if you use `allowed_ips` behind a proxy.
* Security: reCAPTCHA validation now fails closed in production when the secret key is missing (previously requests passed through unverified). Development environments with WP_DEBUG still skip validation for convenience.
* Security: Form password comparison for plain-text passwords now uses hash_equals() to avoid timing side channels.
* Security: CORS wildcard origin (`*`) no longer sends Access-Control-Allow-Credentials, avoiding the unsafe wildcard+credentials combination. Named origins are unaffected.
* Dev: Schema lint now warns when a `recaptcha` field has no secret key configured.

= 1.3.7 =
* Security: Removed skip_sanitize parameter from hxfe_process_fields() — all values are now sanitized on every call, including confirm→submit flow
* Security: Clarified that sanitize_text_field() and related functions are idempotent, so re-sanitizing already-sanitized values is safe

= 1.3.6 =
* Security: Added nonce verification to hxfe_handle_back() to prevent unauthorized form re-rendering
* Security: Back button now passes hxfe_nonce (hxfe_validate_{form_id}) via hx-vals
* Code: Clarified skip_sanitize=true comment in hxfe_handle_submit() to explain double-sanitize prevention

= 1.3.5 =
* Renamed schema key: `redirect_rules` → `complete_redirect_rules` (consistent with other `complete_*` keys)
* Renamed schema keys: `before_open_html` / `after_close_html` → `before_html` / `after_html`
* Renamed field keys: `min_check` / `max_check` → `min` / `max` (consistent with `min_date` / `max_date`)
* Deprecated field key `hide_if` — use `show_if` instead (backward compatible, will be removed in a future version)

= 1.3.4 =
* Added: Page slug auto-injected into subject as [form-id@slug] for per-page tracking
* Added: Schema examples panel in admin UI with 7 copy-paste samples
* Added: Responsive breakpoint at 768px
* Added: CSS custom properties (design tokens) for easy theme customization
* Added: `download_url` / `download_label` schema keys — download button on complete screen
* Added: `available_from` / `available_until` schema keys — form availability window
* Added: `before_html` / `after_html` schema keys — custom messages outside window
* Added: `allowed_origins` schema key — per-form iframe CORS restriction
* Added: Embed HTML `<iframe>` copy button in admin form list
* Improved: Admin UI redesigned — stat cards, clean table, monospace field type chips
* Improved: chatbot send button replaced with paper-plane SVG icon
* Improved: Login/auth screen with border, padding, full-width button
* Improved: iframe / CORS Settings tab removed — now schema-level only
* Fixed: hx-encoding="multipart/form-data" auto-applied when file field present
* Fixed: File name shown correctly on confirm screen
* Fixed: File attachment preserved through confirm → submit flow
* Fixed: Temporary file cleanup cron (hourly, 1h expiry)
* Fixed: Fade-in flash bug (remove+reflow caused visible flicker)
* Added: `complete_html_rules` schema key — conditional complete screen HTML (supports {field_key} interpolation)
* Added: Diagnosis/calculator mode — omit `to` with `complete_html_rules` for no-email chatbot
* Added: chatbot + `show_if` / `required_if` conditional fields now fully supported
* Improved: chatbot UI redesigned with LINE/Slack-style header, timestamps, and bubble shadows
* Added: `pattern` / `minlength` / `maxlength` / `error_message` schema keys for field-level validation
* Added: `hxfe_validate_field` filter for per-field custom validation
* Added: `hxfe_validate_form` filter for cross-field validation (password confirm, at-least-one, etc.)
* Added: `before_html` / `after_html` schema keys for injecting HTML around fields
* Added: `disable_context` schema key to opt out of page slug auto-injection

= 1.3.3 =
* Renamed: SHFE → HXFE across all PHP/JS/CSS (class names, IDs, function prefixes)
* Fixed: Scroll after htmx outerHTML swap (re-fetch element by ID after swap)
* Fixed: Step form Back button returning 403 (nonce was missing from hx-vals)
* Fixed: chatbot phpcs:ignore comment appearing as visible text in UI
* Fixed: Copy button fallback for HTTP environments (execCommand)
* Changed: Shortcode copy buttons labeled "Form" / "iFrame" for clarity

= 1.3.2 =
* Added: IP restriction — whitelist IPs and CIDR ranges per form (`allowed_ips`)
* Added: Customizable IP blocked message (`ip_blocked_html`)
* Added: Form-level password authentication (`auth.users`)
* Added: Brute-force protection — lockout after 5 failed attempts (15 min)
* Added: Auth session via secure httponly samesite=strict cookie
* Added: Login form labels fully customizable per form
* Added: Support for wp-config.php constants as password source (keeps secrets out of Git)

= 1.3.1 =
* Security: file field now uses HXFE's own safe MIME whitelist by default instead of WordPress defaults
* Security: added .htaccess to hxfe-uploads/ directory to prevent PHP execution
* Added: file field type now works — uploaded files are attached to admin notification emails
* Added: includes/file-upload.php with wp_handle_upload() based processing
* Added: mime_types schema key to whitelist allowed MIME types server-side
* Added: temporary files are automatically deleted after email is sent
* Removed: lint warning about file field being unimplemented

= 1.3.0 =
* Refactored: extracted field renderer functions to includes/fields/field-renderers.php
* Refactored: added hxfe_validate_step_request() to DRY up step endpoint gate logic
* Refactored: improved PhpDoc type definitions on core functions (hxfe_process_fields, hxfe_eval_condition, hxfe_interpolate)

= 1.2.2 =
* Fixed: confirm_label now correctly applies to the input form submit button
* Fixed: hxfe-front.js (scroll, focus, loading state) was not enqueued
* Fixed: disable_default_css and custom_css now work correctly
* Fixed: chatbot.js is now only loaded on pages with chatbot forms
* Fixed: uninstall.php now removes all plugin settings from the database
* Added: error_message schema key to customize the validation error summary text
* Added: confirm_label schema key (separate from submit_label for clarity)
* Note: file field type renders HTML but file saving is not yet implemented

= 1.2.1 =
* Added tel and url field types with built-in validation
* Added Webhook support (Zapier, Make, Slack, custom HTTP endpoints)
* Added label customization: submit_label, back_label, next_label, confirm_heading
* Fixed confirmation screen: radio/select now shows label instead of value
* Fixed confirmation screen: checkbox_group shows comma-separated labels
* Fixed confirmation screen: hidden fields (show_if=false) are now excluded
* Fixed mail body: empty fields and hidden fields are now excluded
* Added confirm: false support for step forms
* Added built-in placeholders: {site_name}, {site_url}, {date}, {time}
* Added admin form list page with lint warnings and shortcode copy buttons
* Strengthened schema lint: all 15 field types, cascade_from, chatbot bot_message
* Added wp_mail() failure logging when WP_DEBUG is enabled

= 1.2.0 =
* Added field types: radio, checkbox_group, number, date, file
* Added chatbot UI mode (step_mode: chatbot) with typing animation
* Added 4 completion patterns: message, custom HTML, redirect, redirect_rules
* Added default values (value key on any field)
* Added multiple recipients (to as array)
* Added confirm: false for immediate submission
* Added cascade select (cascade_from / cascade_options)

= 1.1.0 =
* Added step forms (groups and one_by_one mode)
* Added reCAPTCHA v2 and v3
* Added privacy policy field
* Added auto-reply email
* Added SMTP configuration (Gmail, SendGrid, Mailgun, custom)
* Added iframe embedding with CORS support
* Added conditional logic (show_if, required_if, skip_if, to_rules, subject_rules)
* Added CSS customization options

= 1.0.0 =
* Initial release
* Schema-driven form rendering
* htmx-powered input → confirm → complete flow
* Admin notification email and auto-reply
* Honeypot spam protection
