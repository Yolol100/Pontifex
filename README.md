# Pontifex OI

> **Supporting engineering project · WordPress/PHP · SOAP API · Mollie · registration and payment flow**

**Developer profile:** [Andrew Baeten](https://github.com/Yolol100) · [Portfolio cases](https://andrewbaeten.nl/category/cases)

Pontifex OI is a WordPress integration for the Pontifex Open Inschrijvingen SOAP API. It exposes exam planning, candidate registration and payment-related frontend flows through WordPress.

The current plugin release is `1.0.1`.

## Main capabilities

- Retrieve and cache Pontifex exam planning data.
- Display exam planning through a shortcode.
- Provide a frontend registration flow for selected exams.
- Integrate Mollie payment handling for payment-enabled flows.
- Expose planning and pricing endpoints for the frontend.
- Process Mollie webhook callbacks through the plugin REST namespace.
- Run scheduled planning synchronization through WordPress Cron.
- Keep public templates and integration logic separated from the plugin bootstrap.

## Requirements

- WordPress 6.0 or newer.
- PHP 8.1 or newer.
- Valid Pontifex API credentials for live planning calls.
- Mollie credentials and the Composer dependency `mollie/mollie-api-php` for payment paths.

The source checkout can bootstrap planning/admin code without `vendor/`, but payment functionality requires the Composer dependencies to be installed or included in the deployed package.

## Installation

1. Place the plugin in `wp-content/plugins/pontifex-oi/` or install a prepared plugin package.
2. Make sure the required Composer dependencies are available when Mollie payments are used.
3. Activate **Pontifex OI** in WordPress.
4. Configure the Pontifex credentials through the deployment's supported configuration path.
5. Configure Mollie when payment processing is required.
6. Add the required shortcodes to the appropriate WordPress pages.
7. Verify planning, registration, payment return and webhook behaviour on staging before production use.

## Shortcodes

- `[pontifex_oi_planning]` — shows the available exam planning.
- `[pontifex_oi_registration]` — shows the registration flow for an exam.
- `[pontifex_oi_payment_success]` — shows the payment-success/confirmation view.

Keep the planning, registration and payment-success steps on pages that fit the intended user flow.

## REST API

The current plugin registers routes under `pontifex-oi/v1`, including:

- `POST /wp-json/pontifex-oi/v1/planning`
- `POST /wp-json/pontifex-oi/v1/price`
- `POST /wp-json/pontifex-oi/v1/webhook`

These routes are implementation surfaces for the plugin. Do not expose credentials, payment secrets or personal registration data in public debugging output or GitHub issues.

## Repository structure

- `pontifex-oi.php` — plugin bootstrap and runtime metadata.
- `includes/` — API, cron, registration, payment and helper logic.
- `admin/` — admin settings, registration overview, and export interface.
- `assets/` — frontend/admin CSS and JavaScript.
- `public/` — shortcode presentation, registration templates, and email templates.
- `composer.json` — PHP dependency declaration, including the Mollie SDK.
- `readme.txt` — WordPress-compatible plugin documentation and version history.
- `tests/` — regression smoke checks for HTML output and CSV export.
- `docs/` — audit findings, architectural decisions and follow-up priorities.
- `.github/workflows/` — repository-only CI (not part of an installable plugin ZIP).

## License

GPL v2 or later, as documented by the project metadata.

## Audit and modernization status

The [15 October 2026 WordPress code audit](docs/WORDPRESS-AUDIT-2026-10-15.md) records confirmed risks, file-level observations, a staged modernization plan, and required tests. The [per-file source review](docs/FILE-BY-FILE-REVIEW-2026-10-15.md) covers the repository by folder and filename, including remaining manual checks. This audit is **not** an assertion that all files passed coding standards or that a release is ready.

**Registration boundary:** `includes/api/class-soap-client.php::sendRegistration()` is documented in its code as an intentional no-op: it returns success but does not send an outbound registration. The webhook still names that stage `soap_sent`. Whether Pontifex should receive registrations is a business-contract question, not established by this repository. **A paid Mollie transaction is not proof of a registration in Pontifex.** If outbound registration is required, implement it against the verified vendor SOAP contract and staging tests; if registrations are meant to remain local, remove the misleading stage after compatibility testing.

The audit branch includes privacy/HTML-escaping improvements, protected CSV export, typed public REST/AJAX filters, reduced global mail side effects and SOAP tracing disabled by default. A clean WordPress CI regression exposed that the exam catalog could be missing from PHP globals and return a zero price; the catalog initialization was corrected and pricing/VAT tests now guard this behavior. Durable webhook handling, complete business-field validation, Composer/WPCS modernization and dependency packaging still require additional work. Existing shortcode names, REST routes, option keys, and database structures must remain compatible unless a tested migration explicitly changes them.
