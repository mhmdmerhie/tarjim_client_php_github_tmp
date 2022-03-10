<?php
/**
 * Tarjim.io PHP Translation client
 * version: 1.4
 *
 * Requires PHP 5+
 * This file includes the Translationclient Class and
 * the _T() function definition
 *
 */

class Tarjimclient {
	/**
	 * pass config params to construct
	 */
	public function __construct($project_id = null, $apikey = null, $default_namespace = null, $additional_namespaces = []) {
		$this->project_id = $project_id;
		$this->apikey = $apikey;
		$this->default_namespace = $default_namespace;
		$this->additional_namespaces = $additional_namespaces;

		if (empty($additional_namespaces) || !is_array($additional_namespaces)) {
			$additional_namespaces = [];
		}

		$this->namespaces = $additional_namespaces;
		array_unshift($this->namespaces, $default_namespace);
		$this->cache_dir = __DIR__.'/cache/';
		$this->cache_backup_file = $this->cache_dir.'translations_backup.json';
		$this->cache_file = $this->cache_dir.'translations.json';
		$this->sanitized_html_cache_file = $this->cache_dir.'sanitized_html.json';
		$this->logs_dir = __DIR__.'/logs/';
		$this->errors_file = $this->logs_dir.'errors.log';
		$this->update_cache_log_file = $this->logs_dir.'update_cache.log';
		$this->tarjim_base_url = 'https://app.tarjim.io';
	}

	/**
	 *
	 */
	public function setActiveLanguage($language) {
		global $_T;
		$_T['meta']['active_language'] = $language;
	}

	/**
	 *
	 */
  public function setTranslations($language) {
		global $_T;

    ## Set translation keys
		$_T = $this->getTranslations();

		## for Cakex view translation (non-json encoded)
		$_T['results'] = $_T['results'];
		$_T['meta']['default_namespace'] = $this->default_namespace;
		$this->setActiveLanguage($language);
  }

	/**
	 * Checks tarjim results_last_updated and compare with latest file in cache
	 * if tarjim result is newer than cache pull from tarjim and update cache
	 */
	public function getTranslations() {
		set_error_handler('tarjimErrorHandler');

		if (!file_exists($this->cache_file) || !filesize($this->cache_file) || is_null(file_get_contents($this->cache_file))) {
			$final = $this->getLatestFromTarjim();
			$this->updateCache($final);
		}
		else {
			$ttl_in_minutes = 15;

			$time_now = time();
			$time_now_in_minutes = (int) ($time_now / 60);
			$locale_last_updated = filemtime($this->cache_file);
			$locale_last_updated_in_minutes = (int) ($locale_last_updated / 60);
			$diff = $time_now_in_minutes - $locale_last_updated_in_minutes;
			## If cache was updated in last $ttl_in_minutes min get data directly from cache
			if ((isset($diff) && $diff < $ttl_in_minutes)) {
				$cache_data = file_get_contents($this->cache_file);
				$final = json_decode($cache_data, true);
			}
			else {
				## Pull meta
				$endpoint = $this->tarjim_base_url.'/api/v1/translationkeys/json/meta/'.$this->project_id.'?apikey='.$this->apikey;
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $endpoint);
				curl_setopt($ch, CURLOPT_TIMEOUT, 30);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

				$meta = curl_exec($ch);

				## Get translations from cache if curl failed
				if (curl_error($ch)) {
					$this->writeToFile($this->errors_file, date('Y-m-d H:i:s').' Curl error line '.__LINE__.': ' . curl_error($ch).PHP_EOL, FILE_APPEND);
					$cache_data = file_get_contents($this->cache_file);
					$final = json_decode($cache_data, true);

					## Restore default error handler
					restore_error_handler();

					return $final;
				}
				curl_close($ch);

				$meta = json_decode($meta, true);

				if ('fail' == $meta['status']) {
					$this->reportErrorToApi('api_error', $meta['result']['error']['message']);	
					$cache_data = file_get_contents($this->cache_file);
					$final = json_decode($cache_data, true);

					## Restore default error handler
					restore_error_handler();

					return $final;
				}

				## Forward compatibility		
				if (array_key_exists('result', $meta)) {
					$meta = $meta['result']['data'];
				}

				## Get cache meta tags
				$cache_meta = file_get_contents($this->cache_file);
				$cache_meta = json_decode($cache_meta, true);

				## If cache if older than tarjim get latest and update cache
				if ($cache_meta['meta']['results_last_update'] < $meta['meta']['results_last_update']) {
					$final = $this->getLatestFromTarjim();
					$this->updateCache($final);
				}
				else {
					## Update cache file timestamp
					touch($this->cache_file);
					$locale_last_updated = filemtime($this->cache_file);

					$cache_data = file_get_contents($this->cache_file);
					$final = json_decode($cache_data, true);
				}
			}
		}

