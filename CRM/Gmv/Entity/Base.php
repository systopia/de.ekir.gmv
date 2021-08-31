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

    /**
     * @var string general CSV separator
     */
    protected $csv_separator = ',';

    /**
     * Map used for boolean (t/f) to int (0/1)
     * @var string[]
     */
    protected $true_false_map = [
        't' => '1',
        'f' => '0',
        ''  => '0',
    ];


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
        $this->log("Opening file '{$this->file}'...");
        $fd = fopen($this->file, 'r');
        if (!$fd) {
            $this->log("Couldn't open file.", 'error');
        }

        // read headers
        $headers = fgetcsv($fd, 0, $this->csv_separator);

        // extract indices to import
        $indices = [];
        foreach ($headers as $index => $header) {
            if (in_array($header, $columns)) {
                $indices[$index] = $header;
            }
        }

        // read data
        $records = [];
        while ($record = fgetcsv($fd, 0, $this->csv_separator)) {
            $labeled_record = [];
            foreach ($indices as $index => $header) {
                $labeled_record[$header] = $record[$index];
            }
            $records[] = $labeled_record;
        }
        fclose($fd);
        return $records;
    }

    /**
     * Run a CiviCRM API3 call
     *
     * @param $entity string
     * @param $action string
     * @param array $parameters
     */
    public function api3($entity, $action, $parameters = [])
    {
        try {
            return $this->controller->api3($entity, $action, $parameters);
        } catch (CiviCRM_API3_Exception $ex) {
            $this->log("Error calling APIv3 ({$entity}.{$action}): " . $ex->getMessage());
            throw $ex;
        }
    }
}
