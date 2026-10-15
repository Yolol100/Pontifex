# Pontifex OI — WordPress code-audit en moderniseringsplan

**Datum:** 15 oktober 2026  
**Bronbaseline:** `main` op commit `2547a04ff34510e2bff052defeabc4b78dc64151`  
**Plugin:** 1.0.1 · WordPress >=6.0 · PHP >=8.1  
**Status:** gefaseerde broncodebeoordeling; **geen release- of staginggoedkeuring**.

## Werkwijze en beperkingen

De GitHub-repositoryboom is integraal geïnventariseerd (64 bestanden; GitHub tree niet afgekapt). De belangrijkste PHP-, JS-, frontend-, admin- en configuratiebestanden zijn via GitHub gelezen; de hieronder genoemde issues zijn aan concrete implementaties gekoppeld. Dit is nog **geen** volledige runtime-, line-by-line WPCS-, accessibility-, PHP-unit- of integratietest. Een lokale clone / PHP-CLI was in deze Chat-sessie niet beschikbaar. De bestaande Actions-workflow is gelezen maar niet opnieuw uitgevoerd. Externe SOAP- en Mollie-sandboxstromen moeten op staging worden getest.

Vervang deze bevindingen niet door de historische `AUDIT_PONTIFEX_OI_2026-04-26.md`: die meldt onder meer een verouderde bootstrap- en README-status.

## Bevestigde bevindingen, op prioriteit

