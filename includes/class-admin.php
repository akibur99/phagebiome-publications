<?php
if (!defined('ABSPATH')) exit;

/** Admin list table: columns, sorting, year filter. */
final class PBP_Admin {
  public static function register() {
    add_filter('manage_' . PBP_CPT . '_posts_columns', [__CLASS__, 'columns']);
    add_action('manage_' . PBP_CPT . '_posts_custom_column', [__CLASS__, 'column_content'], 10, 2);
    add_filter('manage_edit-' . PBP_CPT . '_sortable_columns', [__CLASS__, 'sortable']);
    add_action('pre_get_posts', [__CLASS__, 'sort_query']);
    add_action('restrict_manage_posts', [__CLASS__, 'year_filter']);
    add_filter('query_vars', [__CLASS__, 'query_var']);
    add_action('pre_get_posts', [__CLASS__, 'filter_query']);
    add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
    add_action('admin_menu', [__CLASS__, 'reorder_menu'], 999);
  }

  /** Exact submenu order, duplicates removed: All, Add New, Import, Settings. */
  public static function reorder_menu() {
    global $submenu;
    $parent = 'edit.php?post_type=' . PBP_CPT;
    if (empty($submenu[$parent])) return;
    $by_slug = [];
    foreach ($submenu[$parent] as $item) {
      $slug = $item[2] ?? '';
      if ('' === $slug || isset($by_slug[$slug])) continue;
      $by_slug[$slug] = $item;
    }
    $want = [$parent, 'post-new.php?post_type=' . PBP_CPT, 'pbp-import', 'pbp-settings'];
    $ordered = [];
    foreach ($want as $slug) {
      if (isset($by_slug[$slug])) { $ordered[] = $by_slug[$slug]; unset($by_slug[$slug]); }
    }
    foreach ($by_slug as $item) $ordered[] = $item;
    $submenu[$parent] = array_values($ordered);
  }

  public static function assets($hook) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen) return;
    $on_cpt = PBP_CPT === $screen->post_type;
    $on_page = in_array($screen->id, ['phage_pub_page_pbp-import', 'phage_pub_page_pbp-settings'], true);
    if ($on_cpt || $on_page) {
      wp_enqueue_style('pbp-admin', PBP_URL . 'assets/css/admin.css', [], PBP_VERSION);
    }
  }

  public static function columns($cols) {
    $out = [];
    $out['cb'] = $cols['cb'] ?? '';
    $out['title'] = __('Title', 'phagebiome-publications');
    $out['pbp_year'] = __('Year', 'phagebiome-publications');
    $out['pbp_journal'] = __('Journal', 'phagebiome-publications');
    $out['pbp_order'] = __('Display order', 'phagebiome-publications');
    $out['pbp_url'] = __('External URL', 'phagebiome-publications');
    $out['date'] = $cols['date'] ?? '';
    return $out;
  }

  public static function column_content($col, $post_id) {
    switch ($col) {
      case 'pbp_year':
        echo esc_html(get_post_meta($post_id, '_pbp_year', true));
        break;
      case 'pbp_journal':
        echo esc_html(get_post_meta($post_id, '_pbp_journal', true));
        break;
      case 'pbp_order':
        $manual = get_post_meta($post_id, '_pbp_order_manual', true);
        $eff = PBP_Order::effective_of($post_id);
        printf('%s <span style="color:#646970">(%s)</span>', esc_html((string) $eff), '' !== $manual ? esc_html__('manual', 'phagebiome-publications') : esc_html__('auto', 'phagebiome-publications'));
        break;
      case 'pbp_url':
        $u = get_post_meta($post_id, '_pbp_url', true);
        if ($u) printf('<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_url($u), esc_html__('Open ↗', 'phagebiome-publications'));
        else echo '—';
        break;
    }
  }

  public static function sortable($cols) {
    $cols['pbp_year'] = 'pbp_year';
    $cols['pbp_order'] = 'pbp_order';
    return $cols;
  }

  public static function sort_query($q) {
    if (!is_admin() || !$q->is_main_query() || PBP_CPT !== $q->get('post_type')) return;
    $ob = $q->get('orderby');
    if ('pbp_year' === $ob) {
      $q->set('meta_key', '_pbp_year');
      $q->set('orderby', 'meta_value_num');
    } elseif ('pbp_order' === $ob) {
      $q->set('meta_key', '_pbp_order');
      $q->set('orderby', 'meta_value_num');
    }
  }

  public static function query_var($vars) {
    $vars[] = 'pbp_year_filter';
    return $vars;
  }

  public static function years() {
    global $wpdb;
    $rows = $wpdb->get_col($wpdb->prepare(
      "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = %s AND p.post_type = %s AND p.post_status = %s ORDER BY meta_value+0 DESC",
      '_pbp_year', PBP_CPT, 'publish'
    ));
    return array_values(array_filter($rows, function ($y) { return PBP_Meta::valid_year($y); }));
  }

  public static function year_filter($post_type) {
    if (PBP_CPT !== $post_type) return;
    $years = self::years();
    if (!$years) return;
    $cur = isset($_GET['pbp_year_filter']) ? sanitize_text_field(wp_unslash($_GET['pbp_year_filter'])) : '';
    echo '<select name="pbp_year_filter"><option value="">' . esc_html__('All years', 'phagebiome-publications') . '</option>';
    foreach ($years as $y) printf('<option value="%s"%s>%s</option>', esc_attr($y), selected($cur, $y, false), esc_html($y));
    echo '</select>';
  }

  public static function filter_query($q) {
    if (!is_admin() || !$q->is_main_query() || PBP_CPT !== $q->get('post_type')) return;
    $y = $q->get('pbp_year_filter');
    if ($y && PBP_Meta::valid_year($y)) {
      $mq = (array) $q->get('meta_query');
      $mq[] = ['key' => '_pbp_year', 'value' => $y, 'compare' => '='];
      $q->set('meta_query', $mq);
    }
  }
}
