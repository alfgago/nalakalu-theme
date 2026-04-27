<?php
/**
 * Hotspots (LookBook) – Carrusel + Testimonios + Galería lateral
 */

if (!defined('ABSPATH')) exit;
if (!function_exists('get_field')) {
  echo '<p><em>ACF plugin required.</em></p>';
  return;
}

$section_id = 'nl-hotspots-' . (isset($block['id']) ? $block['id'] : uniqid());
$classes    = 'nl-hotspots hs-skin';

if (!empty($block['className'])) $classes .= ' ' . esc_attr($block['className']);
if (!empty($block['align']))     $classes .= ' align' . esc_attr($block['align']);

$sel = get_field('lookbook_selector');
$ids = is_array($sel) ? $sel : (empty($sel) ? [] : [$sel]);

$lookbook_ids = [];
foreach ($ids as $it) {
  if (is_numeric($it)) $lookbook_ids[] = (int)$it;
  elseif (is_object($it) && isset($it->ID)) $lookbook_ids[] = (int)$it->ID;
  elseif (is_array($it) && isset($it['ID'])) $lookbook_ids[] = (int)$it['ID'];
}
$lookbook_ids = array_values(array_unique(array_filter($lookbook_ids)));

if (!$lookbook_ids) {
  if (current_user_can('edit_posts')) {
    echo '<div style="opacity:.7;padding:.75rem 0;"><em>Elegí uno o más LookBooks en “lookbook_selector”.</em></div>';
  }
  return;
}

/* Woo helpers */
if (!function_exists('nlhs_product_bits')) {
  function nlhs_product_bits($pid){
    $out = [
      'name'       => '',
      'price_html' => '',
      'permalink'  => '',
      'img_url'    => '',
    ];

    if (!function_exists('wc_get_product')) return $out;

    $p = wc_get_product($pid);
    if (!$p) return $out;

    $out['name']       = wp_strip_all_tags($p->get_name());
    $out['price_html'] = $p->get_price_html();
    $out['permalink']  = get_permalink($pid);

    $img_id = $p->get_image_id();
    if ($img_id) {
      $out['img_url'] = wp_get_attachment_image_url($img_id, 'medium');
    }

    return $out;
  }
}

if (!function_exists('nlhs_gallery_bits')) {
  function nlhs_gallery_bits($gallery_field){
    $out = [];

    if (empty($gallery_field) || !is_array($gallery_field)) {
      return $out;
    }

    foreach ($gallery_field as $img) {
      $id  = 0;
      $url = '';
      $alt = '';

      if (is_numeric($img)) {
        $id  = (int) $img;
        $url = wp_get_attachment_image_url($id, 'large');
        $alt = get_post_meta($id, '_wp_attachment_image_alt', true);

      } elseif (is_array($img)) {
        if (!empty($img['ID'])) {
          $id = (int) $img['ID'];
        }

        if (!empty($img['sizes']['large'])) {
          $url = $img['sizes']['large'];
        } elseif (!empty($img['url'])) {
          $url = $img['url'];
        }

        if (!empty($img['alt'])) {
          $alt = $img['alt'];
        } elseif ($id) {
          $alt = get_post_meta($id, '_wp_attachment_image_alt', true);
        }

      } elseif (is_object($img) && !empty($img->ID)) {
        $id  = (int) $img->ID;
        $url = wp_get_attachment_image_url($id, 'large');
        $alt = get_post_meta($id, '_wp_attachment_image_alt', true);
      }

      if ($url) {
        $out[] = [
          'id'  => $id,
          'url' => $url,
          'alt' => $alt,
        ];
      }
    }

    return $out;
  }
}

if (!function_exists('nlhs_extract_main_img_src')) {
  function nlhs_extract_main_img_src($html){
    if (preg_match('/<img[^>]*class="[^"]*wlb-image[^"]*"[^>]*\s(?:src|data-src|data-lazy|data-original)\s*=\s*"([^"]+)"/i', $html, $m)) {
      return $m[1];
    }

    if (preg_match('/<img[^>]*class="[^"]*wlb-image[^"]*"[^>]*\ssrcset\s*=\s*"([^"]+)"/i', $html, $m)) {
      $parts = preg_split('/\s*,\s*/', $m[1]);
      if ($parts) {
        $last = end($parts);
        if (preg_match('/^\s*(\S+)/', $last, $n)) return $n[1];
      }
    }

    if (preg_match('/<img[^>]*(?:src|data-src|data-lazy|data-original)\s*=\s*"([^"]+)"/i', $html, $m)) {
      return $m[1];
    }

    return '';
  }
}

