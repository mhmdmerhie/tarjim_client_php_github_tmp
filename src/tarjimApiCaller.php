<?php

namespace Merhie\TarjimClientPhpGithubTmp;

class TarjimApiCaller extends Tarjim {

	public function __construct($config_file_path) {
		parent::__construct($config_file_path);
	}

	/**
	 *
	 */
	public function getMetaFromTarjim() {
		$endpoint = '/api/v1/translationkeys/json/meta/'.$this->project_id;
		$result = $this->doCurlCall($endpoint, 'GET', ['apikey' => $this->apikey]); 
		return $result;
	} 

	/**
	 * Get full results from tarjim
	 */
	public function getLatestFromTarjim() {
		set_error_handler('tarjimErrorHandler');
		$endpoint = '/api/v1/translationkeys/jsonByNameSpaces';
		$post_params = [
			'project_id' => $this->project_id,
			'apikey' => $this->apikey,	
			'namespaces' => $this->namespaces,
		];
		$result = $this->doCurlCall($endpoint, 'POST', $post_params); 

		restore_error_handler();

		return $result;

	}

}
