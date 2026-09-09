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
	 * Saves or updates a registration entry by external order/payment id.
	 * Repeated Mollie webhook delivery must not create duplicate local rows.
	 *
	 * @param string $order_id
	 * @param array $order
	 * @param string|null $planning_identifier
	 * @return int|false The row ID on success, false on failure.
	 */
	public static function save(string $order_id, array $order, ?string $planning_identifier = null) {
		global $wpdb;

		$order_id = sanitize_text_field($order_id);
		if ($order_id === '') {
			return false;
		}

		$table = self::table_name();
		$existing_id = (int) $wpdb->get_var(
			$wpdb->prepare("SELECT id FROM {$table} WHERE order_id = %s ORDER BY id ASC LIMIT 1", $order_id)
		);

		$payload = wp_json_encode($order, JSON_UNESCAPED_UNICODE);
		if ($payload === false) {
			return false;
		}

		$planning_identifier = $planning_identifier ? sanitize_text_field($planning_identifier) : null;

		if ($existing_id > 0) {
			$updated = $wpdb->update(
				$table,
				[
					'planning_identifier' => $planning_identifier,
					'payload' => $payload,
					'updated_at' => current_time('mysql'),
				],
				['id' => $existing_id],
				['%s', '%s', '%s'],
				['%d']
			);

			return $updated === false ? false : $existing_id;
		}

		$data = [
			'order_id' => $order_id,
			'planning_identifier' => $planning_identifier,
			'payload' => $payload,
			'created_at' => current_time('mysql'),
			'updated_at' => null,
		];
		$ok = $wpdb->insert($table, $data, ['%s', '%s', '%s', '%s', '%s']);
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

		$allowed_orderby = ['id', 'order_id', 'created_at'];
		if (!in_array($orderby, $allowed_orderby, true)) {
			$orderby = 'created_at';
		}
		$order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

		$where = '1=1';
		$params = [];
		if ($search !== '') {
			$where .= ' AND (order_id LIKE %s OR payload LIKE %s)';
			$like = '%' . $wpdb->esc_like($search) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		if (!empty($params)) {
			$query_total = $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}", ...$params);
		} else {
			$query_total = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		}
		$total = (int)$wpdb->get_var($query_total);

		$sql = "SELECT id, order_id, planning_identifier, payload, created_at
				FROM {$table} WHERE {$where}
				ORDER BY {$orderby} {$order}
				LIMIT %d OFFSET %d";

		if (!empty($params)) {
			$rows = $wpdb->get_results(
				$wpdb->prepare($sql, ...array_merge($params, [$per_page, $offset])),
				ARRAY_A
			);
		} else {
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
