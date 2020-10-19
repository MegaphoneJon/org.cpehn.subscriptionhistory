<?php

class CRM_Subscriptionhistory_Form_Report_SubscriptionHistory extends CRM_Report_Form {

  protected $_emailField = FALSE;

  protected $_summary = NULL;

  protected $_customGroupGroupBy = FALSE; 

  protected $_customGroupExtends = array('Contact', 'Individual', 'Household', 'Organization');
  
  function __construct() {
    $this->_columns = array(
      'civicrm_contact' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => $this->getBasicContactFields(),
        'filters' => array(
          'sort_name' => array(
            'title' => ts('Contact Name'),
            'operator' => 'like',
          ),
          'is_deceased' => array(
            'title' => ts('Is Deceased'),
            'type' => CRM_Utils_Type::T_ENUM,
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'options' => array('' => '-- select Status --', '1' => 'Deceased','0' => 'Not Deceased')
          ),
          'id' => array(
            'no_display' => TRUE,
          ),
        ),
        'order_bys' => array(
          'sort_name' => array(
            'title' => ts('Last Name, First Name'),
            'default' => '1',
            'default_weight' => '0',
            'default_order' => 'ASC',
          ),
        ),
        'grouping' => 'contact-fields',
      ),
      'civicrm_address' => array(
        'dao' => 'CRM_Core_DAO_Address',
        'fields' => array(
          'street_address' => NULL,
          'supplemental_address_1' => NULL,
          'supplemental_address_2' => NULL,
          'supplemental_address_3' => NULL,
          'city' => NULL,
          'postal_code' => NULL,
          'state_province_id' => array(
            'title' => ts('State/Province'),
          ),
          'country_id' => array(
            'title' => ts('Country'),
          ),
        ),
        'grouping' => 'contact-fields',
      ),
      'civicrm_phone' => [
        'dao' => 'CRM_Core_DAO_Phone',
        'grouping' => 'contact-field',
        'fields' => [
          'phone' => [
            'title' => ts('Phone'),
            'default' => TRUE,
          ],
        ],
      ],
      'civicrm_group' => array(
        'dao' => 'CRM_Contact_DAO_GroupContact',
        'fields' => array(
          'title' => array(
            'title' => ts('Group'),
            'required' => TRUE,
            'default' => TRUE,
          ),
        ),
        'grouping' => 'group-fields',
      ),
      'civicrm_subscription_history' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => array(
          'date' => array(
            'title' => ts('Subscription Date'),
            'required' => TRUE,
            'default' => TRUE,
          ),
          'status' => array(
            'title' => ts('Subscription Status'),
            'default' => TRUE,
          ),
          'method' => array(
            'title' => ts('Subscription Method'),
          ),
        ),
        'filters' => array(
          'group_id'=> array(
            'title' => ts('Group'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'group' => TRUE,
            'options' => CRM_Core_PseudoConstant::group(),
          ),
          'date' => array(
            'title' => ts('Subscription Date'),
            'type' => CRM_Utils_Type::T_DATE,
          ),
          'status' => array(
            'title' => ts('Subscription Status'),
            'type' => CRM_Utils_Type::T_ENUM,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => array('Added' => 'Added','Removed' => 'Removed', 'Pending' => 'Pending', 'Deleted' => 'Deleted')
          ),
          'method' => array(
            'title' => ts('Subscription Method'),
            'type' => CRM_Utils_Type::T_ENUM,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => array('Admin' => 'Admin', 'Email' => 'Email', 'Web' => 'Web', 'API' => 'API' ),
          ),
        ),
        'grouping' => 'group-fields',
        'order_bys' => [
          'group_id' => [
            'title' => ts('Group'),
            'default' => '0',
            'default_weight' => '1',
            'default_order' => 'ASC',
          ],
          'date' => [
            'title' => ts('Subscription Date'),
            'default' => TRUE,
            'default_weight' => '0',
            'default_order' => 'DESC',
          ],
        ],
      ),
      'civicrm_email' => array(
        'dao' => 'CRM_Core_DAO_Email',
        'fields' => array('email' => NULL),
        'grouping' => 'contact-fields',
      ),
    ) + $this->addAddressFields(FALSE, TRUE);
    parent::__construct();
  }

  function preProcess() {
    $this->assign('reportTitle', ts('Subscription History'));
    parent::preProcess();
  }

  function select() {
    $select = $this->_columnHeaders = array();

    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $fieldName => $field) {
          if (CRM_Utils_Array::value('required', $field) ||
            CRM_Utils_Array::value($fieldName, $this->_params['fields'])
          ) {
            if ($tableName == 'civicrm_email') {
              $this->_emailField = TRUE;
            }
            $select[] = "{$field['dbAlias']} as {$tableName}_{$fieldName}";
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = $field['title'];
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] = CRM_Utils_Array::value('type', $field);
          }
        }
      }
    }
    foreach ($select as $key => $clause) {
      if (substr($clause, 0, 36) == 'subscription_history_civireport.date') {
        $select[$key] = str_replace('subscription_history_civireport.date', 'DATE_FORMAT(MAX(subscription_history_civireport.date), \'%Y-%m-%d\')', $clause);
      }
    }

    $this->_select = "SELECT " . implode(', ', $select) . " ";
  }

  function from() {
    $this->_from = NULL;

    $this->_from = "
                   FROM civicrm_subscription_history {$this->_aliases['civicrm_subscription_history']}
              LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
                     ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_subscription_history']}.contact_id
              LEFT JOIN civicrm_group {$this->_aliases['civicrm_group']}
                     ON {$this->_aliases['civicrm_group']}.id = {$this->_aliases['civicrm_subscription_history']}.group_id ";


    //used when email field is selected
    if ($this->_emailField) {
      $this->_from .= "
              LEFT JOIN civicrm_email {$this->_aliases['civicrm_email']}
                     ON {$this->_aliases['civicrm_contact']}.id =
                        {$this->_aliases['civicrm_email']}.contact_id AND
                        {$this->_aliases['civicrm_email']}.is_primary = 1\n";
    }

    $this->joinAddressFromContact();
    $this->joinPhoneFromContact();
  }

  function where() {
    $clauses = array(
      "{$this->_aliases['civicrm_contact']}.is_deleted = 0 ",
    );
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('filters', $table)) {
        foreach ($table['filters'] as $fieldName => $field) {
          $clause = NULL;
          if (CRM_Utils_Array::value('operatorType', $field) & CRM_Utils_Type::T_DATE) {
            $relative = CRM_Utils_Array::value("{$fieldName}_relative", $this->_params);
            $from     = CRM_Utils_Array::value("{$fieldName}_from", $this->_params);
            $to       = CRM_Utils_Array::value("{$fieldName}_to", $this->_params);

            $clause = $this->dateClause($field['name'], $relative, $from, $to, $field['type']);
          }
          else {
            $op = CRM_Utils_Array::value("{$fieldName}_op", $this->_params);
            if ($op) {
              $clause = $this->whereClause($field,
                $op,
                CRM_Utils_Array::value("{$fieldName}_value", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_min", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_max", $this->_params)
              );
            }
          }

          if (!empty($clause)) {
            $clauses[] = $clause;
          }
        }
      }
    }

    if (empty($clauses)) {
      $this->_where = "WHERE ( 1 ) ";
    }
    else {
      $this->_where = "WHERE " . implode(' AND ', $clauses);
    }

    if ($this->_aclWhere) {
      $this->_where .= " AND {$this->_aclWhere} ";
    }
  }

  function groupBy() {
    $this->_groupBy = " GROUP BY {$this->_aliases['civicrm_contact']}.id, {$this->_aliases['civicrm_group']}.id";
  }

  function postProcess() {

    $this->beginPostProcess();

    // get the acl clauses built before we assemble the query
    $this->buildACLClause($this->_aliases['civicrm_contact']);
    $sql = $this->buildQuery(TRUE);

    $rows = array();
    CRM_Core_DAO::disableFullGroupByMode();
    $this->buildRows($sql, $rows);
    CRM_Core_DAO::reenableFullGroupByMode();
    $this->formatDisplay($rows);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
  }

  function alterDisplay(&$rows) {
    // custom code to alter rows
    $entryFound = FALSE;
    $checkList = array();
    foreach ($rows as $rowNum => $row) {

      if (!empty($this->_noRepeats) && $this->_outputMode != 'csv') {
        // not repeat contact display names if it matches with the one
        // in previous row
        $repeatFound = FALSE;
        foreach ($row as $colName => $colVal) {
          if (CRM_Utils_Array::value($colName, $checkList) &&
            is_array($checkList[$colName]) &&
            in_array($colVal, $checkList[$colName])
          ) {
            $rows[$rowNum][$colName] = "";
            $repeatFound = TRUE;
          }
          if (in_array($colName, $this->_noRepeats)) {
            $checkList[$colName][] = $colVal;
          }
        }
      }

      if (array_key_exists('civicrm_contact_sort_name', $row) &&
        $rows[$rowNum]['civicrm_contact_sort_name'] &&
        array_key_exists('civicrm_contact_id', $row)
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view",
          'reset=1&cid=' . $row['civicrm_contact_id'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_contact_sort_name_link'] = $url;
        $rows[$rowNum]['civicrm_contact_sort_name_hover'] = ts("View Contact Summary for this Contact.");
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_address_state_province_id', $row)) {
        if ($colVal = $row['civicrm_address_state_province_id']) {
          $rows[$rowNum]['civicrm_address_state_province_id'] = CRM_Core_PseudoConstant::stateProvinceAbbreviation($colVal, FALSE);
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_address_country_id', $row)) {
        if ($colVal = $row['civicrm_address_country_id']) {
          $rows[$rowNum]['civicrm_address_country_id'] = CRM_Core_PseudoConstant::country($colVal, FALSE);
        }
        $entryFound = TRUE;
      }

      if (!$entryFound) {
        break;
      }
    }
  }
}
