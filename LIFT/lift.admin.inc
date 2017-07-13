<?php
/**
 * @file
 * Administration Page callbacks for the LIFT Module
 */

/**
 * Form Builder.
 *
 * @ingroup forms
 * @see system_settings_form().
 */

/**
 * Form function, called by drupal_get_form()
 * in lift_menu().
 */
function lift_admin_form($form, &$form_state) {
  $default = variable_get('lift_sunset_time');
  $form['lift_sunset_time'] = array(
      '#type' => 'textfield',
      '#title' => t('Default File Sunset Time in Minutes'),
      '#default_value' => $default,
      '#description' => t('The default number of minutes from post time that files will expire'),
      '#element_validate' => array('element_validate_number'),
      '#required' => TRUE,
  );
  $default = variable_get('lift_file_location');
  $form['lift_file_location'] = array(
      '#type' => 'textfield',
      '#title' => t('File Storage Location'),
      '#default_value' => $default,
      '#description' => t('The subfolder in your private file system path to store files for LIFTing.'),
      //'#element_validate' => array('lift_file_location_validate'),  Will fix this
      '#required' => FALSE,
  );
  $default = variable_get('lift_max_attempts');
  $form['lift_max_attempts'] = array(
      '#type' => 'textfield',
      '#title' => t('Default Max Download Attempts'),
      '#default_value' => $default,
      '#description' => t('The default number of times a file download can be attempted.'),
      '#element_validate' => array('element_validate_number'),
      '#required' => TRUE,
  );
  $form['#submit'][] = 'lift_admin_form_submit';

  return system_settings_form($form);
}

/**
 * Implementation of hook_form_submit
 * @see lift_admin_form().
 */
function lift_admin_form_submit($form, &$form_state) {
  foreach ($form_state['values'] as $key => $value) {
    if (is_array($value) && isset($form_state['values']['array_filter'])) {
      $value = array_keys(array_filter($value));
    }
    variable_set($key, $value);
  }
  drupal_set_message(t('The configuration options have been saved.'));
}
