<?php
require_once('wp-load.php');

$active_plugins = get_option('active_plugins');
$plugin_name = 'AI sportclub/sportclub_manager.php';

if (in_array($plugin_name, $active_plugins)) {
    echo 'Plugin is active' . PHP_EOL;
} else {
    echo 'Plugin is not active' . PHP_EOL;
    echo 'Active plugins:' . PHP_EOL;
    print_r($active_plugins);
}
?>
