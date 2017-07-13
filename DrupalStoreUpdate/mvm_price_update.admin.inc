<?php
/**
 * @file
 * Administration Page callbacks for the nmsucontacts Module
 */

/**
 * Form Builder.  Configure nmsucontacts
 *
 * @ingroup forms
 * @see system_settings_form().
 */

function mvm_price_update_admin_settings() {
	$form = array('#cache' => TRUE, '#attributes' => array('enctype' => 'multipart/form-data'));
	$ifilelocation = file_directory_path().'/mvm_price_update/items.txt';
	$pfilelocation = file_directory_path().'/mvm_price_update/prices.txt';
	 if(!$mtime = filemtime($pfilelocation)){
	 	$pfileinfo = 'not set';
	 }
	 else {
	 	$pfileinfo = $pfilelocation.' last updated - '.date('Y-m-d H:i:s',$mtime);
	 }
	 if(!$mtime = filemtime($ifilelocation)){
	 	$ifileinfo = 'not set';
	 }
	 else {
	 	$ifileinfo = $ifilelocation.' last updated - '.date('Y-m-d H:i:s',$mtime);
	 }
	$form['mvm_price_update_itemsfile'] = array(
	'#type' => 'file',
	'#weight' => -8,
	'#title' => t('Enter the item output file from CoPOS'),
	'#element_validate' => array('mvm_price_update_itemsfile_validate'),
	'#size' => 80,
	'#description' => t('The file should be a tab-delimited output from CoPOS with all items and item information.').'<br />'.t('Current file is ').$ifileinfo,
	);
	$form['mvm_price_update_pricefile'] = array(
	'#type' => 'file',
	'#weight' => -8,
	'#title' => t('Enter the sales output file from CoPOS'),
	'#element_validate' => array('mvm_price_update_pricefile_validate'),
	'#size' => 80,
	'#description' => t('The file should be a tab-delimited output from CoPOS with all sales and sale prices.').'<br />'.t('Current file is ').$pfileinfo,
	);
	$options = array('yes' => 'Run updates now');
	$form['mvm_price_update_now'] = array(
	'#type' => 'checkboxes',
	'#weight' => -7,
	'#prefix' => '<br />',
	'#title' => t('Update Now'),
	'#default_value' => variable_get('mvm_price_update_now','no'),
	'#description' => t("Check this box if you would like the the prices to be updated immediately."),
	'#options' => $options,
	);
	$options = array('yes' => 'Update prices at a scheduled time');
	$form['mvm_price_update_cron'] = array(
	'#type' => 'checkboxes',
	'#weight' => -3,
	'#prefix' => '<br />',
	'#title' => t('Scheduled Update'),
	'#default_value' => variable_get('mvm_price_update_cron','yes'),
	'#description' => t("Check this box if you would like the the prices to be updated via cron at the specified time."),
	'#options' => $options,
	);
	$form['mvm_price_update_runtime'] = array(
	'#type' => 'textfield',
	'#weight' => 0,
	'#title' => t('Time to Run'),
	'#default_value' => date('Y-m-d',strtotime('tomorrow')).' 00:00:00',
	'#description' => t("Enter the date and time that this price update should run (Central Time).  The default is midnight tonight. Current setting is ").variable_get('mvm_price_update_runtime','0'),
	);

	/*
	$form['mvm_price_updates_now'] = array(
	'#type' => 'button',
	'#weight' => -7,
	'#value' => 'Update Prices Now',
	'#suffix' => '<br />',
	'#validate' => array('mvm_price_update_inputfile_validate'),
	'#ahah' => array(
		'event' => 'click',
		'path' => 'mvm_price_update/js',
		'wrapper' => 'db-update-wrapper',
		'method' => 'replace',
		'effect' => 'fade',
		'progress' => array(
			'type' => 'throbber',
			'message' => t('Updating Prices.  This may take some time.'),
			)
		)
	);
	*/
	$form['mvm_price_update_db_update_wrapper'] = array(
	'#prefix' => '<div id="db-update-wrapper">',
	'#value' => '&nbsp;',
	'#suffix' => '</div>',
	'#weight' => -6,
	);

    $form['#submit'][] = 'mvm_price_update_admin_settings_submit';

	return system_settings_form($form);
}

