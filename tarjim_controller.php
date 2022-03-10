<?php

class TarjimController extends AppController {
	public $uses = [];

	public function __construct() {
    $this->autoRender = false;

		 ## Log all incoming requests
		if (!empty($_REQUEST)) {
			## Log all incoming
			CakeLog::write('apiv1_incoming', '['.$_SERVER['REQUEST_METHOD'].'] '.$_SERVER['REQUEST_URI'].' '. json_encode($_REQUEST));
		}


		## Differentiate between e.g. aninja.com (prod), baz.dev.aninja.com (dev), etc..
		$this->active_http_host = $_SERVER['HTTP_HOST'];

		## Start Timers
		$this->request_arrived_at = date('Y-m-d H:i:s u');
		$this->request_timer_start = microtime(true);

		## Set preferred language
		if (!empty($_GET['locale'])) {
			$preferred_language = 'en';
			if ($_GET['locale'] === 'fr') {
				$preferred_language = 'fr';
			}
			Configure::write('Config.language', $preferred_language);
			$_SESSION['Config']['language'] = $preferred_language;
		}

		## Validate mobile app version
		$this->validateAppVersion();

		parent::__construct();
	}

	/**
	 *
	 */
	public function validateApp() {
		$this->validateAppVersion();

		## Successful api response
		$result = ['deprecated_app_version' => false];
		$this->outputSuccessfulApi($result);
	}

	/**
	 *
	 */
	public function validateAppVersion() {
		##  Return if not mobile
		if (empty($_GET['platform']) || 'mobile' !== $_GET['platform']) {
			return;
		}

		## Validate params
		$device_os = null;
		if (!empty($_GET['device_os'])) {
			$device_os = $_GET['device_os'];
		} else {
			$error['message'] = 'missing field device_os';
			$this->outputFailedApi($error, 422);
		}
		$panda7_version = null;
		if (!empty($_GET['panda7_version'])) {
			$panda7_version = $_GET['panda7_version'];
		} else {
			$error['message'] = 'missing field panda7_version';
			$this->outputFailedApi($error, 422);
		}

		## Deprecated app version
		if ($panda7_version === '1.0' && $device_os === 'ios') {
			$result = ['deprecated_app_version' => true];
			$this->outputSuccessfulApi($result);
			return;
		}
	}

	/**
	 * Output successful api result
	 */
	public function outputSuccessfulApi($data = [], $status_code = 200) {
		## Api status
		$status = 'success';

		## Api result
		$result['data'] = $data;

		## Output api result
		$this->outputApiResult($result, $status, $status_code);
	}

	/**
	 * Output failed api result
	 */
	public function outputFailedApi($error, $status_code) {
		## Api status
		$status = 'fail';

		## Api result
		$result['error'] = $error;

		## Output api result
		$this->outputApiResult($result, $status, $status_code);
	}

  /**
   * Check if field missing
   */
  public function missingFieldValidation($key, $fields, $details = '') {
    if (!isset($fields[$key])) {
      $error = [
        'message' => 'missing field ' . $key,
        'details' => $details
      ];
      $this->outputFailedApi($error, 400);
    }

    return $fields[$key];
  }

	/**
	 * Output JSON data with status / stats / timers
	 */
	public function outputApiResult($result, $status, $status_code) {
		## Update metadata / timer info / etc.
		$metadata['application'] = $this->active_http_host;
		$request_timer_total_ms = microtime(true) - $this->request_timer_start;
		$metadata['request_arrived_at'] = $this->request_arrived_at;
		$metadata['request_total_time_ms'] = $request_timer_total_ms;
		$metadata['api_version'] = 'v1';
		$metadata['language'] = Configure::read('Config.language');

		## Decide on HTTP response code
		if ('success' == $status) {
			$http_status_code = '200';
		}
		else {
			$http_status_code = '417'; ## Expectation Failed
		}

		## Set header status code
		if (!empty($status_code)) {
			$http_status_code = $status_code;
		}
		$metadata['http_status_code'] = $http_status_code;

		## Build-up return data
		$finaldata = [];
		$finaldata = [
			'status' => $status,
			'result' => $result,
			'metadata' => $metadata
		];

		## Log all output
		CakeLog::write('apiv1_outgoing', '['.$_SERVER['REQUEST_METHOD'].'] '.$_SERVER['REQUEST_URI'].' '. json_encode($finaldata));

		## Output and exit
		http_response_code($http_status_code);
		header('Content-Type: application/json');

		echo json_encode($finaldata);
		exit(0);
	}

  /**
   *
   */
  public function updateLocaleCache() {
		$project_id = Configure::read('TARJIM_PROJECT_ID');
		$apikey = Configure::read('TARJIM_APIKEY');
		$default_namespace = Configure::read('TARJIM_DEFAULT_NAMESPACE');
		$additional_namespaces = Configure::read('TARJIM_ADDITIONAL_NAMESPACES');

    ## Set translation keys
    $Tarjim = new Tarjimclient($project_id, $apikey, $default_namespace, $additional_namespaces);
    
    $result = $Tarjim->getLatestFromTarjim();
		$Tarjim->updateCache($result);
    
		$Tarjim->writeToFile($Tarjim->update_cache_log_file, 'cache refreshed on '.date('Y-m-d H:i:s').PHP_EOL, FILE_APPEND);

    $this->outputSuccessfulApi();
  }
}
