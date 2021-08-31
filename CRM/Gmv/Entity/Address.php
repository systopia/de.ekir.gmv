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
class CRM_Gmv_Entity_Address extends CRM_Gmv_Entity_Entity
{
    /** @var string[] raw column name to field name */
    protected $data_mapping = [
        // used
        'id' => 'contact_address_id',
        'owner_id' => 'contact_id',
        'addition' => 'supplemental_address_1',
        'principal' => 'is_primary',
        'address_id' => 'address_data_id',
        'classification' => 'location_type_id',
    ];

    public function __construct($controller, $entity, $file)
    {
        parent::__construct($controller, 'Address', $file);
    }

    /**
     * Load the raw contact data
     *
     * @return \CRM_Gmv_Entity_Address
     */
    public function load()
    {
        if (!$this->entity_data) {
            $this->entity_data = $this->getRawData(array_keys($this->data_mapping));
            $this->renameKeys($this->data_mapping);
            $this->indexBy('contact_address_id');
            $this->indexBy('contact_id');

            // simple mapping
            $this->mapEntityValues('is_primary', $this->true_false_map);
            $this->mapEntityValues('location_type_id', $this->location_type_map);

            // add the address data
            $this->joinData($this->controller->address_data, 'address_data_id', 'address_id');
        }

        return $this;
    }

}
