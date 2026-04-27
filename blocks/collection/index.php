<?php
/**
 * Render del bloque "Collection"
 * - Selector de colección: field 'collection_selector' (return id)
 * - Fondo de .collection-text: campo ACF del término 'background_video' (archivo: imagen o video)
 * - Muestra último producto de la colección como destacado
 * - Carrusel: hasta 10 productos de la colección (excluye el destacado)
 */

if ( ! function_exists('get_field') ) {
    echo '<p><em>ACF plugin required.</em></p>';
    return;
}

$term_id = (int) get_field('collection_selector');

$section_id = 'collection-block-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$class_name = 'collection-section';
if ( ! empty($block['className']) ) $class_name .= ' ' . esc_attr($block['className']);
if ( ! empty($block['align']) )     $class_name .= ' align' . esc_attr($block['align']);

$taxonomy = taxonomy_exists('coleccion') ? 'coleccion' : ( taxonomy_exists('pa_coleccion') ? 'pa_coleccion' : 'coleccion' );

if ( ! $term_id ) {
    if ( current_user_can('edit_posts') ) {
        echo '<section id="' . esc_attr($section_id) . '" class="' . esc_attr($class_name) . '"><p style="opacity:.7">Seleccioná una Colección en el panel del bloque.</p></section>';
    }
    return;
}

// Datos del término
$term      = get_term($term_id, $taxonomy);
$term_name = $term && ! is_wp_error($term) ? $term->name : '';
$term_desc = $term && ! is_wp_error($term) ? term_description($term_id, $taxonomy) : '';

/**
 * ACF del término
 * - title: título custom
 * - logo: archivo
 */
$term_title    = $term_name; // fallback al nombre real del término
$term_logo_url = '';
$term_logo_alt = $term_name;

if ( function_exists('get_field') ) {
    $acf_term_title = get_field('title', "{$taxonomy}_{$term_id}");
    if ( ! empty($acf_term_title) ) {
        $term_title = $acf_term_title;
    }

    $term_logo = get_field('logo', "{$taxonomy}_{$term_id}");

    if ( $term_logo ) {
        if ( is_array($term_logo) ) {
            $term_logo_url = ! empty($term_logo['url']) ? $term_logo['url'] : '';
            if ( ! empty($term_logo['alt']) ) {
                $term_logo_alt = $term_logo['alt'];
            } elseif ( ! empty($term_logo['title']) ) {
                $term_logo_alt = $term_logo['title'];
            }
        } elseif ( is_numeric($term_logo) ) {
            $term_logo_url = wp_get_attachment_url((int) $term_logo);
            $alt = get_post_meta((int) $term_logo, '_wp_attachment_image_alt', true);
            if ( $alt ) {
                $term_logo_alt = $alt;
            }
        } else {
            $term_logo_url = $term_logo; // fallback por si devuelve URL
        }
    }
}

// Campo ACF del término: 'background_video' (archivo imagen/video)
$bg_image_url = '';
$bg_video_url = '';
$bg_is_video  = false;
$bg_mime_type = '';
$bg_style     = '';

if ( function_exists('get_field') ) {
    $background_media = get_field('background_video', "{$taxonomy}_{$term_id}");

    if ( $background_media ) {
        $file_id   = 0;
        $mime_type = '';
        $url       = '';

        if ( is_array($background_media) ) {
            // ACF file retornando array
            $file_id   = isset($background_media['ID']) ? (int) $background_media['ID'] : 0;
            $mime_type = isset($background_media['mime_type']) ? $background_media['mime_type'] : '';
            $url       = ! empty($background_media['url']) ? $background_media['url'] : '';
        } elseif ( is_numeric($background_media) ) {
            // ACF file retornando ID
            $file_id   = (int) $background_media;
            $mime_type = get_post_mime_type($file_id);
            $url       = wp_get_attachment_url($file_id);
        } else {
            // Fallback viejo: string URL
            $url       = $background_media;
            $mime_type = '';
        }

        if ( $url ) {
            if ( $mime_type && strpos($mime_type, 'video/') === 0 ) {
                $bg_video_url = $url;
                $bg_is_video  = true;
                $bg_mime_type = $mime_type;
            } else {
                $bg_image_url = $url;
            }
        }
    }
}

if ( $bg_image_url ) {
    // Gradient + imagen de fondo (el gradient arriba de la imagen)
    $bg_style  = "background-image: ";
    $bg_style .= "linear-gradient(289deg, #e3d1bac7 56.71%, #b7a48d70 99.59%), ";
    $bg_style .= "url('" . esc_url($bg_image_url) . "');";
    $bg_style .= "background-size: cover;";
    $bg_style .= "background-position: center;";
}

// Último producto (destacado)
$last_q = new WP_Query(array(
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => 1,
    'tax_query'      => array(
        array(
            'taxonomy' => $taxonomy,
            'field'    => 'term_id',
            'terms'    => $term_id,
        ),
    ),
    'orderby'        => 'date',
    'order'          => 'DESC',
));

