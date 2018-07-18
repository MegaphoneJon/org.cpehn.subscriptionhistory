<?php

class CRM_Subscriptionhistory_Form_Report_SubscriptionHistory extends CRM_Report_Form {

  protected $_emailField = FALSE;

  protected $_summary = NULL;

  protected $_customGroupGroupBy = FALSE; 

  function __construct() {
    $this->_columns = array(
      'civicrm_contact' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => array(
          'sort_name' => array(
            'title' => ts('Contact Name'),
            'required' => TRUE,
            'default' => TRUE,
          ),
          'id' =>
          array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
        ),
        'filters' => array(
          'sort_name' => array(
            'title' => ts('Contact Name'),
            'operator' => 'like',
          ),
          'id' => array(
            'no_display' => TRUE,
          ),
        ),
        'grouping' => 'contact-fields',
      ),
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
            'required' => TRUE,
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
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'options' => array('' => '-- select Status --', 'Added' => 'Added','Removed' => 'Removed', 'Pending' => 'Pending', 'Deleted' => 'Deleted')
          ),
          'method' => array(
            'title' => ts('Subscription Method'),
            'type' => CRM_Utils_Type::T_ENUM,
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'options' => array('' => '-- select Method --', 'Admin' => 'Admin', 'Email' => 'Email', 'Web' => 'Web', 'API' => 'API' ),
          ),
        ),
        'grouping' => 'group-fields',
      ),
      'civicrm_email' => array(
        'dao' => 'CRM_Core_DAO_Email',
        'fields' => array('email' => NULL),
        'grouping' => 'contact-fields',
      ),
      'civicrm_phone' => array(
        'dao' => 'CRM_Core_DAO_Phone',
        'fields' => array('phone' => NULL),
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

    $this->addPhoneFromClause();
    $this->addAddressFromClause();
  }

  function where() {
    $clauses = array();
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

  function orderBy() {
    $this->_orderBy = " ORDER BY {$this->_aliases['civicrm_contact']}.sort_name ASC, {$this->_aliases['civicrm_subscription_history']}.date DESC";
  }

  function postProcess() {

    $this->beginPostProcess();

    // get the acl clauses built before we assemble the query
    $this->buildACLClause($this->_aliases['civicrm_contact']);
    $sql = $this->buildQuery(TRUE);

    $rows = array();
    $this->buildRows($sql, $rows);

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

      $entryFound = $this->alterDisplayAddressFields($row, $rows, $rowNum, 'org.cpehn.subscriptionhistory/subscriptionhistory', 'List subscription history') ? TRUE : $entryFound;

      if (!$entryFound) {
        break;
      }
    }
  }
}
