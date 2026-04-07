<?php
/**
 * Hotspots (LookBook) – Carrusel + Testimonios
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

/* Extrae src confiable desde el HTML del plugin */
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
    $t_rows = get_field('testimonios', $lb_id);
  }

  $tmap = []; // pid => [nombre, rol, contenido]
  $pmap = []; // pid => product bits

  if (is_array($t_rows)) {
    foreach ($t_rows as $row) {
      $rel = $row['producto_asociado'] ?? 0;
      $pid = 0;

      if (is_array($rel)) {
        if (isset($rel[0])) {
          $first = $rel[0];
          if (is_numeric($first)) $pid = (int)$first;
          elseif (is_object($first) && isset($first->ID)) $pid = (int)$first->ID;
          elseif (is_array($first) && isset($first['ID'])) $pid = (int)$first['ID'];
        } elseif (isset($rel['ID'])) {
          $pid = (int)$rel['ID'];
        }
      } elseif (is_object($rel) && isset($rel->ID)) {
        $pid = (int)$rel->ID;
      } elseif (is_numeric($rel)) {
        $pid = (int)$rel;
      }

      if ($pid) {
        $tmap[$pid] = [
          'nombre'    => (string)($row['nombre'] ?? ''),
          'rol'       => (string)($row['rol_testimonial'] ?? ''),
          'contenido' => (string)($row['testimonio_content'] ?? ''),
        ];

        if (!isset($pmap[$pid])) {
          $pmap[$pid] = nlhs_product_bits($pid);
        }
      }
    }
  }

  /* Solo detectar IDs reales de producto desde el HTML */
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
    'id'   => $lb_id,
    'html' => $lookbook_html,
    'tmap' => $tmap,
    'pmap' => $pmap,
  ];
}

/* Estado inicial */
$init_name = $init_role = $init_quote = '';
$init_img = $init_pname = $init_price = $init_link = '';

