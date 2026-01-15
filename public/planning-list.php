<?php
/**
 * Pontifex OI - Exam Planning & Registration Redirect Template
 *
 * Toont het examen-planning overzicht (stap 1) en handelt de 'go' redirect af naar inschrijven.
 *
 * @package PontifexOI
 * @since   1.0.0
 */

defined('ABSPATH') || exit;

// ========================
// Redirect afhandeling bij GET met ?go=1
// ========================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['go'])) {
    // Veilig alle parameters ontsmetten
    $params = wp_unslash($_GET);

    $safe_params = [
        'go'        => 1,
        'exam_type' => sanitize_key($params['exam_type'] ?? ''),
        'language'  => sanitize_key($params['language'] ?? ''),
        'material'  => sanitize_key($params['material'] ?? '1'),
        'date'      => sanitize_text_field($params['date'] ?? ''),
        'time'      => sanitize_text_field($params['time'] ?? ''),
        'location'  => sanitize_text_field($params['location'] ?? ''),
        'province'  => sanitize_text_field($params['province'] ?? ''),
        'spots'     => sanitize_text_field($params['spots'] ?? ''),
        'price'     => '', // Wordt server-side berekend → niet meesturen
    ];

    // Bouw veilige redirect-URL
    $registration_url = $args['registration_url'] ?? (
        function_exists('get_permalink')
        ? (($id = (int) get_option('pontifex_oi_registration_page_id'))
            ? get_permalink($id)
            : home_url('/cursus-inschrijven/'))
        : home_url('/cursus-inschrijven/')
    );

    $redirect_url = add_query_arg(
        array_filter($safe_params, fn($v) => $v !== '' && $v !== null),
        trailingslashit($registration_url)
    );

    // Veilig redirecten
    wp_safe_redirect($redirect_url);
    exit;
}

// ========================
// Standaard filterwaarden
// ========================
$exam_type = sanitize_key($args['filters']['exam_type'] ?? $_GET['exam_type'] ?? 'los-examen-vca-basis');
$language  = sanitize_key($args['filters']['language']  ?? $_GET['language']  ?? 'nl');
$material  = sanitize_key($args['filters']['material']  ?? $_GET['material']  ?? '1');

// ========================
// Filteropties voorbereiden
// ========================
$examTypes  = array_filter($args['exam_types']  ?? [], fn($et) => !empty($et['id']) || $et['id'] === '');
$languages  = $args['languages']  ?? [];
$months     = $args['months']     ?? [];
$provinces  = $args['provinces']  ?? [];
$locations  = $args['locations']  ?? [];
$timeslots  = $args['timeslots']  ?? [];

// Helper: placeholder toevoegen aan begin van array (indien nog niet aanwezig)
$add_placeholder = function (&$array, string $label): void {
    if (empty($array) || (isset($array[0]['id']) && $array[0]['id'] !== '')) {
        array_unshift($array, ['id' => '', 'name' => $label]);
    }
};

$add_placeholder($languages,  __('Kies taal', 'pontifex-oi'));
$add_placeholder($months,     __('Kies maand', 'pontifex-oi'));
$add_placeholder($provinces,  __('Kies provincie', 'pontifex-oi'));
$add_placeholder($locations,  __('Kies locatie', 'pontifex-oi'));
$add_placeholder($timeslots,  __('Kies dagdeel', 'pontifex-oi'));

// Voor weekend-examens vaak geen material keuze tonen
$hide_material = in_array($exam_type, ['vca-basis-weekend', 'vca-vol-weekend'], true);

// ========================
// Paginatie & totaal
// ========================
$current_page = (int) ($args['current_page'] ?? 1);
$total_pages  = (int) ($args['total_pages']  ?? 1);
$total_results = (int) ($args['total_results'] ?? 0);
?>

<div class="pontifex-oi-stepper" role="heading" aria-level="2" tabindex="0">
    <?php esc_html_e('Stap 1: Inplannen', 'pontifex-oi'); ?>
</div>

