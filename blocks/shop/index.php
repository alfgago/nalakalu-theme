<?php
/**
 * Block: Shop (Woo + AJAX)
 */
if ( ! defined('ABSPATH') ) exit;

/**
 * Helpers locales (evita redeclare)
 */
if ( ! function_exists('nlk_shop__get_term_thumb_url') ) {
  function nlk_shop__get_term_thumb_url($term_id){
    $thumb_id = (int) get_term_meta($term_id, 'thumbnail_id', true);
    if ($thumb_id) {
      $url = wp_get_attachment_image_url($thumb_id, 'full');
      return $url ? $url : '';
    }
    return '';
  }
}

if ( ! function_exists('nlk_shop__get_default_banner_url') ) {
  function nlk_shop__get_default_banner_url(){
    $parents = get_terms([
  'taxonomy'   => 'product_cat',
  'hide_empty' => false,
  'parent'     => 0,
  'orderby'    => 'menu_order',
  'order'      => 'ASC',
]);

    if (!empty($parents) && !is_wp_error($parents)) {
      foreach ($parents as $p) {
        // 1) primero probamos el padre
        $u = nlk_shop__get_term_thumb_url($p->term_id);
        if ($u) return $u;

        // 2) si no tiene, probamos el primer hijo
        $first_child = get_terms([
          'taxonomy'   => 'product_cat',
          'hide_empty' => false,
          'parent'     => $p->term_id,
          'number'     => 1,
          'orderby'    => 'name',
          'order'      => 'ASC',
        ]);

        if (!empty($first_child) && !is_wp_error($first_child)) {
          $u2 = nlk_shop__get_term_thumb_url($first_child[0]->term_id);
          if ($u2) return $u2;
        }
      }
    }

    return function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src('full') : '';
  }
}

if ( ! function_exists('nlk_shop__normalize_image_url') ) {
  function nlk_shop__normalize_image_url($image){
    if (empty($image)) return '';

    // ACF image array
    if (is_array($image) && !empty($image['url'])) {
      return $image['url'];
    }

    // ACF image ID
    if (is_numeric($image)) {
      $url = wp_get_attachment_image_url((int)$image, 'full');
      return $url ? $url : '';
    }

    // URL string
    if (is_string($image)) {
      return $image;
    }

    return '';
  }
}
if ( ! function_exists('nlk_shop__get_breadcrumbs_html') ) {
  function nlk_shop__get_breadcrumbs_html($selected_term_id = 0){
    $crumbs = [];
    $pos    = 1;

    $shop_url = function_exists('wc_get_page_permalink')
      ? wc_get_page_permalink('shop')
      : get_post_type_archive_link('product');

    // Siempre arranca en tienda
    $crumbs[] = [
      'label' => 'TIENDA',
      'url'   => $selected_term_id ? $shop_url : null,
      'pos'   => $pos++,
    ];

    if ($selected_term_id) {
      $term = get_term((int) $selected_term_id, 'product_cat');

      if ($term && !is_wp_error($term)) {
        $ancestor_ids = array_reverse(get_ancestors($term->term_id, 'product_cat', 'taxonomy'));

        foreach ($ancestor_ids as $ancestor_id) {
          $ancestor = get_term((int) $ancestor_id, 'product_cat');
          if ($ancestor && !is_wp_error($ancestor)) {
            $url = get_term_link($ancestor);
            $crumbs[] = [
              'label' => $ancestor->name,
              'url'   => is_wp_error($url) ? null : $url,
              'pos'   => $pos++,
            ];
          }
        }

        $crumbs[] = [
          'label' => $term->name,
          'url'   => null,
          'pos'   => $pos++,
        ];
      }
    }

    ob_start();
    ?>
    <nav class="nl-breadcrumbs" aria-label="Ruta de la tienda">
      <ol class="nl-bc-list">
        <?php foreach ($crumbs as $c): ?>
          <li class="nl-bc-item">
            <?php if (!empty($c['url'])): ?>
              <a href="<?php echo esc_url($c['url']); ?>" class="nl-bc-link"><?php echo esc_html($c['label']); ?></a>
            <?php else: ?>
              <span class="nl-bc-current"><?php echo esc_html($c['label']); ?></span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    </nav>
    <?php
    return ob_get_clean();
  }
}

