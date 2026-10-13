<?php
if (!defined('ABSPATH')) exit;

/**
 * Automatic display ordering.
 *
 * Stored keys:
 * - _pbp_order_manual  user override ('' = automatic)
 * - _pbp_order_auto    assigned increasing integer per Publication Year
 * - _pbp_first_published Unix timestamp of first publish (chronology + tie-break)
 * - _pbp_order         EFFECTIVE mirror (manual if set, else auto) — powers SQL sorting
 * - _pbp_order_legacy  pre-migration archive of _pbp_order
 *
 * Effective order = manual override when supplied, otherwise automatic number.
 */
final class PBP_Order {
  public static function register() {
    add_action('transition_post_status', [__CLASS__, 'on_transition'], 10, 3);
    add_action('admin_init', [__CLASS__, 'maybe_migrate']);
  }

  public static function auto_of($post_id) {
    $v = get_post_meta($post_id, '_pbp_order_auto', true);
    return '' === $v ? '' : (int) $v;
  }

  public static function manual_of($post_id) {
    $v = get_post_meta($post_id, '_pbp_order_manual', true);
    return '' === $v ? '' : (int) $v;
  }

  public static function first_published_of($post_id) {
    $v = (int) get_post_meta($post_id, '_pbp_first_published', true);
    if ($v > 0) return $v;
    $p = get_post($post_id);
    return ($p && $p->post_date) ? (int) strtotime($p->post_date) : 0;
  }

  /** Effective order with legacy fallback. Never discards data silently. */
  public static function effective_of($post_id) {
    $manual = get_post_meta($post_id, '_pbp_order_manual', true);
    if ('' !== $manual) return (int) $manual;
    $auto = get_post_meta($post_id, '_pbp_order_auto', true);
    if ('' !== $auto) return (int) $auto;
    $legacy = get_post_meta($post_id, '_pbp_order', true);
    if ('' !== $legacy && is_numeric($legacy)) return (int) $legacy;
    return 999999;
  }

  public static function refresh_mirror($post_id) {
    update_post_meta($post_id, '_pbp_order', self::effective_of($post_id));
  }

  /**
   * Assign the next automatic number within the record's year.
   * The next number continues the visible sequence: one above the highest
   * effective number (manual overrides included), so after 1, 2, 3 comes 4.
   * Drafts/autosaves never reach here (transition hook only fires into publish).
   * Simultaneous publishes could theoretically collide; ties stay deterministic
   * via first_published + ID ordering, so display order never breaks.
   */
  public static function maybe_assign($post_id) {
    if ('' !== get_post_meta($post_id, '_pbp_order_auto', true)) return (int) get_post_meta($post_id, '_pbp_order_auto', true);
    $year = (string) get_post_meta($post_id, '_pbp_year', true);
    if (!PBP_Meta::valid_year($year)) return 0;
    $next = self::next_in_year($year, $post_id);
    update_post_meta($post_id, '_pbp_order_auto', $next);
    if (!get_post_meta($post_id, '_pbp_first_published', true)) {
      update_post_meta($post_id, '_pbp_first_published', time());
    }
    self::refresh_mirror($post_id);
    return $next;
  }

  /** Highest effective number in a year (manuals, autos, legacy), excluding one record. */
  public static function max_in_year($year, $exclude = 0) {
    $posts = get_posts([
      'post_type' => PBP_CPT, 'post_status' => ['publish', 'private', 'future'],
      'numberposts' => -1, 'fields' => 'ids',
      'meta_key' => '_pbp_year', 'meta_value' => $year,
      'no_found_rows' => true, 'update_post_term_cache' => false,
    ]);
    $max = 0;
    foreach ($posts as $pid) {
      if ((int) $pid === (int) $exclude) continue;
      foreach (['_pbp_order_manual', '_pbp_order_auto', '_pbp_order'] as $k) {
        $v = get_post_meta($pid, $k, true);
        if ('' !== $v && is_numeric($v) && (int) $v > $max) $max = (int) $v;
      }
    }
    return $max;
  }

  public static function next_in_year($year, $exclude = 0) {
    return self::max_in_year($year, $exclude) + 1;
  }

