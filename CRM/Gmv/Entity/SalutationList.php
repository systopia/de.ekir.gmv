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
class CRM_Gmv_Entity_SalutationList extends CRM_Gmv_Entity_List
{
    /**
     * Simply strip the 5 first characters
     *
     * @return array|null
     */
    public function load()
    {
        parent::load();

        // strip the first 5 characters off the value
        foreach ($this->data as $key => &$value) {
            $value = preg_replace('/^Herr/', '', $value);
            $value = preg_replace('/^Frau/', '', $value);
            $value = trim($value);
        }

        return $this->data;
    }
}
