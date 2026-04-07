<?php
// blocks/text-video/acf.php

if ( ! function_exists('acf_add_local_field_group') ) { return; }

add_action('acf/include_fields', function () {
  if ( ! function_exists('acf_add_local_field_group') ) return;

  acf_add_local_field_group(array(
    'key'    => 'group_text_video_hero',
    'title'  => 'Text + Video (Hero)',
    'fields' => array(
      array(
        'key'     => 'field_tv_headline',
        'label'   => 'Headline',
        'name'    => 'headline',
        'type'    => 'text',
        'wrapper' => array('width' => '50'),
      ),
      array(
        'key'       => 'field_tv_description',
        'label'     => 'Description',
        'name'      => 'description',
        'type'      => 'textarea',
        'rows'      => 3,
        'new_lines' => 'br',
        'wrapper'   => array('width' => '50'),
      ),
      array(
        'key'         => 'field_tv_video',
        'label'       => 'Video (URL principal)',
        'name'        => 'video',
        'type'        => 'url',
        'instructions'=> 'Usá MP4/WEBM directo para que funcionen los controles custom.',
        'required'    => 1,
      ),
      array(
        'key'         => 'field_tv_bg_video',
        'label'       => 'Background video (panel derecho)',
        'name'        => 'background_video_source',
        'type'        => 'url',
        'instructions'=> 'MP4/WEBM recomendado. Se reproduce en loop y muteado detrás del texto.',
      ),
    ),
    'location' => array(
      array(
        array(
          'param'    => 'block',
          'operator' => '==',
          'value'    => 'nalakalu/text-video', // 👈 mismo namespace que usás en ACF
        ),
      ),
    ),
    'position' => 'normal',
    'style'    => 'default',
    'active'   => true,
  ));
});

/**
 * Encola el CSS del bloque (front + editor).
 * Dejalo acá o movelo a functions.php, como prefieras.
 */
add_action('enqueue_block_assets', function () {
  $path = get_stylesheet_directory() . '/blocks/text-video/style.css';
  $uri  = get_stylesheet_directory_uri() . '/blocks/text-video/style.css';
  if ( file_exists($path) ) {
    wp_enqueue_style('text-video-block', $uri, array(), filemtime($path));
  }
});
