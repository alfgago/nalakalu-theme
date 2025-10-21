<?php
/**
 * Render del bloque "Collection"
 * - Selector de colección: field 'collection_selector' (return id)
 * - Fondo de .collection-text: campo ACF del término 'background_video' (URL a GIF/imagen)
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
$term = get_term($term_id, $taxonomy);
$term_name = $term && !is_wp_error($term) ? $term->name : '';
$term_desc = $term && !is_wp_error($term) ? term_description($term_id, $taxonomy) : '';

// Campo ACF del término: 'background_video' (ahora usado como fondo GIF/imagen)
$background_media = function_exists('get_field') ? get_field('background_video', "{$taxonomy}_{$term_id}") : '';
$bg_style = '';
if ( $background_media ) {
    $bg_style = "background-image:url('". esc_url($background_media) ."');";
    // el CSS ya define size/position/repeat
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

$last_id = 0;
$last_img = '';
$last_link = '';
$last_name = '';
$last_price_html = '';
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

    // Primera categoría (product_cat)
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

$collections_url = home_url('/colecciones/');
?>
<section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($class_name); ?>">
  <div class="collection-content">
    <div class="collection-row-1">
      <div class="collection-text" style="<?php echo esc_attr($bg_style); ?>">
        <div class="collection-head">
          <p class="collection-label">Colección</p>
          <?php if ($term_name): ?>
            <h1 class="collection-title"><?php echo esc_html($term_name); ?></h1>
          <?php endif; ?>
        </div>

        <?php if ($term_desc): ?>
          <div class="collection-foot">
            <p class="collection-description"><?php echo wp_kses_post( wp_strip_all_tags($term_desc) ); ?></p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="collection-row-2 collection-product"
         <?php if ($last_img): ?>
           style="background-image:
              linear-gradient(180deg,
                rgba(225,207,186,0) 0%,
                rgba(225,207,186,0) 50%,
                rgba(225,207,186,1) 60%,
                rgba(225,207,186,1) 80%,
                rgba(255,255,255,1) 90%,
                rgba(255,255,255,1) 100%
              ),
              url('<?php echo esc_url($last_img); ?>');
              background-position: 0 0, center;
              background-size: 100% 100%, cover;
              background-repeat: no-repeat, no-repeat;"
         <?php endif; ?>
    >
      <div class="product-badge">
           <img class="text-image" src="https://nalakalu.stag.host/wp-content/uploads/2025/10/cfsad.svg">
        <div class="badge-arrow"><img class="arrow_badge" src="https://nalakalu.stag.host/wp-content/uploads/2025/10/naka_arrow.svg"></div>
      </div>

      <div class="product-details">
        <div class="product-meta">
          <span class="product-category"><?php echo esc_html($last_category_name); ?></span>
          <?php if ($last_link): ?>
            <a href="<?php echo esc_url($last_link); ?>" class="product-link">Ver producto →</a>
          <?php endif; ?>
        </div>
        <div class="product-info-row">
          <h2 class="product-name"><?php echo esc_html($last_name); ?></h2>
          <?php if ($last_price_html): ?>
            <span class="product-price"><?php echo $last_price_html; // wc safe html ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="carousel-container" id="<?php echo esc_attr($section_id); ?>-carousel">
  <div class="info-carousel">
    <span>Explora la colección</span>
    <a href="<?php echo esc_url( $collections_url ); ?>" class="btn-collections">Ver colecciones
    <img class="cta-arrow" src="<?php echo esc_url('https://nalakalu.stag.host/wp-content/uploads/2025/10/arrow_forward.svg'); ?>" alt="" aria-hidden="true" />
    </a>
    
  </div>

  <div class="carousel-wrapper" id="<?php echo esc_attr($section_id); ?>-wrapper">
    <?php if ( $loop_q->have_posts() ) : while ( $loop_q->have_posts() ) : $loop_q->the_post(); ?>
      <?php
        $pid = get_the_ID();
        $plink = get_permalink($pid);
        $pname = get_the_title($pid);
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
            <div class="item-title"><?php echo esc_html($pname); ?></div>
            <div class="item-subtitle">&nbsp;</div>
          </div>
          <?php if ($pprice): ?>
            <div class="item-price"><?php echo $pprice; // wc safe html ?></div>
          <?php endif; ?>
        </div>
      </a>
    <?php endwhile; wp_reset_postdata(); else: ?>
      <div style="padding:1rem 3rem; opacity:.7;">No hay productos en esta colección.</div>
    <?php endif; ?>
  </div>
</section>

<script>
(() => {
  const els = document.querySelectorAll('.product-badge');

  function update() {
    const y = window.scrollY || window.pageYOffset;
    els.forEach(el => {
      const speed = parseFloat(el.dataset.rotSpeed) || 0.25; // grados por px scrolleado
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

  update(); // estado inicial
})();
</script>
<script>
(function () {
  var carousel = document.getElementById('<?php echo esc_js($section_id); ?>-carousel');
  var wrapper  = document.getElementById('<?php echo esc_js($section_id); ?>-wrapper');
  if (!carousel || !wrapper) return;

  // Evitá drag nativo de imágenes y anchors (ghost drag)
  wrapper.querySelectorAll('img').forEach(function (img) {
    img.setAttribute('draggable', 'false');
    img.addEventListener('dragstart', function (e) { e.preventDefault(); });
  });
  wrapper.querySelectorAll('a').forEach(function (a) {
    a.addEventListener('dragstart', function (e) { e.preventDefault(); });
  });

  // ---- Helpers
  function getTranslateX() {
    var m = (wrapper.style.transform || '').match(/translateX\((-?\d+(?:\.\d+)?)px\)/);
    return m ? parseFloat(m[1]) : 0;
  }
  function clamp(val){
    var maxScroll = -(wrapper.scrollWidth - carousel.clientWidth);
    if (!isFinite(maxScroll)) maxScroll = 0;
    return Math.max(maxScroll, Math.min(0, val));
  }

  // ---- Pointer Events (moderno y confiable)
  if (window.PointerEvent) {
    var dragging = false;
    var moved    = false;
    var startX   = 0;
    var startT   = 0;

    // Bloquea clicks si hubo drag
    wrapper.addEventListener('click', function (e) {
      if (moved) {
        e.preventDefault();
        e.stopPropagation();
        moved = false; // resetea para el próximo interaction
      }
    }, true);

    wrapper.addEventListener('pointerdown', function (e) {
      // Solo botón izq si es mouse
      if (e.pointerType === 'mouse' && e.button !== 0) return;

      dragging = true;
      moved = false;

      startX = e.clientX;
      startT = getTranslateX();

      wrapper.style.transition = 'none';

      // Capturamos el puntero para seguir recibiendo los moves aunque salgas del wrapper
      if (wrapper.setPointerCapture) wrapper.setPointerCapture(e.pointerId);
    });

    wrapper.addEventListener('pointermove', function (e) {
      if (!dragging) return;

      var dx   = e.clientX - startX;
      if (Math.abs(dx) > 3) moved = true;

      var next = clamp(startT + dx * 1.5);
      wrapper.style.transform = 'translateX(' + next + 'px)';

      // Evita selección y scroll mientras se arrastra
      e.preventDefault();
    }, { passive: false });

    function endPointer(e){
      if (!dragging) return;
      dragging = false;
      if (wrapper.releasePointerCapture && e.pointerId != null) {
        try { wrapper.releasePointerCapture(e.pointerId); } catch(_) {}
      }
      wrapper.style.transition = 'transform 0.3s ease-out';
      // el click se cancela en el handler de click si moved === true
    }

    wrapper.addEventListener('pointerup', endPointer);
    wrapper.addEventListener('pointercancel', endPointer);

  } else {
    // ---- Fallback Mouse/Touch (por si no hay Pointer Events)
    var isDown = false, moved = false, startX = 0, startT = 0;

    wrapper.addEventListener('click', function (e) {
      if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; }
    }, true);

    function onMouseDown(e){
      if (e.button !== 0) return;
      isDown = true; moved = false;
      startX = e.clientX;
      startT = getTranslateX();
      wrapper.style.transition = 'none';
    }
    function onMouseMove(e){
      if (!isDown) return;
      var dx = e.clientX - startX;
      if (Math.abs(dx) > 3) moved = true;
      var next = clamp(startT + dx * 1.5);
      wrapper.style.transform = 'translateX(' + next + 'px)';
      e.preventDefault();
    }
    function onMouseUp(){
      isDown = false;
      wrapper.style.transition = 'transform 0.3s ease-out';
    }

    function onTouchStart(e){
      isDown = true; moved = false;
      startX = e.touches[0].clientX;
      startT = getTranslateX();
      wrapper.style.transition = 'none';
    }
    function onTouchMove(e){
      if (!isDown) return;
      var dx = e.touches[0].clientX - startX;
      if (Math.abs(dx) > 3) moved = true;
      var next = clamp(startT + dx * 1.5);
      wrapper.style.transform = 'translateX(' + next + 'px)';
      e.preventDefault();
    }
    function onTouchEnd(){
      isDown = false;
      wrapper.style.transition = 'transform 0.3s ease-out';
    }

    // Mouse
    carousel.addEventListener('mousedown', onMouseDown);
    window.addEventListener('mousemove', onMouseMove, { passive: false });
    window.addEventListener('mouseup', onMouseUp);

    // Touch
    carousel.addEventListener('touchstart', onTouchStart, { passive: true });
    window.addEventListener('touchmove', onTouchMove, { passive: false });
    window.addEventListener('touchend', onTouchEnd);
  }
})();
</script>
