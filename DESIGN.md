# HXFE — Design System & Customization Guide

HXFE uses CSS custom properties (variables) for all visual styling.
Override any variable in your theme CSS to customize the appearance.

---

## CSS Variables (Design Tokens)

All tokens are scoped to `.hxfe-wrap`. Override them in your theme:

```css
.hxfe-wrap {
    --hxfe-color-primary:      #2563eb;  /* Main color — buttons, focus rings, accents */
    --hxfe-color-primary-dark: #1d4ed8;  /* Hover state of primary color */
    --hxfe-color-error:        #dc2626;  /* Error messages and borders */
    --hxfe-color-border:       #e2e8f0;  /* Input borders, dividers */
    --hxfe-color-border-focus: #2563eb;  /* Input focus border */
    --hxfe-color-bg:           #ffffff;  /* Input / card backgrounds */
    --hxfe-color-bg-subtle:    #f8fafc;  /* Subtle backgrounds (confirm screen, etc.) */
    --hxfe-color-text:         #0f172a;  /* Primary text */
    --hxfe-color-text-muted:   #64748b;  /* Secondary / placeholder text */
    --hxfe-color-text-label:   #334155;  /* Field labels */
    --hxfe-radius-sm:          4px;      /* Small radius (tags, badges) */
    --hxfe-radius-md:          8px;      /* Medium radius (inputs, buttons) */
    --hxfe-radius-lg:          12px;     /* Large radius (cards, confirm screen) */
    --hxfe-font-size-sm:       0.8125rem; /* Small text (labels, hints) */
    --hxfe-font-size-base:     0.9375rem; /* Base text size */
    --hxfe-spacing-field:      1.5rem;   /* Vertical gap between fields */
    --hxfe-shadow-sm:          0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
    --hxfe-shadow-md:          0 4px 12px rgba(0,0,0,.08), 0 1px 3px rgba(0,0,0,.04);
}
```

---

## Customization Examples

### Brand color change

```css
.hxfe-wrap {
    --hxfe-color-primary:      #0ea5e9;  /* Sky blue */
    --hxfe-color-primary-dark: #0284c7;
    --hxfe-color-border-focus: #0ea5e9;
}
```

### Softer, rounded style

```css
.hxfe-wrap {
    --hxfe-radius-md: 12px;
    --hxfe-radius-lg: 20px;
    --hxfe-color-border: #f1f5f9;
    --hxfe-shadow-sm: 0 2px 8px rgba(0,0,0,.08);
}
```

### Sharp / flat style

```css
.hxfe-wrap {
    --hxfe-radius-sm: 0;
    --hxfe-radius-md: 2px;
    --hxfe-radius-lg: 4px;
    --hxfe-shadow-sm: none;
    --hxfe-shadow-md: none;
}
```

### Dark mode

```css
@media (prefers-color-scheme: dark) {
    .hxfe-wrap {
        --hxfe-color-bg:           #1e293b;
        --hxfe-color-bg-subtle:    #0f172a;
        --hxfe-color-border:       #334155;
        --hxfe-color-text:         #f1f5f9;
        --hxfe-color-text-muted:   #94a3b8;
        --hxfe-color-text-label:   #cbd5e1;
        --hxfe-color-border-focus: #60a5fa;
    }
}
```

### Per-form customization

```css
/* Only apply to a specific form */
#hxfe-wrap-contact {
    --hxfe-color-primary: #16a34a;  /* Green for contact form */
}
```

---

## Disabling Default Styles

To disable HXFE's default stylesheet entirely and build from scratch:

```php
add_action( 'wp_enqueue_scripts', function() {
    wp_dequeue_style( 'hxfe' );
}, 20 );
```

Then add your own stylesheet with the same class names.

---

## Class Reference

| Class | Description |
|---|---|
| `.hxfe-wrap` | Root wrapper — scope for all CSS variables |
| `.hxfe-field` | Individual field wrapper |
| `.hxfe-label` | Field label |
| `.hxfe-input` | Text / email / tel / url / number / date input |
| `.hxfe-textarea` | Textarea |
| `.hxfe-select` | Select dropdown |
| `.hxfe-btn` | Base button class |
| `.hxfe-btn-submit` | Submit button |
| `.hxfe-btn-back` | Back button (step / confirm screen) |
| `.hxfe-confirm-list` | Confirmation screen field list |
| `.hxfe-complete` | Completion message wrapper |
| `.hxfe-chatbot-wrap` | Chatbot UI root |
| `.hxfe-chatbot-bubble--bot` | Bot message bubble |
| `.hxfe-chatbot-bubble--user` | User message bubble |
| `.hxfe-chatbot-choice-btn` | Choice button (radio/select in chatbot mode) |
| `.hxfe-error-msg` | Field-level error message |
| `.hxfe-field--error` | Field wrapper when validation fails |

---

## Design Philosophy

HXFE's default styles follow a "subtraction design" approach:

- Minimal dependencies — no external CSS frameworks
- Theme-friendly — styles are scoped and don't bleed into the rest of the page
- Token-first — every visual decision is a CSS variable, not a hardcoded value
- AI-readable — this file exists so AI agents can generate accurate customizations

