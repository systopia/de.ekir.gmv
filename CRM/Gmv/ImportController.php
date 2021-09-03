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

    /** @var array record all changes (except for newly created contacts) */
    protected $recorded_changes = [];

    /** @var array caches gmz values */
    protected $gmv_id_cache = [];

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
        $this->fillGmvIdCache();
        $this->log("Starting GMV importer on: " . $this->getFolder());
        $this->syncDataStructures();
        $this->loadLists();
        $this->loadContactDetails();
//        $this->loadOrganisations();
//        $this->syncOrganisations();
//        $this->loadContacts();
//        $this->syncContacts();
        $this->syncEmails();
//        $this->syncPhones();
//        $this->syncAddresses();
        $this->generateChangeActivities();
    }


    /*****************************************************************
     *                 HELPER / INFRASTRUCTURE                      **
     *****************************************************************/

    /**
     * Record changes so we can later generate the change activities
     *
     * @param $contact_id integer
     * @param $attribute string
     * @param $old_value string
     * @param $new_value string
     */
    protected function recordChange($contact_id, $attribute, $old_value, $new_value)
    {
        // some exceptions:
        if ($new_value === '0' && $old_value === null) {
            return;
        }
        $this->recorded_changes[$contact_id][$attribute] = [$old_value, $new_value];
    }

    /**
     * Will update the given contact data
     * @param $contact_id integer already identified contact id
     * @param $contact_data array contact data to be added
     */
    public function updateContact($contact_id, $contact_data)
    {
        unset($contact_data['gmv_id']);
        $current_contact_data = $this->api3('Contact', 'getsingle', ['id' => $contact_id]);
        $contact_update = [];
        foreach ($contact_data as $field_name => $requested_value) {
            $current_value = CRM_Utils_Array::value($field_name, $current_contact_data);
            if ($current_value != $requested_value) {
                $this->recordChange($contact_id, $field_name, $current_value, $requested_value);
                $contact_update[$field_name] = $requested_value;
            }
        }

        if (!empty($contact_update)) {
            $contact_update['id'] = $contact_id;
            $this->api3('Contact', 'create', $contact_update);
        }
    }

    /**
     * Get the identity tracker type fpr the GMV identity type
     */
    public function getGmvIdType() {
        return 'gmv_id';
    }

    /** will fill the gmv id cache */
    public function fillGmvIdCache() {
        $query = CRM_Core_DAO::executeQuery("
            SELECT 
             entity_id  AS contact_id, 
             identifier AS gmv_id
            FROM civicrm_value_contact_id_history 
            WHERE identifier_type='gmv_id';
        ");
        while ($query->fetch()) {
            $gmv_id = substr($query->gmv_id, 4);
            $this->gmv_id_cache[$gmv_id] = $query->contact_id;
        }
        $cache_size = count($this->gmv_id_cache);
        $this->log("Filled GMV-ID cache with {$cache_size} entries.");
    }

    /**
     * Get a contact_id of the contact with the given GMV-ID
     *
     * @param $gmv_id
     */
    public function getGmvContactId($gmv_id) {
        if (isset($this->gmv_id_cache[$gmv_id])) {
            return $this->gmv_id_cache[$gmv_id];
        }

        $gmv_id = 'GMV-' . $gmv_id;
        $result = $this->api3('Contact', 'findbyidentity', [
            'identifier' => $gmv_id,
            'identifier_type' => $this->getGmvIdType(),
        ]);
        if (isset($result['id'])) {
            $this->gmv_id_cache[$gmv_id] = $result['id'];
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
        $this->gmv_id_cache[$gmv_id] = $contact_id;
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

    /**
     * Generate a diff of the existing to the desired entity
     * @param $desired_entity array data
     * @param $existing_entity array data
     * @param $attributes array of attributes to consider
     * @param $strip_attributes array attributes to strip from the result
     */
    protected function diff($desired_entity, $existing_entity, $attributes, $strip_attributes = [], $case_sensitive = true)
    {
        // first, find out if the entities are different
        $diff = [];
        foreach ($attributes as $attribute) {
            $desired_value = $desired_entity[$attribute] ?? null;
            $existing_value = $existing_entity[$attribute] ?? null;
            if ($case_sensitive) {
                if ($desired_value != $existing_value) {
                    $diff[$attribute] = $desired_value;
                }
            } else {
                if (strtolower($desired_value) != strtolower($existing_value)) {
                    $diff[$attribute] = $desired_value;
                }
            }
        }

        foreach ($strip_attributes as $strip_attribute) {
            unset($diff[$strip_attribute]);
        }

        return $diff;
    }

    /**
     * @param $options array list of options offered (as arrays)
     * @param $search array template data to look for
     * @param $attributes array list of attributes to take into account
     * @return array|null option from the options array that matches
     */
    protected function extractCurrentDetail($options, $search, $attributes) {
        foreach ($options as $option) {
            // accept the option, if all attributes match
            foreach ($attributes as $attribute) {
                $requested_value = $search[$attribute] ?? null;
                $option_value = $option[$attribute] ?? null;
                if ($requested_value != $option_value) {
                    continue 2;
                }
            }
            // seems to be fine
            return $option;
        }
        return null;
    }


    /**
     * Get a set of labels of for the given fields
     *
     * @param array $field_set
     *   list of field names (internal)
     *
     * @return array  field_name => field_label mapping
     */
    public function getFieldLabels($field_set, $xcm_config) {
        // todo: caching/customfields
        return CRM_Xcm_Tools::getFieldLabels($field_set, $xcm_config);
    }


    /*****************************************************************
     *                  LOADING DATA                                **
     *****************************************************************/

    /**
     * Synchronise the data structures with the custom data helper
     */
    protected function syncDataStructures()
    {
        $this->log("Syncing data structures");
        $customData = new CRM_Gmv_CustomData(E::LONG_NAME);
        $customData->syncOptionGroup(E::path('resources/option_group_catechism.json'));
        $customData->syncCustomGroup(E::path('resources/custom_group_ekir_organisation.json'));
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
    protected function loadOrganisations()
    {
        $this->organisations = (new CRM_Gmv_Entity_Organization($this,
                $this->getImportFile('ekir_gmv/organization.csv')))->load();

        $this->log("Organization data loaded.");
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



    /*****************************************************************
     *                  IMPORT / SYNCHRONISATION                    **
     *****************************************************************/

    /**
     * Apply the option groups
     */
    protected function syncOrganisations()
    {
        // update/create organisation data
        $newly_created_contacts = [];
        $counter = 0;
        $created_counter = 0;
        $record_count = $this->organisations->getRecordCount();
        $this->log("Starting organisation synchronisation of {$record_count} records...", 'info');
        foreach ($this->organisations->getAllRecords() as $record) {
            // links to other organisations will be synced below
            unset($record['gmv_data.gmv_data_master_id']);

            // sync with civicrm: first: does the contact already exist?
            $existing_contact_id = $this->getGmvContactId($record['gmv_id']);
            if ($existing_contact_id) {
                // contact exists, check if update is necessary
                $this->updateContact($existing_contact_id, $record);
            } else {
                try {
                    // prepare
                    CRM_Gmv_CustomData::resolveCustomFields($record);
                    $create_result = $this->api3('Contact', 'create', $record);
                    $contact_id = $create_result['id'];
                    $newly_created_contacts[$contact_id] = $create_result;
                    $this->setGmvContactID($contact_id, $record['gmv_id']);
                } catch (CiviCRM_API3_Exception $ex) {
                    $this->log("Organisation creation failed [{$record['gmv_id']}]: " . $ex->getMessage(), 'error');
                }
            }

            // progress logging
            $counter++;
            if (!($counter % 100)) {
                $this->log("{$counter} organisations synchronised...");
            }
        }
        $this->log("all organisations synchronised.");

        // add contact links
        $counter = 0;
        $link_field_key = CRM_Gmv_CustomData::getCustomFieldKey('gmv_data','gmv_data_master_id');
        foreach ($this->organisations->getAllRecords() as $record) {
            if (empty($record['gmv_data.gmv_data_master_id'])) continue; // nothing to do here

            // links to other organisations will be synced below
            $contact_id = $this->getGmvContactId($record['gmv_id']);
            $parent_id = $this->getGmvContactId($record['gmv_data.gmv_data_master_id']);
            if (!$contact_id || !$parent_id) {
                $this->log("Either GMV-{$record['gmv_id']} or GMV-{$record['gmv_data.gmv_data_master_id']} do not exist", 'error');
            }

            // newly created contacts don't need an upgrade
            $contact_newly_created = isset($newly_created_contacts[$contact_id]);
            if ($contact_newly_created) {
                // just set the ID
                $this->api3('Contact', 'create', ['id' => $contact_id, $link_field_key => $parent_id]);
            } else {
                // we have to check the current value
                $current_parent_id = (string) $this->api3('Contact', 'getvalue', [
                    'id' => $contact_id, 'return' => $link_field_key]);
                if ($current_parent_id != $parent_id) {
                    $this->api3('Contact', 'create', ['id' => $contact_id, $link_field_key => $parent_id]);
                    $this->recordChange($contact_id, 'gmv_data.gmv_data_master_id', $current_parent_id, $parent_id);
                }
            }

            // progress logging
            $counter++;
            if (!($counter % 100)) {
                $this->log("{$counter} organisations linked...");
            }
        }
        $this->log("all organisations linked...");

        // todo: deleted contacts?
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
                $this->log("GMV Contact 'GMV-{$record['gmv_id']}' by ID-Tracker: {$existing_contact_id}", 'debug');
            } else {
                unset($record['id']); // just to be safe
            }

            // run XCM
            $record['xcm_profile'] = $xcm_profile;
            $record['location_type_id'] = 2; // work
            try {
                $xcm_result = $this->api3('Contact', 'getorcreate', $record);
                $this->log("GMV Contact 'GMV-{$record['gmv_id']}' passed through XCM", 'debug');
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
            $existing_contact_id = $this->getGmvContactId($record['gmv_id']);
            if ($existing_contact_id) {
                // contact exists, check if update is necessary
                $this->updateContact($existing_contact_id, $record);
            } else {
                try {
                    $create_result = $this->api3('Contact', 'create', $record);
                    $contact_id = $create_result['id'];
                    $this->setGmvContactID($contact_id, $record['gmv_id']);
                } catch (CiviCRM_API3_Exception $ex) {
                    $this->log("Individual creation failed [{$record['gmv_id']}]: " . $ex->getMessage(), 'error');
                }
            }
        }
    }

    /**
     * Synchronise all emails
     */
    public function syncEmails()
    {
        $this->log("Synchronising emails...", 'info');
        $contact2email_wanted = [];
        $contact2email_current = [];

        // generate expected email by contact list
        $records = $this->emails->getAllRecords();
        foreach ($records as $record) {
            $contact_id = $this->getGmvContactId($record['contact_id']);
            if ($contact_id) {
                $record['contact_id'] = $contact_id;
                $contact2email_wanted[$contact_id][] = $record;
            }
        }

        // generate current email by contact list
        $email_data = CRM_Core_DAO::executeQuery("
            SELECT 
                email.contact_id       AS contact_id, 
                email.email            AS email, 
                email.id               AS email_id, 
                email.location_type_id AS location_type_id
            FROM civicrm_email email
            LEFT JOIN civicrm_contact contact
                   ON contact.id = email.contact_id
            INNER JOIN civicrm_value_contact_id_history idtracker
                    ON idtracker.entity_id = email.contact_id
                   AND idtracker.identifier_type = 'gmv_id'
            WHERE contact.is_deleted = 0;
        ");
        while ($email_data->fetch()) {
            $contact2email_current[$email_data->contact_id][] = [
                'email'            => $email_data->email,
                'location_type_id' => $email_data->location_type_id,
                'email_id'         => $email_data->email_id,
                'contact_id'       => $email_data->contact_id,
            ];
        }
        $email_data->free();

        // now sync
        $important_attributes = ['email', 'location_type_id'];
        $match_order = [['email', 'location_type_id'], ['email'], ['location_type_id']];
        foreach ($contact2email_wanted as $contact_id => &$wanted_contact_emails) {
            // now see if this already exists in the db
            // first try full matches, then only by email
            foreach ($match_order as $match_attributes) {
                foreach ($wanted_contact_emails as $index => $wanted_contact_email) {
                    $existing_email = $this->extractCurrentDetail($contact2email_current[$contact_id] ?? [], $wanted_contact_email, $match_attributes);
                    if ($existing_email) {
                        // we have a match!
                        unset($wanted_contact_emails[$index]); // we got this
                        $diff = $this->diff($wanted_contact_email, $existing_email, $important_attributes, ['email_id'], false);
                        if ($diff) {
                            // but...it needs to be updated
                            $this->log("Updating email [{$existing_email['email_id']}]: {$wanted_contact_email['email']}", 'debug');
                            $this->api3('Email', 'create', [
                                'id' => $existing_email['email_id'],
                                'email' => $wanted_contact_email['email'],
                                'location_type_id' => $wanted_contact_email['location_type_id'],
                            ]);
                            $this->recordChange($existing_email['contact_id'], "email [{$existing_email['location_type_id']}]", $existing_email['email'], $wanted_contact_email['email']);
                        }
                        continue 3; // move on to the next wanted email
                    }
                }
            }

            // when we get here, the remaining emails need to be created
            foreach ($wanted_contact_emails as $wanted_contact_email) {
                $this->api3('Email', 'create', [
                    'contact_id' => $wanted_contact_email['contact_id'],
                    'email' => $wanted_contact_email['email'],
                    'location_type_id' => $wanted_contact_email['location_type_id'],
                ]);
                $this->log("Created email {$wanted_contact_email['email']} for contact {$wanted_contact_email['contact_id']}", 'debug');
                $this->recordChange($contact_id, "email [{$wanted_contact_email['location_type_id']}]", '', $wanted_contact_email['email']);
            }
        }
        $this->log("Synchronising emails done.", 'info');
    }




    /**
     * Take the recorded change items and turn into an activity with the contact
     */
    public function generateChangeActivities()
    {
        $this->log("TODO: activities", 'todo');
        $xcm_profile_name = Civi::settings()->get('gmv_xcm_profile_individuals');
        $xcm_profile = CRM_Xcm_Configuration::getConfigProfile($xcm_profile_name);

        foreach ($this->recorded_changes as $contact_id => $change_set) {
            if (!empty($change_set)) {
                // create activity
                $data = array(
                    'differing_attributes' => $change_set,
                    'fieldlabels'          => $this->getFieldLabels($change_set, $xcm_profile),
                    'existing_contact'     => $contact_id,
                );

                $activity_data = array(
                    'activity_type_id'   => $options['diff_activity'],
                    'subject'            => $subject,
                    'status_id'          => 'Completed',
                    'activity_date_time' => date("YmdHis"),
                    'target_contact_id'  => (int) $contact_id,
                    'source_contact_id'  => todo,
                    'campaign_id'        => CRM_Utils_Array::value('campaign_id', $contact_data),
                    'details'            => $this->renderTemplate('activity/diff.tpl', $data),
                );

                try {
                    $activity = CRM_Activity_BAO_Activity::create($activity_data);
                } catch (Exception $e) {
                    // some problem with the creation
                    error_log("de.systopia.xcm: error when trying to create diff activity: " . $e->getMessage());
                }
            }
        }
    }

}