function mvm_price_update_admin_settings_validate($form_id, &$form_values) {
	$pfieldname = 'mvm_price_update_pricefile';
	$ifieldname = 'mvm_price_update_itemsfile';
	$fields = array('prices.txt'=>$pfieldname,'items.txt'=>$ifieldname);
	foreach($fields as $new_name => $fieldname) {
		if(isset($_FILES['files']) && is_uploaded_file($_FILES['files']['tmp_name'][$fieldname]))
			{
				$validators = array();
		        $filepath = file_directory_path().'/mvm_price_update';
		        if(!file_check_directory($filepath, FILE_CREATE_DIRECTORY)) {
		        	form_set_error($fieldname,'Could not validate upload directory');
		        }

				$file = file_save_upload($fieldname);
				if(!$file) {
					form_set_error($fieldname, 'Error uploading file.');
					return;
				}
				$new_filepath = $filepath.'/'.$new_name;
				if(file_move($file,$new_filepath, FILE_EXISTS_REPLACE)){
					drupal_write_record('files',$file,'fid');
				}
				else{
					form_set_error($fieldname, 'Could not move file to the proper location');
					return;
				}
				$form_values['values']['file'] = $file;
			}
		else
			{
				$ifilelocation = file_directory_path().'/mvm_price_update/items.txt';
				$pfilelocation = file_directory_path().'/mvm_price_update/prices.txt';
				 if(!file_exists($pfilelocation)){
				 	drupal_set_message(t('Could not verify the sales file exists.'),'error');
				 	return false;
				 }
				 else {
				 	drupal_set_message(t('Successfully updated the sales file'));
				 }
				 if(!file_exists($ifilelocation)){
				 	drupal_set_message(t('Could not verify the item file exists.'),'error');
				 	return false;
				 }
			}
	}
}

function mvm_price_update_admin_settings_submit($form_id, &$form_values) {
	$ifilelocation = file_directory_path().'/mvm_price_update/items.txt';
	$pfilelocation = file_directory_path().'/mvm_price_update/prices.txt';
	 if(!file_exists($pfilelocation)){
	 	drupal_set_message(t('Could not verify that the sales file was accepted'),'error');
	 	return false;
	 }
	 else {
	 	// Check the file for proper formatting.
	 	$contents = file($pfilelocation);
  		$delimiter = "\t";
  		// Columns is a list of the columns we want to keep.  We ignore all the rest
  		$columns = array(
	      'Item Code',
	      'SKU',
	      'Sale Price',
	      'Sale Start Date',
	      'Sale End Date',
	      'Member Only (Y/N)'
  		);
	 	$data = explode($delimiter,$contents[0]);
	 	foreach($data as $colnum=>$col){
	 		$data[$colnum] = trim($col);
	 	}
	 	foreach($columns as $needle){
	 		if(!in_array($needle,$data)){
	 			drupal_set_message(t('The sales file is improperly formatted: Missing column "'.$needle.'".'),'error');
	 			return;
	 		}
	 	}

	 	drupal_set_message(t('Successfully updated the sales file'));
	 }
	 if(!file_exists($ifilelocation)){
	 	drupal_set_message(t('Could not verify that the item file was accepted'),'error');
	 	return false;
	 }
	 else {
	 	// Check the file for proper formatting.
	 	$contents = file($ifilelocation);
  		$delimiter = "\t";
  		// Columns is a list of the columns we want to keep.  We ignore all the rest
  		$columns = array(
	      'UPC/PLU',
	      'Department #',
	      'Description',
	      'Brand Code',
	      'Category Code',
	      'SKU',
	      'Unit Cost',
	      'SRP',
	      'Qty/Case',
	      'Item Units',
	      'Random Wt Flag (Y/N)',
	      'EBT Eligible (Y/N)',
	      'Discountable Flag (Y/N)',
	      'Taxable (Y/N)',
	      'Active (Y/N)',
		  );
	 	$data = explode($delimiter,$contents[0]);
	 	foreach($data as $colnum=>$col){
	 		$data[$colnum] = trim($col);
	 	}
	 	foreach($columns as $needle){
	 		if(!in_array($needle,$data)){
	 			drupal_set_message(t('The item file is improperly formatted: Missing column "'.$needle.'".'),'error');
	 			return;
	 		}
	 	}
	 	drupal_set_message(t('Successfully updated the item file'));
	 }
	 if(variable_get('mvm_price_update_cron', 'No') == 'Yes') {
		$runtime = variable_get('mvm_price_update_runtime','0');
	 	drupal_set_message(t('Prices are set to be updated after '.$runtime.'. Please ensure that the cron job is set to run immediately following this time.'));
	 }
    if($form_values['values']['mvm_price_update_now']['yes']){
      _mvm_price_update_do_batch();
    }
    $form_state['redirect'] = 'admin';
}
