<?php
/**
 * Campos ACF para el bloque "Animated Title + Image"
 */

if ( ! function_exists('acf_add_local_field_group') ) {
    return;
}

add_action('acf/include_fields', function () {
    if ( ! function_exists('acf_add_local_field_group') ) {
        return;
    }

    acf_add_local_field_group( array(
        'key' => 'group_animated_title_image',
        'title' => 'Animated title image fields',
        'fields' => array(
            array(
                'key' => 'field_ati_desc',
                'label' => 'Description',
                'name' => 'text',
                'type' => 'textarea',
                'rows' => 3,
                'new_lines' => '', // plano, sin <p>
            ),
            array(
                'key' => 'field_ati_top',
                'label' => 'top heading',
                'name' => 'top_heading',
                'type' => 'text',
            ),
            array(
                'key' => 'field_ati_left',
                'label' => 'left heading',
                'name' => 'left_heading',
                'type' => 'text',
            ),
            array(
                'key' => 'field_ati_right',
                'label' => 'right heading',
                'name' => 'right_heading',
                'type' => 'text',
            ),
            array(
                'key' => 'field_ati_bottom',
                'label' => 'bottom heading',
                'name' => 'bottom_heading',
                'type' => 'text',
            ),
            array(
                'key' => 'field_ati_image',
                'label' => 'image',
                'name' => 'image',
                'type' => 'image',
                'return_format' => 'array',
                'preview_size' => 'medium',
                'library' => 'all',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'block',
                    'operator' => '==',
                    'value'    => 'nalakalu-animated-title', // <— igual al "name" del block.json
                ),
            ),
        ),
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'left',
        'active' => true,
        'show_in_rest' => 0,
    ) );
});
