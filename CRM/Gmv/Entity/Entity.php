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

    /** @var array adds an indexed access layer to the entity_data */
    protected $indexed_entity_data = null;

    public function __construct($controller, $entity, $file)
    {
        parent::__construct($controller, $file);
        $this->entity = $entity;
    }

    /**
     * Map used for boolean (t/f) to int (0/1)
     * @var string[]
     */
    protected $location_type_map = [
        '0' => '2', // work / dienstlich
        '1' => '1', // home / privat
    ];

    /**
     * Get an array with all records
     *
     * @return array
     */
    public function getAllRecords()
    {
        return $this->entity_data;
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
    public function setEntityValues($key, $value)
    {
        foreach ($this->entity_data as &$entity) {
            $entity[$key] = $value;
        }
    }

    /**
     * Set the following property to the given key for a entity with the given key
     *
     * @param string $entity_key_name  the key field name, e.g. 'id'
     * @param string $entity_key       the value to identify the entity
     * @param string $field            the field name to set in the entity
     * @param string $value            the value to set it to
     */
    public function setEntityValue($entity_key_name, $entity_key, $field, $value)
    {
        // make sure it's indexed
        $this->indexBy($entity_key_name);

        // find the entity
        if (isset($this->indexed_entity_data[$entity_key_name][$entity_key])) {
            // it exists!
            $this->indexed_entity_data[$entity_key_name][$entity_key][$field] = $value;
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

    /**
     * Add an indexed layer with the given key
     *
     * @param $key_name
     */
    public function indexBy($key_name)
    {
        if (!isset($this->indexed_entity_data[$key_name])) {
            $this->indexed_entity_data[$key_name] = [];
            foreach ($this->entity_data as &$entity_datum) {
                if (isset($entity_datum[$key_name])) {
                    $this->indexed_entity_data[$key_name][$entity_datum[$key_name]] = &$entity_datum;
                }
            }
        }
    }

    /**
     * Get a record from the set by key
     *
     * @param string $key_value
     * @param string $key_field
     *
     * @return mixed|null
     */
    public function getDataRecord($key_value, $key_field = 'id')
    {
        return $this->indexed_entity_data[$key_field][$key_value] ?? null;
    }

    /**
     * Remove the given attribute from all entities
     *
     * @param string $attribute_name
     */
    public function dropEntityAttribute($attribute_name)
    {
        foreach ($this->entity_data as &$entity) {
            unset($entity[$attribute_name]);
        }
    }

    /**
     * Join additional data from another entity.
     *
     * @param $other_data CRM_Gmv_Entity_Entity the other entity data
     * @param $my_key     string  the field name of my data to join on
     * @param $other_key  string  the field name of the other data to join on
     * @param $fields     array   list of field names to add. Default: all
     */
    public function joinData($other_data, $my_key, $other_key = 'id', $fields = null) {
        $other_data->indexBy($other_key);
        foreach ($this->entity_data as &$entity) {
            if (!empty($entity[$my_key])) {
                $key_value = $entity[$my_key];
                $record = $other_data->getDataRecord($key_value, $other_key);
                if ($record) {
                    if ($fields) {
                        // set (override) the given fields
                        foreach ($fields as $field) {
                            $entity[$field] = CRM_Utils_Array::value($field, $record);
                        }
                    } else {
                        // add given data (only if not already present)
                        foreach ($record as $field => $value) {
                            if (!array_key_exists($field, $entity)) {
                                $entity[$field] = $value;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Delete the given entry via the main key
     *  (i.e. the key used in $this->entity_data
     *
     * @param $main_key
     */
    public function deleteEntry($main_key)
    {
        // delete from main set
        $data = $this->entity_data[$main_key];
        unset($this->entity_data[$main_key]);

        // delete from indexed subsets
        foreach ($this->indexed_entity_data as $key => &$entities) {
            unset($this->indexed_entity_data[$key][$data[$key]]);
        }
    }

    /**
     * Restrict the entity data set by a list of criteria
     *
     * @param string $field
     *  field name
     * @param string $type
     *  filter type
     *
     * Caution:
     */
    public function filterEntityData($field, $type, $value = null)
    {
        $keys_to_delete = [];
        switch ($type) {
            case 'not_empty':
                foreach ($this->entity_data as $main_key => &$entity_datum) {
                    if (empty($entity_datum[$field])) {
                        $keys_to_delete[] = $main_key;
                    }
                }
                break;

            default:
                $this->log("Filter type '{$type}' not defined!", 'error');
                break;
        }

        // delete the fields
        foreach ($keys_to_delete as $main_key_to_delete) {
            $this->deleteEntry($main_key_to_delete);
        }
    }
}
