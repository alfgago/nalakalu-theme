<?php
/**
 * Render del bloque "Collection"
 * - Selector de colección: field 'collection_selector' (return id)
 * - Fondo de .collection-text: campo ACF del término 'background_video' (archivo: imagen o video)
 * - Muestra último producto de la colección como destacado
 * - Carrusel: hasta 10 productos de la colección (excluye el destacado)
 */

if ( ! function_exists('get_field') ) {
    echo '<p><em>ACF plugin required.</em></p>';
    return;
}

$term_id = (int) get_field('collection_selector');

$section_id = 'collection-block-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$class_name = 'collection-section';
if ( !empty($block['className']) ) $class_name .= ' ' . esc_attr($block['className']);
if ( !empty($block['align']) )     $class_name .= ' align' . esc_attr($block['align']);

$taxonomy = taxonomy_exists('coleccion') ? 'coleccion' : ( taxonomy_exists('pa_coleccion') ? 'pa_coleccion' : 'coleccion' );

if ( ! $term_id ) {
    if ( current_user_can('edit_posts') ) {
        echo '<section id="'.esc_attr($section_id).'" class="'.esc_attr($class_name).'"><p style="opacity:.7">Seleccioná una Colección en el panel del bloque.</p></section>';
    }
    return;
}

// Datos del término
$term      = get_term($term_id, $taxonomy);
$term_name = $term && !is_wp_error($term) ? $term->name : '';
$term_desc = $term && !is_wp_error($term) ? term_description($term_id, $taxonomy) : '';

// Campo ACF del término: 'background_video' (archivo imagen/video)
$bg_image_url  = '';
$bg_video_url  = '';
$bg_is_video   = false;
$bg_mime_type  = '';
$bg_style      = '';

if ( function_exists('get_field') ) {
    $background_media = get_field('background_video', "{$taxonomy}_{$term_id}");

    if ( $background_media ) {
        $file_id   = 0;
        $mime_type = '';
        $url       = '';

        if ( is_array($background_media) ) {
            // ACF file retornando array
            $file_id   = isset($background_media['ID']) ? (int) $background_media['ID'] : 0;
            $mime_type = isset($background_media['mime_type']) ? $background_media['mime_type'] : '';
            $url       = !empty($background_media['url']) ? $background_media['url'] : '';
        } elseif ( is_numeric($background_media) ) {
            // ACF file retornando ID
            $file_id   = (int) $background_media;
            $mime_type = get_post_mime_type($file_id);
            $url       = wp_get_attachment_url($file_id);
        } else {
            // Fallback viejo: string URL
            $url       = $background_media;
            $mime_type = '';
        }

        if ( $url ) {
            if ( $mime_type && strpos($mime_type, 'video/') === 0 ) {
                $bg_video_url = $url;
                $bg_is_video  = true;
                $bg_mime_type = $mime_type;
            } else {
                $bg_image_url = $url;
            }
        }
    }
}

if ( $bg_image_url ) {
    // Gradient + imagen de fondo (el gradient arriba de la imagen)
    $bg_style  = "background-image: ";
    $bg_style .= "linear-gradient(289deg, #e3d1bac7 56.71%, #b7a48d70 99.59%), ";
    $bg_style .= "url('" . esc_url($bg_image_url) . "');";
    $bg_style .= "background-size: cover;";
    $bg_style .= "background-position: center;";
}


// Último producto (destacado)
$last_q = new WP_Query(array(
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => 1,
    'tax_query'      => array(
        array(
            'taxonomy' => $taxonomy,
            'field'    => 'term_id',
            'terms'    => $term_id,
        ),
    ),
    'orderby'        => 'date',
    'order'          => 'DESC',
));

$last_id            = 0;
$last_img           = '';
$last_link          = '';
$last_name          = '';
$last_price_html    = '';
$last_category_name = '';

if ( $last_q->have_posts() ) {
    $last_q->the_post();
    $last_id   = get_the_ID();
    $last_link = get_permalink($last_id);
    $last_name = get_the_title($last_id);

    if ( has_post_thumbnail($last_id) ) {
        $img = wp_get_attachment_image_src(get_post_thumbnail_id($last_id), 'large');
        if (!empty($img[0])) $last_img = esc_url($img[0]);
    }

    if ( function_exists('wc_get_product') ) {
        $product = wc_get_product($last_id);
        if ($product) $last_price_html = $product->get_price_html();
    }

    $cats = get_the_terms($last_id, 'product_cat');
    if (!is_wp_error($cats) && !empty($cats)) {
        $last_category_name = $cats[0]->name;
    }
    wp_reset_postdata();
}

