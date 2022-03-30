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
namespace Merhie\TarjimClientPhpGithubTmp; 
require_once __DIR__.'/functions.php';

class TarjimClient extends Tarjim {
	/**
	 * pass config params to construct
	 */
	public function __construct($config_file_path) {
		parent::__construct($config_file_path);
		$this->TarjimApiCaller = new TarjimApiCaller($config_file_path);
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
		$_T['meta']['config_file_path'] = $this->config_file_path;
		$this->setActiveLanguage($language);
  }

	/**
	 * Checks tarjim results_last_updated and compare with latest file in cache
	 * if tarjim result is newer than cache pull from tarjim and update cache
	 */
	public function getTranslations() {
		set_error_handler('tarjimErrorHandler');

		if (!file_exists($this->cache_file) || !filesize($this->cache_file) || is_null(file_get_contents($this->cache_file))) {
			$apiData = $this->TarjimApiCaller->getLatestFromTarjim();
			if ('fail' == $apiData) {
				restore_error_handler();
				die('failed to get data from tarjim api check error logs for more details');
			}
			$this->updateCache($final['result']);
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
				$tarjim_meta = $this->TarjimApiCaller->getMetaFromTarjim();
				if ('fail' == $tarjim_meta['status']) {
					## Get cached data
					$cache_data = file_get_contents($this->cache_file);
					$final = json_decode($cache_data, true);

					## Restore default error handler
					restore_error_handler();

					return $final;
				}
				
				$tarjim_meta = $tarjim_meta['result'];

				## Get cache meta tags
				$cache_meta = file_get_contents($this->cache_file);
				$cache_meta = json_decode($cache_meta, true);

				## If cache if older than tarjim get latest and update cache
				if ($cache_meta['meta']['results_last_update'] < $tarjim_meta['meta']['results_last_update']) {
					$apiResults = $this->TarjimApiCaller->getLatestFromTarjim();
					
					## Get cached data
					if ('fail' == $apiResults['status']) {
						$cache_data = file_get_contents($this->cache_file);
						$final = json_decode($cache_data, true);
					}
					else {
						$final = $apiResults['result'];
					}

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
			$this->writeToFile($this->cache_backup_file, $cache_backup);
		}

		$encoded = json_encode($latest);
		$this->writeToFile($this->cache_file, $encoded);

		## Restore default error handler
		restore_error_handler();
	}

	/**
	 *
	 */
	public function forceUpdateCache() {
    $result = $this->TarjimApiCaller->getLatestFromTarjim();

		if ('fail' == $result['status']) {
			die('failed to get data from tarjim api check error logs for more details');
		}

		$this->updateCache($result['result']);
    
		$this->writeToFile($this->update_cache_log_file, 'cache refreshed on '.date('Y-m-d H:i:s').PHP_EOL, FILE_APPEND);
	}
}
