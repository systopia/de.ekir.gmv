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
    /** @var string folder name  */
    const BASE_FOLDER = 'GMV_Imports';

    /** @var string XCM profile name to be used for individuals */
    const XCM_PROFILE_INDIVIDUALS = 'gmv_xcm_profile_individuals';

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

    /** @var CRM_Gmv_Entity_Entity $employment relationship data */
    public $employments = null;

    /** @var CRM_Gmv_Entity_Individual contact data */
    public $individuals = null;

    /** @var CRM_Gmv_Entity_Organization contact data */
    public $organizations = null;

    /** @var CRM_Gmv_Entity_Individual contact data prepped for XCM */
    public $individuals_xcm = null;

    /** @var array person id -> organisation id */
    public $_individual2organisation = null;

    /**
     * Run the given import
     */
    public function run()
    {
        $this->log("Starting GMV importer on: " . $this->getFolder());
        $this->fillGmvIdCache();
        $this->loadLists();
        $this->syncDataStructures();
        $this->loadOrganisations();
        $this->loadEmployments();
        $this->syncOrganisations();
        $this->loadContactDetails();
        $this->loadContacts();
//        $this->syncContacts();
        // details
        $this->purgeDetails();
        $this->syncEmails();
        $this->syncPhones();
        $this->syncAddresses();

        // employments
//        $this->syncEmployments();

//        $this->generateChangeActivities();
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
        $query->free();
        $cache_size = count($this->gmv_id_cache);
        $this->log("Filled GMV-ID cache with {$cache_size} entries.");
    }

    /**
     * Get a contact_id of the contact with the given GMV-ID
     *
     * @param $gmv_id
     */
    public function getGmvContactId($gmv_id, $cache_only = false) {
        if (isset($this->gmv_id_cache[$gmv_id])) {
            return $this->gmv_id_cache[$gmv_id];
        } else {
            if ($cache_only) {
                // shortcut to skip lookup
                return null;
            }
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
    protected function diff($desired_entity, $existing_entity, $attributes, $strip_attributes = [], $case_agnostic_attributes = [])
    {
        // first, find out if the entities are different
        $diff = [];
        foreach ($attributes as $attribute) {
            $desired_value = $desired_entity[$attribute] ?? null;
            $existing_value = $existing_entity[$attribute] ?? null;
            if (in_array($attribute, $case_agnostic_attributes)) {
                $desired_value = strtolower($desired_value);
                $existing_value = strtolower($existing_value);
            }
            if ($desired_value != $existing_value) {
                $diff[$attribute] = $desired_value;
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
    protected function fetchNextDetail($options, $search, $attributes, $case_agnostic_attributes = []) {
        foreach ($options as $option) {
            // accept the option, if all attributes match
            foreach ($attributes as $attribute) {
                $requested_value = $search[$attribute] ?? null;
                $option_value = $option[$attribute] ?? null;
                if (in_array($attribute, $case_agnostic_attributes)) {
                    $requested_value = strtolower($requested_value);
                    $option_value = strtolower($option_value);
                }
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
        // update option groups
        $this->log("Syncing option groups");
        $this->occupations->syncToOptionGroup('gmv_employee_job', "Job Titel");

        // update data structures
        $this->log("Syncing data structures");
        CRM_Gmv_DataStructures::getEmploymentRelationshipType();
        $customData = new CRM_Gmv_CustomData(E::LONG_NAME);
        $customData->syncCustomGroup(E::path('resources/custom_group_ekir_employment.json'));
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
        if (!isset($this->organisations)) {
            $this->organisations = (new CRM_Gmv_Entity_Organization($this,
                    $this->getImportFile('ekir_gmv/organization.csv')))->load();

            $this->log("Organization data loaded.");
        }
    }

    /**
     * Load the contacts
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
     * Load the contact emplyment relationship
     */
    protected function loadEmployments()
    {
        $this->employments = (new CRM_Gmv_Entity_Employment($this,
                $this->getImportFile('ekir_gmv/employment.csv')))->load();
        $this->log("Employment data loaded.");
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
        if (empty($xcm_profile)) throw new Exception("XCM profile for individuals not set. See civicrm/gmv/zipupload");
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
                $this->log("XCM FAILED ['GMV-{$record['gmv_id']}']. Error is:  " . $ex->getMessage(), 'error');
                $this->log("XCM FAILED ['GMV-{$record['gmv_id']}']. Record is: " . json_encode($record), 'debug');
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
     * Restrict the details (email, address, phone) to the
     *  contacts actually existing
     */
    public function purgeDetails()
    {
        $individual_ids = $this->individuals->getAttributeOccurance('gmv_id');
        $organisation_ids = $this->organisations->getAttributeOccurance('gmv_id');
        $importing_gmv_ids = array_merge(array_keys($individual_ids), array_keys($organisation_ids));
        $this->emails->filterEntityData('contact_id', 'in', $importing_gmv_ids);
        $this->phones->filterEntityData('contact_id', 'in', $importing_gmv_ids);
        $this->websites->filterEntityData('contact_id', 'in', $importing_gmv_ids);
        $this->addresses->filterEntityData('contact_id', 'in', $importing_gmv_ids);
        $this->employments->filterEntityData('contact_id_a', 'in', $importing_gmv_ids);
        $this->employments->filterEntityData('contact_id_b', 'in', $importing_gmv_ids);
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
                email.is_primary       AS is_primary, 
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
                'is_primary'       => $email_data->is_primary,
                'email_id'         => $email_data->email_id,
                'contact_id'       => $email_data->contact_id,
            ];
        }
        $email_data->free();

        // now sync
        $important_attributes = ['email', 'location_type_id', 'is_primary'];
        $match_order = [['email', 'location_type_id', 'is_primary'], ['email', 'location_type_id'], ['email']];
        foreach ($contact2email_wanted as $contact_id => &$wanted_contact_emails) {
            // first make sure there is exactly only one is_primary
            $this->removeDuplicates($wanted_contact_emails, ['email']);
            $this->fixPrimary($wanted_contact_emails);
            // now see if this already exists in the db
            // first try full matches, then only by email
            foreach ($wanted_contact_emails as $index => $wanted_contact_email) {
                foreach ($match_order as $match_attributes) {
                    $existing_email = $this->fetchNextDetail($contact2email_current[$contact_id] ?? [], $wanted_contact_email, $match_attributes, ['email']);
                    if ($existing_email) {
                        // we have a match!
                        unset($wanted_contact_emails[$index]); // we got this
                        $diff = $this->diff($wanted_contact_email, $existing_email, $important_attributes, ['email_id'], ['email']);
                        if ($diff) {
                            $this->api3('Email', 'create', [
                                'id' => $existing_email['email_id'],
                                'email' => $wanted_contact_email['email'],
                                'is_primary' => $wanted_contact_email['is_primary'],
                                'location_type_id' => $wanted_contact_email['location_type_id'],
                            ]);
                            $this->log("Updated email [{$existing_email['email_id']}]: {$wanted_contact_email['email']}", 'debug');
                            $this->recordChange($existing_email['contact_id'], "email [{$existing_email['location_type_id']}]", $existing_email['email'], $wanted_contact_email['email']);
                        }
                        continue 2; // move on to the next wanted email
                    }
                }
            }

            // when we get here, the remaining emails need to be created
            foreach ($wanted_contact_emails as $wanted_contact_email) {
                $this->api3('Email', 'create', [
                    'contact_id' => $wanted_contact_email['contact_id'],
                    'email' => $wanted_contact_email['email'],
                    'is_primary' => $wanted_contact_email['is_primary'],
                    'location_type_id' => $wanted_contact_email['location_type_id'],
                ]);
                $this->log("Created email {$wanted_contact_email['email']} for contact {$wanted_contact_email['contact_id']}", 'debug');
                $this->recordChange($contact_id, "email [{$wanted_contact_email['location_type_id']}]", '', $wanted_contact_email['email']);
            }
        }
        $this->log("Synchronising emails done.", 'info');
    }

    /**
     * Synchronise all phones
     */
    public function syncPhones()
    {
        $this->log("Synchronising phones...", 'info');
        $contact2phone_wanted = [];
        $contact2phone_current = [];

        // generate expected phone by contact list
        $records = $this->phones->getAllRecords();
        foreach ($records as $record) {
            $contact_id = $this->getGmvContactId($record['contact_id']);
            if ($contact_id) {
                $record['contact_id'] = $contact_id;
                $contact2phone_wanted[$contact_id][] = $record;
            }
        }

        // generate current phone by contact list
        $phone_data = CRM_Core_DAO::executeQuery("
            SELECT 
                phone.contact_id       AS contact_id, 
                phone.phone            AS phone, 
                phone.id               AS phone_id, 
                phone.phone_type_id    AS phone_type_id, 
                phone.location_type_id AS location_type_id
            FROM civicrm_phone phone
            LEFT JOIN civicrm_contact contact
                   ON contact.id = phone.contact_id
            INNER JOIN civicrm_value_contact_id_history idtracker
                    ON idtracker.entity_id = phone.contact_id
                   AND idtracker.identifier_type = 'gmv_id'
            WHERE contact.is_deleted = 0;
        ");
        while ($phone_data->fetch()) {
            $contact2phone_current[$phone_data->contact_id][] = [
                'phone'            => $phone_data->phone,
                'location_type_id' => $phone_data->location_type_id,
                'phone_type_id'    => $phone_data->phone_type_id,
                'phone_id'         => $phone_data->phone_id,
                'contact_id'       => $phone_data->contact_id,
            ];
        }
        $phone_data->free();

        // now sync
        $important_attributes = ['phone', 'location_type_id', 'phone_type_id'];
        $match_order = [['phone', 'location_type_id', 'phone_type_id'], ['phone', 'phone_type_id'], ['phone', 'location_type_id'], ['phone']];
        foreach ($contact2phone_wanted as $contact_id => &$wanted_contact_phones) {
            // first make sure there is exactly only one is_primary
            $this->removeDuplicates($wanted_contact_phones, ['phone']);
            $this->fixPrimary($wanted_contact_phones);
            // now see if this already exists in the db
            // first try full matches, then only by phone
            foreach ($wanted_contact_phones as $index => $wanted_contact_phone) {
                foreach ($match_order as $match_attributes) {
                    $existing_phone = $this->fetchNextDetail($contact2phone_current[$contact_id] ?? [], $wanted_contact_phone, $match_attributes);
                    if ($existing_phone) {
                        // we have a match!
                        unset($wanted_contact_phones[$index]); // we got this
                        $diff = $this->diff($wanted_contact_phone, $existing_phone, $important_attributes, ['phone_id'], ['phone']);
                        if ($diff) {
                            // but...it needs to be updated
                            $this->api3('Phone', 'create', [
                                'id' => $existing_phone['phone_id'],
                                'phone' => $wanted_contact_phone['phone'],
                                'phone_type_id' => $wanted_contact_phone['phone_type_id'],
                                'location_type_id' => $wanted_contact_phone['location_type_id'],
                            ]);
                            $this->log("Updated phone [{$existing_phone['phone_id']}]: '{$wanted_contact_phone['phone']}'", 'debug');
                            $this->recordChange($existing_phone['contact_id'], "phone [{$existing_phone['location_type_id']}]", $existing_phone['phone'], $wanted_contact_phone['phone']);
                        }
                        continue 2; // move on to the next wanted phone
                    }
                }
            }

            // when we get here, the remaining phones need to be created
            foreach ($wanted_contact_phones as $wanted_contact_phone) {
                $this->api3('Phone', 'create', [
                    'contact_id' => $wanted_contact_phone['contact_id'],
                    'phone' => $wanted_contact_phone['phone'],
                    'phone_type_id' => $wanted_contact_phone['phone_type_id'],
                    'location_type_id' => $wanted_contact_phone['location_type_id'],
                ]);
                $this->log("Created phone '{$wanted_contact_phone['phone']}' for contact {$wanted_contact_phone['contact_id']}", 'debug');
                $this->recordChange($contact_id, "phone [{$wanted_contact_phone['location_type_id']}]", '', $wanted_contact_phone['phone']);
            }
        }
        $this->log("Synchronising phones done.", 'info');
    }

    /**
     * Synchronise all addresses
     */
    public function syncAddresses()
    {
        $this->log("Synchronising addresses...", 'info');
        $contact2address_wanted = [];
        $contact2address_current = [];

        // turn off geocoding
        $geocoding_provider = Civi::settings()->get('geoProvider');
        Civi::settings()->set('geoProvider', '');

        // generate expected address by contact list
        $records = $this->addresses->getAllRecords();
        foreach ($records as $record) {
            $contact_id = $this->getGmvContactId($record['contact_id']);
            if ($contact_id) {
                $record['contact_id'] = $contact_id;
                $contact2address_wanted[$contact_id][] = $record;
            }
        }

        // generate current address by contact list
        $address_data = CRM_Core_DAO::executeQuery("
            SELECT 
                address.contact_id                AS contact_id, 
                address.street_address            AS street_address, 
                address.postal_code               AS postal_code, 
                address.city                      AS city, 
                address.supplemental_address_1    AS supplemental_address_1, 
                address.supplemental_address_2    AS supplemental_address_2, 
                address.is_primary                AS is_primary, 
                address.country_id                AS country_id, 
                address.geo_code_1                AS geo_code_1, 
                address.geo_code_2                AS geo_code_2, 
                address.id                        AS address_id, 
                address.location_type_id          AS location_type_id
            FROM civicrm_address address
            LEFT JOIN civicrm_contact contact
                   ON contact.id = address.contact_id
            INNER JOIN civicrm_value_contact_id_history idtracker
                    ON idtracker.entity_id = address.contact_id
                   AND idtracker.identifier_type = 'gmv_id'
            WHERE contact.is_deleted = 0;
        ");
        while ($address_data->fetch()) {
            $contact2address_current[$address_data->contact_id][] = [
                'location_type_id' => $address_data->location_type_id,
                'street_address' => $address_data->street_address,
                'postal_code' => $address_data->postal_code,
                'city' => $address_data->city,
                'supplemental_address_1' => $address_data->supplemental_address_1,
                'supplemental_address_2' => $address_data->supplemental_address_2,
                'is_primary' => $address_data->is_primary,
                'country_id' => $address_data->country_id,
                'geo_code_1' => $address_data->geo_code_1,
                'geo_code_2' => $address_data->geo_code_2,
                'contact_id' => $address_data->contact_id,
                'id' => $address_data->address_id,
            ];
        }
        $address_data->free();

        // now sync
        $important_attributes = ['street_address', 'postal_code', 'city', 'supplemental_address_1', 'location_type_id'];
        $match_order = [['street_address', 'postal_code', 'city', 'supplemental_address_1', 'location_type_id'], ['street_address', 'postal_code', 'city', 'supplemental_address_1']];
        foreach ($contact2address_wanted as $contact_id => &$wanted_contact_addresses) {
            // first make sure there is exactly only one is_primary
            $this->removeDuplicates($wanted_contact_addresses, ['street_address', 'postal_code']);
            $this->fixPrimary($wanted_contact_addresses);
            // now see if this already exists in the db
            // first try full matches, then only by address
            foreach ($wanted_contact_addresses as $index => $wanted_contact_address) {
                foreach ($match_order as $match_attributes) {
                    $existing_address = $this->fetchNextDetail($contact2address_current[$contact_id] ?? [], $wanted_contact_address, $match_attributes, ['address']);
                    if ($existing_address) {
                        // we have a match!
                        unset($wanted_contact_addresses[$index]); // we got this
                        $diff = $this->diff($wanted_contact_address, $existing_address, $important_attributes, ['address_id'], ['address']);
                        if ($diff) {
                            // but...it needs to be updated
                            $this->api3('Address', 'create', [
                                'id' => $existing_address['address_id'],
                                'contact_id' => $existing_address['contact_id'],
                                'location_type_id' => $wanted_contact_address['location_type_id'],
                                'street_address' => $wanted_contact_address['street_address'],
                                'postal_code' => $wanted_contact_address['postal_code'],
                                'city' => $wanted_contact_address['city'],
                                'supplemental_address_1' => $wanted_contact_address['supplemental_address_1'],
                                'country_id' => $wanted_contact_address['country_id'],
                                'geo_code_1' => $wanted_contact_address['geo_code_1'],
                                'geo_code_2' => $wanted_contact_address['geo_code_2'],
                            ]);
                            $this->log("Updated address [{$existing_address['address_id']}]: {$wanted_contact_address['postal_code']}/{$wanted_contact_address['street_address']}", 'debug');
                            $this->recordChange($existing_address['contact_id'], "address [{$existing_address['location_type_id']}]", $existing_address['address'], $wanted_contact_address['address']);
                        }
                        continue 2; // move on to the next wanted address
                    }
                }
            }

            // when we get here, the remaining addresses need to be created
            foreach ($wanted_contact_addresses as $wanted_contact_address) {
                $this->api3('Address', 'create', [
                    'contact_id' => $wanted_contact_address['contact_id'],
                    'is_primary' => $wanted_contact_address['is_primary'],
                    'location_type_id' => $wanted_contact_address['location_type_id'],
                    'street_address' => $wanted_contact_address['street_address'],
                    'postal_code' => $wanted_contact_address['postal_code'],
                    'city' => $wanted_contact_address['city'],
                    'supplemental_address_1' => $wanted_contact_address['supplemental_address_1'],
                    'country_id' => $wanted_contact_address['country_id'],
                    'geo_code_1' => $wanted_contact_address['geo_code_1'],
                    'geo_code_2' => $wanted_contact_address['geo_code_2'],
                ]);
                $this->log("Created address {$wanted_contact_address['postal_code']}/{$wanted_contact_address['street_address']} for contact {$wanted_contact_address['contact_id']}", 'debug');
                $this->recordChange($contact_id, "address [{$wanted_contact_address['location_type_id']}]", '', $wanted_contact_address['address']);
            }
        }
        // turn on geocoding
        Civi::settings()->set('geoProvider', $geocoding_provider);

        $this->log("Synchronising addresses done.", 'info');
    }

    /**
     * Synchronise the employments
     *
     * @todo THIS IS JUST AN IMPORT, IMPLEMENT SYNCING!
     */
    public function syncEmployments()
    {
        // compile our records
        $relationship_type = CRM_Gmv_DataStructures::getEmploymentRelationshipType();
        $records = $this->employments->getAllRecords();
        foreach ($records as $record) {
            $create_record = $record;
            $create_record['contact_id_a'] = $this->getGmvContactId("{$record['contact_id_a']}");
            $create_record['contact_id_b'] = $this->getGmvContactId("{$record['contact_id_b']}");
            $create_record['relationship_type_id'] = $relationship_type['id'];
            if (empty($create_record['contact_id_a']) || empty($create_record['contact_id_b'])) {
                $this->log("Contacts missing!");
            }
            try {
                CRM_Gmv_CustomData::resolveCustomFields($create_record);
                civicrm_api3('Relationship', 'create', $create_record);
                $this->log("Created employer relationship between [GMV-{$record['contact_id_a']}] and [GMV-{$record['contact_id_b']}]");
            } catch (CiviCRM_API3_Exception $ex) {
                $this->log("Error while creating employer relationship between [GMV-{$record['contact_id_a']}] and [GMV-{$record['contact_id_b']}]: " . $ex->getMessage(), 'warn');
            }
        }

        // run a query to set the employer_ids
        CRM_Core_DAO::executeQuery("
            UPDATE civicrm_contact contact
            LEFT JOIN civicrm_relationship relationship 
                 ON relationship.contact_id_a = contact.id
                 AND relationship.relationship_type_id = {$relationship_type['id']}
            LEFT JOIN civicrm_contact organisation
                 ON relationship.contact_id_b = organisation.id
            SET 
                contact.employer_id = relationship.contact_id_b,
                contact.organization_name = organisation.display_name
            WHERE contact.employer_id IS NULL;
        ");
    }



    /**
     * Take the recorded change items and turn into an activity with the contact
     */
    public function generateChangeActivities()
    {
        $activity_type_id = (int) Civi::settings()->get('gmv_change_activity_type_id');
        if (!$activity_type_id) {
            $this->log('Anlegen der ??nderungsaktivit??ten deaktiviert (kein Aktivit??tstyp ausgew??hlt)', 'debug');
        }

        $smarty = CRM_Core_Smarty::singleton();
        foreach ($this->recorded_changes as $contact_id => $change_set) {
            if (!empty($change_set)) {
                // compile the change data
                $change_data = [];
                foreach ($change_set as $key => $old_new_value) {
                    $change_data[] = [
                        'label' => $this->getChangeLabel($key),
                        'old_value' => $this->formatValue($key, $old_new_value[0]),
                        'new_value' => $this->formatValue($key, $old_new_value[1]),
                    ];
                }

                // render the change data
                $smarty->assign('change_set', $change_data);

                // create result
                $this->api3('Activity', 'create', [
                    'activity_type_id'   => 1, //$activity_type_id,
                    'subject'            => "Update durch GMV",
                    'status_id'          => 'Completed',
                    'activity_date_time' => date("YmdHis"),
                    'target_contact_id'  => (int) $contact_id,
                    'source_contact_id'  => CRM_Core_Session::getLoggedInContactID(),
                    'details'            => $smarty->fetch(E::path('resources/change_record.tpl'))
                ]);
            }
        }
    }

    /**
     * Get the label of a field name
     *
     * @param $field_name
     *   technical field name
     * @return string
     *   label for the string
     */
    protected function getChangeLabel($field_name)
    {
        static $label_cache = [];
        if (!isset($label_cache[$field_name])) {
            $known_labels = [
                'organization_name' => 'Organisationsname',
                'first_name' => 'Vorname',
                'last_name' => 'Nachname',
                'birth_date' => 'Geburtsdatum',
                'gender_id' => 'Geschlecht',
                'prefix_id' => 'Anrede',
                'job_title' => 'Berufsbezeichnung',
                'city' => 'Stadt',
                'country_id' => 'Land',
                'street_address' => 'Stra??e/Hausnummer',
                'postal_code' => 'Postleitzahl',
                'supplemental_address_1' => 'Addresszusatz',
                'email' => 'E-Mail',
                'phone' => 'Telefon',
                'is_primary' => 'Prim??r?',
                'location_type_id' => 'Typ',
                'phone_type_id' => 'Telefonart',
            ];

            // check if it's a custom field
            if (isset($known_labels[$field_name])) {
                // check if it's in the list
                $label_cache[$field_name] = $known_labels[$field_name];
            } elseif (strstr($field_name, '.')) {
                // check if it's a custom field
                [$group_name, $field_key] = explode('.', $field_name, 2);
                $field = CRM_Gmv_CustomData::getCustomField($group_name, $field_key);
                if ($field) {
                    $label_cache[$field_name] = $field['label'];
                }
            } else {
                $this->log("No label for changed field '{$field_name}'", 'warning');
                $label_cache[$field_name] = ucwords($field_name);
            }
        }
        return $label_cache[$field_name] ?? $field_name;
    }

    /**
     * Get the label of a field name
     *
     * @param $field_name string internal field name
     * @param $value string value
     * @return string the value to be printed
     */
    protected function formatValue($field_name, $value)
    {
        switch ($field_name) {
            case 'gender_id':
                return $this->optionValueLookup($value, 'gender');
            case 'prefix_id':
                return $this->optionValueLookup($value, 'individual_prefix');
            case 'phone_type_id':
                return $this->optionValueLookup($value, 'phone_type');
            case 'country_id':
                return $this->countryLookup($value);
            case 'location_type_id':
                return $this->locationLookup($value);
            default:
                // check if it's a custom field with a value
                if (strstr($field_name, '.')) {
                    [$group_name, $field_key] = explode('.', $field_name, 2);
                    $field = CRM_Gmv_CustomData::getCustomField($group_name, $field_key);
                    if ($field && !empty($field['option_group_id'])) {
                        return $this->optionValueLookup($value, $field['option_group_id']);
                    }
                }
        }
        return $value;
    }

    /**
     * @param $value string
     *      the value to be looked up
     * @param $option_group_id string
     *      option group name or ID
     *
     * @return string formatted value
     */
    protected function optionValueLookup($value, $option_group_id) {
        static $option_groups = [];
        if (!isset($option_groups[$option_group_id])) {
            $option_groups[$option_group_id] = [];
            $data = $this->api3('OptionValue', 'get', [
                'option_group_id' => $option_group_id,
                'return' => 'value,label',
                'option.limit' => 0,
            ]);
            foreach ($data['values'] as $entry) {
                $option_groups[$option_group_id][$entry['value']] = $entry['label'];
            }
        }

        return $option_groups[$option_group_id][$value] ?? $value;
    }

    /**
     * Get the location type resolved value
     *
     * @param $value
     */
    protected function locationLookup($value)
    {
        static $location_types = null;
        if ($location_types === null) {
            $location_types = $this->api3('LocationType', 'get', ['sequential' => 0])['values'];
        }
        return $location_types[$value] ?? $value;
    }

    /**
     * Get the country
     *
     * @param $value
     */
    protected function countryLookup($value)
    {
        static $country_data = null;
        if ($country_data === null) {
            $country_data = civicrm_api3('Country', 'get', [
                'option.limit' => 0,
                'return' => ['id', 'name'],
                'sequential' => 0,
            ])['values'];
        }
        return $country_data[$value]['name'] ?? $value;
    }

    /**
     * Make sure that only one of the records is primary,
     *  but also make sure that there is one
     *
     * @param array $records
     *   list of array-based data records
     * @param array $attributes
     *   list of attributes that constitute a duplicate
     */
    public function removeDuplicates(&$records, $attributes)
    {
        // make sure that there is no more than one primary
        $keys = [];
        $duplicates = [];
        foreach ($records as $index => &$record) {
            $key_elements = [];
            foreach ($attributes as $attribute) {
                $key_elements[] = $record[$attribute] ?? '';
            }
            $key = implode('|', $key_elements);
            if (in_array($key, $keys)) {
                // this is a duplicate
                $duplicates[] = $index;
            } else {
                $keys[] = $key;
            }
        }

        // finally, remove the duplicates
        foreach ($duplicates as $duplicate_key) {
            unset($records[$duplicate_key]);
        }
    }

    /**
     * Make sure that only one of the records is primary,
     *  but also make sure that there is one
     *
     * @param array $records
     *   list of array-based data records
     */
    public function fixPrimary(&$records)
    {
        // make sure that there is no more than one primary
        $has_primary = false;
        foreach ($records as &$record) {
            if ($has_primary) {
                // we already have one, so set all others to 0
                $record['is_primary'] = 0;
            } else {
                if (empty($record['is_primary'])) {
                    $record['is_primary'] = 0;
                } else {
                    $has_primary = true;
                    $record['is_primary'] = 1;
                }
            }
        }

        // but also make sure that there is one primary!
        if (!$has_primary) {
            foreach ($records as &$record) {
                $record['is_primary'] = 1;
                return;
            }
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
        static $log_file = null;
        if ($log_file === null) {
            $log_file_name = $this->folder . DIRECTORY_SEPARATOR . 'import_' . date('YmdHis') . '.log';
            $log_file = fopen($log_file_name, 'w');
            Civi::log()->debug("GMV Log file is '{$log_file_name}'");
        }

        // write log
        $timestamp = date('Y-m-d H:i:s');
        fputs($log_file, "{$timestamp} [{$level}]: {$message}\n");
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

}
