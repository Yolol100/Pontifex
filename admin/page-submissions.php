<?php
/**
 * Pontifex OI - Inzendingen Overzicht Pagina (Admin)
 *
 * Toont een dynamisch geladen overzicht van inzendingen met zoek-, sorteer- en exportfunctionaliteit.
 *
 * @package PontifexOI
 * @since   1.0.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

use PontifexOI\Helpers\Registrations;

// Helper functies laden
require_once PONTIFEX_OI_PATH . 'includes/helpers/submissions-helpers.php';

// ========================
// Query parameters veilig verwerken
// ========================
$page     = max(1, (int) ($_GET['paged']     ?? 1));
$per_page = max(1, min(200, (int) ($_GET['per_page'] ?? 20)));
$search   = isset($_GET['s'])       ? sanitize_text_field((string) $_GET['s'])       : '';
$orderby  = isset($_GET['orderby']) ? sanitize_key((string) $_GET['orderby'])       : 'created_at';
$order    = (isset($_GET['order']) && strtoupper((string) $_GET['order']) === 'ASC') ? 'ASC' : 'DESC';

// ========================
// Export functionaliteit (CSV)
// ========================
if (isset($_GET['export_submissions']) && current_user_can('manage_options')) {
    // Extra beveiliging: nonce check (optioneel, maar sterk aanbevolen)
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'pontifex_oi_export_submissions')) {
        wp_die(esc_html__('Ongeldige of ontbrekende beveiligingstoken. Export geweigerd.', 'pontifex-oi'), 403);
    }

    // Haal ALLE relevante records op (geen paginering bij export)
    $all = Registrations::list(1, 5000, $search, $orderby, $order);

    if (!is_array($all) || empty($all['rows'])) {
        wp_die(esc_html__('Geen gegevens gevonden om te exporteren.', 'pontifex-oi'));
    }

    // Voorkom output buffering problemen
    if (ob_get_level()) {
        while (ob_get_level()) {
            ob_end_clean();
        }
    }

    // Headers voor CSV download
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inzendingen-' . date('Y-m-d-His') . '.csv"');

    $output = fopen('php://output', 'w');

    // CSV kolomkoppen (moet overeenkomen met pontifex_oi_order_to_rows output)
    $headers = [
        'Naam', 'Tussenvoegsel', 'Achternaam', 'Geboortedatum',
        'Postcode', 'Huisnummer', 'Straat', 'Stad',
        'Telefoonnummer', 'E-mailadres', 'Bedrijfsnaam', 'Functie',
        'BTW-nummer', 'Examen', 'Taal', 'Lesmateriaal', 'Extra lesmateriaal',
        'Locatie', 'Datum', 'Tijd', 'Totaal'
    ];

    fputcsv($output, $headers);

    foreach ($all['rows'] as $record) {
        $payload = json_decode($record['payload'] ?? '{}', true) ?: [];
        $rows = pontifex_oi_order_to_rows($payload);

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
    }

    fclose($output);
    exit;
}

// ========================
// Initiële data ophalen (alleen voor totaal telling + titel)
// ========================
$initial_list = Registrations::list($page, $per_page, $search, $orderby, $order);

// Zorg voor consistente structuur
if (!is_array($initial_list)) {
    $initial_list = [
        'rows'     => [],
        'total'    => 0,
        'page'     => 1,
        'pages'    => 1,
        'per_page' => $per_page,
    ];
}
?>

<div class="pontifex-admin-wrap">
    <div class="pontifex-admin-card pontifex-card-submissions">
        <h2>
            <?php esc_html_e('Inzendingen', 'pontifex-oi'); ?>
            <span class="pontifex-sub-count">
                (<?php echo esc_html(number_format_i18n((int) ($initial_list['total'] ?? 0))); ?>)
            </span>
        </h2>

        <div class="pontifex-admin-search-bar">
            <input type="search"
                   id="poi_sub_s"
                   class="pontifex-admin-input poi-sub-search"
                   value="<?php echo esc_attr($search); ?>"
                   placeholder="<?php esc_attr_e('Zoek op order-id, naam, e-mail…', 'pontifex-oi'); ?>" />

            <select id="poi_sub_perpage" class="pontifex-admin-input poi-sub-select">
                <?php foreach ([10, 20, 50, 100, 200] as $pp): ?>
                    <option value="<?php echo $pp; ?>" <?php selected($per_page, $pp); ?>>
                        <?php echo $pp; ?> <?php esc_html_e('per pagina', 'pontifex-oi'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button id="poi_sub_apply" class="button button-primary poi-sub-btn">
                <?php esc_html_e('Toepassen', 'pontifex-oi'); ?>
            </button>

            <?php
            $export_url = wp_nonce_url(
                add_query_arg([
                    'page'              => 'pontifex_oi_submissions',
                    's'                 => $search,
                    'orderby'           => $orderby,
                    'order'             => $order,
                    'export_submissions' => '1',
                ], admin_url('admin.php')),
                'pontifex_oi_export_submissions',
                '_wpnonce'
            );
            ?>
            <a href="<?php echo esc_url($export_url); ?>"
               class="button button-secondary poi-sub-btn poi-export-btn">
                <?php esc_html_e('Exporteren als CSV', 'pontifex-oi'); ?>
            </a>
        </div>

        <div id="poi_sub_cardgrid" class="pontifex-oi-card-grid" aria-live="polite">
            <div class="pontifex-oi-card-item pontifex-loading">
                <div class="pontifex-oi-card-body">
                    <?php esc_html_e('Bezig met laden...', 'pontifex-oi'); ?>
                </div>
            </div>
        </div>

        <div id="poi_sub_pagination" class="poi-sub-pagination" style="display:none;">
            <button type="button" class="button" data-direction="prev" disabled>
                ← <?php esc_html_e('Vorige', 'pontifex-oi'); ?>
            </button>
            <span id="poi_sub_pageinfo">1 / 1</span>
            <button type="button" class="button" data-direction="next" disabled>
                <?php esc_html_e('Volgende', 'pontifex-oi'); ?> →
            </button>
        </div>
    </div>
</div>