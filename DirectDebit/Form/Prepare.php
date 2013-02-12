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

require_once 'CRM/Core/Form.php';
require_once 'CRM/Core/Session.php';

/**
 * This class provides the functionality to delete a group of
 * contacts. This class provides functionality for the actual
 * addition of contacts to groups.
 */

class DirectDebit_Form_Prepare extends CRM_Core_Form {

    /**
     * build all the data structures needed to build the form
     *
     * @return void
     * @access public
     */
	function preProcess()
  {	
        parent::preProcess( );
		    
	}
	
    /**
     * Build the form
     *
     * @access public
     * @return void
     */
    function buildQuickForm( ) {
        
	    CRM_Utils_System::setTitle('Prepare for Direct Debit Run');
        
        $this->addDate( 'start_date', ts('Start Date'), true, array('formatType' => 'activityDate') );
        
        $this->addDate( 'end_date', ts('End Date'), true, array('formatType' => 'activityDate') );
                           
		$this->addButtons(array( 
                                    array ( 'type'      => 'next', 
                                            'name'      => ts('Next'), 
                                            'spacing'   => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', 
                                            'isDefault' => false   ), 
                                    //array ( 'type'      => 'cancel', 
                                    //        'name'      => ts('Cancel') ), 
                                    ) 
                              );
    }
    	
    /**
     * process the form after the input has been submitted and validated
     *
     * @access public
     * @return None
     */
    public function postProcess() {

		$params = $this->controller->exportValues( );
        
        
        $dateArray1 = @explode('/' , $params['start_date']);
        $dtStartDay = $dateArray1[2].$dateArray1[0].$dateArray1[1].'000000'; 
        //$dtStartDay = date("YmdHis", strtotime($dateArray1[1].'/'.$dateArray1[0].'/'.$dateArray1[2].' 00:00:00'));
        
        $dateArray2 = @explode('/' , $params['end_date']);
        $dtEndDay = $dateArray2[2].$dateArray2[0].$dateArray2[1].'235959';
        //$dtEndDay = date("YmdHis", strtotime($dateArray2[1].'/'.$dateArray2[0].'/'.$dateArray2[2].' 23:59:59'));
        
        CRM_Utils_System::redirect(CRM_Utils_System::url( 'civicrm/directdebit/process', 'action=step1&start_date='.$dtStartDay.'&end_date='.$dtEndDay.'&reset=1'));
        CRM_Utils_System::civiExit( );
        
	}//end of function
}
