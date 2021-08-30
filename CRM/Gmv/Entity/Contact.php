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
class CRM_Gmv_Entity_Contact extends CRM_Gmv_Entity_Entity
{
    public function __construct($controller, $entity, $file)
    {
        parent::__construct($controller, $entity, $file);
    }

    /**
     * Synchronise the given contacts
     */
    public function syncContacts($xcm_profile_name)
    {
        foreach ($this->entity_data as $contact_record) {
            $gmv_id = $this->getGmvID($contact_record);
            $contact_id = $this->findGmvContact($gmv_id);
            if ($contact_id) {
                // contact already imported as GMV contact
                $contact_record['id'] = $contact_id;
                $new_contact_id = $this->runXCM($contact_record);
            } else {
                // contact not yet a GMV contact
                unset($contact_record['id']);
                $new_contact_id = $this->runXCM($contact_record);
                $this->markGmvContact($new_contact_id, $gmv_id);
            }
        }
    }

    /**
     * Get the GMV ID
     * @param $contact_data array
     *   contact data
     */
    public function getGmvID($contact_data)
    {
        return 'GMV-' . $contact_data['gmv_id'];
    }

    /**
     * Get the GMV ID
     * @param $contact_data array
     *   contact data
     */
    public function findGmvContact($gmv_id)
    {
        $result = $controller->api3('Contact', 'findbyidentity', [
            'identifier' => $gmv_id,
            'identifier_type' => $this->controller->getGmvIdType(),
        ]);
        return $result['id'];
    }

    /**
     * Add the GMV ID to the given contact ID
     *
     * @param $contact_id
     * @param $gmv_id
     */
    public function markGmvContact($contact_id, $gmv_id)
    {
        $controller->api3('Contact', 'addidentity', [
            'contact_id' => $contact_id,
            'identifier' => $gmv_id,
            'identifier_type' => $this->controller->getGmvIdType(),
        ]);
    }

    /**
     * @param $contact_record
     */
    public function runXCM($contact_record)
    {
        $result = $controller->api3('Contact', 'getorcreate', $contact_record);
        return $result['id'];
    }
}
