<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 3.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2010                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2010
 * $Id$
 *
 */

require_once 'CRM/Core/DAO.php';
require_once 'CRM/Core/Error.php';
require_once 'CRM/Utils/Array.php';
require_once 'CRM/Utils/Date.php';
require_once 'DirectDebit/Utils/Hook.php';

class DirectDebit_Utils_Batching {

    /**
     * How long a positive Gift Aid declaration is valid for under HMRC rules (years).
     */
    const DECLARATION_LIFETIME = 3;

    /**
     * Get Gift Aid declaration record for Individual.
     *
     * @param int    $contactID - the Individual for whom we retrieve declaration
     * @param date   $date      - date for which we retrieve declaration (in ISO date format)
     *							- e.g. the date for which you would like to check if the contact has a valid
* 								  declaration
     * @return array            - declaration record as associative array,
     *                            else empty array.
     * @access public
     * @static
     */
    static function getDeclaration( $contactID, $date = null , $contributionID) {

        if ( is_null($date) ) {
            $date = date('Y-m-d H:i:s');
        }

        // Get current declaration: start_date in past, end_date in future or null
        // - if > 1, pick latest end_date
        $currentDeclaration = array();
        $sql = "
        SELECT *
        FROM  civicrm_value_direct_debit_details dd JOIN civicrm_value_bank_details bd ON dd.mandate_id = bd.id  
        WHERE dd.entity_id = %1
        ";
        $sqlParams = array( 1 => array($contributionID, 'Integer'),
                            );
        // allow query to be modified via hook
        //DirectDebit_Utils_Hook::alterDeclarationQuery( $sql, $sqlParams );

        $dao = CRM_Core_DAO::executeQuery( $sql, $sqlParams );
        if ( $dao->fetch() ) {
            $currentDeclaration['id'] = $dao->id;
            $currentDeclaration['mandate_id'] = $dao->mandate_id;
            $currentDeclaration['entity_id'] = $dao->entity_id;
            $currentDeclaration['eligible_for_direct_debit'] = 1;
        }
        //CRM_Core_Error::debug('currentDeclaration', $currentDeclaration);
        return $currentDeclaration;
    }

    static function isEligibleForGiftAid( $contactID, $date = null, $contributionID = null ) {
        $declaration = self::getDeclaration( $contactID, $date , $contributionID);
        $isEligible  = ( $declaration['eligible_for_direct_debit'] == 1 );
        // hook can alter the eligibility if needed
        //DirectDebit_Utils_Hook::giftAidEligible( $isEligible, $contactID, $date, $contributionID );
        
        return $isEligible;
    }

