<?php
/**
 * Bloque: Product Carousel (Tabs) — mobile con dots, sin flechas
 * Campos ACF:
 * - product_selector_1 (term de product_cat o showroom)
 * - product_selector_2
 * - product_selector_3
 */

if ( ! function_exists('get_field') ) {
  echo '<p><em>ACF plugin required.</em></p>';
  return;
}

$arrow_url  = 'https://nalakalu.stag.host/wp-content/uploads/2025/10/arrow_forward.svg';
$section_id = 'pcarousel-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$classes    = 'pcarousel bg-beige-claro';
if (!empty($block['className'])) $classes .= ' ' . esc_attr($block['className']);
if (!empty($block['align']))     $classes .= ' align' . esc_attr($block['align']);

/**
 * Los selectores de ACF ahora pueden ser:
 * - un WP_Term
 * - un array ACF con ['term_id','taxonomy']
 * - o un ID (legacy, product_cat)
 */
$raw_selectors = [
  get_field('product_selector_1'),
  get_field('product_selector_2'),
  get_field('product_selector_3'),
];

$tabs = [];

foreach ($raw_selectors as $sel) {
  if (!$sel) continue;

  $term = null;

  // Caso 1: ACF devuelve WP_Term
  if ($sel instanceof WP_Term) {
    $term = $sel;

  // Caso 2: ACF formato array ['term_id' => X, 'taxonomy' => 'product_cat'|'showroom']
  } elseif (is_array($sel) && isset($sel['term_id'])) {
    $tax  = !empty($sel['taxonomy']) ? $sel['taxonomy'] : 'product_cat';
    $term = get_term((int) $sel['term_id'], $tax);

  // Caso 3: solo ID → probamos product_cat y luego showroom
  } else {
    $tid = (int) $sel;
    if (!$tid) continue;

    $term = get_term($tid, 'product_cat');
    if (!$term || is_wp_error($term)) {
      $term = get_term($tid, 'showroom');
    }
  }

  if ($term && !is_wp_error($term)) {
    $tabs[] = [
      'id'       => (int) $term->term_id,
      'taxonomy' => $term->taxonomy,          // <-- guardamos la taxonomía
      'name'     => $term->name,
      'link'     => get_term_link($term),
      'slug'     => $term->slug,
    ];
  }
}

if (empty($tabs)) {
  if ( current_user_can('edit_posts') ) {
    echo '<section class="'.esc_attr($classes).'"><div class="container"><p style="opacity:.7">Seleccioná al menos una categoría de producto o showroom.</p></div></section>';
  }
  return;
}

