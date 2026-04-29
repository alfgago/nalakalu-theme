<?php
/* =========================================================
 *  RECOMENDACIONES (featured products carousel)
 * ======================================================= */
if ( ! function_exists('wc_get_featured_product_ids') ) return;

$featured_ids = array_filter( (array) wc_get_featured_product_ids(), 'is_numeric' );
$featured_ids = array_diff( $featured_ids, [ get_the_ID() ] );

if ( empty($featured_ids) ) {
  $fallback = new WP_Query([
    'post_type'=>'product','post_status'=>'publish',
    'meta_query'=>[[ 'key' => '_featured', 'value' => 'yes' ]],
    'posts_per_page'=>12,'fields'=>'ids',
  ]);
  $featured_ids = $fallback->posts ?: [];
  wp_reset_postdata();
}
if (!empty($featured_ids)):
  $section_id = 'recs-' . uniqid();
?>
<section id="<?php echo esc_attr($section_id); ?>" class="pcarousel">
  <div class="container">
    <div class="title-row">
      <h1 class="detrasde-title">Recomendaciones</h1>
      <div class="nav-buttons">
        <button class="carousel-btn prev-btn" aria-label="Anterior">←</button>
        <button class="carousel-btn next-btn" aria-label="Siguiente">→</button>
      </div>
    </div>

    <div class="carousel-section" data-items-per-view="">
      <div class="products-wrapper">
        <div class="products-track is-active" data-track="<?php echo esc_attr($section_id); ?>-track">
          <div class="products-grid" id="<?php echo esc_attr($section_id); ?>-track">
            <?php
            $q = new WP_Query([
              'post_type'=>'product','post_status'=>'publish',
              'post__in'=>$featured_ids,'orderby'=>'post__in','posts_per_page'=>12,
            ]);
            if ( $q->have_posts() ) :
              while ( $q->have_posts() ) : $q->the_post();
                $pid    = get_the_ID();
                $plink  = get_permalink($pid);
                $pname  = get_the_title($pid);
                $thumb  = get_the_post_thumbnail_url($pid, 'woocommerce_thumbnail');
                if (!$thumb && function_exists('wc_placeholder_img_src')) $thumb = wc_placeholder_img_src();
                $price_html = '';
                if ( function_exists('wc_get_product') ) { $p = wc_get_product($pid); if ($p) $price_html = $p->get_price_html(); }
                ?>
                <a class="product-card" href="<?php echo esc_url($plink); ?>">
                  <div class="product-image">
                    <?php if ($thumb): ?><img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($pname); ?>" loading="lazy" decoding="async"><?php endif; ?>
                  </div>
                  <div class="product-info">
                    <div class="product-name"><div class="font-button"><?php echo esc_html($pname); ?></div></div>
                    <div class="font-overline"><?php echo wp_kses_post($price_html); ?></div>
                  </div>
                </a>
              <?php endwhile; wp_reset_postdata(); endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

 <script>
