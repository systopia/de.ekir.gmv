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
            'File',
            'gmv_zip',
            "GMV ZIP",
            'size=30 maxlength=255 accept=application/zip',
            TRUE
        );
        $this->setMaxFileSize('8m');

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
        parent::postProcess();
    }

}
