<?php
if (!defined('ABSPATH')) exit;

/**
 * Non-public "Publication" content type: editable in admin only.
 * No single pages, no archive, no rewrite rules, excluded from search/sitemaps.
 */
final class PBP_CPT {
  public static function register() {
    add_action('init', [__CLASS__, 'register_post_type'], 5);
  }

  public static function register_post_type() {
    register_post_type(PBP_CPT, [
      'labels' => [
        'name' => __('Publications', 'phagebiome-publications'),
        'singular_name' => __('Publication', 'phagebiome-publications'),
        'add_new' => __('Add New', 'phagebiome-publications'),
        'add_new_item' => __('Add New Publication', 'phagebiome-publications'),
        'edit_item' => __('Edit Publication', 'phagebiome-publications'),
        'new_item' => __('New Publication', 'phagebiome-publications'),
        'view_item' => __('View Publication', 'phagebiome-publications'),
        'search_items' => __('Search Publications', 'phagebiome-publications'),
        'not_found' => __('No publications found.', 'phagebiome-publications'),
        'not_found_in_trash' => __('No publications found in Trash.', 'phagebiome-publications'),
        'all_items' => __('All Publications', 'phagebiome-publications'),
        'menu_name' => __('Publications', 'phagebiome-publications'),
      ],
      'public' => false,
      'publicly_queryable' => false,
      'exclude_from_search' => true,
      'show_ui' => true,
      'show_in_menu' => true,
      'show_in_nav_menus' => false,
      'show_in_admin_bar' => false,
      'show_in_rest' => false,
      'has_archive' => false,
      'rewrite' => false,
      'query_var' => false,
      'capability_type' => 'post',
      'map_meta_cap' => true,
      'hierarchical' => false,
      'supports' => ['title'],
      'menu_icon' => 'dashicons-media-document',
      'delete_with_user' => false,
    ]);
  }
}
