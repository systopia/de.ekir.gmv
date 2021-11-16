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
 * Some data structures
 */
class CRM_Gmv_DataStructures
{
    const KIRCHENKREIS    = 'Kirchenkreis';
    const KIRCHENGEMEINDE = 'Kirchengemeinde';
    const PFARRSTELLE     = 'Pfarrstelle';

    /**
     * Make sure the three contact types exist
     */
    public static function syncContactTypes() {
        foreach ([self::KIRCHENKREIS, self::KIRCHENGEMEINDE, self::PFARRSTELLE] as $contact_type) {
            $types = civicrm_api3('ContactType', 'get', [
                'name' => $contact_type,
            ]);
            if ($types['count'] > 1) {
                Civi::log()->error(E::ts("Multiple matching {$contact_type} contact types found!"));
            }
            if ($types['count'] == 0) {
                // create it
                $new_type = civicrm_api3('ContactType', 'create', [
                    'name' => $contact_type,
                    'label' => $contact_type,
                    'image_URL' => E::url('icons/church_contact_type.png'),
                    'parent_id' => 3, // 'Organisation'
                ]);
            }
        }
    }

    /**
     * Make sure the identity tracker type exists
     */
    public static function addIdentityTrackerType()
    {
        // also: add the 'Remote Contact' type to the identity tracker
        $exists_count = civicrm_api3(
            'OptionValue',
            'getcount',
            [
                'option_group_id' => 'contact_id_history_type',
                'value' => 'gmv_id',
            ]
        );
        switch ($exists_count) {
            case 0:
                // not there -> create
                civicrm_api3(
                    'OptionValue',
                    'create',
                    [
                        'option_group_id' => 'contact_id_history_type',
                        'value' => 'gmv_id',
                        'is_reserved' => 1,
                        'description' => "ID wie sie vom GMV verwendet wird",
                        'name' => 'gmv_id',
                        'label' => E::ts("GMV ID"),
                    ]
                );
                break;

            case 1:
                // does exist, nothing to do here
                break;

            default:
                // more than one exists: that's not good!
                CRM_Core_Session::setStatus(
                    "Es gibt bereits mehrere ID Typen mit type 'gmv_id', das dürfte Probleme bereiten.",
                    E::ts("Warning"),
                    'warn'
                );
                break;
        }
    }
}
