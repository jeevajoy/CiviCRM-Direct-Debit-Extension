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

{literal}
<style type="text/css">
<!--
.batching_report_directdebit {
    font-size:13px;
    font-family : arial,sans-serif;
}
-->
</style> 
{/literal}

    {if $section eq 1}
    <div class="crm-block crm-content-block crm-report-layoutGraph-form-block">
        {*include the graph*}
        {include file="CRM/Report/Form/Layout/Graph.tpl"}
    </div>
    {elseif $section eq 2}
    <div class="crm-block crm-content-block crm-report-layoutTable-form-block">
        {*include the table layout*}
        {include file="CRM/Report/Form/Layout/Table.tpl"}
	</div>
    {else}
    <div class="crm-block crm-form-block crm-report-field-form-block">
        {include file="CRM/Report/Form/Fields.tpl"}
    </div>
    
    <div class="crm-block crm-content-block crm-report-form-block">
        {*include actions*}
        
        {if !$printOnly} {* NO print section starts *}

            {* build the print pdf buttons *}
            {if $rows}
                <div class="crm-tasks">
                {assign var=print value="_qf_"|cat:$form.formName|cat:"_submit_print"}
                {assign var=pdf   value="_qf_"|cat:$form.formName|cat:"_submit_pdf"}
                {assign var=csv   value="_qf_"|cat:$form.formName|cat:"_submit_csv"}
                {assign var=group value="_qf_"|cat:$form.formName|cat:"_submit_group"}
                {assign var=chart value="_qf_"|cat:$form.formName|cat:"_submit_chart"}
                <table style="border:0;">
                    <tr>
                        <td>
                            <table class="form-layout-compressed">
                                <tr>
                                    <td>{$form.$print.html}&nbsp;&nbsp;</td>
                                    <td>{$form.$pdf.html}&nbsp;&nbsp;</td>
                                    <td>{$form.$csv.html}&nbsp;&nbsp;</td>
                                    {if $instanceUrl}
                                        <td>&nbsp;&nbsp;&raquo;&nbsp;<a href="{$instanceUrl}">{ts}Existing report(s) from this template{/ts}</a></td>
                                    {/if}
                                </tr>
                            </table>
                        </td>
                        <td>
                            <table class="form-layout-compressed" align="right">                        
                                {if $chartSupported}
                                    <tr>
                                        <td>{$form.charts.html|crmReplace:class:big}</td>
                                        <td align="right">{$form.$chart.html}</td>
                                    </tr>
                                {/if}
                                {if $form.groups}
                                    <tr>
                                        <td>{$form.groups.html|crmReplace:class:big}</td>
                                        <td align="right">{$form.$group.html}</td>
                                    </tr>
                                {/if}
                            </table>
                        </td>
                    </tr>
                </table>
                </div>
            {/if}
        
        {/if} {* NO print section ends *}
        

        {*Statistics at the Top of the page*}
        {include file="CRM/Report/Form/Statistics.tpl" top=true}
    
        {*include the graph*}
        {include file="CRM/Report/Form/Layout/Graph.tpl"}
    
        {*include the table layout*}
        {include file="CRM/Report/Form/Layout/Table.tpl"}    
    	<br />
        {*Statistics at the bottom of the page*}
        {include file="CRM/Report/Form/Statistics.tpl" bottom=true}    
    
        {include file="CRM/Report/Form/ErrorMessage.tpl"}
    </div>
    {/if}

{if !$printOnly}
    {assign var="batch_ids" value=""}
    {foreach from=$statistics.batch_array item=item key=key}
    {if $batch_ids != null}
        {assign var="batch_ids" value="`$batch_ids`,"}
    {/if}
    {assign var="batch_ids" value="`$batch_ids``$item`"}
    {/foreach}
    <div class="batching_report_directdebit">
      <div class="crm-block crm-content-block crm-report-form-block"><div class="crm-tasks">
      <table>
       <!-- <tr>
        <td valign="middle">
            <table height="100%">
            <tr><td align="center">
              <b><font size="3px"><a href="{crmURL p="civicrm/member" q="reset=1&action=export_batch_to_csv&batch_ids=$batch_ids"}">Click here</a> to Export to CSV</font></b>
            </td></tr>
            </table>          
        </td>
        </tr> -->
        <tr>
        <td valign="middle">
            <table height="100%">
            <tr><td align="center">
              <b><font size="3px"><a href="{crmURL p="civicrm/member" q="reset=1&action=export_to_csv&batch_ids=$batch_ids"}">Click here</a> to Export Contact Details</font></b>
            </td></tr>
            </table>          
        </td>
        </tr>
      </table>
      </div>
    </div>    
{/if}

