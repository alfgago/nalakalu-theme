<?php
/**
 * ACF fields para el bloque "Pillars"
 */
if ( ! function_exists('acf_add_local_field_group') ) { return; }

add_action('acf/include_fields', function () {
  if ( ! function_exists('acf_add_local_field_group') ) return;

  acf_add_local_field_group(array(
    'key' => 'group_pillars_block',
    'title' => 'Pillars – Contenido',
    'fields' => array(
      array(
        'key' => 'field_pillars_pretitle',
        'label' => 'Pretitle',
        'name' => 'pretitle',
        'type' => 'text',
        'placeholder' => 'PILARES'
      ),
      array(
        'key' => 'field_pillars_title',
        'label' => 'Title',
        'name' => 'title',
        'type' => 'text',
        'placeholder' => 'LO QUE NOS DEFINE'
      ),
      array(
        'key' => 'field_pillars_button_text',
        'label' => 'Button Text',
        'name' => 'button_text',
        'type' => 'text',
        'placeholder' => 'Conócenos →'
      ),
      array(
        'key' => 'field_pillars_button_url',
        'label' => 'Button URL',
        'name' => 'button_url',
        'type' => 'url',
        'placeholder' => 'https://...'
      ),
      array(
        'key' => 'field_pillars_description',
        'label' => 'Description',
        'name' => 'description',
        'type' => 'textarea',
        'rows' => 3
      ),
      array(
        'key' => 'field_pillars_cards_group',
        'label' => 'Cards',
        'name' => 'cards',
        'type' => 'group',
        'layout' => 'row',
        'sub_fields' => array(
          // Card 1
          array(
            'key' => 'field_card1_image',
            'label' => 'Card 1 Image',
            'name' => 'card1_image',
            'type' => 'image',
            'return_format' => 'array',
            'preview_size' => 'medium_large'
          ),
          array(
            'key' => 'field_card1_title',
            'label' => 'Card 1 Title',
            'name' => 'card1_title',
            'type' => 'text'
          ),
          array(
            'key' => 'field_card1_url',
            'label' => 'Card 1 URL',
            'name' => 'url_card_1',
            'type' => 'url'
          ),
          // Card 2
          array(
            'key' => 'field_card2_image',
            'label' => 'Card 2 Image',
            'name' => 'card2_image',
            'type' => 'image',
            'return_format' => 'array',
            'preview_size' => 'medium_large'
          ),
          array(
            'key' => 'field_card2_title',
            'label' => 'Card 2 Title',
            'name' => 'card2_title',
            'type' => 'text'
          ),
          array(
            'key' => 'field_card2_url',
            'label' => 'Card 2 URL',
            'name' => 'url_card_2',
            'type' => 'url'
          ),
          // Card 3
          array(
            'key' => 'field_card3_image',
            'label' => 'Card 3 Image',
            'name' => 'card3_image',
            'type' => 'image',
            'return_format' => 'array',
            'preview_size' => 'medium_large'
          ),
          array(
            'key' => 'field_card3_title',
            'label' => 'Card 3 Title',
            'name' => 'card3_title',
            'type' => 'text'
          ),
          array(
            'key' => 'field_card3_url',
            'label' => 'Card 3 URL',
            'name' => 'url_card_3',
            'type' => 'url'
          ),
        )
      ),
    ),
    'location' => array(
      array(
        array(
          'param'    => 'block',
          'operator' => '==',
          'value'    => 'nalakalu/pillars',
        ),
      ),
    ),
    'position' => 'normal',
    'style'    => 'default',
    'active'   => true,
  ));
});
