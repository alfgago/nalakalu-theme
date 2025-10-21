<?php
/**
 * ACF fields para el bloque "Featured"
 * Campos:
 * - title (text)
 * - description (textarea)
 *
 * Nota: el valor de 'value' en 'location' debe coincidir con el "name" del bloque (block.json),
 * aquí asumimos 'nalakalu/featured'.
 */
if ( ! function_exists('acf_add_local_field_group') ) { return; }

add_action('acf/include_fields', function () {
    if ( ! function_exists('acf_add_local_field_group') ) return;

    acf_add_local_field_group(array(
        'key'    => 'group_featured_block',
        'title'  => 'Featured – Campos',
        'fields' => array(
            array(
                'key'           => 'field_featured_title',
                'label'         => 'Título',
                'name'          => 'title',
                'type'          => 'text',
                'placeholder'   => 'Productos destacados',
            ),
            array(
                'key'           => 'field_featured_description',
                'label'         => 'Descripción',
                'name'          => 'description',
                'type'          => 'textarea',
                'rows'          => 3,
                'new_lines'     => 'wpautop',
                'placeholder'   => 'Un breve texto introductorio…',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'block',
                    'operator' => '==',
                    'value'    => 'nalakalu/featured',
                ),
            ),
        ),
        'position' => 'normal',
        'style'    => 'default',
        'active'   => true,
    ));
});
