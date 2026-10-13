<?php
if (!defined('ABSPATH')) exit;

/**
 * Meta box + validation for publications.
 * Required to publish: title (core), sensible 4-digit year, valid http(s) URL.
 * Drafts may be incomplete; entered values are always preserved.
 */
final class PBP_Meta {
  const NONCE = 'pbp_meta_nonce';

  public static function register() {
    add_action('add_meta_boxes_' . PBP_CPT, [__CLASS__, 'add_boxes']);
    add_filter('enter_title_here', [__CLASS__, 'title_placeholder'], 10, 2);
    add_filter('wp_insert_post_data', [__CLASS__, 'enforce_publish_requirements'], 10, 2);
    add_action('save_post_' . PBP_CPT, [__CLASS__, 'save'], 10, 2);
    add_action('admin_notices', [__CLASS__, 'notices']);
  }

  /** Proper meta box so fields render inside a postbox under the title. */
  public static function add_boxes($post) {
    add_meta_box(
      'pbp-details',
      __('Publication Details', 'phagebiome-publications'),
      [__CLASS__, 'box'],
      PBP_CPT,
      'normal',
      'high'
    );
  }

  public static function title_placeholder($text, $post) {
    if ($post instanceof WP_Post && PBP_CPT === $post->post_type) {
      return __('Enter publication title', 'phagebiome-publications');
    }
    return $text;
  }

  public static function box($post) {
    if (!$post instanceof WP_Post) return;
    wp_nonce_field(self::NONCE, self::NONCE);
    $year = get_post_meta($post->ID, '_pbp_year', true);
    $journal = get_post_meta($post->ID, '_pbp_journal', true);
    $summary = get_post_meta($post->ID, '_pbp_summary', true);
    $url = get_post_meta($post->ID, '_pbp_url', true);
    $manual = get_post_meta($post->ID, '_pbp_order_manual', true);
    $auto = get_post_meta($post->ID, '_pbp_order_auto', true);
    $is_new = 'auto-draft' === $post->post_status;
    ?>
    <div class="pbp-fields">
      <div class="pbp-field">
        <label for="pbp_year"><?php esc_html_e('Publication Year', 'phagebiome-publications'); ?> <span class="pbp-required" aria-hidden="true">*</span></label>
        <input name="pbp_year" id="pbp_year" type="number" min="1900" max="2100" step="1" placeholder="2026" value="<?php echo esc_attr($year); ?>" class="small-text" required aria-required="true" />
        <p class="description"><?php esc_html_e('The year the paper was published (four digits, e.g. 2026). Required before publishing. This is not the WordPress post date.', 'phagebiome-publications'); ?></p>
      </div>
      <div class="pbp-field">
        <label for="pbp_journal"><?php esc_html_e('Journal Name', 'phagebiome-publications'); ?></label>
        <input name="pbp_journal" id="pbp_journal" type="text" placeholder="<?php esc_attr_e('Enter journal or publisher name', 'phagebiome-publications'); ?>" value="<?php echo esc_attr($journal); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e('The journal or publisher name shown under the title.', 'phagebiome-publications'); ?></p>
      </div>
      <div class="pbp-field">
        <label for="pbp_summary"><?php esc_html_e('Short Description', 'phagebiome-publications'); ?></label>
        <textarea name="pbp_summary" id="pbp_summary" rows="4" cols="50" class="large-text" placeholder="<?php esc_attr_e('Enter a short description', 'phagebiome-publications'); ?>"><?php echo esc_textarea($summary); ?></textarea>
        <p class="description"><?php esc_html_e('A brief summary or additional publication information, shown after the journal name.', 'phagebiome-publications'); ?></p>
      </div>
      <div class="pbp-field">
        <label for="pbp_url"><?php esc_html_e('External Publication URL', 'phagebiome-publications'); ?> <span class="pbp-required" aria-hidden="true">*</span></label>
        <input name="pbp_url" id="pbp_url" type="url" inputmode="url" placeholder="https://doi.org/10.xxxx/example" value="<?php echo esc_attr($url); ?>" class="regular-text code" required aria-required="true" />
        <p class="description"><?php esc_html_e('Direct link to the publisher, DOI, or publication page. Must start with http:// or https://. Required before publishing.', 'phagebiome-publications'); ?></p>
      </div>
      <div class="pbp-field pbp-field-order">
        <span class="pbp-order-status" role="status">
          <?php if ($is_new || ('' === $manual && '' === $auto)) : ?>
            <?php esc_html_e('Assigned when published', 'phagebiome-publications'); ?>
          <?php elseif ('' !== $manual) : ?>
            <?php printf(esc_html__('Manual ordering active: %d', 'phagebiome-publications'), (int) $manual); ?>
          <?php else : ?>
            <?php printf(esc_html__('Automatic order: %d', 'phagebiome-publications'), (int) $auto); ?>
          <?php endif; ?>
        </span>
        <label for="pbp_order_manual"><?php esc_html_e('Display Order', 'phagebiome-publications'); ?></label>
        <input name="pbp_order_manual" id="pbp_order_manual" type="number" min="0" step="1" placeholder="<?php esc_attr_e('Automatic', 'phagebiome-publications'); ?>" value="<?php echo ('' === $manual) ? '' : esc_attr((int) $manual); ?>" class="small-text" />
        <p class="description"><?php esc_html_e('Assigned automatically when first published. Enter a number to override the automatic order.', 'phagebiome-publications'); ?></p>
        <?php if ('' !== $manual) : ?>
          <p><button type="submit" name="pbp_reset_auto" value="1" class="button button-secondary"><?php esc_html_e('Reset to automatic', 'phagebiome-publications'); ?></button></p>
        <?php endif; ?>
      </div>
    </div>
    <?php
  }

