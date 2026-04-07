<?php
/**
 * ACF: Campos para bloque "Team"
 */
if ( ! function_exists('acf_add_local_field_group') ) { return; }

add_action('acf/include_fields', function () {
  if ( ! function_exists('acf_add_local_field_group') ) return;

  acf_add_local_field_group(array(
    'key' => 'group_nl_team',
    'title' => 'Team – Miembros',
    'fields' => array(
      array(
        'key' => 'field_nl_team_repeater',
        'label' => 'Team Members',
        'name' => 'team_member',
        'type' => 'repeater',
        'collapsed' => 'field_nl_team_titulo',
        'layout' => 'row',
        'button_label' => 'Agregar miembro',
        'sub_fields' => array(
          array(
            'key' => 'field_nl_team_titulo',
            'label' => 'Título de la sección',
            'name' => 'titulo',
            'type' => 'text',
          ),
          array(
            'key' => 'field_nl_team_name',
            'label' => 'Nombre del miembro',
            'name' => 'name_member',
            'type' => 'text',
          ),
          array(
            'key' => 'field_nl_team_rol',
            'label' => 'Rol del miembro',
            'name' => 'rol_member',
            'type' => 'text',
          ),
          array(
            'key' => 'field_nl_team_desc1',
            'label' => 'Descripción (intro, arriba de miniaturas)',
            'name' => 'member_description',
            'type' => 'textarea',
            'rows' => 3,
            'new_lines' => 'br'
          ),
          array(
            'key' => 'field_nl_team_desc2',
            'label' => 'Descripción secundaria (derecha del nombre)',
            'name' => 'secondary_description',
            'type' => 'textarea',
            'rows' => 3,
            'new_lines' => 'br'
          ),
          array(
            'key' => 'field_nl_team_img1',
            'label' => 'Imagen 1 (principal / avatar botón)',
            'name' => 'imagen_1',
            'type' => 'image',
            'return_format' => 'array',
            'preview_size' => 'medium',
            'library' => 'all'
          ),
          array(
            'key' => 'field_nl_team_img2',
            'label' => 'Imagen 2 (miniatura)',
            'name' => 'imagen_2',
            'type' => 'image',
            'return_format' => 'array',
            'preview_size' => 'medium',
            'library' => 'all'
          ),
          array(
            'key' => 'field_nl_team_img3',
            'label' => 'Imagen 3 (miniatura)',
            'name' => 'imagen_3',
            'type' => 'image',
            'return_format' => 'array',
            'preview_size' => 'medium',
            'library' => 'all'
          ),
          array(
            'key' => 'field_nl_team_btnurl',
            'label' => 'URL del botón',
            'name' => 'button_url',
            'type' => 'url',
          ),
        ),
      ),
    ),
    'location' => array(
      array(
        array(
          'param'    => 'block',
          'operator' => '==',
          'value'    => 'nalakalu/team',
        ),
      ),
    ),
    'position' => 'normal',
    'style'    => 'default',
    'active'   => true,
  ));
});
