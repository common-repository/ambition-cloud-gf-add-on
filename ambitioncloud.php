<?php
/**
Plugin Name: Ambition Cloud GF Add-On
Plugin URI: https://www.fintelligence.com.au/crm-system/plugins/gravity-forms
Description: Integrates Gravity Forms with Ambition Cloud, allowing form submissions to be automatically sent to your Ambition Cloud account.
Version: 2.0.4
Author: Fintelligence
Author URI: https://fintelligence.com.au
License: GPL-2.0+
Text Domain: ambitioncloud
Domain Path: /languages

------------------------------------------------------------------------

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/


// Don't load directly.
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

define( 'GF_AMBITIONCLOUD_VERSION', '2.0.1' );

// If Gravity Forms is loaded, bootstrap the AmbitionCloud Add-On.
add_action( 'gform_loaded', array( 'GF_AmbitionCloud_Bootstrap', 'load' ), 5 );

/**
 * Class GF_AmbitionCloud_Bootstrap
 *
 * Handles the loading of the AmbitionCloud Add-On and registers with the Add-On framework.
 */
class GF_AmbitionCloud_Bootstrap {

	/**
	 * If the Feed Add-On Framework exists, Post Creation Add-On is loaded.
	 *
	 * @access public
	 * @static
	 */
	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}

		require_once( 'class-gf-ambitioncloud.php' );

		GFAddOn::register( 'GFAmbitionCloud' );

	}

}

/**
 * Returns an instance of the GFAmbitionCloud class
 *
 * @see    GFAmbitionCloud::get_instance()
 *
 * @return object GFAmbitionCloud
 */
function gf_ambitioncloud() {
	return GFAmbitionCloud::get_instance();
}
