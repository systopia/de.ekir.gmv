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
 * Email Importer.
 */
class CRM_Gmv_Entity_Phone extends CRM_Gmv_Entity_Entity
{
    /** @var string[] raw column name to field name */
    protected $data_mapping = [
        // used
        'owner_id' => 'contact_id',
        'country_code' => '_country_code',
        'code' => '_code',
        'number' => '_number',
        'type' => 'phone_type_id',
        'classification' => 'location_type_id',
    ];

    /**
     * Used for phone type mapping
     */
    protected $phone_type_map = [
        '0' => '2', // mobile
        '1' => '1', // landline
        '2' => '1', // todo: ??
        '3' => '1', // todo: ??
    ];

    public function __construct($controller, $entity, $file)
    {
        parent::__construct($controller, 'Email', $file);
    }

    /**
     * Load the raw contact data
     *
     * @return \CRM_Gmv_Entity_Phone
     */
    public function load()
    {
        if (!$this->entity_data) {
            $this->entity_data = $this->getRawData(array_keys($this->data_mapping));
            $this->renameKeys($this->data_mapping);
            $this->indexBy('contact_id');

            // simple mapping
            $this->mapEntityValues('phone_type_id', $this->phone_type_map);
            $this->mapEntityValues('location_type_id', $this->location_type_map);

            // compile phone numbers
            foreach ($this->entity_data as &$entity_datum) {
                $country_code = preg_replace('/^00/', '', $entity_datum['_country_code']);
                $area_code = preg_replace('/^0/', '', $entity_datum['_code']);
                $number = $entity_datum['_number'];
                $entity_datum['phone'] = "+{$country_code} {$area_code} {$number}";

                // remove temp fields
                unset($entity_datum['_country_code'], $entity_datum['_code'], $entity_datum['_number']);
            }
        }

        return $this;
    }

}
