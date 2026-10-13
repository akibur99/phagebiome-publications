# Phagebiome Publications 1.0.4

Admin-managed publication listing. Shortcode: `[phagebiome_publications]`

Developed by InsoSpark Agency
https://insospark.com

## Installation

1. In WordPress admin go to **Plugins → Add New → Upload Plugin**.
2. Upload `phagebiome-publications.zip` (see “Build the ZIP” below) and click **Install Now → Activate**.
3. No setup is required. Defaults match the reference site wording.

## Shortcode usage

Place `[phagebiome_publications]` in:
- any page or post (Shortcode block or plain content),
- an Elementor Shortcode widget,
- a legacy Text widget (`add_filter('widget_text', 'do_shortcode')` is core default).

The shortcode renders search + year filters + result count + rows. Multiple shortcodes per page work independently. Without JavaScript, search and filters work via normal form submission.

## Managing publications

**Publications → All Publications**: add, edit, publish, unpublish (switch to Draft), trash, restore — standard WordPress flows. Menu order is always: All Publications, Add New Publication, Import, Settings.

Required to publish: publication title, a 4-digit year (1900–2100), and an `http(s)` external URL. Incomplete records stay as drafts; the editor shows exactly what is missing without losing your entries.

- **Display order is automatic.** Leave the Display Order field empty (“Automatic”) and the next number in that Publication Year is assigned on first publish (after 1, 2, 3 comes 4). The assigned number and an “Automatic” indicator are shown after publishing.
- Enter a number to **override**; it is preserved through edits (`Manual ordering active`). **Reset to automatic** restores the stored automatic value.
- Changing the Publication Year moves an automatically ordered record to the next number in the destination year. Drafts never consume numbers; edits, unpublishing, republishing, and deletions never renumber.
- List column shows the effective number with an auto/manual tag and is sortable, like Year.

## Display settings

**Publications → Settings**: search placeholder, empty-results message, reset-button label, plus:
- **Publication Year Order**: Descending (newest first, default) or Ascending.
- **Publication Order Within Each Year**: Ascending (default) or Descending.
Year-filter buttons follow the year direction; search and filters preserve the configured order.

## Importing the reference dataset

**Publications → Import**: one click imports the 20 real records from the original website. Re-running never duplicates (stable keys) and never overwrites your edits. Incomplete source rows become drafts; a summary reports imported / skipped / drafts / invalid.

## Display settings

**Publications → Settings**: search placeholder, empty-results message, reset-button label.

## Notes

- No single-publication pages, archives, or sitemap entries are created. Titles and ↗ icons link straight to the publisher (`target="_blank"`, `rel="noopener noreferrer"`, with “opens in new tab” text for screen readers).
- Records and settings are preserved on deactivation and uninstall.
- Requires WordPress 6.0+ and PHP 7.4+. No ACF, Elementor, or paid plugins needed.

## Build the ZIP

From the plugin folder’s parent directory:

```powershell
Compress-Archive -Path .\phagebiome-publications -DestinationPath .\phagebiome-publications.zip -Force
```

The ZIP must contain the top-level `phagebiome-publications/` folder with `phagebiome-publications.php` directly inside it.