/** Render del track de productos por término (product_cat o showroom) */
$nl_render_products_track = function($term_id, $taxonomy, $track_id, $is_active = false) {
  $q = new WP_Query([
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => 12,
    'tax_query'      => [[
      'taxonomy' => $taxonomy,     // <-- antes estaba fijo en 'product_cat'
      'field'    => 'term_id',
      'terms'    => $term_id,
    ]],
    'orderby'        => 'date',
    'order'          => 'DESC',
  ]);

  echo '<div class="products-track'.($is_active ? ' is-active' : '').'" data-track="'.esc_attr($track_id).'">';
  echo   '<div class="products-grid" id="'.esc_attr($track_id).'">';

  if ($q->have_posts()) {
    while ($q->have_posts()) {
      $q->the_post();
      $pid    = get_the_ID();
      $plink  = get_permalink($pid);
      $pname  = get_the_title($pid);
      $thumb  = get_the_post_thumbnail_url($pid, 'woocommerce_thumbnail');
      if (!$thumb) {
        $thumb = function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src() : '';
      }
      $price_html = '';
      if (function_exists('wc_get_product')) {
        $product = wc_get_product($pid);
        if ($product) $price_html = $product->get_price_html();
      }

      echo '<a class="product-card" href="'.esc_url($plink).'">';
        echo '<div class="product-image">';
          if ($thumb) echo '<img src="'.esc_url($thumb).'" alt="'.esc_attr($pname).'" loading="lazy" decoding="async">';
        echo '</div>';
        echo '<div class="product-info">';
          echo   '<div class="product-name"><div class="font-button">'.esc_html($pname).'</div></div>';
          echo   '<div class="font-overline">'.$price_html.'</div>';
        echo '</div>';
      echo '</a>';
    }
    wp_reset_postdata();
  } else {
    echo '<div style="opacity:.7;padding:1rem 0;">No hay productos en esta categoría.</div>';
  }

  echo   '</div>'; // .products-grid
  echo '</div>';   // .products-track
};
?>
<section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($classes); ?>">
  <div class="container">
    <div class="header-nav">
      <div class="tabs" role="tablist" aria-label="Categorías de productos">
        <?php foreach ($tabs as $i => $t): ?>
          <button
            class="tab font-overline<?php echo $i===0 ? ' active' : ''; ?>"
            role="tab"
            aria-selected="<?php echo $i===0 ? 'true' : 'false'; ?>"
            aria-controls="<?php echo esc_attr($section_id.'-track-'.$i); ?>"
            data-tab-index="<?php echo esc_attr($i); ?>"
          ><?php echo esc_html($t['name']); ?></button>
        <?php endforeach; ?>
      </div>

      <?php
        $first_name = $tabs[0]['name'];
        $first_link = $tabs[0]['link'];
      ?>
      <a class="desktop-only view-more btn btn-outline-cafe" href="<?php echo esc_url($first_link); ?>">
        <span class="view-more__label">Ver stock de <?php echo esc_html($first_name); ?></span>
        <img class="cta-arrow" src="<?php echo esc_url($arrow_url); ?>" alt="" aria-hidden="true" />
      </a>
    </div>

    <!-- Título + flechas (flechas ocultas en mobile) -->
    <div class="title-row">
      <h1 class="font-heading-2">
        AHORA DISPONIBLE EN <span class="current-location"><?php echo esc_html($first_name); ?></span>
      </h1>
      <div class="nav-buttons">
        <button class="carousel-btn prev-btn" aria-label="Anterior"><svg xmlns="http://www.w3.org/2000/svg" width="30" height="28" viewBox="0 0 16 14" fill="none">
  <path d="M6.96785 13.8533C7.01866 13.9011 7.0786 13.9387 7.14425 13.9638C7.2099 13.989 7.27997 14.0012 7.35047 13.9999C7.49284 13.9972 7.62829 13.9393 7.72701 13.839C7.82574 13.7386 7.87966 13.604 7.8769 13.4648C7.87415 13.3256 7.81496 13.1931 7.71234 13.0966L1.78861 7.52432L15.2131 7.52432C15.3555 7.52432 15.4921 7.46901 15.5927 7.37055C15.6934 7.27209 15.75 7.13854 15.75 6.9993C15.75 6.86005 15.6934 6.72651 15.5927 6.62805C15.4921 6.52959 15.3555 6.47427 15.2131 6.47427L1.79004 6.47427L7.71305 0.90341C7.76387 0.855607 7.80455 0.798484 7.83279 0.735305C7.86102 0.672125 7.87626 0.604126 7.87762 0.535189C7.87898 0.466252 7.86645 0.397728 7.84073 0.333528C7.81501 0.26933 7.77661 0.210712 7.72773 0.161024C7.67885 0.111336 7.62043 0.0715501 7.55582 0.0439384C7.49122 0.0163257 7.42168 0.00142741 7.35118 9.5129e-05C7.28069 -0.00123715 7.21062 0.0110214 7.14497 0.0361717C7.07931 0.061321 7.01937 0.09887 6.96856 0.146673L0.219443 6.49527C0.15005 6.56059 0.0948477 6.63892 0.0571413 6.72559C0.019435 6.81225 0 6.90547 0 6.99965C0 7.09383 0.019435 7.18705 0.0571413 7.27371C0.0948477 7.36038 0.15005 7.43871 0.219443 7.50402L6.96785 13.8533Z" fill="#3D332B"/>
