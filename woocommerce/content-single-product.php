<?php
defined('ABSPATH') || exit;
global $product;
if (!$product) return;

$post_id = $product->get_id();

if (!function_exists('nl_get_primary_collection_info')) {
  function nl_get_primary_collection_info( $product ) {
    $post_id = $product->get_id();
    $info = ['name' => '', 'url' => null];

    // 1) Taxonomía 'coleccion'
    if ( taxonomy_exists('showroom') ) {
      $terms = get_the_terms( $post_id, 'showroom' );
      if ( $terms && ! is_wp_error($terms) ) {
        $t = array_values($terms)[0]; // primera
        $info['name'] = $t->name;
        $link = get_term_link( $t );
        if ( ! is_wp_error($link) ) $info['url'] = $link;
        return $info;
      }
    }

    // 2) Atributo 'pa_coleccion' (o 'coleccion')
    $attrCandidates = ['pa_showroom', 'showroom'];
    foreach ($attrCandidates as $attr) {
      $raw = $product->get_attribute($attr); // ej: "Escazú, Otro"
      if ( is_string($raw) && trim($raw) !== '' ) {
        $name = trim( explode(',', $raw)[0] );
        $info['name'] = $name;
        foreach (['showroom','pa_showroom'] as $tax) {
          if ( taxonomy_exists($tax) ) {
            $term = get_term_by('name', $name, $tax);
            if ( $term && ! is_wp_error($term) ) {
              $link = get_term_link($term);
              if ( ! is_wp_error($link) ) { $info['url'] = $link; break 2; }
            }
          }
        }
        return $info;
      }
    }

    // 3) ACF (campo 'coleccion' o 'colecciones')
    if ( function_exists('get_field') ) {
      $acf = get_field('showroom', $post_id);
      if ( ! $acf ) $acf = get_field('showrooms', $post_id);

      $pick = null;
      if ( is_array($acf) && isset($acf[0]) ) $pick = $acf[0];
      elseif ( $acf ) $pick = $acf;

      if ( $pick ) {
        if ( is_object($pick) && ! empty($pick->name) ) {
          $info['name'] = $pick->name;
          $t = get_term( $pick->term_id ?? 0 );
          if ( $t && ! is_wp_error($t) ) {
            $link = get_term_link($t);
            if ( ! is_wp_error($link) ) $info['url'] = $link;
          }
        } elseif ( is_array($pick) && ! empty($pick['name']) ) {
          $info['name'] = $pick['name'];
          if ( ! empty($pick['term_id']) ) {
            $t = get_term( (int)$pick['term_id'] );
            if ( $t && ! is_wp_error($t) ) {
              $link = get_term_link($t);
              if ( ! is_wp_error($link) ) $info['url'] = $link;
            }
          }
        } elseif ( is_numeric($pick) ) {
          $t = get_term( (int)$pick );
          if ( $t && ! is_wp_error($t) ) {
            $info['name'] = $t->name;
            $link = get_term_link($t);
            if ( ! is_wp_error($link) ) $info['url'] = $link;
          }
        } elseif ( is_string($pick) ) {
          $info['name'] = $pick;
        }
      }
    }

    if ( ! $info['name'] ) $info['name'] = 'Colección';
    return $info;
  }
}

if ( ! function_exists('nl_get_category_pair') ) {
  function nl_get_category_pair( $post_id ) {
    $out = ['category' => null, 'subcategory' => null];
    $cats = get_the_terms( $post_id, 'product_cat' );
    if ( ! $cats || is_wp_error($cats) ) return $out;

    $depth = static function( $term ) {
      $d = 0; $p = $term->parent;
      while ( $p ) {
        $pt = get_term( $p, 'product_cat' );
        if ( ! $pt || is_wp_error($pt) ) break;
        $d++; $p = $pt->parent;
      }
      return $d;
    };

    $deepest = null; $max = -1;
    foreach ($cats as $c) {
      $d = $depth($c);
      if ( $d > $max ) { $max = $d; $deepest = $c; }
    }
    if ( ! $deepest ) return $out;

    $anc_ids = array_reverse( get_ancestors( $deepest->term_id, 'product_cat' ) );
    $chain   = [];
    foreach ( $anc_ids as $id ) {
      $t = get_term( $id, 'product_cat' );
      if ( $t && ! is_wp_error($t) ) $chain[] = $t;
    }
    $chain[] = $deepest;

    $len = count($chain);
    if ( $len >= 2 ) {
      $out['category']    = $chain[$len - 2];
      $out['subcategory'] = $chain[$len - 1];
    } else {
      $out['category']    = $chain[0];
      $out['subcategory'] = null;
    }
    return $out;
  }
}

if (!function_exists('nl_get_collection_name')) {
  function nl_get_collection_name($product) {
    $post_id   = $product->get_id();
    $tax_slug  = 'showroom';
    $name      = '';

    // 1) Taxonomía
    if (taxonomy_exists($tax_slug)) {
      if (function_exists('wc_get_product_terms')) {
        $names = wc_get_product_terms($post_id, $tax_slug, ['fields' => 'names']);
      } else {
        $terms = get_the_terms($post_id, $tax_slug);
        $names = ($terms && !is_wp_error($terms)) ? wp_list_pluck($terms, 'name') : [];
      }
      if (!empty($names)) $name = implode(', ', array_map('trim', $names));
    }

    // 2) Atributo
    if (!$name) {
      foreach (['pa_showroom', 'showroom'] as $key) {
        $raw = $product->get_attribute($key);
        if (is_string($raw) && trim($raw) !== '') {
          $parts = array_map('trim', explode(',', $raw));
          if (!empty($parts[0])) { $name = $parts[0]; break; }
        }
      }
      if (!$name) {
        foreach ((array) $product->get_attributes() as $attr) {
          if (method_exists($attr, 'get_name') && $attr->get_name() === 'pa_showroom') {
            $raw = $product->get_attribute('pa_showroomn');
            if ($raw) { $parts = array_map('trim', explode(',', $raw)); if (!empty($parts[0])) { $name = $parts[0]; break; } }
          }
        }
      }
    }

    // 3) ACF
    if (!$name && function_exists('get_field')) {
      $acf_val = get_field('showroom', $post_id) ?: get_field('showrooms', $post_id);
      $names = [];
      $push = static function($item) use (&$names){
        if (is_object($item) && !empty($item->name)) $names[] = $item->name;
        elseif (is_array($item) && !empty($item['name'])) $names[] = $item['name'];
        elseif (is_numeric($item)) { $t = get_term((int)$item); if ($t && !is_wp_error($t) && !empty($t->name)) $names[] = $t->name; }
        elseif (is_string($item) && $item !== '') $names[] = $item;
      };
      if (is_array($acf_val)) { foreach ($acf_val as $it) { $push($it); } }
      elseif ($acf_val) { $push($acf_val); }
      if (!empty($names)) $name = implode(', ', array_map('trim', $names));
    }

    return $name ?: 'Showroom';
  }
}

if (!function_exists('nl_get_product_banner_image_id')) {
  function nl_get_product_banner_image_id($product) {
    $post_id = $product->get_id();
    $value = 0;

    if (function_exists('get_field')) {
      $value = get_field('product_banner_image', $post_id) ?: get_field('banner_image', $post_id);
    }

    if (!$value) {
      $value = get_post_meta($post_id, 'product_banner_image', true);
    }

    if (is_array($value) && !empty($value['ID'])) return (int) $value['ID'];
    if (is_object($value) && !empty($value->ID)) return (int) $value->ID;
    if (is_numeric($value)) return (int) $value;
    if (is_string($value) && preg_match('~^https?://~', $value)) return (int) attachment_url_to_postid($value);

    return 0;
  }
}

