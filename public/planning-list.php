<?php
/**
 * Handles the redirection for exam registration and renders the exam planning interface.
 */

// Handle registration redirection if 'aanmelden' parameter is present
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['aanmelden'])) {
    $query = http_build_query([
        'aanmelden'   => 1,
        'exam_type'   => $_GET['exam_type'] ?? '',
        'language'    => $_GET['language'] ?? '',
        'material'    => $_GET['material'] ?? '',
        'date'        => $_GET['date'] ?? '',
        'time'        => $_GET['time'] ?? '',
        'location'    => $_GET['location'] ?? '',
        'province'    => $_GET['province'] ?? '',
        'spots'       => $_GET['spots'] ?? '',
        'price'       => $_GET['price'] ?? '',
    ]);
    wp_redirect('/cursus-inschrijven/?' . $query);
    exit;
}

use function PontifexOI\Helpers\render_select;

// Defaults
$exam_type = $args['filters']['exam_type'] ?? $_GET['exam_type'] ?? 'los-examen-vca-basis';
if (empty($exam_type)) $exam_type = 'los-examen-vca-basis';

$language = $args['filters']['language'] ?? $_GET['language'] ?? 'nl';
if (empty($language)) $language = 'nl';

$material = $args['filters']['material'] ?? $_GET['material'] ?? '1';
if (empty($material)) $material = '1';

$examTypes = array_filter($args['exam_types'] ?? [], fn($et) => !empty($et['id']));
$languages = $args['languages'] ?? [];
$months     = $args['months'] ?? [];
$provinces  = $args['provinces'] ?? [];
$locations  = $args['locations'] ?? [];
$timeslots  = $args['timeslots'] ?? [];

$add_toon_alles = function (&$arr, $label) {
    if (!isset($arr[0]) || (isset($arr[0]['id']) && $arr[0]['id'] !== '')) {
        array_unshift($arr, ['id' => '', 'name' => $label]);
    }
};
if (($args['context'] ?? '') === 'filter') {
    $add_toon_alles($languages,  __('Toon alles','pontifex-oi'));
    $add_toon_alles($months,     __('Toon alles','pontifex-oi'));
    $add_toon_alles($provinces,  __('Toon alles','pontifex-oi'));
    $add_toon_alles($locations,  __('Toon alles','pontifex-oi'));
    $add_toon_alles($timeslots,  __('Toon alles','pontifex-oi'));
}

$hide_material = in_array($exam_type, ['vca-basis-weekend','vca-vol-weekend'], true);

// Voor initiële laadtijd, optioneel voor JS (kan blijven)
$current_page = $args['current_page'] ?? 1;
$total_pages = $args['total_pages'] ?? 1;
?>

<div class="pontifex-oi-stepper" role="heading" aria-level="2" tabindex="0">
    <?php esc_html_e('Stap 1: Inplannen','pontifex-oi'); ?>
</div>

