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
class CRM_Gmv_Entity_Individual extends CRM_Gmv_Entity_Contact
{
    /** @var string[] raw column name to field name */
    protected $data_mapping = [
        // used
        'id' => 'gmv_id',
        'firstname' => 'first_name',
        'lastname' => 'last_name',
        'birthdate' => 'birth_date',
        'gender' => 'gender_id',
        'department_designation_id' => 'job_title',
        'salutation_id' => 'formal_title',
        // todo: more of the following:
//        'function' => 'todo_function',
//        'status' => 'todo_contact_status',
//        'ordination_status' => 'todo_ordination_status',
//        'martial_status' => 'todo_martial_status',
//        'historical' => 'todo_historical',
//        'visible_in_search' => 'todo_visible_in_search',
//        'department_end' => 'todo_department_end',
//        'department_end_reason' => 'todo_department_end_reason',
//        'department_since' => 'todo_department_since',
    ];

    protected $gender_map = [  // todo: live lookup needed?
        '0' => '2', // male
        '1' => '1', // female
    ];

    protected $prefix_map = [  // todo: live lookup needed?
        '0' => '2', // Herr
        '1' => '1', // Frau
    ];

    public function __construct($controller, $file)
    {
        parent::__construct($controller, 'Individual', $file);
    }

    /**
     * Load the raw contact data
     *
     * @return CRM_Gmv_Entity_Individual
     */
    public function load()
    {
        if (!$this->entity_data) {
            $this->entity_data = $this->getRawData(array_keys($this->data_mapping));
            $this->renameKeys($this->data_mapping);
            $this->setEntityValue('contact_type', 'Individual');
            $this->copyEntityValue('gender_id', 'individual_prefix');

            // do some lookups
            $this->mapEntityListValues('job_title', $this->controller->occupations);
            $this->mapEntityListValues('formal_title', $this->controller->salutations);
            $this->mapEntityValues('gender_id', $this->gender_map);
            $this->mapEntityValues('individual_prefix', $this->prefix_map);

            // add main address (for xcm)
            $this->joinData($this->controller->addresses, 'id', 'address_data_link');

            // add main email (for xcm)

        }

        return $this;
    }
}
