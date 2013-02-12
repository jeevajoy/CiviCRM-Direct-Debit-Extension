<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 2.2                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2009                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007.                                       |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License along with this program; if not, contact CiviCRM LLC       |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2009
 * $Id$
 *
 */
 
require_once '../civicrm.config.php';
require_once 'CRM/Core/Config.php';

$debug = false;

// Include the Direct Debit Settings file
require_once '../../civicrm_direct_debit/direct_debit_settings.php';

require_once 'CRM/Utils/Request.php';

function ProcessRecurringContributions( ) {
    global $debug;
    
    $config =& CRM_Core_Config::singleton();
          
    require_once 'CRM/Utils/System.php';
    require_once 'CRM/Utils/Hook.php';
  
    //$dtCurrentDay = date("Ymd", mktime(0, 0, 0, date("m") , date("d") , date("Y")));
    //$dtCurrentDayStart  = $dtCurrentDay."000000"; 
    //$dtCurrentDayEnd = $dtCurrentDay."235959";
    
    $dtCurrentDayStart = date("YmdHis", mktime(0, 0, 0, date("m") , 1, date("Y")));
    $dtCurrentDayEnd = date('YmdHis',strtotime('-1 second',strtotime('+1 month',strtotime(date('m').'/01/'.date('Y').' 00:00:00')))); 
      
    //$dtCurrentDayStart = '20111201000000';
    //$dtCurrentDayEnd = '20111231235959';   
      
    $sql = "SELECT * FROM civicrm_contribution_recur ccr 
                    WHERE ccr.end_date IS NULL AND ccr.next_sched_contribution >= {$dtCurrentDayStart} AND ccr.next_sched_contribution <= {$dtCurrentDayEnd}";
                    //AND cm.status_id = 3
    //echo $sql;exit;                                          
    $dao = CRM_Core_DAO::executeQuery( $sql );
    $count = 0;
    while($dao->fetch()) {
            
        $contact_id                 = $dao->contact_id;
        $hash                       = md5(uniqid(rand(), true)); 
        $total_amount               = $dao->amount;
        $contribution_recur_id      = $dao->id;
        $contribution_type_id       = 1;
        
        $source                     = "Recurring Contribution from Contact Id - ".$contact_id;
        
        $receive_date = date("YmdHis");
        
        //echo $receive_date;exit;
        $contribution_status_id = 2;
        
        require_once 'CRM/Contribute/PseudoConstant.php';
        
        $paymentInstruments = CRM_Contribute_PseudoConstant::paymentInstrument();
        
        $payment_instrument_id = CIVICRM_DIRECT_DEBIT_PAYMENT_INSTRUMENT_ID;
        
        require_once 'api/v2/Contribution.php';
        $params = array(
                'contact_id'             => $contact_id,
                'receive_date'           => $receive_date,
                'total_amount'           => $total_amount,
                'payment_instrument_id'  => $payment_instrument_id,
                'trxn_id'                => $hash,
                'invoice_id'             => $hash,
                'source'                 => $source,
                'contribution_status_id' => $contribution_status_id,
                'contribution_type_id'   => $contribution_type_id,
                'contribution_recur_id'  => $contribution_recur_id,
                'contribution_page_id'   => $entity_id
                );
        //print_r ($params);        
        $contributionArray =& civicrm_contribution_add($params);
        //print_r ($contributionArray);echo "<br />";
        $contribution_id = $contributionArray['id'];
    
        $mem_end_date = $member_dao->end_date;
        $temp_date = strtotime($dao->next_sched_contribution);
        
        $next_collectionDate = strtotime ( "+$dao->frequency_interval $dao->frequency_unit" , $temp_date ) ;
        $next_collectionDate = date ( 'YmdHis' , $next_collectionDate );
        //$next_collectionDate = '20120220000000';
        
        $update_sql = "UPDATE civicrm_contribution_recur SET next_sched_contribution = '$next_collectionDate' WHERE id = '".$dao->id."'";
        CRM_Core_DAO::executeQuery( $update_sql );
       
        $mandate_id = $dao->processor_id;
        
        $activityDate = date("YmdHis", mktime(0, 0, 0, date("m") , 20 , date("Y")));
        require_once 'api/v2/Activity.php';
        $params = array(
             'activity_type_id' => CIVICRM_DIRECT_DEBIT_STANDARD_PAYMENT_ACTIVITY_ID ,
             'source_contact_id' => $contact_id,
             'target_contact_id' => $contact_id,
             'subject' => "Donation, Mandate Id - ".$mandate_id,
             'status_id' => 1,
             'activity_date_time' => $activityDate 
            );
        $act = civicrm_activity_create($params);
        //print_r ($act);echo "<br />";
        $activity_id = $act['id'];
        if ($mandate_id && $activity_id) {      
            $update_sql = "INSERT INTO civicrm_value_activity_bank_relationship SET entity_id = '$activity_id', bank_id = '$mandate_id'";
            CRM_Core_DAO::executeQuery( $update_sql );
            
            $update_sql = "INSERT INTO civicrm_value_direct_debit_details SET activity_id = '$activity_id' , entity_id = '$contribution_id' , mandate_id = '$mandate_id'";
            CRM_Core_DAO::executeQuery( $update_sql );
        }
        
        $url = CRM_Utils_System::url( 'civicrm/contact/view', "reset=1&cid={$contact_id}"  );
        $contactArray[] = " Contact ID: " . $contact_id . "  - <a href =\"$url\"> ". $contact_id . " </a> ";
        //echo "<hr />";
        $count++;
    }
    echo "Contributions Created: ".$count."<br />";
    if (count($contactArray) > 0)
        echo $status = implode( '<br/>', $contactArray );
}    

ProcessRecurringContributions();

?>

<FORM><INPUT TYPE="BUTTON" VALUE="Back" ONCLICK="history.go(-1)"></FORM>
