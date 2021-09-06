<?php
/*-------------------------------------------------------+
| EKIR GMV / ORG DB Synchronisation                      |
| Copyright (C) 2021 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

use CRM_Gmv_ExtensionUtil as E;

/**
 * Address Importer.
 *
 *  This is based on TWO files:
 *   - addresses.csv: link to contact and additional data
 *   - address.csv:   bare address data
 */
class CRM_Gmv_Entity_AddressData extends CRM_Gmv_Entity_Entity
{
    /** @var string[] raw column name to field name */
    protected $data_mapping = [
        // used
        'id' => 'address_id',
        'city' => 'city',
        'country' => 'country_id',
        'street' => 'street_address',
        'zip' => 'postal_code',
        'housenumber' => '_housenumber',
        'latitude' => 'geo_code_1',
        'longitude' => 'geo_code_2',
        'addition' => 'supplemental_address_1',
    ];

    public $country_correction = [
        'AUT' => 'Österreich',
        'BEL' => 'Belgien',
        'CH' => 'Schweiz',
        'CHE' => 'Schweiz',
        'CZE' => 'Tschechien',
        'Deutschlan' => 'Deutschland',
        'DNK' => 'Dänemark',
        'ESP' => 'Spanien',
        'EST' => 'Estland',
        'FRA' => 'Frankreich',
        'GR' => 'Griechenland',
        'GUATEMALA' => 'Guatemala',
        'IDN' => 'Indien',
        'IRN' => 'Iran, Islamische Republik',
        'ISR' => 'Israel',
        'LUX' => 'Luxemburg',
        'NIEDERLANDE' => 'Niederlande',
        'NLD' => 'Niederlande',
        'NOR' => 'Norwegen',
        'SCHWEIZ' => 'Schweiz',
        'SPANIEN' => 'Spanien',
        'SUI' => 'Schweiz',
        'SWE' => 'Schweden',
        'USA' => 'Vereinigte Staaten',
    ];

    public function __construct($controller, $entity, $file)
    {
        parent::__construct($controller, 'Address', $file);
    }

    /**
     * Load the raw contact data
     *
     * @return \CRM_Gmv_Entity_AddressData
     */
    public function load()
    {
        if (!$this->entity_data) {
            $this->entity_data = $this->getRawData(array_keys($this->data_mapping));
            $this->renameKeys($this->data_mapping);
            $this->indexBy('address_id');

            // look up country
            $country_name_to_id = $this->getCountryMap();

            // join street address and set country
            foreach ($this->entity_data as &$entity_datum) {
                $entity_datum['street_address'] = trim($entity_datum['street_address'] . ' ' . $entity_datum['_housenumber']);

                // truncate the geocodes (CiviCRM restriction)
                $entity_datum['geo_code_1'] = substr($entity_datum['geo_code_1'], 0, 14);
                $entity_datum['geo_code_2'] = substr($entity_datum['geo_code_2'], 0, 14);

                // DEAL WITH THE COUNTRY MESS
                if (empty($entity_datum['country_id'])) {
                    // assume country is Germany
                    $entity_datum['country_id'] = 'Deutschland';
                }

                // apply the known corrections
                $entity_datum['country_id'] = $this->country_correction[$entity_datum['country_id']] ?? $entity_datum['country_id'];

                // map to CiviCRM ID
                if (!isset($country_name_to_id[$entity_datum['country_id']])) {
                    $this->log("Cannot map country '{$entity_datum['country_id']}'", 'warning');
                    $entity_datum['country_id'] = '';
                } else {
                    $entity_datum['country_id'] = $country_name_to_id[$entity_datum['country_id']];
                }
            }

            // remove helper column
            $this->dropEntityAttribute('_housenumber');
        }

        return $this;
    }

    /**
     * Get a country name => country_id mapping
     */
    protected function getCountryMap()
    {
        static $country_map = null;
        if ($country_map === null) {
            $country_map = ['' => ''];
            $country_data = civicrm_api3('Country', 'get', [
                'option.limit' => 0,
                'return' => ['id', 'name']
            ]);
            foreach ($country_data['values'] as $country) {
                $country_name = ts($country['name'], ['context' => 'country']);
                $country_map[$country_name] = $country['id'];
            }
        }
        return $country_map;
    }
}