(function(){
  var root = document.getElementById('<?php echo esc_js($section_id); ?>');
  if(!root) return;

  var prevBtn  = root.querySelector('.prev-btn');
  var nextBtn  = root.querySelector('.next-btn');
  var wrapper  = root.querySelector('.products-wrapper');
  var grid     = root.querySelector('.products-grid');
  if(!wrapper || !grid) return;

  var cards = Array.from(grid.querySelectorAll('.product-card'));
  if(cards.length < 2) return;

  var mqMobile = window.matchMedia('(max-width: 768px)');
  var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // =========================
  // DESKTOP (tu lógica original)
  // =========================
  var state = { index: 0, perView: 4 };

  function updatePerViewDesktop(){
    var w = window.innerWidth;
    if (w <= 1200) state.perView = 3;
    else state.perView = 4;
  }

  function counts(){
    var total = cards.length;
    var maxIndex = Math.max(0, total - state.perView);
    return { total: total, maxIndex: maxIndex };
  }

  function clamp(i){
    var c = counts();
    return Math.max(0, Math.min(c.maxIndex, i));
  }

  function updateButtonsDesktop(){
    var c = counts();
    if (prevBtn) prevBtn.disabled = (state.index === 0);
    if (nextBtn) nextBtn.disabled = (state.index >= c.maxIndex);
  }

  function updateCarouselDesktop(animate){
    if (!cards.length) return;
    var card = cards[0];
    var style = window.getComputedStyle(grid);
    var gapPx = parseFloat(style.columnGap || style.gap || 0);
    var cardW = card.getBoundingClientRect().width;
    var x = -(state.index * (cardW + gapPx));
    grid.style.transition = animate ? 'transform .5s ease' : 'none';
    grid.style.transform  = 'translateX(' + x + 'px)';
    updateButtonsDesktop();
  }

  function goDesktop(delta){
    state.index = clamp(state.index + delta);
    updateCarouselDesktop(true);
  }

  if (prevBtn) prevBtn.addEventListener('click', function(e){
    e.preventDefault();
    if (mqMobile.matches) return;
    goDesktop(-state.perView);
  });

  if (nextBtn) nextBtn.addEventListener('click', function(e){
    e.preventDefault();
    if (mqMobile.matches) return;
    goDesktop(+state.perView);
  });

  // =========================
  // MOBILE (scroll-snap + dots)
  // =========================
  var dotsWrap = null;
  var dots = [];
  var raf = null;

  function ensureDots(){
    if (dotsWrap) return;

    dotsWrap = document.createElement('div');
    dotsWrap.className = 'recs-dots';
    dotsWrap.setAttribute('role','tablist');
    dotsWrap.setAttribute('aria-label','Navegación del carrusel');

    // lo ponemos debajo del wrapper
    wrapper.parentNode.insertBefore(dotsWrap, wrapper.nextSibling);

    dots = cards.map(function(card, i){
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'recs-dot' + (i===0 ? ' active' : '');
      b.setAttribute('aria-label', 'Ir al producto ' + (i+1));
      b.setAttribute('aria-current', i===0 ? 'true' : 'false');
      b.addEventListener('click', function(){
        wrapper.scrollTo({
          left: card.offsetLeft,
          behavior: prefersReduced ? 'auto' : 'smooth'
        });
      });
      dotsWrap.appendChild(b);
      return b;
    });
  }

  function setActiveDot(idx){
    dots.forEach(function(d, i){
      var active = i === idx;
      d.classList.toggle('active', active);
      d.setAttribute('aria-current', active ? 'true' : 'false');
    });
  }

  function onScrollMobile(){
    if (raf) cancelAnimationFrame(raf);
    raf = requestAnimationFrame(function(){
      var center = wrapper.scrollLeft + wrapper.clientWidth / 2;
      var best = 0, bestDist = Infinity;

      for (var i=0;i<cards.length;i++){
        var c = cards[i];
        var cCenter = c.offsetLeft + c.clientWidth / 2;
        var dist = Math.abs(center - cCenter);
        if (dist < bestDist){ bestDist = dist; best = i; }
      }
      setActiveDot(best);
    });
  }

  function enterMobile(){
    // aseguramos que el transform de desktop no moleste
    grid.style.transform = 'none';
    grid.style.transition = 'none';

    ensureDots();
    wrapper.addEventListener('scroll', onScrollMobile, { passive:true });
    onScrollMobile();
  }

  function exitMobile(){
    wrapper.removeEventListener('scroll', onScrollMobile);
    if (dotsWrap){
      dotsWrap.remove();
      dotsWrap = null;
      dots = [];
    }
  }

  // =========================
  // Switch según breakpoint
  // =========================
  function apply(){
    if (mqMobile.matches){
      enterMobile();
      // por las dudas, botones no-interactivos
      if (prevBtn) prevBtn.disabled = true;
      if (nextBtn) nextBtn.disabled = true;
    } else {
      exitMobile();
      updatePerViewDesktop();
      state.index = clamp(state.index);
      updateCarouselDesktop(false);
    }
  }

  apply();
  window.addEventListener('resize', function(){
    apply();
  });
  window.addEventListener('load', function(){
    apply();
  });
})();
</script>



</section>
<?php endif; ?>

<?php if (function_exists('nl_render_lookbook_relocated')): ?>
  <!-- LOOKBOOK full-width reubicado -->
  <section class="nl-lookbook-fw" aria-labelledby="nl-lookbook-heading">
    <div class="nl-lookbook-header">
      <h2 id="detrasde-title" class="detrasde-title">Armoniza con</h2>
    </div>
    <div class="nl-lookbook-body">
      <?php nl_render_lookbook_relocated([
        'debug'    => false, // true para ver panel
        'relocate' => true,  // evita duplicado en hook original
      ]); ?>
    </div>
  </section>
<?php endif; ?>