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
class CRM_Gmv_Entity_List extends CRM_Gmv_Entity_Base
{
    /** @var $value_column string */
    protected $value_column;

    /** @var $label_column string */
    protected $label_column;

    /** @var $default_value string */
    protected $default_value = '';


    /**
     * @var array value => label
     */
    protected $data = null;

    public function __construct($controller, $file, $value_column, $label_column)
    {
        parent::__construct($controller, $file);
        $this->value_column = $value_column;
        $this->label_column = $label_column;
    }

    public function load()
    {
        // load the data
        if ($this->data === null) {
            $this->data = [];
            $raw_data = $this->getRawData([$this->value_column, $this->label_column]);
            foreach ($raw_data as $raw_datum)  {
                $this->data[$raw_datum[$this->value_column]] = $raw_datum[$this->label_column];
            }
            $this->log(E::ts("%1 value/label pairs loaded", [1 => count($this->data)]));
        }

        return $this;
    }

    /**
     * Use the list to map up a value
     *
     * @param $value string the value to look up in the list
     * @param $strict boolean log an error if the value is not found
     *
     * @return string the looked up label to the value
     */
    public function map($value, $strict = true)
    {
        if (isset($this->data[$value])) {
            // data found
            return $this->data[$value];
        } else {
            // data NOT found
            if ($strict && $value !== '') {
                $this->log("Label for value '{$value}' not found. Setting to '{$this->default_value}'.", 'warn');
            }
            return $this->default_value;
        }
    }

    /**
     * Get the mapping array
     *
     * @return array
     *   key -> value array
     */
    public function getMapping()
    {
        $this->load();
        return $this->data;
    }

    /**
     * Sync this list value/label to a CiviCRM option group
     *
     * @param string $group_name
     * @param string $group_title
     * @param string $missing_values w
     *   hat to do with existing values missing in new imported list. Options:
     *     'ignore'  - do nothing
     *     'disable' - mark option value as disabled
     *     'delete'  - delete this option value (caution!)
     */
    public function syncToOptionGroup($group_name, $group_title, $missing_values = 'ignore')
    {
        // first, make sure the option group exists
        $requested_values = $this->getMapping();
        $requested_value_count = count($requested_values);
        $this->log("Syncing {$requested_value_count} values with option group '{$group_title}'");
        $option_group = civicrm_api3('OptionGroup', 'get', ['name' => $group_name]);
        if (!empty($option_group['id'])) {
            $option_group_id = $option_group['id'];
        } else {
            $this->log("Option group '{$group_name}' not found, will create");
            $result = civicrm_api3('OptionGroup', 'create', [
                'name' => $group_name,
                'title' => $group_title,
                'is_active' => 1,
            ]);
            $option_group_id = $result['id'];
        }

        // now, syncing the option values
        $current_value_query = civicrm_api3('OptionValue', 'get', [
            'option_group_id' => $option_group_id,
            'option.limit'    => 0,
            'return'          => 'value,label,id'
        ]);
        foreach ($current_value_query['values'] as $option_value) {
            $value = $option_value['value'];
            if (isset($requested_values[$value])) {
                // value found. Update title if required
                if ($option_value['label'] != $requested_values[$value]) {
                    $this->log("Updating label for value '{$value}' to '{$requested_values[$value]}'.");
                    civicrm_api3('OptionValue', 'create', [
                        'id'    => $option_value['id'],
                        'label' => $requested_values[$value],
                    ]);
                }

            } else {
                // value is in the system, but not on our list
                switch ($missing_values) {
                    case 'disable':
                        civicrm_api3('OptionValue', 'create', [
                            'id'        => $option_value['id'],
                            'is_active' => 0,
                        ]);
                        $this->log("Deactivated option value '{$value}'.");
                        break;

                    case 'delete':
                        civicrm_api3('OptionValue', 'delete', [
                            'id'        => $option_value['id'],
                        ]);
                        $this->log("Deleted option value '{$value}'.");
                        break;

                    case 'ignore':
                        break;
                }
            }

            // finally, remove from list
            unset($requested_values[$value]);
        }

        // these are the left-overs, these need to be created
        foreach ($requested_values as $option_value => $option_label) {
            civicrm_api3('OptionValue', 'create', [
                'value' => $option_value,
                'label' => $option_label,
                'option_group_id' => $option_group_id,
            ]);
            $this->log("Created option value '{$option_value}'.");
        }
        $this->log("Syncing of {$requested_value_count} values with option group '{$group_title}' completed.");
    }
}
