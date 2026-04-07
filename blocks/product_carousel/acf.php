<?php
/**
 * ACF fields para el bloque "Product Carousel (Tabs)"
 * - product_selector_1 (taxonomy product_cat)
 * - product_selector_2 (taxonomy product_cat)
 * - product_selector_3 (taxonomy product_cat)
 */
if ( ! function_exists('acf_add_local_field_group') ) { return; }

add_action('acf/include_fields', function () {
  if ( ! function_exists('acf_add_local_field_group') ) return;

  acf_add_local_field_group(array(
    'key' => 'group_product_carousel_tabs',
    'title' => 'Product Carousel – Categorías',
    'fields' => array(
      array(
        'key' => 'field_pc_selector_1',
        'label' => 'Category (Tab 1)',
        'name' => 'product_selector_1',
        'type' => 'taxonomy',
        'taxonomy' => 'product_cat',
        'field_type' => 'select',
        'return_format' => 'id',
        'add_term' => 0,
        'load_terms' => 0,
        'save_terms' => 0,
        'multiple' => 0,
        'ui' => 1,
      ),
      array(
        'key' => 'field_pc_selector_2',
        'label' => 'Category (Tab 2)',
        'name' => 'product_selector_2',
        'type' => 'taxonomy',
        'taxonomy' => 'product_cat',
        'field_type' => 'select',
        'return_format' => 'id',
        'add_term' => 0,
        'load_terms' => 0,
        'save_terms' => 0,
        'multiple' => 0,
        'ui' => 1,
      ),
      array(
        'key' => 'field_pc_selector_3',
        'label' => 'Category (Tab 3)',
        'name' => 'product_selector_3',
        'type' => 'taxonomy',
        'taxonomy' => 'product_cat',
        'field_type' => 'select',
        'return_format' => 'id',
        'add_term' => 0,
        'load_terms' => 0,
        'save_terms' => 0,
        'multiple' => 0,
        'ui' => 1,
      ),
    ),
    'location' => array(
      array(
        array(
          'param'    => 'block',
          'operator' => '==',
          'value'    => 'nalakalu/product-carousel',
        ),
      ),
    ),
    'position' => 'normal',
    'style'    => 'default',
    'active'   => true,
  ));
});
