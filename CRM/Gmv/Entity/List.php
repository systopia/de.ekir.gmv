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
     * value => label
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
}
