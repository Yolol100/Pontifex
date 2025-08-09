<?php
namespace PontifexOI\Api;

use Exception;

class SoapClient {
    private $client = null;
    private $endpoint;
    private $options = [
        'trace'              => 1,
        'exceptions'         => 1,
        'cache_wsdl'         => WSDL_CACHE_BOTH,
        'connection_timeout' => 15,
        'compression'        => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
        'features'           => SOAP_SINGLE_ELEMENT_ARRAYS,
    ];

    public function __construct() {
        // Altijd de staging-URL met ?wsdl als default
        $this->endpoint = get_option(
            'pontifex_oi_soap_url',
            'https://staging-webservice.pontifexcertificatie.nl/service.php/?wsdl'
        );
    }

    private function getClient() {
        if ($this->client === null) {
            try {
                $this->client = new \SoapClient($this->endpoint, $this->options);
            } catch (Exception $e) {
                error_log('PontifexOI: SOAP client aanmaken gefaald: ' . $e->getMessage());
                throw new Exception('SOAP client kon niet worden aangemaakt: ' . $e->getMessage());
            }
        }
        return $this->client;
    }

    /**
     * Haalt planning op via SOAP en slaat lokaal op in DB.
     *
     * @throws Exception
     */
    public function fetchAndStorePlanning() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pontifex_planning';

        $user_identifier    = get_option('pontifex_oi_soap_user_id', '');
        $company_identifier = get_option('pontifex_oi_soap_company_id', ''); // Wordt niet meegestuurd
        $hash               = get_option('pontifex_oi_soap_hash', '');

        // Debug logging
        error_log("PontifexOI DEBUG - user_identifier: " . var_export($user_identifier, true) .
                  " | company_identifier: " . var_export($company_identifier, true) .
                  " | hash: " . var_export($hash, true));

        if (empty($user_identifier) || empty($hash)) {
            error_log("PontifexOI ERROR - Lege SOAP authenticatievelden: user_identifier=" . var_export($user_identifier,true) . ", hash=" . var_export($hash,true));
            throw new Exception('SOAP authenticatiegegevens niet correct ingesteld.');
        }

        // Company_identifier NIET meesturen!
        $params = [
            'user_identifier' => (int) $user_identifier,
            'hash'            => (string) $hash,
            // 'partner'       => (int) $company_identifier, // Niet meesturen!
        ];

        error_log("PontifexOI DEBUG - SOAP-call params: " . print_r($params, true));

        $client = $this->getClient();

        try {
            // Let op: GEEN partner meesturen!
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
                throw new Exception('Geen planningen ontvangen van SOAP API.');
            }

