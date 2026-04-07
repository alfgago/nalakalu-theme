<?php if ( current_user_can('manage_options') ) : ?>
  <!-- DEBUG: ESTAS EN taxonomy-coleccion.php -->
<?php endif; ?>


<?php
/**
 * Template para la taxonomía "coleccion"
 */

defined('ABSPATH') || exit;

get_header();

// ========= Datos del término actual =========
$term = get_queried_object();
if ( ! $term || is_wp_error( $term ) ) {
    $term_name = '';
    $bg_field  = null;
} else {
    $term_name = $term->name;
    // Campo ACF en la taxonomía: background_image
    $bg_field  = get_field( 'background_image', $term );
    // Fallback por si ACF lo guarda como "coleccion_{id}"
    if ( ! $bg_field ) {
        $bg_field = get_field( 'background_image', $term->taxonomy . '_' . $term->term_id );
    }
}

// Helper para sacar URL de imagen (evitamos redeclare)
if ( ! function_exists( 'nlk_coleccion_hero_img_url' ) ) {
    function nlk_coleccion_hero_img_url( $img, $size = 'full' ) {
        if ( is_array( $img ) ) {
            if ( ! empty( $img['url'] ) ) {
                return esc_url( $img['url'] );
            }
            if ( ! empty( $img['ID'] ) ) {
                $src = wp_get_attachment_image_src( (int) $img['ID'], $size );
                if ( $src && ! empty( $src[0] ) ) {
                    return esc_url( $src[0] );
                }
            }
        } elseif ( is_numeric( $img ) ) {
            $src = wp_get_attachment_image_src( (int) $img, $size );
            if ( $src && ! empty( $src[0] ) ) {
                return esc_url( $src[0] );
            }
        } elseif ( is_string( $img ) ) {
            return esc_url( $img );
        }
        return '';
    }
}

$bg_url = nlk_coleccion_hero_img_url( $bg_field );

// ID y clases del hero
$section_id = 'coleccion-hero-' . ( $term ? $term->term_id : uniqid() );
$classes    = 'nl-coleccion-hero';

// Style inline para gradiente + imagen
$bg_style = '';
if ( $bg_url ) {
    $bg_style = "background-image: linear-gradient(to bottom,
        rgba(60, 50, 40, 0.3) 0%,
        rgba(40, 35, 30, 0.4) 50%,
        rgba(30, 25, 20, 0.5) 100%
      ), url('" . esc_url( $bg_url ) . "');";
}
?>

<main id="primary" class="site-main">

  <!-- HERO DE COLECCIÓN -->
  <section id="<?php echo esc_attr( $section_id ); ?>" class="<?php echo esc_attr( $classes ); ?>">
    <div class="nl-coleccion-hero__background"<?php if ( $bg_style ) : ?> style="<?php echo esc_attr( $bg_style ); ?>"<?php endif; ?>></div>

    <div class="nl-coleccion-hero__content">
      <span class="font-heading-2 text-white">Colección</span>

      <?php if ( $term_name ) : ?>
        <h1 class="font-heading-display text-blanco-hueso">
          <?php echo esc_html( $term_name ); ?>
        </h1>
      <?php endif; ?>
    </div>
  </section>
  
  
<?php
// =============================
// Productos de la colección
// =============================
$coleccion_term = get_queried_object();

