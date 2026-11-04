# Repository 6/37 — Pontifex OI: gerichte codekwaliteitsaudit

Datum: **5 november 2026**. Basis van `main`: `8b3bef8ed5ed95b4e4c4e45355fdeb5b552cdffb`. Concept: [PR #17](https://github.com/Yolol100/Pontifex/pull/17) op branch `quality/registration-pagination-guards-20261105`. Bestaande onafhankelijke [PR #16](https://github.com/Yolol100/Pontifex/pull/16) over productielogging blijft ongemoeid.

**Geen productieaanpassing, staginginstallatie, betaling, SOAP-aanroep, SMTP-verzending of klantgegevensverwerking.** Alleen geïsoleerde WordPress/MySQL-data in GitHub Actions.

## Bevestigde fout — POI-001

**Ernst:** middel voor betrouwbaarheid van het beheeroverzicht; geen aangetoonde ongeautoriseerde inzage.

`includes/helpers/class-registrations.php::Registrations::list()` gebruikte `$per_page` direct in een SQL-`LIMIT`, `OFFSET` en `ceil($total / $per_page)`. Een oproep met `per_page=0` gaf daarom een fatale `DivisionByZeroError`; een negatieve pagina kon ook ongeldige offsetmetadata opleveren.

- **Bewijs vóór wijziging:** een nieuwe `tests/wp-cli-registration-list-smoke.php` creëert één synthetisch record, test nul/negatieve en normale paginering en verwijdert de testdata daarna. [Rode WordPress-matrix #41884988567](https://github.com/Yolol100/Pontifex/actions/runs/41884988567): in PHP 8.1 en 8.3 `DivisionByZeroError` bij class-registrations.php rond regel 160.
- **Reparatie in bestaande eigenaar:** normaliseer `$page = max(1, $page)` en `$per_page = max(1, $per_page)` vóór de offset- en queryberekening. Dit houdt reguliere positieve paginering en het publieke arraycontract intact.
- **Hertest:** [WordPress runtime #41885218478](https://github.com/Yolol100/Pontifex/actions/runs/41885218478), gecombineerd met bestaande PHP/JS/Composer, security, privacy, Mollie lifecycle mock en uninstall-smokes. **Beoordeel de eindstatus van deze run opnieuw voor release-/PR-acceptatie.**
- **Bestandsaudit:** [volledige broninventaris #41885218354](https://github.com/Yolol100/Pontifex/actions/runs/41885218354) op dezelfde codecommit is groen. Heuristische signalen zijn geen onweerlegbaar bewijs van foutloos gedrag.

## Vergelijking met externe code en best practices

- [WordPress Core WP_List_Table::get_pagenum()](https://developer.wordpress.org/reference/classes/wp_list_table/get_pagenum/): paginanummer wordt minimaal 1. Kernprincipe direct toegepast.
- [WordPress Core WP_List_Table](https://developer.wordpress.org/reference/classes/wp_list_table/): geldige pagina-/per_page-waarden zijn een voorwaarde voor paginationberekening.
- [WordPress wpdb::prepare()](https://developer.wordpress.org/reference/classes/wpdb/prepare/): waarden voor `LIMIT` en `OFFSET` moeten numeriek en geparametriseerd zijn. Bestaande SQL-preparatie behouden.
- [PHP DivisionByZeroError](https://www.php.net/class.divisionbyzeroerror.php): een deling door nul is een fatale fout op ondersteunde PHP-versies.

## Belangrijkste resterende risico's: doelbewust niet gewijzigd

1. Externe **Mollie-betaling, refund/chargeback, dubbele gelijktijdige webhook, crash/herstel en SMTP** zijn niet in een echte provider-sandbox aanvaard. De uitgebreide huidige tests zijn geïsoleerde mocks of bronchecks.
2. **SOAP-planning en externe registratie**: de bestaande bron beschrijft alleen lokale registratie; een externe inschrijving is niet bewezen.
3. **Historische order-ID duplicaten** en een eventueel ontbrekende unieke database-index vragen back-up, migratieplan, verificatie en onafhankelijke stagingtoestemming. Geen automatische datamigratie.
4. **Frontend CSS/JS schoonmaak** (globale selectors, veel `!important`, legacy assets) vereist screenshot-, toetsenbord-, mobiel- en formulierregressies; geen blind verwijderen.
5. **Persoonsgegevens/privacy:** registratiegegevens en dataverwijdering zijn bestaande high-risk paden; geen wijziging zonder heldere bewaartermijn en privacyacceptatie.
6. **Releasepakket:** Composer vendor moet reproduceerbaar worden ingepakt; echte ZIP-/stagingpariteit en eigenaarreleasegoedkeuring ontbreken.
7. Potentiële uitbijters zoals extreem grote positieve paginanummers zijn niet aan een load-/stressprofiel getoetst; normale begrenzing is de gerichte reparatie.

## Publicatiegrenzen

Voor deze PR gelden: groene CI op de **actuele** PR-head, beoordeling door eigenaar, verplichte provider-/browser-/releasechecks voor een echte livegang. Sluit PR #17 om codewijzigingen terug te draaien; `main` en echte klantomgevingen blijven ongewijzigd. Voer geen WordPress-databasemigratie of betaalgerelateerde aanpassing uit op basis van alleen deze broncontrole.