$block_id   = 'nlk-shop-' . ($block['id'] ?? uniqid());
$className  = 'nlk-shop';
if (!empty($block['className'])) $className .= ' ' . $block['className'];
if (!empty($block['align']))     $className .= ' align' . $block['align'];

// Estado inicial (si venís desde categoría o ?cat=slug)
$selected_term_id = 0;
if ( function_exists('is_product_category') && is_product_category() ) {
  $qo = get_queried_object();
  if ($qo && !empty($qo->term_id)) $selected_term_id = (int) $qo->term_id;
} elseif (!empty($_GET['cat'])) {
  $t = get_term_by('slug', sanitize_text_field(wp_unslash($_GET['cat'])), 'product_cat');
  if ($t && !is_wp_error($t)) $selected_term_id = (int) $t->term_id;
}

// Config
$per_page = 12;
$paged    = 1;
$order    = 'stock';

// Query inicial
$args = [
  'post_type'      => 'product',
  'post_status'    => 'publish',
  'posts_per_page' => $per_page,
  'paged'          => $paged,
];

if ($selected_term_id) {
  $args['tax_query'] = [[
    'taxonomy' => 'product_cat',
    'field'    => 'term_id',
    'terms'    => [$selected_term_id],
  ]];
}

// Orden inicial
switch ($order) {
  case 'price_desc':
    $args['meta_key'] = '_price';
    $args['orderby']  = 'meta_value_num';
    $args['order']    = 'DESC';
    break;
  case 'price_asc':
    $args['meta_key'] = '_price';
    $args['orderby']  = 'meta_value_num';
    $args['order']    = 'ASC';
    break;
  case 'date_asc':
    $args['orderby']  = 'date';
    $args['order']    = 'ASC';
    break;
  case 'date_desc':
    $args['orderby']  = 'date';
    $args['order']    = 'DESC';
    break;
  case 'stock':
  default:
    $args['meta_key'] = '_stock_status';
    $args['orderby']  = 'meta_value';
    $args['order']    = 'ASC';
    break;
}

$q = new WP_Query($args);

$selected_term = $selected_term_id ? get_term($selected_term_id, 'product_cat') : null;
$title_raw     = ($selected_term && !is_wp_error($selected_term)) ? $selected_term->name : 'TIENDA';

/**
 * Banner fijo del hero
 * 1) intenta leer ACF 'hero_banner_fijo'
 * 2) si no existe, usa una imagen hardcodeada del theme
 */
$hero_banner_fijo = function_exists('get_field') ? get_field('hero_banner_fijo') : '';
$fixed_banner_url = nlk_shop__normalize_image_url($hero_banner_fijo);

if (!$fixed_banner_url) {
  $fixed_banner_url = 'https://nalakalu.stag.host/wp-content/uploads/2026/04/pr.jpg';
}

// Hero siempre fijo
$banner_url = $fixed_banner_url;
$default_banner_url = $fixed_banner_url;

$count = (int) ($q->found_posts ?? 0);

$nonce   = wp_create_nonce('nlk_shop_nonce');
$ajaxUrl = admin_url('admin-ajax.php');

// Categorías (parents)
   $parents = get_terms([
  'taxonomy'   => 'product_cat',
  'hide_empty' => false,
  'parent'     => 0,
  'orderby'    => 'menu_order',
  'order'      => 'ASC',
]);

// Abrir parent si un child está activo
$active_parent_id = 0;
if ($selected_term_id) {
  $sel = get_term($selected_term_id, 'product_cat');
  if ($sel && !is_wp_error($sel) && !empty($sel->parent)) {
    $active_parent_id = (int) $sel->parent;
  }
}

// Uppercase seguro
$title = strtoupper($title_raw);
$breadcrumbs_html = function_exists('nlk_shop__get_breadcrumbs_html')
  ? nlk_shop__get_breadcrumbs_html($selected_term_id)
  : '';
$breadcrumbs_html = nlk_shop__get_breadcrumbs_html($selected_term_id);
?>
<section
  id="<?php echo esc_attr($block_id); ?>"
  class="<?php echo esc_attr($className); ?>"
  data-ajax-url="<?php echo esc_attr($ajaxUrl); ?>"
  data-nonce="<?php echo esc_attr($nonce); ?>"
  data-per-page="<?php echo esc_attr($per_page); ?>"
  data-term-id="<?php echo esc_attr($selected_term_id); ?>"
  data-order="<?php echo esc_attr($order); ?>"
  data-default-banner="<?php echo esc_attr($default_banner_url); ?>"
  data-fixed-banner="<?php echo esc_attr($fixed_banner_url); ?>"