		## Restore default error handler
		restore_error_handler();

		return $final;
	}

	/**
	 * Update cache files
	 */
	public function updateCache($latest) {
		set_error_handler('tarjimErrorHandler');
		if (file_exists($this->cache_file)) {
			$cache_backup = file_get_contents($this->cache_file);
			$cmd = 'chmod 777 '.$this->cache_backup_file;
			exec($cmd);
			$this->writeToFile($this->cache_backup_file, $cache_backup);
		}

		$encoded = json_encode($latest);
		$cmd = 'chmod 777 '.$this->cache_file;
		exec($cmd);
		$this->writeToFile($this->cache_file, $encoded);

		## Restore default error handler
		restore_error_handler();
	}

	/**
	 * Get full results from tarjim
	 */
	public function getLatestFromTarjim() {
		set_error_handler('tarjimErrorHandler');

		$endpoint = $this->tarjim_base_url.'/api/v1/translationkeys/jsonByNameSpaces';

		$post_params = [
			'project_id' => $this->project_id,
			'apikey' => $this->apikey,	
			'namespaces' => $this->namespaces,
		];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $endpoint);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_params));
		curl_setopt($ch, CURLOPT_POSTREDIR, 3);

		$result = curl_exec($ch);

		if (curl_error($ch) || empty($result)) {
			$this->writeToFile($this->errors_file, date('Y-m-d H:i:s').' Curl error line '.__LINE__.': ' . curl_error($ch).PHP_EOL, FILE_APPEND);
			$cache_data = file_get_contents($this->cache_file);
			$final = json_decode($cache_data, true);

			## Restore default error handler
			restore_error_handler();
			return $final;
		}

		$decoded = json_decode($result, true);
		if ('fail' == $decoded['status']) {
			$this->writeToFile($this->errors_file, date('Y-m-d H:i:s').' Tarjim Error'.__LINE__.' tarjim response: ' . json_encode($decoded).PHP_EOL, FILE_APPEND);
			$error_details = $decoded['result']['error']['message'];
			$this->reportErrorToApi('api_error', $error_details);

			$cache_data = file_get_contents($this->cache_file);
			$final = json_decode($cache_data, true);

			## Restore default error handler
			restore_error_handler();
			return $final;
		}
		
		## Forward compatibility		
		if (array_key_exists('result', $decoded)) {
			$decoded = $decoded['result']['data'];
		}

		## Restore default error handler
		restore_error_handler();

		return $decoded;
	}

	/**
	 *
	 */
	public function writeToFile($file, $content, $options = null) {
		if (file_exists($file)) {
			if (is_writable($file)) {
				file_put_contents($file, $content, $options);
			}
			else {
				$error_details = $file.' is not writable';
				$this->reportErrorToApi('file_error', $error_details);
			}
		}
		else {
			$error_details = $file.' does not exist';
			$this->reportErrorToApi('file_error', $error_details);
		}
	}

	/**
	 *
	 */
	public function reportErrorToApi($error_type, $error_details) {
		$endpoint = $this->tarjim_base_url.'/api/v1/report-client-error/';

		if (php_sapi_name() != 'cli') {
			$domain = $_SERVER['HTTP_HOST']; 
		}
		else {
			$domain = 'cli';
		}

		$post_params = [
			'domain' => $domain,
			'project_id' => $this->project_id,
			'apikey' => $this->apikey,	
			'error_type' => $error_type,
			'error_details' => $error_details,
		];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $endpoint);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_params));
		curl_setopt($ch, CURLOPT_POSTREDIR, 3);

		$result = curl_exec($ch);
	}

}

