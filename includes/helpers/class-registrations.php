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
	 * Recover a previously persisted registration by its verified payment ID.
	 * Never use this for a payment that has not already been saved locally.
	 *
	 * @return array|null Decoded registration payload.
	 */
	public static function find_order(string $order_id): ?array {
		global $wpdb;

		if ($order_id === '' || strlen($order_id) > 80) {
			return null;
		}
		$table = self::table_name();
		$raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT payload FROM {$table} WHERE order_id = %s ORDER BY id ASC LIMIT 1",
				$order_id
			)
		);
		if (!is_string($raw) || $raw === '') {
			return null;
		}
		$payload = json_decode($raw, true);
		return is_array($payload) ? $payload : null;
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
	/**
	 * Scan registrations by stable row-id pagination, never by mutable email.
	 * Pagination must not skip rows when privacy erasure changes payload.
	 */
	private static function privacy_page(string $email, int $page): array {
		global $wpdb;

		if (!is_email($email) || $page < 1) {
			return ['rows' => [], 'done' => true];
		}
		$table = self::table_name();
		$limit = 50;
		$offset = ($page - 1) * $limit;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, order_id, payload, created_at FROM {$table} ORDER BY id ASC LIMIT %d OFFSET %d",
				$limit, $offset
			),
			ARRAY_A
		);
		$rows = is_array($rows) ? $rows : [];
		$matched = [];
		foreach ($rows as $row) {
			$order = json_decode((string) ($row['payload'] ?? ''), true);
			if (!is_array($order)) {
				continue;
			}
			$contact = $order['order_email'] ?? ($order['email'] ?? '');
			if (is_string($contact) && strtolower(sanitize_email($contact)) === strtolower($email)) {
				$row['order'] = $order;
				$matched[] = $row;
			}
		}
		return ['rows' => $matched, 'done' => count($rows) < $limit];
	}

	public static function register_privacy_exporter(array $exporters): array {
		$exporters['pontifex-oi'] = [
			'exporter_friendly_name' => __('Pontifex inschrijvingen', 'pontifex-oi'),
			'callback' => [self::class, 'export_personal_data'],
		];
		return $exporters;
	}

	public static function register_privacy_eraser(array $erasers): array {
		$erasers['pontifex-oi'] = [
			'eraser_friendly_name' => __('Pontifex inschrijvingen', 'pontifex-oi'),
			'callback' => [self::class, 'erase_personal_data'],
		];
		return $erasers;
	}

	/**
	 * Export only the requester's buyer/contact fields and payment reference.
	 * Another exam candidate's birthdate is NOT exposed to the payer by email.
	 */
	public static function export_personal_data(string $email_address, int $page = 1): array {
		$batch = self::privacy_page($email_address, $page);
		$keys = [
			'given-name' => __('Voorletters', 'pontifex-oi'),
			'family-name' => __('Achternaam', 'pontifex-oi'),
			'additional-name' => __('Tussenvoegsel', 'pontifex-oi'),
			'street-address' => __('Straat', 'pontifex-oi'),
			'address-line2' => __('Huisnummer', 'pontifex-oi'),
			'postal-code' => __('Postcode', 'pontifex-oi'),
			'address-level2' => __('Plaats', 'pontifex-oi'),
			'tel' => __('Telefoonnummer', 'pontifex-oi'),
			'order_email' => __('E-mailadres', 'pontifex-oi'),
			'organization' => __('Bedrijf', 'pontifex-oi'),
			'organization-title' => __('Functie', 'pontifex-oi'),
		];
		$data = [];
		foreach ($batch['rows'] as $row) {
			$fields = [
				['name' => __('Betaalreferentie', 'pontifex-oi'), 'value' => (string) $row['order_id']],
				['name' => __('Inschrijfdatum', 'pontifex-oi'), 'value' => (string) $row['created_at']],
			];
			foreach ($keys as $key => $label) {
				$value = $row['order'][$key] ?? null;
				if (is_scalar($value) && (string) $value !== '') {
					$fields[] = ['name' => $label, 'value' => (string) $value];
				}
			}
			$data[] = [
				'group_id' => 'pontifex-registrations',
				'group_label' => __('Pontifex inschrijvingen', 'pontifex-oi'),
				'item_id' => 'pontifex-registration-' . (int) $row['id'],
				'data' => $fields,
			];
		}
		return ['data' => $data, 'done' => $batch['done']];
	}

	/**
	 * Erasure is fail-closed for payment/qualification records. The site's
	 * legal/financial data-retention owner must approve anonymization by filter.
	 * Even when approved, candidate exam data remains marked as retained.
	 */
	public static function erase_personal_data(string $email_address, int $page = 1): array {
		global $wpdb;
		$batch = self::privacy_page($email_address, $page);
		$removed = false;
		$retained = false;
		$messages = [];
		foreach ($batch['rows'] as $row) {
			$approved = (bool) apply_filters(
				'pontifex_oi_allow_buyer_data_erasure',
				false,
				(string) $row['order_id'],
				(int) $row['id']
			);
			if (!$approved) {
				$retained = true;
				continue;
			}
			$order = $row['order'];
			foreach ([
				'given-name', 'family-name', 'additional-name',
				'street-address', 'address-line2', 'postal-code',
				'address-level2', 'tel', 'order_email', 'email',
				'organization', 'organization-title',
				'order_initials', 'order_lastname', 'order_infix',
				'order_postcode', 'order_housenumber', 'order_street',
				'order_city', 'order_phone', 'order_company', 'order_function',
			] as $key) {
				unset($order[$key]);
			}
			$updated = $wpdb->update(
				self::table_name(),
				['payload' => wp_json_encode($order), 'updated_at' => current_time('mysql')],
				['id' => (int) $row['id']],
				['%s', '%s'],
				['%d']
			);
			if ($updated === false) {
				$retained = true;
				$messages[] = __('Gegevens konden niet worden bijgewerkt.', 'pontifex-oi');
			} else {
				$removed = true;
				$retained = true; // Candidate data and accounting references are retained.
			}
		}
		if ($retained) {
			$messages[] = __('Inschrijvings- en betaalgegevens kunnen nog onder de wettelijke bewaarplicht vallen; beoordeel deze handmatig.', 'pontifex-oi');
		}
		return [
			'items_removed' => $removed,
			'items_retained' => $retained,
			'messages' => array_values(array_unique($messages)),
			'done' => $batch['done'],
		];
	}

}
