# Pontifex OI — acceptatie- en releasechecklist

**Datum:** 15 oktober 2026  
**Verwachte codebasis:** `fix/pontifex-phase3-lifecycle-20261015` -> PR #8 -> `main` (alleen na head-SHA-test).  
**Productclaim:** WordPress slaat lokale registraties op; SOAP wordt momenteel uitsluitend voor planning gebruikt. **Geen bewijs van externe registratie.**

## Automatische controles (CI)

| Scenario | Verwacht | Bewijsbron |
|---|---|---|
| PHP 8.1 + WordPress 6.0 | Bootstrap, PHP lint en WordPress-activatie zonder plugin errors | GitHub Actions run op exacte head-SHA |
| PHP 8.3 + WordPress 7.1 | Dezelfde checks | GitHub Actions head-SHA |
| Composer | `validate`, locked `install` en `audit`; echte advisories blokkeren | CI job |
| JS-syntax en XSS-fixtures | Ongeldige syntax/injectie faalt | `node --check`, `tests/js-render-safety-smoke.js` |
| CSV en nonce | Formulevelden zijn tekst; export alleen admin-post met nonce/capability | `tests/wp-cli-security-smoke.php` |
| Kandidaatvelden | 1–12; datum/naam/achternaam; lege optionele tussenvoegsels verschuiven rijen niet | WP-CLI-fixtures |
| Contact en producten | Geldige gebruiker/examen/taal/betaling, ontbrekend email/naam/telefoon en ongekende producten falen | WP-CLI-fixtures |
| REST/planning/prijzen | Veilige scalar-typen; legitieme prijs > 0, 2 kandidaten + BTW kloppen | WP-CLI-fixtures |
| Webhook-status | Transientstatus migreert; duurzaam state na transientverval, geregistreerde order terugvindbaar | WP-CLI-fixtures |
| Uninstall (single site) | 3 tabellen en Pontifex-opties/cron verwijderd, zonder WP-coredata | `tests/wp-cli-uninstall-smoke.php` via echte `wp plugin uninstall` |

**Alleen groen op exacte huidige commit telt.** CLI-smokechecks simuleren **geen** echte Mollie/SOAP/SMTP-transacties.

## Verplichte handmatige en staging-acceptatie (niet automatisch opgelost)

| Test | Verwacht | Status |
|---|---|---|
| Betaling (Mollie sandbox) | 1 succesvolle betaalflow, correct bedrag, correct lokale registratie, correct e-mails | Niet uitgevoerd |
| Onbetaald/ingetrokken | Geen registratie-/succesmail en correcte status | Niet uitgevoerd |
| Refund/chargeback na betaald | Administratie en klant krijgen passende correctiestatus | Nog niet geïmplementeerd/bewezen |
| Dubbele gelijktijdige webhook | Één lokale rij; geen dubbele e-mails, lock race onder belasting | Niet bewezen |
| Crash tussen e-mailversturen en statuspersist | Herstel zonder dubbele e-mails, expliciete handmatige interventie bij onzeker resultaat | Niet bewezen |
| Eerste webhook later dan 2 dagen | Vereiste orderdata nog herstelbaar, of veilige beheerreconciliatie | **Niet opgelost; huidige token-transient 2 dagen** |
| Echte SOAP-contractvraag | Officiële bevestiging: alleen lokale registratie, of expliciete externe registratieprocedure | **Besluit vereist** |
| SOAP-planning timeout / WSDL change | Veilige fallback/feedback en geen verdwenen examenaanbod | Niet uitgevoerd |
| Test/live Mollie API-sleutels | Juiste omgeving, geen keylogging, correcte webhook URL/secret | Niet uitgevoerd |
| Multisite uninstall | Alle site-specifieke tabellen en secrets verwijderd | Alleen codepad, stagingtest ontbreekt |
| Inschrijfformulier en testmodus | Keyboard, labels, client/server-validatie en WCAG op mobiel/desktop | Niet uitgevoerd |
| E-mail (SMTP) | HTML/template escaping, correcte ontvangers en retry | Niet uitgevoerd |
| WP Plugin Check + WordPressCS | Security, i18n, PHP/WP-standaarden en distributieverplichtingen | Open |
| Installatie-ZIP | Locked `composer install --no-dev`, alle runtime dependencies aanwezig, geen tests/.github/devbestanden | Releasepakket niet gegenereerd |
| Rollback | Data- en webhookstatus blijven coherent na code-rollback | Niet op staging gevalideerd |

## Datalevenscyclus en deploy

- `deactivate` behoudt inschrijvingen; `uninstall` verwijdert database- en optiegegevens. Maak vooraf een database-back-up.
- Historische duplicaten op `order_id` moeten vóór het toevoegen van een unieke index veilig geïnventariseerd en gemigreerd worden. Geen blinde schemawijziging.
- Webhook-stage-status bevat alleen hash van payment-ID en stageflags, geen kandidaten. Volledige pendingregistratie blijft tijdelijk in WordPress: twee dagen.
- `soap_sent` is geen bewijs van externe registratie. Gebruik uitsluitend de bewezen `local_registration_only` status.
- De reservekopie van de oorspronkelijke `main` staat op branch `backup/main-before-2026-10-15-modernization`. Die branch is een **code**-rollback, geen database-back-up.
- Begin niet aan productie-uitrol zonder staging/Mollie/SOAP-bewijs en gecontroleerde datamigratie, handmatige eigenaaracceptatie en een volledige backup.

## Officiële richtlijnen

- [WordPress Plugin Security](https://developer.wordpress.org/plugins/security/)
- [WordPress data validation](https://developer.wordpress.org/apis/security/data-validation/)
- [WordPress REST permissions](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/)
- [WordPress privacy tools](https://developer.wordpress.org/plugins/privacy/)
- [WordPress Plugin Check](https://developer.wordpress.org/plugins/developer-tools/helper-plugins/)
- [Composer Locker content hash](https://github.com/composer/composer/blob/main/src/Composer/Package/Locker.php)
