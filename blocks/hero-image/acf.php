<?php
// Campos ACF para el bloque "Hero Image"
if ( ! function_exists('acf_add_local_field_group') ) { return; }

add_action('acf/include_fields', function () {
  if ( ! function_exists('acf_add_local_field_group') ) return;

  acf_add_local_field_group(array(
    'key'    => 'group_hero_image',
    'title'  => 'Hero Image – Campos',
    'fields' => array(
      array(
        'key'           => 'field_hero_bg_image',
        'label'         => 'Background image',
        'name'          => 'background_image',
        'type'          => 'image',
        'return_format' => 'url',   // devolvemos URL directa para simplificar
        'preview_size'  => 'medium',
        'library'       => 'all',
        'mime_types'    => 'jpg,jpeg,png,webp,svg'
      ),
      array(
        'key'           => 'field_hero_text',
        'label'         => 'Text',
        'name'          => 'text',
        'type'          => 'textarea',
        'rows'          => 4,
        'new_lines'     => 'br',    // respeta saltos con <br>
        'placeholder'   => 'Escribí el texto del hero…'
      ),
    ),
    'location' => array(
      array(
        array(
          'param'    => 'block',
          'operator' => '==',
          'value'    => 'nalakalu/hero-image',
        ),
      ),
    ),
    'position' => 'normal',
    'style'    => 'default',
    'active'   => true,
  ));
});
