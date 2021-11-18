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
 * Employment Relationship importer
 *
 */
class CRM_Gmv_Entity_Employment extends CRM_Gmv_Entity_Entity
{
    /** @var string[] raw column name to field name */
    protected $data_mapping = [
        // used
        'since' => 'start_date',
        'end' => 'end_date',
        'employee_id' => 'contact_id_a',
        'employer_id' => 'contact_id_b',
        'occupation_id' => 'gmv_employee.gmv_employee_job',
        'designation' => 'gmv_employee.gmv_employee_designation',
        'end_reason' => 'gmv_employee.gmv_employee_end_reason',
        'historical' => 'historical',
    ];

    public function __construct($controller, $file)
    {
        parent::__construct($controller, 'Relationship', $file);
    }

    /**
     * Load the raw contact data
     *
     * @return \CRM_Gmv_Entity_Employment
     */
    public function load()
    {
        if (!$this->entity_data) {
            $this->entity_data = $this->getRawData(array_keys($this->data_mapping));
            $this->renameKeys($this->data_mapping);
            $this->indexBy('contact_id_a');
            $this->indexBy('contact_id_b');

            // restrict to current data
            $this->filterEntityData('historical', 'equals', 'f');
            $this->dropEntityAttribute('historical');

            // filter down to actually existing organisations
            $this->joinData($this->controller->organisations, 'contact_id_b', 'gmv_id', ['gmv_id']);
            $this->filterEntityData('gmv_id', 'not_empty');
            $this->dropEntityAttribute('gmv_id');

            // simple mapping
            $this->mapEntityValues('gmv_employee.gmv_employee_job', $this->controller->occupations->getMapping());
        }

        return $this;
    }

}
