<?php
if ( ! function_exists('get_field') ) {
  echo '<p><em>ACF plugin required.</em></p>';
  return;
}

$section_id = 'nl-blog-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$classes    = 'nl-blog';
if (!empty($block['className'])) $classes .= ' ' . esc_attr($block['className']);
if (!empty($block['align']))     $classes .= ' align' . esc_attr($block['align']);

$bg        = get_field('background');
$bg_url    = (is_array($bg) && !empty($bg['url'])) ? esc_url($bg['url']) : '';
$post_q    = (string) get_field('post_q');
$cat_id    = (int) get_field('category');
$arrow_url = 'https://nalakalu.stag.host/wp-content/uploads/2025/10/arrow_forward.svg';

if (!function_exists('nalakalu_blog_posts_page_link')) {
  function nalakalu_blog_posts_page_link() {
    $page_for_posts = (int) get_option('page_for_posts');
    if ($page_for_posts) return get_permalink($page_for_posts);
    return home_url('/blog/');
  }
}
if (!function_exists('nalakalu_blog_smart_excerpt')) {
  function nalakalu_blog_smart_excerpt($post_id, $words = 22) {
    $ex = get_the_excerpt($post_id);
    if (!$ex) $ex = wp_strip_all_tags( get_post_field('post_content', $post_id) );
    return wp_trim_words($ex, $words, '…');
  }
}
if (!function_exists('nalakalu_blog_parse_ppp')) {
  function nalakalu_blog_parse_ppp($val, $fallback = 6) {
    $v = trim((string)$val);
    if ($v === '' ) return $fallback;
    if (strcasecmp($v, 'Todos') === 0 || strcasecmp($v, 'all') === 0) return -1;
    $n = (int) $v;
    return $n > 0 ? $n : $fallback;
  }
}

$ppp   = nalakalu_blog_parse_ppp($post_q, 6);
$args  = array(
  'post_type'      => 'post',
  'post_status'    => 'publish',
  'posts_per_page' => $ppp,
  'orderby'        => 'date',
  'order'          => 'DESC',
);
if ($cat_id) $args['cat'] = $cat_id;

$q = new WP_Query($args);
$has_posts = $q->have_posts();
$blog_link = nalakalu_blog_posts_page_link();

$has_bg = $bg_url ? ' has-bg' : '';
$style  = $bg_url ? '--nlb-bg: url('.$bg_url.');' : '';
?>

<div class="nlb-header">
  <h1 class="font-heading-2">NUESTRO BLOG</h1>
  <a class="desktop-only btn btn-outline-cafe" href="<?php echo esc_url($blog_link); ?>">Ir al Blog →</a>
</div>

<section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($classes.$has_bg); ?>" style="<?php echo esc_attr($style); ?>">
  <div class="nlb-inner">
    <div class="nlb-wrapper" data-drag-scope>
      <div class="nlb-track">
        <?php if ($has_posts): ?>
          <?php while ($q->have_posts()): $q->the_post();
            $pid    = get_the_ID();
            $ptitle = get_the_title();
            $plink  = get_permalink();
            $thumb  = get_the_post_thumbnail_url($pid, 'large');
            if (!$thumb) $thumb = get_the_post_thumbnail_url($pid, 'medium_large');
            if (!$thumb) $thumb = 'https://via.placeholder.com/800x500?text=Post';
            $excerpt = nalakalu_blog_smart_excerpt($pid, 22);
          ?>
            <article class="nlb-card">
              <img class="nlb-card__image" src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($ptitle); ?>" loading="lazy" decoding="async" draggable="false">
              <div class="nlb-card__content">
                <h2 class="font-heading-5"><?php echo esc_html($ptitle); ?></h2>
                <p class="font-body-medium-light"><?php echo esc_html($excerpt); ?></p>
                <a class="btn btn-small" href="<?php echo esc_url($plink); ?>">Leer más <img class="cta-arrow" src="<?php echo esc_url($arrow_url); ?>" alt="" aria-hidden="true" /></a>
              </div>
            </article>
          <?php endwhile; wp_reset_postdata(); ?>
        <?php else: ?>
          <?php if ( current_user_can('edit_posts') ): ?>
            <div style="opacity:.7;padding:1rem 0 3rem;">No hay entradas para mostrar con los filtros actuales.</div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