/**
 * Tarjim error handler
 */
function tarjimErrorHandler($errno, $errstr, $errfile, $errline) {
	$Tarjim = new Tarjimclient();
	$Tarjim->writeToFile($Tarjim->errors_file, date('Y-m-d H:i:s').' Tarjim client error file '.$errfile.' (line '.$errline.'): '.$errstr.PHP_EOL, FILE_APPEND);
}

/**
 * Tarjim.io Translation helper
 * N.B: if calling _T() inside Javascript code, pass the do_addslashes as true
 *
 * Read from the global $_T
 */
///////////////////////////////
function _T($key, $config = [], $debug = false) {
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

/**
 * return dataset with all languages for key
 */
function _TD($key, $config = []) {
	global $_T;
	$namespace = $_T['meta']['default_namespace'];

	if (isset($config['namespace'])) {
		$namespace = $config['namespace'];
	}

	$dataset = [];
	$original_key = $key;
	$key = strtolower($key);

	$translations = $_T['results'];	
	if ('all_namespaces' == $namespace) {
		foreach ($translations as $namespace => $namespace_translations) {
			if ('meta' == $namespace) {
				continue;
			};
			foreach ($namespace_translations as $language => $language_translations) {
				$dataset[$namespace][$language] = '';
				if (isset($language_translations[$key])) {
					$sanitized_value = sanitizeResult($key, $language_translations[$key]['value']);
					$dataset[$namespace][$language] = $sanitized_value;
				}
			}
		}
	}
	else {
		$namespace_translations = $translations[$namespace];
		foreach ($namespace_translations as $language => $language_translations) {
			$dataset[$language] = '';
			if (isset($language_translations[$key])) {
				$sanitized_value = sanitizeResult($key, $language_translations[$key]['value']);
				$dataset[$language] = $sanitized_value;
			}
		}
	}
	return $dataset;
}

/**
 * Shorthand for _T($key, ['skip_tid'])
 * Skip assigning data-tid and wrapping in span
 * used with images, placeholders, title, select/dropdown
 */
function _TS($key, $config = []) {
	$config['skip_tid'] = true;
	return _T($key, $config);
}

/**
 * Alias for _TM()
 */
function _TI($key, $attributes) {
	return _TM($key, $attributes);
}

/**
 * Used for media
 * @param String $key key for media
 * @param Array $attributes attributes for media eg: class, id, width...
 * If received key doesn't have type:image return _T($key) instead
 */
function _TM($key, $attributes=[]) {
	## Sanity
	if (empty($key)) {
		return;
	}

	set_error_handler('tarjimErrorHandler');
	
	$namespace = '';
	if (isset($attributes['namespace'])) {
		$namespace = $attributes['namespace'];
		unset($attributes['namespace']);
	}
	
	$result = getTarjimValue($key, $namespace);
	$value = $result['value'];
	$tarjim_id = $result['tarjim_id'];
	$full_value = $result['full_value'];

	$attributes_from_remote = [];
	$sanitized_value = sanitizeResult($key, $value);
	$final_value = 'src='.$sanitized_value.' data-tid='.$tarjim_id;

	if (array_key_exists('attributes', $full_value)) {
		$attributes_from_remote = $full_value['attributes'];
	}

	## Merge attributes from tarjim.io and those received from view
	# for attributes that exist in both arrays take the value from tarjim.io
	$attributes = array_merge($attributes, $attributes_from_remote);
	if (!empty($attributes)) {
		foreach ($attributes as $attribute => $attribute_value) {
			$final_value .= ' ' .$attribute . '="' . $attribute_value .'"';
		}
	}

	## Restore default error handler
	restore_error_handler();
	return $final_value;
}

/**
 * Get value for key from global $_T object
 * returns array with
 * value => string to render or media src
 * tarjim_id => id to assign to data-tid
 * assign_tarjim_id => boolean
 * full_value => full object for from $_T to retreive extra attributes if needed
 */
function getTarjimValue($key, $namespace = '') {
	set_error_handler('tarjimErrorHandler');
	global $_T;
		
	if (empty($namespace)) {
		$namespace = $_T['meta']['default_namespace'];
	}

	$active_language = $_T['meta']['active_language'];
	$original_key = $key;
	$key = strtolower($key);
	$assign_tarjim_id = false;
	$tarjim_id = '';
	$full_value = [];
	$translations = $_T['results'];

	## Direct match
	if (isset($translations[$namespace][$active_language][$key]) && !empty($translations[$namespace][$active_language][$key])) {
		$mode = 'direct';
		if (is_array($translations[$namespace][$active_language][$key])) {
			if (!empty($translations[$namespace][$active_language][$key]['value'])) {
				$value = $translations[$namespace][$active_language][$key]['value'];
			}
			else {
				$mode = 'empty_value_fallback';
				$value = $original_key;
			}
			$tarjim_id = $translations[$namespace][$active_language][$key]['id'];
			$assign_tarjim_id = true;
			$full_value = $translations[$namespace][$active_language][$key];
		}
		else {
			$value = $translations[$namespace][$active_language][$key];
		}
	}

	## Fallback key
	if (isset($translations[$namespace][$active_language][$key]) && empty($translations[$namespace][$active_language][$key])) {
		$mode = 'key_fallback';
		$value = $original_key;
	}

	## Empty fall back (return key)
	if (!isset($translations[$namespace][$active_language][$key])) {
		$mode = 'empty_key_fallback';
		$value = $original_key;
	}

	$result = [
		'value' => $value,
		'tarjim_id' => $tarjim_id,
		'assign_tarjim_id' => $assign_tarjim_id,
		'full_value' => $full_value,
	];

	## Restore default error handler
	restore_error_handler();

	return $result;
}

/**
 *
 */
function assignTarjimId($id, $value) {
	$result = sprintf('<span data-tid=%s>%s</span>', $id, $value);
	return $result;
}

/**
 * Remove <script> tags from translation value
 * Prevent js injection
 */
function sanitizeResult($key, $result) {
	global $_T;
	$unacceptable_tags = ['script'];
	$unacceptable_attribute_values = [
		'function',
		'{.*}',
	];

	if ($result != strip_tags($result)) {
		$Tarjim = new Tarjimclient;
		## Get meta from cache
		$cache_data = file_get_contents($Tarjim->cache_file);
		$cache_data = json_decode($cache_data, true);
		$cache_results_checksum = $cache_data['meta']['results_checksum'];

		## Get active language
		if (isset($_T['meta']) && isset($_T['meta']['active_language'])) {
			$active_language = $_T['meta']['active_language'];
		}
		elseif (isset($_SESSION['Config']['language'])) {
			$active_language = $_SESSION['Config']['language'];
		}

		if (file_exists($Tarjim->sanitized_html_cache_file) && filesize($Tarjim->sanitized_html_cache_file) && isset($active_language)) {
			$sanitized_html_cache_file = $Tarjim->sanitized_html_cache_file;
			$cache_file = $Tarjim->cache_file;

			## Get sanitized cache
			$sanitized_cache = file_get_contents($sanitized_html_cache_file);
			$sanitized_cache = json_decode($sanitized_cache, true);
			$sanitized_cache_checksum = $sanitized_cache['meta']['results_checksum'];

			if (isset($sanitized_cache['results'][$active_language])) {
				$sanitized_cache_results = $sanitized_cache['results'][$active_language];

				## If locale haven't been updated and key exists in sanitized cache
				# Get from cache
				if ($cache_results_checksum == $sanitized_cache_checksum && array_key_exists($key, $sanitized_cache_results)) {
					return $sanitized_cache['results'][$active_language][$key];
				}
			}
		}

		$dom = new DOMDocument;
		$dom->loadHTML('<?xml encoding="utf-8" ?>'.$result, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

		## Remove unawanted nodes
		foreach ($unacceptable_tags as $tag) {
			## Get unwanted nodes
			$unwanted_nodes = $dom->getElementsByTagName($tag);
			## Copy unwanted nodes to loop over without updating length on removal of nodes
			$unwanted_nodes_copy = iterator_to_array($unwanted_nodes);
			foreach ($unwanted_nodes_copy as $unwanted_node) {
				## Delete node
				$unwanted_node->parentNode->removeChild($unwanted_node);
			}
		}

		$nodes = $dom->getElementsByTagName('*');

		foreach ($nodes as $node) {
			## Remove unwanted attributes
			if ($node->hasAttributes()) {
				$attributes_copy = iterator_to_array($node->attributes);
				foreach ($attributes_copy as $attr) {
					foreach ($unacceptable_attribute_values as $value) {
						$regex = '/'.$value.'/is';
						if (preg_match_all($regex, $attr->nodeValue)) {
							$node->removeAttribute($attr->nodeName);
							break;
						}
					}
				}
			}
		}

		$sanitized = $dom->saveHTML($dom);
		$stripped = str_replace(['<p>', '</p>'], '', $sanitized);
		cacheSanitizedHTML($key, $stripped, $cache_results_checksum);
		return $stripped;
	}

	return $result;
}

/**
 *
 */
function cacheSanitizedHTML($key, $sanitized, $cache_results_checksum) {
	global $_T;
	$Tarjim = new Tarjimclient;
	$sanitized_html_cache_file = $Tarjim->sanitized_html_cache_file;

	## Get active language
	if (isset($_T['meta']) && isset($_T['meta']['active_language'])) {
		$active_language = $_T['meta']['active_language'];
	}
	elseif (isset($_SESSION['Config']['language'])) {
		$active_language = $_SESSION['Config']['language'];
	}
	else {
		return;
	}

	if (file_exists($sanitized_html_cache_file) && filesize($sanitized_html_cache_file)) {
		$sanitized_html_cache = file_get_contents($sanitized_html_cache_file);
		$sanitized_html_cache = json_decode($sanitized_html_cache, true);

		## If translation cache checksum is changed overwrite sanitized cache
		if ($sanitized_html_cache['meta']['results_checksum'] != $cache_results_checksum) {
			$sanitized_html_cache = [];
		}
	}

	$sanitized_html_cache['meta']['results_checksum'] = $cache_results_checksum;
	$sanitized_html_cache['results'][$active_language][$key] = $sanitized;
	$encoded_sanitized_html_cache = json_encode($sanitized_html_cache);
	$cmd = 'chmod 777 '.$Tarjim->sanitized_html_cache_file;
	exec($cmd);
	$Tarjim->writeToFile($sanitized_html_cache_file, $encoded_sanitized_html_cache);
}

/**
 *
 */
function injectValuesIntoTranslation($translation_string, $mappings) {
	## Get all keys to replace and save into matches
	$matches = [];
	preg_match_all('/%%.*?%%/', $translation_string, $matches);

	## Inject values into result
	foreach ($matches[0] as $match) {
		$match_stripped = str_replace('%', '', $match);
		$regex = '/'.$match.'/';
		$translation_string = preg_replace($regex, $mappings[$match_stripped], $translation_string);
	}

	return $translation_string;
}


/**
 * Helper function to create a keys file
 */
function InjectViewKeysIntoTranslationTable() {
	## TODO 1. Exec the command, and inject the keys into the translations DB (indicating which namespace & language)
	#$cmd = 'grep -ohriE "_T\('.*'\)" ./views/* > keys';
	#exec ($cmd);

}