    /**
     * Create / update Gift Aid declaration records on Individual when
     * "Eligible for Gift Aid" field on Contribution is updated.
     * See http://wiki.civicrm.org/confluence/display/CRM/Gift+aid+implementation
     *
     * TODO change arguments to single $param array
     * @param array  $params    - fields to store in declaration:
     *               - entity_id:  the Individual for whom we will create/update declaration
     *               - eligible_for_gift_aid: 1 for positive declaration, 0 for negative
     *               - start_date: start date of declaration (in ISO date format) 
     *               - end_date:   end date of declaration (in ISO date format) 
     *
     * @return array   TODO         
     * @access public
     * @static
     */
    static function setDeclaration( $params ) {

        if ( !CRM_Utils_Array::value('entity_id', $params) ) {
            return( array(
                'is_error' => 1,
                'error_message' => 'entity_id is required',
            ) );
        }

        // Retrieve existing declarations for this user.
        $currentDeclaration = GiftAid_Utils_GiftAid::getDeclaration($params['entity_id'], $params['start_date']);

        // Get future declarations: start_date in future, end_date in future or null
        // - if > 1, pick earliest start_date
        $futureDeclaration = array();
        $sql = "
        SELECT id, eligible_for_gift_aid, start_date, end_date
        FROM   civicrm_value_gift_aid_declaration
        WHERE  entity_id = %1 AND start_date > %2 AND (end_date > %2 OR end_date IS NULL)
        ORDER BY start_date";
        $dao = CRM_Core_DAO::executeQuery( $sql, array(
            1 => array($params['entity_id'], 'Integer'),
            2 => array(CRM_Utils_Date::isoToMysql($params['start_date']), 'Timestamp'),
        ) );
        if ( $dao->fetch() ) {
            $futureDeclaration['id'] = $dao->id;
            $futureDeclaration['eligible_for_gift_aid'] = $dao->eligible_for_gift_aid;
            $futureDeclaration['start_date'] = $dao->start_date;
            $futureDeclaration['end_date'] = $dao->end_date;
        }
       #CRM_Core_Error::debug('futureDeclaration', $futureDeclaration);

        $specifiedEndTimestamp = null;
        if ( CRM_Utils_Array::value('end_date', $params) ) {
            $specifiedEndTimestamp = strtotime( CRM_Utils_Array::value('end_date', $params) );
        }

        // Calculate new_end_date for negative declaration
        // - new_end_date =
        //   if end_date specified then (specified end_date)
        //   else (start_date of first future declaration if any, else null)
        $futureTimestamp = null;
        if ( CRM_Utils_Array::value('start_date', $futureDeclaration) ) {
            $futureTimestamp = strtotime( CRM_Utils_Array::value('start_date', $futureDeclaration) );
        }

        if ( $specifiedEndTimestamp ) {
            $endTimestamp = $specifiedEndTimestamp;
        } else if ( $futureTimestamp ) {
            $endTimestamp = $futureTimestamp;
        } else {
            $endTimestamp = null;
        }

        if ( $params['eligible_for_gift_aid'] == 1 ) {

            if ( !$currentDeclaration ) {
                // There is no current declaration so create new.
                GiftAid_Utils_GiftAid::_insertDeclaration( $params, $endTimestamp );

            } else if ( $currentDeclaration['eligible_for_gift_aid'] == 1 ) {
                //   - if current positive, extend its end_date to new_end_date.
                $updateParams = array(
                                      'id' => $currentDeclaration['id'],
                                      'end_date' => date('YmdHis', $endTimestamp),
                                      );
                GiftAid_Utils_GiftAid::_updateDeclaration( $updateParams );

            } else if ( $currentDeclaration['eligible_for_gift_aid'] == 0 ) {
                //   - if current negative, set its end_date to now and create new ending new_end_date.
                $updateParams = array(
                                      'id' => $currentDeclaration['id'],
                                      'end_date' => CRM_Utils_Date::isoToMysql($params['start_date']),
                                      );
                GiftAid_Utils_GiftAid::_updateDeclaration( $updateParams );
                GiftAid_Utils_GiftAid::_insertDeclaration( $params, $endTimestamp );
            }

        } else if ( $params['eligible_for_gift_aid'] == 0 ) {

            if ( !$currentDeclaration ) {
                // There is no current declaration so create new.
                GiftAid_Utils_GiftAid::_insertDeclaration( $params, $endTimestamp );

            } else if ( $currentDeclaration['eligible_for_gift_aid'] == 1 ) {
                //   - if current positive, set its end_date to now and create new ending new_end_date.
                $updateParams = array(
                                      'id' => $currentDeclaration['id'],
                                      'end_date' => CRM_Utils_Date::isoToMysql($params['start_date']),
                                      );
                GiftAid_Utils_GiftAid::_updateDeclaration( $updateParams );
                GiftAid_Utils_GiftAid::_insertDeclaration( $params, $endTimestamp );
            }
            //   - if current negative, leave as is.
        }

        return array (
            'is_error' => 0,
            // TODO 'inserted' => array(id => A, entity_id = B, ...),
            // TODO 'updated'  => array(id => A, entity_id = B, ...),
        );
    }

    /*
     * Private helper function for setDeclaration
     * - update a declaration record.
     */
    static function _updateDeclaration( $params ) {
        // Update (currently we only need to update end_date but can make generic)
        // $params['end_date'] should by in date('YmdHis') format
        $sql = "
        UPDATE civicrm_value_gift_aid_declaration
        SET    end_date = %1
        WHERE  id = %2";
        $dao = CRM_Core_DAO::executeQuery( $sql, array(
            1 => array($params['end_date'], 'Timestamp'),
            2 => array($params['id'], 'Integer'),
        ) );
    }

