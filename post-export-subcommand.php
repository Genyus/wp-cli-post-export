<?php

namespace Ingenyus\WP_CLI;

use WP_CLI;

if (! class_exists('\WP_CLI') ) {
	return;
}

$wpcli_post_export_autoloader = __DIR__ . '/vendor/autoload.php';

if (file_exists($wpcli_post_export_autoloader) ) {
	include_once $wpcli_post_export_autoloader;
}

WP_CLI::add_command('post', Post_Export_Subcommand::class );
