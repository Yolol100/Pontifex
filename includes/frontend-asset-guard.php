<?php
/**
 * Prevent Pontifex frontend assets and viewport markup from loading sitewide.
 *
 * @package PontifexOI
 */

namespace PontifexOI\PublicPart;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether the current frontend request renders a Pontifex flow.
 */
function request_needs_pontifex_assets(): bool {
    if (!is_singular()) {
        return (bool) apply_filters('pontifex_oi_should_enqueue_assets', false);
    }

    $post_id = (int) get_queried_object_id();
    $content = (string) get_post_field('post_content', $post_id);
    foreach (['pontifex_oi_planning', 'pontifex_oi_registration', 'pontifex_oi_payment_success'] as $shortcode) {
        if ($content !== '' && has_shortcode($content, $shortcode)) {
            return true;
        }
    }

    $registration_page_id = (int) get_option('pontifex_oi_registration_page_id');
    if ($registration_page_id > 0 && $post_id === $registration_page_id) {
        return true;
    }

    $slug = (string) get_post_field('post_name', $post_id);
    if (in_array($slug, ['cursus-zoeken', 'cursus-inschrijven', 'betaling-gelukt'], true)) {
        return true;
    }

    return (bool) apply_filters('pontifex_oi_should_enqueue_assets', false, $post_id);
}

add_action(
    'wp',
    static function (): void {
        if (request_needs_pontifex_assets()) {
            return;
        }

        $frontend = Frontend::get_instance();
        remove_action('wp_head', [$frontend, 'add_viewport_meta_tag'], 1);
        remove_action('wp_enqueue_scripts', [$frontend, 'enqueue_assets']);
    },
    20
);