    /*
     * Private helper function for setDeclaration
     * - insert a declaration record.
     */
    static function _insertDeclaration( $params, $endTimestamp ) {
        // Insert
        $sql = "
        INSERT INTO civicrm_value_gift_aid_declaration (entity_id, eligible_for_gift_aid, start_date, end_date, reason_ended, source, notes)
        VALUES (%1, %2, %3, %4, %5, %6, %7)";
        $dao = CRM_Core_DAO::executeQuery( $sql, array(
            1 => array($params['entity_id'], 'Integer'),
            2 => array($params['eligible_for_gift_aid'], 'Integer'),
            3 => array(CRM_Utils_Date::isoToMysql($params['start_date']), 'Timestamp'),
            4 => array(($endTimestamp ? date('YmdHis', $endTimestamp) : ''), 'Timestamp'),
            5 => array(CRM_Utils_Array::value('reason_ended', $params, ''), 'String'),
            6 => array(CRM_Utils_Array::value('source', $params, ''), 'String'),
            7 => array(CRM_Utils_Array::value('notes', $params, ''), 'String'),
        ) );
    }
    
    /*
     * Function to produce the fiscal receipt template for each contribution and return the template 
     * Author : rajesh@millertech.co.uk 
     */
    static function civicrm_direct_debit_civicrm_pageRun_produceSetUpLetter( $mandate_id , $contact_id , $default_template , $return_content = false , $contribution_id , $first_collectionDate) {
    
        //$sql = "SELECT * FROM civicrm_value_bank_details WHERE id = %1";
        //$params  = array( 1 => array( $entity_id , 'Integer' ));
        //$dao = CRM_Core_DAO::executeQuery( $sql, $params );
    
        //$dao->fetch( )
        
        //$contribution_id = $dao->entity_id;
        
        ## Get the contribution details
        //require_once 'CRM/Contribute/DAO/Contribution.php';
        //$contribution_dao =& new CRM_Contribute_DAO_Contribution( );
        //$contribution_dao->get($contribution_id);
        //$contribution_date = $contribution_dao->receive_date;
        
        //$contribution_date = strtotime(date("d/m/Y", strtotime($contribution_date)));
        //$contribution_date = date('mdY', $contribution_date);
        
        //require_once 'CRM/Core/DAO';
        
        $fiscal_template = $default_template;
        
        $date = date('d/m/y');
        
        //$amount = $dao->amount;
        
        require_once 'CRM/Contribute/DAO/Contribution.php';
        $contrib_dao = new CRM_Contribute_DAO_Contribution;
        $contrib_dao->id = $contribution_id;
        $contrib_dao->find(true);
        
        require_once "api/v2/Contact.php";
        $contactParams = array('id' => $contact_id);
        $contact =& civicrm_contact_get($contactParams);
        
        require_once "CRM/Mailing/BAO/Mailing.php";
        $mailing = new CRM_Mailing_BAO_Mailing;
        $mailing->body_text = $fiscal_template;
        $mailing->body_html = $fiscal_template;
        $tokens = $mailing->getTokens();
        
        //print_r ($tokens);exit;
        
        require_once "CRM/Utils/Token.php";
        if ($contact_id) {
            $fiscal_template  = CRM_Utils_Token::replaceContactTokens($fiscal_template, $contact, false, $tokens['html']);
        }
        
        //$address = preg_replace("/\n/","<br>",$dao->address);
        
        $mandate_sql = "SELECT * FROM civicrm_value_bank_details bd WHERE bd.id = %1";
        $mandate_params  = array( 1 => array( $mandate_id , 'Integer' ));
        $mandate_dao = CRM_Core_DAO::executeQuery( $mandate_sql, $mandate_params );
        $mandate_dao->fetch();
        
        $day_of_collection = $_ENV['collectionDayArray'][$mandate_dao->collection_day]." of every month";
        
        //$fiscal_template = str_replace(  '{invoice_number}',        $receipt_number ,               $fiscal_template);
        $fiscal_template = str_replace(  '{invoice_date}',          $date ,                         $fiscal_template);
        $fiscal_template = str_replace(  '{amount}',                $contrib_dao->total_amount ,    $fiscal_template);
        $fiscal_template = str_replace(  '{first_collection_date}', $first_collectionDate ,         $fiscal_template);
        $fiscal_template = str_replace(  '{day_of_collection}',     $day_of_collection ,            $fiscal_template);
        $fiscal_template = str_replace(  '{account_name}',          $mandate_dao->account_name ,    $fiscal_template);
        $fiscal_template = str_replace(  '{account_number}',        $mandate_dao->account_number ,  $fiscal_template);
        $fiscal_template = str_replace(  '{sort_code}',             $mandate_dao->sort_code ,       $fiscal_template);
        
        $final_template = $fiscal_template;
        //$final_template .= "<div STYLE='page-break-after: always'></div>";
        //echo $final_template;exit;
        
        $file_name = "SetUp_Letter_".$contact_id.".pdf";
        $fileContent = self::civicrm_direct_debit_civicrm_pageRun_html2pdf($final_template , $file_name , "external");
        
        require_once("CRM/Core/Config.php");
        $config =& CRM_Core_Config::singleton( );
        
        $csv_path = $config->customFileUploadDir;
        //$csv_path = "sites/default/files/civicrm/custom";
        $filePathName   = "{$csv_path}/{$file_name}";
        
        $handle = fopen($filePathName, 'w');
        file_put_contents($filePathName, $fileContent);
        fclose($handle);
        
        return array('content'=> $final_template , 'file_name' => $file_name );
        
        /*if ($return_content)
            return $final_template;  
        else
            return $file_name;*/
    }
    