if (!function_exists('nlhs_resolve_related_id')) {
  function nlhs_resolve_related_id($rel){
    if (empty($rel)) return 0;

    if (is_numeric($rel)) {
      return (int) $rel;
    }

    if (is_object($rel) && isset($rel->ID)) {
      return (int) $rel->ID;
    }

    if (is_array($rel)) {
      if (isset($rel['ID']) && is_numeric($rel['ID'])) {
        return (int) $rel['ID'];
      }

      foreach ($rel as $item) {
        $id = nlhs_resolve_related_id($item);
        if ($id) return $id;
      }
    }

    return 0;
  }
}

if (!function_exists('nlhs_has_info_row')) {
  function nlhs_has_info_row($row){
    if (!is_array($row)) return false;
    if (!empty($row['nombre'])) return true;
    if (!empty($row['rol'])) return true;
    if (!empty($row['contenido'])) return true;
    if (!empty($row['galeria']) && is_array($row['galeria'])) return true;
    return false;
  }
}

/* Slides y thumbs */
$slides = [];
$thumbs = [];

foreach ($lookbook_ids as $lb_id) {

  ob_start();
  echo do_shortcode('[woocommerce_lookbook id="' . intval($lb_id) . '"]');
  $lookbook_html = ob_get_clean();

  $thumb_url = nlhs_extract_main_img_src($lookbook_html);
  if (!$thumb_url) {
    $thumb_url = get_the_post_thumbnail_url($lb_id, 'large') ?: '';
  }

  $thumbs[] = [
    'id'  => $lb_id,
    'src' => $thumb_url ? esc_url($thumb_url) : '',
    'alt' => esc_attr(get_the_title($lb_id)),
  ];

  $grp = get_field('lookbook', $lb_id);
  $t_rows = [];

  if (is_array($grp) && isset($grp['testimonios']) && is_array($grp['testimonios'])) {
    $t_rows = $grp['testimonios'];
  } else {
    $tmp = get_field('testimonios', $lb_id);
    if (is_array($tmp)) {
      $t_rows = $tmp;
    }
  }

  $imap = []; // pid => [pid, nombre, rol, contenido, galeria]
  $pmap = []; // pid => product bits (solo para modal mobile)
  $first_info = [
    'pid'       => 0,
    'nombre'    => '',
    'rol'       => '',
    'contenido' => '',
    'galeria'   => [],
  ];

  if (is_array($t_rows)) {
    foreach ($t_rows as $row) {
      $pid = nlhs_resolve_related_id($row['producto_asociado'] ?? 0);

      $info = [
        'pid'       => $pid,
        'nombre'    => (string)($row['nombre'] ?? ''),
        'rol'       => (string)($row['rol_testimonial'] ?? ''),
        'contenido' => (string)($row['testimonio_content'] ?? ''),
        'galeria'   => nlhs_gallery_bits($row['galeria'] ?? []),
      ];

      if (!nlhs_has_info_row($first_info) && nlhs_has_info_row($info)) {
        $first_info = $info;
      }

      if ($pid) {
        $imap[$pid] = $info;

        if (!isset($pmap[$pid])) {
          $pmap[$pid] = nlhs_product_bits($pid);
        }
      }
    }
  }

  /* Detectar IDs reales de producto desde el HTML */
  $product_ids_from_html = [];

  if (preg_match_all('/data-pid="(\d+)"/i', $lookbook_html, $m1)) {
    $product_ids_from_html = array_merge($product_ids_from_html, array_map('intval', $m1[1]));
  }

  if (preg_match_all('/data-product(?:_id)?="(\d+)"/i', $lookbook_html, $m2)) {
    $product_ids_from_html = array_merge($product_ids_from_html, array_map('intval', $m2[1]));
  }

  $product_ids_from_html = array_values(array_unique(array_filter($product_ids_from_html)));

  foreach ($product_ids_from_html as $pid) {
    if (!isset($pmap[$pid])) {
      $pmap[$pid] = nlhs_product_bits($pid);
    }
  }

  $slides[] = [
    'id'        => $lb_id,
    'html'      => $lookbook_html,
    'imap'      => $imap,
    'pmap'      => $pmap,
    'firstinfo' => $first_info,
  ];
}

