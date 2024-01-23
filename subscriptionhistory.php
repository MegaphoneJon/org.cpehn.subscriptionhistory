<?php

require_once 'subscriptionhistory.civix.php';

/**
 * Implementation of hook_civicrm_config
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function subscriptionhistory_civicrm_config(&$config) {
  _subscriptionhistory_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_install
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function subscriptionhistory_civicrm_install() {
  return _subscriptionhistory_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_enable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function subscriptionhistory_civicrm_enable() {
  return _subscriptionhistory_civix_civicrm_enable();
}
