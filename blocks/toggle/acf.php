<?php
/**
 * Campos ACF para el bloque "Toggle / Accordion"
 * - title (text)
 * - button_text (text)
 * - url_button (url)
 * - repeater:
 *     - title_repeater (text)
 *     - text_repeater (textarea)
 *     - img_repeater (image, return array)
 */
if ( ! function_exists('acf_add_local_field_group') ) { return; }

add_action('acf/include_fields', function () {
    if ( ! function_exists('acf_add_local_field_group') ) return;

    acf_add_local_field_group(array(
        'key'    => 'group_toggle_block',
        'title'  => 'Toggle – Campos',
        'fields' => array(
            array(
                'key'         => 'field_toggle_title',
                'label'       => 'Título',
                'name'        => 'title',
                'type'        => 'text',
                'placeholder' => 'Preguntas frecuentes',
            ),
            array(
                'key'         => 'field_toggle_button_text',
                'label'       => 'Texto del botón',
                'name'        => 'button_text',
                'type'        => 'text',
                'placeholder' => 'Ver más',
            ),
            array(
                'key'         => 'field_toggle_url_button',
                'label'       => 'URL del botón',
                'name'        => 'url_button',
                'type'        => 'url',
                'placeholder' => 'https://',
            ),
            array(
                'key'       => 'field_toggle_repeater',
                'label'     => 'Items',
                'name'      => 'repeater',
                'type'      => 'repeater',
                'collapsed' => 'field_toggle_item_title',
                'button_label' => 'Agregar ítem',
                'layout'    => 'row',
                'sub_fields'=> array(
                    array(
                        'key'   => 'field_toggle_item_title',
                        'label' => 'Título del ítem',
                        'name'  => 'title_repeater',
                        'type'  => 'text',
                    ),
                    array(
                        'key'        => 'field_toggle_item_text',
                        'label'      => 'Texto del ítem',
                        'name'       => 'text_repeater',
                        'type'       => 'textarea',
                        'rows'       => 4,
                        'new_lines'  => 'wpautop',
                        'placeholder'=> 'Contenido del acordeón…'
                    ),
                    array(
                        'key'           => 'field_toggle_item_img',
                        'label'         => 'Imagen del ítem',
                        'name'          => 'img_repeater',
                        'type'          => 'image',
                        'return_format' => 'array',
                        'preview_size'  => 'medium',
                        'library'       => 'all'
                    ),
                ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'block',
                    'operator' => '==',
                    'value'    => 'nalakalu/toggle',
                ),
            ),
        ),
        'position' => 'normal',
        'style'    => 'default',
        'active'   => true,
    ));
});