</svg></button>
        <button class="carousel-btn next-btn" aria-label="Siguiente"><svg xmlns="http://www.w3.org/2000/svg" width="30" height="28" viewBox="0 0 16 14" fill="none">
  <path d="M8.78215 13.8533C8.73134 13.9011 8.6714 13.9387 8.60575 13.9638C8.5401 13.989 8.47003 14.0012 8.39953 13.9999C8.25716 13.9972 8.12171 13.9393 8.02299 13.839C7.92426 13.7386 7.87034 13.604 7.8731 13.4648C7.87585 13.3256 7.93504 13.1931 8.03766 13.0966L13.9614 7.52432L0.536892 7.52432C0.394499 7.52432 0.257937 7.46901 0.15725 7.37055C0.0565633 7.27209 -1.93715e-06 7.13854 -1.93715e-06 6.9993C-1.93715e-06 6.86005 0.0565633 6.72651 0.15725 6.62805C0.257937 6.52959 0.394499 6.47427 0.536892 6.47427L13.96 6.47427L8.03695 0.90341C7.98613 0.855607 7.94545 0.798484 7.91721 0.735305C7.88898 0.672125 7.87374 0.604126 7.87238 0.535189C7.87102 0.466252 7.88355 0.397728 7.90927 0.333528C7.93499 0.26933 7.97339 0.210712 8.02227 0.161024C8.07115 0.111336 8.12957 0.0715501 8.19418 0.0439384C8.25878 0.0163257 8.32832 0.00142741 8.39882 9.5129e-05C8.46931 -0.00123715 8.53938 0.0110214 8.60503 0.0361717C8.67069 0.061321 8.73063 0.09887 8.78144 0.146673L15.5306 6.49527C15.6 6.56059 15.6552 6.63892 15.6929 6.72559C15.7306 6.81225 15.75 6.90547 15.75 6.99965C15.75 7.09383 15.7306 7.18705 15.6929 7.27371C15.6552 7.36038 15.6 7.43871 15.5306 7.50402L8.78215 13.8533Z" fill="#3D332B"/>
</svg></button>
      </div>
    </div>

    <div class="carousel-section" data-items-per-view="">
      <div class="products-wrapper">
        <?php foreach ($tabs as $i => $t): ?>
          <?php
            // ahora le pasamos también la taxonomía
            $nl_render_products_track($t['id'], $t['taxonomy'], $section_id.'-track-'.$i, $i===0);
          ?>
        <?php endforeach; ?>
      </div>

      <!-- Dots (solo mobile) -->
      <div class="carousel-dots" id="<?php echo esc_attr($section_id); ?>-dots"></div>

      <a class="mobile-only view-more btn btn-outline-cafe" href="<?php echo esc_url($first_link); ?>">
        <span class="view-more__label">Ver stock de <?php echo esc_html($first_name); ?></span>
        <img class="cta-arrow" src="<?php echo esc_url($arrow_url); ?>" alt="" aria-hidden="true" />
      </a>
    </div>
  </div>

  <script>
