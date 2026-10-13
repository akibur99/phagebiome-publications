<?php
if (!defined('ABSPATH')) exit;

/** One-click import of the bundled reference dataset. Idempotent via stable keys. */
final class PBP_Importer {
  const ACTION = 'pbp_import_reference';
  const REMOVE_ACTION = 'pbp_remove_imported';

  public static function register() {
    add_action('admin_menu', [__CLASS__, 'menu'], 20);
    add_action('admin_post_' . self::ACTION, [__CLASS__, 'handle']);
    add_action('admin_post_' . self::REMOVE_ACTION, [__CLASS__, 'handle_remove']);
  }

  public static function menu() {
    add_submenu_page(
      'edit.php?post_type=' . PBP_CPT,
      __('Import Reference Data', 'phagebiome-publications'),
      __('Import', 'phagebiome-publications'),
      'manage_options',
      'pbp-import',
      [__CLASS__, 'page']
    );
  }

  public static function data_file() {
    return PBP_DIR . 'data/publications.json';
  }

  public static function load_data() {
    $raw = file_get_contents(self::data_file());
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
  }

  public static function page() {
    if (!current_user_can('manage_options')) wp_die(esc_html__('Not allowed.', 'phagebiome-publications'));
    $result = get_transient('pbp_import_result_' . get_current_user_id());
    if ($result) delete_transient('pbp_import_result_' . get_current_user_id());
    $count = count(self::load_data());
    ?>
    <div class="wrap">
      <h1><?php esc_html_e('Import Reference Publications', 'phagebiome-publications'); ?></h1>
      <p><?php printf(esc_html__('Bundled reference dataset: %d real records transcribed from the original website (%s).', 'phagebiome-publications'), esc_html($count), esc_html__('js/publications.js', 'phagebiome-publications')); ?></p>
      <ul>
        <li><?php esc_html_e('Already-imported records are skipped; your manual edits are never overwritten.', 'phagebiome-publications'); ?></li>
        <li><?php esc_html_e('Complete records are published; incomplete ones are saved as drafts.', 'phagebiome-publications'); ?></li>
      </ul>
      <?php if (is_array($result)) : ?>
        <div class="notice notice-success is-dismissible"><p>
          <?php printf(esc_html__('Imported %1$d, skipped %2$d (already present), saved %3$d as drafts, %4$d invalid.', 'phagebiome-publications'), (int) $result['imported'], (int) $result['skipped'], (int) $result['drafts'], (int) $result['invalid']); ?>
        </p></div>
      <?php endif; ?>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field(self::ACTION, self::ACTION . '_nonce'); ?>
        <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>" />
        <?php submit_button(__('Import reference publications', 'phagebiome-publications'), 'primary'); ?>
      </form>
      <hr />
      <h2><?php esc_html_e('Remove Imported Reference Publications', 'phagebiome-publications'); ?></h2>
      <?php
      $eligible = self::eligible_for_removal();
      $removed = get_transient('pbp_remove_result_' . get_current_user_id());
      if ($removed) delete_transient('pbp_remove_result_' . get_current_user_id());
      ?>
      <p><?php printf(
        esc_html(_n(
          'There is currently %d imported reference publication.',
          'There are currently %d imported reference publications.',
          count($eligible),
          'phagebiome-publications'
        )),
        (int) count($eligible)
      ); ?></p>
      <p><?php esc_html_e('Moves all reference-imported publications, including edited copies, to Trash. Manually added publications are preserved.', 'phagebiome-publications'); ?></p>
      <?php if (is_array($removed)) : ?>
        <div class="notice notice-success is-dismissible"><p>
          <?php printf(esc_html__('%d imported reference publications moved to Trash.', 'phagebiome-publications'), (int) $removed['moved']); ?>
          <?php if ($removed['failed'] > 0) printf(' ' . esc_html__('%d could not be moved (permission or error).', 'phagebiome-publications'), (int) $removed['failed']); ?>
        </p></div>
      <?php endif; ?>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field(self::REMOVE_ACTION, self::REMOVE_ACTION . '_nonce'); ?>
        <input type="hidden" name="action" value="<?php echo esc_attr(self::REMOVE_ACTION); ?>" />
        <?php submit_button(__('Remove All Imported Reference Publications', 'phagebiome-publications'), 'delete', null, false, count($eligible) ? null : ['disabled' => 'disabled']); ?>
      </form>
      <p class="description"><?php esc_html_e('Trashed records can be restored from Publications → Trash. Re-running the importer skips trashed references without restoring or duplicating them.', 'phagebiome-publications'); ?></p>
    </div>
    <?php
  }

