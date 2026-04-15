<?php
/**
 * Bloque: Toggle / Accordion (inmersivo)
 * Campos:
 * - title, button_text, url_button
 * - repeater: title_repeater, text_repeater, img_repeater
 * - color (texto/color) -> fondo del panel con alpha 0.85
 * - lado_imagen (radio: Derecho | Izquierdo)
 */

if ( ! function_exists('get_field') ) {
  echo '<p><em>ACF plugin required.</em></p>';
  return;
}

$section_id = 'toggle-immersive-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$class_name = 'toggle-immersive';
if (!empty($block['className'])) $class_name .= ' ' . esc_attr($block['className']);
if (!empty($block['align']))     $class_name .= ' align' . esc_attr($block['align']);

$title       = (string) get_field('title');
$button_text = (string) get_field('button_text');
$url_button  = (string) get_field('url_button');
$rows        = get_field('repeater'); // array

$raw_color = (string) get_field('color');
$alpha     = 0.85;
$bg_rgba   = '';

if ($raw_color !== '') {
  $c = strtolower(trim($raw_color));
  $c = preg_replace('/\s+/', '', $c);

  if (preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $c, $m)) {
    $hex = ltrim($m[0], '#');
    if (strlen($hex) === 3) {
      $r = hexdec(str_repeat($hex[0], 2));
      $g = hexdec(str_repeat($hex[1], 2));
      $b = hexdec(str_repeat($hex[2], 2));
    } else {
      $r = hexdec(substr($hex, 0, 2));
      $g = hexdec(substr($hex, 2, 2));
      $b = hexdec(substr($hex, 4, 2));
    }
    $bg_rgba = sprintf('rgba(%d,%d,%d,%.2f)', $r, $g, $b, $alpha);
  }
  elseif (preg_match('/^rgba?\((\d{1,3}),(\d{1,3}),(\d{1,3})(?:,([0-9.]+))?\)$/i', $c, $m)) {
    $r = max(0, min(255, (int)$m[1]));
    $g = max(0, min(255, (int)$m[2]));
    $b = max(0, min(255, (int)$m[3]));
    $bg_rgba = sprintf('rgba(%d,%d,%d,%.2f)', $r, $g, $b, $alpha);
  }
}

$panel_style = $bg_rgba ? '--content-bg:' . $bg_rgba . ';' : '';

$r = 90; $g = 81; $b = 69;
if ($panel_style && preg_match('/--content-bg:\s*rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,/i', $panel_style, $m)) {
  $r = (int)$m[1]; $g = (int)$m[2]; $b = (int)$m[3];
}

$brightness = (0.299 * $r) + (0.587 * $g) + (0.114 * $b);
$is_light   = ($brightness >= 170);

$fg_color = $is_light ? '#3D332B' : '#FFFFFF';
$hairline = $is_light ? 'rgba(61,51,43,0.20)' : 'rgba(255,255,255,0.20)';

/**
 * Flecha dinámica:
 * - Base del SVG: negro
 * - Si el panel es oscuro (texto blanco) -> invertimos a blanco
 * - Si el panel es claro  (texto oscuro) -> la dejamos negra
 */
$arrow_filter = $is_light ? 'brightness(0) invert(0)' : 'brightness(0) invert(1)';

$panel_style .= '--content-fg:' . $fg_color . ';--hairline:' . $hairline . ';--arrow-filter:' . $arrow_filter . ';';

$class_name .= $is_light ? ' theme-light' : ' theme-dark';

$arrow_url = 'https://nalakalu.stag.host/wp-content/uploads/2025/10/arrow_forward.svg';

$side_choice = (string) get_field('lado_imagen');
if ($side_choice === '') $side_choice = 'Derecho'; // default
$content_side_class = ($side_choice === 'Derecho') ? 'content-left' : 'content-right';
$class_name .= ' ' . $content_side_class;

