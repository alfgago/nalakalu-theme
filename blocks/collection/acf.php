<?php
/**
 * ACF fields para el bloque "Collection"
 * - collection_selector: selector de término de la taxonomía 'coleccion' (o 'pa_coleccion')
 * Nota: el video de fondo se toma del término (campo ACF en la taxonomía) con name 'background_video' (tipo URL).
 */
if ( ! function_exists('acf_add_local_field_group') ) { return; }

add_action('acf/include_fields', function () {
    if ( ! function_exists('acf_add_local_field_group') ) return;

    $taxonomy_slug = taxonomy_exists('coleccion')
        ? 'coleccion'
        : ( taxonomy_exists('pa_coleccion') ? 'pa_coleccion' : 'coleccion' );

    acf_add_local_field_group(array(
        'key'    => 'group_collection_block',
        'title'  => 'Collection – Opciones',
        'fields' => array(
            array(
                'key'           => 'field_collection_selector',
                'label'         => 'Colección',
                'name'          => 'collection_selector',
                'type'          => 'taxonomy',
                'taxonomy'      => $taxonomy_slug,
                'field_type'    => 'select',
                'allow_null'    => 1,
                'multiple'      => 0,
                'return_format' => 'id',
                'add_term'      => 0,
                'save_terms'    => 0,
                'load_terms'    => 0,
                'instructions'  => 'Elegí la colección para el hero y el carrusel.',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'block',
                    'operator' => '==',
                    'value'    => 'nalakalu/collection',
                ),
            ),
        ),
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'left',
        'active' => true,
    ));
});
