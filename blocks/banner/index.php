<?php
/**
 * Bloque: Banner with Text
 * Campos ACF:
 * - background (image)
 * - pretitle (text)
 * - title (text)
 * - content (textarea)
 */

if ( ! function_exists('get_field') ) {
  echo '<p><em>ACF plugin required.</em></p>';
  return;
}

$section_id = 'nl-banner-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$classes    = 'nl-banner';
if (!empty($block['className'])) $classes .= ' ' . esc_attr($block['className']);
if (!empty($block['align']))     $classes .= ' align' . esc_attr($block['align']);

$bg      = get_field('background');
$pre     = (string) get_field('pretitle');
$title   = (string) get_field('title');
$content = (string) get_field('content');

/** Helper protegido para obtener URL de imagen */
if (!function_exists('nl_banner_img_url')) {
  function nl_banner_img_url($img, $size = 'full') {
    if (is_array($img)) {
      if (!empty($img['sizes'][$size])) return esc_url($img['sizes'][$size]);
      if (!empty($img['url'])) return esc_url($img['url']);
    } elseif (is_numeric($img)) {
      $src = wp_get_attachment_image_src((int)$img, $size);
      if ($src && !empty($src[0])) return esc_url($src[0]);
    } elseif (is_string($img) && filter_var($img, FILTER_VALIDATE_URL)) {
      return esc_url($img);
    }
    return '';
  }
}

$bg_url = nl_banner_img_url($bg, '1536x1536') ?: nl_banner_img_url($bg, 'full');
$has_bg = $bg_url ? ' has-bg' : '';
$style  = $bg_url ? '--banner-bg:url('.$bg_url.');' : '';

?>
<section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($classes.$has_bg); ?>" style="<?php echo esc_attr($style); ?>">
  <div class="nlb-hero">
    <div class="nlb-content">
      <?php if ($pre): ?>
        <p class="font-overline"><?php echo esc_html($pre); ?></p>
      <?php endif; ?>

      <?php if ($title): ?>
        <h1 class="font-medium-light"><?php echo esc_html($title); ?></h1>
      <?php endif; ?>

      <?php if ($content): ?>
        <p class="font-body-medium-light"><?php echo wp_kses_post( nl2br($content) ); ?></p>
      <?php endif; ?>
    </div>
  </div>
</section>