if ( $coleccion_term instanceof WP_Term ) {

  // Traemos SOLO productos que pertenecen a esta colección
  $nlc_args = [
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'tax_query'      => [
      [
        'taxonomy' => 'coleccion',
        'field'    => 'term_id',
        'terms'    => $coleccion_term->term_id,
      ],
    ],
    'orderby'        => 'date',
    'order'          => 'DESC',
  ];

  $nlc_query    = new WP_Query( $nlc_args );
  $nlc_products = [];
  $nlc_cats_map = []; // slug => [ 'name'=>..., 'slug'=>... ]
  $nlc_cat_ids  = []; // term_id => WP_Term

  if ( $nlc_query->have_posts() ) {
    while ( $nlc_query->have_posts() ) {
      $nlc_query->the_post();
      $pid   = get_the_ID();
      $plink = get_permalink( $pid );
      $pname = get_the_title( $pid );

      // Imagen
      $thumb = get_the_post_thumbnail_url( $pid, 'woocommerce_thumbnail' );
      if ( ! $thumb && function_exists( 'wc_placeholder_img_src' ) ) {
        $thumb = wc_placeholder_img_src( 'woocommerce_thumbnail' );
      }

      // Precio
      $price_html = '';
      if ( function_exists( 'wc_get_product' ) ) {
        $product_obj = wc_get_product( $pid );
        if ( $product_obj ) {
          $price_html = $product_obj->get_price_html();
        }
      }

      // Categorías de producto (product_cat) SOLO de estos productos
      $cats      = get_the_terms( $pid, 'product_cat' );
      $cat_slugs = [];

      if ( $cats && ! is_wp_error( $cats ) ) {
        foreach ( $cats as $c ) {
          // ⛔️ blacklist: no sumar uncategorized / sin-categoria
          $blacklist_slugs = [ 'uncategorized', 'sin-categoria' ];
          if ( in_array( $c->slug, $blacklist_slugs, true ) ) {
            continue;
          }

          $cat_slugs[] = $c->slug;

          // Guardamos este término en un map global para tabs (solo categorías de esta colección)
          if ( ! isset( $nlc_cat_ids[ $c->term_id ] ) ) {
            $nlc_cat_ids[ $c->term_id ] = $c;
          }
        }
      }

      $nlc_products[] = [
        'id'         => $pid,
        'link'       => $plink,
        'name'       => $pname,
        'thumb'      => $thumb,
        'price_html' => $price_html,
        'cat_slugs'  => $cat_slugs,
      ];
    }
    wp_reset_postdata();
  }

  // Armamos el map de categorías SOLO con las que tienen productos en esta colección
  if ( ! empty( $nlc_cat_ids ) ) {
    foreach ( $nlc_cat_ids as $term_id => $term_obj ) {
      if ( $term_obj instanceof WP_Term && ! is_wp_error( $term_obj ) ) {

        // ⛔️ blacklist acá también, por las dudas
        $blacklist_slugs = [ 'uncategorized', 'sin-categoria' ];
        if ( in_array( $term_obj->slug, $blacklist_slugs, true ) ) {
          continue;
        }

        $nlc_cats_map[ $term_obj->slug ] = [
          'slug' => $term_obj->slug,
          'name' => $term_obj->name,
        ];
      }
    }
  }

  // Si no hay productos, no mostramos sección
  if ( ! empty( $nlc_products ) ) :

    // Ordenamos categorías por nombre
    if ( ! empty( $nlc_cats_map ) ) {
      uasort( $nlc_cats_map, function( $a, $b ) {
        return strcasecmp( $a['name'], $b['name'] );
      } );
    }

    $sec_id = 'nl-coleccion-products-' . $coleccion_term->term_id;
    ?>
    <section id="<?php echo esc_attr( $sec_id ); ?>" class="nl-coleccion-products">
      <div class="nl-coleccion-products__inner">
        <div class="nl-coleccion-products__header">
          <h1 class="font-heading-2">
            <?php esc_html_e( 'Explora la colección', 'nalakalu' ); ?>
          </h1>

          <div class="nl-coleccion-products__tabs font-overline" role="tablist" aria-label="<?php esc_attr_e( 'Categorías de productos', 'nalakalu' ); ?>">
            <!-- Tab "Todos" -->
            <button
              type="button"
              class="nl-coleccion-products__tab is-active"
              data-cat="all"
              role="tab"
              aria-selected="true">
              <?php esc_html_e( 'TODOS', 'nalakalu' ); ?>
            </button>

            <?php if ( ! empty( $nlc_cats_map ) ) : ?>
              <?php foreach ( $nlc_cats_map as $cat ) : ?>
                <button
                  type="button"
                  class="nl-coleccion-products__tab"
                  data-cat="<?php echo esc_attr( $cat['slug'] ); ?>"
                  role="tab"
                  aria-selected="false">
                  <?php echo esc_html( mb_strtoupper( $cat['name'], 'UTF-8' ) ); ?>
                </button>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="nl-coleccion-products__grid-wrapper">
          <div class="nl-coleccion-products__grid">
            <?php foreach ( $nlc_products as $index => $p ) : ?>
              <?php
              $data_cats = ! empty( $p['cat_slugs'] )
                ? implode( ' ', array_map( 'sanitize_html_class', $p['cat_slugs'] ) )
                : '';
              ?>
              <a
                href="<?php echo esc_url( $p['link'] ); ?>"
                class="nl-coleccion-products__card"
                data-cats="<?php echo esc_attr( $data_cats ); ?>">
                <div class="nl-coleccion-products__image">
                  <?php if ( $p['thumb'] ) : ?>
                    <img
                      src="<?php echo esc_url( $p['thumb'] ); ?>"
                      alt="<?php echo esc_attr( $p['name'] ); ?>"
                      loading="lazy"
                      decoding="async">
                  <?php endif; ?>
                </div>
                <div class="nl-coleccion-products__info">
                  <div class="nl-coleccion-products__details">
                    <div class="nl-coleccion-products__name">
                      <?php echo esc_html( $p['name'] ); ?>
                    </div>
                  </div>

                  <?php if ( $p['price_html'] ) : ?>
                    <div class="nl-coleccion-products__price">
                      <?php echo wp_kses_post( $p['price_html'] ); ?>
                    </div>
                  <?php endif; ?>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <script>
      (function(){
        var root = document.getElementById('<?php echo esc_js( $sec_id ); ?>');
        if(!root) return;

        var tabs  = root.querySelectorAll('.nl-coleccion-products__tab');
        var cards = root.querySelectorAll('.nl-coleccion-products__card');

        function setActiveTab(tab){
          tabs.forEach(function(t){
            t.classList.remove('is-active');
            t.setAttribute('aria-selected','false');
          });
          tab.classList.add('is-active');
          tab.setAttribute('aria-selected','true');
        }

        function filterCards(catSlug){
          cards.forEach(function(card){
            var catsAttr = card.getAttribute('data-cats') || '';
            var cats     = catsAttr.split(/\s+/).filter(Boolean);

            var show = (catSlug === 'all');
            if (!show && cats.length){
              show = cats.indexOf(catSlug) !== -1;
            }

            card.style.display = show ? '' : 'none';
          });
        }

        tabs.forEach(function(tab){
          tab.addEventListener('click', function(){
            var cat = tab.getAttribute('data-cat') || 'all';
            setActiveTab(tab);
            filterCards(cat);
          });
        });

        filterCards('all');
      })();
      </script>
      <script>
(function () {
  var mq = window.matchMedia("(max-width: 768px)");

  function safeAddMqListener(mql, fn){
    if (mql.addEventListener) mql.addEventListener("change", fn);
    else if (mql.addListener) mql.addListener(fn);
  }

  function initMobileCarousel(block){
    if (!block || block.__taxMobileInit) return;

    var wrapper = block.querySelector(".tax_lanz_carousel-wrapper");
    var track   = block.querySelector(".tax_lanz_carousel-container");
    var items   = track ? track.querySelectorAll(".tax_lanz_carousel-item") : null;
    var nav     = block.querySelector(".tax_lanz_nav-buttons");

    if (!wrapper || !track || !items || !items.length || !nav) return;

    block.__taxMobileInit = true;

    // Guardar ubicación original del nav para restaurar en desktop
    if (!nav.__taxStored) {
      nav.__taxStored = true;
      nav.__taxOrigParent = nav.parentNode;
      nav.__taxOrigNext = nav.nextSibling;
    }

    function placeNavMobile(){
      // pone las flechas debajo del carrusel
      if (nav.parentNode !== wrapper.parentNode || nav.previousElementSibling !== wrapper) {
        wrapper.insertAdjacentElement("afterend", nav);
      }
    }

    function restoreNavDesktop(){
      var p = nav.__taxOrigParent;
      if (!p) return;

      // si sigue existiendo el nextSibling original, lo insertamos antes
      if (nav.__taxOrigNext && nav.__taxOrigNext.parentNode === p) {
        p.insertBefore(nav, nav.__taxOrigNext);
      } else {
        p.appendChild(nav);
      }

      // sacamos el transform inline (no pisamos lógica desktop)
      track.style.transform = "";
    }

    var current = 0;

    function getSlideW(){
      return wrapper.getBoundingClientRect().width || wrapper.clientWidth || 0;
    }

    function clamp(n, min, max){
      return Math.max(min, Math.min(max, n));
    }

    function goTo(i){
      i = clamp(i, 0, items.length - 1);
      current = i;

      var w = getSlideW();
      track.style.transform = "translate3d(" + (-current * w) + "px, 0, 0)";

      updateBtns();
    }

    function updateBtns(){
      var btns = nav.querySelectorAll(".tax_lanz_nav-btn");
      if (btns.length >= 1) btns[0].disabled = (current === 0);
      if (btns.length >= 2) btns[1].disabled = (current === items.length - 1);
    }

    function bindBtns(){
      var btns = nav.querySelectorAll(".tax_lanz_nav-btn");
      if (!btns.length) return;

      // Detecta por data-dir="prev|next" si existe, si no asume 1ro prev / 2do next
      var prev = null, next = null;

      for (var k = 0; k < btns.length; k++){
        var d = (btns[k].getAttribute("data-dir") || "").toLowerCase();
        if (d === "prev") prev = btns[k];
        if (d === "next") next = btns[k];
      }
      if (!prev && btns.length >= 1) prev = btns[0];
      if (!next && btns.length >= 2) next = btns[1];

      if (prev && !prev.__taxBound){
        prev.__taxBound = true;
        prev.addEventListener("click", function(e){
          e.preventDefault();
          goTo(current - 1);
        });
      }

      if (next && !next.__taxBound){
        next.__taxBound = true;
        next.addEventListener("click", function(e){
          e.preventDefault();
          goTo(current + 1);
        });
      }
    }

    function bindSwipe(){
      var startX = 0, startY = 0, active = false;

      wrapper.addEventListener("touchstart", function(e){
        if (!mq.matches) return;
        if (!e.touches || e.touches.length !== 1) return;
        active = true;
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
      }, {passive:true});

      wrapper.addEventListener("touchend", function(e){
        if (!mq.matches) return;
        if (!active) return;
        active = false;

        var t = (e.changedTouches && e.changedTouches[0]) ? e.changedTouches[0] : null;
        if (!t) return;

        var dx = t.clientX - startX;
        var dy = t.clientY - startY;

        // evita interferir con scroll vertical
        if (Math.abs(dx) < 35) return;
        if (Math.abs(dx) < Math.abs(dy)) return;

        if (dx < 0) goTo(current + 1);
        else goTo(current - 1);
      }, {passive:true});
    }

    function onMode(){
      if (mq.matches){
        placeNavMobile();
        bindBtns();
        goTo(current);
      } else {
        restoreNavDesktop();
      }
    }

    window.addEventListener("resize", function(){
      if (!mq.matches) return;
      goTo(current);
    });

    bindSwipe();
    safeAddMqListener(mq, onMode);
    onMode();
  }

  function boot(){
    var blocks = document.querySelectorAll(".tax_lanz_block");
    for (var i = 0; i < blocks.length; i++){
      initMobileCarousel(blocks[i]);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
</script>

    </section>
    <?php
  endif; 
} 
?>

<?php
/**
 * Sección: Gallery Taxonomy (sin hero)
 * Campos ACF (asociados al término de la taxonomía "coleccion"):
 * - background1 (image)
 * - title1 (text)
 * - description1 (textarea)
 * - background2, title2, description2
 * - background3, title3, description3
 */

if ( ! function_exists('get_field') ) {
    // ACF no está activo, no hacemos nada.
    echo '<p><em>ACF plugin required.</em></p>';
} else {

    $term = get_queried_object();
    if ( ! ( $term instanceof WP_Term ) ) {
        $term = null;
    }

    /**
     * Helper para obtener URL de imagen
     */
    if ( ! function_exists('nl_tax_gallery_img_url') ) {
        function nl_tax_gallery_img_url( $img, $size = 'large' ) {
            if (is_array($img)) {
                if (!empty($img['sizes'][$size])) return esc_url($img['sizes'][$size]);
                if (!empty($img['url']))          return esc_url($img['url']);
            } elseif (is_numeric($img)) {
                $src = wp_get_attachment_image_src((int) $img, $size);
                if ($src && !empty($src[0]))      return esc_url($src[0]);
            } elseif (is_string($img) && filter_var($img, FILTER_VALIDATE_URL)) {
                return esc_url($img);
            }
            return '';
        }
    }

    // ID y clases base (únicas para esta sección)
    $section_id = 'tax-gallery-' . ( $term ? $term->term_id : uniqid() );
    $classes    = 'tax_gallery_block';

    // Armo las 3 secciones
    $sections = [];
    $has_any  = false;

    if ( $term ) {
        $term_key = $term->taxonomy . '_' . $term->term_id; // por si usás este formato en ACF

        for ($i = 1; $i <= 3; $i++) {
            // Intentamos primero con el objeto término, luego con "coleccion_{id}"
            $bg_raw = get_field("background{$i}", $term);
            if ( ! $bg_raw ) {
                $bg_raw = get_field("background{$i}", $term_key);
            }

            $t = (string) get_field("title{$i}", $term);
            if ( $t === '' ) {
                $t = (string) get_field("title{$i}", $term_key);
            }

            $d = (string) get_field("description{$i}", $term);
            if ( $d === '' ) {
                $d = (string) get_field("description{$i}", $term_key);
            }

            $bg_url = nl_tax_gallery_img_url($bg_raw, 'large') ?: nl_tax_gallery_img_url($bg_raw, 'full');

            $t = trim($t);
            $d = trim($d);

            $sections[$i] = [
                'bg' => $bg_url,
                't'  => $t,
                'd'  => $d,
            ];

            if ( $bg_url !== '' || $t !== '' || $d !== '' ) {
                $has_any = true;
            }
        }
    }

    if ( $has_any ) : ?>
        <section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($classes); ?>">
            <?php for ($i = 1; $i <= 3; $i++):
                $bg = $sections[$i]['bg'] ?? '';
                $t  = $sections[$i]['t'] ?? '';
                $d  = $sections[$i]['d'] ?? '';

                if ($bg === '' && $t === '' && $d === '') continue;
            ?>
                <div class="tax_gallery_section tax_gallery_section-<?php echo (int) $i; ?>">
                    <!-- Imagen de fondo STICKY -->
                    <div class="tax_gallery_section-background"
                        <?php if ($bg): ?>style="background-image:url('<?php echo esc_url($bg); ?>');"<?php endif; ?>>
                    </div>

                    <div class="tax_gallery_content-wrapper">
                        <div class="tax_gallery_content-column">
                            <div class="tax_gallery_content-box">
                                <?php if ($t): ?>
                                    <h2 class="font-overline text-white">
                                        <?php echo esc_html($t); ?>
                                    </h2>
                                <?php endif; ?>

                                <?php if ($d): ?>
                                    <p class="font-body-medium-light text-white">
                                        <?php echo wp_kses_post(nl2br($d)); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>
        </section>
    <?php
    endif; // $has_any
} // endif get_field
?>

<?php
// =============================
// Sección: Lanzamiento (carousel)
// Campos ACF en la taxonomía "coleccion":
//  - Grupo: lanzamiento
//      - title_lanzamiento (text)
//      - descripcionlanzamiento (textarea)
//      - imagen1_lanzamiento ... imagen6_lanzamiento (image)
// =============================

if ( function_exists('get_field') ) {

    // Reutilizamos $term si ya existe, o lo obtenemos de nuevo
    $lanz_term = ( isset($term) && $term instanceof WP_Term )
        ? $term
        : get_queried_object();

    if ( $lanz_term instanceof WP_Term ) {

        $term_key = $lanz_term->taxonomy . '_' . $lanz_term->term_id;

        // Leemos el GROUP "lanzamiento" desde el término
        $lanz = get_field('lanzamiento', $lanz_term);
        if ( ! $lanz ) {
            $lanz = get_field('lanzamiento', $term_key);
        }

        if ( is_array($lanz) ) {

            $title = isset($lanz['title_lanzamiento'])
                ? trim((string) $lanz['title_lanzamiento'])
                : '';

            $desc  = isset($lanz['descripcionlanzamiento'])
                ? trim((string) $lanz['descripcionlanzamiento'])
                : '';

            // Helper para imágenes
            if ( ! function_exists('tax_lanz_img_url') ) {
                function tax_lanz_img_url( $img, $size = 'large' ) {
                    if ( is_array($img) ) {
                        if ( ! empty($img['sizes'][$size]) ) return esc_url($img['sizes'][$size]);
                        if ( ! empty($img['url']) )         return esc_url($img['url']);
                    } elseif ( is_numeric($img) ) {
                        $src = wp_get_attachment_image_src((int) $img, $size);
                        if ( $src && ! empty($src[0]) )      return esc_url($src[0]);
                    } elseif ( is_string($img) && filter_var($img, FILTER_VALIDATE_URL) ) {
                        return esc_url($img);
                    }
                    return '';
                }
            }

            // Imágenes del carrusel (1..6)
            $images = [];
            for ($i = 1; $i <= 6; $i++) {
                $key = "imagen{$i}_lanzamiento";
                if ( ! empty($lanz[$key]) ) {
                    $url = tax_lanz_img_url($lanz[$key], 'large') ?: tax_lanz_img_url($lanz[$key], 'full');
                    if ( $url ) {
                        $images[] = $url;
                    }
                }
            }

            // Si no hay nada en ningún campo, no mostramos la sección
            if ( $title !== '' || $desc !== '' || ! empty($images) ) :

                $sec_id       = 'tax-lanzamiento-' . $lanz_term->term_id;
                $carousel_id  = $sec_id . '-carousel';
                $prev_id      = $sec_id . '-prev';
                $next_id      = $sec_id . '-next';
                ?>
                
                <section id="<?php echo esc_attr($sec_id); ?>" class="tax_lanz_block">
                    <div class="tax_lanz_container">
                        <div class="tax_lanz_header">
                            <?php if ( $title ) : ?>
                                <div class="tax_lanz_title-section">
                                    <h1 class="tax_lanz_title font-heading-1">
                                        <?php echo esc_html( $title ); ?>
                                    </h1>
                                </div>
                            <?php endif; ?>

                            <?php if ( $desc || count($images) > 1 ) : ?>
                                <div class="tax_lanz_description-section">
                                    <?php if ( $desc ) : ?>
                                        <p class="tax_lanz_description font-body-small">
                                            <?php echo wp_kses_post( nl2br( $desc ) ); ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if ( count($images) > 1 ) : ?>
                                        <div class="tax_lanz_nav-buttons">
                                            <button
                                                class="tax_lanz_nav-btn"
                                                id="<?php echo esc_attr($prev_id); ?>"
                                                type="button"
                                                aria-label="<?php esc_attr_e('Anterior', 'nalakalu'); ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none">
  <path d="M6.96785 13.8533C7.01866 13.9011 7.0786 13.9387 7.14425 13.9638C7.2099 13.989 7.27997 14.0012 7.35047 13.9999C7.49284 13.9972 7.62829 13.9393 7.72701 13.839C7.82574 13.7386 7.87966 13.604 7.8769 13.4648C7.87415 13.3256 7.81496 13.1931 7.71234 13.0966L1.78861 7.52432L15.2131 7.52432C15.3555 7.52432 15.4921 7.46901 15.5927 7.37055C15.6934 7.27209 15.75 7.13854 15.75 6.9993C15.75 6.86005 15.6934 6.72651 15.5927 6.62805C15.4921 6.52959 15.3555 6.47427 15.2131 6.47427L1.79004 6.47427L7.71305 0.90341C7.76387 0.855607 7.80455 0.798484 7.83279 0.735305C7.86102 0.672125 7.87626 0.604126 7.87762 0.535189C7.87898 0.466252 7.86645 0.397728 7.84073 0.333528C7.81501 0.26933 7.77661 0.210712 7.72773 0.161024C7.67885 0.111336 7.62043 0.0715501 7.55582 0.0439384C7.49122 0.0163257 7.42168 0.00142741 7.35118 9.5129e-05C7.28069 -0.00123715 7.21062 0.0110214 7.14497 0.0361717C7.07931 0.061321 7.01937 0.09887 6.96856 0.146673L0.219443 6.49527C0.15005 6.56059 0.0948477 6.63892 0.0571413 6.72559C0.019435 6.81225 0 6.90547 0 6.99965C0 7.09383 0.019435 7.18705 0.0571413 7.27371C0.0948477 7.36038 0.15005 7.43871 0.219443 7.50402L6.96785 13.8533Z" fill="#3D332B"/>
</svg>
                                            </button>
                                            <button
                                                class="tax_lanz_nav-btn"
                                                id="<?php echo esc_attr($next_id); ?>"
                                                type="button"
                                                aria-label="<?php esc_attr_e('Siguiente', 'nalakalu'); ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none">
  <path d="M8.78215 13.8533C8.73134 13.9011 8.6714 13.9387 8.60575 13.9638C8.5401 13.989 8.47003 14.0012 8.39953 13.9999C8.25716 13.9972 8.12171 13.9393 8.02299 13.839C7.92426 13.7386 7.87034 13.604 7.8731 13.4648C7.87585 13.3256 7.93504 13.1931 8.03766 13.0966L13.9614 7.52432L0.536892 7.52432C0.394499 7.52432 0.257937 7.46901 0.15725 7.37055C0.0565633 7.27209 -1.93715e-06 7.13854 -1.93715e-06 6.9993C-1.93715e-06 6.86005 0.0565633 6.72651 0.15725 6.62805C0.257937 6.52959 0.394499 6.47427 0.536892 6.47427L13.96 6.47427L8.03695 0.90341C7.98613 0.855607 7.94545 0.798484 7.91721 0.735305C7.88898 0.672125 7.87374 0.604126 7.87238 0.535189C7.87102 0.466252 7.88355 0.397728 7.90927 0.333528C7.93499 0.26933 7.97339 0.210712 8.02227 0.161024C8.07115 0.111336 8.12957 0.0715501 8.19418 0.0439384C8.25878 0.0163257 8.32832 0.00142741 8.39882 9.5129e-05C8.46931 -0.00123715 8.53938 0.0110214 8.60503 0.0361717C8.67069 0.061321 8.73063 0.09887 8.78144 0.146673L15.5306 6.49527C15.6 6.56059 15.6552 6.63892 15.6929 6.72559C15.7306 6.81225 15.75 6.90547 15.75 6.99965C15.75 7.09383 15.7306 7.18705 15.6929 7.27371C15.6552 7.36038 15.6 7.43871 15.5306 7.50402L8.78215 13.8533Z" fill="#3D332B"/>
</svg>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ( ! empty($images) ) : ?>
                            <div class="tax_lanz_carousel-wrapper">
                                <div class="tax_lanz_carousel-container" id="<?php echo esc_attr($carousel_id); ?>">
                                    <?php foreach ( $images as $url ) : ?>
                                        <div class="tax_lanz_carousel-item">
                                            <img src="<?php echo esc_url($url); ?>" alt="" loading="lazy" decoding="async">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ( count($images) > 1 ) : ?>
                        <script>
                        (function() {
                            var carousel = document.getElementById('<?php echo esc_js($carousel_id); ?>');
                            var prevBtn  = document.getElementById('<?php echo esc_js($prev_id); ?>');
                            var nextBtn  = document.getElementById('<?php echo esc_js($next_id); ?>');

                            if (!carousel || !prevBtn || !nextBtn) return;

                            var items = carousel.querySelectorAll('.tax_lanz_carousel-item');
                            if (!items.length) return;

                            var currentIndex = 0;
                            var totalItems   = items.length;
                            var maxIndex     = Math.max(0, totalItems - 2); // mostramos ~2 ítems

                            function updateCarousel() {
                                if (!items[0]) return;
                                var itemWidth = items[0].offsetWidth;
                                var gap       = 30;
                                var offset    = currentIndex * (itemWidth + gap);
                                carousel.style.transform = 'translateX(-' + offset + 'px)';
                            }

                            prevBtn.addEventListener('click', function() {
                                if (currentIndex > 0) {
                                    currentIndex--;
                                    updateCarousel();
                                }
                            });

                            nextBtn.addEventListener('click', function() {
                                if (currentIndex < maxIndex) {
                                    currentIndex++;
                                    updateCarousel();
                                }
                            });

                            window.addEventListener('resize', updateCarousel);
                            updateCarousel();
                        })();
                        </script>
                    <?php endif; ?>
                </section>

                <?php
            endif; // hay contenido
        } // is_array( $lanz )
    } // $lanz_term instanceof WP_Term
} // function_exists('get_field')
?>

<?php
// =============================
// Sección: Próximos eventos (Events)
// Grupo ACF en la taxonomía "coleccion":
//  - events
//      - pretitle_events (text)
//      - year_events (text)
//      - title_events (text)
//      - description_events (textarea)
//      - url_events (url)
// =============================

if ( function_exists('get_field') ) {

    // Reusamos $term si ya existe, si no lo tomamos de la query
    $events_term = ( isset($term) && $term instanceof WP_Term )
        ? $term
        : get_queried_object();

    if ( $events_term instanceof WP_Term ) {

        $term_key = $events_term->taxonomy . '_' . $events_term->term_id;

        // Leemos el GROUP "events" desde el término
        $events = get_field('events', $events_term);
        if ( ! $events ) {
            $events = get_field('events', $term_key);
        }

        if ( is_array($events) ) {

            $pretitle = isset($events['pretitle_events'])
                ? trim((string) $events['pretitle_events'])
                : '';

            $year = isset($events['year_events'])
                ? trim((string) $events['year_events'])
                : '';

            $title = isset($events['title_events'])
                ? trim((string) $events['title_events'])
                : '';

            $description = isset($events['description_events'])
                ? trim((string) $events['description_events'])
                : '';

            $url_button = isset($events['url_events'])
                ? trim((string) $events['url_events'])
                : '';

            // ¿Hay algo de contenido?
            $has_content = (
                $pretitle !== '' ||
                $year !== '' ||
                $title !== '' ||
                $description !== '' ||
                $url_button !== ''
            );

            if ( $has_content ) :

                $sec_id = 'tax-events-' . $events_term->term_id;
                ?>
                <section id="<?php echo esc_attr($sec_id); ?>" class="tax_events_block">
                  <div class="tax_events_container">
                    <div class="tax_events_content">

                      <?php if ( $pretitle || $year ) : ?>
                        <div class="tax_events_eyebrow">
                          <?php if ( $pretitle ) : ?>
                            <span class="tax_events_eyebrow-label font-overline">
                              <?php echo esc_html($pretitle); ?>
                            </span>
                          <?php elseif ( current_user_can('edit_posts') ): ?>
                            <span class="tax_events_eyebrow-label font-overline" style="opacity:.6;">
                              Asigná el campo “pretitle_events”.
                            </span>
                          <?php endif; ?>

                          <?php if ( $year ) : ?>
                            <span class="tax_events_eyebrow-year font-overline">
                              <?php echo esc_html($year); ?>
                            </span>
                          <?php elseif ( current_user_can('edit_posts') ): ?>
                            <span class="tax_events_eyebrow-year font-overline" style="opacity:.6;">
                              Año
                            </span>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>

                      <?php if ( $title ) : ?>
                        <h1 class="tax_events_title font-heading-1">
                          <?php echo nl2br( esc_html($title) ); ?>
                        </h1>
                      <?php elseif ( current_user_can('edit_posts') ): ?>
                        <h1 class="tax_events_title font-heading-1" style="opacity:.6;">
                          Asigná el campo “title_events”.
                        </h1>
                      <?php endif; ?>

                      <?php if ( $description ) : ?>
                        <div class="tax_events_description font-body-medium-light">
                          <?php echo wp_kses_post( wpautop($description) ); ?>
                           <?php if ( $url_button ) : ?>
                        <a href="<?php echo esc_url($url_button); ?>" class="btn btn-cafe">
                          Registrarse
                          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
                            <path d="M10.1458 7.5H0V5.83333H10.1458L5.47917 1.16667L6.66667 0L13.3333 6.66667L6.66667 13.3333L5.47917 12.1667L10.1458 7.5Z" fill="white"/>
                          </svg>
                        </a>
                      <?php endif; ?>
                        </div>
                      <?php elseif ( current_user_can('edit_posts') ): ?>
                        <div class="tax_events_description font-body-medium-light" style="opacity:.6;">
                          Asigná el campo “description_events”.
                        </div>
                      <?php endif; ?>

                     

                    </div><!-- /.tax_events_content -->
                  </div><!-- /.tax_events_container -->
                </section>
                <?php
            endif; // $has_content
        }
    }
}
?>

</main>

<?php
get_footer();
