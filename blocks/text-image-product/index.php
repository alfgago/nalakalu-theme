<?php
/**
 * Block: Text Image Product
 *
 * Campos ACF:
 * - title1       (text)       -> "TU ESPACIO"
 * - description1 (textarea)   -> texto derecha fila 1
 * - title2       (text)       -> "ES"
 * - image        (image)      -> silla
 * - title3       (text)       -> "TU"
 * - url_button   (url)        -> enlace botón
 * - description2 (textarea)   -> texto izquierda fila 3
 * - title4       (text)       -> "SANTUARIO"
 */

if ( ! function_exists('get_field') ) {
  echo '<p><em>ACF plugin required.</em></p>';
  return;
}

$section_id = 'tip-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$classes    = 'text-image-product';
if ( ! empty($block['className']) ) $classes .= ' ' . esc_attr($block['className']);
if ( ! empty($block['align']) )     $classes .= ' align' . esc_attr($block['align']);

/** Helper imagen */
if ( ! function_exists('nl_tip_img_url') ) {
  function nl_tip_img_url($img, $size = 'large') {
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

// Campos
$title1       = (string) get_field('title1');
$description1 = (string) get_field('descripcion1');
$title2       = (string) get_field('title2');
$image        =        get_field('image');
$title3       = (string) get_field('title3');
$url_button   = (string) get_field('url_button');
$description2 = (string) get_field('descripcion2');
$title4       = (string) get_field('title4');

$image_url = nl_tip_img_url($image, 'large') ?: nl_tip_img_url($image, 'full');
?>
<section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($classes); ?>">
  <div class="tip_hero-section">
    <div class="tip_content-wrapper">

      <!-- PRIMERA FILA: 65% - 35% -->
      <div class="tip_row-1">
        <?php if ($title1): ?>
          <h1 class="font-heading-display">
            <?php echo esc_html($title1); ?>
          </h1>
        <?php elseif ( current_user_can('edit_posts') ): ?>
          <h1 class="font-heading-display" style="opacity:.6;">
            Asigná el campo “title1”.
          </h1>
        <?php endif; ?>

        <div class="font-body-small">
          <?php if ($description1): ?>
            <?php echo nl2br( esc_html($description1) ); ?>
          <?php elseif ( current_user_can('edit_posts') ): ?>
            <span style="opacity:.6;">Asigná el campo “description1”.</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- SEGUNDA FILA: ES + Silla (absolute) + TU + Botón -->
      <div class="tip_row-2">
        <?php if ($title2): ?>
          <div class="font-heading-display">
            <?php echo esc_html($title2); ?>
          </div>
        <?php elseif ( current_user_can('edit_posts') ): ?>
          <div class="font-heading-display" style="opacity:.6;">
            Asigná “title2”.
          </div>
        <?php endif; ?>

        <?php if ($image_url): ?>
          <div class="tip_chair-container">
            <img src="<?php echo esc_url($image_url); ?>"
                 alt=""
                 class="tip_chair-image">
          </div>
        <?php elseif ( current_user_can('edit_posts') ): ?>
          <div class="tip_chair-container" style="opacity:.6;">
            Subí una imagen en el campo “image”.
          </div>
        <?php endif; ?>

        <div class="tip_right-content">
          <?php if ($title3): ?>
            <div class="font-heading-display">
              <?php echo esc_html($title3); ?>
            </div>
          <?php elseif ( current_user_can('edit_posts') ): ?>
            <div class="font-heading-display" style="opacity:.6;">
              Asigná “title3”.
            </div>
          <?php endif; ?>

          <?php if ($url_button): ?>
            <a href="<?php echo esc_url($url_button); ?>" class="btn btn-outline-cafe">
              Ver productos →
            </a>
          <?php elseif ( current_user_can('edit_posts') ): ?>
            <a href="#" class="btn btn-outline-cafe" style="opacity:.6;pointer-events:none;">
              Definí “url_button”.
            </a>
          <?php endif; ?>
        </div>
      </div>

      <div class="tip_row-3">
        <div class="font-body-small">
          <?php if ($description2): ?>
            <?php echo nl2br( esc_html($description2) ); ?>
          <?php elseif ( current_user_can('edit_posts') ): ?>
            <span style="opacity:.6;">Asigná “description2”.</span>
          <?php endif; ?>
        </div>

        <?php if ($title4): ?>
          <h2 class="font-heading-display">
            <?php echo esc_html($title4); ?>
          </h2>
        <?php elseif ( current_user_can('edit_posts') ): ?>
          <h2 class="font-heading-display" style="opacity:.6;">
            Asigná “title4”.
          </h2>
        <?php endif; ?>
      </div>

    </div>
  </div>
</section>