$last_id            = 0;
$last_img           = '';
$last_link          = '';
$last_name          = '';
$last_price_html    = '';
$last_category_name = '';

if ( $last_q->have_posts() ) {
    $last_q->the_post();
    $last_id   = get_the_ID();
    $last_link = get_permalink($last_id);
    $last_name = get_the_title($last_id);

    if ( has_post_thumbnail($last_id) ) {
        $img = wp_get_attachment_image_src(get_post_thumbnail_id($last_id), 'large');
        if ( ! empty($img[0]) ) $last_img = esc_url($img[0]);
    }

    if ( function_exists('wc_get_product') ) {
        $product = wc_get_product($last_id);
        if ( $product ) $last_price_html = $product->get_price_html();
    }

    $cats = get_the_terms($last_id, 'product_cat');
    if ( ! is_wp_error($cats) && ! empty($cats) ) {
        $last_category_name = $cats[0]->name;
    }

    wp_reset_postdata();
}

// Carrusel (hasta 10), excluyendo el destacado
$loop_q = new WP_Query(array(
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => 10,
    'post__not_in'   => $last_id ? array($last_id) : array(),
    'tax_query'      => array(
        array(
            'taxonomy' => $taxonomy,
            'field'    => 'term_id',
            'terms'    => $term_id,
        ),
    ),
    'orderby' => 'date',
    'order'   => 'DESC',
));

/**
 * URL del botón "Ver colección" = link real del término seleccionado
 * Fallback: /colecciones/
 */
$collections_url = '';
if ( $term && ! is_wp_error($term) ) {
    $link = get_term_link($term, $taxonomy);
    if ( ! is_wp_error($link) ) {
        $collections_url = $link;
    }
}
if ( ! $collections_url ) {
    $collections_url = home_url('/colecciones/');
}

$arrow_url = 'https://nalakalu.stag.host/wp-content/uploads/2025/10/arrow_forward.svg';
?>

<section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($class_name); ?>">
  <div class="collection-content">
    <div class="collection-row-1">
      <div class="collection-text<?php echo $bg_is_video ? ' has-video-bg' : ''; ?>" style="<?php echo esc_attr($bg_style); ?>">

        <?php if ( $bg_is_video && $bg_video_url ) : ?>
          <div class="collection-bg-video-deco" aria-hidden="true">
            <video class="collection-bg-video" autoplay muted loop playsinline preload="auto">
              <source src="<?php echo esc_url($bg_video_url); ?>" type="<?php echo esc_attr($bg_mime_type ?: 'video/mp4'); ?>">
            </video>
          </div>
        <?php endif; ?>

        <div class="collection-head">
          <?php if ( $term_logo_url ) : ?>
            <div class="collection-tax-logo">
              <img
                src="<?php echo esc_url($term_logo_url); ?>"
                alt="<?php echo esc_attr($term_logo_alt); ?>"
                class="collection-tax-logo__img"
              >
            </div>
          <?php endif; ?>

        </div>

        <?php if ( $term_desc ) : ?>
          <div class="collection-foot">
            <p class="font-body-small text-blanco-hueso">
              <?php echo wp_kses_post(wp_strip_all_tags($term_desc)); ?>
            </p>
          </div>
        <?php endif; ?>

      </div>
    </div>