$items     = is_array($rows) ? array_values($rows) : array();
$has_items = !empty($items);
?>
<section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($class_name); ?>">
  <div class="main-section">
    <div class="image-container">
      <?php if ($has_items): ?>
        <?php foreach ($items as $i => $row):
          $img    = (isset($row['img_repeater']) && is_array($row['img_repeater'])) ? $row['img_repeater'] : null;
          $url    = ($img && !empty($img['url'])) ? esc_url($img['url']) : '';
          $alt    = ($img && !empty($img['alt'])) ? esc_attr($img['alt']) : '';
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

    <div class="content-container" style="<?php echo esc_attr($panel_style); ?>">
      <div class="header-toggle">
        <?php if ($title): ?>
          <h2 class="font-overline text-blanco-hueso"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>

        <?php if ($button_text && $url_button): ?>
          <a href="<?php echo esc_url($url_button); ?>" class="desktop-only btn">
            <?php echo esc_html($button_text); ?> <img class="cta-arrow" src="<?php echo esc_url($arrow_url); ?>" alt="" aria-hidden="true" />
          </a>
        <?php endif; ?>
      </div>

      <div class="accordion-container">
        <?php if ($has_items): ?>
          <?php foreach ($items as $i => $row):
            $item_title = isset($row['title_repeater']) ? (string) $row['title_repeater'] : '';
            $item_text  = isset($row['text_repeater'])  ? (string) $row['text_repeater']  : '';
            $img        = (isset($row['img_repeater']) && is_array($row['img_repeater'])) ? $row['img_repeater'] : null;

            $thumb_url = '';
            if ($img) {
              if (!empty($img['sizes']['medium']))      $thumb_url = esc_url($img['sizes']['medium']);
              elseif (!empty($img['url']))              $thumb_url = esc_url($img['url']);
            }
            $thumb_alt = $img && !empty($img['alt']) ? esc_attr($img['alt']) : esc_attr($item_title);

            $num    = str_pad((string)($i+1), 2, '0', STR_PAD_LEFT);
            $img_id = $section_id . '-image-' . ($i+1);
          ?>
            <div class="accordion-item<?php echo $i === 0 ? ' active' : ''; ?>" data-image="<?php echo esc_attr($img_id); ?>">
              <div class="accordion-header">
                <div class="accordion-title-wrapper">
                  <span class="font-button"><?php echo esc_html($num); ?></span>
                  <h3 class="font-heading-2"><?php echo esc_html($item_title); ?></h3>
                </div>
                <?php if ($thumb_url): ?>
                  <img class="accordion-thumbnail" src="<?php echo $thumb_url; ?>" alt="<?php echo $thumb_alt; ?>" loading="lazy" decoding="async">
                <?php endif; ?>
              </div>

              <div class="accordion-content<?php echo $i === 0 ? ' active' : ''; ?>">
                <?php if ($item_text): ?>
                  <p class="font-body-small"><?php echo wp_kses_post( wpautop( $item_text ) ); ?></p>

                  <a href="<?php echo esc_url($url_button); ?>" class="mobile-only btn">
                    <?php echo esc_html($button_text); ?> <img class="cta-arrow" src="<?php echo esc_url($arrow_url); ?>" alt="" aria-hidden="true" />
                  </a>
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
(function () {
  var root = document.getElementById('<?php echo esc_js($section_id); ?>');
  if (!root) return;

  if (!window.CSS) window.CSS = {};
  if (!CSS.escape) {
    CSS.escape = function (s) {
      return String(s).replace(/[^a-zA-Z0-9_\-:.]/g, '\\$&');
    };
  }

  var items = Array.prototype.slice.call(root.querySelectorAll('.accordion-item'));
  var currentIndex = -1;

  if (!items.length) return;

  function setActiveByIndex(index) {
    index = Math.max(0, Math.min(items.length - 1, index));
    if (index === currentIndex) return;

    items.forEach(function (item, i) {
      var content = item.querySelector('.accordion-content');
      var imageId = item.getAttribute('data-image');
      var image = imageId ? root.querySelector('#' + CSS.escape(imageId)) : null;

      if (i === index) {
        item.classList.add('active');
        item.setAttribute('aria-expanded', 'true');
        if (content) content.classList.add('active');
        if (image) image.classList.add('active');
      } else {
        item.classList.remove('active');
        item.setAttribute('aria-expanded', 'false');
        if (content) content.classList.remove('active');
        if (image) image.classList.remove('active');
      }
    });

    currentIndex = index;
  }

  function bindClicks() {
    items.forEach(function (item, index) {
      var header = item.querySelector('.accordion-header') || item;

      header.setAttribute('role', 'button');
      header.setAttribute('tabindex', '0');
      item.setAttribute('aria-expanded', index === 0 ? 'true' : 'false');

      header.addEventListener('click', function () {
        setActiveByIndex(index);
      });

      header.addEventListener('keydown', function (e) {
        var key = e.key || e.code;
        if (key === 'Enter' || key === ' ' || key === 'Spacebar') {
          e.preventDefault();
          setActiveByIndex(index);
        }
      });
    });
  }

  root.classList.remove('ti-scroll-mode');
  root.style.minHeight = '';

  bindClicks();
  setActiveByIndex(0);
})();
</script>
</section>
