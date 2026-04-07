<?php
/**
 * Block: Marquesina de texto (text-marquee)
 */

if ( ! function_exists('get_field') ) {
  echo '<p><em>ACF plugin required.</em></p>';
  return;
}

$section_id = 'text-marquee-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$classes    = 'tm-marquee';
if (!empty($block['className'])) $classes .= ' ' . esc_attr($block['className']);
if (!empty($block['align']))     $classes .= ' align' . esc_attr($block['align']);

$t1   = trim((string) get_field('text1'));
$t2   = trim((string) get_field('text2'));
$t3   = trim((string) get_field('text3'));
$col  = trim((string) get_field('text_color'));

// --- Helper con guard para evitar redeclaración ---
if ( ! function_exists('nalakalu_tm_color_sanitize') ) {
  function nalakalu_tm_color_sanitize($c, $fallback = '#d4c5b9'){
    $c = trim((string)$c);
    if ($c === '') return $fallback;
    $ok =
      preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $c) ||
      preg_match('/^rgba?\(\s*[\d.\s%,]+\)$/i', $c) ||
      preg_match('/^hsla?\(\s*[\d.\s%,]+\)$/i', $c) ||
      preg_match('/^var\(--[a-z0-9_-]+\)$/i', $c) ||
      preg_match('/^currentColor$/i', $c);
    return $ok ? $c : $fallback;
  }
}

$color   = nalakalu_tm_color_sanitize($col);
$phrases = array_values(array_filter([$t1, $t2, $t3], function($s){ return $s !== ''; }));

// Si no hay frases, no renderizamos (solo aviso en editor).
if (empty($phrases)) {
  if ( current_user_can('edit_posts') ) {
    echo '<section class="'.esc_attr($classes).'" style="--tm-color:'.esc_attr($color).'">
            <div style="opacity:.7;padding:1rem 0;">Cargá al menos una frase (Texto 1/2/3) para mostrar la marquesina.</div>
          </section>';
  }
  return;
}

// Construimos un único track con contenido duplicado (primera mitad == segunda mitad) para loop sin salto.
$unit_repeat = 4; // repetimos el set de frases varias veces para asegurar ancho
ob_start();
for ($r=0; $r<$unit_repeat; $r++) {
  foreach ($phrases as $p) {
    echo '<span class="font-heading-1 custom">'.esc_html($p).'</span>';
  }
}
$chunk = ob_get_clean();
?>
<section
  id="<?php echo esc_attr($section_id); ?>"
  class="<?php echo esc_attr($classes); ?>"
  style="--tm-color: <?php echo esc_attr($color); ?>;"
>
  <div class="tm-rail" aria-hidden="true">
    <div class="tm-track">
      <?php
        // Duplicamos el chunk para que el 0%→-50% sea seamless
        echo $chunk; echo $chunk;
      ?>
    </div>
  </div>
</section>
