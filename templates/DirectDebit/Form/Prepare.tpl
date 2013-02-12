{*
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
*}
<input id="campaign_selected_id" name="campaign_selected_id" type="hidden" value="{$campaign_id}" />

<div class="crm-block crm-form-block crm-export-form-block">
<div class="help">
<p>{ts}Select the dates for the Direct Debit Run.{/ts}</p>
</div>
<table class="form-layout">
     <tr>
		<td>
			<table class="form-layout">
				<tr>
			       <td class="label">{$form.start_date.label}</td>
			       <td>{include file="CRM/common/jcalendar.tpl" elementName=start_date}</td>
		        </tr>
                <tr>
			       <td class="label">{$form.end_date.label}</td>
			       <td>{include file="CRM/common/jcalendar.tpl" elementName=end_date}</td>
		        </tr>
			</table>
		</td>
     </tr>
</table>
<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="top"}
</div>

</div>
{literal}
<script type="text/javascript">
cj(function() {
   cj().crmaccordions(); 
});
</script>
{/literal}

<script type="text/javascript" src="{$config->resourceBase}js/rest.js"></script>{literal}

<script type="text/javascript">
{/literal}{if $config->cleanURL eq 0}{literal}
var campaignUrl = '{/literal}{$config->userFrameworkBaseURL}{literal}index.php?q=civicrm/ajax/rest&className=CRM_Batch_Page_AJAX&fnName=getCampaignList&json=1&limit=25';
{/literal}{else}{literal}
var campaignUrl = '{/literal}{$config->userFrameworkBaseURL}{literal}civicrm/ajax/rest&className=CRM_Batch_Page_AJAX&fnName=getCampaignList&json=1&limit=25';
{/literal}{/if}{literal}
//var campaignUrl = {/literal}"{crmURL p='civicrm/ajax/rest&className=CRM_Segment_BAO_HierarchyLevel&fnName=getCampaignList&json=1&limit=25'}"{literal};
//alert(campaignUrl);
var contactElement = '#campaign_id';
var contactHiddenElement = 'input[id=campaign_selected_id]';
cj( contactElement ).autocomplete( campaignUrl, { 
    selectFirst : false, matchContains: true, minChars: 1
}).result( function(event, data, formatted) {
    cj( contactHiddenElement ).val(data[1]);
}).focus( );

 
$(document).ready(function()
{
    $("input[type='text']:first", document.forms[0]).focus();
    $('#expected_value').blur(function()
    {
        //alert('asdasd');
        //alert($('#expected_value').val());
        var temp = formatCurrency($('#expected_value').val());
        $('#expected_value').val(temp)
        //alert(temp);
        //$('#expected_value').formatCurrency();
    });
    
    $('#payment_instrument_id').change(function() {
      var paymentInstrumentName = $('#payment_instrument_id :selected').text()
        if(paymentInstrumentName.toLowerCase() == 'voucher') {
            $('#exclude_from_posting').attr('checked', true);
        } else {
            $('#exclude_from_posting').attr('checked', false);
        } 
     });  
    
});

function formatCurrency(num) {
    num = isNaN(num) || num === '' || num === null ? 0.00 : num;
    return parseFloat(num).toFixed(2);
}

//$('#expected_value').priceFormat({
//    prefix: '',
//    thousandsSeparator: ''
//}); 
 
//$('#expected_value').blur(function() {
  //var expectedValue = $('#expected_value').val();
  //alert(expectedValue);
  //if(isNaN(expectedValue)) {
  //    alert('Please Enter Valid Amount');
  //    $('#expected_value').val('')
  //    $('#expected_value').focus();
  //    //return false;  
  //}
  //else if(expectedValue.indexOf(".") == -1 && expectedValue != ''){
  //   $('#expected_value').val(expectedValue + '.00'); 	
	//alert(expectedValue.indexOf("."));
      
  //}
  //alert('asdasd');
  //$('.expected_value').formatCurrency();	
//});
</script>
{/literal}
