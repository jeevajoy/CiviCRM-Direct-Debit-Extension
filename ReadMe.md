06/02/2013 

==========================================
CiviCRM Direct Debit Extension Install File  
==========================================

Requirements
---------------------------------------

This Extension requires CiviCRM 4.2.x or greater

Installation Instructions
---------------------------------------

To install the CiviCRM Direct Debit Extension, move the 
`nfpservices.co.uk.module.civicrm_direct_debit` directory to your CiviCRM custom Extenison  directory.

Set Direct Debit Mode (Paper-based or Paperless)  
------------------------------------------------

To set the direct debit mode, open 'direct_debit_settings.php' file under civicrm_direct_debit directory using any text editor.

On line number 38, set 1 if Paperless or 0 if Paper-based direct debit 

In our example it will be paperless

define( 'CIVICRM_DIRECT_DEBIT_IS_PAPERLESS',   '1' );


Create new Payment Instrument Direct Debit
---------------------------------------

Navigate to Administer > CiviContribute > Payment Intruments and add a new payment instrument 'Direct Debit'.

Now open 'direct_debit_settings.php' file under civicrm_direct_debit directory using any text editor and assign the payment instrument value on line number 44 .

In our example it will be paperless

define( 'CIVICRM_DIRECT_DEBIT_PAYMENT_INSTRUMENT_ID', 6 );

The value of the payment instrument, can be found in the second column in Payment Instruments list page (Administer > CiviContribute > Payment Intruments).

Create new DD Letter Template
--------------------------------------- 
Navigate to Mailings > Message Templates > Add Template.

Copy the content from DD Letter Template.html and add in the template.

Note the newly created Message Template ID and set in 

define( 'CIVICRM_DIRECT_DEBIT_SETUP_LETTER_TEMPLATE_ID', '60' ); in direct_debit_settings.php

 
Register the Direct Debit report
---------------------------------------

The CiviCRM Direct Debit Extension comes with a report.  You need to register this report in CiviCRM's report registry.

Navigate to Administer > CiviReport > Register Report.

The URL for this report should be: DirectDebit/Report/Form/Contribute/Batching.php

The class name is: DirectDebit_Report_Form_Contribute_Batching

A sensible title would be: Direct Debit Batch Report

You should add it as part of the CiviContribute component.

Include report link in Navigation Menu
---------------------------------------

To include the report link in the navigation menu, we need to find the URL of the report instance. For this, first we need to create the report instance.

To create the report instance, navigate to Reports > Create Reports from Templates.

Now you should be able to see `Direct Debit Batch Report` link under Contribution Report Templates.

Clicking the link will show you the report template. Now click 'Preview Report' button.  

Under Create Report, give the report title, description, etc. and give the permission `access CiviContribute` and press `Create Report` button.

To find the URL for the report instance, again navigate to Reports > Create Reports from Templates.

Now you should be able to see `Existing Report(s)` link under 'Direct Debit Batch Report'.

Clicking `Existing Report(s)` link will show the Report created from the template: Direct Debit Batch Report.

Click the report link and in the next page, you should be able to see the URL for the report instance in the browser's address bar.  

For example : http://[your_root_url]/index.php?q=civicrm/report/instance/29&reset=1

Note down the URL. We dont need the full URL, copy only from civicrm. ie. 'civicrm/report/instance/29&reset=1'.

Now open 'direct_debit_settings.php' file under civicrm_direct_debit directory using any text editor.

On line number 48, replace the value with your report instance URL 

In our example it will be

define( 'CIVICRM_DIRECT_DEBIT_BATCH_REPORT_URL',   'civicrm/report/instance/29&reset=1' );

To include the report link in Navigation Menu, you need to rebuild the menus.

The following URL (replace the relevant parts with your credentials) should rebuild the menus.

[http://[your_root_url]/index.php?q=civicrm/menu/rebuild&reset=1

Now you will be able to see new menu item 'Direct Debit' and four options under it.

1. Create Authorisation File 
2. Process DD 
3. DD - Batch Report



Create Activity Types to process direct debit
-----------------------------------------------------

In  order to process direct stages of direct debit signup and processing, you need to create a list of activity types

Navigate to Administer > Option Lists > Activity Types and add the following activity types 

1. New DD SignUp
2. DD Awaiting Signed Declaration
3. DD Authorization Required
4. DD First Collection
5. DD Standard Payment
6. DD Final Payment

Now open 'direct_debit_settings.php' file under civicrm_direct_debit directory using any text editor.

From line number 51 through 66, replace the value with the value of the activity types created above.

The value of the activity types, can be found in the second column in Activity Type list page (Administer > Option Lists > Activity Types).

How to Use 
---------------------------------------

If all has gone to plan, users will be able to contribute or signup for membership by paying by Direct Debit. They will be able to give their bank details in the Direct Debit SignUp Form in Online Contribution Page.

The bank details will be saved as custom data again contact as 'Direct Debit Mandate'.

Whenever a new user signup for direct debit, an activity of type 'New DD SignUp' will be created with status 'Completed'.

And if the Direct Debit mode is set as paperless, an activity of type 'DD Authorization Required' will be created with status 'Pending' and if paper-based, an activity of type 'DD Awaiting Signed Declaration' will be created with status 'Pending'.

In paper-based, you need to collect signed declaration from the user and edit the activity (of type 'DD Awaiting Signed Declaration') and change the status to 'Completed'. Now an activity of type 'DD Authorization Required' will be created with status 'Pending'.   

To create the authorisation(lodgement) file(s), click 'Create Authorisation File' link under 'Direct Debit' menu. This process will pick up all the activities of type 'DD Authorization Required' and will produce authorisation file for all the associated mandates.

The authorisation file will be a single file, which will have the details of all the mandates which are associated with activity of type 'DD Authorization Required' during that time. And the authorisation file will be saved against all the mandate which are authorised.   

Also direct debit setup letters will be generated for each mandate and will be saved against the associated contact under tab 'Direct Debit Files'.

Now for all the mandates which are authorised, a new activity of type 'DD First Collection' with status 'Pending' will be created.

To process the direct debit, click 'Process DD' link under 'Direct Debit' menu. This process will pick up all the activities of type 'DD First Collection' and the valid contributions will be displayed in a summary screen, which can be added to a direct debit batch.

For all the subsequent collections, all the activities of type 'DD First Collection' and 'DD Standard Payment' will be processed and can be added to batch.

After adding the contributions to the direct debit batch, you will be redirected to the batch summary page, which you will be able to create the BACS collection file for all the contributions in the batch.

Direct Debit batches can also be viewed by clicking 'DD - Batch Report' under 'Direct Debit' menu.       
                                                            
Contact Information
---------------------------------------

All feedback and comments of a technical nature (including support questions)
and for all other comments you can reach me at the following e-mail address. Please
include "CiviCRM Direct Debit Extension" somewhere in the subject.

jag AT millertech.co.uk

License Information
---------------------------------------

Copyright (C) Miller Technology 2010