if (!function_exists('nl_get_product_image_ids')) {
  // Versión robusta (placeholder + galería + variaciones + adjuntos + ACF opcional, limit 5)
  function nl_get_product_image_ids($product){
    $ids = [];
    $banner_id = function_exists('nl_get_product_banner_image_id') ? nl_get_product_banner_image_id($product) : 0;

    // destacada
    $main_id = get_post_thumbnail_id($product->get_id());
    if ($main_id) $ids[] = (int)$main_id;

    // galería Woo
    $gallery = (array) $product->get_gallery_image_ids();
    if ($gallery) $ids = array_merge($ids, array_map('intval', $gallery));

    // variaciones
    if ($product->is_type('variable')) {
      foreach ((array) $product->get_children() as $vid) {
        $vimg = get_post_thumbnail_id($vid);
        if ($vimg) $ids[] = (int)$vimg;
      }
    }

    // adjuntos
    $attached = get_posts([
      'post_type'      => 'attachment',
      'post_mime_type' => 'image',
      'post_parent'    => $product->get_id(),
      'posts_per_page' => -1,
      'fields'         => 'ids',
      'orderby'        => 'menu_order',
      'order'          => 'ASC',
    ]);
    if ($attached) $ids = array_merge($ids, array_map('intval', $attached));

    // ACF gallery opcional
    if (function_exists('get_field')) {
      $acf_gallery = get_field('galeria') ?: get_field('gallery');
      if (is_array($acf_gallery)) {
        foreach ($acf_gallery as $img) {
          if (is_numeric($img)) $ids[] = (int)$img;
          elseif (is_array($img) && !empty($img['ID'])) $ids[] = (int)$img['ID'];
          elseif (is_object($img) && !empty($img->ID)) $ids[] = (int)$img->ID;
        }
      }
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if ($banner_id) {
      $ids = array_values(array_diff($ids, [(int)$banner_id]));
    }
    return array_slice($ids, 0, 5);
  }
}

if (!function_exists('nl_pick_default_variation')) {
  function nl_pick_default_variation( WC_Product_Variable $product ){
    $defaults = array_filter((array)$product->get_default_attributes());
    if ($defaults) {
      foreach ($product->get_children() as $vid) {
        $v = wc_get_product($vid);
        if (!$v || !$v->is_purchasable()) continue;
        $atts = $v->get_attributes();
        $ok = true;
        foreach ($defaults as $k => $val) {
          $k = sanitize_title($k);
          $in = $atts[$k] ?? $atts['attribute_'.$k] ?? '';
          if ($in === '' || wc_strtolower($in) !== wc_strtolower($val)) { $ok = false; break; }
        }
        if ($ok && ($v->is_in_stock() || $v->backorders_allowed())) return $v;
      }
    }
    foreach ($product->get_children() as $vid) {
      $v = wc_get_product($vid);
      if ($v && $v->is_purchasable() && ($v->is_in_stock() || $v->backorders_allowed())) return $v;
    }
    return null;
  }
}

if (!function_exists('nl_has_content_wysiwyg')) {
  function nl_has_content_wysiwyg($raw){
    if ($raw === null) return false;
    if (is_array($raw) || is_object($raw)) $raw = (string) wp_json_encode($raw);
    $s = (string) $raw;
    if (function_exists('strip_shortcodes')) $s = strip_shortcodes($s);
    else $s = preg_replace('/\[[^\]]+\]/', '', $s);
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = preg_replace('/(&(nbsp|#160);)+/i', ' ', $s);
    $s = trim( wp_strip_all_tags($s) );
    return $s !== '';
  }
}

/* =========================================================
 *  BREADCRUMBS
 * ======================================================= */
$coll  = nl_get_primary_collection_info( $product );
$pair  = nl_get_category_pair( $post_id );
$title = get_the_title();

$crumbs = []; $pos = 1;
if ( ! empty($coll['name']) ) {
  $crumbs[] = ['label'=>$coll['name'], 'url'=>$coll['url'] ?? null, 'pos'=>$pos++];
}
if ( $pair['category'] ) {
  $url = get_term_link( $pair['category'] );
  $crumbs[] = ['label'=>$pair['category']->name, 'url'=> is_wp_error($url) ? null : $url, 'pos'=>$pos++];
}
if ( $pair['subcategory'] && $pair['subcategory']->term_id !== $pair['category']->term_id ) {
  $url = get_term_link( $pair['subcategory'] );
  $crumbs[] = ['label'=>$pair['subcategory']->name, 'url'=> is_wp_error($url) ? null : $url, 'pos'=>$pos++];
}
$crumbs[] = ['label'=>$title, 'url'=>null, 'pos'=>$pos++];
?>
<nav class="nl-breadcrumbs" aria-label="Ruta del producto">
  <ol class="nl-bc-list">
    <?php foreach ($crumbs as $c): ?>
      <li class="nl-bc-item">
        <?php if ($c['url']): ?>
          <a href="<?php echo esc_url($c['url']); ?>" class="nl-bc-link"><?php echo esc_html($c['label']); ?></a>
        <?php else: ?>
          <span class="nl-bc-current"><?php echo esc_html($c['label']); ?></span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
</nav>

<?php
/* =========================================================
 *  SHOWROOM (galería principal + badge)
 * ======================================================= */
$collection_name = nl_get_collection_name($product);
$badge_text      = 'Disponible ahora en ' . $collection_name;

$image_ids       = nl_get_product_image_ids($product);
$banner_id       = nl_get_product_banner_image_id($product);
$showroom_id     = $banner_id ?: ($image_ids[0] ?? 0);
$uid             = 'nl-showroom-' . get_the_ID();
$circle_id       = 'nl-badge-circle-path-' . get_the_ID();
$use_placeholder = ! $showroom_id && function_exists('wc_placeholder_img_src');
?>
<section id="<?php echo esc_attr($uid); ?>" class="nl-showroom-section" aria-label="Showroom del producto">
  <div class="nl-image-container">
    <?php if ($use_placeholder): ?>
      <div class="nl-main-image active" data-index="0">
        <img src="<?php echo esc_url( wc_placeholder_img_src('full') ); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" />
        <div class="nl-overlay"></div>
      </div>
    <?php elseif ($showroom_id): ?>
      <div class="nl-main-image active" data-index="0">
        <?php echo wp_get_attachment_image($showroom_id,'full',false,[
          'alt'=>trim(get_post_meta($showroom_id,'_wp_attachment_image_alt',true)) ?: get_the_title(),
          'loading'=>'eager','decoding'=>'async'
        ]); ?>
        <div class="nl-overlay"></div>
      </div>
    <?php endif; ?>
  </div>

 

  <div class="nl-showroom-badge">
    <div class="nl-badge-circle">
      <div class="nl-gradient-border"></div>
      <div class="nl-inner-circle"></div>
      <svg class="nl-badge-svg" viewBox="0 0 200 200" aria-label="<?php echo esc_attr($badge_text); ?>">
        <defs><path id="<?php echo esc_attr($circle_id); ?>" d="M100,100 m-86,0 a86,86 0 1,1 172,0 a86,86 0 1,1 -172,0"></path></defs>
        <text class="nl-badge-text-svg">
          <textPath href="#<?php echo esc_attr($circle_id); ?>" startOffset="50%" text-anchor="middle"><?php echo esc_html($badge_text); ?></textPath>
        </text>
      </svg>
      <div class="nl-badge-center" aria-hidden="true">
        <span class="nl-badge-center-ring"></span>
        <span class="nl-badge-center-disc"></span>
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="13" viewBox="0 0 12 13" class="arrow-icon-product"><path d="M11.1129 9.92419C11.1156 9.9939 11.1047 10.0638 11.0806 10.1298C11.0565 10.1959 11.0198 10.2568 10.9725 10.3091C10.8771 10.4148 10.7445 10.4791 10.604 10.4877C10.4635 10.4964 10.3266 10.4488 10.2233 10.3554C10.12 10.262 10.0589 10.1304 10.0534 9.9896L9.73424 1.86317L0.926969 11.9948C0.833551 12.1022 0.702211 12.169 0.561845 12.1804C0.421479 12.1918 0.283583 12.1469 0.178494 12.0555C0.0734036 11.9642 0.00972796 11.8339 0.00147502 11.6933C-0.00677792 11.5527 0.0410676 11.4133 0.134486 11.3059L8.94081 1.17536L0.850579 1.9907C0.781167 1.99768 0.711364 1.99091 0.645157 1.97077C0.578951 1.95064 0.517635 1.91752 0.464714 1.87332C0.411793 1.82913 0.368301 1.77471 0.336722 1.71318C0.305144 1.65165 0.286095 1.58422 0.280666 1.51472C0.275237 1.44523 0.283533 1.37505 0.30508 1.30817C0.326628 1.24129 0.361004 1.17904 0.406247 1.12496C0.451491 1.07089 0.506715 1.02604 0.568767 0.992996C0.630818 0.959948 0.698482 0.939344 0.767895 0.932357L9.98707 0.00379129C10.0819 -0.00573188 10.1772 0.0039978 10.2674 0.0323982C10.3575 0.0607983 10.4406 0.107285 10.5117 0.169075C10.5828 0.230865 10.6404 0.306687 10.681 0.392002C10.7217 0.477316 10.7446 0.570369 10.7484 0.66559L11.1129 9.92419Z" fill="#3D332B"/></svg>
      </div>
    </div>
  </div>
</section>

<script>
(function(){
  var root = document.getElementById('<?php echo esc_js($uid); ?>');
  if(!root) return;
  return;

  var thumbs = root.querySelectorAll('.nl-thumbnail');
  var mains  = root.querySelectorAll('.nl-main-image');

  if (!mains || mains.length === 0) return;

  var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Detectar índice actual (el que está activo al cargar)
  var currentIndex = 0;
  mains.forEach(function(m){
    if (m.classList.contains('active')) {
      var idx = parseInt(m.getAttribute('data-index'), 10);
      if (!isNaN(idx)) currentIndex = idx;
    }
  });

  function activate(idx){
    currentIndex = idx;

    mains.forEach(function(m){ m.classList.remove('active'); });
    thumbs.forEach(function(t){ t.classList.remove('active'); t.setAttribute('aria-selected','false'); });

    var candidate = root.querySelector('.nl-main-image[data-index="'+idx+'"]');
    if(candidate) candidate.classList.add('active');

    var thumbBtn = root.querySelector('.nl-thumbnail[data-target="'+idx+'"]');
    if(thumbBtn){
      thumbBtn.classList.add('active');
      thumbBtn.setAttribute('aria-selected','true');
    }
  }

  // Click / teclado en miniaturas
  thumbs.forEach(function(btn){
    btn.addEventListener('click', function(){
      var idx = parseInt(this.getAttribute('data-target'), 10);
      if (!isNaN(idx)) {
        activate(idx);
        resetAutoplay();
      }
    });
    btn.addEventListener('keydown', function(e){
      if(e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        var idx = parseInt(this.getAttribute('data-target'), 10);
        if (!isNaN(idx)) {
          activate(idx);
          resetAutoplay();
        }
      }
    });
  });

  // -------------------------
  // AUTOPLAY cada 3s
  // -------------------------
  var timer = null;
  var INTERVAL = 3000;

  function next(){
    var nextIndex = (currentIndex + 1) % mains.length;
    activate(nextIndex);
  }

  function startAutoplay(){
    if (prefersReduced) return;
    if (mains.length < 2) return;
    stopAutoplay();
    timer = setInterval(next, INTERVAL);
  }

  function stopAutoplay(){
    if (timer) {
      clearInterval(timer);
      timer = null;
    }
  }

  function resetAutoplay(){
    startAutoplay();
  }

  // Pausa cuando el usuario está interactuando (hover/focus) y vuelve al salir
  root.addEventListener('mouseenter', stopAutoplay);
  root.addEventListener('mouseleave', startAutoplay);
  root.addEventListener('focusin', stopAutoplay);
  root.addEventListener('focusout', startAutoplay);

  // Arrancar
  startAutoplay();
})();
</script>


<?php
/* =========================================================
 *  INFO PRODUCTO (título, desc, precio, add to cart + galería thumbs)
 * ======================================================= */
$image_ids  = nl_get_product_image_ids($product);
// Banner/showroom is hidden via CSS; show every available image in the carousel below.
// Original behavior (kept for reference): array_diff($image_ids, $showroom_id ? [$showroom_id] : []).
$gallery_image_ids = $image_ids;
$main_id    = $gallery_image_ids[0] ?? 0;
$thumb_ids  = $gallery_image_ids;
$main_full  = $main_id ? wp_get_attachment_image_url($main_id, 'full') : '';

$main_html  = $main_id
  ? wp_get_attachment_image($main_id, 'large', false, [
      'class'=>'info-product-main-image','id'=>'mainImage','data-full'=>$main_full,
      'alt'=>get_the_title(),'decoding'=>'async','loading'=>'eager'
    ])
  : '<img id="mainImage" class="info-product-main-image" src="'.esc_url(wc_placeholder_img_src()).'" alt="'.esc_attr(get_the_title()).'">';

$title      = get_the_title();
$short_desc = apply_filters('woocommerce_short_description', get_post_field('post_excerpt', $product->get_id()));
$price_html = $product->get_price_html();
$tax_suffix = wc_prices_include_tax() ? 'IVA inc.' : '+IVA';
$shop_url   = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/tienda');

$chosen_variation = null;
if ( $product->is_type('variable') ) {
  $chosen_variation = nl_pick_default_variation($product);
  if ( $chosen_variation instanceof WC_Product_Variation ) $price_html = $chosen_variation->get_price_html();
}
?>
<div class="info-product-container">
  <a href="<?php echo esc_url($shop_url); ?>" class="info-product-back font-button" onclick="if(document.referrer){history.back();return false;}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    Regresar
  </a>

  <div class="info-product-layout">
    <div class="info-product-details">
      <?php if (!empty($short_desc)): ?>
        <div class="info-product-description">
          <h1 class="font-heading-4"><?php echo esc_html($title); ?></h1><?php echo $short_desc; ?>
        </div>
      <?php endif; ?>

      <?php if ( $product->is_type('variable') ) : ?>
  <?php
    // ======================================================
    // UI Variaciones:
    // - Atributos SIN imagen => radio “texto” (como venías)
    // - Atributos CON imagen => swatches en tabs (como captura)
    // ======================================================

    if ( ! function_exists('nlk__attr_term_image_url') ) {
      function nlk__attr_term_image_url($term_id){
        // 1) meta comunes
        $meta_keys = ['thumbnail_id','image_id','product_attribute_image','swatch_image','term_image','image'];
        foreach ($meta_keys as $k){
          $v = get_term_meta($term_id, $k, true);
          if (is_numeric($v) && (int)$v) {
            $u = wp_get_attachment_image_url((int)$v, 'medium');
            if ($u) return $u;
          }
          if (is_string($v) && preg_match('~^https?://~', $v)) return $v;
        }

        // 2) ACF en términos (si lo usás)
        if (function_exists('get_field')) {
          $acf_keys = ['imagen','image','swatch','thumbnail'];
          foreach ($acf_keys as $k){
            $v = get_field($k, 'term_'.$term_id);
            if (is_array($v) && !empty($v['url'])) return $v['url'];
            if (is_numeric($v) && (int)$v) {
              $u = wp_get_attachment_image_url((int)$v, 'medium');
              if ($u) return $u;
            }
            if (is_string($v) && preg_match('~^https?://~', $v)) return $v;
          }
        }

        return '';
      }
    }

    if ( ! function_exists('nlk__attr_term_group') ) {
      function nlk__attr_term_group($term_id){
        $keys = ['grupo','group','tone_group','familia','coleccion'];
        foreach ($keys as $k){
          $v = get_term_meta($term_id, $k, true);
          if (is_string($v) && trim($v) !== '') return trim($v);
          if (function_exists('get_field')) {
            $v2 = get_field($k, 'term_'.$term_id);
            if (is_string($v2) && trim($v2) !== '') return trim($v2);
          }
        }
        return '';
      }
    }

    $variation_attributes = $product->get_variation_attributes();
    $default_attributes   = $product->get_default_attributes();
    $available_variations = $product->get_available_variations();

    $text_attrs  = []; // radios texto
    $image_attrs = []; // tabs swatches

    foreach ($variation_attributes as $attr_name => $options) {
      $attr_key   = 'attribute_' . $attr_name;
      $label      = function_exists('wc_attribute_label') ? wc_attribute_label($attr_name) : ucwords(str_replace(['pa_','_'],['',' '], $attr_name));
      $label_up   = function_exists('mb_strtoupper') ? mb_strtoupper($label, 'UTF-8') : strtoupper($label);

      $opts = is_array($options) ? $options : [];
      $opts = array_values(array_filter($opts, function($x){ return $x !== '' && $x !== null; }));

      // default para este atributo
      $def = '';
      if (!empty($default_attributes[$attr_name])) $def = (string)$default_attributes[$attr_name];
      if (!$def && !empty($opts)) $def = (string)$opts[0];

      // detecta si tiene imágenes
      $has_images = false;
      if (taxonomy_exists($attr_name)) {
        foreach ($opts as $slug) {
          $term = get_term_by('slug', (string)$slug, $attr_name);
          if ($term && !is_wp_error($term)) {
            $img = nlk__attr_term_image_url((int)$term->term_id);
            if ($img) { $has_images = true; break; }
          }
        }
      }

      if ($has_images) {
        // armamos grupos (Cálidos/Fríos/etc) si existen
        $groups = [];
        foreach ($opts as $slug) {
          $slug = (string)$slug;
          $term = taxonomy_exists($attr_name) ? get_term_by('slug', $slug, $attr_name) : null;
          if (!$term || is_wp_error($term)) continue;

          $img   = nlk__attr_term_image_url((int)$term->term_id);
          $name  = !empty($term->name) ? $term->name : $slug;
          $group = nlk__attr_term_group((int)$term->term_id);

          $gkey = $group !== '' ? $group : '__default__';
          if (!isset($groups[$gkey])) $groups[$gkey] = ['label'=>$group, 'items'=>[]];

          $groups[$gkey]['items'][] = [
            'slug' => $slug,
            'name' => $name,
            'img'  => $img,
          ];
        }

        $tab_id = sanitize_title($label);

        $image_attrs[] = [
          'attr_name' => $attr_name,
          'attr_key'  => $attr_key,
          'label'     => $label,
          'label_up'  => $label_up,
          'tab_id'    => $tab_id,
          'default'   => $def,
          'groups'    => $groups,
        ];
      } else {
        // radios texto (sin imágenes)
        $items = [];
        foreach ($opts as $raw_opt) {
          $slug = (string)$raw_opt;
          $opt_label = $slug;

          // si es taxonomía, mostramos el nombre del término
          if (taxonomy_exists($attr_name)) {
            $term = get_term_by('slug', $slug, $attr_name);
            if ($term && !is_wp_error($term) && !empty($term->name)) $opt_label = $term->name;
          }

          // split “Titulo: detalle”
          $title_line = trim($opt_label);
          $sub_line   = '';
          $parts = explode(':', $opt_label, 2);
          if (count($parts) === 2) {
            $title_line = trim($parts[0]).':';
            $sub_line   = trim($parts[1]);
          }

          $items[] = [
            'slug' => $slug,
            'title'=> $title_line,
            'sub'  => $sub_line,
          ];
        }

        $text_attrs[] = [
          'attr_name' => $attr_name,
          'attr_key'  => $attr_key,
          'label'     => $label,
          'label_up'  => $label_up,
          'default'   => $def,
          'items'     => $items,
        ];
      }
    }

    $variations_json = wp_json_encode(
      $available_variations,
      JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
  ?>

  <div class="info-product-variations" data-role="variations" data-variations="<?php echo esc_attr($variations_json); ?>">

    <?php if (!empty($text_attrs)) : ?>
      <div class="info-product-variation-list">
        <?php foreach ($text_attrs as $a) : ?>
          <div class="info-product-variation info-product-variation--radios" data-attr="<?php echo esc_attr($a['attr_key']); ?>">
            <span class="screen-reader-text"><?php echo esc_html($a['label']); ?></span>

            <div class="info-product-radio-list" role="radiogroup" aria-label="<?php echo esc_attr($a['label']); ?>">
              <?php foreach ($a['items'] as $it) : ?>
                <label class="info-product-radio-item">
                  <input
                    type="radio"
                    class="info-product-radio"
                    name="<?php echo esc_attr($a['attr_key']); ?>"
                    value="<?php echo esc_attr($it['slug']); ?>"
                    <?php checked($a['default'] === $it['slug']); ?>
                  >
                  <span class="info-product-radio-ui" aria-hidden="true"></span>

                  <span class="info-product-radio-text">
                    <span class="info-product-radio-title font-button"><?php echo esc_html($it['title']); ?></span>
                    <?php if ($it['sub'] !== '') : ?>
                      <span class="info-product-radio-sub font-body-small"><?php echo esc_html($it['sub']); ?></span>
                    <?php endif; ?>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($image_attrs)) : ?>
      <div class="info-product-var-tabs" data-role="img-tabs">
        <?php if (count($image_attrs) > 1) : ?>
          <div class="info-product-var-tabs__head" role="tablist" aria-label="Opciones con imagen">
            <?php foreach ($image_attrs as $i => $a) : ?>
              <button
                type="button"
                class="info-product-var-tab<?php echo $i===0 ? ' is-active' : ''; ?>"
                data-tab="<?php echo esc_attr($a['tab_id']); ?>"
                role="tab"
                aria-selected="<?php echo $i===0 ? 'true' : 'false'; ?>"
              >
                <?php echo esc_html($a['label_up']); ?>
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
<?php if (count($image_attrs) === 1) : ?>
  <div class="info-product-var-tabs__single-title font-overline">
    <?php echo esc_html($image_attrs[0]['label_up']); ?>
  </div>
<?php endif; ?>

        <div class="info-product-var-tabs__panes">
          <?php foreach ($image_attrs as $i => $a) : ?>
          
            <div
              class="info-product-var-pane<?php echo ($i===0 ? ' is-active' : ''); ?>"
              data-pane="<?php echo esc_attr($a['tab_id']); ?>"
              role="tabpanel"
            >
              <?php foreach ($a['groups'] as $g) : ?>
                <?php $show_group_title = ($g['label'] !== '') || (count($a['groups']) > 1); ?>
                <div class="info-product-swatch-group">
                  <?php if ($show_group_title) : ?>
                    <div class="info-product-swatch-group__title font-button">
                      <?php echo esc_html($g['label'] !== '' ? $g['label'] : 'Opciones'); ?>
                    </div>
                  <?php endif; ?>

                  <div class="info-product-swatch-row" role="radiogroup" aria-label="<?php echo esc_attr($a['label']); ?>">
                    <?php foreach ($g['items'] as $it) : ?>
                      <label class="info-product-swatch">
                        <input
                          type="radio"
                          class="info-product-swatch__input"
                          name="<?php echo esc_attr($a['attr_key']); ?>"
                          value="<?php echo esc_attr($it['slug']); ?>"
                          <?php checked($a['default'] === $it['slug']); ?>
                        >
                        <span class="info-product-swatch__circle" aria-hidden="true">
                          <?php if (!empty($it['img'])) : ?>
                            <img src="<?php echo esc_url($it['img']); ?>" alt="<?php echo esc_attr($it['name']); ?>" loading="lazy" decoding="async">
                          <?php else : ?>
                            <span class="info-product-swatch__fallback"><?php echo esc_html(mb_substr($it['name'], 0, 2)); ?></span>
                          <?php endif; ?>
                        </span>
                        <span class="screen-reader-text"><?php echo esc_html($it['name']); ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="info-product-variation-msg" data-role="var-msg" aria-live="polite"></div>
  </div>
<?php endif; ?>


      <div>
        <div class="info-product-price-section">
          <div class="font-button">Precio</div>
          <div>
            <span class="info-product-price"><?php echo wp_kses_post($price_html); ?></span>
            <span class="info-product-price-tax"><?php echo esc_html($tax_suffix); ?></span>
          </div>
        </div>

        <?php if ( $product->is_type('simple') ) : ?>
          <form class="cart" action="<?php echo esc_url( apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype="multipart/form-data">
            <?php
            do_action('woocommerce_before_add_to_cart_button');
            woocommerce_quantity_input([
              'min_value'   => 1,
              'max_value'   => $product->backorders_allowed() ? '' : $product->get_stock_quantity(),
              'input_value' => 1,
            ]);
            ?>
            <button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="single_add_to_cart_button button alt info-product-add-button">Agregar al carrito</button>
            <?php do_action('woocommerce_after_add_to_cart_button'); ?>
          </form>

        <?php elseif ( $product->is_type('variable') ) : ?>
          <?php
            $variation_attributes = $product->get_variation_attributes();
            $default_attributes   = $product->get_default_attributes();

            $v_id_default = ($chosen_variation instanceof WC_Product_Variation) ? $chosen_variation->get_id() : 0;

            $max_qty = '';
            if ($chosen_variation instanceof WC_Product_Variation && !$chosen_variation->backorders_allowed()) {
              $max_qty = $chosen_variation->get_stock_quantity();
            }

            // ✅ mismo default que arriba (si no hay default, toma el 1er option del primer atributo)
            $first_attr_name = '';
            foreach ($variation_attributes as $k => $_v) { $first_attr_name = $k; break; }
          ?>
          <form class="cart" data-role="var-form" action="<?php echo esc_url( apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype="multipart/form-data">
            <?php
            do_action('woocommerce_before_add_to_cart_button');
            woocommerce_quantity_input([
              'min_value'   => 1,
              'max_value'   => $max_qty,
              'input_value' => 1,
            ]);
            ?>
            <input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>">
            <input type="hidden" name="product_id" value="<?php echo esc_attr( $product->get_id() ); ?>">

            <input type="hidden" name="variation_id" class="variation_id" data-role="variation-id" value="<?php echo esc_attr($v_id_default); ?>">

            <?php foreach ($variation_attributes as $attr_name => $_opts) : ?>
              <?php
                $attr_key = 'attribute_' . $attr_name;
                $val = $default_attributes[$attr_name] ?? '';

                // si es el primer atributo y no hay default, setea primer opción
                if (!$val && $attr_name === $first_attr_name && !empty($_opts) && is_array($_opts)) {
                  $val = (string) $_opts[0];
                }
              ?>
              <input
                type="hidden"
                name="<?php echo esc_attr($attr_key); ?>"
                value="<?php echo esc_attr($val); ?>"
                data-role="attr-hidden"
                data-attr="<?php echo esc_attr($attr_key); ?>"
              >
            <?php endforeach; ?>

            <button type="submit" class="single_add_to_cart_button button alt info-product-add-button" data-role="add-btn">Agregar al carrito</button>
            <?php do_action('woocommerce_after_add_to_cart_button'); ?>
          </form>

        <?php elseif ( $product->is_type('external') ) : ?>
          <p><a href="<?php echo esc_url( $product->add_to_cart_url() ); ?>" target="_blank" rel="noopener" class="single_add_to_cart_button button alt info-product-add-button">
            <?php echo esc_html( $product->add_to_cart_text() ); ?>
          </a></p>

        <?php else : ?>
          <p class="stock out-of-stock"><?php esc_html_e('Producto no disponible', 'woocommerce'); ?></p>
        <?php endif; ?>
      </div>
    </div>

    <div class="info-product-gallery">
      <div class="info-product-main-image-wrapper">
        <button class="info-product-zoom-button" type="button" aria-label="Ampliar imagen">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/><path d="M11 8v6M8 11h6"/></svg>
        </button>
        <?php echo $main_html; ?>
      </div>

      <?php if (!empty($thumb_ids)): ?>
        <div class="info-product-thumbnails">
          <?php foreach ($thumb_ids as $i => $att_id):
            $large = wp_get_attachment_image_url($att_id, 'large');
            $full  = wp_get_attachment_image_url($att_id, 'full');
            $thumb = wp_get_attachment_image_url($att_id, 'woocommerce_gallery_thumbnail');
            $alt   = trim(get_post_meta($att_id, '_wp_attachment_image_alt', true)) ?: $title . ' vista ' . ($i+1);
          ?>
          <button type="button" class="info-product-thumbnail<?php echo $i===0 ? ' active' : ''; ?>" data-large="<?php echo esc_url($large); ?>" data-full="<?php echo esc_url($full); ?>" data-alt="<?php echo esc_attr($alt); ?>" data-index="<?php echo esc_attr($i+1); ?>" aria-label="<?php echo esc_attr('Ver ' . $alt); ?>" aria-current="<?php echo $i===0 ? 'true' : 'false'; ?>">
            <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($alt); ?>">
          </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="info-product-lightbox" id="infoProductLightbox" hidden>
  <button class="info-product-lightbox-close" type="button" aria-label="Cerrar imagen ampliada">&times;</button>
  <img class="info-product-lightbox-image" src="" alt="">
</div>

<script>
(function(){
  var root = document.querySelector('.info-product-gallery');
  if(!root) return;
  var main = root.querySelector('#mainImage');
  if(!main) return;
  var zoom = root.querySelector('.info-product-zoom-button');
  var thumbs = root.querySelectorAll('.info-product-thumbnail');
  var lightbox = document.getElementById('infoProductLightbox');
  var lightboxImg = lightbox ? lightbox.querySelector('.info-product-lightbox-image') : null;
  var lightboxClose = lightbox ? lightbox.querySelector('.info-product-lightbox-close') : null;

  function setMainFromThumb(thumb){
    var url = thumb.getAttribute('data-large');
    if(!url) return;
    thumbs.forEach(function(x){
      x.classList.remove('active');
      x.setAttribute('aria-current', 'false');
    });
    thumb.classList.add('active');
    thumb.setAttribute('aria-current', 'true');
    main.src = url;
    main.removeAttribute('srcset');
    main.removeAttribute('sizes');
    main.setAttribute('data-full', thumb.getAttribute('data-full') || url);
    main.alt = thumb.getAttribute('data-alt') || main.alt;
  }

  thumbs.forEach(function(t){
    t.addEventListener('click', function(){
      setMainFromThumb(this);
    });
  });

  function closeLightbox(){
    if(!lightbox) return;
    lightbox.hidden = true;
    document.documentElement.classList.remove('info-product-lightbox-open');
  }

  if(zoom && lightbox && lightboxImg){
    zoom.addEventListener('click', function(){
      lightboxImg.src = main.getAttribute('data-full') || main.currentSrc || main.src;
      lightboxImg.alt = main.alt || '';
      lightbox.hidden = false;
      document.documentElement.classList.add('info-product-lightbox-open');
    });
  }

  if(lightboxClose) lightboxClose.addEventListener('click', closeLightbox);
  if(lightbox) {
    lightbox.addEventListener('click', function(e){
      if(e.target === lightbox) closeLightbox();
    });
  }
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape') closeLightbox();
  });
})();
</script>