// Carrusel (hasta 10), excluyendo el destacado
$loop_q = new WP_Query(array(
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => 10,
    'post__not_in'   => $last_id ? array($last_id) : array(),
    'tax_query'      => array(
        array(
            'taxonomy' => $taxonomy,
            'field'    => 'term_id',
            'terms'    => $term_id,
        ),
    ),
    'orderby' => 'date',
    'order'   => 'DESC',
));

/**
 * ✅ CORRECCIÓN: URL del botón "Ver coleccion" = link real del término seleccionado
 * Fallback: /colecciones/
 */
$collections_url = '';
if ( $term && ! is_wp_error($term) ) {
    $link = get_term_link($term, $taxonomy);
    if ( ! is_wp_error($link) ) {
        $collections_url = $link;
    }
}
if ( ! $collections_url ) {
    $collections_url = home_url('/colecciones/');
}

$arrow_url       = 'https://nalakalu.stag.host/wp-content/uploads/2025/10/arrow_forward.svg';
?>
<section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($class_name); ?>">
  <div class="collection-content">
    <div class="collection-row-1">
      <div class="collection-text<?php echo $bg_is_video ? ' has-video-bg' : ''; ?>" style="<?php echo esc_attr($bg_style); ?>">

     <?php if ( $bg_is_video && $bg_video_url ) : ?>
  <div class="collection-bg-video-deco" aria-hidden="true">
    <video class="collection-bg-video" autoplay muted loop playsinline preload="auto">
      <source src="<?php echo esc_url($bg_video_url); ?>" type="<?php echo esc_attr( $bg_mime_type ?: 'video/mp4' ); ?>">
    </video>
  </div>
<?php endif; ?>


        <div class="collection-head">
          <p class="font-heading-3 text-blanco-hueso">Colección</p>
          <?php if ($term_name): ?>
            <h1 class="font-heading-display text-blanco-hueso"><?php echo esc_html($term_name); ?></h1>
          <?php endif; ?>
        </div>

        <?php if ($term_desc): ?>
          <div class="collection-foot">
            <p class="font-body-small text-blanco-hueso"><?php echo wp_kses_post( wp_strip_all_tags($term_desc) ); ?></p>
          </div>
        <?php endif; ?>
      </div>
    </div>
<?php
$style_attr = '';
if ( ! empty( $last_img ) ) {
    $style_attr = '--bg-desktop: url("' . esc_url( $last_img ) . '");';
}
?>
    <div class="collection-row-2 collection-product has-dynamic-bg"
     <?php echo $style_attr ? 'style="' . esc_attr( $style_attr ) . '"' : ''; ?>>
      <div class="product-badge">
        <img class="text-image" src="https://nalakalu.stag.host/wp-content/uploads/2025/10/cfsad.svg" alt="">
        <div class="badge-arrow"><img class="arrow_badge" src="https://nalakalu.stag.host/wp-content/uploads/2025/10/naka_arrow.svg" alt=""></div>
      </div>

      <div class="product-details">
        <div class="product-meta">
          <span class="font-body-small"><?php echo esc_html($last_category_name); ?></span>
          <?php if ($last_link): ?>
            <a href="<?php echo esc_url($last_link); ?>" class="desktop-only btn">Ver producto <img class="cta-arrow" src="<?php echo esc_url($arrow_url); ?>" alt="" aria-hidden="true" /></a>
          <?php endif; ?>
        </div>
        <div class="product-info-row">
          <h2 class="font-heading-5"><?php echo esc_html($last_name); ?></h2>
          <?php if ($last_price_html): ?>
            <span class="font-caption-small"><?php echo $last_price_html; ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="carousel-container" id="<?php echo esc_attr($section_id); ?>-carousel">
  <div class="info-carousel">
    <span class="font-heading-3">Explora la colección</span>
    <a href="<?php echo esc_url( $collections_url ); ?>" class="desktop-only btn btn-outline-cafe">
      Ver colección
      <img class="cta-arrow" src="<?php echo esc_url('https://nalakalu.stag.host/wp-content/uploads/2025/10/arrow_forward.svg'); ?>" alt="" aria-hidden="true" />
    </a>
  </div>

  <div class="carousel-wrapper" id="<?php echo esc_attr($section_id); ?>-wrapper">
    <?php if ( $loop_q->have_posts() ) : while ( $loop_q->have_posts() ) : $loop_q->the_post(); ?>
      <?php
        $pid    = get_the_ID();
        $plink  = get_permalink($pid);
        $pname  = get_the_title($pid);
        $pprice = '';
        if ( function_exists('wc_get_product') ) {
          $pobj = wc_get_product($pid);
          if ($pobj) $pprice = $pobj->get_price_html();
        }
        $thumb = get_the_post_thumbnail_url($pid, 'large');
      ?>
      <a class="carousel-item" href="<?php echo esc_url($plink); ?>">
        <?php if ($thumb): ?>
          <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($pname); ?>" class="item-image">
        <?php else: ?>
          <div class="item-image" style="background:#f1f1f1;"></div>
        <?php endif; ?>
        <div class="item-info">
          <div class="item-details">
            <div class="font-button"><?php echo esc_html($pname); ?></div>
          </div>
          <?php if ($pprice): ?>
            <div class="font-overline"><?php echo $pprice; ?></div>
          <?php endif; ?>
        </div>
      </a>
    <?php endwhile; wp_reset_postdata(); else: ?>
      <div style="padding:1rem 3rem; opacity:.7;">No hay productos en esta colección.</div>
    <?php endif; ?>
  </div>

  <!-- Dots para mobile (se llenan por JS sólo en mobile) -->
  <div class="carousel-dots" id="<?php echo esc_attr($section_id); ?>-dots" hidden></div>

  <a href="<?php echo esc_url( $collections_url ); ?>" class="mobile-only-flex btn btn-outline-cafe">Ver colección</a>
