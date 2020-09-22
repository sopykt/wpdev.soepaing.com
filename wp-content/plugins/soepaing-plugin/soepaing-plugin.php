<?php
/**
 * Plugin Name
 *
 * @package           SoepaingPlugin
 * @author            Dr Soe Paing
 * @copyright         2020 SoePaing.com
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Soepaing Plugin
 * Plugin URI:        https://soepaing.com
 * Description:       This is my first attempt
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Dr Soe Paing
 * Author URI:        https://soepaing.com
 * Text Domain:       plugin-slug
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

/*
Copyright (C) 2020  Dr Soe Paing

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

defined('ABSPATH') or die( 'Hey, You can\t access this file, You silly Human!' );

class SoePaing
{
  function activate() {

  }

  function deactivate() {

  }

  function uninstall() {

  }
}

if ( class_exists('SoePaing') ) {
  $soePaing = new SoePaing();
}

//activation
register_activation_hook(__FILE__, array( $soePaing, 'activate' ) );

//deactivation
register_deactivation_hook(__FILE__, array( $soePaing, 'deactivate' ) );

//uninstall
