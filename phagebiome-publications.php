<?php
/**
 * Plugin Name: Phagebiome Publications
 * Description: Admin-managed publication listing with search and year filters. Use the [phagebiome_publications] shortcode.
 * Version: 1.0.5
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: InsoSpark Agency
 * Author URI: https://insospark.com
 * Plugin URI: https://insospark.com
 * License: GPL v2 or later
 * Text Domain: phagebiome-publications
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

define('PBP_VERSION', '1.0.5');
define('PBP_DIR', plugin_dir_path(__FILE__));
define('PBP_URL', plugin_dir_url(__FILE__));
define('PBP_CPT', 'phage_pub');
define('PBP_OPTION', 'pbp_settings');

require_once PBP_DIR . 'includes/class-cpt.php';
require_once PBP_DIR . 'includes/class-meta.php';
require_once PBP_DIR . 'includes/class-order.php';
require_once PBP_DIR . 'includes/class-admin.php';
require_once PBP_DIR . 'includes/class-importer.php';
require_once PBP_DIR . 'includes/class-settings.php';
require_once PBP_DIR . 'includes/class-shortcode.php';

add_action('plugins_loaded', function () {
  load_plugin_textdomain('phagebiome-publications', false, dirname(plugin_basename(__FILE__)) . '/languages');
  PBP_CPT::register();
  PBP_Meta::register();
  PBP_Order::register();
  PBP_Admin::register();
  PBP_Importer::register();
  PBP_Settings::register();
  PBP_Shortcode::register();
  add_action('admin_notices', ['PBP_Order', 'migration_notice']);
});

register_activation_hook(__FILE__, function () {
  PBP_CPT::register();
  flush_rewrite_rules(false);
  if (false === get_option(PBP_OPTION)) {
    add_option(PBP_OPTION, [
      'search_placeholder' => __('Search title, journal, year…', 'phagebiome-publications'),
      'empty_message' => __('No publications found.', 'phagebiome-publications'),
      'reset_label' => __('Reset', 'phagebiome-publications'),
    ]);
  }
});

register_deactivation_hook(__FILE__, function () {
  flush_rewrite_rules(false);
});
