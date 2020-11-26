<?php
/*
Plugin Name: GravityForms Coda Addon
Plugin URI: http://www.mohamed-hamad.com
Description: A Gravity Forms Feed Addon for Coda
Version: 1.1
Author: Mohamed Hamad
Author URI: http://www.mohamed-hamad.com
License:     GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
------------------------------------------------------------------------
Copyright 2010-2019w Moahmed Hamad

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

@package   GravityForms_Coda
@version   1.0.1
@author    Mohamed Hamad <mohamed@mohamed-hamad.com>
@license   GPL-2.0+
@link      https://www.mohamed-hamad.com
@copyright 2015-2017 gravity+

*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require 'vendor/autoload.php';

define( 'GF_CODA_VERSION', '1.1' );
define( 'GF_CODA_FILE', __FILE__ );
define( 'GF_CODA_PATH', plugin_dir_path( __FILE__ ) );
define( 'GF_CODA_URL', plugin_dir_url( __FILE__ ) );
define( 'GF_CODA_SLUG', plugin_basename( dirname( __FILE__ ) ) );

add_action( 'gform_loaded', array( 'GF_Coda_Feed_AddOn_Bootstrap', 'load' ), 5 );

class GF_Coda_Feed_AddOn_Bootstrap {

	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}

		require_once( 'class-gravityforms-coda-addon.php' );

		GFAddOn::register( 'GravityFormsCodaFeedAddOn' );
	}

}

function gf_coda_feed_addon() {
	return GravityFormsCodaFeedAddOn::get_instance();
}