    /*
     * Function to produce the fiscal receipt PDF 
     * Author : rajesh@millertech.co.uk 
     */  
    static function civicrm_direct_debit_civicrm_pageRun_html2pdf( $text , $fileName = 'FiscalReceipts.pdf' , $calling = "internal" ) {

        require_once 'packages/dompdf/dompdf_config.inc.php';
        spl_autoload_register('DOMPDF_autoload');
        $dompdf = new DOMPDF( );
    
        $values = array( );
        if ( ! is_array( $text ) ) {
            $values =  array( $text );
        } else {
            $values =& $text;
        }
        $html = "";
        foreach ( $values as $value ) {
            $html .= "{$value}\n";
        }
        
        //echo $html;exit;
        
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        
        $dompdf->load_html( $html );
        $dompdf->set_paper ('a4', 'portrait');
        $dompdf->render( );
        
    		if($calling == "external"){ // like calling from cron job
    			$fileContent = $dompdf->output();
    			return $fileContent;
    		}
    		else{	
    			$dompdf->stream( $fileName );
    		}
    		exit;
    }
    
    
    /*
     * Function to produce the Batch CSV File  
     *  
     */  
    static function civicrm_direct_debit_civicrm_pageRun_produce_csv( $mandate_id , $contact_id , $return_content = false , $transcation_code , $amount = null) {
        
        $bankdetails_sql = "SELECT * FROM civicrm_value_bank_details bd WHERE bd.id = %1";
        $bankdetails_params  = array( 1 => array( $mandate_id , 'Integer' ));
        $bankdetails_dao = CRM_Core_DAO::executeQuery( $bankdetails_sql, $bankdetails_params );
        //$bankdetails_dao->fetch();
        
        if ( $bankdetails_dao->fetch() ) {
            $payment_method = "";
            $source_code = "";
            $csv_line = array();
             $str = "";
            //$csv_string .= "ADATASTART\n";
            //$csv_string .= "PROCESSING DATE=".date("dmy")."\n";
         
	          /*$str .= str_pad($bankdetails_dao->sort_code , 6, "0", STR_PAD_LEFT);
	          $str .= "      ";
            $str .= str_pad($bankdetails_dao->account_number , 8, "0", STR_PAD_LEFT);
            $str .= "      ";
		        $str .= $transcation_code;
		        $str .= "      ";
		        $str .= $amount;
		        $str .= "      ";
            $str .= str_pad($bankdetails_dao->bacs_ref_number , 18, " ", STR_PAD_LEFT);
            $str .= "      ";
            $str .= str_pad($bankdetails_dao->account_name , 20, " ", STR_PAD_LEFT);*/
            
            $str .= '"'.$bankdetails_dao->sort_code.'"';
	          $str .= ',';
            $str .= '"'.$bankdetails_dao->account_number.'"';
            $str .= ',';
            $str .= '"'.$bankdetails_dao->account_name.'"';
            $str .= ',';
		        if ($amount) {
    		        $str .= '"'.$amount.'"';
    		    } else {
    		        $str .= '"0"';
            }
            $str .= ',';
            $str .= '"'.$bankdetails_dao->bacs_ref_number.'"';
            $str .= ',';
            $str .= '"'.(string)$transcation_code.'"';
            
	          $csv_string = $str."\n";
	          //echo $csv_string;exit;
	          //$csv_string .= "ADATAEND\n";
        }
        
        return $csv_string;
    }
    