<script>
(function(){
  var root = document.querySelector('.info-product-container');
  if (!root) return;

  var ui = root.querySelector('[data-role="variations"]');
  if (!ui) return;

  var form = root.querySelector('[data-role="var-form"]');
  if (!form) return;

  var msgEl    = ui.querySelector('[data-role="var-msg"]');
  var priceEl  = root.querySelector('.info-product-price');
  var addBtn   = form.querySelector('[data-role="add-btn"]');
  var varIdEl  = form.querySelector('[data-role="variation-id"]');
  var qtyInput = form.querySelector('input.qty');
  var hiddenAttrInputs = Array.from(form.querySelectorAll('[data-role="attr-hidden"]'));
  var allRadioInputs   = Array.from(ui.querySelectorAll('input[type="radio"]'));

  var variations = [];
  try {
    variations = JSON.parse(ui.getAttribute('data-variations') || '[]');
  } catch(e) {
    variations = [];
  }

  if (!variations.length) return;

  function setMsg(html){
    if (!msgEl) return;
    msgEl.innerHTML = html || '';
  }

  function radiosByName(name){
    return allRadioInputs.filter(function(r){ return r.name === name; });
  }

  function getCheckedRadioValue(name){
    var radios = radiosByName(name);
    for (var i = 0; i < radios.length; i++) {
      if (radios[i].checked) return radios[i].value || '';
    }
    return '';
  }

  function getSelected(){
    var out = {};
    hiddenAttrInputs.forEach(function(h){
      var val = getCheckedRadioValue(h.name);
      if (!val) val = h.value || '';
      out[h.name] = val;
    });
    return out;
  }

  function setHiddenAttributes(selected){
    hiddenAttrInputs.forEach(function(inp){
      inp.value = selected[inp.name] || '';
    });
  }

  function allChosen(selected){
    for (var k in selected) {
      if (!selected[k]) return false;
    }
    return true;
  }

  function partialMatchVariation(variation, selected){
    if (!variation || !variation.attributes) return false;

    var attrs = variation.attributes;

    for (var key in selected) {
      if (!selected.hasOwnProperty(key)) continue;

      var wanted = String(selected[key] || '');
      if (!wanted) continue;

      var current = (typeof attrs[key] !== 'undefined') ? String(attrs[key] || '') : '';

      // si la variación no define ese attr o viene vacío, no bloquea
      if (current === '') continue;

      if (current !== wanted) return false;
    }

    return true;
  }

  function exactMatchVariation(selected){
    for (var i = 0; i < variations.length; i++) {
      var v = variations[i];
      if (!v || !v.attributes) continue;

      var attrs = v.attributes;
      var ok = true;

      for (var key in selected){
        if (!selected.hasOwnProperty(key)) continue;

        var want = String(selected[key] || '');
        var got  = (typeof attrs[key] !== 'undefined') ? String(attrs[key] || '') : '';

        if (got === '') continue;
        if (got !== want) {
          ok = false;
          break;
        }
      }

      if (!ok) continue;

      for (var a in attrs){
        if (!attrs.hasOwnProperty(a)) continue;

        var need = String(attrs[a] || '');
        if (!need) continue;

        if (!selected[a] || String(selected[a]) !== need) {
          ok = false;
          break;
        }
      }

      if (ok) return v;
    }

    return null;
  }

  function findFirstCompatibleVariation(selected){
    for (var i = 0; i < variations.length; i++) {
      if (partialMatchVariation(variations[i], selected)) {
        return variations[i];
      }
    }
    return null;
  }

  function isOptionCompatible(attrName, optionValue, selected){
    var candidate = Object.assign({}, selected);
    candidate[attrName] = optionValue;

    for (var i = 0; i < variations.length; i++) {
      if (partialMatchVariation(variations[i], candidate)) {
        return true;
      }
    }
    return false;
  }

  function toggleOptionState(input, enabled){
    input.disabled = !enabled;

    var label = input.closest('.info-product-radio-item, .info-product-swatch');
    if (label) {
      label.classList.toggle('is-disabled', !enabled);
      label.setAttribute('aria-disabled', enabled ? 'false' : 'true');
    }
  }

  function refreshOptionStates(selected){
    hiddenAttrInputs.forEach(function(h){
      var attrName = h.name;
      var radios = radiosByName(attrName);

      radios.forEach(function(radio){
        var enabled = isOptionCompatible(attrName, radio.value, selected);
        toggleOptionState(radio, enabled);
      });
    });
  }

  function ensureValidSelections(){
    var guard = 0;
    var changed = false;

    do {
      changed = false;

      var selected = getSelected();
      refreshOptionStates(selected);

      hiddenAttrInputs.forEach(function(h){
        var attrName = h.name;
        var radios = radiosByName(attrName);
        if (!radios.length) return;

        var checkedValid = radios.some(function(r){ return r.checked && !r.disabled; });

        if (!checkedValid) {
          var firstEnabled = radios.find(function(r){ return !r.disabled; });
          if (firstEnabled) {
            firstEnabled.checked = true;
            changed = true;
          }
        }
      });

      guard++;
    } while (changed && guard < 10);

    var finalSelected = getSelected();
    refreshOptionStates(finalSelected);
    return finalSelected;
  }

  function applyVariationToInputs(variation){
    if (!variation || !variation.attributes) return;

    var attrs = variation.attributes;

    hiddenAttrInputs.forEach(function(h){
      var attrName = h.name;
      var value = (typeof attrs[attrName] !== 'undefined') ? String(attrs[attrName] || '') : '';

      if (!value) return;

      var radios = radiosByName(attrName);
      if (!radios.length) {
        h.value = value;
        return;
      }

      radios.forEach(function(r){
        r.checked = (String(r.value) === value);
      });
    });
  }

  function setQtyLimits(v){
    if (!qtyInput) return;

    var min = 1;
    var max = '';

    if (v && typeof v.min_qty !== 'undefined' && v.min_qty !== null) {
      var m1 = parseInt(v.min_qty, 10);
      if (isFinite(m1) && m1 > 0) min = m1;
    }

    if (v && typeof v.max_qty !== 'undefined' && v.max_qty !== null && v.max_qty !== '') {
      var m2 = parseInt(v.max_qty, 10);
      if (isFinite(m2) && m2 > 0) max = String(m2);
    }

    qtyInput.min = String(min);

    if (max === '') qtyInput.removeAttribute('max');
    else qtyInput.max = max;

    if (parseInt(qtyInput.value || '1', 10) < min) {
      qtyInput.value = String(min);
    }
  }

  function setPrice(v){
    if (!priceEl || !v || !v.price_html) return;
    priceEl.innerHTML = v.price_html;
  }

  function setAvailability(v, selected){
    if (!addBtn) return;

    if (!allChosen(selected)) {
      addBtn.disabled = true;
      setMsg('');
      return;
    }

    if (!v) {
      // NO mostramos más el mensaje de combinación inválida
      addBtn.disabled = true;
      setMsg('');
      return;
    }

    if (v.is_in_stock === false || v.is_purchasable === false) {
      addBtn.disabled = true;
      setMsg(v.availability_html || '<span class="stock out-of-stock">Sin stock para esa opción.</span>');
      return;
    }

    addBtn.disabled = false;
    setMsg(v.availability_html || '');
  }

  function sync(){
    var selected = ensureValidSelections();
    setHiddenAttributes(selected);

    var exact = allChosen(selected) ? exactMatchVariation(selected) : null;

    // fallback defensivo:
    // si por algún motivo la selección actual no tiene match exacto,
    // saltamos a la primera variación compatible real
    if (!exact) {
      var fallback = findFirstCompatibleVariation(selected);
      if (fallback) {
        applyVariationToInputs(fallback);
        selected = ensureValidSelections();
        setHiddenAttributes(selected);
        exact = exactMatchVariation(selected) || fallback;
      }
    }

    if (varIdEl) {
      varIdEl.value = (exact && exact.variation_id) ? String(exact.variation_id) : '';
    }

    if (exact) {
      setPrice(exact);
      setQtyLimits(exact);
    }

    setAvailability(exact, selected);
  }

  allRadioInputs.forEach(function(el){
    el.addEventListener('change', sync);
  });

  sync();
})();
</script>

