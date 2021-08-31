<?php

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
        // add the new identity type (GMV)
        $this->addIdentityTrackerType();

        // create custom data structures
        $customData = new CRM_Gmv_CustomData(E::LONG_NAME);
        // todo:
        // $customData->syncOptionGroup(E::path('resources/option_group_remote_contact_roles.json'));

        // add XCM profiles
        // TODO:
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




    // HELPER FUNCTIONS
    public function addIdentityTrackerType()
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