  public static function on_transition($new, $old, $post) {
    if (PBP_CPT !== $post->post_type) return;
    if ('publish' !== $new) return;
    if (!(defined('DOING_CRON') && DOING_CRON) && !current_user_can('edit_post', $post->ID)) return;
    self::maybe_assign($post->ID);
  }

  /** Reassign when the year changed and no manual override exists. */
  public static function reassign_on_year_change($post_id, $new_year) {
    if ('' !== get_post_meta($post_id, '_pbp_order_manual', true)) {
      self::refresh_mirror($post_id);
      return;
    }
    delete_post_meta($post_id, '_pbp_order_auto');
    if (PBP_Meta::valid_year($new_year)) self::maybe_assign($post_id);
    else self::refresh_mirror($post_id);
  }

  /**
   * One-time migration (flag: pbp_order_migrated_102).
   * Rule (documented): legacy _pbp_order values that are non-empty and non-zero
   * are preserved as MANUAL overrides; '0'/empty become automatic. Originals are
   * archived in _pbp_order_legacy. Groups by year, oldest post_date first, ID tie-break.
   */
  public static function maybe_migrate() {
    if (!current_user_can('manage_options')) return;
    if ('1' === get_option('pbp_order_migrated_102', '')) return;
    $ids = get_posts(['post_type' => PBP_CPT, 'post_status' => ['publish', 'private', 'future'], 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC']);
    $groups = [];
    foreach ($ids as $pid) {
      $legacy = get_post_meta($pid, '_pbp_order_legacy', true);
      if ('' === $legacy) {
        $orig = get_post_meta($pid, '_pbp_order', true);
        update_post_meta($pid, '_pbp_order_legacy', '' === $orig ? '' : (string) $orig);
      }
      $manual = '';
      $orig = get_post_meta($pid, '_pbp_order_legacy', true);
      if ('' !== $orig && '0' !== (string) $orig && is_numeric($orig)) $manual = (int) $orig;
      update_post_meta($pid, '_pbp_order_manual', '' === $manual ? '' : $manual);
      $p = get_post($pid);
      $ts = ($p && $p->post_date) ? strtotime($p->post_date) : 0;
      if (!get_post_meta($pid, '_pbp_first_published', true)) update_post_meta($pid, '_pbp_first_published', $ts);
      $year = (string) get_post_meta($pid, '_pbp_year', true);
      if (!PBP_Meta::valid_year($year)) $year = '0000';
      $groups[$year][] = ['id' => $pid, 'ts' => $ts];
    }
    $auto_total = 0;
    $manual_total = 0;
    foreach ($groups as $members) {
      usort($members, function ($a, $b) {
        if ($a['ts'] !== $b['ts']) return $a['ts'] - $b['ts'];
        return $a['id'] - $b['id'];
      });
      $used = [];
      foreach ($members as $m) {
        $man = get_post_meta($m['id'], '_pbp_order_manual', true);
        if ('' !== $man) $used[(int) $man] = true;
      }
      $next = $used ? max(array_keys($used)) + 1 : 1;
      foreach ($members as $m) {
        if ('' === get_post_meta($m['id'], '_pbp_order_manual', true)) {
          while (isset($used[$next])) $next++;
          update_post_meta($m['id'], '_pbp_order_auto', $next);
          $used[$next] = true;
          $next++;
          $auto_total++;
        } else $manual_total++;
        self::refresh_mirror($m['id']);
      }
    }
    update_option('pbp_order_migrated_102', '1', false);
    set_transient('pbp_migrate_notice_' . get_current_user_id(), [$auto_total, $manual_total, count($ids)], 120);
  }

  public static function migration_notice() {
    $n = get_transient('pbp_migrate_notice_' . get_current_user_id());
    if (!$n) return;
    delete_transient('pbp_migrate_notice_' . get_current_user_id());
    printf(
      '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
      esc_html(sprintf(
        /* translators: 1: auto count, 2: manual count, 3: total */
        __('Display-order migration complete: %1$d automatic, %2$d manual preserved, %3$d records total. Originals archived per record.', 'phagebiome-publications'),
        (int) $n[0], (int) $n[1], (int) $n[2]
      ))
    );
  }
}
