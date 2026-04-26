## Pontifex OI

**Contributors:** andrewbaeten  
**Tags:** pontifex, exam registration, SOAP API, planning, payments, shortcodes  
**Requires at least:** 6.0  
**Tested up to:** 6.6  
**Requires PHP:** 8.1  
**Stable tag:** 1.0.0  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  
**Text Domain:** pontifex-oi  

Adds Pontifex exam planning, registration and payment functionality to WordPress using shortcodes.

### Description

Pontifex OI is a WordPress plugin that connects your website to the Pontifex Open Inschrijvingen SOAP API. It enables you to display exam planning, collect candidate registrations, and process payments directly within WordPress. 

The plugin uses a shortcode-based workflow, making it easy to place each step of the process on its own page. It is built with modern object-oriented PHP (8.1+), follows WordPress coding standards, and is designed to be extendable.

### Features

* **API Sync:** Display exam planning retrieved from the Pontifex SOAP API.
* **Frontend Workflow:** Registration forms for candidates via shortcodes.
* **Payments:** Seamless payment handling via Mollie integration.
* **Modern Stack:** REST API endpoints for frontend interaction and headless potential.
* **Automation:** Daily synchronization using WordPress Cron (03:15 local time).
* **Developer Friendly:** Object-oriented, Composer-ready, and overrideable templates.

### Installation

1. Upload the `pontifex-oi` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin via the Plugins menu in WordPress.
3. Provide your Pontifex API credentials using the provided filters or the plugin settings page.
4. Create separate WordPress pages and insert the required shortcodes.

### Shortcodes

For the best user experience, place each shortcode on a separate page.

* `[pontifex_oi_planning]`: Displays the available exam planning overview.
* `[pontifex_oi_registration]`: Displays the registration form for a selected exam.
* `[pontifex_oi_payment_success]`: Displays a confirmation message after a successful payment.

### REST API

The plugin registers the following endpoints:
* `POST /wp-json/pontifex-oi/v1/planning`: Returns cached exam planning data.
* `POST /wp-json/pontifex-oi/v1/price`: Returns dynamic pricing information.
* `POST /wp-json/pontifex-oi/v1/webhook`: Secured Mollie payment webhook.

### Requirements

* **PHP 8.1** or higher.
* **WordPress 6.0** or higher.
* Valid Pontifex API credentials.
* **Mollie API Key** for processing payments.

### Changelog

#### 1.0.0 (2025-10-07)
* Initial release.
* Implementation of SOAP API synchronization.
* Mollie payment integration.