<section class="pontifex-oi-section">
    <div id="step-1" class="active">
        <div class="pontifex-oi-bestellen">
            <form class="pontifex-oi-filters" 
                  method="get" 
                  action="" 
                  aria-label="<?php esc_attr_e('Filter examens en planning', 'pontifex-oi'); ?>">
                
                <div class="pontifex-oi-filter-row">
                    <div class="pontifex-oi-filter-group">
                        <h2 class="select-exam-label"><?php esc_html_e('Selecteer een examen', 'pontifex-oi'); ?></h2>
                        
                        <div class="pontifex-oi-select-row">
                            <!-- Examensoort -->
                            <div class="pontifex-oi-filter-single">
                                <label for="exam_type-select"><?php esc_html_e('Examensoort', 'pontifex-oi'); ?></label>
                                <select id="exam_type-select" 
                                        name="exam_type" 
                                        class="pontifex-oi-filter" 
                                        data-filter="exam_type" 
                                        required>
                                    <option value=""><?php esc_html_e('Kies examensoort', 'pontifex-oi'); ?></option>
                                    <?php foreach ($examTypes as $opt): ?>
                                        <option value="<?php echo esc_attr($opt['id']); ?>"
                                            <?php selected($exam_type, $opt['id']); ?>>
                                            <?php echo esc_html($opt['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Taal -->
                            <div class="pontifex-oi-filter-single">
                                <label for="language-select"><?php esc_html_e('Taal', 'pontifex-oi'); ?></label>
                                <select id="language-select" 
                                        name="language" 
                                        class="pontifex-oi-filter" 
                                        data-filter="language" 
                                        required>
                                    <?php foreach ($languages as $opt): ?>
                                        <option value="<?php echo esc_attr($opt['id']); ?>"
                                            <?php selected($language, $opt['id']); ?>>
                                            <?php echo esc_html($opt['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Soort examen (material) – soms verborgen -->
                            <div class="pontifex-oi-filter-single" 
                                 <?php echo $hide_material ? 'style="display:none;"' : ''; ?>>
                                <label for="material-select"><?php esc_html_e('Soort examen', 'pontifex-oi'); ?></label>
                                <select id="material-select" 
                                        name="material" 
                                        class="pontifex-oi-filter" 
                                        data-filter="material"
                                        data-selected-value="<?php echo esc_attr($material); ?>">
                                    <option value=""><?php esc_html_e('Kies soort examen', 'pontifex-oi'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <h2 class="select-date-label"><?php esc_html_e('Kies een datum en locatie', 'pontifex-oi'); ?></h2>

                <div class="pontifex-oi-filter-row -no-gap">
                    <?php foreach ([
                        ['month',     'Maand',      $months],
                        ['province',  'Provincie',  $provinces],
                        ['location',  'Locatie',    $locations],
                        ['timeslot',  'Dagdeel',    $timeslots]
                    ] as [$field, $label, $options]): ?>
                        <div class="pontifex-oi-filter-group">
                            <div class="pontifex-oi-filter-single">
                                <label for="<?php echo esc_attr($field); ?>-select">
                                    <?php echo esc_html($label); ?>
                                </label>
                                <select id="<?php echo esc_attr($field); ?>-select"
                                        name="<?php echo esc_attr($field); ?>"
                                        class="pontifex-oi-filter"
                                        data-filter="<?php echo esc_attr($field); ?>">
                                    <?php foreach ($options as $opt): ?>
                                        <option value="<?php echo esc_attr($opt['id']); ?>"
                                            <?php selected($args['filters'][$field] ?? '', $opt['id']); ?>>
                                            <?php echo esc_html($opt['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </form>

            <!-- Tabel met beschikbare momenten -->
            <table id="pontifex-oi-table"
                   class="pontifex-oi-table pontifex-oi-table-custom"
                   role="table"
                   data-current-page="<?php echo esc_attr($current_page); ?>"
                   data-total-pages="<?php echo esc_attr($total_pages); ?>">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Datum', 'pontifex-oi'); ?></th>
                        <th><?php esc_html_e('Tijd', 'pontifex-oi'); ?></th>
                        <th><?php esc_html_e('Locatie', 'pontifex-oi'); ?></th>
                        <th><?php esc_html_e('Provincie', 'pontifex-oi'); ?></th>
                        <th><?php esc_html_e('Plekken', 'pontifex-oi'); ?></th>
                        <th><?php esc_html_e('Prijs', 'pontifex-oi'); ?></th>
                        <th><?php esc_html_e('Aanmelden', 'pontifex-oi'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($args['planning'])): ?>
                        <?php foreach ($args['planning'] as $row):
                            $row_exam     = $row['exam']     ?? $exam_type;
                            $row_language = $row['language'] ?? $language;
                            $row_material = '1'; // vaste default in dit template
                        ?>
                            <tr>
                                <td><?php echo esc_html($row['date'] ?? '-'); ?></td>
                                <td><?php echo esc_html($row['time'] ?? '-'); ?></td>
                                <td><?php echo esc_html($row['location'] ?? '-'); ?></td>
                                <td><?php echo esc_html($row['province'] ?? '-'); ?></td>
                                <td><?php echo esc_html($row['spots'] ?? '-'); ?></td>
                                <td>
                                    <span class="pontifex-oi-dynamic-price"
                                          data-date="<?php echo esc_attr($row['date'] ?? ''); ?>"
                                          data-time="<?php echo esc_attr($row['time'] ?? ''); ?>"
                                          data-location="<?php echo esc_attr($row['location'] ?? ''); ?>"
                                          data-exam="<?php echo esc_attr($row_exam); ?>"
                                          data-language="<?php echo esc_attr($row_language); ?>"
                                          data-material="<?php echo esc_attr($row_material); ?>">
                                        <span class="pontifex-oi-price-loader" aria-hidden="true">…</span>
                                        <span class="pontifex-oi-price-amount"></span>
                                    </span>
                                </td>
                                <td>
                                    <?php if (strtoupper($row['spots'] ?? '') !== 'VOL'): ?>
                                        <form method="get" 
                                              class="pontifex-oi-aanmeld-form" 
                                              action="<?php echo esc_url($registration_url); ?>">
                                            <input type="hidden" name="go" value="1">
                                            <input type="hidden" name="exam_type" value="<?php echo esc_attr($row_exam); ?>">
                                            <input type="hidden" name="language"  value="<?php echo esc_attr($row_language); ?>">
                                            <input type="hidden" name="material"  value="<?php echo esc_attr($row_material); ?>">
                                            <input type="hidden" name="date"      value="<?php echo esc_attr($row['date'] ?? ''); ?>">
                                            <input type="hidden" name="time"      value="<?php echo esc_attr($row['time'] ?? ''); ?>">
                                            <input type="hidden" name="location"  value="<?php echo esc_attr($row['location'] ?? ''); ?>">
                                            <input type="hidden" name="province"  value="<?php echo esc_attr($row['province'] ?? ''); ?>">
                                            <input type="hidden" name="spots"     value="<?php echo esc_attr($row['spots'] ?? ''); ?>">
                                            <input type="hidden" name="price"     value="" class="pontifex-oi-price-input">

                                            <button type="submit" class="pontifex-oi-aanmelden">
                                                <?php esc_html_e('Kandidaat aanmelden', 'pontifex-oi'); ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="pontifex-oi-vol"><?php esc_html_e('VOL', 'pontifex-oi'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="pontifex-no-results">
                                <?php esc_html_e('Geen beschikbare momenten gevonden', 'pontifex-oi'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- ARIA live regio voor dynamische updates -->
            <div class="pontifex-oi-aria-live is-visually-hidden" aria-live="polite" aria-atomic="true"></div>

            <!-- Kaarten container (voor mobiel/responsive view) -->
            <div id="pontifex-oi-cards-container" class="pontifex-oi-cards-container"></div>

            <!-- Paginatie & rijen per pagina -->
            <div class="pontifex-oi-pagination-wrapper"
                 role="region"
                 aria-label="<?php esc_attr_e("Navigatie resultatenpagina's", 'pontifex-oi'); ?>"
                 data-total-results="<?php echo esc_attr($total_results); ?>">
                <div class="-left">
                    <label class="pontifex-oi-rows">
                        <span><?php esc_html_e('Rijen per pagina', 'pontifex-oi'); ?></span>
                        <select id="pontifex-oi-results-per-page" 
                                class="pontifex-oi-rows-select"
                                aria-controls="pontifex-oi-table"
                                aria-label="<?php esc_attr_e('Aantal resultaten per pagina', 'pontifex-oi'); ?>">
                            <?php foreach ([7, 12, 25, 50] as $num): ?>
                                <option value="<?php echo $num; ?>" <?php selected($num, $args['per_page'] ?? 25); ?>>
                                    <?php echo $num; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="-right">
                    <nav class="pontifex-oi-pagination-nav"
                         aria-label="<?php esc_attr_e('Paginanavigatie', 'pontifex-oi'); ?>"
                         aria-live="polite"
                         aria-atomic="true">
                        <!-- Dynamisch gevuld door JS (zie pontifex-oi-pagination.js) -->
                    </nav>
                </div>
            </div>
        </div>
    </div>
</section>