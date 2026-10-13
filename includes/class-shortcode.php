<?php
if (!defined('ABSPATH')) exit;

/** [phagebiome_publications] — server-rendered listing, JS-enhanced, no-JS fallback. */
final class PBP_Shortcode {
  private static $instances = 0;
  private static $rendered = false;

  public static function register() {
    add_shortcode('phagebiome_publications', [__CLASS__, 'render']);
    add_action('init', [__CLASS__, 'register_assets']);
    add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_enqueue']);
    add_action('wp_footer', [__CLASS__, 'footer_fallback'], 20);
  }

  public static function register_assets() {
    wp_register_style('pbp-frontend', PBP_URL . 'assets/css/frontend.css', [], PBP_VERSION);
    wp_register_script('pbp-frontend', PBP_URL . 'assets/js/frontend.js', [], PBP_VERSION, true);
  }

  /** Early detection: post content, Elementor data, or Elementor preview/edit. */
  public static function maybe_enqueue() {
    if (self::detect()) self::enqueue();
  }

  public static function footer_fallback() {
    if (self::$rendered && !wp_style_is('pbp-frontend', 'enqueued')) {
      wp_print_styles('pbp-frontend');
    }
    if (self::$rendered && !wp_script_is('pbp-frontend', 'enqueued')) {
      wp_print_scripts('pbp-frontend');
    }
  }

  private static function detect() {
    if (is_admin()) return false;
    if (isset($_GET['elementor-preview'])) return true;
    if (!is_singular()) return false;
    $post = get_queried_object();
    if (!$post instanceof WP_Post) return false;
    if (has_shortcode($post->post_content, 'phagebiome_publications')) return true;
    $edata = get_post_meta($post->ID, '_elementor_data', true);
    if (is_string($edata) && false !== stripos($edata, 'phagebiome_publications')) return true;
    return false;
  }

  private static function enqueue() {
    wp_enqueue_style('pbp-frontend');
    wp_enqueue_script('pbp-frontend');
  }

  /** Published pubs sorted: year (setting direction), effective order (setting direction), first-published + ID tie-break. */
  public static function all() {
    $posts = get_posts([
      'post_type' => PBP_CPT, 'post_status' => 'publish', 'numberposts' => -1,
      'no_found_rows' => true, 'update_post_term_cache' => false,
    ]);
    $rows = [];
    foreach ($posts as $p) {
      $rows[] = [
        'id' => $p->ID,
        'title' => $p->post_title,
        'year' => (string) get_post_meta($p->ID, '_pbp_year', true),
        'journal' => (string) get_post_meta($p->ID, '_pbp_journal', true),
        'summary' => (string) get_post_meta($p->ID, '_pbp_summary', true),
        'url' => (string) get_post_meta($p->ID, '_pbp_url', true),
        'order' => PBP_Order::effective_of($p->ID),
        'first' => PBP_Order::first_published_of($p->ID),
      ];
    }
    $opt = PBP_Settings::get();
    $yd = ('asc' === $opt['year_direction']) ? 1 : -1;
    $od = ('desc' === $opt['order_direction']) ? -1 : 1;
    usort($rows, function ($a, $b) use ($yd, $od) {
      $yc = ((int) $a['year'] - (int) $b['year']) * $yd;
      if ($yc) return $yc;
      if ($a['order'] !== $b['order']) return ($a['order'] - $b['order']) * $od;
      if ($a['first'] !== $b['first']) return $a['first'] - $b['first'];
      $t = strcasecmp($a['title'], $b['title']);
      if ($t) return $t;
      return $a['id'] - $b['id'];
    });
    return $rows;
  }

  public static function years($rows) {
    $ys = [];
    foreach ($rows as $r) if (PBP_Meta::valid_year($r['year'])) $ys[$r['year']] = true;
    $ys = array_keys($ys);
    $opt = PBP_Settings::get();
    if ('asc' === $opt['year_direction']) sort($ys, SORT_NUMERIC);
    else rsort($ys, SORT_NUMERIC);
    return $ys;
  }

