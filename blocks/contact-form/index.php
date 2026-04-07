<?php
/**
 * Block: Contact Form
 * Campos ACF:
 * - title        (text)
 * - description  (textarea)
 * - image        (image)
 * - form_responses (email) -> destino de los mensajes
 */

if ( ! function_exists('get_field') ) {
  echo '<p><em>ACF plugin required.</em></p>';
  return;
}

$section_id = 'nlk-contact-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$classes    = 'nlk-contact-section';
if ( ! empty($block['className']) ) $classes .= ' ' . esc_attr($block['className']);
if ( ! empty($block['align']) )     $classes .= ' align' . esc_attr($block['align']);

$title       = (string) get_field('title');
$description = (string) get_field('description');
$image       = get_field('image');
$form_to     = (string) get_field('form_responses');

// Helper de imagen (evitamos redeclare)
if ( ! function_exists('nlk_cf_get_image_url') ) {
  function nlk_cf_get_image_url( $img, $size = 'large' ) {
    if ( is_array($img) ) {
      if ( ! empty($img['sizes'][$size]) ) return esc_url($img['sizes'][$size]);
      if ( ! empty($img['url']) )          return esc_url($img['url']);
    } elseif ( is_numeric($img) ) {
      $src = wp_get_attachment_image_src( (int) $img, $size );
      if ( $src && ! empty($src[0]) ) return esc_url($src[0]);
    } elseif ( is_string($img) && filter_var($img, FILTER_VALIDATE_URL) ) {
      return esc_url($img);
    }
    return '';
  }
}

$bg_url = nlk_cf_get_image_url($image, 'large') ?: nlk_cf_get_image_url($image, 'full');

// Mensaje de éxito por query arg
$show_success = isset($_GET['nlk_cf_success']) && $_GET['nlk_cf_success'] === '1';
?>
<section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($classes); ?>">
  <div class="nlk-contact-grid">
    <div class="nlk-contact-image-column"
      <?php if ($bg_url): ?>
        style="background-image:url('<?php echo esc_url($bg_url); ?>');"
      <?php endif; ?>
    ></div>

    <div class="nlk-contact-form-column">
      <?php if ($title): ?>
        <h1 class="font-heading-1"><?php echo esc_html($title); ?></h1>
      <?php endif; ?>

      <?php if ($description): ?>
        <p class="font-body-small">
          <?php echo wp_kses_post( nl2br($description) ); ?>
        </p>
      <?php endif; ?>

      <form class="nlk-contact-form"
            method="post"
            action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">

        <?php
        // NONCE: acción 'nlk_contact_form' y campo 'nlk_contact_form_nonce'
        wp_nonce_field( 'nlk_contact_form', 'nlk_contact_form_nonce' );
        ?>
        <input type="hidden" name="action" value="nlk_contact_form">

        <?php if ( $form_to ): ?>
          <input type="hidden" name="nlk_cf_to" value="<?php echo esc_attr($form_to); ?>">
        <?php endif; ?>

        <div class="nlk-contact-form-row">
          <div class="nlk-contact-form-group">
            <label for="<?php echo esc_attr($section_id); ?>-nombre">Nombre</label>
            <input type="text"
                   id="<?php echo esc_attr($section_id); ?>-nombre"
                   name="nlk_nombre"
                   placeholder="Jane"
                   required>
          </div>
          <div class="nlk-contact-form-group">
            <label for="<?php echo esc_attr($section_id); ?>-apellido">Apellido</label>
            <input type="text"
                   id="<?php echo esc_attr($section_id); ?>-apellido"
                   placeholder="Doe"
                   name="nlk_apellido">
          </div>
        </div>

        <div class="nlk-contact-form-row">
          <div class="nlk-contact-form-group">
            <label for="<?php echo esc_attr($section_id); ?>-email">Email</label>
            <input type="email"
                   id="<?php echo esc_attr($section_id); ?>-email"
                   name="nlk_email"
                    placeholder="janedoe@gmail.com"
                   required>
          </div>
          <div class="nlk-contact-form-group">
            <label for="<?php echo esc_attr($section_id); ?>-telefono">Número de teléfono</label>
            <input type="tel"
                   id="<?php echo esc_attr($section_id); ?>-telefono"
                    placeholder="+51283748294"
                   name="nlk_telefono">
          </div>
        </div>

        <div class="nlk-contact-form-group">
          <label>Asunto</label>
          <div class="nlk-contact-radio-group">
            <div class="nlk-contact-radio-option">
              <input type="radio"
                     id="<?php echo esc_attr($section_id); ?>-consulta"
                     name="nlk_asunto"
                     value="Consulta general"
                     checked>
              <label for="<?php echo esc_attr($section_id); ?>-consulta">Consulta general</label>
            </div>
            <div class="nlk-contact-radio-option">
              <input type="radio"
                     id="<?php echo esc_attr($section_id); ?>-compra"
                     name="nlk_asunto"
                     value="Compra de producto">
              <label for="<?php echo esc_attr($section_id); ?>-compra">Compra de producto</label>
            </div>
            <div class="nlk-contact-radio-option">
              <input type="radio"
                     id="<?php echo esc_attr($section_id); ?>-problema"
                     name="nlk_asunto"
                     value="Problema con mi producto">
              <label for="<?php echo esc_attr($section_id); ?>-problema">Problema con mi producto</label>
            </div>
          </div>
        </div>

        <div class="nlk-contact-form-group">
          <label for="<?php echo esc_attr($section_id); ?>-mensaje">Mensaje</label>
          <textarea id="<?php echo esc_attr($section_id); ?>-mensaje"
                    name="nlk_mensaje"
                    placeholder="Escribe tu mensaje"
                    required></textarea>
        </div>

        <div class="nlk-contact-button-container">
          <button type="submit" class="btn btn-cafe">
            Enviar mensaje
          </button>
        </div>
      </form>

      <?php if ( $show_success ): ?>
        <p class="nlk-contact-success">Su mensaje se envió con éxito.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
