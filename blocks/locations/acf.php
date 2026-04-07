<?php
/**
 * ACF fields para el bloque "Locations"
 * Campos:
 * - pretitle (text)
 * - headline (text)
 * - description (textarea)
 * - ubications (repeater)
 *    - title_ubications (text)
 *    - image_ubications (image, return array)
 *    - url_ubications (url)
 *    - location_description (textarea)
 */
if ( ! function_exists('acf_add_local_field_group') ) { return; }

add_action('acf/include_fields', function () {
  if ( ! function_exists('acf_add_local_field_group') ) return;

  acf_add_local_field_group(array(
    'key'    => 'group_locations_block',
    'title'  => 'Locations – Campos',
    'fields' => array(
      array(
        'key'   => 'field_locations_pretitle',
        'label' => 'Pretitle',
        'name'  => 'pretitle',
        'type'  => 'text',
        'placeholder' => 'UBICACIONES'
      ),
      array(
        'key'   => 'field_locations_headline',
        'label' => 'Headline',
        'name'  => 'headline',
        'type'  => 'text',
        'placeholder' => 'VENÍ A CONOCERNOS'
      ),
      array(
        'key'   => 'field_locations_description',
        'label' => 'Description',
        'name'  => 'description',
        'type'  => 'textarea',
        'rows'  => 3,
        'new_lines' => 'wpautop',
        'placeholder' => 'Texto descriptivo del bloque…'
      ),
      array(
        'key'        => 'field_locations_repeater',
        'label'      => 'Ubications',
        'name'       => 'ubications',
        'type'       => 'repeater',
        'layout'     => 'row',
        'min'        => 1,
        'button_label' => 'Agregar ubicación',
        'sub_fields' => array(
          array(
            'key'   => 'field_locations_item_title',
            'label' => 'Title (tag)',
            'name'  => 'title_ubications',
            'type'  => 'text',
          ),
          array(
            'key'           => 'field_locations_item_image',
            'label'         => 'Image',
            'name'          => 'image_ubications',
            'type'          => 'image',
            'return_format' => 'array',
            'preview_size'  => 'medium',
            'library'       => 'all'
          ),
          array(
            'key'   => 'field_locations_item_url',
            'label' => 'URL',
            'name'  => 'url_ubications',
            'type'  => 'url',
            'placeholder' => 'https://'
          ),
          array(
            'key'        => 'field_locations_item_desc',
            'label'      => 'Hover description',
            'name'       => 'location_description',
            'type'       => 'textarea',
            'rows'       => 3,
            'new_lines'  => 'wpautop',
            'placeholder'=> 'Descripción que aparece al pasar el mouse…'
          ),
        ),
      ),
    ),
    'location' => array(
      array(
        array(
          'param'    => 'block',
          'operator' => '==',
          'value'    => 'nalakalu/locations',
        ),
      ),
    ),
    'position' => 'normal',
    'style'    => 'default',
    'active'   => true,
  ));
});
