<?php
/**
 * Block: Next Launch
 *
 * Campos ACF:
 * - pretitle      (text)        -> "EVENTOS NALAKALÚ"
 * - year          (text)        -> "2025"
 * - title         (text)        -> título grande
 * - description   (textarea)    -> texto descriptivo
 * - image         (image)       -> imagen derecha
 * - url_button    (url)         -> enlace del botón
 * - mostrar_imagen (radio)      -> "Si" / "No" (valor)
 */

if ( ! function_exists('get_field') ) {
  echo '<p><em>ACF plugin required.</em></p>';
  return;
}

$section_id = 'nl-nextlaunch-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$classes    = 'nl_nextlaunch_block';
if ( ! empty($block['className']) ) $classes .= ' ' . esc_attr($block['className']);
if ( ! empty($block['align']) )     $classes .= ' align' . esc_attr($block['align']);

/** Helper imagen (evitar redeclare) */
if ( ! function_exists('nl_nextlaunch_img_url') ) {
  function nl_nextlaunch_img_url($img, $size = 'large') {
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
$pretitle    = (string) get_field('pretitle');
$year        = (string) get_field('year');
$title       = (string) get_field('title');
$description = (string) get_field('description');
$url_button  = (string) get_field('url_button');
$image_raw   = get_field('image');
$image_url   = nl_nextlaunch_img_url($image_raw, 'large') ?: nl_nextlaunch_img_url($image_raw, 'full');

// Radio mostrar_imagen ("Si"/"No")
$mostrar_imagen_raw = get_field('mostrar_imagen');
$show_image         = ( (string) $mostrar_imagen_raw === 'Si' );

if ( ! $image_url ) {
  // Si no hay imagen, aunque diga "Si", no mostramos la columna
  $show_image = false;
}

if ( ! $show_image ) {
  $classes .= ' nl_nextlaunch--no-image';
}
?>
<section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($classes); ?>">
  <div class="nl_nextlaunch_container">

    <div class="nl_nextlaunch_content">

      <?php if ( $pretitle || $year ): ?>
        <div class="nl_nextlaunch_eyebrow">
          <?php if ( $pretitle ): ?>
            <span class="nl_nextlaunch_eyebrow-label font-overline">
              <?php echo esc_html($pretitle); ?>
            </span>
          <?php elseif ( current_user_can('edit_posts') ): ?>
            <span class="nl_nextlaunch_eyebrow-label font-overline" style="opacity:.6;">
              Asigná el campo “pretitle”.
            </span>
          <?php endif; ?>

          <?php if ( $year ): ?>
            <span class="nl_nextlaunch_eyebrow-year font-overline">
              <?php echo esc_html($year); ?>
            </span>
          <?php elseif ( current_user_can('edit_posts') ): ?>
            <span class="nl_nextlaunch_eyebrow-year font-overline" style="opacity:.6;">
              Año
            </span>
          <?php endif; ?>
          
        </div>
        
      <?php endif; ?>
      
      

      <?php if ( $title ): ?>
        <h1 class="nl_nextlaunch_title font-heading-1">
          <?php echo nl2br( esc_html($title) ); ?>
        </h1>
      <?php elseif ( current_user_can('edit_posts') ): ?>
        <h1 class="nl_nextlaunch_title font-heading-1" style="opacity:.6;">
          Asigná el campo “title”.
        </h1>
      <?php endif; ?>

      <?php if ( $description ): ?>
        <div class="nl_nextlaunch_description font-body-medium-light">
          <?php echo wp_kses_post( wpautop($description) ); ?>
        </div>
      <?php elseif ( current_user_can('edit_posts') ): ?>
        <div class="nl_nextlaunch_description font-body-medium-light" style="opacity:.6;">
          Asigná el campo “description”.
        </div>
      <?php endif; ?>
      
      <div class="newsletter-form-collection">

  <form class="form-group" action="#" method="post">
    <input 
      type="email" 
      name="email"
      placeholder="<?php echo esc_attr( get_theme_mod('nlk_news_placeholder', 'Correo Electrónico') ); ?>" 
      required
    >

    <button type="submit">Enviar</button>
  </form>
</div>

      <?php if ( $url_button ): ?>
        <a href="<?php echo esc_url($url_button); ?>" class="btn btn-cafe">
          Registrarse<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
  <path d="M10.1458 7.5H0V5.83333H10.1458L5.47917 1.16667L6.66667 0L13.3333 6.66667L6.66667 13.3333L5.47917 12.1667L10.1458 7.5Z" fill="white"/>
</svg>
        </a>
      <?php endif; ?>

    </div><!-- /.nl_nextlaunch_content -->

    <?php if ( $show_image ): ?>
      <div class="nl_nextlaunch_image-col">
        <div class="nl_nextlaunch_image-container">
          <div class="nl_nextlaunch_image-frame">
            <div class="nl_nextlaunch_corner-bracket nl_nextlaunch_corner-top-left"></div>
            <div class="nl_nextlaunch_corner-bracket nl_nextlaunch_corner-top-right"></div>
            <div class="nl_nextlaunch_corner-bracket nl_nextlaunch_corner-bottom-left"></div>
            <div class="nl_nextlaunch_corner-bracket nl_nextlaunch_corner-bottom-right"></div>

            <div class="nl_nextlaunch_image-wrapper">
              <img
                src="<?php echo esc_url($image_url); ?>"
                alt="<?php echo esc_attr($title ?: 'Imagen del evento'); ?>"
              >
              
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </div><!-- /.nl_nextlaunch_container -->
</section>
