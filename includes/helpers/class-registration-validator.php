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
                if (strlen($value) > 191 || ($field === 'candidate_fullname' && trim($value) === '')) {
                    return new \WP_Error('pontifex_invalid_candidate_name', __('Controleer de ingevoerde kandidaatnamen.', 'pontifex-oi'));
                }
                $clean[] = $value;
            }

            $order[$field] = $clean;
        }

        $order['candidate_count'] = $count;
        return $order;
    }
}
