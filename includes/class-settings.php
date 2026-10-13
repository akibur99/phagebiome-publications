<?php
if (!defined('ABSPATH')) exit;

/** Small settings screen: placeholder, empty message, reset label. */
final class PBP_Settings {
  public static function register() {
    add_action('admin_menu', [__CLASS__, 'menu'], 20);
    add_action('admin_init', [__CLASS__, 'settings']);
  }

  public static function menu() {
    add_submenu_page(
      'edit.php?post_type=' . PBP_CPT,
      __('Publication Display Settings', 'phagebiome-publications'),
      __('Settings', 'phagebiome-publications'),
      'manage_options',
      'pbp-settings',
      [__CLASS__, 'page']
    );
  }

  public static function defaults() {
    return [
      'search_placeholder' => __('Search title, journal, year…', 'phagebiome-publications'),
      'empty_message' => __('No publications found.', 'phagebiome-publications'),
      'reset_label' => __('Reset', 'phagebiome-publications'),
      'year_direction' => 'desc',
      'order_direction' => 'asc',
    ];
  }

  public static function get() {
    return wp_parse_args((array) get_option(PBP_OPTION, []), self::defaults());
  }

  public static function settings() {
    register_setting('pbp_settings_group', PBP_OPTION, [
      'type' => 'array',
      'sanitize_callback' => function ($in) {
        $in = is_array($in) ? $in : [];
        $yd = (isset($in['year_direction']) && 'asc' === $in['year_direction']) ? 'asc' : 'desc';
        $od = (isset($in['order_direction']) && 'desc' === $in['order_direction']) ? 'desc' : 'asc';
        return [
          'search_placeholder' => isset($in['search_placeholder']) ? sanitize_text_field($in['search_placeholder']) : '',
          'empty_message' => isset($in['empty_message']) ? sanitize_text_field($in['empty_message']) : '',
          'reset_label' => isset($in['reset_label']) ? sanitize_text_field($in['reset_label']) : '',
          'year_direction' => $yd,
          'order_direction' => $od,
        ];
      },
    ]);
    add_settings_section('pbp_main', __('Listing interface text', 'phagebiome-publications'), '__return_false', 'pbp-settings');
    foreach (['search_placeholder' => __('Search placeholder', 'phagebiome-publications'), 'empty_message' => __('Empty-results message', 'phagebiome-publications'), 'reset_label' => __('Reset-button label', 'phagebiome-publications')] as $key => $label) {
      add_settings_field($key, $label, function () use ($key) {
        $o = self::get();
        printf('<input type="text" name="%1$s[%2$s]" value="%3$s" class="regular-text" />', esc_attr(PBP_OPTION), esc_attr($key), esc_attr($o[$key]));
      }, 'pbp-settings', 'pbp_main');
    }
    add_settings_section('pbp_sort', __('Publication order', 'phagebiome-publications'), '__return_false', 'pbp-settings');
    add_settings_field('year_direction', __('Publication Year Order', 'phagebiome-publications'), function () {
      $o = self::get();
      printf('<select name="%1$s[year_direction]"><option value="desc"%2$s>%3$s</option><option value="asc"%4$s>%5$s</option></select><p class="description">%6$s</p>',
        esc_attr(PBP_OPTION), selected($o['year_direction'], 'desc', false), esc_html__('Descending: newest year first', 'phagebiome-publications'),
        selected($o['year_direction'], 'asc', false), esc_html__('Ascending: oldest year first', 'phagebiome-publications'),
        esc_html__('Also controls the order of the year-filter buttons.', 'phagebiome-publications'));
    }, 'pbp-settings', 'pbp_sort');
    add_settings_field('order_direction', __('Publication Order Within Each Year', 'phagebiome-publications'), function () {
      $o = self::get();
      printf('<select name="%1$s[order_direction]"><option value="asc"%2$s>%3$s</option><option value="desc"%4$s>%5$s</option></select><p class="description">%6$s</p>',
        esc_attr(PBP_OPTION), selected($o['order_direction'], 'asc', false), esc_html__('Ascending: lower display-order numbers first', 'phagebiome-publications'),
        selected($o['order_direction'], 'desc', false), esc_html__('Descending: higher display-order numbers first', 'phagebiome-publications'),
        esc_html__('Uses the manual override when set, otherwise the automatic number.', 'phagebiome-publications'));
    }, 'pbp-settings', 'pbp_sort');
  }

  public static function page() {
    if (!current_user_can('manage_options')) wp_die(esc_html__('Not allowed.', 'phagebiome-publications'));
    ?>
    <div class="wrap">
      <h1><?php esc_html_e('Publication Display Settings', 'phagebiome-publications'); ?></h1>
      <form method="post" action="options.php">
        <?php settings_fields('pbp_settings_group'); do_settings_sections('pbp-settings'); submit_button(); ?>
      </form>
      <h2><?php esc_html_e('Shortcode', 'phagebiome-publications'); ?></h2>
      <p><?php esc_html_e('Place this shortcode in any page, post, Shortcode block, or Elementor Shortcode widget:', 'phagebiome-publications'); ?></p>
      <p><code>[phagebiome_publications]</code></p>
    </div>
    <?php
  }
}
