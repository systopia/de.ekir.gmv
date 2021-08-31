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
 * Import Controller
 */
class CRM_Gmv_ImportController
{
    const BASE_FOLDER = 'GMV_Imports';

    /** @var string folder to work on */
    protected $folder = null;

    /**
     * Create a new ImportController
     *
     * @param string $folder
     *   the folder the import controller works on. leave empty to generate a new one.
     *
     * @return \CRM_Gmv_ImportController
     */
    public static function getController($folder = null)
    {
        if ($folder === null) {
            // create a new folder
            $folder = self::getBaseFolder() . DIRECTORY_SEPARATOR . 'Import_' . date('YmdHis');
            mkdir($folder);
        }

        // make sure there's only one per folder
        static $instances = [];
        if (!isset($instances[$folder])) {
            $instances[$folder] = new CRM_Gmv_ImportController($folder);
        }
        return $instances[$folder];
    }

    /**
     * Create a new Import Controller
     */
    protected function __construct($folder)
    {
        $this->folder = $folder;
        if (!file_exists($folder)) {
            throw new Exception("Cannot access folder '{$folder}'");
        }
    }

    /**
     * Get the path for the source data
     */
    public function getDataPath()
    {
        $path = $this->folder . DIRECTORY_SEPARATOR . 'data';
        if (!file_exists($path)) {
            mkdir($path);
        }
        return $path;
    }

    /**
     * Get the path for the source data
     *
     * @return string folder
     */
    public function getFolder()
    {
        $base_folder = self::getBaseFolder();
        return substr($this->folder, strlen($base_folder) + 1);
    }

    /**
     * log message
     */
    public function log($message, $level = 'info', $context = []) {
        // todo: implement log to file
        Civi::log()->log($level, $message, $context);
    }


    /**
     * Return the base folder for all import data
     *
     * @return string
     */
    public static function getBaseFolder()
    {
        $path = Civi::paths()->getPath('[civicrm.files]/gmv_imports');
        if (!file_exists($path)) {
            mkdir($path);
        }
        return $path;
    }

    /**
     * Get the full file name of an import file
     *
     * @param $file_name string
     *  local file name
     *
     * @return string
     *  full file path
     */
    public function getImportFile($file_name)
    {
        $file_path = $this->getDataPath() . DIRECTORY_SEPARATOR . $file_name;
        if (!file_exists($file_path)) {
            $this->log("File '{$file_name}' not found!", 'error');
        }
        if (!is_readable($file_path)) {
            $this->log("File '{$file_name}' cannot be read!", 'error');
        }
        return $file_path;
    }

    /**
     * Return the base folder for all import data
     *
     * @return string
     */
    public static function getFullPath($folder_name)
    {
        return self::getBaseFolder() . DIRECTORY_SEPARATOR . $folder_name;
    }


    /********************************************************************
     *                         IMPORT CODE                              *
     *******************************************************************/

    // data
    /** @var CRM_Gmv_Entity_List list of individual prefixes by ID */
    public $salutations = null;

    /** @var CRM_Gmv_Entity_List list of job_titles by ID */
    public $occupations = null;

    /** @var CRM_Gmv_Entity_List list of 'departments' by ID */
    public $departments = null;

    /** @var CRM_Gmv_Entity_AddressData list address data, not linked to contacts (yet) */
    public $address_data = null;

    /** @var CRM_Gmv_Entity_AddressData list address data, not linked to contacts (yet) */
    public $addresses = null;

    /** @var CRM_Gmv_Entity_Entity phone data, not linked to contacts (yet) */
    public $phones = null;

    /** @var CRM_Gmv_Entity_Entity email data, not linked to contacts (yet) */
    public $emails = null;

    /** @var CRM_Gmv_Entity_Entity website data, not linked to contacts (yet) */
    public $websites = null;

    /** @var CRM_Gmv_Entity_Individual contact data */
    public $individuals = null;

    /** @var CRM_Gmv_Entity_Individual contact data prepped for XCM */
    public $individuals_xcm = null;

    /**
     * Run the given import
     */
    public function run()
    {
        $this->log("Starting GMV importer on: " . $this->getFolder());
        $this->syncDataStructures();
        $this->loadLists();
        $this->loadContactDetails();
        //        $this->loadOrganisations();
        //        $this->syncOrganisations();
        $this->loadContacts();
        $this->syncContacts();
    }


    /**
     * Synchronise the data structures with the custom data helper
     */
    protected function syncDataStructures()
    {
        $this->log("Syncing XXX");
        // todo
    }

