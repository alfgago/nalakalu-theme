<?php
/**
 * Bloque: Showrooms Main
 * Taxonomía: showroom
 * Campo ACF en la taxonomía: 'background' (tipo imagen)
 */

if ( ! defined('ABSPATH') ) {
    exit;
}

// ID único del bloque
$section_id = 'showrooms-main-' . ( isset( $block['id'] ) ? $block['id'] : uniqid() );

// Clases base + las del editor
$classes = 'nl-showrooms-main';
if ( ! empty( $block['className'] ) ) {
    $classes .= ' ' . esc_attr( $block['className'] );
}
if ( ! empty( $block['align'] ) ) {
    $classes .= ' align' . esc_attr( $block['align'] );
}

// Taxonomía
$taxonomy = 'showroom';

// Obtenemos hasta 3 showrooms
$terms = get_terms( [
    'taxonomy'   => $taxonomy,
    'hide_empty' => false,
    'number'     => 3,
    'orderby'    => 'term_order',
    'order'      => 'ASC',
] );

if ( is_wp_error( $terms ) || empty( $terms ) ) {
    if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
        echo '<p><em>No se encontraron términos en la taxonomía "showroom". Crea algunos showrooms primero.</em></p>';
    }
    return;
}
?>

<section id="<?php echo esc_attr( $section_id ); ?>" class="<?php echo esc_attr( $classes ); ?>">
    <?php
    $total = count( $terms );
    $i     = 0;

    foreach ( $terms as $term ) :

        // Campo ACF de imagen en la taxonomía: 'background'
        $image = get_field( 'background', $taxonomy . '_' . $term->term_id );

        $image_url = '';
        $image_alt = $term->name;

        if ( is_array( $image ) ) {
            $image_url = ! empty( $image['url'] ) ? $image['url'] : '';
            if ( ! empty( $image['alt'] ) ) {
                $image_alt = $image['alt'];
            }
        } elseif ( is_string( $image ) ) {
            $image_url = $image;
        }

        $slug         = sanitize_title( $term->name );
        $showroom_url = home_url( '/' . $slug . '/' );
    ?>
        <a href="<?php echo esc_url( $showroom_url ); ?>" class="nl-showrooms-main__card">
            <?php if ( $image_url ) : ?>
                <img
                    src="<?php echo esc_url( $image_url ); ?>"
                    alt="<?php echo esc_attr( $image_alt ); ?>"
                    class="nl-showrooms-main__img"
                    loading="lazy"
                />
            <?php endif; ?>

            <div class="nl-showrooms-main__content">
                <p class="font-overline">Showroom</p>

                <div class="nl-showrooms-main__title-row">
                    <h2 class="nl-showrooms-main__title font-heading-2">
                        <?php echo esc_html( $term->name ); ?>
                    </h2>

                    <span class="nl-showrooms-main__arrow" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12"
                             viewBox="0 0 12 12" fill="none">
                            <path d="M11.2076 9.97564C11.2054 10.0454 11.1896 10.1143 11.161 10.1785C11.1323 10.2427 11.0914 10.3009 11.0406 10.3498C10.9381 10.4486 10.8014 10.5035 10.6606 10.5023C10.5198 10.5012 10.3865 10.4441 10.29 10.3437C10.1935 10.2433 10.1417 10.1078 10.146 9.96698L10.3946 1.83808L0.901995 11.3306C0.801308 11.4313 0.66563 11.4888 0.524811 11.4904C0.383992 11.4919 0.249566 11.4375 0.151105 11.339C0.0526435 11.2406 -0.00178764 11.1061 -0.000213707 10.9653C0.00136022 10.8245 0.0588101 10.6888 0.159497 10.5881L9.65104 1.0966L1.52364 1.34561C1.45391 1.34774 1.38475 1.33611 1.32011 1.3114C1.25547 1.2867 1.19661 1.24939 1.1469 1.2016C1.09719 1.15382 1.0576 1.0965 1.03039 1.03292C1.00318 0.969341 0.988886 0.900741 0.988317 0.83104C0.987748 0.761339 1.00092 0.691901 1.02708 0.626692C1.05324 0.561483 1.09188 0.501778 1.14078 0.450988C1.18969 0.400199 1.2479 0.359317 1.31211 0.330679C1.37632 0.30204 1.44525 0.286206 1.51498 0.284079L10.7765 0.000871313C10.8717 -0.00201452 10.9661 0.0143417 11.0541 0.0489611C11.142 0.0835802 11.2217 0.135751 11.2883 0.202348C11.3549 0.268946 11.4071 0.348601 11.4417 0.436545C11.4763 0.524489 11.4926 0.618913 11.4898 0.714164L11.2076 9.97564Z" fill="#3D332B"/>
                        </svg>
                    </span>
                </div>
            </div>
        </a>


    <?php endforeach; ?>
</section>