    function create_authorisation_file() {
    
        // Get the DD Setup Letter template
        require_once("CRM/Core/Config.php");
        $config =& CRM_Core_Config::singleton( );
        
        ## getting DD Setup Letter mail template
        $query = "SELECT * FROM civicrm_msg_template WHERE id =".CIVICRM_DIRECT_DEBIT_SETUP_LETTER_TEMPLATE_ID;
        $dao = CRM_Core_DAO::executeQuery( $query );
      
         if(!$dao->fetch()){
          print("Not able to get Email Template");
          return;
         }
         $default_dd_template = $dao->msg_html;
              
         $activity_sql = "SELECT * FROM civicrm_activity ca WHERE ca.activity_type_id = %1 AND ca.status_id = %2";
         $activity_params  = array( 
                                    1 => array( CIVICRM_DIRECT_DEBIT_AUTHORISAION_REQUIRED_ACTIVITY_ID   , 'Integer' ) ,
                                    2 => array( 1   , 'Integer' )
                                    );
         $activity_dao = CRM_Core_DAO::executeQuery( $activity_sql, $activity_params );
         $count = 0;
         
         $mandateArray = array();
         $pdfletterArray = array();
         $csv_string = "";
         $html = "";
                           
         while($activity_dao->fetch()) {
              
            $activity_id = $activity_dao->id;
           
            $contribution_sql = "SELECT * FROM civicrm_value_direct_debit_details WHERE activity_id = '$activity_id'";
            $contribution_dao = CRM_Core_DAO::executeQuery( $contribution_sql );
            $contribution_dao->fetch();
            $contribution_id = $contribution_dao->entity_id;
            
            require_once 'CRM/Contribute/DAO/Contribution.php';
            $cTypedao =& new CRM_Contribute_DAO_Contribution();
            $cTypedao->id = $contribution_id;
            $cTypedao->find(true);
            
            $sql = "SELECT * FROM civicrm_value_activity_bank_relationship WHERE entity_id = '$activity_id'";
            $dao = CRM_Core_DAO::executeQuery( $sql );
            $dao->fetch();
            $mandate_id = $dao->bank_id;
            
            $mandate_sql = "SELECT * FROM civicrm_value_bank_details bd WHERE bd.id = %1 AND bd.cease_date IS NULL AND bd.suspend_date IS NULL";
            $mandate_params  = array( 1 => array( $mandate_id , 'Integer' ));
            $mandate_dao = CRM_Core_DAO::executeQuery( $mandate_sql, $mandate_params );
            $mandate_dao->fetch();
            
            
            if (!empty($mandate_dao->collection_day))
                $collection_day = $mandate_dao->collection_day;
            else 
                $collection_day = CIVICRM_DIRECT_DEBIT_DEFAULT_COLLECTION_DAY;
            
			
            $first_collectionDate = civicrm_direct_debit_civicrm_pageRun_get_valid_collection_date($collection_day , date("m") , 'F dS, Y' );
            
            // Get transcation code for the contribution, depending on the associated activity type  
            $transcation_code = civicrm_direct_debit_civicrm_pageRun_get_transcation_code( $activity_dao->activity_type_id );
            
            // Create the Authorisation CSV file
            $csv_string .= DirectDebit_Utils_Batching::civicrm_direct_debit_civicrm_pageRun_produce_csv( $mandate_id , $activity_dao->source_contact_id , true , $transcation_code );
            						            
			$mandateArray[$mandate_id] = $activity_dao->source_contact_id;
            
            // Create the Setup letter
            $pdfletterArray = DirectDebit_Utils_Batching::civicrm_direct_debit_civicrm_pageRun_produceSetUpLetter ($mandate_id , $activity_dao->source_contact_id , $default_dd_template , false , $contribution_id , $first_collectionDate);
            
            // Create PDF Setup File Entry for the person
            civicrm_direct_debit_civicrm_pageRun_insert_file_for_contact( $pdfletterArray['file_name'] , $activity_dao->source_contact_id , 'SetUp Letter');
            
            if ($count > 0)
                $html .= "<div STYLE='page-break-after: always'></div>";
                
            $html .= $pdfletterArray['content'];
            			
			
            $update_activity_sql = "UPDATE civicrm_activity SET status_id = '2' WHERE id = '$activity_id'";
            $update_activity_dao = CRM_Core_DAO::executeQuery( $update_activity_sql );
            
            $target_sql = "SELECT * FROM civicrm_activity_target WHERE activity_id = '$activity_id'";
            $target_dao = CRM_Core_DAO::executeQuery( $target_sql );
            $target_dao->fetch();
            
            // Create First Collection date activity
            $activity_label = civicrm_direct_debit_civicrm_post_get_activity_subject(CIVICRM_DIRECT_DEBIT_FIRST_COLLECTION_ACTIVITY_ID , $cTypedao->contribution_type_id);
			
            $subject = $activity_label.", Mandate Id - ".$mandate_id;
			
            $activity_id  = civicrm_direct_debit_civicrm_post_createActivity (CIVICRM_DIRECT_DEBIT_FIRST_COLLECTION_ACTIVITY_ID , $activity_dao->source_contact_id , $target_dao->target_contact_id , $subject , 1 , $first_collectionDate , $mandate_id);
            
            $sql = "UPDATE civicrm_value_direct_debit_details SET activity_id = '$activity_id' WHERE entity_id = '$contribution_id'";
            $dao = CRM_Core_DAO::executeQuery( $sql );
            
            $current_date = date( 'YmdHis' );
             
            // Get date in Mysql Date Format   
			/* Fixed the error in the below line by adding $collection_day on 03112011 (boby).--start-- */
            $first_collectionDate = civicrm_direct_debit_civicrm_pageRun_get_valid_collection_date($collection_day , date("m") , 'YmdHis' );
			/* -----end-----*/
			
            $sql = "UPDATE civicrm_value_bank_details SET start_date = '$first_collectionDate' , authorization_date = '$current_date' WHERE id = '$mandate_id'";			
            $dao = CRM_Core_DAO::executeQuery( $sql );
            
            $contact_id = $activity_dao->source_contact_id;			
            $contact_params = array('contact_id' => $contact_id);
            $contact_details = &civicrm_contact_get( $contact_params );            
            $url = CRM_Utils_System::url( 'civicrm/contact/view', "reset=1&cid={$contact_id}"  );
            $status[] = " Contact ID: " . $contact_id . "  - <a href =\"$url\"> ". $contact_details[$contact_id]['display_name'] . " </a> ";
            
            $count++;
          }
          
          $file_name = "basclodg ".date('M').".txt";
          $csv_path = $config->customFileUploadDir;
          $filePathName   = "{$csv_path}/{$file_name}";
        
          $handle = fopen($filePathName, 'w');
          file_put_contents($filePathName, $csv_string);
          fclose($handle);
          
          while(list($k,$v)=@each($mandateArray)) {
              civicrm_direct_debit_civicrm_pageRun_insert_file_for_mandate( $file_name , $k , $v , 'Authorisation File');    
          }
          
          $referer_page = CRM_Utils_Array::value('HTTP_REFERER', $_SERVER, '');
          
          if ($count == 0) {
              $status = array(ts('No Authorisation file produced, as there are no new Direct Debit Signup'));
          } else {
              $all_letters_file_name = "Set_Up_Letters_".date('YmdHis').".pdf";
              $fileContent = DirectDebit_Utils_Batching::civicrm_direct_debit_civicrm_pageRun_html2pdf( $html , $all_letters_file_name  , "external");
              $filePathName   = "{$csv_path}/{$all_letters_file_name}";
              $handle = fopen($filePathName, 'w');
              
              file_put_contents($filePathName, $fileContent);
              fclose($handle);
              
              $donwloadPath = $config->userFrameworkBaseURL."/sites/default/files/civicrm/custom/".$all_letters_file_name;
              $status[] = ts('Authorisation file(s) produced: %1'       , array(1 => $count));
              $status = array_reverse($status);
              $status[] =  ts("To download all setup letters, please click <a href='".$donwloadPath."' target='_blank'>here</a>
              <!--<br /><div class='messages status'>
              <div class='icon inform-icon'></div>
              Important: Please download the setup lettes, if you want to print them in one go.</div>-->");
          }                
                        
          $status = implode( '<br/>', $status );
          CRM_Core_Session::setStatus( $status );
          header("Location: $referer_page");
          CRM_Utils_System::civiExit( );
    }
    
}
