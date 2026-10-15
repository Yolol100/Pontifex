# Pontifex — diepgaande heraudit per bestand

**Peildatum:** 16 oktober 2026 · **Hoofdbranch:** `60df62443332a513d1d79e2ffca8612c9779f7c5` · **Eigenaar:** WordPress Quality Architect · **Onafhankelijke QA:** Website QA.

## Werkwijze, bewijsklassen en reikwijdte

De GitHub-tree van de hoofdbranch bevat **55 van 55 gevolgde bestanden, niet afgekapt**. De Python-auditor leest alle bytes en produceert per pad SHA256, LOC, functies en patroonsignalen. De uitgebreide PR-audit omvat daarnaast workflow en scannerbestanden. Belangrijke geld-, registratie-, SOAP-, AJAX- en beheerpaden zijn handmatig op risico beoordeeld. **Handmatige volledige semantische uitvoering van elk codepad is niet aangetoond:** zonder echte Mollie-, SOAP-, SMTP-, multisite- en browserstaging zijn die scenario's `not_run`. Een risicosignaal is geen bewezen fout.

**Referenties:** [gefaseerde auditprompt](AUDIT-PROTOCOL-2026-10-16.md), [releasechecklist](RELEASE-QA.md), [voorgaande bestandsreview](FILE-BY-FILE-REVIEW-2026-10-15.md), [open risico's #10-#12](https://github.com/Yolol100/Pontifex/issues), [WordPress plugins](https://developer.wordpress.org/plugins/), [Mollie webhooks](https://docs.mollie.com/reference/webhooks).

## Volledige matrix — geen overgeslagen bestanden

| Pad | Gebied | Bytes | Risicoprofiel | Concreet inspectieresultaat / nog te toetsen |
|---|---|---:|---|---|
| `.github/workflows/wordpress-compatibility.yml` | CI | 9542 | medium | WP6.0/PHP8.1 + WP7.1/PHP8.3, syntax/Composer/WordPress/CSV/JS smoke; Plugin Check is advisory, no sandbox. |
| `admin/class-admin.php` | Admin | 16038 | medium | Instellingen, submenu, AJAX en CSV: rollen/nonce grotendeels aanwezig; ajax_fetch_submissions() onderdrukt globale foutreporting en wist alle buffers. |
| `admin/page-soap-settings.php` | Admin | 3355 | medium | SOAP-credentialvelden; controleer origin, escaping, autocompletion en geen geheimen in screenshots. |
| `admin/page-submissions.php` | Admin | 2982 | medium | GET-filterstate, PII-zoekweergave en export; browser/role/accessibility-test open. |
| `assets/css/pontifex-oi-admin.css` | CSS | 11719 | low | 67 !important; admin scope/cascade tegenover WordPress core visueel testen. |
| `assets/css/pontifex-oi-payment-success.css` | CSS | 2887 | low | 18 !important; focus- en betaalstatusweergave op mobiel testen. |
| `assets/css/pontifex-oi-planning.css` | CSS | 22419 | medium | 301 !important; planningresponsive/layout vereist screenshotregressie voor cleanup. |
| `assets/css/pontifex-oi-registration.css` | CSS | 12646 | medium | 166 !important; validatie, focus, foutmelding, responsive visueel testen. |
| `assets/css/pontifex-oi-shared.css` | CSS | 8731 | medium | 115 !important; gedeelde en globale selectoren kunnen andere frontends beïnvloeden. |
| `assets/js/candidates.js` | JS | 15320 | medium | Dynamische kandidaatrijen en max12; arrayvolgorde, ID/label-toegankelijkheid en events hertesten. |
| `assets/js/config.js` | JS | 434 | low | Global compatibility shim, 10 regels; referenties traceren vóór verwijdering. |
| `assets/js/filters.js` | JS | 13726 | medium | Dynamische filterdropdowns, HTML sinks, storage, AJAX; providerdata als onbetrouwbaar behandelen. |
| `assets/js/form-validation.js` | JS | 7695 | high | BEWEZEN: globale kandidaatrij/bedragselectie kon bij 2 formulieren data mengen; in audit-PR per formulier gescoped + Node-fixfixture. |
| `assets/js/main.js` | JS | 3869 | medium | Eventbindingen, lokale state en conditionele initialisatie; herhaalde montage testen. |
| `assets/js/material.js` | JS | 16947 | medium | Weekend-/lesmateriaal keuzelogica en HTML-opbouw; prijspariteit en 5 consolelogs toetsen. |
| `assets/js/pagination.js` | JS | 3685 | medium | Genereert dynamische paginatie; HTML-escaping, focus/ARIA en leeg-uitslagscenario. |
| `assets/js/pontifex-oi-admin.js` | JS | 16995 | medium | Dynamische admin-kaarten, AJAX; 17 HTML-sinks, 6 consolecalls; stored-XSS/masking roltest. |
| `assets/js/pontifex-oi-custom.js` | JS | 3313 | low | Legacy prijs/weekend-fallback, mogelijk overlapping met material/price; bewijs nodig vóór merge/cleanup. |
| `assets/js/pontifex-oi-testmode.js` | JS | 1061 | high | Verwijdert HTML required-attributen in testmodus; server-side betaalvalidatie mag nooit afzwakken. |
| `assets/js/price.js` | JS | 18079 | high | 507 regels frontendprijzen, console/debug en AJAX; BTW, meerdere kandidaten, weekend versus PHP vergelijken. |
| `assets/js/prices-admin.js` | JS | 6858 | medium | Prijsbeheer en dynamische admin markup; overlap met pontifex-oi-admin.js onderzoeken. |
| `assets/js/sidebar.js` | JS | 17223 | medium | Filterstate, mobiele overlay; HTML-sinks en toetsenbord/focus, overlay-closing scenario. |
| `assets/js/table.js` | JS | 15519 | medium | SOAP-planning tabel/kaart, escape-fixtures groen; DOM-XSS en table/a11y browsertest open. |
| `assets/js/utils.js` | JS | 8471 | medium | REST-naar-AJAX-fallback, webstorage; race/failover en caching/dataretentie contract. |
| `AUDIT_PONTIFEX_OI_2026-04-26.md` | Documentatie | 962 | low | Historische samenvatting; geen actuele release-autorisatie of uitvoerbewijs. |
| `composer.json` | Build | 698 | medium | PHP ^8.1 en 8 classmap-paden komen overeen; afhankelijkheid mollie/mollie-api-php vereist productie-vendor. |
| `composer.lock` | Build | 14908 | medium | Dependencyversies gelockt; composer audit CI groen, exacte distributie-ZIP nog niet bewezen. |
| `docs/FILE-BY-FILE-REVIEW-2026-10-15.md` | Documentatie | 11186 | low | Eerdere 55-bestandsreview; deels achterhaalde regelaantallen na patches. |
| `docs/RELEASE-QA.md` | Documentatie | 5572 | high | Benoemt QA NO-GO, ontbrekende Mollie/SOAP/SMTP/ZIP/rollback-scenario's. |
| `docs/WORDPRESS-AUDIT-2026-10-15.md` | Documentatie | 21027 | medium | Legacy audit/issue-inventaris; WPCS en nieuwe confirmed fixes aanvullen. |
| `includes/api/class-soap-client.php` | PHP/SOAP | 17262 | high | SOAP-planning en MySQL-synchronisatie; request tracing uit; sendRegistration() is bewust no-op; providercontract ontbreekt. |
| `includes/class-frontend.php` | PHP/Frontend | 42660 | high | 987 regels: shortcode, REST, AJAX, prijzen, checkout; ongesaniteerde GET-array-filters in planning BEVESTIGD en patch + test in auditbranch. |
| `includes/config/producten-prijzen.php` | PHP/Config | 13923 | high | Catalogus globale arrays en aliassen; eerdere nulprijscatalogusbug opgelost, prijsbron/single source of truth nog open. |
| `includes/cron/fetch-planning-cron.php` | PHP/Cron | 2409 | medium | Scheduled planning en cache; foutlog, tijdzone, locking en externe SOAP-uitval simuleren. |
| `includes/frontend-asset-guard.php` | PHP/Assets | 1569 | medium | Dynamische conditionele asset-enqueues; shortcodes in builders/templates testen. |
| `includes/helpers/ajax-soap-fetch-handler.php` | PHP/AJAX | 2184 | medium | Beheer-only SOAP ophalen met capability/nonce; exception-masking/provideruitval controleren. |
| `includes/helpers/class-mail-helpers.php` | PHP/Mail | 6437 | high | Klant- en eigenaarsmails worden sequentieel verstuurd, bool gecombineerd; gedeeltelijk succes kan retry-dubbelmail geven. |
| `includes/helpers/class-payment-helpers.php` | PHP/Mollie | 19248 | high | Mollie API/prijs/btw/redirect en harde weekendprijs; geen betaal-sandbox of race/herhaalbetaalbewijs. |
| `includes/helpers/class-registration-validator.php` | PHP/Validation | 7073 | medium | Kandidaat/contact/prijsschema is server-side; datum- en businessvarianten smoke groen, volledige klantdata consent/retentie ontbreekt. |
| `includes/helpers/class-registrations.php` | PHP/Database | 4423 | high | SELECT-then-INSERT zonder UNIQUE order-ID; concurrent callbacks kunnen dubbele rijen opleveren; migratie nodig. |
| `includes/helpers/submissions-helpers.php` | PHP/Export | 21046 | medium | CSV-formule-escaping en PII-row flattening; definitieve privacyexport/eraser ontbreekt. |
| `includes/webhooks/webhook-handler.php` | PHP/Webhook | 7764 | high | Na complete directe return VÓÓR Mollie herverificatie: late refund/chargeback gemist; 2-daagse transients, race/idempotency/e-mailrisico. |
| `languages/pontifex-oi.pot` | Vertaling | 5348 | medium | POT lijkt uit 2025; nieuwe strings uit plugin ontbreken mogelijk; update via WP i18n tooling. |
| `pontifex-oi.php` | PHP/Bootstrap | 6254 | high | Pluginheader, includevolgorde, 3 tabellen, cron-migratie; DB order_id-index is NIET uniek. |
| `public/class-payment-success-shortcode.php` | PHP/Output | 5108 | medium | Vraagt Mollie status op; betaling expliciet niet hetzelfde als externe examenregistratie; token/cache-errors testen. |
| `public/emails/owner-notification-email-template.php` | PHP/E-mail | 17275 | medium | 54 PHPCS-escape-signalen (vaak htmlspecialchars false positive); PII escaping/mailclients, ontvangers validatie. |
| `public/emails/payment-success-email-template.php` | PHP/E-mail | 21754 | medium | 30 PHPCS-escape-signalen (vaak htmlspecialchars false positive); betaalclaim/PII/HTML weergave. |
| `public/planning-list.php` | PHP/Template | 11799 | medium | GET-planningwaarden, dynamische selectvelden en HTML; attributes, escaping en keyboard-flow controleren. |
| `public/registration-form.php` | PHP/Template | 31568 | high | BEVESTIGD: ongebruikte globale starts_with() veroorzaakte redeclare bij 2 renders; verwijderd + WP smoke. URL-query/form labels nog review. |
| `README.md` | Documentatie | 6202 | medium | Architectuuroverzicht en dev-runbook; release-ZIP/vendor/rollback ontbreken als afgeronde stap. |
| `readme.txt` | Documentatie | 3925 | medium | WordPress-pluginmetadata incl Requires PHP 8.1; licentie/i18n/privacy en vendorinstallatiecontrole. |
| `tests/js-render-safety-smoke.js` | Tests | 3003 | low | In-memory XSS HTML-fixtures; geen echte browser of CSS/keyboard. |
| `tests/wp-cli-security-smoke.php` | Tests | 13348 | medium | WordPress REST, CSV, prijzen/BTW, webhook state, mail/registraties; positief/negatief, geen live providers. |
| `tests/wp-cli-uninstall-smoke.php` | Tests | 1015 | medium | Single-site uninstall op wegwerp-WordPress; multisite/dataloss herstel niet afgedekt. |
| `uninstall.php` | PHP/Lifecycle | 2723 | high | 3 tabellen en eigen opties per site; multisite codepad aanwezig, geen echt multisite-integratietestbewijs. |

## Nieuwe bevestigde afwijkingen uit deze audit

| ID | Bewijs | Ernst | Afwijking en gerichte wijziging |
|---|---|---|---|
| A-01 | `public/registration-form.php` rond regels 99–101, tijdens shortcode-include | Hoog | Ongebruikte globale `starts_with()` was op iedere formulierrender gedeclareerd. Bij twee shortcodes in één PHP-request geeft herdeclaratie een fatal. Functie uit render-template verwijderd; PHP/WordPress runtime-smoke voor 2 renders toegevoegd. |
| A-02 | `assets/js/form-validation.js` rond 62/126 + vormtest met 2 formulieren | Hoog | Globaal `$('.pontifex-oi-candidate-row')` en `$('#payment_amount')` pakten kandidaten en bedrag buiten het ingestuurde formulier. Scoped selectors aan `$form` toegevoegd; browserloze Node-differentiële regressietest. **Twee identieke HTML-ID's in dezelfde pagina zijn nog een eigen browsertestrisico.** |
| A-03 | `includes/class-frontend.php:203–212` publieke planning-shortcode | Middel/hoog | URL-parameters gingen als ongevalideerde array rechtstreeks naar SOAP/planning. Reeds aanwezige REST/AJAX normalizer nu ook toegepast op GET (wp_unslash/type/allowlist) en malformed + geldig weekendscenario in WP-CLI-test. |
| A-04 | `includes/class-frontend.php:render_registration_shortcode()` publieke GET | Middel | Onverwachte arraywaarden of te lange URL-data kunnen het inschrijfformulier/de redirect ontregelen. Vooraf scalar/lengtebeveiliging en negatieve WordPress-shortcodefixture toegevoegd. |

## Harde bestaande releaseproblemen (niet als gefixt markeren)

| ID | Risico | Minimale acceptatie |
|---|---|---|
| B-01 | `includes/webhooks/webhook-handler.php`: `complete` return vóór verse Mollie-statuscheck. Late refund/chargeback blijft onverwerkt. | Een refund/chargeback state-machine met sandboxcheck en idempotente side effects; <https://docs.mollie.com/reference/webhooks>. |
| B-02 | Pending registratiegegevens vervallen na 2 dagen; de eerste betaalstatusmelding kan later aankomen. | Duurzame betaling→orderkoppeling, privacyretentiebeleid, late callback en recoverytests. |
| B-03 | `Registrations::save()` gebruikt SELECT→INSERT op `order_id` zonder unieke DB-constraint; semantisch geen atomaire idempotentie. | Migratieplan bestaande duplicaten, back-up/rollback, unieke index en parallelle webhookfixtures. |
| B-04 | `MailHelpers::send_inschrijving_mails()` verstuurt twee mails, en blijft slechts één samengestelde bool geven: gedeeltelijke aflevering kan bij retry dubbele mail veroorzaken. | Status per ontvanger/side effect, uncertainty/reconciliation, geen blinde retry. |
| B-05 | `SoapClient::sendRegistration()` is opzettelijke no-op. | Officieel extern registratiecontract: volstaat lokale inschrijving of verplicht providerstap? Vendor/staging vereist. |
| B-06 | Geen aantoonbare WordPress privacy-exporter/eraser voor persoonsgegevens in custom registratietabel. | Privacybeleid, rechtmatige retentie, exporter/eraser of gedocumenteerde uitzonderingen volgens [WordPress Privacy](https://developer.wordpress.org/plugins/privacy/). |
| B-07 | PHP/JS/CSS naming/style debt, ontbrekende release ZIP, ontbrekende uitvoerige browser-/WCAG- en providerproeven. | WPCS prioriteitsfixes, Plugin Check-triage, distributie-hash en stagingacceptatie. |

## Officiële kwaliteits- en compatibiliteitscontroles

- Exact SHA-gebonden GitHub Actions voor PHP 8.1/WordPress 6.0 en PHP 8.3/WordPress 7.1; PHP syntax, Node syntax, XSS-, CSV-, REST-, kandidaat-, BTW-, uninstall- en optioneel Plugin Check-advies.
- Iedere file komt terug in `repository-audit.json` en `repository-audit.md`; artifacts worden 30 dagen op de GitHub Actions-run bewaard. Source snapshot heeft SHA256.
- WordPress Coding Standards-scan met `wp-coding-standards/wpcs`: **12.124 errors + 890 warnings**, waaronder veel mechanische whitespace/naming issues. Dit is **niet** het aantal functionele bugs. 103 output-escaping signalen, 47 MissingUnslash, 32 InputNotSanitized, 13 interpolated SQL signalen verdienen handmatige triage; `htmlspecialchars()` in emailtemplates is vaak een parserfalse-positive.
- Geen automatische php-cs-fixer over alle bestanden: codeformats, autoloading, backward compatibiliteit en visuele CSS-regressies eerst onderbouwen.

## Releasebesluit en grenzen

**Broncodeaudit: gedeeltelijk bevestigd. Productie: NO-GO.** De drie nieuwe regressiefixes zijn op een afgeschermde auditbranch geplaatst; nooit betaalprovider- of SOAP-calls uitvoeren om een broncodecheck als productieproef te presenteren. Bestaande release-issues #10–#12 blijven open. CI toont enkel de scenario's die echt zijn gedraaid. Voor ontbrekende sandbox-/SMTP-/browsertests: `not_run`.
