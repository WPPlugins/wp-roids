<?php
// Exit if accessed directly
if( ! defined('WP_UNINSTALL_PLUGIN') ) exit;
require_once( __DIR__ . '/wp-roids.php' );
WPRoidsPhil::uninstall();
