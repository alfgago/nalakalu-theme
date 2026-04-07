<?php
/**
 * Block: Collection Carousel Selector
 *
 * Campo ACF:
 * - selector_col (taxonomy) -> taxonomía con productos asignados
 */

if ( ! function_exists('get_field') ) {
  echo '<p><em>ACF plugin required.</em></p>';
  return;
}

$section_id = 'collection-carousel-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$classes    = 'collection-carousel-selector-block';
if ( ! empty($block['className']) ) $classes .= ' ' . esc_attr($block['className']);
if ( ! empty($block['align']) )     $classes .= ' align' . esc_attr($block['align']);

/**
 * Normalizador de valor del campo taxonomy de ACF a un WP_Term
 * Evitamos redeclare.
 */
if ( ! function_exists('nl_ccs_normalize_term') ) {
  function nl_ccs_normalize_term( $field_value, $field_key_or_name = null ) {
    $term = null;

    if ( $field_value instanceof WP_Term ) {
      $term = $field_value;
    } elseif ( is_array($field_value) && ! empty($field_value) ) {
      if ( isset($field_value[0]) ) {
        $first = $field_value[0];

        if ( $first instanceof WP_Term ) {
          $term = $first;
        } elseif ( is_numeric($first) ) {
          if ( $field_key_or_name ) {
            $fobj = get_field_object($field_key_or_name);
            if ( $fobj && ! empty($fobj['taxonomy']) ) {
              $tax  = is_array($fobj['taxonomy']) ? reset($fobj['taxonomy']) : $fobj['taxonomy'];
              $term = get_term( (int) $first, $tax );
            }
          }
        }
      } elseif ( isset($field_value['term_id'], $field_value['taxonomy']) ) {
        $term = get_term( (int) $field_value['term_id'], $field_value['taxonomy'] );
      }
    } elseif ( is_numeric($field_value) ) {
      if ( $field_key_or_name ) {
        $fobj = get_field_object($field_key_or_name);
        if ( $fobj && ! empty($fobj['taxonomy']) ) {
          $tax  = is_array($fobj['taxonomy']) ? reset($fobj['taxonomy']) : $fobj['taxonomy'];
          $term = get_term( (int) $field_value, $tax );
        }
      }
    }

    if ( $term instanceof WP_Term && ! is_wp_error($term) ) {
      return $term;
    }

    return null;
  }
}

// Obtenemos el valor crudo del campo taxonomy
$raw_term = get_field('selector_col');
$term     = nl_ccs_normalize_term($raw_term, 'selector_col');

if ( ! $term ) {
  if ( current_user_can('edit_posts') ) {
    echo '<section class="'.esc_attr($classes).'"><div style="padding:2rem 8rem;opacity:.7;">Seleccioná una taxonomía válida en el campo <strong>selector_col</strong>.</div></section>';
  }
  return;
}

$taxonomy = $term->taxonomy;
$term_id  = $term->term_id;

// Query de productos vinculados a esa taxonomía
$q = new WP_Query([
  'post_type'      => 'product',
  'post_status'    => 'publish',
  'posts_per_page' => 20,
  'tax_query'      => [[
    'taxonomy' => $taxonomy,
    'field'    => 'term_id',
    'terms'    => $term_id,
  ]],
  'orderby'        => 'date',
  'order'          => 'DESC',
]);
?>
<section
  id="<?php echo esc_attr($section_id); ?>"
  class="<?php echo esc_attr($classes); ?>"
  data-ccs-carousel="1"
