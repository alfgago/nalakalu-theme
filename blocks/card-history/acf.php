<?php
/**
 * ACF fields para el bloque "Card History"
 * Campos:
 * - background_image (image)
 * - card (repeater)
 *    - pretititle (text)
 *    - age (text)
 *    - title (text)
 *    - description (textarea)
 *    - image (image)
 */
if ( ! function_exists('acf_add_local_field_group') ) { return; }

add_action('acf/include_fields', function () {
  if ( ! function_exists('acf_add_local_field_group') ) return;

  acf_add_local_field_group(array(
    'key' => 'group_card_history',
    'title' => 'Card History – Campos',
    'fields' => array(
      array(
        'key' => 'field_ch_bg_image',
        'label' => 'Background image',
        'name' => 'background_image',
        'type' => 'image',
        'return_format' => 'array',
        'preview_size'  => 'medium',
        'library'       => 'all',
      ),
      array(
        'key' => 'field_ch_cards',
        'label' => 'Cards',
        'name' => 'card',
        'type' => 'repeater',
        'layout' => 'row',
        'button_label' => 'Agregar card',
        'sub_fields' => array(
          array(
            'key' => 'field_ch_pretititle',
            'label' => 'Pretititle',
            'name' => 'pretititle',
            'type' => 'text',
          ),
          array(
            'key' => 'field_ch_age',
            'label' => 'Age / Año',
            'name' => 'age',
            'type' => 'text',
          ),
          array(
            'key' => 'field_ch_title',
            'label' => 'Title',
            'name' => 'title',
            'type' => 'text',
          ),
          array(
            'key' => 'field_ch_description',
            'label' => 'Description',
            'name' => 'description',
            'type' => 'textarea',
            'rows' => 5,
            'new_lines' => 'br'
          ),
          array(
            'key' => 'field_ch_image',
            'label' => 'Image',
            'name' => 'image',
            'type' => 'image',
            'return_format' => 'array',
            'preview_size'  => 'medium',
            'library'       => 'all',
          ),
        ),
      ),
    ),
    'location' => array(
      array(
        array(
          'param'    => 'block',
          'operator' => '==',
          'value'    => 'nalakalu/card-history',
        ),
      ),
    ),
    'position' => 'normal',
    'style'    => 'default',
    'active'   => true,
  ));
});
