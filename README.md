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
- Valid Pontifex API credentials for live planning/registration calls.
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
- `public/` — public-facing shortcode and presentation code.
- `admin/` — admin settings, registration overview, and export interface.
- `assets/` — frontend/admin CSS and JavaScript.
- `public/` — shortcode presentation, registration templates, and email templates.
- `composer.json` — PHP dependency declaration, including the Mollie SDK.
- `readme.txt` — legacy WordPress-format documentation and version history.

## License

GPL v2 or later, as documented by the project metadata.
## Audit and modernization status

The [15 October 2026 WordPress code audit](docs/WORDPRESS-AUDIT-2026-10-15.md) records confirmed risks, file-level observations, a staged modernization plan, and required tests. This audit is **not** an assertion that all files passed coding standards or that a release is ready.

**Important known limitation:** `includes/api/class-soap-client.php::sendRegistration()` currently returns success without calling the SOAP registration endpoint. A paid webhook may therefore be marked as processed without a verified registration in Pontifex. **Do not treat a payment confirmation as proof of exam registration.** Restore and test the real vendor SOAP contract before production acceptance.

The webhook lifecycle, privacy controls, CSV export, server-side validation, and dependency packaging also require additional work. Existing shortcode names, REST routes, option keys, and database structures must remain compatible unless a tested migration explicitly changes them.