  public static function handle() {
    if (!current_user_can('manage_options')) wp_die(esc_html__('Not allowed.', 'phagebiome-publications'));
    check_admin_referer(self::ACTION, self::ACTION . '_nonce');
    $result = self::run();
    set_transient('pbp_import_result_' . get_current_user_id(), $result, 60);
    wp_safe_redirect(add_query_arg(['post_type' => PBP_CPT, 'page' => 'pbp-import'], admin_url('edit.php')));
    exit;
  }

  /**
   * IDs eligible for removal: records carrying the importer's stable reference
   * key (_pbp_import_key) that are not already trashed. Manually created records
   * have no key and are never touched; edited copies keep their key.
   */
  public static function eligible_for_removal() {
    $ids = get_posts([
      'post_type' => PBP_CPT,
      'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
      'numberposts' => -1,
      'fields' => 'ids',
      'meta_query' => [[
        'key' => '_pbp_import_key',
        'value' => '',
        'compare' => '!=',
      ]],
      'orderby' => 'ID',
      'order' => 'ASC',
    ]);
    return array_map('intval', (array) $ids);
  }

  public static function handle_remove() {
    if (!current_user_can('manage_options')) wp_die(esc_html__('Not allowed.', 'phagebiome-publications'));
    check_admin_referer(self::REMOVE_ACTION, self::REMOVE_ACTION . '_nonce');
    $result = self::run_removal();
    set_transient('pbp_remove_result_' . get_current_user_id(), $result, 60);
    wp_safe_redirect(add_query_arg(['post_type' => PBP_CPT, 'page' => 'pbp-import'], admin_url('edit.php')));
    exit;
  }

  /** Move all eligible reference records to Trash. Returns [moved, failed]. */
  public static function run_removal() {
    $result = ['moved' => 0, 'failed' => 0];
    foreach (self::eligible_for_removal() as $pid) {
      if (!current_user_can('delete_post', $pid)) { $result['failed']++; continue; }
      $r = wp_trash_post($pid);
      if ($r) $result['moved']++;
      else $result['failed']++;
    }
    return $result;
  }

  /** Import records. Returns [imported, skipped, drafts, invalid]. Never touches existing posts. */
  public static function run() {
    $result = ['imported' => 0, 'skipped' => 0, 'drafts' => 0, 'invalid' => 0];
    foreach (self::load_data() as $row) {
      $key = isset($row['key']) ? sanitize_key($row['key']) : '';
      $title = isset($row['title']) ? trim(sanitize_text_field($row['title'])) : '';
      $year = isset($row['year']) ? trim((string) $row['year']) : '';
      $journal = isset($row['journal']) ? trim(sanitize_text_field($row['journal'])) : '';
      $summary = isset($row['summary']) ? trim(sanitize_textarea_field($row['summary'])) : '';
      $url = isset($row['url']) ? trim($row['url']) : '';
      $order = isset($row['order']) ? absint($row['order']) : 0;
      if ('' === $key || '' === $title) { $result['invalid']++; continue; }
      $exists = get_posts([
        'post_type' => PBP_CPT, 'post_status' => ['publish', 'draft', 'pending', 'private', 'trash'],
        'meta_key' => '_pbp_import_key', 'meta_value' => $key, 'fields' => 'ids', 'numberposts' => 1,
      ]);
      if ($exists) { $result['skipped']++; continue; }
      $complete = PBP_Meta::valid_year($year) && PBP_Meta::valid_url($url);
      $status = $complete ? 'publish' : 'draft';
      $pid = wp_insert_post([
        'post_type' => PBP_CPT,
        'post_title' => $title,
        'post_status' => $status,
        // No explicit order: published rows receive the next automatic number
        // for their year via the publish transition; drafts on first publish.
        'meta_input' => [
          '_pbp_import_key' => $key,
          '_pbp_year' => PBP_Meta::valid_year($year) ? (string) (int) $year : '',
          '_pbp_journal' => $journal,
          '_pbp_summary' => $summary,
          '_pbp_url' => PBP_Meta::valid_url($url) ? esc_url_raw($url, ['http', 'https']) : '',
          '_pbp_order_manual' => '',
        ],
      ], true);
      if (is_wp_error($pid) || !$pid) { $result['invalid']++; continue; }
      if ('publish' === $status) $result['imported']++;
      else $result['drafts']++;
    }
    return $result;
  }
}
