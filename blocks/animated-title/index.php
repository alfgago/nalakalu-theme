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

// Fondo
$bg_raw = get_field('background_color');
$bg_hex = '';
if ($bg_raw) {
    if (function_exists('sanitize_hex_color')) {
        $bg_hex = sanitize_hex_color($bg_raw);
    } else {
        if (preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $bg_raw)) {
            $bg_hex = $bg_raw;
        }
    }
}

$style_parts = [];
if ($bg_hex) {
    $style_parts[] = 'background-color:' . $bg_hex;
    $style_parts[] = '--reveal-bg:' . $bg_hex;
}
$section_style = implode(';', $style_parts);
if ($section_style !== '') {
    $section_style .= ';';
}

// Toggle CTA
$show_cta = (bool) get_field('show_cta');

// Imagen
$img_url = isset($image['url']) ? esc_url($image['url']) : '';
$img_alt = isset($image['alt']) ? esc_attr($image['alt']) : '';
$img_w   = isset($image['width'])  ? (int) $image['width']  : 0;
$img_h   = isset($image['height']) ? (int) $image['height'] : 0;

/** CTA */
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

// ID único
$section_id = 'animated-title-' . ( isset($block['id']) ? $block['id'] : uniqid() );

// Clases extra
$class_name = 'animated_container';
if ( !empty($block['className']) ) {
    $class_name .= ' ' . esc_attr($block['className']);
}
if ( !empty($block['align']) ) {
    $class_name .= ' align' . esc_attr($block['align']);
}
?>

<section
    id="<?php echo esc_attr($section_id); ?>"
    class="<?php echo esc_attr($class_name); ?>"
    style="<?php echo esc_attr($section_style); ?>"
>
    <div class="title-row first">
        <?php if ($desc): ?>
            <p class="text-cafe font-body-small desktop-only reveal-up" style="--delay: .06s;">
                <span class="reveal-up__inner"><?php echo esc_html($desc); ?></span>
            </p>
        <?php endif; ?>

        <?php if ($top): ?>
            <h2 class="font-heading-display reveal-up" style="--delay: .14s;">
                <span class="reveal-up__inner"><?php echo esc_html($top); ?></span>
            </h2>
        <?php endif; ?>
    </div>

    <?php if ($left || $img_url || $right): ?>
        <div class="title-row second">
            <?php if ($left): ?>
                <span class="font-heading-display reveal-up" style="--delay: .24s;">
                    <span class="reveal-up__inner"><?php echo esc_html($left); ?></span>
                </span>
            <?php endif; ?>

            <?php if ($img_url): ?>
                <div class="animated-media reveal-x" style="--delay: .36s;">
                    <img
                        class="image"
                        src="<?php echo $img_url; ?>"
                        alt="<?php echo $img_alt; ?>"
                        <?php if ($img_w) echo 'width="' . $img_w . '"'; ?>
                        <?php if ($img_h) echo 'height="' . $img_h . '"'; ?>
                        loading="lazy"
                        decoding="async"
                    >
                </div>
            <?php endif; ?>

            <?php if ($right): ?>
                <span class="font-heading-display desktop-only reveal-up" style="--delay: .48s;">
                    <span class="reveal-up__inner"><?php echo esc_html($right); ?></span>
                </span>
            <?php endif; ?>
        </div>

        <div class="title-row second mobile-only-animated">
            <?php if ($right): ?>
                <span class="font-heading-display reveal-up" style="--delay: .48s;">
                    <span class="reveal-up__inner"><?php echo esc_html($right); ?></span>
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($bottom || $show_cta): ?>
        <div class="title-row third">
            <?php if ($bottom): ?>
                <span class="font-heading-display reveal-up" style="--delay: .60s;">
                    <span class="reveal-up__inner"><?php echo esc_html($bottom); ?></span>
                </span>
            <?php endif; ?>

            <?php if ($desc): ?>
                <p class="text-cafe font-body-small mobile-only-animated reveal-up" style="--delay: .70s;">
                    <span class="reveal-up__inner"><?php echo esc_html($desc); ?></span>
                </p>
            <?php endif; ?>

            <?php if ($show_cta): ?>
                <a class="btn btn-outline-cafe reveal-fade" style="--delay: .82s;" href="<?php echo esc_url($cta_url); ?>">
                    Ver productos
                    <img class="desktop-only cta-arrow" src="<?php echo esc_url($arrow_url); ?>" alt="" aria-hidden="true" />
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>