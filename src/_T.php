<?php

/**
 * Tarjim.io Translation helper
 * N.B: if calling _T() inside Javascript code, pass the do_addslashes as true
 *
 * Read from the global $_T
 */
///////////////////////////////
function _T($key, $config = [], $debug = false) {
  die($key);
  ## Sanity
  if (empty($key)) {
    return;
  }


  set_error_handler('tarjimErrorHandler');

  ## Check for mappings
  if (isset($config['mappings'])) {
    $mappings = $config['mappings'];
  }
  
  $namespace = '';
  if (isset($config['namespace'])) {
    $namespace = $config['namespace'];
  }


  $result = getTarjimValue($key, $namespace);
  $value = $result['value'];
  $assign_tarjim_id = $result['assign_tarjim_id'];
  $tarjim_id = $result['tarjim_id'];
  $full_value = $result['full_value'];

  ## Check config keys and skip assigning tid and wrapping in a span for certain keys
  # ex: page title, input placeholders, image hrefs...
  if (
    (isset($config['is_page_title']) || in_array('is_page_title', $config)) ||
    (isset($config['skip_assign_tid']) || in_array('skip_assign_tid', $config)) ||
    (isset($config['skip_tid']) || in_array('skip_tid', $config)) ||
    (isset($full_value['skip_tid']) && $full_value['skip_tid'])
  ) {
    $assign_tarjim_id = false;
  }

  ## Debug mode
  if (!empty($debug)) {
    echo $mode ."\n";
    echo $key . "\n" .$value;
  }

  if (isset($config['do_addslashes']) && $config['do_addslashes']) {
    $result = addslashes($value);
  }

  if (isset($mappings)) {
    $value = injectValuesIntoTranslation($value, $mappings);
  }

  $sanitized_value = sanitizeResult($key, $value);

  ## Restore default error handler
  restore_error_handler();

  if ($assign_tarjim_id) {
    $final_value = assignTarjimId($tarjim_id, $sanitized_value);
    return $final_value;
  }
  else {
    return strip_tags($sanitized_value);
  }
}
