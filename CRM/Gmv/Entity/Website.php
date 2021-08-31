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
 * Website Importer.
 */
class CRM_Gmv_Entity_Website extends CRM_Gmv_Entity_Entity
{
    /** @var string[] raw column name to field name */
    protected $data_mapping = [
        // used
        'owner_id' => 'contact_id',
        'url' => 'url',
        'classification' => 'location_type_id', // todo: what is this? 0 or 1
    ];


    public function __construct($controller, $entity, $file)
    {
        parent::__construct($controller, 'Website', $file);
    }

    /**
     * Load the raw contact data
     *
     * @return \CRM_Gmv_Entity_Entity
     */
    public function load()
    {
        if (!$this->entity_data) {
            $this->entity_data = $this->getRawData(array_keys($this->data_mapping));
            $this->renameKeys($this->data_mapping);
            $this->indexBy('contact_id');

            // simple mapping
            $this->mapEntityValues('location_type_id', $this->location_type_map);

            // drop empty urls
            $this->filterEntityData('url', 'not_empty');

            // make sure they all start with http
            $http_counter = 0;
            foreach ($this->entity_data as &$entity_datum) {
                if (substr($entity_datum['url'], 0, 4) != 'http') {
                    $entity_datum['url'] = 'http://' . $entity_datum['url'];
                    $http_counter++;
                }
            }
            if ($http_counter) {
                $this->log("Added 'http://' prefix to {$http_counter} URLs.", 'info');
            }
        }

        return $this;
    }

}
