<?php
/**
 * Bloque: Toggle / Accordion (diseño inmersivo con fondo que cambia)
 * Campos ACF:
 * - title (texto superior)
 * - button_text (texto del botón)
 * - url_button (url del botón)
 * - repeater (items):
 *     - title_repeater (título del item)
 *     - text_repeater  (texto del item)
 *     - img_repeater   (imagen del item; se usa como thumbnail y como fondo grande)
 */

if ( ! function_exists('get_field') ) {
  echo '<p><em>ACF plugin required.</em></p>';
  return;
}

$section_id   = 'toggle-immersive-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$class_name   = 'toggle-immersive';
if (!empty($block['className'])) $class_name .= ' ' . esc_attr($block['className']);
if (!empty($block['align']))     $class_name .= ' align' . esc_attr($block['align']);

$title        = (string) get_field('title');
$button_text  = (string) get_field('button_text');
$url_button   = (string) get_field('url_button');
$rows         = get_field('repeater'); // array de items

// Normalizo filas
$items = is_array($rows) ? array_values($rows) : array();
$has_items = count($items) > 0;
?>
<section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($class_name); ?>">
  <div class="main-section">
    <!-- Fondo imágenes (una por item) -->
    <div class="image-container">
      <?php if ($has_items): ?>
        <?php foreach ($items as $i => $row):
          $img   = isset($row['img_repeater']) && is_array($row['img_repeater']) ? $row['img_repeater'] : null;
          $url   = $img && !empty($img['url']) ? esc_url($img['url']) : '';
          $alt   = $img && !empty($img['alt']) ? esc_attr($img['alt']) : '';
          $img_id = $section_id . '-image-' . ($i+1);
          if (!$url) continue;
        ?>
          <img
            src="<?php echo $url; ?>"
            alt="<?php echo $alt; ?>"
            class="background-image<?php echo $i === 0 ? ' active' : ''; ?>"
            id="<?php echo esc_attr($img_id); ?>"
            loading="lazy" decoding="async"
          >
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Contenido (capa derecha) -->
    <div class="content-container">
      <div class="header">
        <?php if ($title): ?>
          <h2 class="header-title"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>

        <?php if ($button_text && $url_button): ?>
          <a href="<?php echo esc_url($url_button); ?>" class="header-link">
            <?php echo esc_html($button_text); ?> →
          </a>
        <?php endif; ?>
      </div>

      <div class="accordion-container">
        <?php if ($has_items): ?>
          <?php foreach ($items as $i => $row):
            $item_title = isset($row['title_repeater']) ? (string) $row['title_repeater'] : '';
            $item_text  = isset($row['text_repeater'])  ? (string) $row['text_repeater']  : '';
            $img        = isset($row['img_repeater']) && is_array($row['img_repeater']) ? $row['img_repeater'] : null;

            // thumbnail preferido
            $thumb_url = '';
            if ($img) {
              // si ACF retorna sizes, intento usar tamaño más chico
              if (!empty($img['sizes']['medium'])) {
                $thumb_url = esc_url($img['sizes']['medium']);
              } elseif (!empty($img['url'])) {
                $thumb_url = esc_url($img['url']);
              }
            }
            $thumb_alt = $img && !empty($img['alt']) ? esc_attr($img['alt']) : esc_attr($item_title);

            $num = str_pad((string)($i+1), 2, '0', STR_PAD_LEFT);
            $img_id = $section_id . '-image-' . ($i+1);
          ?>
            <div class="accordion-item<?php echo $i === 0 ? ' active' : ''; ?>" data-image="<?php echo esc_attr($img_id); ?>">
              <div class="accordion-header">
                <div class="accordion-title-wrapper">
                  <span class="accordion-number"><?php echo esc_html($num); ?></span>
                  <h3 class="accordion-title"><?php echo esc_html($item_title); ?></h3>
                </div>
                <?php if ($thumb_url): ?>
                  <img class="accordion-thumbnail" src="<?php echo $thumb_url; ?>" alt="<?php echo $thumb_alt; ?>" loading="lazy" decoding="async">
                <?php endif; ?>
              </div>

              <div class="accordion-content<?php echo $i === 0 ? ' active' : ''; ?>">
                <?php if ($item_text): ?>
                  <p class="accordion-text"><?php echo wp_kses_post( wpautop( $item_text ) ); ?></p>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <?php if ( current_user_can('edit_posts') ): ?>
            <p style="opacity:.7;">Agregá ítems en el repetidor del bloque para ver el acordeón.</p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
  (function(){
    var root = document.getElementById('<?php echo esc_js($section_id); ?>');
    if (!root) return;

    var items   = root.querySelectorAll('.accordion-item');
    var images  = root.querySelectorAll('.background-image');

    function setActive(item){
      // cerrar todos
      items.forEach(function(i){
        i.classList.remove('active');
        var c = i.querySelector('.accordion-content');
        if (c) c.classList.remove('active');
      });
      images.forEach(function(img){ img.classList.remove('active'); });

      // abrir el clickeado
      item.classList.add('active');
      var content = item.querySelector('.accordion-content');
      if (content) content.classList.add('active');

      var imageId = item.getAttribute('data-image');
      var img = root.querySelector('#' + CSS.escape(imageId));
      if (img) img.classList.add('active');
    }

    root.querySelectorAll('.accordion-item').forEach(function(item){
      item.addEventListener('click', function(){
        if (!this.classList.contains('active')) setActive(this);
      });
    });
  })();
  </script>
</section>
