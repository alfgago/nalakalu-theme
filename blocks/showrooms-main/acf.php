<?php
/**
 * Registro del bloque ACF: Showrooms Main
 */

if ( ! defined('ABSPATH') ) {
    exit;
}

if ( function_exists('acf_register_block_type') && ! function_exists('nalakalu_register_block_showrooms_main') ) {

    function nalakalu_register_block_showrooms_main() {

        acf_register_block_type( [
            'name'            => 'showrooms-main',
            'title'           => __('Showrooms principal', 'nalakalu'),
            'description'     => __('Bloque con los 3 showrooms principales.', 'nalakalu'),
            'category'        => 'layout',
            'icon'            => 'building',
            'keywords'        => [ 'showroom', 'local', 'tienda' ],
            'supports'        => [
                'align'  => [ 'full', 'wide' ],
                'anchor' => true,
            ],
            'mode'            => 'preview',
            // Ruta relativa al tema
            'render_template' => 'blocks/showrooms-main/block.php',
            // Encola el CSS del bloque
            'enqueue_style'   => get_theme_file_uri( 'blocks/showrooms-main/style.css' ),
        ] );
    }

    add_action( 'acf/init', 'nalakalu_register_block_showrooms_main' );
}
