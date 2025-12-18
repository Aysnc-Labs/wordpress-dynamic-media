<?php
/**
 * PHPUnit bootstrap file for wp-env
 *
 * @package aysnc/wordpress-dynamic-media
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$_tests_dir = getenv( 'WP_TESTS_DIR' );

require_once $_tests_dir . '/includes/functions.php';
require_once $_tests_dir . '/includes/bootstrap.php';