| ID | Ernst | Bestand | Probleem | Aanpak / bewijs |
|---|---|---|---|---|
| P01 | **Kritiek** | `includes/api/class-soap-client.php` | `sendRegistration(array $order)` retourneert letterlijk `true` zonder SOAP-verzoek. De webhook markeert daarna de fase `soap_sent` als geslaagd. **Een betaling bewijst dus geen aflevering aan Pontifex.** | Herstel pas met het geverifieerde Pontifex SOAP-contract, sandbox-fixtures, idempotency-key en integratietests; anders expliciet fail-closed. |
| P02 | **Hoog** | `assets/js/form-validation.js` | Complete registratie/`ajaxData` werd in browserconsole gelogd; foutlog kon serverresponse tonen. | **Branchfix uitgevoerd:** verwijder persoonsgegevens uit consolelogs. Browser/privacy-test nodig. |
| P03 | **Hoog** | `assets/js/pontifex-oi-admin.js` | Inschrijvingsvelden en titel werden onescaped in HTML-strings ingevoegd; response werd gelogd. Potentieel stored XSS in beheerscherm. | **Branchfix uitgevoerd:** tekstescapen bij kaartopbouw en ruwe responseconsolelog verwijderen. XSS-fixturetest vereist. |
| P04 | **Hoog** | `admin/page-submissions.php` | CSV-export via `GET export_submissions` mist nonce en draait tijdens admin-page rendering. | Verplaats export naar `admin_post_*` handler met `manage_options`, nonce en `wp_safe_redirect` bij fouten; controleer Excel/CSV formula injection en exportlimiet. |
| P05 | **Hoog** | `includes/webhooks/webhook-handler.php`, `includes/helpers/class-registrations.php` | Webhookstappen slechts 7 dagen in transient; `order_id` is in SQL niet uniek; een eerder bestaand record kan opnieuw SOAP/mail doorlopen. Ook bij verlopen tijdelijke orderdata kan de flow niet herstelbaar zijn. | Duurzame status op payment-ID, unieke DB-constraint na duplicaatmigratie, idempotente externe registratie, retry/reconciliation. Test simultane callbacks en herstel na fouten. |
| P06 | **Hoog** | `includes/webhooks/webhook-handler.php`, `includes/helpers/class-payment-helpers.php` | Webhooksecret is optioneel en als queryparameter verwerkt. Zelfs met Mollie API-herverificatie blijft het verstandig initiatie-/originecontrole, replay- en rate-control te verifiëren. | Veilige webhook-identiteitscontrole en secret-rotatie, zonder live URL's te breken. |
| P07 | **Hoog** | `assets/js/pontifex-oi-testmode.js`, `includes/class-frontend.php` | Testmodus verwijdert frontend `required`-attributen; payment endpoint controleert vooral kandidaat-count en bedrag en valideert overige registratievelden niet aantoonbaar volledig server-side. | Eén serverside schema; dezelfde noodzakelijke validatie in test/live, veilige test-fixtures. |
| P08 | **Middel** | `includes/class-frontend.php`, `public/registration-form.php` | Geneste invoer rechtstreeks uit `$_POST`/`$_GET`, herhaald `sanitize_text_field` zonder overal `wp_unslash` en typechecks; `array_map` op onverwachte types kan warnings/fouten geven. | Per veld type valideren, unslashen, sanitize en enum/length-limieten; negatieve tests. |
| P09 | **Middel** | `includes/class-frontend.php` | Twee prijsroutes en meerdere PHP/JS-prijsregels plus hardcoded weekendprijs; mogelijke afwijking tussen scherm- en serverberekening. | Server als enige prijswaarheid; regressietests voor BTW, kandidaten, weekends, materialen en onbekende IDs. |
| P10 | **Middel** | `includes/class-frontend.php`, `includes/frontend-asset-guard.php` | Frontend registreert assets aanvankelijk breed; guard verwijdert hooks afhankelijk van inhoud/slugs. | Assets alleen waar nodig en betrouwbare shortcode-/block-/template-detectie; test dynamische content en page builders. |
| P11 | **Middel** | `includes/api/class-soap-client.php` | SOAP trace staat op `1` terwijl credentials worden gebruikt; endpoint uit instellingen krijgt geen expliciete HTTPS/host-controle; extern timeout/response- en foutbeleid moet expliciet. | Standaard trace uit, endpointbeleid, veilige foutlogging en sandbox-validatie. |
| P12 | **Middel** | `uninstall.php` | Multisitepad verwijdert opties per site maar custom tabellen slechts voor de huidige site; `pontifex_oi_cron_event` wijkt af van actieve `pontifex_oi_cron_fetch_planning`; opties zijn onvolledig opgesomd. | Consistente multisite-cleanup/retentiebeleid; test uninstall op testsite met PII. |
| P13 | **Middel** | `composer.json`, `pontifex-oi.php` | Composer vereist `php:^8.0`, terwijl plugin minimum 8.1 is. PSR-4 map `PontifexOI\\` naar `includes/`, maar diverse codeklassen staan in `includes/helpers/`, `includes/api/`, `includes/class-frontend.php` en `admin/` met bestandsnamen die niet volledig PSR-4 matchen. | Composerconstraint op 8.1 uitlijnen na lockfile-check. Eerst autoloadmapping/callers analyseren; geen blinde rename. |
| P14 | **Middel** | `README.md`, `readme.txt` | README noemt een `templates/` map die niet bestaat. `Tested up to: 7.1` en geclaimde CI-verificatie zijn opnieuw te bewijzen op de huidige head. | Documentatie naar feitelijke paden, tests en publicatievorm bijwerken. |
| P15 | **Middel** | `.github/workflows/wordpress-compatibility.yml` | Goede gehashte Actions en min/max bootstrapmatrix, maar veel `grep`-invarianten toetsen letterlijke tekst in plaats van gedrag; geen zichtbare PHPUnit/PHPCS/Plugin Check-testsetup. | Voeg WordPressCS, Plugin Check, en transactionele integratietests toe; pin dependencies/actions; readback van echte head. |
| P16 | **Laag–middel** | `admin/`, `public/`, `assets/` | Procedurele templates, gemengde conventies, veel consoledebug, gedeeltelijk Engelstalige/Nederlandstalige namen, historische patchcomments, globals en dubbele prijs/admin-scripts. | Eenheid in naamgeving/structuur na contractanalyse; houd shortcode IDs, option keys, database/tablenamen en externe hooks compatibel. |
| P17 | **Middel** | `includes/helpers/class-mail-helpers.php` | Globale filter `wp_mail_charset` wordt bij laden geregistreerd; dit kan mail van andere plugins beïnvloeden. | Beperk mailinstellingen tot eigen mails en test mailheaders, retry en mailfailures. |

## Map- en bestandsinventaris (bronreview)

