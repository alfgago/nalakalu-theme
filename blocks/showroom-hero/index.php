<?php
/**
 * Bloque ACF: showroom-hero
 * Campos:
 * - background (imagen de fondo)
 * - url        (url del botón)
 * Título: tomado del título de la página donde se inserta el bloque.
 */

if ( ! defined('ABSPATH') ) {
    exit;
}

// ID único del bloque
$section_id = 'showroom-hero-' . ( isset( $block['id'] ) ? $block['id'] : uniqid() );

// Clases base + las que agregue el editor
$classes = 'nl-showroom-hero';
if ( ! empty( $block['className'] ) ) {
    $classes .= ' ' . esc_attr( $block['className'] );
}
if ( ! empty( $block['align'] ) ) {
    $classes .= ' align' . esc_attr( $block['align'] );
}

// Campos ACF
$bg      = get_field( 'background' ); // imagen
$btn_url = get_field( 'url' );        // url del botón

$bg_url = '';
if ( is_array( $bg ) && ! empty( $bg['url'] ) ) {
    $bg_url = $bg['url'];
} elseif ( is_string( $bg ) ) {
    $bg_url = $bg;
}

// Style inline para combinar gradiente + imagen
$bg_style = '';
if ( $bg_url ) {
    $bg_style = "background-image: linear-gradient(to bottom,
        rgba(60, 50, 40, 0.3) 0%,
        rgba(40, 35, 30, 0.4) 50%,
        rgba(30, 25, 20, 0.5) 100%
      ), url('" . esc_url( $bg_url ) . "');";
}

// Título: el título de la página actual
$post_id    = get_the_ID();
$page_title = $post_id ? get_the_title( $post_id ) : '';
?>

<section id="<?php echo esc_attr( $section_id ); ?>" class="<?php echo esc_attr( $classes ); ?>">
    <div class="nl-showroom-hero__background"<?php if ( $bg_style ) : ?> style="<?php echo esc_attr( $bg_style ); ?>"<?php endif; ?>></div>

    <div class="nl-showroom-hero__content">
        <span class="font-overline text-white">SHOWROOM</span>

        <?php if ( $page_title ) : ?>
            <h1 class="font-heading-display text-white">
                <?php echo esc_html( $page_title ); ?>
            </h1>
        <?php endif; ?>

        <?php if ( $btn_url ) : ?>
            <a target="_blank" href="<?php echo esc_url( $btn_url ); ?>" class="btn btn-blanco">
                Agendar cita <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                                  <mask id="mask0_1985_2781" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="20" height="20">
                                    <rect width="20" height="20" fill="#D9D9D9"/>
                                  </mask>
                                  <g mask="url(#mask0_1985_2781)">
                                    <path d="M13.4779 10.832H3.33203V9.16536H13.4779L8.8112 4.4987L9.9987 3.33203L16.6654 9.9987L9.9987 16.6654L8.8112 15.4987L13.4779 10.832Z" fill="#3D332B"/>
                                  </g>
                                </svg>
            </a>
        <?php endif; ?>
    </div>
</section>
