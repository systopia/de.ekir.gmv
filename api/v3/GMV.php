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
 * Run the GMV import with the given data folder
 */
function _civicrm_api3_g_m_v_sync_spec(&$params) {
    $params['data'] = [
        'name'         => 'data',
        'api.required' => 1,
        'type'         => CRM_Utils_Type::T_STRING,
        'title'        => 'Data Folder',
        'description'  => 'GMV Data Folder to run the import with',
    ];
}

/**
 * Run the GMV import with the given data folder
 */
function civicrm_api3_g_m_v_sync($params) {
    try {
        $full_path = CRM_Gmv_ImportController::getFullPath($params['data']);
        $controller = CRM_Gmv_ImportController::getController($full_path);
        $controller->run();
    } catch (Exception $ex) {
        throw new CiviCRM_API3_Exception($ex->getMessage(), 0, $params, $ex);
    }
    return civicrm_api3_create_success();
}