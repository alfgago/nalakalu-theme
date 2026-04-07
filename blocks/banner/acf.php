<?php
/**
 * ACF fields para el bloque "Banner with Text"
 * Campos:
 * - background (image)
 * - pretitle (text)
 * - title (text)
 * - content (textarea)
 */
if ( ! function_exists('acf_add_local_field_group') ) { return; }

add_action('acf/include_fields', function () {
  if ( ! function_exists('acf_add_local_field_group') ) return;

  acf_add_local_field_group(array(
    'key'    => 'group_nl_banner',
    'title'  => 'Banner – Campos',
    'fields' => array(
      array(
        'key'           => 'field_nl_banner_bg',
        'label'         => 'Background',
        'name'          => 'background',
        'type'          => 'image',
        'return_format' => 'array',
        'preview_size'  => 'medium',
        'library'       => 'all'
      ),
      array(
        'key'   => 'field_nl_banner_pretitle',
        'label' => 'Pretitle',
        'name'  => 'pretitle',
        'type'  => 'text',
        'default_value' => ''
      ),
      array(
        'key'   => 'field_nl_banner_title',
        'label' => 'Title',
        'name'  => 'title',
        'type'  => 'text',
        'default_value' => ''
      ),
      array(
        'key'   => 'field_nl_banner_content',
        'label' => 'Content',
        'name'  => 'content',
        'type'  => 'textarea',
        'rows'  => 4,
        'new_lines' => 'br'  // respeta saltos de línea
      ),
    ),
    'location' => array(
      array(
        array(
          'param'    => 'block',
          'operator' => '==',
          'value'    => 'nalakalu/banner',
        ),
      ),
    ),
    'position' => 'normal',
    'style'    => 'default',
    'active'   => true,
  ));
});
