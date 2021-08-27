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
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Gmv_Form_GmvZipUpload extends CRM_Core_Form
{
    public function buildQuickForm()
    {
        $this->setTitle("GMV ZIP Importer");

        $this->add(
            'file',
            'gmv_zip',
            ts('Import Data File'),
            'size=30 maxlength=255',
            TRUE
        );

        $max_size = CRM_Utils_Number::formatUnitSize(1024 * 1024 * 8, true);
        $this->setMaxFileSize($max_size);

        $this->addButtons([
              [
                  'type' => 'submit',
                  'name' => E::ts('Upload and Import'),
                  'isDefault' => true,
              ],
          ]);

        parent::buildQuickForm();
    }

    public function postProcess()
    {
        $zip_file_data = $this->_submitFiles['gmv_zip'];
        if (!empty($zip_file_data['error']) || empty($zip_file_data['tmp_name'])) {
            CRM_Core_Session::setStatus("Eventuell einmal die PHP file upload Einstellungen prüfen.", "Upload fehlgeschlagen", 'error');
        } else {
            // get new folder from controller
            $controller = CRM_Gmv_ImportController::getController();
            $data_path = $controller->getDataPath();

            // unzip files
            chdir($data_path);
            $zip = new ZipArchive();
            if ($zip->open($zip_file_data['tmp_name']) === TRUE) {
                $zip->extractTo($data_path);
                $zip->close();
            } else {
                throw new Exception("Couldn't open zip archive " . $zip_file_data['tmp_name']);
            }

            // redirect to the runner page
            CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/gmv/import', 'folder=' . $controller->getFolder()));
        }
        parent::postProcess();
    }

}
