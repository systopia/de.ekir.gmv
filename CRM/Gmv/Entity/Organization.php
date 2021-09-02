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
    /** @var string identifier marking EKIR itself */
    protected $EKIR_SELF_ID = 'A1';

    /** @var string[] raw column name to field name */
    protected $data_mapping = [
        // used
        'id' => 'gmv_id',
        'historical' => '_historic',
        'parent_id' => 'gmv_data.gmv_data_master_id',
        'designation' => 'organization_name',
        'additions' => 'note',
        'disbanded' => 'gmv_data.gmv_data_disbanded',
        'established' => 'gmv_data.gmv_data_established',
        'catechism' => 'gmv_data.gmv_data_catechism',
        'government_district' => 'gmv_data.gmv_data_government_district',
        'religious_community' => 'gmv_data.gmv_data_religious_community',
        'identifier' => 'gmv_data.gmv_data_identifier',
//        'identifier' => 'external_identifier',
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

            // custom adjustments
            foreach ($this->entity_data as &$entity_datum) {
                // truncate 'organization_name' to 128
                if (strlen($entity_datum['organization_name']) > 128) {
                    $this->log("Organisation name {$entity_datum['organization_name']} too long, will be truncated to 128 characters", 'warning');
                    $entity_datum['organization_name'] = substr($entity_datum['organization_name'], 0, 128);
                }

                // derive contact_type and subtype
                $entity_datum['contact_type'] = 'Organization';
                if ($entity_datum['gmv_data.gmv_data_identifier'] == $this->EKIR_SELF_ID) {
                    // this is EKIR itself, there's some special stuff
                    $entity_datum['gmv_data.gmv_data_master_id'] = '';
                    $entity_datum['contact_sub_type'] = '';
                } else {
                    // this is *any* other entry:
                    // todo: is there a better way?
                    switch (strlen($entity_datum['gmv_data.gmv_data_identifier'])) {
                        case 6:
                            $entity_datum['contact_sub_type'] = 'Kirchenkreis';
                            break;
                        case 8:
                            $entity_datum['contact_sub_type'] = 'Kirchengemeinde';
                            break;
                        case 10:
                        default:
                            $entity_datum['contact_sub_type'] = 'Kirchenstelle';
                            break;
//                        default:
//                            $this->log("ID '{$entity_datum['external_identifier']}' is ill-formatted.");
//                            $entity_datum['contact_sub_type'] = '';
//                            break;
                        }
                }
            }
        }

        // only keep 'historic=t' values
        $this->filterEntityData('_historic', 'equals', 'f');
        $this->dropEntityAttribute('_historic');

        return $this;
    }
}