<section class="pontifex-oi-section">
  <div id="step-1" class="active">
    <div class="pontifex-oi-bestellen">

      <form class="pontifex-oi-filters" method="get" action="" aria-label="<?php esc_attr_e('Filter examens en planning','pontifex-oi'); ?>">
        <!-- Examensoort + Taal -->
        <div class="pontifex-oi-filter-row">
          <div class="pontifex-oi-filter-group">
            <h2 class="select-exam-label"><?php esc_html_e('Selecteer een examen','pontifex-oi'); ?></h2>
            <div class="pontifex-oi-select-row">
              <div class="pontifex-oi-filter-single">
                <label for="exam_type-select"><?php esc_html_e('Examensoort','pontifex-oi'); ?></label>
                <select id="exam_type-select" name="exam_type" class="pontifex-oi-filter" data-filter="exam_type" required>
                  <?php foreach($examTypes as $opt): ?>
                    <option value="<?php echo esc_attr($opt['id']); ?>" <?php selected($exam_type, $opt['id']); ?>>
                      <?php echo esc_html($opt['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="pontifex-oi-filter-single">
                <label for="language-select"><?php esc_html_e('Taal','pontifex-oi'); ?></label>
                <select id="language-select" name="language" class="pontifex-oi-filter" data-filter="language" required>
                  <?php foreach($languages as $opt): ?>
                    <option value="<?php echo esc_attr($opt['id']); ?>" <?php selected($language, $opt['id']); ?>>
                      <?php echo esc_html($opt['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Datum & Locatie -->
        <h2 class="select-date-label"><?php esc_html_e('Kies een datum en locatie','pontifex-oi'); ?></h2>
        <div class="pontifex-oi-filter-row -no-gap">
          <?php foreach ([ 
            ['month','Maand',$months],
            ['province','Provincie',$provinces],
            ['location','Locatie',$locations],
            ['timeslot','Dagsoort',$timeslots]
          ] as list($field,$label,$options)): ?>
            <div class="pontifex-oi-filter-group">
              <div class="pontifex-oi-filter-single">
                <label for="<?php echo $field; ?>-select"><?php echo esc_html__($label,'pontifex-oi'); ?></label>
                <select id="<?php echo $field; ?>-select" name="<?php echo $field; ?>" class="pontifex-oi-filter" data-filter="<?php echo $field; ?>">
                  <?php foreach($options as $opt): ?>
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

      <!-- Tabel weergave -->
      <table id="pontifex-oi-table" 
             class="pontifex-oi-table pontifex-oi-table-custom" 
             role="table"
             data-current-page="<?php echo esc_attr($current_page); ?>"
             data-total-pages="<?php echo esc_attr($total_pages); ?>">
        <thead>
          <tr>
            <th><?php esc_html_e('Datum','pontifex-oi'); ?></th>
            <th><?php esc_html_e('Tijd','pontifex-oi'); ?></th>
            <th><?php esc_html_e('Locatie','pontifex-oi'); ?></th>
            <th><?php esc_html_e('Provincie','pontifex-oi'); ?></th>
            <th><?php esc_html_e('Plekken','pontifex-oi'); ?></th>
            <th><?php esc_html_e('Prijs','pontifex-oi'); ?></th>
            <th><?php esc_html_e('Aanmelden','pontifex-oi'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($args['planning'])): ?>
            <?php foreach($args['planning'] as $row):
              $row_exam     = !empty($row['exam'])     ? $row['exam']     : $exam_type;
              $row_language = !empty($row['language']) ? $row['language'] : $language;
              $row_material = '1';
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
                  <span class="pontifex-oi-price-loader"></span>
                </span>
              </td>
              <td>
                <?php if (strtoupper($row['spots'] ?? '') !== 'VOL'): ?>
                  <form method="get" class="pontifex-oi-aanmeld-form" action="/cursus-inschrijven/">
                    <input type="hidden" name="aanmelden" value="1">
                    <input type="hidden" name="exam_type"  value="<?php echo esc_attr($row_exam); ?>">
                    <input type="hidden" name="language"   value="<?php echo esc_attr($row_language); ?>">
                    <input type="hidden" name="material"   value="<?php echo esc_attr($row_material); ?>">
                    <input type="hidden" name="date"       value="<?php echo esc_attr($row['date'] ?? ''); ?>">
                    <input type="hidden" name="time"       value="<?php echo esc_attr($row['time'] ?? ''); ?>">
                    <input type="hidden" name="location"   value="<?php echo esc_attr($row['location'] ?? ''); ?>">
                    <input type="hidden" name="province"   value="<?php echo esc_attr($row['province'] ?? ''); ?>">
                    <input type="hidden" name="spots"      value="<?php echo esc_attr($row['spots'] ?? ''); ?>">
                    <input type="hidden" name="price"      value="" class="pontifex-oi-price-input">
                    <button type="submit" class="pontifex-oi-aanmelden"><?php esc_html_e('Kandidaat aanmelden','pontifex-oi'); ?></button>
                  </form>
                <?php else: ?>
                  <span class="pontifex-oi-vol"><?php esc_html_e('VOL','pontifex-oi'); ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="7"><?php esc_html_e('Geen resultaten gevonden','pontifex-oi'); ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <!-- Kaarten weergave (tablet/mobiel) -->
      <div id="pontifex-oi-cards-container" class="pontifex-oi-cards-container">
        <?php if (!empty($args['planning'])): ?>
          <?php foreach($args['planning'] as $row):
            $row_exam     = !empty($row['exam'])     ? $row['exam']     : $exam_type;
            $row_language = !empty($row['language']) ? $row['language'] : $language;
            $spots        = $row['spots'] ?? '-';
          ?>
          <article class="pontifex-oi-card" role="listitem">
            <dl>
              <dd class="pontifex-oi-date"><?php echo esc_html($row['date'] ?? '-'); ?></dd>
              <dt><?php esc_html_e('Tijd','pontifex-oi'); ?></dt><dd><?php echo esc_html($row['time'] ?? '-'); ?></dd>
              <dt><?php esc_html_e('Locatie','pontifex-oi'); ?></dt><dd><?php echo esc_html($row['location'] ?? '-'); ?></dd>
              <dt><?php esc_html_e('Provincie','pontifex-oi'); ?></dt><dd><?php echo esc_html($row['province'] ?? '-'); ?></dd>
              <dt><?php esc_html_e('Plekken vrij','pontifex-oi'); ?></dt><dd><?php echo esc_html($spots); ?></dd>
              <dt><?php esc_html_e('Prijs','pontifex-oi'); ?></dt>
              <dd>
                <span class="pontifex-oi-dynamic-price"
                      data-date="<?php echo esc_attr($row['date'] ?? ''); ?>"
                      data-time="<?php echo esc_attr($row['time'] ?? ''); ?>"
                      data-location="<?php echo esc_attr($row['location'] ?? ''); ?>"
                      data-exam="<?php echo esc_attr($row_exam); ?>"
                      data-language="<?php echo esc_attr($row_language); ?>"
                      data-material="1">
                  <span class="pontifex-oi-price-loader">…</span>
                </span>
              </dd>
              <dd>
                <?php if (strtoupper($spots) !== 'VOL'): ?>
                  <form method="get" class="pontifex-oi-aanmeld-form" action="/cursus-inschrijven/">
                    <input type="hidden" name="aanmelden"   value="1">
                    <input type="hidden" name="exam_type"   value="<?php echo esc_attr($row_exam); ?>">
                    <input type="hidden" name="language"    value="<?php echo esc_attr($row_language); ?>">
                    <input type="hidden" name="material"    value="1">
                    <input type="hidden" name="date"        value="<?php echo esc_attr($row['date'] ?? ''); ?>">
                    <input type="hidden" name="time"        value="<?php echo esc_attr($row['time'] ?? ''); ?>">
                    <input type="hidden" name="location"    value="<?php echo esc_attr($row['location'] ?? ''); ?>">
                    <input type="hidden" name="province"    value="<?php echo esc_attr($row['province'] ?? ''); ?>">
                    <input type="hidden" name="spots"       value="<?php echo esc_attr($row['spots'] ?? ''); ?>">
                    <input type="hidden" name="price"       value="" class="pontifex-oi-price-input">
                    <button type="submit" class="pontifex-oi-aanmelden"><?php esc_html_e('Kandidaat aanmelden','pontifex-oi'); ?></button>
                  </form>
                <?php else: ?>
                  <span class="pontifex-oi-vol"><?php esc_html_e('VOL','pontifex-oi'); ?></span>
                <?php endif; ?>
              </dd>
            </dl>
          </article>
          <?php endforeach; ?>
        <?php else: ?>
          <!-- Aangepast: Geen resultaten kaart ipv simpele paragraaf -->
          <div class="pontifex-oi-card-noresults">
            <div class="pontifex-oi-card-noresults-header"><?php esc_html_e('Geen resultaten','pontifex-oi'); ?></div>
            <div class="pontifex-oi-card-noresults-body">
              <?php esc_html_e('Geen resultaten voor deze combinatie. Pas je filters aan voor meer opties.','pontifex-oi'); ?>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Paginatie -->
      <div class="pontifex-oi-pagination-wrapper" role="region" aria-label="<?php esc_attr_e("Navigatie resultatenpagina's",'pontifex-oi'); ?>">
        <div class="pontifex-oi-results-per-page">
          <label for="pontifex-oi-results-per-page"><?php esc_html_e('Aantal resultaten','pontifex-oi'); ?>:</label>
          <select id="pontifex-oi-results-per-page"
                  aria-controls="pontifex-oi-table"
                  aria-label="<?php esc_attr_e('Aantal resultaten per pagina','pontifex-oi'); ?>">
            <option value="10" <?php selected(10, $args['per_page'] ?? 10); ?>>10</option>
            <option value="25" <?php selected(25, $args['per_page'] ?? 10); ?>>25</option>
            <option value="50" <?php selected(50, $args['per_page'] ?? 10); ?>>50</option>
          </select>
        </div>

        <nav aria-label="<?php echo esc_attr__('Paginanavigatie','pontifex-oi'); ?>">
          <!-- Pagination links worden nu dynamisch gegenereerd via JS -->
        </nav>
      </div>

    </div>
  </div>
</section>