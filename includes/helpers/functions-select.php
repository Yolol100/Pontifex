<?php
namespace PontifexOI\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Genereert een select+label combinatie.
 * Geeft nooit een lege optie voor exam_type!
 */
function render_select($name, $label, $options, $selected = null, $label_class = '') {
    // Voeg alleen lege optie toe als het GEEN exam_type is
    $hasEmpty = false;

    // Check of er al een lege optie is
    if (isset($options[0])) {
        $first = $options[0];
        $hasEmpty = (is_array($first) && isset($first['id']) && $first['id'] === '') || $first === '';
    }

    // Voeg lege optie toe, behalve bij exam_type
    if ($name !== 'exam_type' && !$hasEmpty) {
        $optLabel = ($name === 'material') ? __('Geen keuze','pontifex-oi') : __('Toon alles', 'pontifex-oi');
        array_unshift($options, ['id' => '', 'name' => $optLabel]);
    }

    // Label class attribuut
    $label_class_attr = $label_class ? ' class="' . esc_attr($label_class) . '"' : '';

    echo '<label' . $label_class_attr . '>';
    echo esc_html($label);
    echo '<select name="' . esc_attr($name) . '" class="pontifex-oi-filter" data-filter="' . esc_attr($name) . '">';
    foreach ($options as $option) {
        // Skip lege optie bij exam_type
        if ($name === 'exam_type' && (isset($option['id']) && $option['id'] === '')) {
            continue;
        }
        $value = isset($option['id']) ? $option['id'] : $option;
        $text  = isset($option['name']) ? $option['name'] : $option;
        $is_selected = ((string)$value === (string)$selected) ? 'selected' : '';
        echo '<option value="' . esc_attr($value) . '" ' . $is_selected . '>' . esc_html($text) . '</option>';
    }
    echo '</select>';
    echo '</label>';
}