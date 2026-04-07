<?php
/**
 * Block: Collection Selector
 *
 * Campos ACF:
 * - collection_name (text)     -> título principal
 * - url_button      (url)      -> enlace botón
 * - image1          (image)    -> imagen grande
 * - image2          (image)    -> imagen chica
 * - descripcion     (textarea) -> texto dentro del glass
 */

if ( ! function_exists('get_field') ) {
  echo '<p><em>ACF plugin required.</em></p>';
  return;
}

$section_id = 'collection-selector-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$classes    = 'collection-selector-block';
if ( ! empty($block['className']) ) $classes .= ' ' . esc_attr($block['className']);
if ( ! empty($block['align']) )     $classes .= ' align' . esc_attr($block['align']);

/** Helper de imagen (evitar redeclare) */
if ( ! function_exists('nl_cs_img_url') ) {
  function nl_cs_img_url($img, $size = 'large') {
    if (is_array($img)) {
      if (!empty($img['sizes'][$size])) return esc_url($img['sizes'][$size]);
      if (!empty($img['url']))          return esc_url($img['url']);
    } elseif (is_numeric($img)) {
      $src = wp_get_attachment_image_src((int)$img, $size);
      if ($src && !empty($src[0])) return esc_url($src[0]);
    } elseif (is_string($img) && filter_var($img, FILTER_VALIDATE_URL)) {
      return esc_url($img);
    }
    return '';
  }
}

// Campos ACF
$collection_name = (string) get_field('collection_name');
$url_button      = (string) get_field('url_button');
$image1          =        get_field('image1');
$image2          =        get_field('image2');
$descripcion     = (string) get_field('descripcion');

$image1_url = nl_cs_img_url($image1, 'large') ?: nl_cs_img_url($image1, 'full');
$image2_url = nl_cs_img_url($image2, 'large') ?: nl_cs_img_url($image2, 'full');
?>
<section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($classes); ?>">
  <div class="cs_section">

    <div class="cs_header">
      <?php if ($collection_name): ?>
        <h1 class="font-heading-1">
          <?php echo esc_html($collection_name); ?>
        </h1>
      <?php elseif ( current_user_can('edit_posts') ): ?>
        <h1 class="font-heading-1" style="opacity:.6;">
          Asigná el campo “collection_name”.
        </h1>
      <?php endif; ?>

      <?php if ($url_button): ?>
        <a href="<?php echo esc_url($url_button); ?>" class="btn btn-outline-cafe">
          Ver colección →
        </a>
      <?php elseif ( current_user_can('edit_posts') ): ?>
        <a href="#" class="btn btn-outline-cafe" style="pointer-events:none;opacity:.6;">
          Definí “url_button”.
        </a>
      <?php endif; ?>
    </div>

    <div class="cs_content-wrapper">
      <div class="cont-btn">
      <?php if ($url_button): ?>
        <a href="<?php echo esc_url($url_button); ?>" class="btn btn-outline-cafe hidden_desktop">
          Ver colección →
        </a>
      <?php elseif ( current_user_can('edit_posts') ): ?>
        <a href="#" class="btn btn-outline-cafe" style="pointer-events:none;opacity:.6;">
          Definí “url_button”.
        </a>
      <?php endif; ?>
      </div>
      <div class="cs_col cs_col-1">
        <?php if ($image1_url): ?>
          <img class="cs_img-col-1"
               src="<?php echo esc_url($image1_url); ?>"
               alt="<?php echo esc_attr($collection_name ?: 'Colección'); ?>">
        <?php elseif ( current_user_can('edit_posts') ): ?>
          <div style="opacity:.6;">Subí una imagen en “image1”.</div>
        <?php endif; ?>
        
      </div>
      

      <div class="cs_col cs_col-2">
        <div class="cs_glass">
          <?php if ($descripcion): ?>
            <p class="font-body-small" ><?php echo nl2br( esc_html($descripcion) ); ?></p>
          <?php elseif ( current_user_can('edit_posts') ): ?>
            <p class="font-body-small" style="opacity:.6;">Asigná el campo “descripcion”.</p>
          <?php endif; ?>
        </div>

        <?php if ($image2_url): ?>
          <img class="cs_img-col-2"
               src="<?php echo esc_url($image2_url); ?>"
               alt="<?php echo esc_attr($collection_name ?: 'Detalle de colección'); ?>">
        <?php elseif ( current_user_can('edit_posts') ): ?>
          <div style="opacity:.6;margin-top:2rem;">Subí una imagen en “image2”.</div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</section>
