<?php
namespace PontifexOI\Api;

use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class SoapClient
{
    private $client = null;
    private $endpoint;
    private $options = [
        'trace' => 1,
        'exceptions' => 1,
        'cache_wsdl' => WSDL_CACHE_BOTH,
        'connection_timeout' => 15,
        'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
        'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
    ];

    public function __construct()
    {
        $this->endpoint = get_option(
            'pontifex_oi_soap_url',
            'https://staging-webservice.pontifexcertificatie.nl/service.php/?wsdl'
        );
    }

    private function getClient()
    {
        if ($this->client === null) {
            try {
                if (!isset($this->options['cache_wsdl'])) {
                    $this->options['cache_wsdl'] = WSDL_CACHE_BOTH;
                }
                $this->client = new \SoapClient($this->endpoint, $this->options);
            } catch (\Exception $e) {
                error_log('PontifexOI: SOAP client aanmaken gefaald (' . get_class($e) . '); code=' . (int) $e->getCode());
                throw new \RuntimeException('SOAP client kon niet worden aangemaakt.', 0, $e);
            }
        }
        return $this->client;
    }

    /**
     * Deze plugin gebruikt geen registratie via SOAP meer.
     * Alleen planning wordt opgehaald. Deze functie logt NIET meer,
     * aangezien de aanroep van de webhook handler zelf (extern) zal worden verwijderd
     * of vervangen door een logregel.
     *
     * @param array $order De ordergegevens die ontvangen zijn (worden nu genegeerd).
     * @return bool Altijd true.
     */
    public function sendRegistration(array $order): bool
    {
        // De logregel is verplaatst naar de aanroepende Webhook-handler om dubbele logging te voorkomen.
        // Zie de notitie in de code van de Webhook-handler.
        return true;
    }

    /**
     * Haalt planning op via SOAP en slaat lokaal op in DB.
     *
     * @return array Lijst van planning_identifiers die zijn bijgewerkt.
     * @throws Exception
     */
    public function fetchAndStorePlanning(): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pontifex_planning';

        $user_identifier = (int) get_option('pontifex_oi_soap_user_id');
        $hash = trim((string) get_option('pontifex_oi_soap_hash'));

        if (empty($user_identifier) || empty($hash)) {
            error_log("PontifexOI SOAP ERROR - Lege authenticatievelden.");
            throw new \Exception('SOAP authenticatiegegevens ontbreken.');
        }

        $params = [
            'user_identifier' => (int) $user_identifier,
            'hash' => $hash,
        ];

        $client = $this->getClient();
        $seenIds = [];
        $reconciliation_safe = true;

        try {
            $response = $client->__soapCall('getWalkInPlanning', $params);

            // Normaliseren van mogelijke wrapper/result objecten
            if (is_object($response) && isset($response->getWalkInPlanningResult)) {
                $response = $response->getWalkInPlanningResult;
            }
            if ($response instanceof \stdClass) {
                $response = (array) $response;
            }
            if (isset($response['planning_identifier'])) {
                $response = [(object) $response];
            }

            if (!is_array($response) || empty($response)) {
                error_log("PontifexOI ERROR - Geen planningen ontvangen van SOAP API.");
                return [];
            }

            foreach ($response as $planning) {
                if (is_array($planning)) {
                    $planning = (object) $planning;
                }

                $planning_identifier = sanitize_text_field((string) ($planning->planning_identifier ?? ''));
                if ($planning_identifier === '' || strlen($planning_identifier) > 64) {
                    $reconciliation_safe = false;
                    continue;
                }

                $data = [
                    'planning_identifier' => $planning_identifier,
                    'planning_date' => $planning->planning_date ?? null,
                    'planning_time' => $planning->planning_time ?? null,
                    'planning_start_date' => isset($planning->planning_start_date) ? date('Y-m-d H:i:s', strtotime($planning->planning_start_date)) : null,
                    'planning_updated' => isset($planning->planning_updated) ? date('Y-m-d H:i:s', strtotime($planning->planning_updated)) : null,
                    'planning_status' => $planning->planning_status ?? null,
                    'available_seats' => (int) ($planning->available_seats ?? 0),
                    'location_identifier' => $planning->location?->location_identifier ?? null,
                    'location_name' => $planning->location?->location_name ?? '',
                    'location_street' => $planning->location?->location_street ?? '',
                    'location_number' => $planning->location?->location_number ?? '',
                    'location_suffix' => $planning->location?->location_suffix ?? '',
                    'location_postcode' => $planning->location?->location_zip_code ?? '',
                    'location_city' => $planning->location?->location_city ?? '',
                    'location_province' => $planning->location?->location_province ?? '',
                    'location_country' => $planning->location?->location_country ?? '',
                    'location_seats' => (int) ($planning->location?->location_seats ?? 0),
                ];

                $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$table_name}
                     (planning_identifier, planning_date, planning_time, planning_start_date, planning_updated, planning_status, available_seats, location_identifier, location_name, location_street, location_number, location_suffix, location_postcode, location_city, location_province, location_country, location_seats)
                     VALUES (%s, %s, %s, %s, %s, %s, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d)
                     ON DUPLICATE KEY UPDATE
                      planning_date = VALUES(planning_date),
                      planning_time = VALUES(planning_time),
                      planning_start_date = VALUES(planning_start_date),
                      planning_updated = VALUES(planning_updated),
                      planning_status = VALUES(planning_status),
                      available_seats = VALUES(available_seats),
                      location_identifier = VALUES(location_identifier),
                      location_name = VALUES(location_name),
                      location_street = VALUES(location_street),
                      location_number = VALUES(location_number),
                      location_suffix = VALUES(location_suffix),
                      location_postcode = VALUES(location_postcode),
                      location_city = VALUES(location_city),
                      location_province = VALUES(location_province),
                      location_country = VALUES(location_country),
                      location_seats = VALUES(location_seats)",
                    ...array_values($data)
                ));
                $seenIds[] = $data['planning_identifier'];
            }

            // Alleen destructief reconciliëren als elk ontvangen record een geldige identifier had.
            // Een lege of malformed upstream response mag bestaande planning nooit weggooien.
            if ($reconciliation_safe && !empty($seenIds)) {
                $placeholders = implode(',', array_fill(0, count($seenIds), '%s'));
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$table_name} WHERE planning_identifier NOT IN ($placeholders) AND planning_date >= CURDATE()",
                    ...$seenIds
                ));
            } elseif (!$reconciliation_safe) {
                error_log('PontifexOI: planning-reconciliatie overgeslagen wegens ongeldige upstream identifiers.');
            }

            // Bust caches
            delete_transient('pontifex_oi_filter_months');
            delete_transient('pontifex_oi_filter_provinces');
            delete_transient('pontifex_oi_filter_cities');
            global $wpdb;
            $table = $wpdb->prefix . 'pontifex_planning';
            $provs = $wpdb->get_col("
                SELECT DISTINCT location_province 
                FROM {$table}
                WHERE location_province IS NOT NULL AND location_province <> ''
            ");
            if ($provs) {
                foreach ($provs as $p) {
                    $key = 'pontifex_oi_filter_cities_' . sanitize_title($p);
                    delete_transient($key);
                }
            }


            return $seenIds;

        } catch (Exception $e) {
            error_log('PontifexOI SOAP planning fetch failed (' . get_class($e) . '); code=' . (int) $e->getCode());
            throw $e;
        }
    }

    /**
     * Haalt planning op uit DB met filters
     * @param array $filters Associatieve array met filter criteria
     * @param int $per_page Het aantal resultaten per pagina (0 voor alles)
     * @param int $offset Het startpunt voor paginatie
     * @return array
     */
    public function getPlanning($filters = [], $per_page = 0, $offset = 0)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pontifex_planning';
        $where = ["p.planning_date >= CURDATE()"];
        $args = [];

        $sql = "SELECT * FROM {$table} p WHERE 1=1";
        
        if (!empty($filters['month'])) {
            $where[] = "DATE_FORMAT(p.planning_date, '%Y-%m') = %s";
            $args[] = $filters['month'];
        }
        // Nieuwe code: accepteer 'city' of 'location'
        if (!empty($filters['city'])) {
            $where[] = "p.location_city = %s";
            $args[]  = $filters['city'];
        } elseif (!empty($filters['location'])) {
            $where[] = "p.location_city = %s";
            $args[]  = $filters['location'];
        }

        if (!empty($filters['province'])) {
            $where[] = "LOWER(p.location_province) = LOWER(%s)";
            $args[] = $filters['province'];
        }
        if (!empty($filters['timeslot'])) {
            $slot = $filters['timeslot'];
            if ($slot === 'ochtend') {
                $where[] = "HOUR(p.planning_time) < 12";
            } elseif ($slot === 'middag') {
                $where[] = "HOUR(p.planning_time) >= 12 AND HOUR(p.planning_time) < 18";
            } elseif ($slot === 'avond') {
                $where[] = "HOUR(p.planning_time) >= 18";
            }
        }

        if ($where) {
            $sql .= " AND " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY p.planning_date ASC, p.planning_time ASC";
        
        // WIJZIGING: Paginatie toevoegen aan SQL via LIMIT en OFFSET
        if ($per_page > 0) {
            $sql .= $wpdb->prepare(' LIMIT %d OFFSET %d', $per_page, $offset);
        }

        $results = !empty($args)
            ? $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A)
            : $wpdb->get_results($sql, ARRAY_A);

        $out = [];
        foreach ($results as $row) {
            $out[] = [
                'date' => date_i18n('d-m-Y (l)', strtotime($row['planning_date'])),
                'time' => substr($row['planning_time'], 0, 5),
                'location' => (!empty($row['location_city']) ? $row['location_city'] : $row['location_name']),
                'province' => $row['location_province'],
                'spots' => (int)$row['available_seats'] > 0 ? $row['available_seats'] . ' plaatsen' : 'VOL',
                'price' => isset($row['planning_price']) && $row['planning_price']
                    ? '&euro; ' . number_format((float)$row['planning_price'], 2, ',', '.')
                    : '',
            ];
        }
        return $out;
    }

    /**
     * Haalt een unieke lijst van maanden op uit de DB voor toekomstige planningen.
     * @return array
     */
    public function getMonths()
    {
        $cache = get_transient('pontifex_oi_filter_months');
        if ($cache !== false) {
            return $cache;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'pontifex_planning';
        $months = $wpdb->get_col("
            SELECT DISTINCT DATE_FORMAT(planning_date, '%Y-%m') AS month
            FROM {$table}
            WHERE planning_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
            ORDER BY month ASC
        ");

        $out = [['id' => '', 'name' => 'Toon alles']];
        foreach ($months as $m) {
            $out[] = ['id' => $m, 'name' => ucfirst(date_i18n('F Y', strtotime($m . '-01')))];
        }

        set_transient('pontifex_oi_filter_months', $out, HOUR_IN_SECONDS);
        return $out;
    }

    /**
     * Haalt een unieke lijst van provincies op uit de DB voor toekomstige planningen.
     * @return array
     */
    public function getProvinces()
    {
        $cache = get_transient('pontifex_oi_filter_provinces');
        if ($cache !== false) {
            return $cache;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'pontifex_planning';
        $provinces = $wpdb->get_col("
            SELECT DISTINCT location_province
            FROM $table
            WHERE location_province IS NOT NULL
              AND location_province <> ''
              AND planning_date >= CURDATE()
            ORDER BY location_province ASC
        ");

        $out = [['id' => '', 'name' => 'Toon alles']];
        foreach ($provinces as $p) {
            $out[] = ['id' => $p, 'name' => $p];
        }

        set_transient('pontifex_oi_filter_provinces', $out, HOUR_IN_SECONDS);
        return $out;
    }

    /**
     * Haalt een unieke lijst van steden op uit de DB voor toekomstige planningen.
     * @param array $filters Optionele filters (bv. provincie)
     * @return array
     */
    public function getLocations($filters = [])
    {
        $cacheKey = 'pontifex_oi_filter_cities';
        if (!empty($filters['province'])) {
            $cacheKey .= '_' . sanitize_title($filters['province']);
        }
        
        $cache = get_transient($cacheKey);
        if ($cache !== false) {
            return $cache;
        }
        
        global $wpdb;
        $t = $wpdb->prefix . 'pontifex_planning';
    
        $where = [
            "location_city IS NOT NULL",
            "location_city <> ''",
            "planning_date >= CURDATE()"
        ];
        $args  = [];
    
        if (!empty($filters['province'])) {
            $where[] = "location_province = %s";
            $args[]  = $filters['province'];
        }
    
        $sql = "
            SELECT DISTINCT location_city AS city, location_province AS province
            FROM {$t}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY location_city ASC
        ";
    
        $results = !empty($args)
            ? $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A)
            : $wpdb->get_results($sql, ARRAY_A);
        
        $out = [['id' => '', 'name' => 'Toon alles']];
        foreach ($results as $l) {
            $out[] = ['id' => $l['city'], 'name' => $l['city']];
        }

        // kortere TTL om stale steden na cron/knop sneller te verversen
        set_transient($cacheKey, $out, 5 * MINUTE_IN_SECONDS);
        return $out;
    }

    public function getTimeslots($filters = [])
    {
        return [
            ['id' => '', 'name' => 'Toon alles'],
            ['id' => 'ochtend', 'name' => 'Ochtend'],
            ['id' => 'middag', 'name' => 'Middag'],
            ['id' => 'avond', 'name' => 'Avond'],
        ];
    }

    public function getLanguages($filters = [])
    {
        return [
            ['id' => 'nl', 'name' => 'Nederlands'],
            ['id' => 'en', 'name' => 'Engels'],
        ];
    }

    public function getMaterials($filters = [])
    {
        return [
            ['id' => '', 'name' => 'Geen keuze'],
            ['id' => '1', 'name' => 'Los examen'],
            ['id' => '2', 'name' => 'Examen + boek'],
            ['id' => '4', 'name' => 'Examen + e-learning'],
            ['id' => '5', 'name' => 'Examen + proefexamens'],
            ['id' => '6', 'name' => 'Examen + boek + proefexamens'],
            ['id' => '7', 'name' => 'Examen + e-learning + proefexamens'],
        ];
    }

    /**
     * Masks a given hash for logging purposes.
     *
     * @param string $hash The hash to mask.
     * @return string
     */
    private function maskHash(string $hash): string
    {
        if (empty($hash)) {
            return '';
        }
        return substr($hash, 0, 1) . '...' . substr($hash, -1);
    }

    /**
     * Publieke debug-helper voor logging vanuit je knop
     *
     * @param bool $do_ping
     * @return array
     */
    public function debugHandshake(bool $do_ping = false): array
    {
        $out = ['ok' => false];
        try {
            $client = $this->getClient();
            $out['soap_functions'] = $client->__getFunctions();
            if ($do_ping) {
                $user_id = (int) get_option('pontifex_oi_soap_user_id', 0);
                $hash = (string) get_option('pontifex_oi_soap_hash', '');

                if ($user_id && $hash) {
                    $client->__soapCall('getWalkInPlanning', [
                        'user_identifier' => $user_id,
                        'hash' => $hash,
                    ]);
                }
            }
            $out['ok'] = true;
            $out['last_request'] = $client->__getLastRequest();
            $out['last_response'] = $client->__getLastResponse();
        } catch (\Throwable $e) {
            $out['message'] = $e->getMessage();
            $out['last_request'] = isset($client) ? $client->__getLastRequest() : null;
            $out['last_response'] = isset($client) ? $client->__getLastResponse() : null;
        }
        return $out;
    }
}