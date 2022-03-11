<?php

$file_path = argv[0];
require_once $file_path;

global $cache_dir, $logs_dir, $apikey, $project_id, $default_namespace, $additional_namespaces;

if (empty($cache_dir)) {
	die("No cache_dir defined");
}
if (empty($default_namespace)) {
	die("No default_namespace defined");
}
if (empty($apikey)) {
	die("No apikey defined");
}
if (empty($project_id)) {
	die("No project_id defined");
}
if (empty($logs_dir)) {
	die("No logs_dir defined");
}

$config = '
define(cache_dir, $cache_dir);
define(logs_dir, $logs_dir);
define(apikey, $apikey);
define(project_id, $project_id);
define(default_namespace, $default_namespace);
';

if (!empty($additional_namespaces)) {
	$config .= 'define(additional_namespaces, $additional_namespaces');
}

file_put_contents('../src/config.php', $config);