>
  <div class="ccs_carousel-section">

    <!-- Header con MISMAS flechas/clases que “Recomendaciones” -->
    <div class="ccs_carousel-header title-row">
      <h2 class="font-heading-2">Explora la colección</h2>
      <div class="nav-buttons desktop-only">
   <button class="carousel-btn prev-btn" type="button" aria-label="Anterior">
    <svg class="ccs-arrow ccs-arrow--prev" xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none">
      <path d="M8.78215 13.8533C8.73134 13.9011 8.6714 13.9387 8.60575 13.9638C8.5401 13.989 8.47003 14.0012 8.39953 13.9999C8.25716 13.9972 8.12171 13.9393 8.02299 13.839C7.92426 13.7386 7.87034 13.604 7.8731 13.4648C7.87585 13.3256 7.93504 13.1931 8.03766 13.0966L13.9614 7.52432L0.536892 7.52432C0.394499 7.52432 0.257937 7.46901 0.15725 7.37055C0.0565633 7.27209 -1.93715e-06 7.13854 -1.93715e-06 6.9993C-1.93715e-06 6.86005 0.0565633 6.72651 0.15725 6.62805C0.257937 6.52959 0.394499 6.47427 0.536892 6.47427L13.96 6.47427L8.03695 0.90341C7.98613 0.855607 7.94545 0.798484 7.91721 0.735305C7.88898 0.672125 7.87374 0.604126 7.87238 0.535189C7.87102 0.466252 7.88355 0.397728 7.90927 0.333528C7.93499 0.26933 7.97339 0.210712 8.02227 0.161024C8.07115 0.111336 8.12957 0.0715501 8.19418 0.0439384C8.25878 0.0163257 8.32832 0.00142741 8.39882 9.5129e-05C8.46931 -0.00123715 8.53938 0.0110214 8.60503 0.0361717C8.67069 0.061321 8.73063 0.09887 8.78144 0.146673L15.5306 6.49527C15.6 6.56059 15.6552 6.63892 15.6929 6.72559C15.7306 6.81225 15.75 6.90547 15.75 6.99965C15.75 7.09383 15.7306 7.18705 15.6929 7.27371C15.6552 7.36038 15.6 7.43871 15.5306 7.50402L8.78215 13.8533Z" fill="#3D332B"/>
    </svg>
  </button>

  <button class="carousel-btn next-btn" type="button" aria-label="Siguiente">
    <svg class="ccs-arrow" xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none">
      <path d="M8.78215 13.8533C8.73134 13.9011 8.6714 13.9387 8.60575 13.9638C8.5401 13.989 8.47003 14.0012 8.39953 13.9999C8.25716 13.9972 8.12171 13.9393 8.02299 13.839C7.92426 13.7386 7.87034 13.604 7.8731 13.4648C7.87585 13.3256 7.93504 13.1931 8.03766 13.0966L13.9614 7.52432L0.536892 7.52432C0.394499 7.52432 0.257937 7.46901 0.15725 7.37055C0.0565633 7.27209 -1.93715e-06 7.13854 -1.93715e-06 6.9993C-1.93715e-06 6.86005 0.0565633 6.72651 0.15725 6.62805C0.257937 6.52959 0.394499 6.47427 0.536892 6.47427L13.96 6.47427L8.03695 0.90341C7.98613 0.855607 7.94545 0.798484 7.91721 0.735305C7.88898 0.672125 7.87374 0.604126 7.87238 0.535189C7.87102 0.466252 7.88355 0.397728 7.90927 0.333528C7.93499 0.26933 7.97339 0.210712 8.02227 0.161024C8.07115 0.111336 8.12957 0.0715501 8.19418 0.0439384C8.25878 0.0163257 8.32832 0.00142741 8.39882 9.5129e-05C8.46931 -0.00123715 8.53938 0.0110214 8.60503 0.0361717C8.67069 0.061321 8.73063 0.09887 8.78144 0.146673L15.5306 6.49527C15.6 6.56059 15.6552 6.63892 15.6929 6.72559C15.7306 6.81225 15.75 6.90547 15.75 6.99965C15.75 7.09383 15.7306 7.18705 15.6929 7.27371C15.6552 7.36038 15.6 7.43871 15.5306 7.50402L8.78215 13.8533Z" fill="#3D332B"/>
    </svg>
  </button>