</section>

<script>
/* Rotación del badge (igual que antes) */
(() => {
  const els = document.querySelectorAll('.product-badge');
  function update() {
    const y = window.scrollY || window.pageYOffset;
    els.forEach(el => {
      const speed = parseFloat(el.dataset.rotSpeed) || 0.25;
      const angle = (y * speed) % 360;
      el.style.transform = `rotate(${angle}deg)`;
    });
  }
  let ticking = false;
  window.addEventListener('scroll', () => {
    if (!ticking) {
      requestAnimationFrame(() => { update(); ticking = false; });
      ticking = true;
    }
  }, { passive: true });
  update();
})();
</script>

<script>
/**
 * Desktop: drag horizontal como tenías.
 * Mobile: NO drag; navegación por dots mostrando 1 card por vez.
 */
(function () {
  var carousel = document.getElementById('<?php echo esc_js($section_id); ?>-carousel');
  var wrapper  = document.getElementById('<?php echo esc_js($section_id); ?>-wrapper');
  var dotsWrap = document.getElementById('<?php echo esc_js($section_id); ?>-dots');
  if (!carousel || !wrapper) return;

  var mql = window.matchMedia('(max-width: 768px)');
  var cleanupFns = [];

  // Utilidad
  function isMobile(){ return mql.matches; }
  function getTranslateX() {
    var m = (wrapper.style.transform || '').match(/translateX\((-?\d+(?:\.\d+)?)px\)/);
    return m ? parseFloat(m[1]) : 0;
  }
  function clamp(val){
    var maxScroll = -(wrapper.scrollWidth - carousel.clientWidth);
    if (!isFinite(maxScroll)) maxScroll = 0;
    return Math.max(maxScroll, Math.min(0, val));
  }

  // ---------- DESKTOP DRAG (intacto, pero sólo si !mobile)
  function initDesktopDrag(){
    // Evitá drag nativo de imágenes y anchors (ghost drag)
    wrapper.querySelectorAll('img').forEach(function (img) {
      img.setAttribute('draggable', 'false');
      img.addEventListener('dragstart', function (e) { e.preventDefault(); });
    });
    wrapper.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('dragstart', function (e) { e.preventDefault(); });
    });

    if (window.PointerEvent) {
      var dragging = false, moved = false, startX = 0, startT = 0;

      function onClickBlocker(e){
        if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; }
      }
      wrapper.addEventListener('click', onClickBlocker, true);

      function onPointerDown(e){
        if (isMobile()) return; // deshabilitado en mobile
        if (e.pointerType === 'mouse' && e.button !== 0) return;

        dragging = true; moved = false;
        startX = e.clientX;
        startT = getTranslateX();
        wrapper.style.transition = 'none';
        if (wrapper.setPointerCapture) wrapper.setPointerCapture(e.pointerId);
      }
      function onPointerMove(e){
        if (!dragging) return;
        var dx = e.clientX - startX;
        if (Math.abs(dx) > 3) moved = true;
        var next = clamp(startT + dx * 1.5);
        wrapper.style.transform = 'translateX(' + next + 'px)';
        e.preventDefault();
      }
      function onPointerEnd(e){
        if (!dragging) return;
        dragging = false;
        if (wrapper.releasePointerCapture && e.pointerId != null) {
          try { wrapper.releasePointerCapture(e.pointerId); } catch(_) {}
        }
        wrapper.style.transition = 'transform 0.3s ease-out';
      }

      wrapper.addEventListener('pointerdown', onPointerDown);
      window.addEventListener('pointermove', onPointerMove, { passive: false });
      window.addEventListener('pointerup', onPointerEnd);
      window.addEventListener('pointercancel', onPointerEnd);

      cleanupFns.push(function(){
        wrapper.removeEventListener('click', onClickBlocker, true);
        wrapper.removeEventListener('pointerdown', onPointerDown);
        window.removeEventListener('pointermove', onPointerMove, { passive: false });
        window.removeEventListener('pointerup', onPointerEnd);
        window.removeEventListener('pointercancel', onPointerEnd);
      });

    } else {
      // Fallback (igual que antes), también sólo desktop
      var isDown=false, moved=false, startX=0, startT=0;

      function onClickBlocker(e){ if (moved) { e.preventDefault(); e.stopPropagation(); moved=false; } }
      function onMouseDown(e){ if (isMobile()) return; if (e.button !== 0) return; isDown=true; moved=false; startX=e.clientX; startT=getTranslateX(); wrapper.style.transition='none'; }
      function onMouseMove(e){ if (!isDown) return; var dx=e.clientX-startX; if (Math.abs(dx)>3) moved=true; var next=clamp(startT+dx*1.5); wrapper.style.transform='translateX('+next+'px)'; e.preventDefault(); }
      function onMouseUp(){ isDown=false; wrapper.style.transition='transform 0.3s ease-out'; }

      wrapper.addEventListener('click', onClickBlocker, true);
      carousel.addEventListener('mousedown', onMouseDown);
      window.addEventListener('mousemove', onMouseMove, { passive: false });
      window.addEventListener('mouseup', onMouseUp);

      cleanupFns.push(function(){
        wrapper.removeEventListener('click', onClickBlocker, true);
        carousel.removeEventListener('mousedown', onMouseDown);
        window.removeEventListener('mousemove', onMouseMove, { passive: false });
        window.removeEventListener('mouseup', onMouseUp);
      });
    }
  }

  // ---------- MOBILE DOTS (1 card por view, sin drag)
  function initMobileDots(){
    var items = wrapper.querySelectorAll('.carousel-item');
    if (!items.length) return;

    // Preparar wrapper visualmente
    wrapper.style.transition = 'none';
    wrapper.style.transform = 'translateX(0px)';

    // Construir dots
    dotsWrap.innerHTML = '';
    var dots = [];
    for (var i=0; i<items.length; i++){
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'carousel-dot';
      b.setAttribute('aria-label', 'Ir al slide ' + (i+1));
      b.setAttribute('role', 'tab');
      b.dataset.index = String(i);
      dotsWrap.appendChild(b);
      dots.push(b);
    }
    dotsWrap.hidden = false;

    var index = 0;

    function gapPx(){
      var cs = getComputedStyle(wrapper);
      var g = parseFloat(cs.gap || cs.columnGap || 0);
      return isNaN(g) ? 0 : g;
    }
    function slideWidth(){
      var first = items[0];
      if (!first) return carousel.clientWidth;
      var w = first.getBoundingClientRect().width;
      return w + gapPx();
    }
    function goTo(i, animate){
      index = Math.max(0, Math.min(items.length - 1, i));
      var x = -(index * slideWidth());
      wrapper.style.transition = animate ? 'transform .35s cubic-bezier(.22,.61,.36,1)' : 'none';
      wrapper.style.transform = 'translateX(' + x + 'px)';
      updateDots();
    }
    function updateDots(){
      dots.forEach(function(d, di){
        if (di === index) {
          d.setAttribute('aria-selected','true');
          d.classList.add('is-active');
        } else {
          d.setAttribute('aria-selected','false');
          d.classList.remove('is-active');
        }
      });
    }

    dots.forEach(function(d){
      d.addEventListener('click', function(){
        var i = parseInt(d.dataset.index, 10) || 0;
        goTo(i, true);
      });
    });

    // Recalcular en resize de mobile
    function onResize(){
      // Mantener el slide visible al recalc
      goTo(index, false);
    }
    window.addEventListener('resize', onResize);
    cleanupFns.push(function(){
      window.removeEventListener('resize', onResize);
      dotsWrap.hidden = true;
      dotsWrap.innerHTML = '';
    });

    // Inicial
    goTo(0, false);
  }

  function teardown(){
    cleanupFns.forEach(function(fn){ try{ fn(); }catch(e){} });
    cleanupFns = [];
    // Reset visual
    wrapper.style.transition = '';
    wrapper.style.transform = 'translateX(0px)';
    if (dotsWrap){ dotsWrap.hidden = true; dotsWrap.innerHTML=''; }
  }

  function init(){
    teardown();
    if (isMobile()){
      initMobileDots();
    } else {
      initDesktopDrag();
    }
  }

  mql.addEventListener ? mql.addEventListener('change', init) : mql.addListener(init);
  init();
})();
</script>