if (!empty($slides)) {
  $first = $slides[0];
  $seed  = 0;

  if (!empty($first['tmap'])) {
    $keys = array_keys($first['tmap']);
    $seed = (int) reset($keys);
  }

  if (!$seed && !empty($first['pmap'])) {
    $keys = array_keys($first['pmap']);
    $seed = (int) reset($keys);
  }

  if ($seed) {
    if (isset($first['tmap'][$seed])) {
      $init_name  = $first['tmap'][$seed]['nombre'] ?? '';
      $init_role  = $first['tmap'][$seed]['rol'] ?? '';
      $init_quote = $first['tmap'][$seed]['contenido'] ?? '';
    }

    if (isset($first['pmap'][$seed])) {
      $init_img   = $first['pmap'][$seed]['img_url'] ?? '';
      $init_pname = $first['pmap'][$seed]['name'] ?? '';
      $init_price = $first['pmap'][$seed]['price_html'] ?? '';
      $init_link  = $first['pmap'][$seed]['permalink'] ?? '';
    }
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
            <p class="font-body-medium-light testimonial-text" data-hs-ttext><?php echo wp_kses_post($init_quote); ?></p>
          </div>

          <div class="product-card">
            <a data-hs-plink href="<?php echo esc_url($init_link ?: '#'); ?>" aria-label="Ver producto">
              <img
                src="<?php echo esc_url($init_img ?: ''); ?>"
                alt=""
                class="product-image"
                data-hs-pimg
              >
            </a>

            <div class="product-info">
              <div class="product-details">
                <a
                  data-hs-pname
                  class="hs-pname-link font-button"
                  href="<?php echo esc_url($init_link ?: '#'); ?>"
                >
                  <?php echo esc_html($init_pname); ?>
                </a>

                <p class="font-button" data-hs-pvariant></p>
              </div>

              <div class="product-price font-overline" data-hs-pprice>
                <?php echo $init_price; ?>
              </div>
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
                  data-tmap="<?php echo esc_attr(base64_encode(wp_json_encode($s['tmap']))); ?>"
                  data-pmap="<?php echo esc_attr(base64_encode(wp_json_encode($s['pmap']))); ?>"
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
  (function () {
  'use strict';

  function matches(el, sel) {
    if (!el || !sel) return false;
    var fn = el.matches || el.webkitMatchesSelector || el.msMatchesSelector;
    return fn ? fn.call(el, sel) : false;
  }

  function closest(el, sel) {
    while (el && el !== document) {
      if (matches(el, sel)) return el;
      el = el.parentNode;
    }
    return null;
  }

  function qs(scope, sel) {
    return scope ? scope.querySelector(sel) : null;
  }

  function qsa(scope, sel) {
    return scope ? Array.prototype.slice.call(scope.querySelectorAll(sel)) : [];
  }

  function toInt(v) {
    var n = parseInt(v || '0', 10);
    return isNaN(n) ? 0 : n;
  }

  function safeJSON64(str) {
    try {
      if (!str) return {};
      return JSON.parse(window.atob(str)) || {};
    } catch (e) {
      console.warn('[HS] JSON64 parse error', e, str);
      return {};
    }
  }

  function getMaps(slide) {
    if (!slide) return { tmap: {}, pmap: {} };

    return {
      tmap: safeJSON64(slide.getAttribute('data-tmap')),
      pmap: safeJSON64(slide.getAttribute('data-pmap'))
    };
  }

  function getSeedPid(slide) {
    var maps = getMaps(slide);

    var tKeys = Object.keys(maps.tmap || {}).filter(function (k) {
      var t = maps.tmap[k] || {};
      return !!(t.nombre || t.rol || t.contenido);
    });
    if (tKeys.length) return toInt(tKeys[0]);

    var pKeys = Object.keys(maps.pmap || {}).filter(function (k) {
      var p = maps.pmap[k] || {};
      return !!(p.name || p.img_url || p.permalink || p.price_html);
    });
    if (pKeys.length) return toInt(pKeys[0]);

    return 0;
  }

  function initHotspots(root) {
    if (!root || root.dataset.hsInit === '1') return;
    root.dataset.hsInit = '1';

    var track      = qs(root, '[data-hs-track]');
    var dotsC      = qs(root, '[data-hs-dots]');
    var thumbsWrap = qs(root, '[data-hs-thumbs]');

    var tName    = qs(root, '[data-hs-tname]');
    var tRole    = qs(root, '[data-hs-trole]');
    var tText    = qs(root, '[data-hs-ttext]');

    var pImg     = qs(root, '[data-hs-pimg]');
    var pName    = qs(root, '[data-hs-pname]');
    var pPrice   = qs(root, '[data-hs-pprice]');
    var pLink    = qs(root, '[data-hs-plink]');
    var pVariant = qs(root, '[data-hs-pvariant]');

    var slides = qsa(root, '.carousel-slide');
    var thumbs = qsa(root, '.thumbnail');
    var navBtns = qsa(root, '[data-hs-nav]');

    if (!track || !slides.length) {
      console.warn('[HS] no track or no slides', root);
      return;
    }

    console.log('[HS] init ok', {
      root: root,
      slides: slides.length,
      thumbs: thumbs.length,
      navBtns: navBtns.length
    });

    var mq = window.matchMedia ? window.matchMedia('(max-width: 768px)') : null;
    var isMobile = mq ? mq.matches : (window.innerWidth <= 768);

    function syncMobile() {
      isMobile = mq ? mq.matches : (window.innerWidth <= 768);
    }

    if (mq) {
      if (mq.addEventListener) mq.addEventListener('change', syncMobile);
      else if (mq.addListener) mq.addListener(syncMobile);
    }

    if (thumbsWrap) {
      thumbsWrap.style.position = 'relative';
      thumbsWrap.style.zIndex = '10002';
      thumbsWrap.style.pointerEvents = 'auto';
    }

    qsa(root, '.thumbnail, .thumbnail img, .nav-buttons, .nav-btn').forEach(function (el) {
      el.style.pointerEvents = 'auto';
    });

    var currentIndex = 0;
    var dots = [];

    var modal = qs(root, '.hs-modal');
    if (!modal) {
      modal = document.createElement('div');
      modal.className = 'hs-modal';
      modal.innerHTML =
        '<div class="hs-modal__backdrop" data-close></div>' +
        '<div class="hs-modal__sheet" role="dialog" aria-modal="true" aria-label="Producto">' +
          '<div class="hs-modal__top">' +
            '<div class="font-overline">Producto</div>' +
            '<button class="hs-modal__close" type="button" aria-label="Cerrar" data-close>✕</button>' +
          '</div>' +
          '<a data-mlink href="#" aria-label="Ver producto">' +
            '<img class="hs-modal__img" data-mimg src="" alt="">' +
          '</a>' +
          '<div class="hs-modal__info">' +
            '<a class="hs-modal__name font-button" data-mname href="#"></a>' +
            '<div class="font-overline" data-mprice></div>' +
          '</div>' +
          '<a class="hs-modal__cta font-button" data-mcta href="#">Ver producto</a>' +
        '</div>';

      root.appendChild(modal);
    }

    var mImg   = qs(modal, '[data-mimg]');
    var mName  = qs(modal, '[data-mname]');
    var mPrice = qs(modal, '[data-mprice]');
    var mLink  = qs(modal, '[data-mlink]');
    var mCta   = qs(modal, '[data-mcta]');

    function closeModal() {
      modal.classList.remove('is-open');
      document.documentElement.classList.remove('hs-modal-open');
      document.body.classList.remove('hs-modal-open');
    }

    function openModalWithProduct(p) {
      if (!p) return;

      if (mImg) {
        if (p.img_url) mImg.src = p.img_url;
        else mImg.removeAttribute('src');
      }

      if (mName) {
        mName.textContent = p.name || '';
        if (p.permalink) mName.setAttribute('href', p.permalink);
        else mName.removeAttribute('href');
      }

      if (mPrice) mPrice.innerHTML = p.price_html || '';

      var href = p.permalink || '#';
      if (mLink) mLink.setAttribute('href', href);
      if (mCta)  mCta.setAttribute('href', href);

      modal.classList.add('is-open');
      document.documentElement.classList.add('hs-modal-open');
      document.body.classList.add('hs-modal-open');
    }

    modal.addEventListener('click', function (e) {
      var t = e.target;
      if (t && t.getAttribute && t.getAttribute('data-close') !== null) {
        closeModal();
      }
    });

    function rebuildDots() {
      dots = [];
      if (!dotsC) return;

      dotsC.innerHTML = '';

      slides.forEach(function (_, i) {
        var d = document.createElement('div');
        d.className = 'dot' + (i === currentIndex ? ' active' : '');
        d.setAttribute('data-index', String(i));

        d.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          console.log('[HS] dot click', i);
          goTo(i);
        });

        dotsC.appendChild(d);
        dots.push(d);
      });
    }

    function updatePanels(slide, pid) {
      if (!slide) return;

      var maps = getMaps(slide);
      var tmap = maps.tmap || {};
      var pmap = maps.pmap || {};

      var usePid = pid;
      if (!usePid || (!tmap[String(usePid)] && !pmap[String(usePid)])) {
        usePid = getSeedPid(slide);
      }

      var t = tmap[String(usePid)] || {};
      var p = pmap[String(usePid)] || {};

      if (tName) tName.textContent = t.nombre || '';
      if (tRole) tRole.textContent = t.rol || '';
      if (tText) tText.innerHTML = t.contenido || '';

      if (pImg) {
        if (p.img_url) pImg.src = p.img_url;
        else pImg.removeAttribute('src');
      }

      if (pName) {
        pName.textContent = p.name || '';
        if (p.permalink) pName.href = p.permalink;
        else pName.removeAttribute('href');
      }

      if (pLink) {
        if (p.permalink) pLink.href = p.permalink;
        else pLink.removeAttribute('href');
      }

      if (pPrice) pPrice.innerHTML = p.price_html || '';
      if (pVariant) pVariant.textContent = '';
    }

    function initFirstMarker(slide) {
      if (!slide) return;

      var maps = getMaps(slide);
      var markers = qsa(slide, '.wlb-marker[data-pid]');

      for (var i = 0; i < markers.length; i++) {
        var pid = toInt(markers[i].getAttribute('data-pid'));
        if (maps.tmap[String(pid)] || maps.pmap[String(pid)]) {
          updatePanels(slide, pid);
          return;
        }
      }

      updatePanels(slide, getSeedPid(slide));
    }

    function setActiveUI() {
      track.style.transform = 'translateX(-' + (currentIndex * 100) + '%)';

      slides.forEach(function (slide, i) {
        if (i === currentIndex) slide.classList.add('is-active');
        else slide.classList.remove('is-active');
      });

      dots.forEach(function (dot, i) {
        if (i === currentIndex) dot.classList.add('active');
        else dot.classList.remove('active');
      });

      thumbs.forEach(function (thumb, i) {
        if (i === currentIndex) thumb.classList.add('active');
        else thumb.classList.remove('active');
      });

      initFirstMarker(slides[currentIndex]);
    }

    function goTo(i) {
      currentIndex = (i + slides.length) % slides.length;

      var maps = getMaps(slides[currentIndex]);
      console.log('[HS] goTo', currentIndex, {
        lbid: slides[currentIndex].getAttribute('data-lbid'),
        tmapKeys: Object.keys(maps.tmap || {}),
        pmapKeys: Object.keys(maps.pmap || {})
      });

      setActiveUI();
    }

    function nextSlide() {
      goTo(currentIndex + 1);
    }

    function prevSlide() {
      goTo(currentIndex - 1);
    }

    navBtns.forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var dir = btn.getAttribute('data-hs-nav');
        console.log('[HS] nav click', dir);

        if (dir === 'next') nextSlide();
        else prevSlide();
      });
    });

    thumbs.forEach(function (thumb, idx) {
      thumb.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        console.log('[HS] thumb click', idx);
        goTo(idx);

        if (thumbsWrap) {
          var tw = thumb.offsetWidth + 20;
          var targetLeft = (tw * idx) - (thumbsWrap.offsetWidth / 2) + (tw / 2);

          if (thumbsWrap.scrollTo) {
            thumbsWrap.scrollTo({ left: targetLeft, behavior: 'smooth' });
          } else {
            thumbsWrap.scrollLeft = targetLeft;
          }
        }
      });

      thumb.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          thumb.click();
        }
      });
    });

    root.addEventListener('click', function (e) {
      var marker = closest(e.target, '.wlb-marker[data-pid]');
      if (!marker || !root.contains(marker)) return;

      var pid = toInt(marker.getAttribute('data-pid'));
      if (!pid) return;

      var slide = closest(marker, '.carousel-slide') || slides[currentIndex];
      if (!slide) return;

      updatePanels(slide, pid);

      if (isMobile) {
        e.preventDefault();
        e.stopPropagation();

        var pmap = getMaps(slide).pmap || {};
        openModalWithProduct(pmap[String(pid)] || {});
      }
    }, true);

    function interceptHotspotTouch(e) {
      if (!isMobile) return;
      if (!root.contains(e.target)) return;
      if (closest(e.target, '.hs-modal')) return;
      if (closest(e.target, '[data-hs-nav]')) return;

      var marker = closest(e.target, '.wlb-marker[data-pid]');
      if (!marker) return;

      var pid = toInt(marker.getAttribute('data-pid'));
      if (!pid) return;

      var slide = closest(marker, '.carousel-slide') || slides[currentIndex];
      if (!slide) return;

      e.preventDefault();
      e.stopPropagation();
      if (e.stopImmediatePropagation) e.stopImmediatePropagation();

      updatePanels(slide, pid);

      var pmap = getMaps(slide).pmap || {};
      openModalWithProduct(pmap[String(pid)] || {});
    }

    try {
      document.addEventListener('touchstart', interceptHotspotTouch, { capture: true, passive: false });
    } catch (err) {
      document.addEventListener('touchstart', interceptHotspotTouch, true);
    }

    rebuildDots();
    setActiveUI();
  }

  function initAll(scope) {
    qsa(scope || document, '[data-hs-root]').forEach(initHotspots);
  }

  window.NLHotspotsInit = initHotspots;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initAll(document);
    });
  } else {
    initAll(document);
  }

  window.addEventListener('load', function () {
    initAll(document);
  });

  if ('MutationObserver' in window) {
    var mo = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (!node || node.nodeType !== 1) return;

          if (matches(node, '[data-hs-root]')) {
            initHotspots(node);
          } else if (node.querySelectorAll) {
            initAll(node);
          }
        });
      });
    });

    if (document.body) {
      mo.observe(document.body, { childList: true, subtree: true });
    } else {
      document.addEventListener('DOMContentLoaded', function () {
        mo.observe(document.body, { childList: true, subtree: true });
      });
    }
  }
})();
</script>