- **Root:** `pontifex-oi.php` bevat versie/header, tabelschema, activatie, cronmigratie en globale includes; `uninstall.php` heeft levenscyclusgaten. `README.md` en `readme.txt` zijn aanwezig; historische audit is niet actueel; `composer.lock` aanwezig.
- **`includes/api/`:** SOAP-planning en registratie, grootste bedrijfskritische mismatch (P01), opslag/reconciliation en foutafhandeling.
- **`includes/cron/`:** dagelijkse planning en opgeschoonde records; tijdzone, conflictvrij plannen, race/lock en schema migraties gericht testen.
- **`includes/helpers/`:** registratie, mail, prijs, CSV/kaartvelden en admin AJAX; bevatten security- en duurzaamheidspunten.
- **`includes/config/`:** product- en prijsmapping; te consolideren na prijsregressietests.
- **`includes/webhooks/`:** Mollie-callback met lock en gefaseerde transients; controle op replay, persistente stages en retry.
- **`admin/`:** instellingen, credentials, AJAX, persoonsdataoverzicht en CSV-export; prioriteit escaping en autorisatie.
- **`public/`:** planning, registratie, succes-shortcode, mailtemplates; meerdere hardcoded permalinks; mogelijk misleidende succesbevestiging zolang P01 bestaat.
- **`assets/js/`:** filters, tabel, kandidaten, materiaal, prijzen, frontendvalidatie, admin en testmodus. Modules gebruiken jQuery, globals en HTML-stringrendering; gerichte QA voor DOM-XSS en redundante listeners.
- **`assets/css/`:** vijf gescheiden stylesheets voor shared, admin, planning, registratie en succes; cascade/scope, WCAG en ongebruikte regels nog visueel onderzoeken.
- **`languages/`:** POT aanwezig; vertaalbaarheid en dynamische strings controleren.
- **`.github/workflows/`:** compatibiliteitsmatrix, dependency audit en een aantal privacy-asserties aanwezig, maar onvoldoende voor diepgaande runtime- en securityzekerheid.

## Gewenste structuur (voorstel, niet blind doorvoeren)

```
pontifex-oi/
├── pontifex-oi.php                # kleine, stabiele bootstrap
├── uninstall.php                  # expliciet, volledig dataretentiebeleid
├── composer.json / composer.lock
├── readme.txt / README.md
├── includes/
│   ├── class-plugin.php           # lifecycle + hooks
│   ├── Admin/                      # instellingen, export, permission checks
│   ├── Application/                # registratie + betalingsorkestratie
│   ├── Infrastructure/             # SOAP, Mollie, repositories, cron
│   ├── Http/                       # REST/AJAX/webhook adapters
│   └── Support/                    # validatie, logging, formatteren
├── templates/                     # planning, registratie, e-mails
├── assets/{css,js}/
├── languages/
├── tests/{Unit,Integration}/
└── .github/workflows/             # buiten distributie-ZIP
```

Dit is **geen verplichte WordPress-mappenstructuur**: de huidige `admin/`, `includes/`, `public/` conventie is op zichzelf geldig. Kies PSR-4 alleen als namespaces, classmap en deployment consequent op elkaar aansluiten. Rename uitsluitend samen met alle callers, templates en hooks en na werkende regressietests.

## Fasering en acceptatie

1. **Fase 1: inventarisatie & risicobeperking.** Baseline/SHA vastleggen; persoonsgegevens niet loggen, adminrendering veilig maken, audit en architectuurdocumentatie vastleggen. **Dit is de huidige fase.**
2. **Fase 2: bedrijfslogica & beveiliging.** SOAP-registratie via vastgelegd vendorcontract, webhook-idempotency, duurzame transactiestatus, server-side validatie, CSV-export naar veilige handler. Geen live betalingen zonder sandboxproef.
3. **Fase 3: onderhoudbaarheid.** Scheid verantwoordelijkheid in kleinere modules, corrigeer Composer/autoload, consolideer prijsberekening en assetloading, pas WPCS/PHPCS en escaping consequent toe. Breek geen externe identifiers of databankschema zonder migratie.
4. **Fase 4: tests, release en documentatie.** PHPUnit en integratiefixtures, WordPress 6.0/PHP 8.1 + doelruntime, Mollie testbetalingen/webhook retries, SOAP sandbox, JS DOM-XSS/browser/WCAG, Plugin Check, composer audit, zip-verificatie en rollback. Update README/readme/changelog alleen met geverifieerde claims.

## QA-matrix voor de eerstvolgende herstelronde

- HTML-payload in titel/labels/waarden van inschrijving verschijnt uitsluitend als tekst.
- Frontendformulieren produceren geen persoons-/betalingsdata in browserconsole, ook niet bij foutresponses.
- CSV: onbevoegde/CSRF-verzoeken blokkeren; Excel-injectiefixtures krijgen veilig exportgedrag.
- Mollie: webhook vóór en ná de 7-daagse grens, dubbele gelijktijdige callbacks, pending/paid/refund/chargeback, provider-timeout, ontbrekende ordertransient.
- SOAP: contractbevestigde `sendRegistration`, fout/timeout, retry en aantoonbare externe ontvangst.
- Data: migratie met reeds bestaande `order_id` duplicaten, multisite-uninstall, cron-activatie en opties.

**Geen automatische merge/deploy.** PR-checks op de laatste SHA en staging-evidence zijn vereist vóór release.