<?php
$style_attr = '';
if ( ! empty($last_img) ) {
    $style_attr = '--bg-desktop: url("' . esc_url($last_img) . '");';
}
?>

    <div class="collection-row-2 collection-product has-dynamic-bg" <?php echo $style_attr ? 'style="' . esc_attr($style_attr) . '"' : ''; ?>>
      <div class="product-badge">
        <svg class="text-image" xmlns="http://www.w3.org/2000/svg" width="164" height="161" fill="none" viewBox="0 0 164 161"><path fill="#fff" d="m55.6 4.8 8.2-2.3.4 1.5-6.5 1.9 1.2 4.4 6.5-1.8.4 1.5-6.4 1.8 1.2 4.5 6.7-1.9.4 1.5-8.2 2.4zm20.3 0 1.8-.2-2.4 5v.3l3.7 4.5-1.8.2-3.2-4h-.3l-2.2 4.7-1.8.2 2.6-5.3V10l-3.5-4.4 1.8-.2 3 3.8h.3zM82 18.4h-1.6l.7-14 1.5.1v1.6h.2q.4-.9 1.5-1.3 1-.4 2-.4A5 5 0 0 1 89.5 6q.6.7 1 1.6.3 1 .2 2.2v.4l-.5 2.1a4 4 0 0 1-2.5 2.5 5 5 0 0 1-2.8 0l-1-.3-.7-.6-.6-.9h-.3zm3.5-5q.8 0 1.3-.2a3 3 0 0 0 1.9-1.7L89 10v-.4a4 4 0 0 0-.8-2.6l-1-.8a3 3 0 0 0-2.6-.1 3 3 0 0 0-1.9 1.8l-.3 1.5v.2q0 .8.2 1.5.1.7.6 1.2a3 3 0 0 0 2.3 1m6.8.3 3.5.7 2.3-10.9-3.4-.7.3-1.4 5 1-2.6 12.3 3.5.8-.3 1.4-8.6-1.8zm21 2.8q-.5 1.1-1.2 1.9t-1.6 1-1.9.4a5 5 0 0 1-4.6-2.9q-.4-.9-.4-1.9-.1-1 .4-2.2v-.2l1.2-1.9 1.7-1q.8-.4 1.8-.4a5 5 0 0 1 3.5 1.4q.8.6 1.1 1.5t.4 1.9q0 1-.3 2.1zm-6 1.5q.7.3 1.3.3t1.3-.3 1-.8q.6-.6.9-1.3v-.2q.4-.7.4-1.5T112 13l-.7-1.1a3 3 0 0 0-2.5-1 3 3 0 0 0-2.4 1.1l-.7 1.3-.1.2-.3 1.5a4 4 0 0 0 1 2.4q.5.5 1.1.7m10.3-5 3.1 1.8-.7 1.1.2.2q.7-.6 1.6-.5.7 0 1.6.5 1.2.8 1.5 2 .3 1.5-.6 3l-1.5-.6q.6-1.2.4-2-.2-1-1-1.4-.6-.3-1.1-.4-.5 0-1 .2l-.9.6-.7 1-2.1 3.6 2.1 1.3-.7 1.2-5.5-3.2.8-1.3 2 1.2 3.5-6-1.7-1.1zm11.4 18-.2-.2q-1 .5-2 .3t-1.6-.8L124 29q-.4-.8-.5-1.7t.3-1.9a6 6 0 0 1 1.1-1.9l.2-.2a6 6 0 0 1 1.7-1.4q.9-.6 1.8-.6t1.7.2q.9.3 1.6.9t1.1 1.5.1 1.9l.2.2 1-1.2 1.2 1-5 6q-.5.5 0 .8l.4.3-1 1.1-.8-.7q-.5-.4-.5-1-.1-.6.4-1.1zm-2.7-1.7a3 3 0 0 0 2.4.7q.6 0 1.2-.5.6-.3 1.2-1l.1-.1q.6-.6.8-1.3l.2-1.3q0-.7-.3-1.3-.2-.5-.8-1a3 3 0 0 0-2.4-.7q-.6 0-1.2.5-.6.3-1.2 1l-.2.2q-1 1.2-1 2.6a3 3 0 0 0 1.2 2.2m12.3 15.5-.8-1.4 8.5-5.1.8 1.3-1.4.9.1.2q2.2-.3 3.3 1.7 1 1.5.5 3-.3 1.3-2.2 2.5l-5 3-.8-1.3 4.8-3q1.3-.7 1.6-1.7.2-1-.3-2a3 3 0 0 0-2-1.5q-1.2-.1-2.6.7zm16.7 11 .5 1.5-9.2 3.7-.5-1.4 1.6-.7v-.2a4 4 0 0 1-2-.6q-.7-.6-1.2-1.7l-.3-1.5q0-.8.3-1.4l1-1.2 1.6-1 5.5-2.1.6 1.4-5.4 2.2q-1.4.5-1.8 1.4t0 2.1q.6 1.3 1.8 1.7a4 4 0 0 0 2.7-.2zm-3.3 7.7q-1.5.3-2.2 1.4-.6 1-.3 2.5 0 .8.4 1.2a3 3 0 0 0 1.4 1.2l.8.2v1.5q-1.5 0-2.6-1a5 5 0 0 1-1.5-2.8v-2a5 5 0 0 1 2-3q1-.6 2-.8l.5-.1q1-.3 2-.1a5 5 0 0 1 3 2q.5.7.8 1.8t0 2.1a4 4 0 0 1-2 2.8l-1.4.6-1.2.3zm4.8 2.3a4 4 0 0 0-1.2-2l-1-.5-1.2-.1 1.4 6.3 1-.6q.5-.4.7-.9l.4-1zm.2 10q-.7 0-1 .7t-.4 2.3-.7 2.6-2 1h-.1a3 3 0 0 1-2.3-.9q-.4-.5-.7-1.2t-.3-1.6q0-1 .2-2l.7-1.4A4 4 0 0 1 153 74l.4 1.5q-1.2 0-1.8 1a3 3 0 0 0-.6 2q0 1.2.6 1.8.4.6 1.2.6.9 0 1.2-.7t.3-2.2q0-1.7.7-2.6t1.9-1h.1q.7-.1 1.3.2.5.2.9.7t.6 1.1l.3 1.4q0 1-.2 1.7-.3.8-.7 1.3t-1 .8l-1 .4-.4-1.5q.8 0 1.4-.8.5-.7.5-1.8l-.5-1.5-.5-.4zm2.8 10.2-.3 3 4 .5-.1 1.6-4-.5-.5 3.9-1.4-.2.4-3.8-6.4-.7q-.6 0-.6.5l-.3 2.8-1.5-.2.4-3.3q0-.7.5-1.1t1.2-.3l6.9.7.3-3zm-1.5 12.4-1 3.4-1.2-.3-.1.2q.6.5.9 1.4v1.6q-.6 1.5-1.7 2-1.2.8-3 .3l.2-1.7q1.3.4 2.1 0 .8-.6 1-1.5.2-.6 0-1 0-.6-.4-1-.2-.3-.8-.7l-1.1-.4-4-1.1-.7 2.4-1.4-.4 1.7-6.1 1.4.4-.6 2.2 6.8 1.8.5-2zM144 114l.1-.2q-.7-.9-.8-1.9t.4-1.8l1-1.5 1.5-1 1.9-.2a6 6 0 0 1 2.1.6l.3.2 1.8 1.2q.7.7 1 1.6.4.8.3 1.7 0 .9-.4 1.8t-1.2 1.5a3 3 0 0 1-1.8.6l-.1.2 1.4.7-.7 1.4-7.1-3.4q-.5-.1-.8.3l-.2.4-1.3-.6.4-1q.3-.5 1-.8.5-.1 1.1.1zm1-3.1a3 3 0 0 0-.1 2.5q.2.6.7 1l1.3 1h.2q.8.3 1.4.4l1.4-.1q.6-.2 1-.6.6-.4.8-1a3 3 0 0 0 .1-2.6l-.7-1q-.6-.5-1.3-.9l-.2-.1q-1.6-.7-2.8-.3a3 3 0 0 0-1.9 1.7m.6 11.7q-.6-.5-1.3-.2-.6.3-1.6 1.6-1.2 1.4-2.2 1.7t-2.2-.4a3 3 0 0 1-1.3-2.2q0-.6.2-1.4t.7-1.4l1.4-1.5 1.4-.7a4 4 0 0 1 2.7.5l-.5 1.4q-1-.6-2-.3a3 3 0 0 0-1.8 1.3q-.6 1-.6 1.7 0 .9.6 1.3.8.4 1.4.1.7-.3 1.6-1.5a5 5 0 0 1 2.1-1.7q1-.5 2.2.3l.9 1 .3 1.2q0 .6-.2 1.3l-.6 1.2q-.6.8-1.2 1.2-.7.5-1.3.7H143q-.7 0-1.2-.3l.6-1.4q.7.4 1.6.2.8-.3 1.5-1.2l.5-1.4v-.7zm-20.3 12 1.2-1 6 8-1.3.9-1-1.4-.2.2q.5 2.1-1.4 3.5-1.3 1-2.8.8t-2.7-2l-3.6-4.7 1.2-1 3.4 4.5q1 1.3 2 1.5t2-.6q1-.8 1.2-2 0-1.5-1-2.6zm-9.4 17.6-1.4.7-4.6-8.7 1.4-.8.8 1.6h.2q0-1.2.4-2t1.5-1.4l1.5-.5q.8 0 1.4.2.7.2 1.3.8t1.1 1.5l2.8 5.3-1.4.7-2.7-5q-.7-1.5-1.6-1.8t-2 .3q-1.4.6-1.6 1.9a4 4 0 0 0 .5 2.6zm-8-2.5q-.5-1.4-1.5-2-1.1-.5-2.6 0-.6.1-1.1.5a3 3 0 0 0-1.2 2.3l-1.5.1q0-1.5.8-2.6t2.6-1.8q1-.3 2-.2a5 5 0 0 1 3.2 1.7q.6.8 1 1.9l.1.4q.3 1 .3 2t-.5 1.8-1.2 1.4l-1.7 1q-1.2.4-2.1.2a4 4 0 0 1-3-1.7q-.5-.6-.7-1.3l-.4-1.2zm-1.7 5a4 4 0 0 0 1.8-1.4l.4-1v-1.2l-6 2q0 .6.6 1 .4.5 1 .6.4.2 1 .2zm-15-5.4 3.1-.5 5 9.3-1.9.3-4.4-8.6h-.2l-1.6 9.5-1.8.3zM79 151.6h.2q.5-1 1.4-1.4.8-.5 1.8-.5l1.8.3 1.5 1 1 1.6q.3 1 .3 2.2v.3a6 6 0 0 1-.4 2.2q-.3.9-1 1.6-.7.6-1.5 1-.7.3-1.8.3-.9 0-1.8-.5a3 3 0 0 1-1.3-1.4h-.2v1.6h-1.6v-8q0-.5-.5-.5h-.5v-1.5h1q.8 0 1.2.5t.4 1.1zm3.2-.4a3 3 0 0 0-2.3 1l-.7 1.1-.2 1.5v.2q0 .8.2 1.5l.7 1.2 1 .7q.6.3 1.3.3a3 3 0 0 0 2.3-1q.4-.5.6-1.1l.3-1.5v-.3q0-1.7-.9-2.7a3 3 0 0 0-2.3-1m-10.3 5.5q.1-.8-.4-1.2t-2.2-.9-2.4-1.3-.6-2v-.2a3 3 0 0 1 1.5-2q.6-.4 1.3-.4t1.6 0l2 .6 1.1 1a4 4 0 0 1 .8 2.7l-1.6.1q.2-1-.5-2a3 3 0 0 0-2-1q-1 0-1.8.2t-.8 1q-.2.9.4 1.3t2 .9a5 5 0 0 1 2.5 1.2q.8.8.6 2v.2l-.5 1.2-1 .7-1.2.4h-1.4q-1-.3-1.6-.7t-1.1-.9q-.4-.5-.6-1l-.2-1.2 1.6-.1q-.1 1 .5 1.6t1.6.8h1.5l.6-.5zm-27.9-14.2a5 5 0 0 1 2.7-1.6q.7-.2 1.6 0 .9 0 1.7.6.9.5 1.6 1.2l1 1.6a5 5 0 0 1-.5 4l-.1.3q-.6 1-1.4 1.7a5 5 0 0 1-3.7 1 6 6 0 0 1-3.3-1.7q-.6-.6-.8-1.4l-.3-1.5q0-.8.2-1.6l1.5.4-.2 1q0 .7.2 1 0 .7.5 1l1 .9a3 3 0 0 0 2.7.2 4 4 0 0 0 2.2-1.8v-.2l.6-1.5a3 3 0 0 0-.8-2.5 4 4 0 0 0-2.3-1.3l-1.1.1-1 .5-.8.7zm-10.1-6q.7-.9 1.7-1.5l1.8-.6q.9 0 1.9.2a5 5 0 0 1 3.6 4l-.1 2q-.3 1-1 2l-.2.2q-.7.9-1.6 1.5a4.8 4.8 0 0 1-3.7.4 5 5 0 0 1-3.7-4l.1-2q.3-1 1-2zm6.1.2q-.5-.5-1.1-.6-.8-.3-1.4-.1-.6 0-1.2.4l-1.2 1-.1.3-.7 1.3q-.2.8-.1 1.3 0 .7.4 1.3a3 3 0 0 0 2 1.6 3 3 0 0 0 2.6-.4l1.1-1 .2-.2q.5-.6.7-1.3t.1-1.4l-.3-1.2zm-5.4-4.2-2.5-2.7-8.1 7.6 2.3 2.5-1 1-3.5-3.7 9.3-8.5-2.5-2.7 1-1 6 6.5zm-10.7-7.8q1.2-.8 1.4-2 .3-1.3-.5-2.5l-.8-1a3 3 0 0 0-2.6-.5l-.5-1.4q1.3-.4 2.7 0t2.4 2q.6 1 .8 2a5 5 0 0 1-.8 3.5q-.6.8-1.6 1.4l-.4.3q-.9.5-1.8.8a5 5 0 0 1-3.5-.8l-1.4-1.4q-.7-1-.8-2a4 4 0 0 1 .8-3.3l1-1 1.1-.7zm-5.4-.4a4 4 0 0 0 1.9 1.4q.5.2 1.1.1.6 0 1.1-.3l-3.5-5.3q-.5.3-.8.9-.3.5-.3 1v1.2zM15.2 107a5 5 0 0 1 3.1.2l1.3 1q.6.7 1 1.5.4 1 .5 2t-.3 1.8a5 5 0 0 1-2.8 3h-.2q-1 .6-2.1.6a5 5 0 0 1-3.6-1.4 6 6 0 0 1-1.5-3.4q-.1-.9.1-1.6t.7-1.4 1.2-1.1l1 1.2-.9.7-.5 1-.2 1q0 .7.4 1.3a3 3 0 0 0 2 1.8 4 4 0 0 0 2.8-.1h.2q.8-.4 1.3-1a3 3 0 0 0 .9-2.4 4 4 0 0 0-1.1-2.5l-1-.6-1-.2-1 .1zM12 95.6a5 5 0 0 1 3 .8q.6.5 1.1 1.2l.8 1.6q.2 1 .1 2 0 1-.5 1.9a5 5 0 0 1-3.3 2.4h-.2q-1.2.3-2.2.2a5 5 0 0 1-3.2-2 6 6 0 0 1-1-3.6q0-.7.4-1.5.3-.8.9-1.3t1.3-.9l.7 1.4-.8.6q-.5.3-.7.8l-.4 1q0 .6.2 1.4a3 3 0 0 0 1.6 2 4 4 0 0 0 2.8.4h.2l1.4-.7a3 3 0 0 0 1.3-2.3 4 4 0 0 0-.6-2.6l-.8-.7q-.5-.4-1-.4h-1zM2 91.1q-.6 0-1-.3-.5-.4-.5-1t.3-1 1-.5 1 .3.5 1-.3 1-1 .5m11.7 1.8-.4-3.5-7 .6.3 3.1-1.4.2-.5-4.7 8.5-.8-.3-3.2 1.4-.1.8 8.2zm-3.6-20.7q1.2 0 2.1.6T14 74t.8 1.7a5 5 0 0 1-1.3 5.2q-.7.7-1.7 1t-2.2.3h-.2Q8 82 7 81.6l-1.5-1.2q-.6-.7-.9-1.7a5 5 0 0 1 1.4-5.2q.6-.7 1.7-1 .9-.3 2.1-.3zm3.3 5.3q0-.8-.2-1.4t-.6-1.1l-1.1-.8-1.5-.4h-.3q-.8 0-1.4.2-.8.1-1.3.6l-.8 1a3 3 0 0 0-.1 2.7 3 3 0 0 0 1.7 1.9l1.5.4h.2q.8 0 1.5-.2.8-.1 1.2-.6.5-.4.8-1zM1 74.2 3.3 76v1.4L1 76zm14.6-4.8-.4 1.5-9.6-2.3.3-1.5 1.6.4.1-.3Q6.1 66 6.5 63.7 7 62 8.2 61.2q1.3-.7 3.4-.2l5.7 1.3L17 64l-5.5-1.3q-1.5-.4-2.4.1t-1.2 1.7q-.3 1.4.4 2.4.8 1 2.3 1.4zm-1.4-12q1.5.4 2.6 0t1.8-1.8l.3-1.2a3 3 0 0 0-.4-1.8L18 52l.9-1.3q1 1 1.5 2.4.3 1.4-.4 3-.4 1-1 1.8a5 5 0 0 1-3.5 1.2q-1 0-2-.4l-.5-.2-1.7-1A5 5 0 0 1 10 54q-.1-1 .4-1.9.4-1 1.2-1.8a4 4 0 0 1 3.2-1.1q.8 0 1.5.3l1.1.5zm-2.6-4.7a4 4 0 0 0-.1 2.3l.5 1 1 .8 2.4-6h-1.2q-.6 0-1 .2l-1 .7zm5.6-8.3q.7.3 1.3 0t1.6-1.6a5 5 0 0 1 2.1-1.8q1.1-.5 2.2.3h.1a3 3 0 0 1 1.3 2.2l-.1 1.4-.7 1.4-1.3 1.6-1.4.7a4 4 0 0 1-2.7-.4l.5-1.4q1 .5 2 .2a3 3 0 0 0 1.7-1.4q.6-1 .6-1.7 0-.8-.7-1.2-.8-.4-1.4 0-.7.3-1.5 1.5a5 5 0 0 1-2 1.8q-1.2.5-2.3-.3-.6-.4-1-1l-.3-1.1q0-.6.2-1.3l.5-1.2q.6-.9 1.2-1.3t1.2-.7 1.3-.1q.6 0 1.1.3l-.5 1.4q-.8-.4-1.6-.1t-1.4 1.2l-.5 1.4.1.7z"/></svg>
        <div class="badge-arrow">
          <img class="arrow_badge" src="https://nalakalu.stag.host/wp-content/uploads/2025/10/naka_arrow.svg" alt="">
        </div>
      </div>

      <div class="product-details">
        <div class="product-meta">
          <span class="font-body-small"><?php echo esc_html($last_category_name); ?></span>
          <?php if ( $last_link ) : ?>
            <a href="<?php echo esc_url($last_link); ?>" class="desktop-only btn">
              Ver producto
              <img class="cta-arrow" src="<?php echo esc_url($arrow_url); ?>" alt="" aria-hidden="true" />
            </a>
          <?php endif; ?>
        </div>

        <div class="product-info-row">
          <h2 class="font-heading-5"><?php echo esc_html($last_name); ?></h2>
          <?php if ( $last_price_html ) : ?>
            <span class="font-caption-small"><?php echo $last_price_html; ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="carousel-container" id="<?php echo esc_attr($section_id); ?>-carousel">
 <div class="info-carousel">
  <span class="font-heading-3">Explora la colección</span>

  <div class="info-carousel-actions">
    <a href="<?php echo esc_url($collections_url); ?>" class="btn btn-outline-cafe">
      Ver colección
      <img class="cta-arrow" src="<?php echo esc_url($arrow_url); ?>" alt="" aria-hidden="true" />
    </a>

    <div class="carousel-arrows" aria-label="Controles del carrusel">
      <button
        type="button"
        class="carousel-arrow carousel-arrow--prev"
        id="<?php echo esc_attr($section_id); ?>-prev"
        aria-label="Producto anterior"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none" aria-hidden="true">
          <path d="M8.92156 13.8533C8.86994 13.9011 8.80904 13.9387 8.74235 13.9638C8.67566 13.989 8.60447 14.0012 8.53286 13.9999C8.38823 13.9972 8.25063 13.9393 8.15034 13.839C8.05004 13.7386 7.99527 13.604 7.99807 13.4648C8.00086 13.3256 8.061 13.1931 8.16525 13.0966L14.183 7.52432L0.545416 7.52433C0.400763 7.52433 0.262034 7.46901 0.159749 7.37055C0.0574633 7.27209 -3.7829e-07 7.13855 -3.85967e-07 6.9993C-3.93644e-07 6.86006 0.0574632 6.72651 0.159749 6.62805C0.262034 6.52959 0.400763 6.47428 0.545416 6.47428L14.1815 6.47428L8.16452 0.903411C8.1129 0.855608 8.07157 0.798486 8.04288 0.735306C8.0142 0.672127 7.99872 0.604127 7.99734 0.535191C7.99595 0.466253 8.00869 0.397729 8.03481 0.333531C8.06094 0.269331 8.09995 0.210714 8.14961 0.161025C8.19927 0.111338 8.25861 0.0715515 8.32424 0.0439398C8.38988 0.016328 8.46052 0.00142973 8.53213 9.64944e-05C8.60374 -0.00123579 8.67493 0.0110227 8.74162 0.036173C8.80832 0.0613224 8.86921 0.0988714 8.92083 0.146674L15.7771 6.49528C15.8476 6.56059 15.9036 6.63892 15.942 6.72559C15.9803 6.81225 16 6.90547 16 6.99965C16 7.09383 15.9803 7.18705 15.942 7.27371C15.9036 7.36038 15.8476 7.43871 15.7771 7.50402L8.92156 13.8533Z" fill="#3D332B"/>
        </svg>
      </button>

      <button
        type="button"
        class="carousel-arrow carousel-arrow--next"
        id="<?php echo esc_attr($section_id); ?>-next"
        aria-label="Producto siguiente"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none" aria-hidden="true">
          <path d="M8.92156 13.8533C8.86994 13.9011 8.80904 13.9387 8.74235 13.9638C8.67566 13.989 8.60447 14.0012 8.53286 13.9999C8.38823 13.9972 8.25063 13.9393 8.15034 13.839C8.05004 13.7386 7.99527 13.604 7.99807 13.4648C8.00086 13.3256 8.061 13.1931 8.16525 13.0966L14.183 7.52432L0.545416 7.52433C0.400763 7.52433 0.262034 7.46901 0.159749 7.37055C0.0574633 7.27209 -3.7829e-07 7.13855 -3.85967e-07 6.9993C-3.93644e-07 6.86006 0.0574632 6.72651 0.159749 6.62805C0.262034 6.52959 0.400763 6.47428 0.545416 6.47428L14.1815 6.47428L8.16452 0.903411C8.1129 0.855608 8.07157 0.798486 8.04288 0.735306C8.0142 0.672127 7.99872 0.604127 7.99734 0.535191C7.99595 0.466253 8.00869 0.397729 8.03481 0.333531C8.06094 0.269331 8.09995 0.210714 8.14961 0.161025C8.19927 0.111338 8.25861 0.0715515 8.32424 0.0439398C8.38988 0.016328 8.46052 0.00142973 8.53213 9.64944e-05C8.60374 -0.00123579 8.67493 0.0110227 8.74162 0.036173C8.80832 0.0613224 8.86921 0.0988714 8.92083 0.146674L15.7771 6.49528C15.8476 6.56059 15.9036 6.63892 15.942 6.72559C15.9803 6.81225 16 6.90547 16 6.99965C16 7.09383 15.9803 7.18705 15.942 7.27371C15.9036 7.36038 15.8476 7.43871 15.7771 7.50402L8.92156 13.8533Z" fill="#3D332B"/>
        </svg>
      </button>
    </div>
  </div>
