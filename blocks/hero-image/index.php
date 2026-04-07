<?php
/**
 * Block: Hero Image
 * Campos:
 * - background_image (image url/id/array)
 * - text (textarea)
 * - pretitle (text)
 */

if ( ! function_exists('get_field') ) {
  echo '<p><em>ACF plugin required.</em></p>';
  return;
}

$section_id = 'hero-image-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$classes    = 'hero-image';
if (!empty($block['className'])) $classes .= ' ' . esc_attr($block['className']);
if (!empty($block['align']))     $classes .= ' align' . esc_attr($block['align']);

/** Normalizador seguro a URL */
if (!function_exists('nlk_get_image_url')) {
  function nlk_get_image_url($img, $size = 'full'){
    if (is_array($img)) {
      if (!empty($img['url'])) return $img['url'];
      if (!empty($img['ID']))  return wp_get_attachment_image_url((int)$img['ID'], $size) ?: '';
    }
    if (is_numeric($img)) {
      return wp_get_attachment_image_url((int)$img, $size) ?: '';
    }
    return trim((string)$img);
  }
}

$bg_raw   = get_field('background_image');           // puede ser url/id/array
$bg_url   = nlk_get_image_url($bg_raw);
$text     = (string) get_field('text');
$pretitle = (string) get_field('pretitle');

/** Inline style: var() + fallback directo */
$style_parts = [];
if ($bg_url) {
  $style_parts[] = "--hero-bg: url('" . esc_url($bg_url) . "')";
  $style_parts[] = "background-image: url('" . esc_url($bg_url) . "')";
}
$style = implode(';', $style_parts);
?>
<section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($classes); ?>" style="<?php echo esc_attr($style); ?>">
  <div class="hero-image__inner">
    <div class="text-content">
      <?php if ($pretitle): ?>
        <span class="hero-image__pretitle font-overline text-white">
          <?php echo esc_html($pretitle); ?>
        </span>
      <?php endif; ?>

      <?php if ($text): ?>
        <span class="font-heading-2 medium reveal-clip text-white">
          <?php echo wp_kses_post( nl2br($text) ); ?>
        </span>
      <?php elseif ( current_user_can('edit_posts') ): ?>
        <span class="font-heading-2 text-white" style="opacity:.7">
          Agregá el “Text” del hero en los campos del bloque.
        </span>
      <?php endif; ?>
    </div>
  </div>
  
  <script>
  (function(){
    var root = document.getElementById('<?php echo esc_js($section_id); ?>');
    if(!root) return;
    var target = root.querySelector('.reveal-clip');
    if(!target) return;

    function play(){
      root.classList.add('is-in'); // activa la animación
      if(io) io.disconnect();
    }

    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function(entries){
        entries.forEach(function(e){ if(e.isIntersecting) play(); });
      }, { threshold: 0.15 });
      io.observe(root);
    } else {
      play();
    }
  })();
  </script>
</section>