  public static function render($atts) {
    self::$instances++;
    self::$rendered = true;
    self::enqueue();
    $n = self::$instances;
    $opt = PBP_Settings::get();
    $rows = self::all();
    $total = count($rows);
    $qid = 'pbp' . $n . '_q';
    $yid = 'pbp' . $n . '_y';
    $q = isset($_GET[$qid]) ? trim(sanitize_text_field(wp_unslash($_GET[$qid]))) : '';
    $yraw = isset($_GET[$yid]) ? sanitize_text_field(wp_unslash($_GET[$yid])) : 'all';
    $year = ('all' === $yraw || PBP_Meta::valid_year($yraw)) ? $yraw : 'all';
    $years = self::years($rows);
    ob_start();
    ?>
    <div class="phagebiome-publications" data-pbp-instance="<?php echo esc_attr($n); ?>" data-pbp-total="<?php echo esc_attr($total); ?>">
      <?php if (0 === $total) : ?>
        <p class="pbp-empty"><?php echo esc_html($opt['empty_message']); ?></p>
      <?php else : ?>
      <?php
      $shown = 0;
      foreach ($rows as $r) if (self::matches($r, $q, $year)) $shown++;
      $base = is_singular() ? get_permalink() : home_url('/');
      if (!$base) $base = home_url('/');
      $action = esc_url(remove_query_arg([$qid, $yid], $base));
      ?>
      <form class="pbp-search-form" method="get" action="<?php echo $action; ?>" role="search" aria-label="<?php esc_attr_e('Search publications', 'phagebiome-publications'); ?>">
        <div class="pbp-search">
          <span class="pbp-search-icon" aria-hidden="true">⌕</span>
          <input type="search" name="<?php echo esc_attr($qid); ?>" value="<?php echo esc_attr($q); ?>"
            placeholder="<?php echo esc_attr($opt['search_placeholder']); ?>"
            aria-label="<?php echo esc_attr($opt['search_placeholder']); ?>" autocomplete="off" />
        </div>
        <div class="pbp-filters" role="group" aria-label="<?php esc_attr_e('Filter by year', 'phagebiome-publications'); ?>">
          <button type="submit" name="<?php echo esc_attr($yid); ?>" value="all" class="pbp-fbtn<?php echo 'all' === $year ? ' is-active' : ''; ?>" aria-pressed="<?php echo 'all' === $year ? 'true' : 'false'; ?>"><?php esc_html_e('All', 'phagebiome-publications'); ?></button>
          <?php foreach ($years as $yy) : ?>
            <button type="submit" name="<?php echo esc_attr($yid); ?>" value="<?php echo esc_attr($yy); ?>" class="pbp-fbtn<?php echo $yy === $year ? ' is-active' : ''; ?>" aria-pressed="<?php echo $yy === $year ? 'true' : 'false'; ?>"><?php echo esc_html($yy); ?></button>
          <?php endforeach; ?>
        </div>
      </form>
      <p class="pbp-count" aria-live="polite"><?php printf(esc_html__('Showing %1$d of %2$d publications', 'phagebiome-publications'), (int) $shown, (int) $total); ?></p>
      <div class="pbp-list">
        <?php foreach ($rows as $r) :
          $match = self::matches($r, $q, $year);
          $meta = $r['journal'] . ('' !== $r['summary'] ? ' · ' . $r['summary'] : '');
          $hay = strtolower(implode(' ', [$r['title'], $r['journal'], $r['year'], $r['summary']]));
        ?>
        <article class="pbp-row" data-year="<?php echo esc_attr($r['year']); ?>" data-search="<?php echo esc_attr($hay); ?>"<?php echo $match ? '' : ' hidden'; ?>>
          <div class="pbp-year"><?php echo esc_html($r['year']); ?></div>
          <div class="pbp-body">
            <h4 class="pbp-title"><a class="pbp-title-link" href="<?php echo esc_url($r['url'], ['http', 'https']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($r['title']); ?><span class="screen-reader-text"> <?php esc_html_e('(opens in new tab)', 'phagebiome-publications'); ?></span></a></h4>
            <?php if ('' !== $meta) : ?><p class="pbp-meta"><?php echo esc_html($meta); ?></p><?php endif; ?>
          </div>
          <a class="pbp-ext" href="<?php echo esc_url($r['url'], ['http', 'https']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php printf(esc_attr__('Open publication (opens in new tab): %s', 'phagebiome-publications'), esc_attr($r['title'])); ?>"><span aria-hidden="true">↗</span></a>
        </article>
        <?php endforeach; ?>
      </div>
      <p class="pbp-empty"<?php echo $shown > 0 ? ' hidden' : ''; ?>><?php echo esc_html($opt['empty_message']); ?></p>
      <p class="pbp-actions"><a class="pbp-reset" href="<?php echo $action; ?>"><?php echo esc_html($opt['reset_label']); ?></a></p>
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
  }

  private static function matches($r, $q, $year) {
    if ('all' !== $year && (string) $r['year'] !== (string) $year) return false;
    if ('' !== $q) {
      $hay = strtolower(implode(' ', [$r['title'], $r['journal'], $r['year'], $r['summary']]));
      if (false === strpos($hay, strtolower($q))) return false;
    }
    return true;
  }
}