</div>

  <div class="carousel-wrapper" id="<?php echo esc_attr($section_id); ?>-wrapper">
    <?php if ( $loop_q->have_posts() ) : ?>
      <?php while ( $loop_q->have_posts() ) : $loop_q->the_post(); ?>
        <?php
          $pid    = get_the_ID();
          $plink  = get_permalink($pid);
          $pname  = get_the_title($pid);
          $pprice = '';

          if ( function_exists('wc_get_product') ) {
              $pobj = wc_get_product($pid);
              if ( $pobj ) $pprice = $pobj->get_price_html();
          }

          $thumb = get_the_post_thumbnail_url($pid, 'large');
        ?>
        <a class="carousel-item" href="<?php echo esc_url($plink); ?>">
          <?php if ( $thumb ) : ?>
            <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($pname); ?>" class="item-image">
          <?php else : ?>
            <div class="item-image" style="background:#f1f1f1;"></div>
          <?php endif; ?>

          <div class="item-info">
            <div class="item-details">
              <div class="font-button"><?php echo esc_html($pname); ?></div>
            </div>
            <?php if ( $pprice ) : ?>
              <div class="font-overline"><?php echo $pprice; ?></div>
            <?php endif; ?>
          </div>
        </a>
      <?php endwhile; wp_reset_postdata(); ?>
    <?php else : ?>
      <div style="padding:1rem 3rem; opacity:.7;">No hay productos en esta colección.</div>
    <?php endif; ?>
  </div>

  <div class="carousel-dots" id="<?php echo esc_attr($section_id); ?>-dots" hidden></div>

  <a href="<?php echo esc_url($collections_url); ?>" class="mobile-only-flex btn btn-outline-cafe">Ver colección</a>
