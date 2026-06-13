# HXFE — Code-First Forms

**Define WordPress forms as PHP arrays. AI-ready, Git-managed, zero database.**

[![WordPress Plugin Version](https://img.shields.io/badge/version-1.4.0-blue)](https://wordpress.org/plugins/hxfe-code-first-forms/)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)](https://php.net)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

[WordPress.org](https://wordpress.org/plugins/hxfe-code-first-forms/) · [Documentation](https://wordpress.org/plugins/hxfe-code-first-forms/) · [Report Issue](https://github.com/okuboyouhei/hxfe-code-first-forms/issues)

---

## What is HXFE?

HXFE is a code-first WordPress form plugin powered by [htmx](https://htmx.org/). Instead of building forms in a GUI, you define them as PHP arrays and place a shortcode anywhere.

**HXFE** stands for **htmx Form Engine**.

Because forms are PHP arrays, AI coding tools (Claude, Cursor, GitHub Copilot) can read and edit them directly — no screenshots, no GUI walkthroughs, no copy-paste. Ask your AI assistant to "add a phone number field" and get back a diff-ready code change instantly.

HXFE ships with `llms.txt`, `ai-reference.md`, and `CLAUDE.md` so AI agents understand the schema format out of the box — the lowest-cost way to build and maintain WordPress forms with AI.

```php
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
```

```
[hxfe_form id="contact"]
```

That's it. Your form is now Git-managed, deploy-safe, and AI-friendly.

---

## Why code-first?

| Problem with GUI builders | HXFE solution |
|---|---|
| AI can't edit forms directly | Forms are PHP arrays — AI reads and writes them natively |
| Forms stored in database | Forms live in your codebase |
| Can't track changes in Git | Every change shows in `git diff` |
| Forms disappear after deploy | Forms deploy with your theme |
| Hard to generate dynamic options | Just use PHP — `get_posts()`, taxonomies, etc. |

---

## Four UI modes from one schema

Add `step_mode` to transform the same fields into a completely different interface:

```php
// Normal form (default) — input → confirm → complete
$schemas['contact'] = [ 'fields' => [...] ];

// Chatbot UI
$schemas['contact']['step_mode'] = 'chatbot';
$schemas['contact']['greeting']  = 'Hi! How can I help you?';
$schemas['contact']['bot_name']  = 'Support Bot';

// One-by-one (survey style)
$schemas['contact']['step_mode'] = 'one_by_one';

// Step form with progress bar
$schemas['contact']['steps'] = [
    [ 'label' => 'Step 1', 'fields' => ['name', 'email'] ],
    [ 'label' => 'Step 2', 'fields' => ['body'] ],
];
```

---

## Key features

- **15 field types** — text, email, tel, url, textarea, select, radio, checkbox, checkbox_group, number, date, file, honeypot, reCAPTCHA, privacy
- **Conditional logic** — `show_if`, `required_if`, `skip_if`
- **Dynamic routing** — `to_rules`, `subject_rules`, `complete_redirect_rules` based on submitted values
- **Diagnosis mode** — use `complete_html_rules` without `to` to show results without sending email
- **Webhook support** — Zapier, Make, Slack, or any HTTP endpoint
- **Built-in SMTP** — Gmail, SendGrid, Mailgun, or custom SMTP
- **File upload** — attached to email, auto-deleted after send
- **IP restriction & password protection** — per-form access control
- **iframe embedding** — embed forms in non-WordPress sites with per-form CORS control
- **Zero cookies** — GDPR/EU cookie-compliant by design
- **AI-friendly** — ships with `llms.txt`, `ai-reference.md`, and `CLAUDE.md`

---

## AI-friendly by design

HXFE ships with AI-facing documentation:

```
hxfe-code-first-forms/
├── llms.txt          ← API summary for AI agents
├── ai-reference.md   ← Schema key reference with examples
└── CLAUDE.md         ← Context for Claude Code
```

Ask your AI assistant to add a field, change routing logic, or build a chatbot schema — it reads the schema directly from your code.

---

## Quick Reference

### Minimal contact form

```php
add_filter( 'hxfe_schemas', function( $schemas ) {
    $schemas['contact'] = [
        'id'      => 'contact',
        'to'      => 'admin@example.com',
        'subject' => 'Contact: {name}',
        'fields'  => [
            [ 'key' => 'name',    'type' => 'text',     'label' => 'Name',    'required' => true ],
            [ 'key' => 'email',   'type' => 'email',    'label' => 'Email',   'required' => true ],
            [ 'key' => 'message', 'type' => 'textarea', 'label' => 'Message', 'required' => true ],
            [ 'key' => 'hp',      'type' => 'honeypot' ], // always include
        ],
    ];
    return $schemas;
} );
```

`[hxfe_form id="contact"]`

---

### Field types (15 total)

`text` `email` `tel` `url` `number` `date` `textarea` `select` `radio` `checkbox` `checkbox_group` `file` `honeypot` `recaptcha` `privacy`

---

### Key schema keys

| Key | Type | Description |
|---|---|---|
| `id` | string | **Required.** Form ID (alphanumeric + hyphens) |
| `to` | string | Recipient email. Empty = no email sent (diagnosis mode) |
| `to_rules` | array | Dynamic routing: `[['when'=>[...],'to'=>'...']]` |
| `subject` | string | Email subject. Supports `{field_key}` interpolation |
| `fields` | array | **Required.** Array of field definitions |
| `step_mode` | string | `'chatbot'` or `'one_by_one'` |
| `bot_name` | string | Chatbot display name (chatbot mode) |
| `bot_icon` | string | Emoji or image URL (chatbot mode) |
| `greeting` | string | Chatbot opening message (chatbot mode) |
| `complete_message` | string | Completion message. Supports `{field_key}` |
| `complete_html` | string | Custom HTML on completion screen |
| `complete_html_rules` | array | Conditional HTML: `[['when'=>[...],'html'=>'...']]` |
| `complete_redirect` | string | Redirect URL after submission |
| `confirm` | bool | Show confirmation screen (default: true) |
| `webhooks` | array | Webhook definitions |
| `steps` | array | Step form: `[['label'=>'...','fields'=>['key1','key2']]]` |

---

### Key field keys

| Key | Type | Description |
|---|---|---|
| `key` | string | **Required.** Unique field identifier |
| `type` | string | **Required.** Field type |
| `label` | string | Field label |
| `required` | bool | Validation: required |
| `placeholder` | string | Input placeholder |
| `options` | array | For select/radio/checkbox_group: `[['value'=>'...','label'=>'...']]` |
| `bot_message` | string | **Required in chatbot mode.** Question text |
| `show_if` | array | Conditional display: `['field_key', 'operator', 'value']` |
| `required_if` | array | Conditional required |
| `skip_if` | array | Skip field if condition met |
| `before_html` | string | HTML inserted before field |
| `after_html` | string | HTML inserted after field |
| `min` / `max` | int | Min/max for number, checkbox_group |
| `min_date` / `max_date` | string | Min/max date (Y-m-d) |
| `maxlength` | int | Max character length |

---

### Conditional logic operators

`==` `!=` `>` `>=` `<` `<=` `contains` `not_contains`

```php
// Single condition
'show_if' => [ 'type', '==', 'other' ]

// AND
'show_if' => [ 'and', [
    [ 'budget', '==', 'high' ],
    [ 'size',   '==', 'large' ],
]]

// OR
'show_if' => [ 'or', [
    [ 'plan', '==', 'a' ],
    [ 'plan', '==', 'b' ],
]]
```

---

### Chatbot mode

```php
$schemas['support'] = [
    'id'        => 'support',
    'to'        => 'support@example.com',
    'subject'   => 'Support: {name}',
    'step_mode' => 'chatbot',
    'bot_name'  => 'Support Bot',
    'bot_icon'  => '🤖',
    'greeting'  => 'Hi! How can I help you today?',
    'fields'    => [
        [ 'key' => 'name',    'type' => 'text',  'label' => 'Name',
          'bot_message' => 'What is your name?',              'required' => true ],
        [ 'key' => 'email',   'type' => 'email', 'label' => 'Email',
          'bot_message' => 'Thanks {name}! What is your email?', 'required' => true ],
        [ 'key' => 'message', 'type' => 'textarea', 'label' => 'Message',
          'bot_message' => 'How can I help you?',             'required' => true ],
        [ 'key' => 'hp', 'type' => 'honeypot' ],
    ],
];
```

> **Note:** `bot_message` is required for every non-honeypot field in chatbot mode.

---

### Diagnosis mode (no email)

```php
$schemas['quiz'] = [
    'id'      => 'quiz',
    'to'      => '',  // no email sent
    'confirm' => false,
    'complete_html_rules' => [
        [ 'when' => [ 'plan', '==', 'basic' ],
          'html' => '<h2>Basic plan recommended</h2><p>Hi {name}!</p>' ],
        [ 'when' => [ 'plan', '==', 'premium' ],
          'html' => '<h2>Premium plan recommended</h2>' ],
        [ 'when' => 'default',
          'html' => '<p>Thank you, {name}.</p>' ],
    ],
    'fields' => [ ... ],
];
```

---

### What HXFE does NOT support

- Saving submissions to the WordPress database (use Webhooks → Google Sheets / Zapier instead)
- `rows` attribute on textarea (use CSS to control height)
- `{field_label}` interpolation — only `{field_key}` (the submitted value) is supported
- Repeater / nested fields
- File upload preview before submission

---

## Requirements

- WordPress 6.0+
- PHP 7.4+
- No build tools required

---

## FAQ

**Q: Does HXFE save submissions to the database?**
No. HXFE sends email only. Use Webhook support to send data to Google Sheets, a CRM, or any external service.

**Q: What is htmx and why does HXFE use it?**
[htmx](https://htmx.org/) is a lightweight JS library that adds AJAX behavior via HTML attributes — no build step, no npm, no React. HXFE uses it for the input → confirm → complete flow without page reloads. It fits naturally with WordPress's server-rendered PHP.

**Q: Can I use HXFE with AI coding tools?**
Yes — this is one of HXFE's strengths. Forms defined as PHP arrays can be read and edited directly by AI tools. HXFE ships with `llms.txt` and `ai-reference.md` for AI agents to reference. Ask Claude or Copilot to "add a phone number field" and get back a diff-ready code change.

**Q: Is HXFE GDPR compliant?**
Yes. Zero cookies. Form state is preserved via hidden JSON fields server-side, not browser storage.

**Q: Can I skip the confirmation screen?**
Yes. Add `'confirm' => false` to your schema.

**Q: Can I restrict a form to specific IP addresses or require a password?**
Yes. Use `allowed_ips` for IP restriction and `auth` for password protection. Both support `wp-config.php` constants to keep sensitive values out of Git.

---

## Security

- Nonce verification on all AJAX endpoints
- All input sanitized, all output escaped
- `hash_equals()` for password comparison (timing-attack safe)
- `REMOTE_ADDR`-only IP matching (proxy header spoofing prevented)
- reCAPTCHA fields fail closed in production if misconfigured
- Uploaded files deleted immediately after email delivery
- Passed WordPress.org manual security review

Found a vulnerability? Please report it via [GitHub Issues](https://github.com/okuboyouhei/hxfe-code-first-forms/issues) or email directly.

---

## Installation

1. Install from [WordPress.org](https://wordpress.org/plugins/hxfe-code-first-forms/)
2. Activate the plugin
3. Add schema to `functions.php`
4. Place `[hxfe_form id="your-id"]` shortcode

---

## License

GPL-2.0-or-later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).

---

## Author

**Youhei Okubo** — [WordPress.org](https://profiles.wordpress.org/youheiokubo/) · [Zenn](https://zenn.dev/youheiokubo) · [GitHub](https://github.com/okuboyouhei)
