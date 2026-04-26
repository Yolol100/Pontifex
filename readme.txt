=== Pontifex OI ===
Contributors: andrewbaeten
Tags: pontifex, exam registration, SOAP API, planning, payments, shortcodes
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: pontifex-oi

Adds Pontifex exam planning, registration and payment functionality to WordPress using shortcodes.

== Description ==
Pontifex OI connects your WordPress site to the Pontifex Open Inschrijvingen SOAP API.

== Installation ==
1. Upload the `pontifex-oi` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Configure SOAP and Mollie settings under Pontifex OI in wp-admin.

== Shortcodes ==
* `[pontifex_oi_planning]`
* `[pontifex_oi_registration]`
* `[pontifex_oi_payment_success]`

== REST API ==
* `POST /wp-json/pontifex-oi/v1/planning`
* `POST /wp-json/pontifex-oi/v1/price`
* `POST /wp-json/pontifex-oi/v1/webhook`

== Changelog ==
= 1.0.0 =
* Initial release.