<div class="nlb-dots" id="<?php echo esc_attr($section_id); ?>-dots"></div>

    <a class="mobile-only btn btn-outline-blanco" href="<?php echo esc_url($blog_link); ?>">Ir al Blog </a>
  </div>

 <script>
(function(){
  var root    = document.getElementById('<?php echo esc_js($section_id); ?>');
  if(!root) return;

  var wrapper = root.querySelector('.nlb-wrapper');
  var track   = root.querySelector('.nlb-track');
  var dotsEl  = root.querySelector('#<?php echo esc_js($section_id); ?>-dots');
  if(!wrapper || !track || !dotsEl) return;

  var mql = window.matchMedia('(max-width: 768px)');

  // ---- utilidades comunes ----
  function cards(){ return track.querySelectorAll('.nlb-card'); }
  function gapPx(){ var cs=getComputedStyle(track); var g=parseFloat(cs.gap||cs.columnGap||0); return isNaN(g)?0:g; }
  function cardWidth(){ var c=cards()[0]; return c ? c.getBoundingClientRect().width : wrapper.clientWidth; }

  // ============================
  // DESKTOP: drag inercial (tu lógica original)
  // ============================
  var down=false, startX=0, startTX=0, vx=0, raf=0, dragged=false;
  function getTX(){ var m=(track.style.transform||'').match(/translateX\((-?\d+(?:\.\d+)?)px\)/); return m?parseFloat(m[1]):0; }
  function setTX(x){ track.style.transform='translateX('+x+'px)'; }
  function bounds(){ var wW=wrapper.clientWidth, tW=track.scrollWidth, min=Math.min(0, wW - tW); return {min:min,max:0}; }
  function clamp(x){ var b=bounds(); return Math.max(b.min, Math.min(b.max, x)); }
  function onDown(cx){ down=true; dragged=false; wrapper.classList.add('is-dragging'); track.style.transition='none'; startX=cx; startTX=getTX(); vx=0; cancelAnimationFrame(raf); }
  function onMove(cx){ if(!down) return; var dx=cx-startX; if(Math.abs(dx)>3) dragged=true; var next=clamp(startTX+dx); vx=next-getTX(); setTX(next); }
  function onUp(){ if(!down) return; down=false; wrapper.classList.remove('is-dragging'); momentum(); }
  function momentum(){
    var f=0.92, stop=0.45;
    (function step(){
      vx*=f;
      var next=clamp(getTX()+vx);
      var b=bounds(); if(next===b.min||next===b.max) vx*=0.5;
      setTX(next);
      if(Math.abs(vx)>stop){ raf=requestAnimationFrame(step);} else { track.style.transition='transform .25s ease'; }
    })();
  }

  // guardamos referencias para poder desuscribir
  var handlers = {
    mousemove: null, mouseup: null, touchmove: null, touchend: null, clickGuard: null,
    mousedown: null, touchstart: null
  };

  function enableDesktopDrag(){
    if (handlers.mousedown) return; // ya activo
    wrapper.style.cursor = 'grab';

    handlers.mousedown = function(e){ e.preventDefault(); onDown(e.clientX); };
    handlers.mousemove = function(e){ onMove(e.clientX); };
    handlers.mouseup   = function(){ onUp(); };
    handlers.touchstart= function(e){ var t=e.touches&&e.touches[0]; if(!t) return; onDown(t.clientX); };
    handlers.touchmove = function(e){ if(!down) return; var t=e.touches&&e.touches[0]; if(!t) return; onMove(t.clientX); };
    handlers.touchend  = function(){ onUp(); };
    handlers.clickGuard= function(e){ if(dragged){ e.preventDefault(); e.stopPropagation(); dragged=false; } };

    wrapper.addEventListener('mousedown', handlers.mousedown);
    window.addEventListener('mousemove', handlers.mousemove, {passive:false});
    window.addEventListener('mouseup', handlers.mouseup);
    wrapper.addEventListener('touchstart', handlers.touchstart, {passive:true});
    window.addEventListener('touchmove', handlers.touchmove, {passive:false});
    window.addEventListener('touchend', handlers.touchend);
    wrapper.addEventListener('click', handlers.clickGuard, true);
  }

  function disableDesktopDrag(){
    wrapper.style.cursor = 'default';
    down=false; wrapper.classList.remove('is-dragging');

    if (handlers.mousedown){ wrapper.removeEventListener('mousedown', handlers.mousedown); handlers.mousedown=null; }
    if (handlers.mousemove){ window.removeEventListener('mousemove', handlers.mousemove); handlers.mousemove=null; }
    if (handlers.mouseup){ window.removeEventListener('mouseup', handlers.mouseup); handlers.mouseup=null; }
    if (handlers.touchstart){ wrapper.removeEventListener('touchstart', handlers.touchstart); handlers.touchstart=null; }
    if (handlers.touchmove){ window.removeEventListener('touchmove', handlers.touchmove); handlers.touchmove=null; }
    if (handlers.touchend){ window.removeEventListener('touchend', handlers.touchend); handlers.touchend=null; }
    if (handlers.clickGuard){ wrapper.removeEventListener('click', handlers.clickGuard, true); handlers.clickGuard=null; }

    // clamp por si quedó desplazado
    setTX( clamp( getTX() ) );
  }

  // ============================
  // MOBILE: páginas + dots (sin drag)
  // ============================
  var pageIndex = 0;
  function totalPages(){ return Math.max(1, cards().length); } // 1+= muestra 1 dot si querés; si no, cambiá a (cards().length || 0)
  function goTo(p, animate){
    var total = totalPages();
    pageIndex = Math.max(0, Math.min(total-1, p));
    var x = -(pageIndex * (cardWidth() + gapPx()));
    track.style.transition = animate ? 'transform .35s cubic-bezier(.22,.61,.36,1)' : 'none';
    track.style.transform  = 'translateX('+x+'px)';
    updateDots();
  }
  function buildDots(){
    dotsEl.innerHTML = '';
    var total = totalPages();
    if (total <= 1) return; // si solo hay 1 post, no mostramos dots

    for (var i=0; i<total; i++){
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'nlb-dot';
      b.setAttribute('aria-label','Ir a post '+(i+1));
      b.dataset.page = String(i);
      b.addEventListener('click', function(){
        var p = parseInt(this.dataset.page, 10) || 0;
        goTo(p, true);
      });
      dotsEl.appendChild(b);
    }
    updateDots();
  }
  function updateDots(){
    var ds = dotsEl.querySelectorAll('.nlb-dot');
    for (var i=0;i<ds.length;i++){
      if (i === pageIndex) ds[i].classList.add('is-active');
      else ds[i].classList.remove('is-active');
    }
  }

  // ============================
  // MODO según breakpoint
  // ============================
  function applyMode(){
    if (mql.matches){
      // MOBILE
      disableDesktopDrag();
      dotsEl.style.display = 'flex';   // fuerza visibilidad por si algún CSS global molesta
      buildDots();
      goTo(pageIndex, false);
    } else {
      // DESKTOP
      dotsEl.innerHTML = '';
      dotsEl.style.display = '';       // lo controla CSS
      enableDesktopDrag();
    }
  }

  // init + resize/change
  applyMode();
  window.addEventListener('resize', applyMode);
  if (mql.addEventListener) mql.addEventListener('change', applyMode);
  else if (mql.addListener) mql.addListener(applyMode); // Safari viejito

  // Por si imágenes cambian el ancho real
  window.addEventListener('load', applyMode);
})();
</script>

</section>