</div>

    </div>

    <div class="ccs_carousel-wrapper">
      <div class="ccs_carousel-container">
        <?php
        if ( $q->have_posts() ) :
          while ( $q->have_posts() ) :
            $q->the_post();
            $pid   = get_the_ID();
            $plink = get_permalink($pid);
            $pname = get_the_title($pid);

            $thumb = get_the_post_thumbnail_url($pid, 'woocommerce_thumbnail');
            if ( ! $thumb && function_exists('wc_placeholder_img_src') ) {
              $thumb = wc_placeholder_img_src('woocommerce_thumbnail');
            }
            ?>
            <a class="ccs_carousel-item" href="<?php echo esc_url($plink); ?>">
              <?php if ( $thumb ): ?>
                <img
                  src="<?php echo esc_url($thumb); ?>"
                  alt="<?php echo esc_attr($pname); ?>"
                  class="ccs_carousel-item-image"
                  loading="lazy"
                  decoding="async"
                >
              <?php endif; ?>
              <span class="font-caption-small">
                <?php echo esc_html($pname); ?>
              </span>
            </a>
          <?php
          endwhile;
          wp_reset_postdata();
        else :
          if ( current_user_can('edit_posts') ) :
            ?>
            <div style="padding:1rem 0;opacity:.7;">
              No se encontraron productos para la taxonomía seleccionada.
            </div>
            <?php
          endif;
        endif;
        ?>
      </div>
    </div>
    <div class="nav-buttons mobile-only">
  <button class="carousel-btn prev-btn" type="button" aria-label="Anterior">
    <svg class="ccs-arrow ccs-arrow--prev" xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none">
      <path d="M8.78215 13.8533C8.73134 13.9011 8.6714 13.9387 8.60575 13.9638C8.5401 13.989 8.47003 14.0012 8.39953 13.9999C8.25716 13.9972 8.12171 13.9393 8.02299 13.839C7.92426 13.7386 7.87034 13.604 7.8731 13.4648C7.87585 13.3256 7.93504 13.1931 8.03766 13.0966L13.9614 7.52432L0.536892 7.52432C0.394499 7.52432 0.257937 7.46901 0.15725 7.37055C0.0565633 7.27209 -1.93715e-06 7.13854 -1.93715e-06 6.9993C-1.93715e-06 6.86005 0.0565633 6.72651 0.15725 6.62805C0.257937 6.52959 0.394499 6.47427 0.536892 6.47427L13.96 6.47427L8.03695 0.90341C7.98613 0.855607 7.94545 0.798484 7.91721 0.735305C7.88898 0.672125 7.87374 0.604126 7.87238 0.535189C7.87102 0.466252 7.88355 0.397728 7.90927 0.333528C7.93499 0.26933 7.97339 0.210712 8.02227 0.161024C8.07115 0.111336 8.12957 0.0715501 8.19418 0.0439384C8.25878 0.0163257 8.32832 0.00142741 8.39882 9.5129e-05C8.46931 -0.00123715 8.53938 0.0110214 8.60503 0.0361717C8.67069 0.061321 8.73063 0.09887 8.78144 0.146673L15.5306 6.49527C15.6 6.56059 15.6552 6.63892 15.6929 6.72559C15.7306 6.81225 15.75 6.90547 15.75 6.99965C15.75 7.09383 15.7306 7.18705 15.6929 7.27371C15.6552 7.36038 15.6 7.43871 15.5306 7.50402L8.78215 13.8533Z" fill="#3D332B"/>
    </svg>
  </button>

  <button class="carousel-btn next-btn" type="button" aria-label="Siguiente">
    <svg class="ccs-arrow" xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none">
      <path d="M8.78215 13.8533C8.73134 13.9011 8.6714 13.9387 8.60575 13.9638C8.5401 13.989 8.47003 14.0012 8.39953 13.9999C8.25716 13.9972 8.12171 13.9393 8.02299 13.839C7.92426 13.7386 7.87034 13.604 7.8731 13.4648C7.87585 13.3256 7.93504 13.1931 8.03766 13.0966L13.9614 7.52432L0.536892 7.52432C0.394499 7.52432 0.257937 7.46901 0.15725 7.37055C0.0565633 7.27209 -1.93715e-06 7.13854 -1.93715e-06 6.9993C-1.93715e-06 6.86005 0.0565633 6.72651 0.15725 6.62805C0.257937 6.52959 0.394499 6.47427 0.536892 6.47427L13.96 6.47427L8.03695 0.90341C7.98613 0.855607 7.94545 0.798484 7.91721 0.735305C7.88898 0.672125 7.87374 0.604126 7.87238 0.535189C7.87102 0.466252 7.88355 0.397728 7.90927 0.333528C7.93499 0.26933 7.97339 0.210712 8.02227 0.161024C8.07115 0.111336 8.12957 0.0715501 8.19418 0.0439384C8.25878 0.0163257 8.32832 0.00142741 8.39882 9.5129e-05C8.46931 -0.00123715 8.53938 0.0110214 8.60503 0.0361717C8.67069 0.061321 8.73063 0.09887 8.78144 0.146673L15.5306 6.49527C15.6 6.56059 15.6552 6.63892 15.6929 6.72559C15.7306 6.81225 15.75 6.90547 15.75 6.99965C15.75 7.09383 15.7306 7.18705 15.6929 7.27371C15.6552 7.36038 15.6 7.43871 15.5306 7.50402L8.78215 13.8533Z" fill="#3D332B"/>
    </svg>
  </button>