            foreach ($response as $planning) {
                if (is_array($planning)) {
                    $planning = (object) $planning;
                }

                $data = [
                    'planning_identifier'   => (string) ($planning->planning_identifier ?? ''),
                    'planning_date'         => $planning->planning_date ?? null,
                    'planning_time'         => $planning->planning_time ?? null,
                    'planning_start_date'   => isset($planning->planning_start_date) ? date('Y-m-d H:i:s', strtotime($planning->planning_start_date)) : null,
                    'planning_updated'      => isset($planning->planning_updated) ? date('Y-m-d H:i:s', strtotime($planning->planning_updated)) : null,
                    'planning_status'       => $planning->planning_status ?? null,
                    'available_seats'       => (int) ($planning->available_seats ?? 0),
                    'location_identifier'   => $planning->location?->location_identifier ?? null,
                    'location_name'         => $planning->location?->location_name ?? '',
                    'location_street'       => $planning->location?->location_street ?? '',
                    'location_number'       => $planning->location?->location_number ?? '',
                    'location_suffix'       => $planning->location?->location_suffix ?? '',
                    'location_zip_code'     => $planning->location?->location_zip_code ?? '',
                    'location_city'         => $planning->location?->location_city ?? '',
                    'location_province'     => $planning->location?->location_province ?? '',
                    'location_country'      => $planning->location?->location_country ?? '',
                    'location_seats'        => (int) ($planning->location?->location_seats ?? 0),
                ];

                // Check of planning al bestaat
                $exists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT planning_identifier FROM $table_name WHERE planning_identifier = %s",
                        $data['planning_identifier']
                    )
                );

                $format = [
                    '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d',
                    '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d',
                ];

                if ($exists) {
                    $wpdb->update($table_name, $data, ['planning_identifier' => $data['planning_identifier']], $format, ['%s']);
                } else {
                    $wpdb->insert($table_name, $data, $format);
                }
            }

            return true;

        } catch (Exception $e) {
            error_log('PontifexOI SOAP fout: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            if (method_exists($e, 'getResponse')) {
                error_log('PontifexOI SOAP response: ' . $e->getResponse());
            }
            throw $e;
        }
    }

    /**
     * Haal planning op uit DB met filters
     * @param array $filters Associatieve array met filter criteria
     * @return array
     */
    public function getPlanning($filters = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'pontifex_planning';
        $where = [];
        $args = [];

        if (!empty($filters['month'])) {
            $where[] = "DATE_FORMAT(planning_date, '%Y-%m') = %s";
            $args[] = $filters['month'];
        }
        if (!empty($filters['province'])) {
            $where[] = "LOWER(location_province) = LOWER(%s)";
            $args[] = $filters['province'];
        }
        if (!empty($filters['location'])) {
            $where[] = "location_name = %s";
            $args[] = $filters['location'];
        }
        if (!empty($filters['timeslot'])) {
            $slot = $filters['timeslot'];
            if ($slot === 'ochtend') {
                $where[] = "HOUR(planning_time) < 12";
            } elseif ($slot === 'middag') {
                $where[] = "HOUR(planning_time) >= 12 AND HOUR(planning_time) < 18";
            } elseif ($slot === 'avond') {
                $where[] = "HOUR(planning_time) >= 18";
            }
        }

        $sql = "SELECT * FROM $table";
        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY planning_date ASC, planning_time ASC";

        $results = $where
            ? $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A)
            : $wpdb->get_results($sql, ARRAY_A);

        $out = [];
        foreach ($results as $row) {
            $out[] = [
                'date'     => date_i18n('d-m-Y (l)', strtotime($row['planning_date'])),
                'time'     => substr($row['planning_time'], 0, 5),
                'location' => $row['location_name'],
                'province' => $row['location_province'],
                'spots'    => (int)$row['available_seats'] > 0 ? $row['available_seats'] . ' plaatsen' : 'VOL',
                'price'    => isset($row['planning_price']) && $row['planning_price']
                    ? '&euro; ' . number_format((float)$row['planning_price'], 2, ',', '.')
                    : '',
            ];
        }
        return $out;
    }

    public function getMonths($filters = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'pontifex_planning';
        $months = $wpdb->get_col("SELECT DISTINCT DATE_FORMAT(planning_date, '%Y-%m') as month FROM $table ORDER BY month ASC");
        $out = [['id' => '', 'name' => 'Toon alles']];
        foreach ($months as $m) {
            $out[] = ['id' => $m, 'name' => ucfirst(date_i18n('F', strtotime($m . '-01')))];
        }
        return $out;
    }

    public function getProvinces($filters = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'pontifex_planning';
        $provinces = $wpdb->get_col("SELECT DISTINCT location_province FROM $table ORDER BY location_province ASC");
        $out = [['id' => '', 'name' => 'Toon alles']];
        foreach ($provinces as $p) {
            $out[] = ['id' => $p, 'name' => $p];
        }
        return $out;
    }

    public function getLocations($filters = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'pontifex_planning';
        $locations = $wpdb->get_col("SELECT DISTINCT location_name FROM $table ORDER BY location_name ASC");
        $out = [['id' => '', 'name' => 'Toon alles']];
        foreach ($locations as $l) {
            $out[] = ['id' => $l, 'name' => $l];
        }
        return $out;
    }

    public function getTimeslots($filters = []) {
        return [
            ['id' => '',        'name' => 'Toon alles'],
            ['id' => 'ochtend', 'name' => 'Ochtend'],
            ['id' => 'middag',  'name' => 'Middag'],
            ['id' => 'avond',   'name' => 'Avond'],
        ];
    }

    public function getExamTypes($filters = []) {
        return [
            ['id' => '',                'name' => 'Toon alles'],
            ['id' => 'los-examen-vca-basis', 'name' => 'VCA Basis'],
            ['id' => 'los-examen-vca-vol',   'name' => 'VCA Vol'],
            ['id' => 'vca-basis-weekend',    'name' => 'VCA Basis Cursus Weekend'],
            ['id' => 'vca-vol-weekend',      'name' => 'VCA Vol Cursus Weekend'],
        ];
    }

    public function getLanguages($filters = []) {
        return [
            ['id' => 'nl', 'name' => 'Nederlands'],
            ['id' => 'en', 'name' => 'Engels'],
        ];
    }

    public function getMaterials($filters = []) {
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
}