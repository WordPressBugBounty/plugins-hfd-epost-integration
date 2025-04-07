<?php
/*
Plugin Name: HFD ePost Integration
Requires Plugins: woocommerce
Plugin URI:
Description: Add shipping method of ePost, allowing the user on the checkout, to select the pickup location point from a google map popup. Also allows to synch the order to HFD API after the order is created.
Version: 2.16
Author: HFD
Author URI: https://www.hfd.co.il
License: GPLv2 or later
Text Domain: hfd-integration
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

//return if woocommerce not installed
if( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ){
	return;
}

define( 'HFD_EPOST_PATH', dirname( __FILE__ ) );
define( 'HFD_EPOST_PLUGIN_FILE', plugin_basename( __FILE__ ) );
define( 'HFD_EPOST_PLUGIN_DIR', basename( __DIR__ ) );
define( 'HFD_EPOST_PLUGIN_URL', plugins_url( HFD_EPOST_PLUGIN_DIR ) );

require 'class/App.php';

$_hfdEpostApp = new \Hfd\Woocommerce\App();

register_activation_hook( __FILE__, array( $_hfdEpostApp, 'pluginActivation' ) );
register_deactivation_hook( __FILE__, array( $_hfdEpostApp, 'pluginDeactivation' ) );

$_hfdEpostApp->init();