    /**
     * Load the option groups listed in the files
     */
    protected function loadLists()
    {
        $this->salutations = (new CRM_Gmv_Entity_SalutationList($this,
            $this->getImportFile('ekir_gmv/salutation.csv'),
            'id', 'designation'))->load();

        $this->occupations = (new CRM_Gmv_Entity_List($this,
            $this->getImportFile('ekir_gmv/occupation.csv'),
           'id', 'designation'))->load();

        $this->departments = (new CRM_Gmv_Entity_List($this,
            $this->getImportFile('ekir_gmv/department_designation.csv'),
            'id', 'designation'))->load();
    }

    /**
     * Load the option groups listed in the files
     */
    protected function loadContactDetails()
    {
        // addresses
        $this->address_data = (new CRM_Gmv_Entity_AddressData($this, 'Address',
            $this->getImportFile('ekir_gmv/address.csv')))->load();
        $this->addresses = (new CRM_Gmv_Entity_Address($this, 'Address',
          $this->getImportFile('ekir_gmv/addresses.csv')))->load();

        // emails
        $this->emails = (new CRM_Gmv_Entity_Email($this, 'Email',
            $this->getImportFile('ekir_gmv/email.csv')))->load();

        // phones
        $this->phones = (new CRM_Gmv_Entity_Phone($this, 'Phone',
              $this->getImportFile('ekir_gmv/phone.csv')))->load();

        // websites
        $this->websites = (new CRM_Gmv_Entity_Website($this, 'Website',
            $this->getImportFile('ekir_gmv/homepage.csv')))->load();

        $this->log("Contact detail data loaded.");
    }

    /**
     * Apply the option groups
     */
    protected function loadContacts()
    {
        $this->individuals_xcm = (new CRM_Gmv_Entity_Individual($this,
            $this->getImportFile('ekir_gmv/person.csv')))->load()->convertToXcmDataSet();

        $this->individuals = (new CRM_Gmv_Entity_Individual($this,
            $this->getImportFile('ekir_gmv/person.csv')))->load();

        $this->log("Contact data loaded.");
    }

    /**
     * Apply the option groups
     */
    protected function syncContacts()
    {
        // FIRST: run through XCM (for change notifications)
        $this->log("Starting XCM synchronisation", 'info');
        $xcm_profile = Civi::settings()->get('gmv_xcm_profile_individuals');
        foreach ($this->individuals_xcm->getAllRecords() as $record) {
            // first: find contact by gmv_id
            $existing_contact_id = $this->getGmvContactId($record['gmv_id']);
            if ($existing_contact_id) {
                $record['id'] = $existing_contact_id;
                $this->log("GMV Contact 'GMV-{$record['gmv_id']}' by ID-Tracker: {$existing_contact_id}");
            } else {
                unset($record['id']); // just to be safe
            }

            // run XCM
            $record['xcm_profile'] = $xcm_profile;
            $record['location_type_id'] = 2; // work
            try {
                $xcm_result = $this->api3('Contact', 'getorcreate', $record);
                $this->log("GMV Contact 'GMV-{$record['gmv_id']}' passed through XCM");
                $contact_id = $xcm_result['id'];
                if (!$existing_contact_id) {
                    $this->setGmvContactID($contact_id, $record['gmv_id']);
                }
            } catch (CiviCRM_API3_Exception $ex) {
                $this->log("XCM FAILED: " . $ex->getMessage(), 'error');
            }
        }

        // then run the rest
        $this->log("Starting detail synchronisation", 'info');
        foreach ($this->individuals->getAllRecords() as $record) {
            // todo: what to do?
        }

        // todo
    }

    /**
     * Get the identity tracker type fpr the GMV identity type
     */
    public function getGmvIdType() {
        return 'gmv_id';
    }

    /**
     * Get a contact_id of the contact with the given GMV-ID
     *
     * @param $gmv_id
     */
    public function getGmvContactId($gmv_id) {
        $gmv_id = 'GMV-' . $gmv_id;
        $result = $this->api3('Contact', 'findbyidentity', [
            'identifier' => $gmv_id,
            'identifier_type' => $this->getGmvIdType(),
        ]);
        if (isset($result['id'])) {
            return $result['id'];
        } else {
            return null;
        }
    }

    /**
     * Get a contact_id of the contact with the given GMV-ID
     *
     * @param $gmv_id
     */
    public function setGmvContactId($contact_id, $gmv_id) {
        $gmv_id = 'GMV-' . $gmv_id;
        $this->api3('Contact', 'addidentity', [
            'contact_id' => $contact_id,
            'identifier' => $gmv_id,
            'identifier_type' => $this->getGmvIdType(),
        ]);
    }

    /**
     * Run a CiviCRM API3 call
     *
     * @param $entity string
     * @param $action string
     * @param array $parameters
     * @throws \CiviCRM_API3_Exception API exception
     */
    public function api3($entity, $action, $parameters = [])
    {
        // anything to do here?
        return civicrm_api3($entity, $action, $parameters);
    }
}
