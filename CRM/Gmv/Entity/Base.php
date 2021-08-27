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
class CRM_Gmv_Entity_Base
{
    /** @var string file with the data */
    protected $file;

    /** @var CRM_Gmv_ImportController controller */
    protected $controller;

    protected $csv_separator = ',';

    public function __construct($controller, $file)
    {
        $this->controller = $controller;
        $this->file = $file;
    }

    /**
     * module id, mostly for logging
     */
    public function getID()
    {
        return basename($this->file);
    }

    /**
     * Log function
     *
     * @param $message
     * @param string $level
     */
    public function log($message, $level = 'debug')
    {
        $this->controller->log('[' . $this->getID() . '] ' . $message, $level);
    }

    /**
     * the data from the file
     *
     * @param $columns array list of column names you want from each record
     *
     * @return array raw data
     */
    public function getRawData($columns)
    {
        $fd = fopen($this->file, 'r');
        if (!$fd) {
            $this->log("Couldn't open file.", 'error');
        }

        // read headers
        $headers = fgetcsv($fd, 0, $this->csv_separator);

        // read data
        $records = [];
        while ($record = fgetcsv($fd, 0, $this->csv_separator)) {
            $labeled_record = [];
            foreach ($headers as $index => $header) {
                $labeled_record[$header] = $record[$index];
            }
            $records[] = $labeled_record;
        }
        fclose($fd);
        return $records;
    }
}