  public static function valid_year($year) {
    return (bool) preg_match('/^(19|20)\d{2}$/', (string) $year) && (int) $year >= 1900 && (int) $year <= 2100;
  }

  public static function valid_url($url) {
    $url = trim((string) $url);
    if ('' === $url) return false;
    $clean = esc_url_raw($url, ['http', 'https']);
    if ('' === $clean) return false;
    $scheme = strtolower((string) parse_url($clean, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) && false !== filter_var($clean, FILTER_VALIDATE_URL);
  }

  private static function posted_or_stored($post_id, $key, $meta_key, $postarr = []) {
    if (isset($_POST[$key])) return is_string($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
    if (isset($postarr['meta_input'][$meta_key])) return (string) $postarr['meta_input'][$meta_key];
    return $post_id ? (string) get_post_meta($post_id, $meta_key, true) : '';
  }

  public static function enforce_publish_requirements($data, $postarr) {
    if (PBP_CPT !== ($data['post_type'] ?? '') && PBP_CPT !== get_post_type($postarr['ID'] ?? 0)) return $data;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return $data;
    $post_id = (int) ($postarr['ID'] ?? 0);
    $new_status = $data['post_status'] ?? '';
    if (!in_array($new_status, ['publish', 'future', 'private'], true)) return $data;
    $can = $post_id ? current_user_can('edit_post', $post_id) : current_user_can('edit_posts');
    if (!$can) return $data;
    $year = trim(self::posted_or_stored($post_id, 'pbp_year', '_pbp_year', $postarr));
    $url = trim(self::posted_or_stored($post_id, 'pbp_url', '_pbp_url', $postarr));
    $problems = [];
    if ('' === $year || !self::valid_year($year)) $problems[] = __('a valid 4-digit publication year', 'phagebiome-publications');
    if ('' === trim($postarr['post_title'] ?? ($post_id ? get_the_title($post_id) : ''))) $problems[] = __('a publication title', 'phagebiome-publications');
    if (!self::valid_url($url)) $problems[] = __('a valid http(s) external URL', 'phagebiome-publications');
    if ($problems) {
      $data['post_status'] = 'draft';
      $msg = sprintf(
        /* translators: %s: list of missing items */
        __('Not published — please provide %s. Your entries were kept as a draft.', 'phagebiome-publications'),
        implode(', ', $problems)
      );
      if ($post_id) update_post_meta($post_id, '_pbp_admin_error', $msg);
      else set_transient('pbp_pending_err_' . get_current_user_id(), $msg, 300);
    }
    return $data;
  }

  /** Attach pending publish-block errors (new posts) to the real post ID. */
  public static function flush_pending_error($post_id, $post) {
    if (PBP_CPT !== $post->post_type) return;
    $key = 'pbp_pending_err_' . get_current_user_id();
    $msg = get_transient($key);
    if ($msg) {
      delete_transient($key);
      update_post_meta($post_id, '_pbp_admin_error', $msg);
    }
  }

  public static function save($post_id, $post) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
    self::flush_pending_error($post_id, $post);
    if (!isset($_POST[self::NONCE]) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST[self::NONCE])), self::NONCE)) return;
    if (!current_user_can('edit_post', $post_id)) return;
    $old_year = (string) get_post_meta($post_id, '_pbp_year', true);
    $year = isset($_POST['pbp_year']) ? trim(sanitize_text_field(wp_unslash($_POST['pbp_year']))) : '';
    if ('' !== $year && !self::valid_year($year)) {
      update_post_meta($post_id, '_pbp_admin_error', __('The year must be a sensible four-digit value (1900–2100). Previous value kept.', 'phagebiome-publications'));
    } else {
      update_post_meta($post_id, '_pbp_year', $year);
    }
    update_post_meta($post_id, '_pbp_journal', isset($_POST['pbp_journal']) ? sanitize_text_field(wp_unslash($_POST['pbp_journal'])) : '');
    update_post_meta($post_id, '_pbp_summary', isset($_POST['pbp_summary']) ? sanitize_textarea_field(wp_unslash($_POST['pbp_summary'])) : '');
    $url = isset($_POST['pbp_url']) ? trim(wp_unslash($_POST['pbp_url'])) : '';
    if ('' !== $url && !self::valid_url($url)) {
      update_post_meta($post_id, '_pbp_admin_error', __('That URL is not a valid http(s) address. Previous value kept; drafts may stay incomplete.', 'phagebiome-publications'));
    } else {
      update_post_meta($post_id, '_pbp_url', $url ? esc_url_raw($url, ['http', 'https']) : '');
    }
    // Manual override: only the dedicated input writes it; an empty field clears back to automatic.
    if (isset($_POST['pbp_reset_auto'])) {
      update_post_meta($post_id, '_pbp_order_manual', '');
    } elseif (array_key_exists('pbp_order_manual', $_POST)) {
      $m = trim(wp_unslash($_POST['pbp_order_manual']));
      update_post_meta($post_id, '_pbp_order_manual', ('' === $m) ? '' : absint($m));
    }
    // Year change with no manual override: take the next number in the destination year.
    // ($posted_year defaults to $old_year when the field was not submitted, so plain edits never trigger this.)
    $posted_year = isset($_POST['pbp_year']) ? trim(sanitize_text_field(wp_unslash($_POST['pbp_year']))) : $old_year;
    if ($posted_year !== $old_year && '' === get_post_meta($post_id, '_pbp_order_manual', true) && self::valid_year($posted_year)) {
      delete_post_meta($post_id, '_pbp_order_auto');
      PBP_Order::maybe_assign($post_id);
    }
    PBP_Order::refresh_mirror($post_id);
  }

  public static function notices() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || PBP_CPT !== $screen->post_type || 'post' !== $screen->base) return;
    global $post;
    if (!$post) return;
    $msg = get_post_meta($post->ID, '_pbp_admin_error', true);
    if ($msg) {
      delete_post_meta($post->ID, '_pbp_admin_error');
      printf('<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html($msg));
    }
  }
}
