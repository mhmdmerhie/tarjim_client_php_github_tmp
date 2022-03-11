<?php

$cwd = $argv[1];
$script_dir = $argv[2];
$file_name = $argv[3];

echo 'cwd: '.$cwd.PHP_EOL;
echo 'script_dir: '.$script_dir.PHP_EOL;
echo 'file_name: '.$file_name.PHP_EOL;
echo '$cwd$file_name: '.$cwd.'/'.$file_name.PHP_EOL;
echo 'package_dir: '.$script_dir.'/..'.PHP_EOL;

require_once $cwd.'/'.$file_name;

$package_dir = $script_dir.'/..';

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

touch($cwd.'/'.$cache_dir.'/translations.json');
touch($cwd.'/'.$cache_dir.'/translations_backup.json');
touch($cwd.'/'.$cache_dir.'/sanitized_html.json');
touch($cwd.'/'.$logs_dir.'/errors.log');

exec('chmod -R 777 '.$cwd.'/'.$cache_dir.' '.$cwd.'/'.$logs_dir); 

$config = "<?php
define('cache_dir', '{$cwd}/{$cache_dir}');
define('logs_dir', '{$cwd}/{$logs_dir}');
define('apikey', '{$apikey}');
define('project_id', '{$project_id}');
define('default_namespace', '{$default_namespace}');
";

if (!empty($additional_namespaces)) {
	$config .= 'define(\'additional_namespaces\', '.json_encode($additional_namespaces).');';
}
else {
	$config .= 'define(\'additional_namespaces\', []);';

}

file_put_contents($package_dir.'/src/config.php', $config);
