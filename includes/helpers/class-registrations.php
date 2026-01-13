<?php
namespace PontifexOI\Helpers;

if (!defined('ABSPATH')) exit;

class Registrations {
	/**
	 * Returns the full table name with the WordPress prefix.
	 *
	 * @return string
	 */
	private static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'pontifex_oi_registrations';
	}

	/**
	 * Saves a new registration entry to the database.
	 *
	 * @param string $order_id
	 * @param array $order
	 * @param string|null $planning_identifier
	 * @return int|false The insert ID on success, false on failure.
	 */
	public static function save(string $order_id, array $order, ?string $planning_identifier = null) {
		global $wpdb;
		$data = [
			'order_id' => sanitize_text_field($order_id),
			'planning_identifier' => $planning_identifier ? sanitize_text_field($planning_identifier) : null,
			'payload' => wp_json_encode($order, JSON_UNESCAPED_UNICODE),
			'created_at' => current_time('mysql'),
			'updated_at' => null,
		];
		// Use correct format specifiers: %s for strings/text, including JSON and dates
		$ok = $wpdb->insert(self::table_name(), $data, ['%s', '%s', '%s', '%s', '%s']);
		return $ok ? (int)$wpdb->insert_id : false;
	}

	/**
	 * Fetches a paginated and searchable list of registrations.
	 *
	 * @param int $page The current page number.
	 * @param int $per_page Items per page.
	 * @param string $search Search term (applies to order_id and payload).
	 * @param string $orderby Column to order by.
	 * @param string $order Sort direction (ASC or DESC).
	 * @return array
	 */
	public static function list(
		int $page = 1,
		int $per_page = 20,
		string $search = '',
		string $orderby = 'created_at',
		string $order = 'DESC'
	): array {
		global $wpdb;
		$table = self::table_name();
		$offset = ($page - 1) * $per_page;

		// Whitelist allowed columns for ORDER BY to prevent SQL injection
		$allowed_orderby = ['id', 'order_id', 'created_at'];
		if (!in_array($orderby, $allowed_orderby, true)) {
			$orderby = 'created_at';
		}
		// Sanitize order direction
		$order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

		$where = '1=1';
		$params = [];
		if ($search !== '') {
			// Apply search filter to order_id and payload
			$where .= ' AND (order_id LIKE %s OR payload LIKE %s)';
			$like = '%' . $wpdb->esc_like($search) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		// --- Fix: Conditioneel gebruik van wpdb::prepare voor de COUNT query ---
		if (!empty($params)) {
			// Search active: Use prepare with the search parameters
			$query_total = $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}", ...$params);
		} else {
			// No search: Safe to use the query directly as there are no placeholders in the WHERE clause
			$query_total = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		}
		$total = (int)$wpdb->get_var($query_total);


		// Get the actual rows
		// Note: $orderby and $order zijn veilig afgehandeld hierboven
		$sql = "SELECT id, order_id, planning_identifier, payload, created_at
				FROM {$table} WHERE {$where}
				ORDER BY {$orderby} {$order}
				LIMIT %d OFFSET %d";

		// --- Fix: Conditioneel gebruik van wpdb::prepare voor de ROWS query ---
		if (!empty($params)) {
			// If there are search parameters, merge them with LIMIT/OFFSET parameters
			$rows = $wpdb->get_results(
				$wpdb->prepare($sql, ...array_merge($params, [$per_page, $offset])),
				ARRAY_A
			);
		} else {
			// If there are NO search parameters, only pass LIMIT/OFFSET parameters
			$rows = $wpdb->get_results(
				$wpdb->prepare($sql, $per_page, $offset),
				ARRAY_A
			);
		}

		return [
			'rows'     => $rows ?: [],
			'total'    => $total,
			'pages'    => max(1, (int)ceil($total / $per_page)),
			'page'     => $page,
			'per_page' => $per_page,
		];
	}
}