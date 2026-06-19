# Security Policy — HXFE — Code-First Forms

## Supported Versions

| Version | Supported |
|---------|-----------|
| 1.4.x   | ✅ Active |
| 1.3.x   | ⚠️ Critical fixes only |
| < 1.3   | ❌ No longer supported |

Upgrade to the latest version is always recommended.

---

## Reporting a Vulnerability

**Please do not report security vulnerabilities in the public WordPress.org support forum.**

Contact via the WordPress.org support forum with the subject prefix `[Security]`, or reach out through the GitHub repository:

- **GitHub:** https://github.com/okuboyouhei/hxfe-code-first-forms
- **Subject line:** `[HXFE Security] Brief description`

### What to include

- Plugin version
- WordPress version and PHP version
- Steps to reproduce
- Expected vs actual behavior
- Impact assessment if known

### Response timeline

| Step | Target |
|------|--------|
| Acknowledgement | Within 3 business days |
| Assessment | Within 7 business days |
| Fix release | Within 30 days (critical: as soon as possible) |

---

## Security Design

HXFE is designed with a minimal attack surface:

- **No database writes** — Submissions are not stored. There is no submission table to attack or leak.
- **No GUI** — All configuration lives in PHP code. There is no admin UI that processes user-supplied schema definitions.
- **No external dependencies** — htmx is bundled and pinned. No npm, no CDN calls at runtime.
- **Schema lives in code** — Form definitions are PHP arrays in version-controlled files, not stored in the database.

### Security measures in place

| Area | Measure |
|------|---------|
| Spam | Honeypot field (built-in), reCAPTCHA v2/v3 (optional) |
| IP restriction | `allowed_ips` per schema, CIDR supported |
| Proxy headers | Opt-in only (`HXFE_TRUST_PROXY`). `X-Forwarded-For` is ignored by default. |
| reCAPTCHA | Fail-closed in production — submissions blocked when secret key is missing or score too low |
| File upload | Attached to email only. Never saved to Media Library or web-accessible paths. |
| Password auth | `hash_equals()` comparison, brute-force lockout (5 attempts / 15 min), httponly + samesite=strict cookie |
| Error logging | Logs written outside webroot; protected by `.htaccess`. Not stored in DB. |
| Custom CSS | Sanitized via `wp_kses_post` |

---

## Forking and Self-Maintenance

HXFE is licensed under GPLv2 or later. You are free to fork and maintain your own version.

If you are concerned about long-term maintenance by a solo developer, see `MAINTENANCE.md` for:

- Plugin architecture overview
- How to update the bundled htmx version
- Common modification patterns
- Fork-friendly notes

The plugin's code-first design means modifications are localized and predictable. There are no compiled assets, no build steps, and no external services required for core functionality.

---

## Disclosure Policy

- Security fixes are released as soon as possible after confirmation.
- The fix version and a brief description are noted in the changelog (`readme.txt`).
- Critical vulnerabilities may be disclosed publicly after a fix is available and users have had reasonable time to update.
- We follow responsible disclosure — please allow time for a fix before public disclosure.

---

## Known Limitations

- This plugin is maintained by a solo developer. Response times may vary.
- SMTP email delivery depends on the hosting environment or a third-party SMTP plugin.
- File upload virus scanning is not performed — validate `mime_types` carefully and rely on server-level scanning if required.

---

*Last updated: 2026-06-19*