</section>

<script>
/* Rotación del badge (igual que antes) */
(() => {
  const els = document.querySelectorAll('.product-badge');
  function update() {
    const y = window.scrollY || window.pageYOffset;
    els.forEach(el => {
      const speed = parseFloat(el.dataset.rotSpeed) || 0.25;
      const angle = (y * speed) % 360;
      el.style.transform = `rotate(${angle}deg)`;
    });
  }
  let ticking = false;
  window.addEventListener('scroll', () => {
    if (!ticking) {
      requestAnimationFrame(() => { update(); ticking = false; });
      ticking = true;
    }
  }, { passive: true });
  update();
})();
</script>

<script>
(function () {
  document.querySelectorAll('.carousel-container').forEach(function (carousel) {
    var wrapper = carousel.querySelector('.carousel-wrapper');
    var dotsWrap = carousel.querySelector('.carousel-dots');

    if (!wrapper) return;

    var items = Array.prototype.slice.call(wrapper.querySelectorAll('.carousel-item'));
    if (!items.length) return;

    var prevBtn =
      carousel.querySelector('[data-collection-carousel-prev]') ||
      carousel.querySelector('.carousel-arrow--prev') ||
      document.getElementById(carousel.id.replace('-carousel', '-prev'));

    var nextBtn =
      carousel.querySelector('[data-collection-carousel-next]') ||
      carousel.querySelector('.carousel-arrow--next') ||
      document.getElementById(carousel.id.replace('-carousel', '-next'));

    var index = 0;
    var mql = window.matchMedia('(max-width: 768px)');

    function getViewportWidth() {
      return carousel.getBoundingClientRect().width;
    }

    function getStep() {
      if (items.length > 1) {
        var firstLeft = items[0].offsetLeft;
        var secondLeft = items[1].offsetLeft;
        var diff = secondLeft - firstLeft;

        if (diff > 0) return diff;
      }

      var itemRect = items[0].getBoundingClientRect();
      return itemRect.width || getViewportWidth();
    }

    function getMaxScroll() {
      var max = wrapper.scrollWidth - getViewportWidth();
      return max > 0 ? max : 0;
    }

    function getMaxIndex() {
      var step = getStep();
      var maxScroll = getMaxScroll();

      if (!step || !maxScroll) return 0;

      return Math.ceil(maxScroll / step);
    }

    function clampIndex(value) {
      return Math.max(0, Math.min(getMaxIndex(), value));
    }

    function getTranslateForIndex(value) {
      var step = getStep();
      var maxScroll = getMaxScroll();

      var x = value * step;

      if (x > maxScroll) {
        x = maxScroll;
      }

      return -x;
    }

    function updateButtons() {
      var max = getMaxIndex();

      if (prevBtn) {
        prevBtn.disabled = index <= 0;
        prevBtn.classList.toggle('is-disabled', index <= 0);
      }

      if (nextBtn) {
        nextBtn.disabled = index >= max;
        nextBtn.classList.toggle('is-disabled', index >= max);
      }
    }

    function updateDots() {
      if (!dotsWrap || dotsWrap.hidden) return;

      var dots = dotsWrap.querySelectorAll('.carousel-dot');

      dots.forEach(function (dot, dotIndex) {
        var active = dotIndex === index;
        dot.classList.toggle('is-active', active);
        dot.setAttribute('aria-selected', active ? 'true' : 'false');
      });
    }

    function buildDots() {
      if (!dotsWrap) return;

      dotsWrap.innerHTML = '';

      var max = getMaxIndex();

      if (!mql.matches || max <= 0) {
        dotsWrap.hidden = true;
        return;
      }

      for (var i = 0; i <= max; i++) {
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'carousel-dot';
        dot.dataset.index = String(i);
        dot.setAttribute('aria-label', 'Ir al slide ' + (i + 1));
        dotsWrap.appendChild(dot);
      }

      dotsWrap.hidden = false;
    }

    function goTo(nextIndex, animate) {
      index = clampIndex(nextIndex);

      wrapper.style.transition = animate
        ? 'transform .35s cubic-bezier(.22,.61,.36,1)'
        : 'none';

      wrapper.style.transform = 'translateX(' + getTranslateForIndex(index) + 'px)';

      updateButtons();
      updateDots();
    }

    function refresh() {
      index = clampIndex(index);
      buildDots();
      goTo(index, false);
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', function (e) {
        e.preventDefault();
        goTo(index - 1, true);
      });
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', function (e) {
        e.preventDefault();
        goTo(index + 1, true);
      });
    }

    if (dotsWrap) {
      dotsWrap.addEventListener('click', function (e) {
        var dot = e.target.closest('.carousel-dot');
        if (!dot) return;

        var nextIndex = parseInt(dot.dataset.index, 10);
        if (isNaN(nextIndex)) return;

        goTo(nextIndex, true);
      });
    }

    wrapper.querySelectorAll('img').forEach(function (img) {
      img.setAttribute('draggable', 'false');

      if (!img.complete) {
        img.addEventListener('load', refresh, { once: true });
      }
    });

    window.addEventListener('resize', refresh);

    if (mql.addEventListener) {
      mql.addEventListener('change', refresh);
    } else if (mql.addListener) {
      mql.addListener(refresh);
    }

    setTimeout(refresh, 50);
    setTimeout(refresh, 500);
    refresh();
  });
})();
</script>