/* Estado inicial */
$init_name    = '';
$init_role    = '';
$init_quote   = '';
$init_gallery = [];

if (!empty($slides)) {
  $first_slide = $slides[0];
  $first_info  = $first_slide['firstinfo'] ?? [];

  if (nlhs_has_info_row($first_info)) {
    $init_name    = $first_info['nombre'] ?? '';
    $init_role    = $first_info['rol'] ?? '';
    $init_quote   = $first_info['contenido'] ?? '';
    $init_gallery = is_array($first_info['galeria'] ?? null) ? $first_info['galeria'] : [];
  }
}
?>

<section
  id="<?php echo esc_attr($section_id); ?>"
  class="<?php echo esc_attr($classes); ?>"
  data-hs-root="1"
  data-section-id="<?php echo esc_attr($section_id); ?>"
>
  <div class="hs-container">
    <h1 class="font-heading-1 light">VIVENCIAS CON NUESTRAS PIEZAS</h1>

    <div class="carousel-wrapper">
      <div class="carousel-content">

        <div class="left-panel">
          <div class="testimonial">
            <h2 class="font-heading-4" data-hs-tname><?php echo esc_html($init_name); ?></h2>
            <p class="role font-overline" data-hs-trole><?php echo esc_html($init_role); ?></p>
            <div class="font-body-medium-light testimonial-text" data-hs-ttext><?php echo wp_kses_post($init_quote); ?></div>
          </div>

          <div class="hs-side-gallery" data-hs-gallery-root>
            <div class="hs-side-gallery__viewport">
              <div class="hs-side-gallery__track" data-hs-gallery-track>
                <?php if (!empty($init_gallery)) : ?>
                  <?php foreach ($init_gallery as $gi => $gimg) : ?>
                    <div class="hs-side-gallery__slide<?php echo $gi === 0 ? ' is-active' : ''; ?>">
                      <img
                        src="<?php echo esc_url($gimg['url']); ?>"
                        alt="<?php echo esc_attr($gimg['alt'] ?? ''); ?>"
                        class="hs-side-gallery__img"
                        loading="lazy"
                      >
                    </div>
                  <?php endforeach; ?>
                <?php else : ?>
                  <div class="hs-side-gallery__slide is-active">
                    <div class="hs-side-gallery__empty"></div>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="hs-side-gallery__dots" data-hs-gallery-dots>
              <?php if (!empty($init_gallery) && count($init_gallery) > 1) : ?>
                <?php foreach ($init_gallery as $gi => $gimg) : ?>
                  <button
                    type="button"
                    class="hs-side-gallery__dot<?php echo $gi === 0 ? ' is-active' : ''; ?>"
                    data-hs-gallery-dot="<?php echo esc_attr($gi); ?>"
                    aria-label="Ver imagen <?php echo esc_attr($gi + 1); ?>"
                  ></button>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="main-carousel">
          <div class="carousel-main-image">
            <div class="dots-indicator" data-hs-dots></div>

            <div class="carousel-track" data-hs-track>
              <?php foreach ($slides as $i => $s): ?>
                <div
                  class="carousel-slide<?php echo $i === 0 ? ' is-active' : ''; ?>"
                  data-lbid="<?php echo esc_attr($s['id']); ?>"
                  data-imap="<?php echo esc_attr(base64_encode(wp_json_encode($s['imap']))); ?>"
                  data-pmap="<?php echo esc_attr(base64_encode(wp_json_encode($s['pmap']))); ?>"
                  data-firstinfo="<?php echo esc_attr(base64_encode(wp_json_encode($s['firstinfo']))); ?>"
                >
                  <?php echo $s['html']; ?>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="nav-buttons nav-buttons--desktop">
              <button class="nav-btn" type="button" data-hs-nav="prev" aria-label="Anterior">
                <svg viewBox="0 0 24 24" fill="none">
                  <path d="M15 18L9 12L15 6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>

              <button class="nav-btn" type="button" data-hs-nav="next" aria-label="Siguiente">
                <svg viewBox="0 0 24 24" fill="none">
                  <path d="M9 18L15 12L9 6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
            </div>
          </div>

          <div class="nav-buttons nav-buttons--mobile">
            <button class="nav-btn" type="button" data-hs-nav="prev" aria-label="Anterior">
              <svg viewBox="0 0 24 24" fill="none">
                <path d="M15 18L9 12L15 6" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>

            <button class="nav-btn" type="button" data-hs-nav="next" aria-label="Siguiente">
              <svg viewBox="0 0 24 24" fill="none">
                <path d="M9 18L15 12L9 6" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
          </div>

          <div class="thumbnail-preview" data-hs-thumbs>
            <?php foreach ($thumbs as $i => $th): ?>
              <div
                class="thumbnail<?php echo $i === 0 ? ' active' : ''; ?>"
                data-index="<?php echo esc_attr($i); ?>"
                role="button"
                tabindex="0"
                aria-label="Ir al slide <?php echo esc_attr($i + 1); ?>"
              >
                <?php if (!empty($th['src'])): ?>
                  <img
                    src="<?php echo esc_url($th['src']); ?>"
                    alt="<?php echo esc_attr($th['alt']); ?>"
                    loading="lazy"
                  >
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>

        </div>
      </div>
    </div>
  </div>
