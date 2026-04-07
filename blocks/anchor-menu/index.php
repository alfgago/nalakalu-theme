<?php
/**
 * Block: Menu Anchor
 * ACF field:
 * - url (text/url) => "#mi-ancla" o "mi-ancla"
 */

defined('ABSPATH') || exit;

if ( ! function_exists('get_field') ) {
  if ( current_user_can('edit_posts') ) {
    echo '<p><em>ACF plugin required.</em></p>';
  }
  return;
}

// --- Helpers (evitamos redeclare) ---
if ( ! function_exists('nlk_anchor_sanitize') ) {
  /**
   * Convierte "#mi ancla" -> "mi-ancla" (safe para id HTML)
   */
  function nlk_anchor_sanitize($raw) {
    $raw = is_string($raw) ? trim($raw) : '';
    if ($raw === '') return '';

    // si viene url completa con #hash, nos quedamos con el hash
    if (strpos($raw, '#') !== false) {
      $parts = explode('#', $raw);
      $raw = end($parts);
      $raw = is_string($raw) ? $raw : '';
    }

    $raw = ltrim($raw, '#');
    $raw = trim($raw);
    $raw = strtolower($raw);

    // espacios y underscores -> guiones
    $raw = str_replace([' ', '_'], '-', $raw);

    // solo chars válidos para id
    $raw = preg_replace('/[^a-z0-9\-_]/', '', $raw);

    return $raw ?: '';
  }
}

$uid = 'menu-anchor-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$classes = 'nlk-menu-anchor';
if ( ! empty($block['className']) ) $classes .= ' ' . esc_attr($block['className']);
if ( ! empty($block['align']) )     $classes .= ' align' . esc_attr($block['align']);

// Campo ACF
$raw_url   = (string) get_field('url');
$anchor_id = nlk_anchor_sanitize($raw_url);

// fallback si no cargaron nada
if ( $anchor_id === '' ) {
  // Solo mostramos aviso en editor/preview
  if ( ! empty($is_preview) ) {
    echo '<div id="' . esc_attr($uid) . '" class="' . esc_attr($classes) . '">';
    echo '<p style="opacity:.7;margin:0;"><em>Menu Anchor: completá el campo <strong>url</strong> con algo tipo <code>#seccion</code>.</em></p>';
    echo '</div>';
  }
  return;
}
?>
<div id="<?php echo esc_attr($uid); ?>" class="<?php echo esc_attr($classes); ?>" data-anchor="<?php echo esc_attr($anchor_id); ?>">
  <span
    id="<?php echo esc_attr($anchor_id); ?>"
    class="nlk-menu-anchor__target"
    aria-hidden="true"
  ></span>

  <?php if ( ! empty($is_preview) ) : ?>
    <span class="nlk-menu-anchor__hint" style="display:block;font-size:12px;opacity:.6;line-height:1.3;margin-top:6px;">
      Anchor: <code>#<?php echo esc_html($anchor_id); ?></code>
    </span>
  <?php endif; ?>
</div>

<style>
  .nlk-menu-anchor__target{
    display:block;
    scroll-margin-top: var(--nlk-scroll-offset, 1rem);
  }

  .nlk-menu-anchor{
    line-height:0;
  }
</style>
