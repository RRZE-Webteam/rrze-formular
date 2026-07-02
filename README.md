# RRZE Formular

Simple forms for the block editor with automatic design, spam protection and secure mail delivery.

**Requires WordPress:** 6.8+  
**Requires PHP:** 8.2+  
**License:** [GPLv3 or later](https://www.gnu.org/licenses/gpl-3.0.html)

## Description

RRZE Formular lets editors create forms directly in the block editor. You define the fields and their order; design, markup, spam protection and mail delivery are handled automatically.

### Features

- Block editor integration (no shortcodes required)
- Form field types with section headings for longer forms
- Templates for FAU websites (contact, teaching, events, research, IT, public relations and more)
- Fixed sender address and name from the website configuration
- Recipient resolution: block setting → plugin default → site administrator e-mail
- Optional recipient name for block and default recipient
- Domain validation for recipient addresses
- Allowed domains from **RRZE Settings** (network) or from plugin settings when RRZE Settings is inactive
- Privacy link on every form (`/datenschutz` on German sites, `/privacy` otherwise)
- Publishing blocked when the required privacy page is not published
- Publishing blocked when a block recipient uses a domain that is not allowed
- Optional confirmation e-mails to the submitter
- Invisible anti-spam measures (honeypot, time token, rate limiting)
- SSO / logged-in user data via WordPress login or filter hook

## Installation

1. Upload the `rrze-formular` folder to `/wp-content/plugins/`.
2. Activate the plugin via the **Plugins** menu.
3. Configure allowed e-mail domains:
   - If **RRZE Settings** is active: Network Admin → RRZE Settings → Plugins → RRZE Formular
   - Otherwise: **Settings → RRZE Formular**
4. Create and publish a privacy page at `/datenschutz` (German) or `/privacy` (other languages).
5. Insert the **RRZE Formular** block in the editor.

## Usage

1. Add the block to a page or post.
2. Choose a template or build your own fields.
3. Optionally set a recipient e-mail and name on an allowed domain.
4. Publish the page (requires a published privacy page and valid recipient configuration).

## Frequently Asked Questions

### Can users set the sender e-mail address?

No. The sender always uses the site e-mail address and site name from WordPress.

### Where are allowed domains configured?

When **RRZE Settings** is active, allowed domains are managed network-wide for RRZE Formular. Otherwise use **Settings → RRZE Formular**.

### When are confirmation mails sent?

When enabled on the block and the submitter provides a valid e-mail address. If allowed domains are configured, the submitter address must match one of them.

### Why can I not publish a page with a form?

Publishing is blocked when either the required privacy page is missing or not published, or a form block uses a recipient address outside the allowed domains.

### How does SSO integration work?

If a user is logged in, name and e-mail can be appended to the operator mail. External SSO systems can supply data via the `rrze_formular_sso_user_data` filter.

## Hooks

| Hook | Purpose |
|------|---------|
| `rrze_formular_defaults` | Plugin settings structure |
| `rrze_formular_allowed_domains` | Allowed recipient domains |
| `rrze_formular_sso_user_data` | SSO user data for operator mails |
| `rrze_formular_resolved_recipient` | Resolved recipient after block/settings/default |
| `rrze_formular_templates` | Form templates in the block editor |
| `rrze_formular_token_ttl` | Anti-spam token lifetime |
| `rrze_formular_allowed_confirmation_email` | Whether a confirmation mail may be sent |



- Initial release

## Links

- [Plugin on GitHub](https://github.com/RRZE-Webteam/rrze-formular)
- [RRZE Webteam](https://www.wp.rrze.fau.de/)