</section>

<script>
(function() {
  'use strict';
  
  function qs(el, sel) { return el ? el.querySelector(sel) : null; }
  function qsa(el, sel) { return el ? Array.from(el.querySelectorAll(sel)) : []; }
  
  function init(root) {
    console.log('🔥 Hotspots completo inicializando...');
    
    // Elementos principales
    var track = qs(root, '[data-hs-track]');
    var slides = qsa(root, '.carousel-slide');
    var thumbs = qsa(root, '.thumbnail');
    var prevBtns = qsa(root, '[data-hs-nav="prev"]');
    var nextBtns = qsa(root, '[data-hs-nav="next"]');
    var dotsBox = qs(root, '[data-hs-dots]');
    
    // Testimonio IZQUIERDA
    var tName = qs(root, '[data-hs-tname]');
    var tRole = qs(root, '[data-hs-trole]');
    var tText = qs(root, '[data-hs-ttext]');
    
    // Galería IZQUIERDA
    var galleryRoot = qs(root, '[data-hs-gallery-root]');
    var galleryTrack = qs(root, '[data-hs-gallery-track]');
    var galleryDots = qs(root, '[data-hs-gallery-dots]');
    
    if (!track || slides.length === 0) return;
    
    var currentSlide = 0;
    var currentGallery = 0;
    
    // ===== 1. CARRUSEL PRINCIPAL =====
    function goToSlide(index) {
      if (index < 0) index = slides.length - 1;
      if (index >= slides.length) index = 0;
      
      currentSlide = index;
      var slideWidth = track.offsetWidth;
      track.style.transform = `translateX(-${currentSlide * slideWidth}px)`;
      
      // Activar slide y thumbs
      slides.forEach((s, i) => s.classList.toggle('is-active', i === currentSlide));
      thumbs.forEach((t, i) => t.classList.toggle('active', i === currentSlide));
      
      // ACTUALIZAR TESTIMONIO + GALERÍA
      updateLeftPanel(slides[currentSlide]);
    }
    
    // ===== 2. TESTIMONIO + GALERÍA IZQUIERDA =====
    function updateLeftPanel(slide) {
      if (!slide) return;
      
      // Extraer datos del slide
      var imap = JSON.parse(atob(slide.dataset.imap || '{}'));
      var firstInfo = JSON.parse(atob(slide.dataset.firstinfo || '{}'));
      
      // Buscar primer testimonio válido
      var info = firstInfo;
      if (!info.nombre && !info.rol && !info.contenido && !info.galeria?.length) {
        // Buscar en imap
        for (var pid in imap) {
          var item = imap[pid];
          if (item.nombre || item.rol || item.contenido || item.galeria?.length) {
            info = item;
            break;
          }
        }
      }
      
      // Actualizar testimonio
      if (tName) tName.textContent = info.nombre || '';
      if (tRole) tRole.textContent = info.rol || '';
      if (tText) tText.innerHTML = info.contenido || '';
      
      // Actualizar galería
      renderGallery(info.galeria || []);
    }
    
    function renderGallery(items) {
      if (!galleryTrack || !galleryDots || !galleryRoot) return;
      
      currentGallery = 0;
      items = Array.isArray(items) ? items : [];
      
      // Limpiar
      galleryTrack.innerHTML = '';
      galleryDots.innerHTML = '';
      
      if (items.length === 0) {
        galleryTrack.innerHTML = '<div class="hs-side-gallery__slide is-active"><div class="hs-side-gallery__empty"></div></div>';
        return;
      }
      
      // Crear slides de galería
      items.forEach((item, i) => {
        var slide = document.createElement('div');
        slide.className = `hs-side-gallery__slide ${i === 0 ? 'is-active' : ''}`;
        slide.innerHTML = `<img class="hs-side-gallery__img" src="${item.url}" alt="${item.alt || ''}" loading="lazy">`;
        galleryTrack.appendChild(slide);
      });
      
      // Crear dots de galería
      if (items.length > 1) {
        items.forEach((_, i) => {
          var dot = document.createElement('button');
          dot.className = `hs-side-gallery__dot ${i === 0 ? 'is-active' : ''}`;
          dot.dataset.galleryIndex = i;
          dot.setAttribute('aria-label', `Imagen ${i + 1}`);
          galleryDots.appendChild(dot);
        });
      }
      
      goToGallery(0);
    }
    
    function goToGallery(index) {
      if (!galleryTrack) return;
      
      var gSlides = qsa(galleryTrack, '.hs-side-gallery__slide');
      var gDots = qsa(root, '[data-gallery-index]');
      
      if (index < 0) index = gSlides.length - 1;
      if (index >= gSlides.length) index = 0;
      
      currentGallery = index;
      var gWidth = galleryRoot.offsetWidth;
      galleryTrack.style.transform = `translateX(-${currentGallery * gWidth}px)`;
      
      gSlides.forEach((s, i) => s.classList.toggle('is-active', i === currentGallery));
      gDots.forEach((d, i) => d.classList.toggle('is-active', i === currentGallery));
    }
    
    // ===== 3. EVENTOS =====
    // Flechas
    prevBtns.forEach(btn => btn.onclick = () => goToSlide(currentSlide - 1));
    nextBtns.forEach(btn => btn.onclick = () => goToSlide(currentSlide + 1));
    
    // Thumbs
    thumbs.forEach((thumb, i) => {
      thumb.onclick = () => goToSlide(i);
    });
    
    // Dots principales
    if (dotsBox) {
      dotsBox.innerHTML = '';
      slides.forEach((_, i) => {
        var dot = document.createElement('button');
        dot.className = `dot ${i === 0 ? 'active' : ''}`;
        dot.onclick = () => goToSlide(i);
        dotsBox.appendChild(dot);
      });
    }
    
    // Dots de galería
    root.addEventListener('click', function(e) {
      var galleryDot = e.target.closest('.hs-side-gallery__dot');
      if (galleryDot && galleryDot.dataset.galleryIndex !== undefined) {
        e.preventDefault();
        goToGallery(parseInt(galleryDot.dataset.galleryIndex));
      }
    });
    
    // Markers (puntos en las imágenes)
    root.addEventListener('click', function(e) {
      var marker = e.target.closest('.wlb-marker[data-pid]');
      if (marker) {
        var pid = marker.dataset.pid;
        var slide = slides[currentSlide];
        var imap = JSON.parse(atob(slide.dataset.imap || '{}'));
        if (imap[pid]) {
          updateLeftPanel(slide); // Re-render con ese producto
        }
      }
    });
    
    // Resize
    var resizeTimer;
    window.addEventListener('resize', function() {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(() => {
        goToSlide(currentSlide);
      }, 250);
    });
    
    // Inicializar
    goToSlide(0);
    console.log('🎉 Hotspots COMPLETO funcionando!');
  }
  
  // Buscar e inicializar
  document.addEventListener('DOMContentLoaded', function() {
    var root = document.querySelector('[data-hs-root="1"]');
    if (root) init(root);
  });
  
  // También por si acaso
  window.addEventListener('load', function() {
    var root = document.querySelector('[data-hs-root="1"]');
    if (root) init(root);
  });
})();
</script>