<?php
/**
 * Candidate data validation used by the public payment flow.
 *
 * @package PontifexOI
 */

namespace PontifexOI\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normalize submitted candidates without shifting associated surname/date rows.
 *
 * The caller must unslash WordPress request input first.
 */
final class RegistrationValidator {
    private const MAX_CANDIDATES = 12;

    /**
     * @param array $order Unslashed registration data.
     * @return array|\WP_Error Normalized data or a safe validation error.
     */
    public static function normalize_candidates(array $order) {
        $fields = [
            'candidate_fullname',
            'candidate_infix',
            'candidate_lastname',
            'candidate_birthdate',
        ];

        $names = $order['candidate_fullname'] ?? null;
        if (!is_array($names) || count($names) < 1 || count($names) > self::MAX_CANDIDATES) {
            return new \WP_Error('pontifex_invalid_candidates', __('Vul minimaal één en maximaal twaalf kandidaten in.', 'pontifex-oi'));
        }

        $count = count($names);
        foreach ($fields as $field) {
            $submitted = $order[$field] ?? [];
            if (!is_array($submitted) || count($submitted) > self::MAX_CANDIDATES) {
                return new \WP_Error('pontifex_invalid_candidate_field', __('De kandidaatgegevens zijn ongeldig.', 'pontifex-oi'));
            }

            // Preserve empty optional fields. Removing them individually shifts rows.
            $submitted = array_values($submitted);
            $clean = [];

            for ($index = 0; $index < $count; $index++) {
                $value = $submitted[$index] ?? '';
                if (!is_scalar($value) && $value !== null) {
                    return new \WP_Error('pontifex_invalid_candidate_value', __('De kandidaatgegevens zijn ongeldig.', 'pontifex-oi'));
                }

                $value = sanitize_text_field((string) ($value ?? ''));
                if (strlen($value) > 191 || (in_array($field, [
                    'candidate_fullname', 'candidate_lastname', 'candidate_birthdate',
                ], true) && trim($value) === '')) {
                    return new \WP_Error('pontifex_invalid_candidate_name', __('Controleer de ingevoerde kandidaatgegevens.', 'pontifex-oi'));
                }
                if ($field === 'candidate_birthdate') {
                    $date = \DateTimeImmutable::createFromFormat('!d-m-Y', $value, wp_timezone());
                    if (!$date || $date->format('d-m-Y') !== $value
                        || $date > new \DateTimeImmutable('today', wp_timezone())
                        || $date->format('Y') < '1900') {
                        return new \WP_Error('pontifex_invalid_birthdate', __('Controleer de geboortedatum (dd-mm-jjjj).', 'pontifex-oi'));
                    }
                }
                $clean[] = $value;
            }

            $order[$field] = $clean;
        }

        $order['candidate_count'] = $count;
        return $order;
    }

    /**
     * Validate the required buyer and pricing fields before creating a payment.
     * Avoid accepting hidden client prices as authoritative.
     *
     * @param array $order Unslashed order data with normalized candidates.
     * @return array|\WP_Error Clean order or a safe error.
     */
    public static function normalize_order_fields(array $order) {
        $required = [
            'given-name', 'family-name', 'postal-code', 'address-line2',
            'street-address', 'address-level2', 'tel', 'organization-title',
        ];
        foreach ($required as $key) {
            $value = $order[$key] ?? null;
            if (!is_scalar($value)) {
                return new \WP_Error('pontifex_missing_buyer_data', __('Vul alle verplichte contactgegevens in.', 'pontifex-oi'));
            }
            $clean = sanitize_text_field((string) $value);
            if ($clean === '' || strlen($clean) > 191) {
                return new \WP_Error('pontifex_invalid_buyer_data', __('Controleer alle verplichte contactgegevens.', 'pontifex-oi'));
            }
            $order[$key] = $clean;
        }

        $email = $order['order_email'] ?? ($order['email'] ?? '');
        if (!is_string($email) || strlen($email) > 254 || !is_email($email)) {
            return new \WP_Error('pontifex_invalid_email', __('Voer een geldig e-mailadres in.', 'pontifex-oi'));
        }
        $order['order_email'] = sanitize_email($email);

        if (!preg_match('/^\+?[0-9\s-]{6,32}$/', $order['tel'])) {
            return new \WP_Error('pontifex_invalid_phone', __('Controleer het telefoonnummer.', 'pontifex-oi'));
        }

        global $EXAM_PRODUCTS, $EXTRA_PRODUCTS;
        $exam = $order['exam_type'] ?? null;
        $language = $order['language'] ?? 'nl';
        $material = $order['material'] ?? '1';
        if (!is_string($exam) || !is_string($language) || !is_string($material)) {
            return new \WP_Error('pontifex_invalid_exam', __('Selecteer een geldig examen.', 'pontifex-oi'));
        }

        $exam = sanitize_text_field($exam);
        $language = sanitize_text_field($language);
        $material = sanitize_text_field($material);
        $normalized_exam = function_exists('pontifex_normalize_exam_key')
            ? pontifex_normalize_exam_key($exam)
            : $exam;
        $supported_material = [
            '', '1', '2', '4', '5', '6', '7',
            'cursus-weekend', 'cursus-weekend-nl', 'cursus-weekend-en', 'weekend_dh',
        ];
        if (!isset($EXAM_PRODUCTS[$normalized_exam])
            || !isset($EXAM_PRODUCTS[$normalized_exam]['prices'][$language])
            || !in_array($material, $supported_material, true)) {
            return new \WP_Error('pontifex_invalid_exam', __('Selecteer een geldig examen, taal en lesmateriaal.', 'pontifex-oi'));
        }

        $extras = $order['extra_options'] ?? [];
        if (!is_array($extras) || count($extras) > 20) {
            return new \WP_Error('pontifex_invalid_extras', __('Ongeldige extra opties.', 'pontifex-oi'));
        }
        $allowed_weekend = ['cursus-weekend', 'cursus-weekend-nl', 'cursus-weekend-en', 'weekend_dh'];
        $normalized_extras = [];
        foreach ($extras as $extra) {
            if (!is_string($extra) || strlen($extra) > 80) {
                return new \WP_Error('pontifex_invalid_extras', __('Ongeldige extra opties.', 'pontifex-oi'));
            }
            $extra = sanitize_text_field($extra);
            if (!isset($EXTRA_PRODUCTS[$extra]) && !in_array($extra, $allowed_weekend, true)) {
                return new \WP_Error('pontifex_invalid_extras', __('Ongeldige extra opties.', 'pontifex-oi'));
            }
            $normalized_extras[$extra] = $extra;
        }

        $order['exam_type'] = $normalized_exam;
        $order['language'] = $language;
        $order['material'] = $material === '' ? '1' : $material;
        $order['extra_options'] = array_values($normalized_extras);
        return $order;
    }
}
