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

class CRM_Gmv_Page_ImportRunner extends CRM_Core_Page
{

    public function run()
    {
        CRM_Utils_System::setTitle("Importer");

        $folder = CRM_Utils_Request::retrieve('folder', 'String');
        $full_path = CRM_Gmv_ImportController::getFullPath($folder);
        $controller = CRM_Gmv_ImportController::getController($full_path);

        // assign some vars
        $this->assign('import_id', $folder);
        $this->assign('full_path', $full_path);
        $this->assign('environment', strstr($full_path, '/dev/') ? 'dev' : 'pro');

        // check dev environment
        parent::run();
    }

}
