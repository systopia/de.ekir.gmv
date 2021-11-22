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

        // make sure the church contact type exists
        CRM_Gmv_DataStructures::getEmploymentRelationshipType();

        // create custom data structures
        $customData = new CRM_Gmv_CustomData(E::LONG_NAME);
        $customData->syncOptionGroup(E::path('resources/option_group_gmv_employee_job.json'));
        $customData->syncCustomGroup(E::path('resources/custom_group_ekir_organisation.json'));
        $customData->syncCustomGroup(E::path('resources/custom_group_ekir_employment.json'));

        // add XCM profiles
        $this->installXcmProfile(
            CRM_Gmv_ImportController::XCM_PROFILE_INDIVIDUALS,
            json_decode(file_get_contents(E::path('resources/xcm_individuals.json')), true)
        );
    }

    /**
     * Installs an XCM profile, if it does not exist.
     *
     * @param $name
     *   The XCM profile name.
     * @param $raw_json_data
     *   The XCM profile data in JSON format.
     */
    protected function installXcmProfile($name, $raw_json_data)
    {
        $profile_list = CRM_Xcm_Configuration::getProfileList();
        if (!isset($profile_list[$name])) {
            // not here? create!
            $profile_data = Civi::settings()->get('xcm_config_profiles');
            $profile_data[$name] = $raw_json_data;
            Civi::settings()->set(CRM_Gmv_ImportController::XCM_PROFILE_INDIVIDUALS, $name);
        }
    }

}
