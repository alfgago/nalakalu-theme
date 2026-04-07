<?php
defined('ABSPATH') || exit;

get_header();

if ( function_exists('is_shop') && is_shop() ) {

  // Renderiza SOLO el contenido de la página Shop (donde está tu bloque)
  $shop_id = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0;

  if ($shop_id && $shop_id > 0) {
    $shop_post = get_post($shop_id);
    if ($shop_post) {
      echo '<main class="nlk-woo-shop">';
      echo apply_filters('the_content', $shop_post->post_content);
      echo '</main>';
    }
  }

} else {

  // Para el resto (categorías, tags, etc.) dejás Woo normal
  if (function_exists('woocommerce_content')) {
    woocommerce_content();
  }
}

get_footer();