</div>
  </div>

  <script>
(function(){
  function initCollectionCarousels(){
    var blocks = document.querySelectorAll('.collection-carousel-selector-block[data-ccs-carousel="1"]');
    if(!blocks.length) return;

    blocks.forEach(function(root){
      if (root.dataset.ccsInitialized === '1') return;
      root.dataset.ccsInitialized = '1';

      var prevBtn = root.querySelector('.prev-btn');
      var nextBtn = root.querySelector('.next-btn');
      var grid    = root.querySelector('.ccs_carousel-container');
      if (!grid) return;

      var state = { index: 0, perView: 4 };

      function isMobile(){
        return window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
      }

      function updatePerView(){
        var w = window.innerWidth;
        if (w <= 768)       state.perView = 1;  // ✅ mobile = 1 card
        else if (w <= 1200) state.perView = 3;
        else                state.perView = 4;
      }

      function counts(){
        var total = grid.querySelectorAll('.ccs_carousel-item').length;
        var maxIndex = Math.max(0, total - state.perView);
        return { total: total, maxIndex: maxIndex };
      }

      function clamp(i){
        var c = counts();
        return Math.max(0, Math.min(c.maxIndex, i));
      }

      function updateButtons(){
        var c = counts();
        if (prevBtn) prevBtn.disabled = (state.index === 0);
        if (nextBtn) nextBtn.disabled = (state.index >= c.maxIndex);
      }

      function updateCarousel(animate){
        var card = grid.querySelector('.ccs_carousel-item');
        if(!card){
          state.index = 0;
          updateButtons();
          return;
        }

        var style = window.getComputedStyle(grid);
        var gapPx = parseFloat(style.columnGap || style.gap || 0) || 0;
        var cardW = card.getBoundingClientRect().width;
        var x = -(state.index * (cardW + gapPx));

        grid.style.transition = animate ? 'transform .5s ease' : 'none';
        grid.style.transform  = 'translateX(' + x + 'px)';

        updateButtons();
      }

      function go(delta){
        state.index = clamp(state.index + delta);
        updateCarousel(true);
      }

      if (prevBtn) prevBtn.addEventListener('click', function(e){
        e.preventDefault();
        var step = isMobile() ? 1 : state.perView;  // ✅ mobile avanza 1
        go(-step);
      });

      if (nextBtn) nextBtn.addEventListener('click', function(e){
        e.preventDefault();
        var step = isMobile() ? 1 : state.perView;  // ✅ mobile avanza 1
        go(+step);
      });

      window.addEventListener('resize', function(){
        updatePerView();
        state.index = clamp(state.index);
        updateCarousel(false);
      });

      // Init
      updatePerView();
      updateCarousel(false);
      window.addEventListener('load', function(){ updateCarousel(false); });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCollectionCarousels);
  } else {
    initCollectionCarousels();
  }
})();
</script>


</section>
