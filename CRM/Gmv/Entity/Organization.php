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
class CRM_Gmv_Entity_Organization extends CRM_Gmv_Entity_Contact
{
    /** @var string[] raw column name to field name */
    protected $data_mapping = [
        // used
        'id' => 'gmv_id',
        'parent_id' => 'gmv_data.gmv_data_master_id',
        'designation' => 'organization_name',
        'additions' => 'note',
        'disbanded' => 'gmv_data.gmv_data_disbanded',
        'established' => 'gmv_data.gmv_data_established',
        'catechism' => 'gmv_data.gmv_data_catechism',
        'government_district' => 'gmv_data.gmv_data_government_district',
        'religious_community' => 'gmv_data.gmv_data_religious_community',
        //'identifier' => 'gmv_data.gmv_data_identifier',
        'identifier' => 'external_identifier',
        'members' => 'gmv_data.gmv_member_count',
    ];


    public function __construct($controller, $file)
    {
        parent::__construct($controller, 'Organization', $file);
    }

    /**
     * Load the raw contact data
     *
     * @return CRM_Gmv_Entity_Organization
     */
    public function load()
    {
        if (!$this->entity_data) {
            $this->entity_data = $this->getRawData(array_keys($this->data_mapping));
            $this->renameKeys($this->data_mapping);
            $this->setEntityValues('contact_type', 'Individual');
            $this->copyEntityValue('gender_id', 'individual_prefix');

            // do some lookups
            $this->mapEntityListValues('job_title', $this->controller->occupations);
            $this->mapEntityListValues('formal_title', $this->controller->salutations);
            $this->mapEntityValues('gender_id', $this->gender_map);
            $this->mapEntityValues('individual_prefix', $this->prefix_map);
        }

        return $this;
    }

    /**
     * "Mangle" the data to be an xcm data set
     */
    public function convertToXcmDataSet()
    {
        // add main address (for xcm)
        $this->joinData($this->controller->addresses, 'gmv_id', 'contact_id');
        $this->dropEntityAttribute('contact_address_id');

        // add main email (for xcm)
        $this->joinData($this->controller->emails, 'gmv_id', 'contact_id');
        $this->dropEntityAttribute('address_id');

        // iterate phones and add up to two to the contact
        $this->indexBy('gmv_id');
        foreach ($this->controller->phones->entity_data as $phone) {
            $contact_copy = $this->getDataRecord($phone['contact_id'], 'gmv_id');
            if ($contact_copy) {
                if (!isset($contact_copy['phone'])) {
                    $this->setEntityValue('gmv_id', $contact_copy['gmv_id'], 'phone', $phone['phone']);
                } else if (!isset($contact_copy['phone2'])) {
                    $this->setEntityValue('gmv_id', $contact_copy['gmv_id'], 'phone2', $phone['phone']);
                }
            }
        }

        return $this;
    }
}
