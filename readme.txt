=== Pontifex OI ===
Contributors: andrewbaeten
Tags: pontifex, inschrijven, examens, soap api, planning, registratie, mollie, shortcodes
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Pontifex planning, registration and Mollie payment flows for WordPress via shortcodes and REST endpoints.

== Description ==

Pontifex OI connects WordPress to the Pontifex Open Inschrijvingen SOAP API. The plugin can show exam planning, provide candidate registration flows and support Mollie payment handling.

Main capabilities:

* Retrieve and cache Pontifex exam planning data.
* Show planning and registration flows through WordPress shortcodes.
* Handle payment-success output for Mollie-enabled flows.
* Expose planning, pricing and webhook routes under `pontifex-oi/v1`.
* Run scheduled planning synchronization through WordPress Cron.

The plugin handles registration and payment-related data. Do not place credentials, payment secrets or personal candidate data in public logs, issues or screenshots.

== Installation ==

1. Upload the `pontifex-oi` plugin folder to `/wp-content/plugins/`, or install a prepared plugin package.
2. When payment functionality is used from a source checkout, make sure the Composer dependencies are available, including `mollie/mollie-api-php`.
3. Activate Pontifex OI in WordPress.
4. Configure the required Pontifex credentials through the deployment's supported configuration path.
5. Configure Mollie when payment processing is required.
6. Add the required shortcodes to the appropriate WordPress pages.
7. Verify planning, registration, payment return and webhook behavior on staging before production use.

== Shortcodes ==

* `[pontifex_oi_planning]` — shows the available exam planning.
* `[pontifex_oi_registration]` — shows the candidate registration flow.
* `[pontifex_oi_payment_success]` — shows the payment-success/confirmation view.

== REST API ==

The current plugin registers POST routes under `pontifex-oi/v1`:

* `/wp-json/pontifex-oi/v1/planning`
* `/wp-json/pontifex-oi/v1/price`
* `/wp-json/pontifex-oi/v1/webhook`

The webhook endpoint is an implementation surface of the payment flow. Do not publish secrets or payment payloads in public debugging output.

== Requirements ==

* WordPress 6.0 or newer.
* PHP 8.1 or newer.
* Valid Pontifex API credentials for live planning and registration calls.
* Mollie credentials plus the Composer payment dependency for Mollie-enabled flows.

The repository compatibility workflow verifies clean activation on WordPress 6.0 / PHP 8.1 and WordPress 7.1 / PHP 8.3.

== Changelog ==

= 1.0.1 =
* Current maintained release metadata aligned with the plugin bootstrap.
* WordPress compatibility coverage verified on the supported minimum runtime and WordPress 7.1 / PHP 8.3.
* Documentation aligned with the current shortcodes, REST routes and Mollie dependency boundary.

= 1.0.0 =
* Initial planning, registration and payment release.
