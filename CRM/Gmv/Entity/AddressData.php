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


            // join street address
            foreach ($this->entity_data as &$entity_datum) {
                $entity_datum['street_address'] = trim($entity_datum['street_address'] . ' ' . $entity_datum['_housenumber']);
            }

            // remove helper column
            $this->dropEntityAttribute('_housenumber');
        }

        return $this;
    }

}
