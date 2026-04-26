<?php
defined('ABSPATH') || exit;

use PontifexOI\Helpers\Registrations;

// 1) Helper-functie is verplaatst naar een aparte file en wordt hier ingeladen.
require_once PONTIFEX_OI_PATH . 'includes/helpers/submissions-helpers.php';

// Query params veilig uitlezen (Only needed for initial search state and export link)
$page = isset($_GET['paged']) ? max(1, (int) wp_unslash($_GET['paged'])) : 1;
$per_page = isset($_GET['per_page']) ? max(1, min(200, (int) wp_unslash($_GET['per_page']))) : 20;
$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'created_at';
$order = (isset($_GET['order']) && strtoupper(sanitize_text_field(wp_unslash($_GET['order']))) === 'ASC') ? 'ASC' : 'DESC';

// Export CSV/Excel (Export logic remains server-side)
if (isset($_GET['export_submissions'])) {
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('Je hebt geen rechten om inzendingen te exporteren.', 'pontifex-oi'));
	}

	$export_nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
	if (!wp_verify_nonce($export_nonce, 'pontifex_oi_export_submissions')) {
		wp_die(esc_html__('Ongeldige exportlink. Vernieuw de pagina en probeer opnieuw.', 'pontifex-oi'));
	}
	// Note: We ignore the current pagination for export, fetching all based on search/sort
	// Limit changed to 5000 for safer batch export performance.
	$all = Registrations::list(1, 5000, $search, $orderby, $order);

	// Streaming output flush
	if (ob_get_level()) { ob_end_clean(); }
	while (ob_get_level()) ob_end_flush();
	
	$filename = 'inzendingen-' . date('Ymd-His') . '.csv';

	// Prevent caching and set CSV headers
	nocache_headers();
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename=' . $filename);
	$output = fopen('php://output', 'w');

	$headers = [
		'Naam','Tussenvoegsel','Achternaam','Geboortedatum',
		'Postcode','Huisnummer','Straat','Stad',
		'Telefoonnummer','E-mailadres','Bedrijfsnaam','Functie',
		'BTW-nummer','Examen','Taal','Lesmateriaal','Extra lesmateriaal',
		'Locatie','Datum','Tijd','Totaal'
	];
	fputcsv($output, $headers);

	foreach ($all['rows'] as $r) {
		$payload = json_decode($r['payload'] ?? '[]', true) ?: [];
		$rows = pontifex_oi_order_to_rows($payload);
		foreach ($rows as $row) {
			// Write to CSV
			fputcsv($output, $row);
		}
	}

	fclose($output);
	exit; // Exit cleanly
}

// Data ophalen: Only call Registrations::list to get the total count for the title row.
// The actual table content will be fetched by JS.
$list = Registrations::list($page, $per_page, $search, $orderby, $order);

// FIX: Zorg dat $list altijd consistente structuur heeft
if (!is_array($list)) {
	$list = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
}

// Headers for the table (must match the pontifex_oi_order_to_rows function output)
$headers = [
	'Naam','Tussenvoegsel','Achternaam','Geboortedatum',
	'Postcode','Huisnummer','Straat','Stad',
	'Telefoonnummer','E-mailadres','Bedrijfsnaam','Functie',
	'BTW-nummer','Examen','Taal','Lesmateriaal','Extra lesmateriaal',
	'Locatie','Datum','Tijd','Totaal'
];

// Set initial values for JavaScript to know the table state
// The JS needs the current search, per_page, orderby, and order to initiate the fetch
?>

<div class="pontifex-admin-wrap">
	<div class="pontifex-admin-card pontifex-card-submissions">
		<div class="pontifex-admin-search-bar">
			<input id="poi_sub_s" type="text" class="pontifex-admin-input poi-sub-search"
				value="<?php echo esc_attr($search); ?>"
				placeholder="<?php esc_attr_e('Zoek op order-id, naam, e-mail…','pontifex-oi'); ?>" />

			<select id="poi_sub_perpage" class="pontifex-admin-input poi-sub-select">
				<?php foreach ([20, 50, 100, 200] as $pp): ?>
					<option value="<?php echo (int)$pp; ?>" <?php selected($per_page, $pp); ?>>
						<?php echo (int)$pp; ?>/pagina
					</option>
				<?php endforeach; ?>
			</select>

			<button id="poi_sub_apply" class="pontifex-admin-submit poi-sub-btn"><?php esc_html_e('Toepassen','pontifex-oi'); ?></button>

			<a href="<?php echo esc_url(add_query_arg([
				'page' => 'pontifex_oi_submissions',
				's' => $search,
				'orderby' => $orderby,
				'order' => $order,
				'export_submissions' => 1,
				'_wpnonce' => wp_create_nonce('pontifex_oi_export_submissions'),
			], admin_url('admin.php'))); ?>"
			class="pontifex-admin-submit poi-sub-btn poi-export-btn"><?php esc_html_e('Exporteren','pontifex-oi'); ?></a>
		</div>

		<div id="poi_sub_cardgrid" class="pontifex-oi-card-grid" aria-live="polite">
			<div class="pontifex-oi-card-item pontifex-loading">
				<div class="pontifex-oi-card-body">Laden...</div>
			</div>
		</div>

		<div id="poi_sub_pagination" class="poi-sub-pagination" style="display:none;">
			<button type="button" class="pontifex-oi-back-link" data-direction="prev" disabled>&larr;</button>
			<div id="poi_sub_pageinfo">1 / 1</div>
			<button type="button" class="pontifex-oi-back-link" data-direction="next" disabled>&rarr;</button>
		</div>
	</div>
</div>