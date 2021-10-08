<?php
/*
Plugin Name: Whitepaper Roundup
Plugin URI: https://carmelosantana.com
Description: Parse whitepapers and other blockchain documents.
Version: 0.1.0
Author: Carmelo Santana
Author URI: https://carmelosantana.com
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

/**
 * Composer
 */
if (!file_exists($composer = plugin_dir_path(__FILE__) . '/vendor/autoload.php')) {
    wp_die(__('Error locating autoloader. Please run <code>composer install</code>.', 'whitepaper-roundup'));
}
require $composer;

new \carmelosantana\WhitepaperRoundup\WhitepaperRoundup();
