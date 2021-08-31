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
class CRM_Gmv_Entity_Email extends CRM_Gmv_Entity_Entity
{
    /** @var string[] raw column name to field name */
    protected $data_mapping = [
        // used
        'owner_id' => 'contact_id',
        'address' => 'email',
        'principal' => 'is_primary',
        'classification' => 'location_type_id', // todo: what is this? 0 or 1
    ];


    public function __construct($controller, $entity, $file)
    {
        parent::__construct($controller, 'Email', $file);
    }

    /**
     * Load the raw contact data
     *
     * @return \CRM_Gmv_Entity_Email
     */
    public function load()
    {
        if (!$this->entity_data) {
            $this->entity_data = $this->getRawData(array_keys($this->data_mapping));
            $this->renameKeys($this->data_mapping);
            $this->indexBy('contact_id');

            // simple mapping
            $this->mapEntityValues('is_primary', $this->true_false_map);
            $this->mapEntityValues('location_type_id', $this->location_type_map);
        }

        return $this;
    }

}
