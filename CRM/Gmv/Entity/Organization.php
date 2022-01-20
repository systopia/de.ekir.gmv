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
    /** string identifier marking EKIR itself */
    const EKIR_SELF_ID = 'A1';

    /** string pattern marking Kirchenkreis */
    const EKIR_KK_PATTERN = '/^15\d{4}?$/';

    /** string pattern marking Kirchengemeinde */
    const EKIR_KG_PATTERN = '/^15\d{6}?$/';

    /** string pattern marking Pfarrstelle */
    const EKIR_PS_PATTERN = '/^15\d{8}?$/';

    /** @var string[] raw column name to field name */
    protected $data_mapping = [
        // used
        'id' => 'gmv_id',
        'historical' => '_historic',
        'parent_id' => 'gmv_data.gmv_data_master_id',
        'designation' => 'organization_name',
        'additions' => 'gmv_data.gmv_addition',
        'disbanded' => 'gmv_data.gmv_data_disbanded',
        'established' => 'gmv_data.gmv_data_established',
        'catechism' => 'gmv_data.gmv_data_catechism',
        'government_district' => 'gmv_data.gmv_data_government_district',
        'religious_community' => 'gmv_data.gmv_data_religious_community',
        'identifier' => 'gmv_data.gmv_data_identifier',
//        'identifier' => 'external_identifier',
        'members' => 'gmv_data.gmv_member_count',
    ];

    /** @var array list of organisations imported, identified by MDE */
    protected $imported_ids = [];

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

            // filter for relevant types, see here: https://projekte.systopia.de/issues/16247#note-16
            $purged_organisation_count = 0;
            foreach (array_keys($this->entity_data) as $entity_key) {
                $identifier = $this->entity_data[$entity_key]['gmv_data.gmv_data_identifier'];
                if (    !preg_match(self::EKIR_KG_PATTERN, $identifier)
                    // disabled Pfarrstellen: &&  !preg_match(self::EKIR_PS_PATTERN, $identifier)
                    &&  !preg_match(self::EKIR_KK_PATTERN, $identifier)
                    &&  $identifier != self::EKIR_SELF_ID) {
                    unset($this->entity_data[$entity_key]);
                    $purged_organisation_count++;
                } else {
                    $gmv_id = $this->entity_data[$entity_key]['gmv_id'];
                    $this->imported_ids[$gmv_id] = true;
                }
            }
            $this->log("{$purged_organisation_count} organisations purged.");

            // custom adjustments
            foreach ($this->entity_data as &$entity_datum) {
                // truncate 'organization_name' to 128
                if (strlen($entity_datum['organization_name']) > 128) {
                    $this->log("Organisation name {$entity_datum['organization_name']} too long, will be truncated to 128 characters", 'warning');
                    $entity_datum['organization_name'] = substr($entity_datum['organization_name'], 0, 128);
                }

                // derive contact_type and subtype
                $entity_datum['contact_type'] = 'Organization';
                // this is *any* other entry:
                if (preg_match(self::EKIR_PS_PATTERN, $entity_datum['gmv_data.gmv_data_identifier'])) {
                    $entity_datum['contact_sub_type'] = 'Pfarrstelle';
                } else if (preg_match(self::EKIR_KG_PATTERN, $entity_datum['gmv_data.gmv_data_identifier'])) {
                    $entity_datum['contact_sub_type'] = 'Kirchengemeinde';
                } else if (preg_match(self::EKIR_KK_PATTERN, $entity_datum['gmv_data.gmv_data_identifier'])) {
                    $entity_datum['contact_sub_type'] = 'Kirchenkreis';
                } else if ($entity_datum['gmv_data.gmv_data_identifier'] == self::EKIR_SELF_ID) {
                    $entity_datum['gmv_data.gmv_data_master_id'] = '';
                    $entity_datum['contact_sub_type'] = '';
                } else {
                    $this->log("Unexpected gmv_data_identifier: {$entity_datum['gmv_data.gmv_data_identifier']}", 'warn');
                }
            }
        }

        // only keep 'historic=t' values
        $this->filterEntityData('_historic', 'equals', 'f');
        $this->dropEntityAttribute('_historic');

        return $this;
    }
}
