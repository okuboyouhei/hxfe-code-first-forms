# HXFE — Code-First Forms

**Define WordPress forms as PHP arrays. No GUI. No database. Git-managed.**

[![WordPress Plugin Version](https://img.shields.io/badge/version-1.3.7-blue)](https://wordpress.org/plugins/hxfe-code-first-forms/)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)](https://php.net)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

[WordPress.org](https://wordpress.org/plugins/hxfe-code-first-forms/) · [Documentation](https://wordpress.org/plugins/hxfe-code-first-forms/) · [Report Issue](https://github.com/okuboyouhei/hxfe-code-first-forms/issues)

---

## What is HXFE?

HXFE is a code-first WordPress form plugin powered by [htmx](https://htmx.org/). Instead of building forms in a GUI, you define them as PHP arrays and place a shortcode anywhere.

**HXFE** stands for **htmx Form Engine**.

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
| Forms stored in database | Forms live in your codebase |
| Can't track changes in Git | Every change shows in `git diff` |
| Forms disappear after deploy | Forms deploy with your theme |
| Hard to generate dynamic options | Just use PHP — `get_posts()`, taxonomies, etc. |
| AI can't edit the form directly | AI writes PHP arrays directly |

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

## Requirements

- WordPress 6.0+
- PHP 7.4+
- No build tools required

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