<script>
(function(){
  var root = document.querySelector('.info-product-container');
  if (!root) return;

  var tabsWrap = root.querySelector('[data-role="img-tabs"]');
  if (!tabsWrap) return;

  var tabs  = Array.prototype.slice.call(tabsWrap.querySelectorAll('.info-product-var-tab'));
  var panes = Array.prototype.slice.call(tabsWrap.querySelectorAll('.info-product-var-pane'));

  if (!tabs.length || !panes.length) return;

  function activate(id){
    tabs.forEach(function(t){
      var on = t.getAttribute('data-tab') === id;
      t.classList.toggle('is-active', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    panes.forEach(function(p){
      p.classList.toggle('is-active', p.getAttribute('data-pane') === id);
    });
  }

  tabs.forEach(function(t){
    t.addEventListener('click', function(){
      activate(this.getAttribute('data-tab'));
    });
  });
})();
</script>


<script>
  (() => {
  const BREAKPOINT = 768;

  const prefersReducedMotion = () =>
    window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function enableMobileCarousel(section) {
    const container = section.querySelector('.nl-image-container');
    if (!container) return;

    const slides = Array.from(container.querySelectorAll('.nl-main-image'));
    if (slides.length < 2) {
      // Si hay 1 sola imagen, no tiene sentido mostrar dots
      const existing = section.querySelector('.nl-dots');
      if (existing) existing.remove();
      return;
    }

    // Evitar reinits duplicados
    if (container.dataset.nlCarouselInit === '1') return;
    container.dataset.nlCarouselInit = '1';

    // Crear/limpiar dots
    let dotsWrap = section.querySelector('.nl-dots');
    if (!dotsWrap) {
      dotsWrap = document.createElement('div');
      dotsWrap.className = 'nl-dots';
      dotsWrap.setAttribute('role', 'tablist');
      dotsWrap.setAttribute('aria-label', 'Galería de imágenes');
      section.appendChild(dotsWrap);
    }
    dotsWrap.innerHTML = '';

    const dots = slides.map((_, i) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'nl-dot' + (i === 0 ? ' active' : '');
      btn.setAttribute('aria-label', `Ir a imagen ${i + 1}`);
      btn.setAttribute('aria-current', i === 0 ? 'true' : 'false');

      btn.addEventListener('click', () => {
        container.scrollTo({
          left: slides[i].offsetLeft,
          behavior: prefersReducedMotion() ? 'auto' : 'smooth',
        });
      });

      dotsWrap.appendChild(btn);
      return btn;
    });

    // Marcar dot activo según scroll
    let raf = null;
    const onScroll = () => {
      if (raf) cancelAnimationFrame(raf);
      raf = requestAnimationFrame(() => {
        const center = container.scrollLeft + container.clientWidth / 2;

        let best = 0;
        let bestDist = Infinity;

        for (let i = 0; i < slides.length; i++) {
          const s = slides[i];
          const sCenter = s.offsetLeft + s.clientWidth / 2;
          const dist = Math.abs(center - sCenter);
          if (dist < bestDist) {
            bestDist = dist;
            best = i;
          }
        }

        dots.forEach((d, i) => {
          const active = i === best;
          d.classList.toggle('active', active);
          d.setAttribute('aria-current', active ? 'true' : 'false');
        });
      });
    };

    container.addEventListener('scroll', onScroll, { passive: true });
    onScroll(); // set inicial
  }

  function disableMobileCarousel(section) {
    const container = section.querySelector('.nl-image-container');
    if (container) delete container.dataset.nlCarouselInit;
    const dotsWrap = section.querySelector('.nl-dots');
    if (dotsWrap) dotsWrap.remove();
  }

  function setup() {
    document.querySelectorAll('.nl-showroom-section').forEach((section) => {
      const mq = window.matchMedia(`(max-width: ${BREAKPOINT}px)`);

      const apply = () => {
        if (mq.matches) enableMobileCarousel(section);
        else disableMobileCarousel(section);
      };

      apply();
      if (mq.addEventListener) mq.addEventListener('change', apply);
      else mq.addListener(apply); // safari viejo
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setup);
  else setup();
})();

</script>


<?php
/* =========================================================
 *  INFORMACIÓN ADICIONAL (Desktop tabs + Mobile accordion)
 * ======================================================= */
$uid = 'nl-info-adicional-' . $post_id;
$field_keys = ['materiales_y_cuidados','certificaciones','servicio_de_transporte','garantia_de_la_pieza'];

$tabs = [];
foreach ($field_keys as $key) {
  $label = $key;

  if (function_exists('get_field_object')) {
    $obj = get_field_object($key, $post_id);
    $label = (is_array($obj) && !empty($obj['label'])) ? $obj['label'] : ucwords(str_replace('_',' ', $key));
  } else {
    $label = ucwords(str_replace('_',' ', $key));
  }

  $raw  = function_exists('get_field') ? get_field($key, $post_id, false) : '';
  $html = is_string($raw) ? apply_filters('the_content', $raw) : '';

  if ( nl_has_content_wysiwyg($raw) || nl_has_content_wysiwyg($html) ) {
    $slug        = sanitize_title($label);
    $label_upper = function_exists('mb_strtoupper') ? mb_strtoupper($label, 'UTF-8') : strtoupper($label);
    $tabs[] = ['key'=>$key,'label'=>$label,'label_upper'=>$label_upper,'slug'=>$slug,'html'=>$html?:''];
  }
}

if (!empty($tabs)): ?>
<div id="<?php echo esc_attr($uid); ?>" class="container">
  <h1 class="font-heading-4">Información Adicional</h1>

  <!-- =======================
       DESKTOP: TABS (igual que antes)
       ======================= -->
  <div class="nl-info-desktop">
    <div class="content-wrapper">
      <ul class="tabs-list" role="tablist" aria-label="Información adicional del producto">
        <?php foreach ($tabs as $i => $t): ?>
          <li
            id="<?php echo esc_attr($t['slug']); ?>-tab"
            class="tab-item font-button<?php echo $i===0?' active':''; ?>"
            data-tab="<?php echo esc_attr($t['slug']); ?>"
            role="tab"
            aria-selected="<?php echo $i===0?'true':'false'; ?>"
            tabindex="<?php echo $i===0?'0':'-1'; ?>"
          >
            <?php echo esc_html($t['label']); ?>
          </li>
        <?php endforeach; ?>
      </ul>

      <div class="content-panel">
        <?php foreach ($tabs as $i => $t): ?>
          <div
            class="tab-content<?php echo $i===0?' active':''; ?>"
            id="<?php echo esc_attr($t['slug']); ?>"
            role="tabpanel"
            aria-labelledby="<?php echo esc_attr($t['slug']); ?>-tab"
          >
            <h2 class="content-title font-overline"><?php echo esc_html($t['label_upper']); ?></h2>
            <div class="section"><?php echo $t['html']; ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <script>
    (function(){
      // ✅ Solo desktop
      if (!window.matchMedia('(min-width: 769px)').matches) return;

      var root = document.getElementById('<?php echo esc_js($uid); ?>');
      if(!root) return;

      var scope = root.querySelector('.nl-info-desktop');
      if(!scope) return;

      var tabs = scope.querySelectorAll('.tab-item');
      var panes = scope.querySelectorAll('.tab-content');

      function activate(slug){
        tabs.forEach(function(t){
          var isActive = t.getAttribute('data-tab') === slug;
          t.classList.toggle('active', isActive);
          t.setAttribute('aria-selected', isActive ? 'true' : 'false');
          t.setAttribute('tabindex', isActive ? '0' : '-1');
        });
        panes.forEach(function(p){
          p.classList.toggle('active', p.id === slug);
        });
      }

      tabs.forEach(function(tab){
        tab.addEventListener('click', function(){
          activate(this.getAttribute('data-tab'));
        });
        tab.addEventListener('keydown', function(e){
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            activate(this.getAttribute('data-tab'));
          }
        });
      });
    })();
    </script>
  </div>

  <!-- =======================
       MOBILE: ACCORDION
       ======================= -->
  <div class="nl-info-mobile" aria-label="Información adicional del producto">
    <div class="nl-accordion">
      <?php foreach ($tabs as $i => $t):
        $btn_id   = $t['slug'] . '-m-btn';
        $panel_id = $t['slug'] . '-m-panel';
      ?>
        <details class="nl-acc-item">
          <summary class="nl-acc-header font-button"
                   id="<?php echo esc_attr($btn_id); ?>"
                   aria-controls="<?php echo esc_attr($panel_id); ?>">
            <span class="nl-acc-label"><?php echo esc_html($t['label']); ?></span>
            <span class="nl-acc-icon" aria-hidden="true"></span>
          </summary>

          <div class="nl-acc-panel"
               id="<?php echo esc_attr($panel_id); ?>"
               role="region"
               aria-labelledby="<?php echo esc_attr($btn_id); ?>">
            <h2 class="content-title font-overline"><?php echo esc_html($t['label_upper']); ?></h2>
            <div class="section"><?php echo $t['html']; ?></div>
          </div>
        </details>
      <?php endforeach; ?>
    </div>
  </div>

</div>
<?php endif; ?>


<?php
/* =========================================================
 *  DETRÁS DE (repeater ACF con carrusel drag + inercia)
 * ======================================================= */
if ( function_exists('get_field') && ($detras_de_cards = get_field('detras_de', $post_id)) ):
  $uid         = 'detrasde-' . $post_id;
  $carousel_id = $uid . '-carousel';
  $btn_text = 'Descubrir más'; $btn_url  = '';
  if (function_exists('get_field')) {
    $tmp_text = get_field('detras_boton_texto', $post_id);
    $tmp_url  = get_field('detras_boton_url',   $post_id);
    if ($tmp_text) $btn_text = $tmp_text;
    if ($tmp_url)  $btn_url  = $tmp_url;
  }
?>
<section id="<?php echo esc_attr($uid); ?>" class="detrasde-block" aria-label="<?php echo esc_attr('Detrás de ' . get_the_title($post_id)); ?>">
  <div class="detrasde-container">
    <div class="detrasde-header">
      <h2 class="detrasde-title">Detrás de cada pieza</h2>
      <?php if ($btn_url): ?>
        <a class="detrasde-discover-btn" href="<?php echo esc_url($btn_url); ?>"><?php echo esc_html($btn_text); ?></a>
      <?php else: ?>
        <button class="btn btn-outline-cafe" type="button"><?php echo esc_html($btn_text); ?>
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none"><mask id="mask0_1859_3089" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="20" height="20"><rect width="20" height="20" fill="#D9D9D9"/></mask><g mask="url(#mask0_1859_3089)"><path d="M13.4798 10.832H3.33398V9.16536H13.4798L8.81315 4.4987L10.0007 3.33203L16.6673 9.9987L10.0007 16.6654L8.81315 15.4987L13.4798 10.832Z" fill="#3D332B"/></g></svg>
        </button>
      <?php endif; ?>
    </div>
  </div>

  <div class="detrasde-carousel-wrap">
    <div class="detrasde-carousel" id="<?php echo esc_attr($carousel_id); ?>">
      <?php foreach ( (array)$detras_de_cards as $card_ref ) :
        $card_post_id = is_object($card_ref) ? (int)$card_ref->ID : (int)$card_ref;

        // --- AHORA VIENE DEL POST (CPT detras_de): title / description / featured image ---
        $title = (string) get_the_title($card_post_id);

        $desc  = (string) get_post_field('post_excerpt', $card_post_id);
        if (!$desc) $desc = (string) get_post_field('post_content', $card_post_id);

        $thumb_id = (int) get_post_thumbnail_id($card_post_id);

        $img_url = ''; $alt = $title ?: get_the_title($post_id);

        if ($thumb_id) {
          $img_url = wp_get_attachment_image_url($thumb_id, 'large');
          $tmp_alt = trim(get_post_meta($thumb_id, '_wp_attachment_image_alt', true));
          if ($tmp_alt) $alt = $tmp_alt;
        }

        if (!$img_url && function_exists('wc_placeholder_img_src')) $img_url = wc_placeholder_img_src('large');
        $desc_html = $desc ? apply_filters('the_content', $desc) : '';
      ?>
        <article class="detrasde-card">
          <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($alt); ?>" class="detrasde-card-image" loading="lazy" decoding="async" draggable="false" />
          <div class="detrasde-card-content">
            <?php if ($title): ?><h3 class="font-overline"><?php echo esc_html($title); ?></h3><?php endif; ?>
            <?php if ($desc_html): ?><div class="detrasde-card-description font-body-small"><?php echo $desc_html; ?></div><?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<script>
(function(){
  var root = document.getElementById('<?php echo esc_js($uid); ?>');
  if(!root) return;

  var wrap = root.querySelector('.detrasde-carousel-wrap');
  var track = root.querySelector('.detrasde-carousel');
  if(!wrap || !track) return;

  var cards = Array.from(track.querySelectorAll('.detrasde-card'));
  if (cards.length < 2) return;

  var mqMobile = window.matchMedia('(max-width: 768px)');

  // -------------------------
  // MOBILE: dots + scroll-snap
  // -------------------------
  function initMobile(){
    if (wrap.dataset.nlDotsInit === '1') return;
    wrap.dataset.nlDotsInit = '1';

    // Asegurar que no quede transform del desktop
    track.style.transform = 'none';

    // Crear contenedor de dots
    var dotsWrap = root.querySelector('.detrasde-dots');
    if (!dotsWrap) {
      dotsWrap = document.createElement('div');
      dotsWrap.className = 'detrasde-dots';
      dotsWrap.setAttribute('role','tablist');
      dotsWrap.setAttribute('aria-label','Navegación del carrusel');
      // Lo ponemos después del carrusel
      wrap.parentNode.insertBefore(dotsWrap, wrap.nextSibling);
    }
    dotsWrap.innerHTML = '';

    var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var dots = cards.map(function(card, i){
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'detrasde-dot' + (i === 0 ? ' active' : '');
      b.setAttribute('aria-label', 'Ir al slide ' + (i+1));
      b.setAttribute('aria-current', i === 0 ? 'true' : 'false');

      b.addEventListener('click', function(){
        wrap.scrollTo({
          left: card.offsetLeft,
          behavior: prefersReduced ? 'auto' : 'smooth'
        });
      });

      dotsWrap.appendChild(b);
      return b;
    });

    var raf = null;
    function onScroll(){
      if (raf) cancelAnimationFrame(raf);
      raf = requestAnimationFrame(function(){
        var center = wrap.scrollLeft + wrap.clientWidth / 2;
        var best = 0, bestDist = Infinity;

        for (var i=0;i<cards.length;i++){
          var c = cards[i];
          var cCenter = c.offsetLeft + c.clientWidth / 2;
          var dist = Math.abs(center - cCenter);
          if (dist < bestDist){ bestDist = dist; best = i; }
        }

        dots.forEach(function(d, i){
          var active = i === best;
          d.classList.toggle('active', active);
          d.setAttribute('aria-current', active ? 'true' : 'false');
        });
      });
    }

    wrap.addEventListener('scroll', onScroll, { passive:true });
    wrap._nlDotsOnScroll = onScroll;
    onScroll();
  }

  function destroyMobile(){
    wrap.dataset.nlDotsInit = '';
    if (wrap._nlDotsOnScroll){
      wrap.removeEventListener('scroll', wrap._nlDotsOnScroll);
      wrap._nlDotsOnScroll = null;
    }
    var dotsWrap = root.querySelector('.detrasde-dots');
    if (dotsWrap) dotsWrap.remove();
  }

  // -------------------------
  // DESKTOP: tu drag + inercia
  // -------------------------
  function initDesktopDrag(){
    if (track.dataset.nlDragInit === '1') return;
    track.dataset.nlDragInit = '1';

    var isDragging=false,startPos=0,currentTranslate=0,prevTranslate=0,velocity=0,lastMoveTime=0,lastMoveX=0;

    track.addEventListener('dragstart', function(e){ e.preventDefault(); });

    function getX(e){ return e.type.indexOf('mouse')!==-1 ? e.pageX : e.touches[0].clientX; }
    function onStart(e){
      isDragging=true;
      startPos=getX(e);
      lastMoveX=startPos;
      lastMoveTime=Date.now();
      velocity=0;
      track.classList.add('dragging');
    }
    function onMove(e){
      if(!isDragging) return;
      var x=getX(e);
      currentTranslate = prevTranslate + x - startPos;

      var maxT=0, minT=-(track.scrollWidth - track.parentElement.offsetWidth);
      if (isFinite(minT)) {
        if (currentTranslate>maxT) currentTranslate=maxT;
        if (currentTranslate<minT) currentTranslate=minT;
      }

      var now=Date.now(), dt=now-lastMoveTime;
      if (dt>0){ velocity=(x-lastMoveX)/dt; lastMoveX=x; lastMoveTime=now; }

      track.style.transform='translateX('+currentTranslate+'px)';
    }
    function onEnd(){
      if(!isDragging) return;
      isDragging=false;
      track.classList.remove('dragging');

      var v=velocity;
      (function inert(){
        if (Math.abs(v)>0.05){
          currentTranslate += v*16; v*=0.92;

          var maxT=0, minT=-(track.scrollWidth - track.parentElement.offsetWidth);
          if (currentTranslate>maxT) currentTranslate=maxT;
          if (currentTranslate<minT) currentTranslate=minT;

          track.style.transform='translateX('+currentTranslate+'px)';
          requestAnimationFrame(inert);
        } else {
          prevTranslate=currentTranslate;
        }
      })();
    }

    track.addEventListener('mousedown', onStart);
    window.addEventListener('mousemove', onMove);
    window.addEventListener('mouseup', onEnd);

    // touch en desktop no es necesario (y en mobile no queremos esto)
    track._nlDesktopHandlers = { onStart:onStart, onMove:onMove, onEnd:onEnd };
  }

  function destroyDesktopDrag(){
    track.dataset.nlDragInit = '';
    // No removemos listeners por simplicidad (no suele cambiar viewport),
    // pero si querés lo hacemos prolijo también.
  }

  function apply(){
    if (mqMobile.matches){
      destroyDesktopDrag();
      initMobile();
    } else {
      destroyMobile();
      initDesktopDrag();
    }
  }

  apply();
  if (mqMobile.addEventListener) mqMobile.addEventListener('change', apply);
  else mqMobile.addListener(apply);
})();
</script>

<?php endif; ?>


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
