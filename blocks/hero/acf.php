<?php

if (! function_exists('acf_add_local_field_group')) {
    return;
}

acf_add_local_field_group([
    'key' => 'group_hero_block',
    'title' => 'Hero Block Fields',
    'fields' => [
        [
            'key' => 'field_hero_bg',
            'label' => 'Background Image',
            'name' => 'background_image',
            'type' => 'image',
            'return_format' => 'array',
            'preview_size' => 'medium',
            'library' => 'all',
        ],
        [
            'key' => 'field_hero_title',
            'label' => 'Title',
            'name' => 'title',
            'type' => 'text',
            'placeholder' => 'Enter hero title',
        ],
        [
            'key' => 'field_hero_desc',
            'label' => 'Description',
            'name' => 'description',
            'type' => 'textarea',
            'rows' => 4,
            'new_lines' => 'wpautop',
            'placeholder' => 'Enter a short description',
        ],
    ],
    'location' => [
        [
            [
                'param' => 'block',
                'operator' => '==',
                'value' => 'nalakalu/hero', // must match block.json name
            ],
        ],
    ],
]);
