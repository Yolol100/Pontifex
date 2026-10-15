<?php
defined('ABSPATH') || exit;

use PontifexOI\Helpers\Registrations;

// Query params veilig uitlezen (Only needed for initial search state and export link)
$page = isset($_GET['paged']) ? max(1, (int)$_GET['paged']) : 1;
$per_page = isset($_GET['per_page']) ? max(1, min(200, (int)$_GET['per_page'])) : 20;
$search = isset($_GET['s']) && is_string($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$orderby = isset($_GET['orderby']) && is_string($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : 'created_at';
$order = (isset($_GET['order']) && is_string($_GET['order']) && strtoupper(wp_unslash($_GET['order'])) === 'ASC') ? 'ASC' : 'DESC';

// Data ophalen: Only call Registrations::list to get the total count for the title row.
// The actual table content will be fetched by JS.
$list = Registrations::list($page, $per_page, $search, $orderby, $order);

// FIX: Zorg dat $list altijd consistente structuur heeft
if (!is_array($list)) {
	$list = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
}

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

			<a href="<?php echo esc_url(wp_nonce_url(add_query_arg([
				'action' => 'pontifex_oi_export_submissions',
				's' => $search,
				'orderby' => $orderby,
				'order' => $order,
			], admin_url('admin-post.php')), 'pontifex_oi_export_submissions')); ?>"
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