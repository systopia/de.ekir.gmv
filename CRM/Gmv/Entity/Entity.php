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
 * Entity Importer base
 */
class CRM_Gmv_Entity_Entity extends CRM_Gmv_Entity_Base
{
    /** @var string the name of the entity (in APIv3) */
    protected $entity;

    /** @var array entity_data */
    protected $entity_data = null;

    public function __construct($controller, $entity, $file)
    {
        parent::__construct($controller, $file);
        $this->entity = $entity;
    }

    /**
     * Rename the keys in the entity data
     *
     * @param $key_mapping array old -> new key mapping
     * @param boolean $delete_old_keys remove the old key
     */
    public function renameKeys($key_mapping, $delete_old_keys = true)
    {
        foreach ($this->entity_data as &$entity) {
            foreach ($key_mapping as $old_key => $new_key) {
                $entity[$new_key] = CRM_Utils_Array::value($old_key, $entity);
                if ($delete_old_keys and ($old_key != $new_key)) {
                    unset($entity[$old_key]);
                }
            }
        }
    }

    /**
     * Apply a mapping to the entity data
     *
     * @param $property string property name
     * @param $mapping CRM_Gmv_Entity_List lookup list
     * @param string $lookup_failed_value value to use if lookup failed
     */
    public function mapEntityListValues($property, $mapping, $failed_lookup_value = '')
    {
        foreach ($this->entity_data as &$entity) {
            if (isset($entity[$property])) {
                // override in place
                $entity[$property] = $mapping->map($entity[$property]);
            } else {
                $this->log("Mapping failed, property '{$property}' missing.", 'warn');
                $entity[$property] = $failed_lookup_value;
            }
        }
    }

    /**
     * Set the following property to the given key for all entities
     *
     * @param string $key
     * @param string $value
     */
    public function setEntityValue($key, $value)
    {
        foreach ($this->entity_data as &$entity) {
            $entity[$key] = $value;
        }
    }

    /**
     * Copy the current values from one key to another
     *
     * @param string $key
     * @param string $new_key
     */
    public function copyEntityValue($key, $new_key)
    {
        foreach ($this->entity_data as &$entity) {
            $entity[$new_key] = CRM_Utils_Array::value($key, $entity);
        }
    }


    /**
     * Apply a mapping to the entity data
     *
     * @param $property string property name
     * @param $mapping array lookup list
     * @param string $lookup_failed_value value to use if lookup failed
     */
    public function mapEntityValues($property, $mapping, $failed_lookup_value = '')
    {
        foreach ($this->entity_data as &$entity) {
            if (isset($entity[$property])) {
                // override in place
                $entity[$property] = CRM_Utils_Array::value($entity[$property], $mapping, $failed_lookup_value);
            } else {
                $this->log("Mapping failed, property '{$property}' missing.", 'warn');
                $entity[$property] = $failed_lookup_value;
            }
        }
    }
}
