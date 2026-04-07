<?php
/**
 * ACF fields – Marquesina de texto (text-marquee)
 * Campos:
 * - text1 (textarea)
 * - text2 (textarea)
 * - text3 (textarea)
 * - text_color (text) -> acepta #hex, rgb(), hsl(), var(--color)
 */
if ( ! function_exists('acf_add_local_field_group') ) { return; }

add_action('acf/include_fields', function () {
  if ( ! function_exists('acf_add_local_field_group') ) return;

  acf_add_local_field_group(array(
    'key' => 'group_text_marquee',
    'title' => 'Marquesina de texto',
    'fields' => array(
      array(
        'key' => 'field_tm_text1',
        'label' => 'Texto 1',
        'name' => 'text1',
        'type' => 'textarea',
        'rows' => 2,
        'new_lines' => 'br',
        'placeholder' => 'Maestros Desde 1960'
      ),
      array(
        'key' => 'field_tm_text2',
        'label' => 'Texto 2',
        'name' => 'text2',
        'type' => 'textarea',
        'rows' => 2,
        'new_lines' => 'br',
        'placeholder' => 'Hecho en Costa Rica'
      ),
      array(
        'key' => 'field_tm_text3',
        'label' => 'Texto 3',
        'name' => 'text3',
        'type' => 'textarea',
        'rows' => 2,
        'new_lines' => 'br',
        'placeholder' => 'Diseño con propósito'
      ),
      array(
        'key' => 'field_tm_text_color',
        'label' => 'Color de texto y bordes',
        'name' => 'text_color',
        'type' => 'text',
        'placeholder' => '#d4c5b9 (o rgb(), hsl(), var(--color))',
        'instructions' => 'Acepta #hex, rgb(a), hsl(a) o var(--token).'
      ),
    ),
    'location' => array(
      array(
        array(
          'param'    => 'block',
          'operator' => '==',
          'value'    => 'nalakalu/text-marquee',
        ),
      ),
    ),
    'position' => 'normal',
    'style'    => 'default',
    'active'   => true,
  ));
});
