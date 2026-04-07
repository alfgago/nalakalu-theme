<?php
/**
 * ACF fields – Hotspots (LookBook wrapper)
 * Campos:
 * - lookbook_id (number) -> ID del LookBook a mostrar
 */
if ( ! function_exists('acf_add_local_field_group') ) { return; }

add_action('acf/include_fields', function () {
  if ( ! function_exists('acf_add_local_field_group') ) return;

  acf_add_local_field_group(array(
    'key' => 'group_hotspots',
    'title' => 'Hotspots (LookBook)',
    'fields' => array(
      array(
        'key' => 'field_lb_id',
        'label' => 'LookBook ID',
        'name' => 'lookbook_id',
        'type' => 'number',
        'required' => 1,
        'min' => 1,
        'instructions' => 'Colocá el ID del LookBook (lo ves en el listado o en el shortcode del plugin).'
      )
    ),
    'location' => array(
      array(
        array(
          'param'    => 'block',
          'operator' => '==',
          'value'    => 'nalakalu/hotspots',
        ),
      ),
    ),
    'position' => 'normal',
    'style'    => 'default',
    'active'   => true,
  ));
});