>
  <div class="nlk-shop__breadcrumbs-wrap" data-role="breadcrumbs">
  <?php echo $breadcrumbs_html; ?>
</div>
  <div
    class="nlk-shop__banner"
    style="<?php echo $banner_url ? '--nlk-shop-banner:url(' . esc_url($banner_url) . ');' : ''; ?>"
  >
    <div class="nlk-shop__banner-media" aria-hidden="true"></div>
  </div>

  <div class="nlk-shop__mobile-actions" aria-label="<?php echo esc_attr__('Acciones', 'textdomain'); ?>">
    <button type="button" class="nlk-shop__mobile-btn" data-action="open-sort">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
        <path d="M5.66976 1.77293C5.76917 1.70831 5.89733 1.70831 5.99675 1.77293L10.6967 4.82793C10.7819 4.88327 10.8333 4.97792 10.8333 5.07946V6.25718C10.8333 6.49522 10.5693 6.63844 10.3698 6.50872L6.66659 4.10166V13.8683C6.66659 14.034 6.53227 14.1683 6.36659 14.1683H5.29992C5.13423 14.1683 4.99992 14.034 4.99992 13.8683V4.10166L1.29675 6.50872C1.09717 6.63844 0.833252 6.49522 0.833252 6.25718V5.07946C0.833252 4.97792 0.884618 4.88327 0.969755 4.82793L5.66976 1.77293ZM13.3333 15.9V6.13332C13.3333 5.96764 13.4676 5.83332 13.6333 5.83332H14.6999C14.8656 5.83332 14.9999 5.96764 14.9999 6.13332V15.9L18.7031 13.4929C18.9027 13.3632 19.1666 13.5064 19.1666 13.7445V14.9222C19.1666 15.0237 19.1152 15.1184 19.0301 15.1737L14.3301 18.2287C14.2307 18.2933 14.1025 18.2933 14.0031 18.2287L9.30309 15.1737C9.21795 15.1184 9.16659 15.0237 9.16659 14.9222V13.7445C9.16659 13.5064 9.43051 13.3632 9.63008 13.4929L13.3333 15.9Z" fill="#3D332B"/>
      </svg>
      <?php echo esc_html__('Ordenar', 'textdomain'); ?>
    </button>

    <button type="button" class="nlk-shop__mobile-btn" data-action="open-filter">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
        <path d="M3.75 5.83334H16.25M5.83333 10H14.1667M8.33333 14.1667H11.6667" stroke="#3D332B" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <?php echo esc_html__('Filtrar', 'textdomain'); ?>
    </button>
  </div>

  <div class="nlk-shop__modal" data-modal="sort" aria-hidden="true">
    <div class="nlk-shop__modal-overlay" data-action="close-modal"></div>
    <div class="nlk-shop__modal-panel" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__('Ordenar', 'textdomain'); ?>">
      <div class="nlk-shop__modal-head">
        <div class="nlk-shop__modal-title">
          <svg xmlns="http://www.w3.org/2000/svg" width="19" height="17" viewBox="0 0 19 17" fill="none">
            <path d="M4.8365 0.0484669C4.93592 -0.0161557 5.06408 -0.0161558 5.1635 0.0484667L9.8635 3.10347C9.94864 3.15881 10 3.25346 10 3.355V4.53272C10 4.77075 9.73608 4.91398 9.5365 4.78426L5.83333 2.37719V12.1439C5.83333 12.3095 5.69902 12.4439 5.53333 12.4439H4.46667C4.30098 12.4439 4.16667 12.3095 4.16667 12.1439V2.37719L0.463496 4.78426C0.26392 4.91398 0 4.77075 0 4.53272V3.355C0 3.25346 0.0513656 3.15881 0.136503 3.10347L4.8365 0.0484669ZM12.5 14.1755V4.40886C12.5 4.24318 12.6343 4.10886 12.8 4.10886H13.8667C14.0324 4.10886 14.1667 4.24318 14.1667 4.40886V14.1755L17.8698 11.7685C18.0694 11.6387 18.3333 11.782 18.3333 12.02V13.1977C18.3333 13.2993 18.282 13.3939 18.1968 13.4493L13.4968 16.5043C13.3974 16.5689 13.2693 16.5689 13.1698 16.5043L8.46984 13.4493C8.3847 13.3939 8.33333 13.2993 8.33333 13.1977V12.02C8.33333 11.782 8.59725 11.6387 8.79683 11.7685L12.5 14.1755Z" fill="#3D332B"/>
          </svg>
          <?php echo esc_html__('Ordenar', 'textdomain'); ?>
        </div>

        <button
          type="button"
          class="nlk-shop__modal-close"
          data-action="close-modal"
          aria-label="<?php echo esc_attr__('Cerrar', 'textdomain'); ?>"
        >×</button>
      </div>

      <div class="nlk-shop__modal-body">
        <div class="nlk-shop__modal-body-s">
          <div class="nlk-shop__order-section nlk-shop__order-section--modal">
            <div class="nlk-shop__order-content">
              <label class="nlk-shop__radio-option">
                <input type="radio" name="<?php echo esc_attr($block_id); ?>-order" value="stock" <?php checked($order === 'stock'); ?>>
                En stock
              </label>

              <label class="nlk-shop__radio-option">
                <input type="radio" name="<?php echo esc_attr($block_id); ?>-order" value="price_desc" <?php checked($order === 'price_desc'); ?>>
                Precio: Mayor a menor
              </label>

              <label class="nlk-shop__radio-option">
                <input type="radio" name="<?php echo esc_attr($block_id); ?>-order" value="price_asc" <?php checked($order === 'price_asc'); ?>>
                Precio: Menor a mayor
              </label>

              <label class="nlk-shop__radio-option">
                <input type="radio" name="<?php echo esc_attr($block_id); ?>-order" value="date_desc" <?php checked($order === 'date_desc'); ?>>
                Lo más reciente
              </label>

              <label class="nlk-shop__radio-option">
                <input type="radio" name="<?php echo esc_attr($block_id); ?>-order" value="date_asc" <?php checked($order === 'date_asc'); ?>>
                Lo más antiguo
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="nlk-shop__modal" data-modal="filter" aria-hidden="true">
    <div class="nlk-shop__modal-overlay" data-action="close-modal"></div>
    <div class="nlk-shop__modal-panel" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__('Filtrar', 'textdomain'); ?>">
      <div class="nlk-shop__modal-head">
        <div class="nlk-shop__modal-title">
          <svg xmlns="http://www.w3.org/2000/svg" width="19" height="20" viewBox="0 0 19 20" fill="none">
            <path d="M3.75 5.83334H16.25M5.83333 10H14.1667M8.33333 14.1667H11.6667" stroke="#3D332B" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <?php echo esc_html__('Filtrar', 'textdomain'); ?>
        </div>
        <button type="button" class="nlk-shop__modal-close" data-action="close-modal" aria-label="<?php echo esc_attr__('Cerrar', 'textdomain'); ?>">×</button>
      </div>
      <div class="nlk-shop__modal-body" data-role="filter-modal-body">
        <!-- se mueve .nlk-shop__filter-box acá -->
      </div>
    </div>
  </div>



  <div class="nlk-shop__page-header">
    <div class="nlk-shop__header">
      <div class="column-header-primary"></div>

      <div class="column-header-secondary">
        <h1 class="nlk-shop__title" data-role="title"><?php echo esc_html($title); ?></h1>
        <span class="nlk-shop__count" data-role="count">
          <?php echo esc_html( sprintf(_n('%s Producto', '%s Productos', $count, 'textdomain'), number_format_i18n($count)) ); ?>
        </span>
      </div>
    </div>
  </div>

  <div class="nlk-shop__full-divider"></div>

  <div class="nlk-shop__container">

    <aside class="nlk-shop__sidebar">
      <div class="nlk-shop__filter-box">
        <h2 class="nlk-shop__filter-title">FILTROS</h2>

        <div class="nlk-shop__filter-content">
          <?php if (!empty($parents) && !is_wp_error($parents)) : ?>
            <?php foreach ($parents as $parent) : ?>
              <?php
              $children = get_terms([
  'taxonomy'   => 'product_cat',
  'hide_empty' => false,
  'parent'     => $parent->term_id,
  'orderby'    => 'menu_order',
  'order'      => 'ASC',
]);
                $has_children = !empty($children) && !is_wp_error($children);
                $is_open = $has_children && ((int)$parent->term_id === (int)$active_parent_id);
              ?>
              <button
                type="button"
                class="nlk-shop__filter-item nlk-shop__filter-parent nlk-shop__cat-btn <?php echo ((int)$parent->term_id === (int)$selected_term_id) ? 'is-active' : ''; ?>"
                data-term-id="<?php echo esc_attr($parent->term_id); ?>"
                data-parent="<?php echo esc_attr($parent->term_id); ?>"
                aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>"
              >
                <span class="nlk-shop__filter-label"><?php echo esc_html($parent->name); ?></span>

                <?php if ($has_children) : ?>
                  <span class="nlk-shop__expand-icon" data-action="toggle-submenu" aria-hidden="true">
                    <?php echo $is_open ? '−' : '+'; ?>
                  </span>
                <?php endif; ?>
              </button>

              <?php if ($has_children) : ?>
                <div class="nlk-shop__submenu" data-submenu="<?php echo esc_attr($parent->term_id); ?>" style="<?php echo $is_open ? '' : 'display:none;'; ?>">
                  <?php foreach ($children as $child) : ?>
                    <button
                      type="button"
                      class="nlk-shop__filter-subitem nlk-shop__cat-btn <?php echo ((int)$child->term_id === (int)$selected_term_id) ? 'is-active' : ''; ?>"
                      data-term-id="<?php echo esc_attr($child->term_id); ?>"
                    >
                      <?php echo esc_html($child->name); ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="nlk-shop__order-section">
          <h3 class="nlk-shop__order-title">ORDENAR POR:</h3>
          <div class="nlk-shop__order-content">
            <label class="nlk-shop__radio-option">
              <input type="radio" name="<?php echo esc_attr($block_id); ?>-order" value="stock" <?php checked($order === 'stock'); ?>>
              En stock
            </label>

            <label class="nlk-shop__radio-option">
              <input type="radio" name="<?php echo esc_attr($block_id); ?>-order" value="price_desc" <?php checked($order === 'price_desc'); ?>>
              Precio: Mayor a menor
            </label>

            <label class="nlk-shop__radio-option">
              <input type="radio" name="<?php echo esc_attr($block_id); ?>-order" value="price_asc" <?php checked($order === 'price_asc'); ?>>
              Precio: Menor a mayor
            </label>

            <label class="nlk-shop__radio-option">
              <input type="radio" name="<?php echo esc_attr($block_id); ?>-order" value="date_desc" <?php checked($order === 'date_desc'); ?>>
              Lo más reciente
            </label>

            <label class="nlk-shop__radio-option">
              <input type="radio" name="<?php echo esc_attr($block_id); ?>-order" value="date_asc" <?php checked($order === 'date_asc'); ?>>
              Lo más antiguo
            </label>

            <div class="nlk-shop__filter-item nlk-shop__filter-item--static">
              <span>Por ubicación</span>
              <span class="nlk-shop__expand-icon">+</span>
            </div>
          </div>
        </div>
      </div>
    </aside>

    <main class="nlk-shop__main">
      <div class="nlk-shop__grid" data-role="grid">
        <?php if ($q->have_posts()) : ?>
          <?php while ($q->have_posts()) : $q->the_post(); ?>
            <?php
              $product = function_exists('wc_get_product') ? wc_get_product(get_the_ID()) : null;
              $img = get_the_post_thumbnail_url(get_the_ID(), 'large');
              if (!$img && function_exists('wc_placeholder_img_src')) $img = wc_placeholder_img_src('large');
            ?>
            <a class="nlk-shop__card" href="<?php the_permalink(); ?>">
              <img class="nlk-shop__img" src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr(get_the_title()); ?>">
              <div class="nlk-shop__info">
                <div class="nlk-shop__name"><?php the_title(); ?></div>
                <div class="nlk-shop__price"><?php echo $product ? wp_kses_post($product->get_price_html()) : ''; ?></div>
              </div>
            </a>
          <?php endwhile; wp_reset_postdata(); ?>
        <?php else: ?>
          <div class="nlk-shop__empty">No hay productos para esta categoría.</div>
        <?php endif; ?>
      </div>

      <div class="nlk-shop__pagination" data-role="pagination">
        <?php
          $total_pages = max(1, (int) $q->max_num_pages);
          $current = max(1, (int) $paged);
        ?>
        <button class="nlk-shop__page-btn nlk-shop__pagination-btn" data-page="<?php echo esc_attr(max(1, $current-1)); ?>" <?php disabled($current <= 1); ?>>
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none">
            <path d="M6.96785 13.8533C7.01866 13.9011 7.0786 13.9387 7.14425 13.9638C7.2099 13.989 7.27997 14.0012 7.35047 13.9999C7.49284 13.9972 7.62829 13.9393 7.72701 13.839C7.82574 13.7386 7.87966 13.604 7.8769 13.4648C7.87415 13.3256 7.81496 13.1931 7.71234 13.0966L1.78861 7.52432L15.2131 7.52432C15.3555 7.52432 15.4921 7.46901 15.5927 7.37055C15.6934 7.27209 15.75 7.13854 15.75 6.9993C15.75 6.86005 15.6934 6.72651 15.5927 6.62805C15.4921 6.52959 15.3555 6.47427 15.2131 6.47427L1.79004 6.47427L7.71305 0.90341C7.76387 0.855607 7.80455 0.798484 7.83279 0.735305C7.86102 0.672125 7.87626 0.604126 7.87762 0.535189C7.87898 0.466252 7.86645 0.397728 7.84073 0.333528C7.81501 0.26933 7.77661 0.210712 7.72773 0.161024C7.67885 0.111336 7.62043 0.0715501 7.55582 0.0439384C7.49122 0.0163257 7.42168 0.00142741 7.35118 9.5129e-05C7.28069 -0.00123715 7.21062 0.0110214 7.14497 0.0361717C7.07931 0.061321 7.01937 0.09887 6.96856 0.146673L0.219443 6.49527C0.15005 6.56059 0.0948477 6.63892 0.0571413 6.72559C0.019435 6.81225 0 6.90547 0 6.99965C0 7.09383 0.019435 7.18705 0.0571413 7.27371C0.0948477 7.36038 0.15005 7.43871 0.219443 7.50402L6.96785 13.8533Z" fill="#3D332B"/>
          </svg>
        </button>

        <span class="nlk-shop__page-info"><?php echo esc_html($current . ' of ' . $total_pages); ?></span>

        <button class="nlk-shop__page-btn nlk-shop__pagination-btn" data-page="<?php echo esc_attr(min($total_pages, $current+1)); ?>" <?php disabled($current >= $total_pages); ?>>
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none">
            <path d="M8.78215 13.8533C8.73134 13.9011 8.6714 13.9387 8.60575 13.9638C8.5401 13.989 8.47003 14.0012 8.39953 13.9999C8.25716 13.9972 8.12171 13.9393 8.02299 13.839C7.92426 13.7386 7.87034 13.604 7.8731 13.4648C7.87585 13.3256 7.93504 13.1931 8.03766 13.0966L13.9614 7.52432L0.536892 7.52432C0.394499 7.52432 0.257937 7.46901 0.15725 7.37055C0.0565633 7.27209 -1.93715e-06 7.13854 -1.93715e-06 6.9993C-1.93715e-06 6.86005 0.0565633 6.72651 0.15725 6.62805C0.257937 6.52959 0.394499 6.47427 0.536892 6.47427L13.96 6.47427L8.03695 0.90341C7.98613 0.855607 7.94545 0.798484 7.91721 0.735305C7.88898 0.672125 7.87374 0.604126 7.87238 0.535189C7.87102 0.466252 7.88355 0.397728 7.90927 0.333528C7.93499 0.26933 7.97339 0.210712 8.02227 0.161024C8.07115 0.111336 8.12957 0.0715501 8.19418 0.0439384C8.25878 0.0163257 8.32832 0.00142741 8.39882 9.5129e-05C8.46931 -0.00123715 8.53938 0.0110214 8.60503 0.0361717C8.67069 0.061321 8.73063 0.09887 8.78144 0.146673L15.5306 6.49527C15.6 6.56059 15.6552 6.63892 15.6929 6.72559C15.7306 6.81225 15.75 6.90547 15.75 6.99965C15.75 7.09383 15.7306 7.18705 15.6929 7.27371C15.6552 7.36038 15.6 7.43871 15.5306 7.50402L8.78215 13.8533Z" fill="#3D332B"/>
          </svg>
        </button>
      </div>
    </main>
  </div>
