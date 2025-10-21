<?php

if ( ! function_exists('get_field') ) {
    echo '<p><em>ACF plugin required.</em></p>';
    return;
}

// Campos
$desc   = get_field('text');
$top    = get_field('top_heading');
$left   = get_field('left_heading');
$right  = get_field('right_heading');
$bottom = get_field('bottom_heading');
$image  = get_field('image');

// Imagen
$img_url = isset($image['url']) ? esc_url($image['url']) : '';
$img_alt = isset($image['alt']) ? esc_attr($image['alt']) : '';
$img_w   = isset($image['width'])  ? (int) $image['width']  : 0;
$img_h   = isset($image['height']) ? (int) $image['height'] : 0;

/** CTA: Ver productos (Shop de Woo o fallbacks) */
$cta_url = '';
if ( function_exists('wc_get_page_permalink') ) {
    $cta_url = wc_get_page_permalink('shop');
}
if ( empty($cta_url) ) {
    $p = get_page_by_path('product-page');
    if ($p) $cta_url = get_permalink($p);
}
if ( empty($cta_url) ) {
    $p = get_page_by_path('products');
    if ($p) $cta_url = get_permalink($p);
}
if ( empty($cta_url) ) {
    $cta_url = home_url('/product-page/');
}
$arrow_url = 'https://nalakalu.stag.host/wp-content/uploads/2025/10/arrow_forward.svg';

// ID único de bloque (ACF pasa $block)
$section_id = 'animated-title-' . ( isset($block['id']) ? $block['id'] : uniqid() );

// Clases extra (align/className)
$class_name = 'animated-title';
if ( !empty($block['className']) ) {
    $class_name .= ' ' . esc_attr($block['className']);
}
if ( !empty($block['align']) ) {
    $class_name .= ' align' . esc_attr($block['align']);
}
?>
<section id="<?php echo esc_attr($section_id); ?>" class="<?php echo $class_name; ?>">
    <div class="title-row first">
        <?php if ($desc): ?>
            <p class="animated text"><?php echo esc_html($desc); ?></p>
        <?php endif; ?>

        <?php if ($top): ?>
            <h2 class="animated heading"><?php echo esc_html($top); ?></h2>
        <?php endif; ?>
    </div>

    <?php if ($left || $img_url || $right): ?>
    <div class="title-row second">
        <?php if ($left): ?>
            <span class="animated heading"><?php echo esc_html($left); ?></span>
        <?php endif; ?>

        <?php if ($img_url): ?>
            <img
                class="animated image"
                src="<?php echo $img_url; ?>"
                alt="<?php echo $img_alt; ?>"
                <?php if ($img_w) echo 'width="' . $img_w . '"'; ?>
                <?php if ($img_h) echo ' height="' . $img_h . '"'; ?>
                loading="lazy" decoding="async"
            >
        <?php endif; ?>

        <?php if ($right): ?>
            <span class="animated heading"><?php echo esc_html($right); ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($bottom): ?>
    <div class="title-row third">
        <span class="animated heading"><?php echo esc_html($bottom); ?></span>

        <!-- CTA: Ver productos (+ flecha) -->
        <a class="animated text cta" href="<?php echo esc_url($cta_url); ?>">
            Ver productos
            <img class="cta-arrow" src="<?php echo esc_url($arrow_url); ?>" alt="" aria-hidden="true" />
        </a>
    </div>
    <?php endif; ?>
</section>
