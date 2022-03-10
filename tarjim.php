<?php 

/**
 *
 */
class TarjimShell extends Shell {
	
	/**
	 *
	 */
	public function updateTarjimLocale() {
		$project_id = Configure::read('TARJIM_PROJECT_ID');
		$apikey = Configure::read('TARJIM_APIKEY');
		$default_namespace = Configure::read('TARJIM_DEFAULT_NAMESPACE');
		$additional_namespaces = Configure::read('TARJIM_ADDITIONAL_NAMESPACES');

    ## Set translation keys
    $Tarjim = new Tarjimclient($project_id, $apikey, $default_namespace, $additional_namespaces);
		$translations = $Tarjim->getLatestFromTarjim();
		$Tarjim->updateCache($translations);
	}
}
