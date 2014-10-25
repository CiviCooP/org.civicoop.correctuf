<?php

/**
 * Contact.CorrectUf API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_contact_correctuf_spec(&$spec) {
}

/**
 * Contact.CorrectUf API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_contact_correctuf($params) {
  $returnValues = array();
  
  $toBeRemoved = array();
  $toBeUpdated = array();
  $toBeInserted = array();
  
  //first find current records in UF table
  $uf_dao = CRM_Core_DAO::executeQuery("SELECT * FROM `civicrm_uf_match` AND `domain_id` = 1");
  while ($uf_dao->fetch()) {
    $email_dao = CRM_Core_DAO::executeQuery("SELECT * FROM `civicrm_email` WHERE `contact_id` = %1 ORDER BY `is_primary` DESC", array(1 => array($uf_dao->contact_id, 'Integer')));
    $uid = false;
    $uf_name = false;
    while($email_dao->fetch()) {
      //check if e-mail exist in drupal user table
      $user = user_load_by_mail($email_dao->email);
      if ($user) {
        $uid = $user->uid;
        $uf_name = $user->name;
        break;
      }
    }
    
    if (!$uid) {
      $toBeRemoved[] = $uf_dao->id;
    } elseif ($uid && $uf_dao->uf_id != $uid) {
      $toBeUpdated[$uf_dao->id]['id'] = $uf_dao->id;
      $toBeUpdated[$uf_dao->id]['contact_id'] = $uf_dao->contact_id;
      $toBeUpdated[$uf_dao->id]['uf_id'] = $uid;
      $toBeUpdated[$uf_dao->id]['uf_name'] = $uf_name;
    }
  }
  
  //now check all drupal users who are not in the UF table
  $users = entity_load('user');
  foreach($users as $uid => $user) {
    //check wether a link exist and wether that link is valid
    $exist = false;
    $uf_dao = CRM_Core_DAO::executeQuery("SELECT * FROM `civicrm_uf_match` WHERE `domain_id` = 1 AND `uf_id` = %1", array(1 => array($uid, 'Integer')));
    if ($uf_dao->fetch()) {
      $exist = $uf_dao->id;
    }
    
    //check wether we can find a contact with the same e-mail address
    $email_dao = CRM_Core_DAO::executeQuery("SELECT * FROM `civicrm_email` WHERE `email` = %1", array(1=>array($user->mail)));
    if ($email_dao->fetch()) {
      //we did find a matching contact
      if ($exist) {
        $toBeUpdated[$exist]['id'] = $exist;
        $toBeUpdated[$exist]['contact_id'] = $email_dao->contact_id;
        $toBeUpdated[$exist]['uf_id'] = $uid;
        $toBeUpdated[$exist]['uf_name'] = $user->name;
      } else {
        $toBeInserted[]['contact_id'] = $email_dao->contact_id;
        $toBeInserted[]['uf_id'] = $uid;
        $toBeInserted[]['uf_name'] = $user->name;
      }
    }
  }
  
    //remove 
  CRM_Core_DAO::executeQuery("DELETE FROM `civicrm_uf_match` WHERE `id` IN (".implode(",", $toBeRemoved));
  //update
  foreach($toBeUpdated as $update) {
    $sql = "UPDATE `civicrm_uf_match` SET `uf_id` = %1, `uf_name` = %2, `contact_id` = %3 WHERE `id` = %4";
    $params[1] = array($update['uf_id'], 'Integer');
    $params[2] = array($update['uf_name'], 'String');
    $params[3] = array($update['contact_id'], 'Integer');
    $params[4] = array($update['id'], 'Integer');
    CRM_Core_DAO::executeQuery($sql, $params);
  }
  
  if (count($toBeInserted)) {
    $sql = "INSERT INTO `civicrm_uf_match (`domain_id`, `uf_id`, `uf_name`,`contact_id`) VALUES ";
    $count = 0;
    foreach($toBeInserted as $insert) {
      if ($count) {
        $sql .= ", ";
      }
      $sql .= "('1','".$insert['uf_id']."', '".$insert['uf_name']."', '".$insert['contact_id']."')";
      $count ++;
    }
    CRM_Core_DAO::executeQuery($sql);
  }
  
  $returnValues[] = array('deleteCount' => count($toBeRemoved), 'updateCount' => count($toBeUpdated), 'insertCount' => count($toBeInserted));
  
  return civicrm_api3_create_success($returnValues, $params, 'Contact', 'Correctuf');
}

