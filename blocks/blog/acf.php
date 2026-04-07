<?php
/**
 * ACF fields – Blog block
 * Campos:
 * - background (image)
 * - post_q (select: 5..10 / Todos)
 * - category (taxonomy: category)
 */
if ( ! function_exists('acf_add_local_field_group') ) { return; }

add_action('acf/include_fields', function () {
  if ( ! function_exists('acf_add_local_field_group') ) return;

  acf_add_local_field_group(array(
    'key' => 'group_nl_blog_block',
    'title' => 'Blog – Configuración',
    'fields' => array(
      array(
        'key' => 'field_blog_background',
        'label' => 'Imagen de fondo',
        'name' => 'background',
        'type' => 'image',
        'return_format' => 'array',
        'preview_size'  => 'medium',
        'library'       => 'all'
      ),
      array(
        'key' => 'field_blog_post_q',
        'label' => 'Cantidad de posts',
        'name' => 'post_q',
        'type' => 'select',
        'choices' => array(
          '5' => '5',
          '6' => '6',
          '7' => '7',
          '8' => '8',
          '9' => '9',
          '10' => '10',
          'Todos' => 'Todos'
        ),
        'default_value' => '6',
        'ui' => 1,
        'allow_null' => 0,
        'multiple' => 0,
        'return_format' => 'value'
      ),
      array(
        'key' => 'field_blog_category',
        'label' => 'Categoría',
        'name' => 'category',
        'type' => 'taxonomy',
        'taxonomy' => 'category',
        'field_type' => 'select',
        'return_format' => 'id',
        'add_term' => 0,
        'load_terms' => 0,
        'save_terms' => 0,
        'multiple' => 0,
        'ui' => 1,
        'allow_null' => 1,
      ),
    ),
    'location' => array(
      array(
        array(
          'param'    => 'block',
          'operator' => '==',
          'value'    => 'nalakalu/blog',
        ),
      ),
    ),
    'position' => 'normal',
    'style'    => 'default',
    'active'   => true,
  ));
});