(function(){
  var root = document.getElementById('<?php echo esc_js($section_id); ?>');
  if(!root) return;

  var tabs         = root.querySelectorAll('.tab');
  var tracks       = root.querySelectorAll('.products-track');
  var viewMore     = root.querySelector('.view-more');
  var viewMoreLabel= viewMore ? viewMore.querySelector('.view-more__label') : null;
  var currentLoc   = root.querySelector('.current-location');
  var prevBtn      = root.querySelector('.prev-btn');
  var nextBtn      = root.querySelector('.next-btn');
  var wrapper      = root.querySelector('.products-wrapper');
  var dotsWrap     = root.querySelector('#<?php echo esc_js($section_id); ?>-dots');

  var FADE_MS = 100;
  var fadeTimer = null;

  var tabMeta = <?php
    $export = array_map(function($t){ return ['name'=>$t['name'], 'link'=>$t['link']]; }, $tabs);
    echo wp_json_encode($export);
  ?>;

  var state = { tabIndex: 0, index: 0, itemsPerView: 4 };

  function isMobile(){ return window.matchMedia('(max-width: 768px)').matches; }

  function updateItemsPerView(){
    var w = window.innerWidth;
    if (w <= 480)        state.itemsPerView = 1;
    else if (w <= 768)   state.itemsPerView = 2;
    else if (w <= 1200)  state.itemsPerView = 3;
    else                 state.itemsPerView = 4;
  }

  function getActiveGrid(){
    var track = tracks[state.tabIndex];
    return track ? track.querySelector('.products-grid') : null;
  }

  function counts(){
    var grid = getActiveGrid();
    if(!grid) return { total:0, maxIndex:0, pages:1 };
    var total = grid.querySelectorAll('.product-card').length;
    var maxIndex = Math.max(0, total - state.itemsPerView);
    var pages = Math.max(1, Math.ceil(total / state.itemsPerView));
    return { total: total, maxIndex: maxIndex, pages: pages };
  }

  function pageFromIndex(){ return Math.floor(state.index / state.itemsPerView); }

  function clampIndex(i){
    var c = counts();
    return Math.max(0, Math.min(c.maxIndex, i));
  }

  function goToIndex(i, animate){
    state.index = clampIndex(i);
    updateCarousel(animate);
  }

  function pageToIndex(p){ return p * state.itemsPerView; }

  function buildDots(){
    if (!dotsWrap) return;
    var c = counts();
    dotsWrap.innerHTML = '';

    // Mostrar dots sólo en mobile; en desktop no estorban
    if (!isMobile()) return;

    for (var i=0; i<c.pages; i++){
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'carousel-dot';
      b.setAttribute('aria-label', 'Ir a página ' + (i+1));
      b.dataset.page = String(i);
      b.addEventListener('click', function(){
        var p = parseInt(this.dataset.page, 10) || 0;
        goToIndex(pageToIndex(p), true);
      });
      dotsWrap.appendChild(b);
    }
    updateDots();
  }

  function updateDots(){
    if (!dotsWrap) return;
    var dots = dotsWrap.querySelectorAll('.carousel-dot');
    var p = pageFromIndex();
    for (var i=0; i<dots.length; i++){
      if (i === p) dots[i].classList.add('is-active');
      else dots[i].classList.remove('is-active');
    }
  }

  function updateButtons(){
    if (!prevBtn || !nextBtn) return;
    // En mobile se ocultan por CSS; en desktop habilitamos según índice
    var c = counts();
    prevBtn.disabled = (state.index === 0);
    nextBtn.disabled = (state.index >= c.maxIndex);
  }

  function updateCarousel(animate){
    var grid = getActiveGrid();
    if(!grid) return;

    var card = grid.querySelector('.product-card');
    if(!card){
      state.index = 0;
      updateButtons();
      updateDots();
      return;
    }

    var style = window.getComputedStyle(grid);
    var gapPx = parseFloat(style.columnGap || style.gap || 0);
    var cardW = card.getBoundingClientRect().width;

    var x = -(state.index * (cardW + gapPx));
    grid.style.transition = animate ? 'transform .5s ease' : 'none';
    grid.style.transform  = 'translateX(' + x + 'px)';

    updateButtons();
    updateDots();
  }

  function applyTab(idx){
    tabs.forEach(function(t,i){
      t.classList.toggle('active', i===idx);
      t.setAttribute('aria-selected', i===idx ? 'true':'false');
    });
    tracks.forEach(function(tr, i){
      tr.classList.toggle('is-active', i===idx);
    });

    if (tabMeta[idx]) {
      currentLoc.textContent = tabMeta[idx].name;
      if (viewMore) {
        if (viewMoreLabel) viewMoreLabel.textContent = 'Ver stock de ' + tabMeta[idx].name;
        viewMore.setAttribute('href', tabMeta[idx].link);
      }
    }

    state.tabIndex = idx;
    state.index    = 0;
    updateItemsPerView();
    buildDots();
    updateCarousel(false);
  }

  function activateTab(idx, instant){
    if (idx === state.tabIndex && !instant) return;

    if (instant){
      if (fadeTimer) { clearTimeout(fadeTimer); fadeTimer = null; }
      if (wrapper) wrapper.classList.remove('is-fading');
      applyTab(idx);
      return;
    }

    if (fadeTimer) { clearTimeout(fadeTimer); fadeTimer = null; }
    if (wrapper) wrapper.classList.add('is-fading');

    fadeTimer = setTimeout(function(){
      applyTab(idx);
      requestAnimationFrame(function(){
        if (wrapper) wrapper.classList.remove('is-fading');
      });
    }, FADE_MS);
  }

  // Eventos
  tabs.forEach(function(tab, i){
    tab.addEventListener('click', function(){ activateTab(i, false); });
  });

  if (prevBtn) prevBtn.addEventListener('click', function(e){
    e.preventDefault();
    // Avanza una "página" completa en desktop
    goToIndex(state.index - state.itemsPerView, true);
  });

  if (nextBtn) nextBtn.addEventListener('click', function(e){
    e.preventDefault();
    goToIndex(state.index + state.itemsPerView, true);
  });

  window.addEventListener('resize', function(){
    var prevItems = state.itemsPerView;
    updateItemsPerView();

    // Re-clamp índice al nuevo max
    state.index = clampIndex(state.index);

    // Rehacer dots si cambió la cantidad por página o si pasamos a/desde mobile
    buildDots();
    updateCarousel(false);
  });

  // Init
  updateItemsPerView();
  activateTab(0, true);

  // Por si las imágenes cargan después y cambian el ancho de card
  window.addEventListener('load', function(){ updateCarousel(false); });
})();
</script>

</section>
