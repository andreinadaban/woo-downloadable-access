<?php

/**
 * Plugin Name: Woo Downloadable Access
 * Plugin URI:  https://github.com/andreinadaban/woo-downloadable-access
 * Description: Access downloadable product files as an admin or shop manager from the media library or using direct links. Only works on Apache web servers.
 * Version:     1.0.0
 * Author:      Andrei Nadaban
 * Author URI:  https://andreinadaban.com
 * License:     GPL-3.0+
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: woo-downloadable-access
 * Domain Path: /languages
 */

namespace Woo_Downloadable_Access;

defined( 'WPINC' ) || exit;

define( __NAMESPACE__ . '\PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( __NAMESPACE__ . '\PLUGIN_BASE', plugin_basename( __FILE__ ) );
define( __NAMESPACE__ . '\PLUGIN_NAME', 'Woo Downloadable Access' );
define( __NAMESPACE__ . '\PLUGIN_SLUG', 'woo_downloadable_access' );

include 'inc/Core.php';

$core = new Core();

register_activation_hook(   __FILE__, [ $core, 'activate' ] );
register_deactivation_hook( __FILE__, [ $core, 'deactivate' ] );

$core->init();