</section>

<script>
(function(){
  window.NLKShop = window.NLKShop || (function(){
    function qs(el, s){ return el.querySelector(s); }
    function qsa(el, s){ return Array.prototype.slice.call(el.querySelectorAll(s)); }

    function setLoading(root, isLoading){
      root.classList.toggle('is-loading', !!isLoading);
    }

    async function loadShop(root, { termId, page, order }){
      const ajaxUrl = root.dataset.ajaxUrl;
      const nonce   = root.dataset.nonce;
      const perPage = root.dataset.perPage;

      setLoading(root, true);

      const fd = new FormData();
      fd.append('action', 'nlk_shop_filter_breadcrumbs');
      fd.append('nonce', nonce);
      fd.append('term_id', termId || 0);
      fd.append('page', page || 1);
      fd.append('order', order || 'stock');
      fd.append('per_page', perPage || 12);

      try {
        const res = await fetch(ajaxUrl, { method:'POST', body: fd, credentials:'same-origin' });
        const json = await res.json();

        if (!json || !json.success) {
          throw new Error((json && json.data && json.data.message) ? json.data.message : 'Error AJAX');
        }

        const data = json.data;

        // Banner fijo: no cambia con categorías
        const banner = qs(root, '.nlk-shop__banner');
        if (banner) {
          const fixedBanner = root.dataset.fixedBanner || root.dataset.defaultBanner || '';
          if (fixedBanner) {
            banner.style.setProperty('--nlk-shop-banner', `url("${fixedBanner}")`);
          }
        }

        // header
        const titleEl = qs(root, '[data-role="title"]');
        const countEl = qs(root, '[data-role="count"]');
        if (titleEl) titleEl.textContent = (data.title || '').toUpperCase();
        if (countEl) countEl.textContent = data.count_label || '';
        
      // breadcrumbs
const breadcrumbsEl = qs(root, '[data-role="breadcrumbs"]');
if (breadcrumbsEl && typeof data.breadcrumbs_html !== 'undefined') {
  breadcrumbsEl.innerHTML = data.breadcrumbs_html || '';
}

        // grid + pagination
        const grid = qs(root, '[data-role="grid"]');
        const pag  = qs(root, '[data-role="pagination"]');
        if (grid) grid.innerHTML = data.grid_html || '';
        if (pag)  pag.innerHTML  = data.pagination_html || '';

        // active state categories
        qsa(root, '.nlk-shop__cat-btn').forEach(btn => {
          btn.classList.toggle('is-active', String(btn.dataset.termId) === String(data.active_term_id));
        });

        // guardar estado
        root.dataset.termId = data.active_term_id || 0;
        root.dataset.order  = data.order || 'stock';

        // sync radios
        qsa(root, 'input[type="radio"][name$="-order"]').forEach(r => {
          r.checked = (r.value === (data.order || 'stock'));
        });

        // scroll suave al header
        const header = qs(root, '.nlk-shop__page-header');
        if (header) header.scrollIntoView({ behavior: 'smooth', block: 'start' });

      } catch (e) {
        console.error(e);
      } finally {
        setLoading(root, false);
      }
    }

    function initRoot(root){
      if (!root || root.__nlkShopInited) return;
      root.__nlkShopInited = true;

      let movedFilterBox = null;
      let sidebarPlaceholder = null;

      function isMobile(){
        return window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
      }

      function getModal(name){
        return qs(root, `.nlk-shop__modal[data-modal="${name}"]`);
      }

      function openModal(name){
        const modal = getModal(name);
        if (!modal) return;

        if (name === 'filter' && isMobile()) {
          const modalBody = qs(root, '[data-role="filter-modal-body"]');
          const filterBox = qs(root, '.nlk-shop__sidebar .nlk-shop__filter-box');
          if (modalBody && filterBox) {
            if (!sidebarPlaceholder) {
              sidebarPlaceholder = document.createElement('div');
              sidebarPlaceholder.className = 'nlk-shop__sidebar-placeholder';
              filterBox.parentNode.insertBefore(sidebarPlaceholder, filterBox);
            }
            movedFilterBox = filterBox;
            movedFilterBox.classList.add('is-in-modal');
            modalBody.appendChild(movedFilterBox);
          }
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('nlk-shop-modal-open');
        document.body.classList.add('nlk-shop-modal-open');
      }

      function closeModals(){
        qsa(root, '.nlk-shop__modal.is-open').forEach(m => {
          m.classList.remove('is-open');
          m.setAttribute('aria-hidden', 'true');
        });

        if (movedFilterBox && sidebarPlaceholder && sidebarPlaceholder.parentNode) {
          movedFilterBox.classList.remove('is-in-modal');
          sidebarPlaceholder.parentNode.insertBefore(movedFilterBox, sidebarPlaceholder);
        }

        document.documentElement.classList.remove('nlk-shop-modal-open');
        document.body.classList.remove('nlk-shop-modal-open');
      }

      qsa(root, '[data-action="open-sort"]').forEach(btn => {
        btn.addEventListener('click', () => openModal('sort'));
      });

      qsa(root, '[data-action="open-filter"]').forEach(btn => {
        btn.addEventListener('click', () => openModal('filter'));
      });

      root.addEventListener('click', (ev) => {
        const close = ev.target.closest('[data-action="close-modal"]');
        if (!close) return;
        closeModals();
      });

      if (!window.__nlkShopEscAttached) {
        window.__nlkShopEscAttached = true;
        document.addEventListener('keydown', (ev) => {
          if (ev.key === 'Escape') {
            document.querySelectorAll('.nlk-shop').forEach((r) => {
              r.classList.remove('nlk-shop-modal-open');
            });
            document.documentElement.classList.remove('nlk-shop-modal-open');
            document.body.classList.remove('nlk-shop-modal-open');

            document.querySelectorAll('.nlk-shop__modal.is-open').forEach(m => {
              m.classList.remove('is-open');
              m.setAttribute('aria-hidden', 'true');
            });
          }
        });
      }

      window.addEventListener('resize', () => {
        if (!isMobile()) closeModals();
      });

      root.addEventListener('click', (ev) => {
        const toggle = ev.target.closest('[data-action="toggle-submenu"]');
        if (toggle && root.contains(toggle)) {
          ev.preventDefault();
          ev.stopPropagation();

          const parentBtn = toggle.closest('.nlk-shop__filter-parent');
          if (!parentBtn) return;

          const parentId = parentBtn.dataset.parent;
          const submenu = qs(root, `.nlk-shop__submenu[data-submenu="${parentId}"]`);
          if (!submenu) return;

          const isOpen = submenu.style.display !== 'none';
          submenu.style.display = isOpen ? 'none' : 'block';
          parentBtn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');

          const icon = qs(parentBtn, '.nlk-shop__expand-icon');
          if (icon) icon.textContent = isOpen ? '+' : '−';

          return;
        }

        const pagBtn = ev.target.closest('.nlk-shop__pagination-btn');
        if (pagBtn && root.contains(pagBtn)) {
          if (pagBtn.hasAttribute('disabled')) return;

          const page   = parseInt(pagBtn.dataset.page || '1', 10);
          const termId = parseInt(root.dataset.termId || '0', 10);
          const order  = root.dataset.order || 'stock';
          loadShop(root, { termId, page, order });
          return;
        }

        const catBtn = ev.target.closest('.nlk-shop__cat-btn');
        if (!catBtn || !root.contains(catBtn)) return;

        const termId = parseInt(catBtn.dataset.termId || '0', 10);
        const order  = root.dataset.order || 'stock';
        loadShop(root, { termId, page: 1, order });
        closeModals();
      });

      qsa(root, 'input[type="radio"][name$="-order"]').forEach(r => {
        r.addEventListener('change', () => {
          if (!r.checked) return;
          const termId = parseInt(root.dataset.termId || '0', 10);
          loadShop(root, { termId, page: 1, order: r.value });

          if (isMobile()) closeModals();
        });
      });
    }

    return { initRoot };
  })();

  window.NLKShop.initRoot(document.getElementById('<?php echo esc_js($block_id); ?>'));
})();
</script>