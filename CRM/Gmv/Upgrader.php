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
 * Collection of upgrade steps.
 */
class CRM_Gmv_Upgrader extends CRM_Gmv_Upgrader_Base
{

    /**
     * Newly install this extension
     */
    public function install()
    {
        // add the new identity type (GMV-)
        CRM_Gmv_DataStructures::addIdentityTrackerType();

        // make sure the church contact type exists
        CRM_Gmv_DataStructures::syncContactTypes();

        // create custom data structures
        $customData = new CRM_Gmv_CustomData(E::LONG_NAME);
        $customData->syncOptionGroup(E::path('resources/option_group_catechism.json'));
        $customData->syncCustomGroup(E::path('resources/custom_group_ekir_organisation.json'));

        // add XCM profiles
        // TODO: ?
    }

    /**
     * Example: Run a couple simple queries.
     *
     * @return TRUE on success
     * @throws Exception
     */
    // public function upgrade_4200() {
    //   $this->ctx->log->info('Applying update 4200');
    //   CRM_Core_DAO::executeQuery('UPDATE foo SET bar = "whiz"');
    //   CRM_Core_DAO::executeQuery('DELETE FROM bang WHERE willy = wonka(2)');
    //   return TRUE;
    // }

}
