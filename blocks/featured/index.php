<?php

if ( ! function_exists('get_field') ) {
    echo '<p><em>ACF plugin required.</em></p>';
    return;
}

// Campos ACF
$block_title = get_field('title');
$block_desc  = get_field('description');
$arrow_url   = 'https://nalakalu.stag.host/wp-content/uploads/2025/10/arrow_forward.svg';

// IDs y clases
$section_id = 'featured-block-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$class_name = 'featured-section';
if ( !empty($block['className']) ) $class_name .= ' ' . esc_attr($block['className']);
if ( !empty($block['align']) )     $class_name .= ' align' . esc_attr($block['align']);

// URL "Ver más productos"
$products_url = 'https://nalakalu.com/tienda/';

// Requiere WooCommerce
if ( ! function_exists('wc_get_product') ) {
    echo '<section id="'.esc_attr($section_id).'" class="'.esc_attr($class_name).'"><p style="opacity:.7">WooCommerce no está activo.</p></section>';
    return;
}

// Traer destacados (hasta 4)
$featured_ids = function_exists('wc_get_featured_product_ids') ? wc_get_featured_product_ids() : array();

if ( ! empty($featured_ids) ) {
    $limit_ids = array_slice($featured_ids, 0, 4);

    $q = new WP_Query(array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 4,
        'post__in'       => $limit_ids,
        'orderby'        => 'post__in',
    ));
} else {
    $q = new WP_Query(array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 4,
        'tax_query'      => array(
            array(
                'taxonomy' => 'product_visibility',
                'field'    => 'name',
                'terms'    => array('featured'),
                'operator' => 'IN',
            ),
        ),
        'orderby' => 'date',
        'order'   => 'DESC',
    ));
}
?>

<section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($class_name); ?>">
  <div class="row1">
    <div class="column1">
      <span class="font-overline">DESTACADOS</span>
      <h3 class="font-heading-1 light"><?php echo $block_title ? esc_html($block_title) : 'PIEZAS QUE AMAMOS'; ?></h3>
    </div>

    <div class="column2">
      <a href="<?php echo esc_url($products_url); ?>" class="btn btn-outline-cafe">
        Ver más productos
        <img class="desktop-only cta-arrow" src="<?php echo esc_url($arrow_url); ?>" alt="" aria-hidden="true" />
      </a>

      <?php if ($block_desc): ?>
        <p class="font-body-medium-light"><?php echo wp_kses_post($block_desc); ?></p>
      <?php endif; ?>
    </div>
  </div>

  <?php if ( $q->have_posts() ) : ?>

    <div class="featured-mobile-slider" id="<?php echo esc_attr($section_id); ?>-mob">
      <div class="featured-mobile-track" id="<?php echo esc_attr($section_id); ?>-mob-track">

        <?php while ( $q->have_posts() ) : $q->the_post(); ?>
          <?php
            $pid   = get_the_ID();
            $plink = get_permalink($pid);
            $pname = get_the_title($pid);

            // Descripción corta
            $pdesc = '';
            $product = wc_get_product($pid);

            if ($product) {
                $short = $product->get_short_description();

                if ($short) {
                    $pdesc = wp_strip_all_tags($short);
                } else {
                    $raw = has_excerpt($pid)
                        ? get_the_excerpt($pid)
                        : wp_strip_all_tags(get_post_field('post_content', $pid));

                    $pdesc = wp_trim_words($raw, 30, '…');
                }
            }

            // Imagen
            $thumb_url = get_the_post_thumbnail_url($pid, 'large');
          ?>

          <a class="product-row-link featured-mobile-slide" href="<?php echo esc_url($plink); ?>" aria-label="<?php echo esc_attr($pname); ?>">
            <div class="row-products">
              <div class="products-column1">
                <h4 class="font-heading-5"><?php echo esc_html($pname); ?></h4>
              </div>

              <div class="products-column2">
                <p class="font-body-medium-light"><?php echo esc_html($pdesc); ?></p>

                <?php if ($thumb_url): ?>
                  <img class="product-image" src="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr($pname); ?>">
                <?php else: ?>
                  <div class="product-image" style="width:10rem; height:10rem; background:#eee; border-radius:10px;"></div>
                <?php endif; ?>
              </div>
            </div>
          </a>

        <?php endwhile; wp_reset_postdata(); ?>

      </div>

      <div class="featured-mobile-dots" id="<?php echo esc_attr($section_id); ?>-mob-dots" hidden></div>
    </div>

    <a href="<?php echo esc_url($products_url); ?>" class="btn btn-outline-cafe featured-mobile-cta">
      Ver más productos
      <img class="desktop-only cta-arrow" src="<?php echo esc_url($arrow_url); ?>" alt="" aria-hidden="true" />
    </a>

  <?php else: ?>

    <p style="opacity:.7; margin-top:2rem;">No hay productos destacados.</p>

  <?php endif; ?>
</section>