<?php
/**
 * Page template: Cart (slug: cart)
 * Archivo: page-cart.php
 */
defined('ABSPATH') || exit;

get_header();

// Asegura carrito cargado
if ( function_exists('wc_load_cart') ) {
  wc_load_cart();
}
?>

<main id="primary" class="site-main nlk-cart-page">
  <?php while ( have_posts() ) : the_post(); ?>

    <?php
      // Renderiza contenido de la página pero SIN el shortcode del carrito (para que no duplique)
      $raw = get_post_field('post_content', get_the_ID());
      $raw = preg_replace('/\[woocommerce_cart\]/i', '', $raw);
      $content = apply_filters('the_content', $raw);

      if ( trim(wp_strip_all_tags($content)) !== '' ) {
        echo '<div class="nlk-cart-page-content">' . $content . '</div>';
      }
    ?>

    <div class="woocommerce">

      <?php
        // Dejá que Woo maneje notices, no las borres.
        do_action('woocommerce_before_cart');

        if ( ! WC()->cart || WC()->cart->is_empty() ) {
          wc_get_template('cart/cart-empty.php');
          do_action('woocommerce_cart_is_empty');
          do_action('woocommerce_after_cart');
          echo '</div>'; // .woocommerce
          break;
        }

        $items_count = WC()->cart->get_cart_contents_count();
      ?>

      <div class="nlk-cart-layout">

        <!-- LEFT (FORM del carrito SOLO acá) -->
        <section class="nlk-cart-left">

          <form class="woocommerce-cart-form nlk-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">

            <div class="nlk-card nlk-card--cart">
              <header class="nlk-cart-header">
                <div class="nlk-cart-header__title">
                  <span class="nlk-cart-header__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                      <path d="M15.998 10C15.998 11.0609 15.5766 12.0783 14.8265 12.8284C14.0763 13.5786 13.0589 14 11.998 14C10.9372 14 9.91977 13.5786 9.16962 12.8284C8.41947 12.0783 7.99805 11.0609 7.99805 10" stroke="#3D332B" stroke-width="1.45833" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M3.10156 6.03516H20.8956" stroke="#3D332B" stroke-width="1.45833" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M3.4 5.467C3.14036 5.81319 3 6.23426 3 6.667V20C3 20.5304 3.21071 21.0391 3.58579 21.4142C3.96086 21.7893 4.46957 22 5 22H19C19.5304 22 20.0391 21.7893 20.4142 21.4142C20.7893 21.0391 21 20.5304 21 20V6.667C21 6.23426 20.8596 5.81319 20.6 5.467L18.6 2.8C18.4137 2.55161 18.1721 2.35 17.8944 2.21115C17.6167 2.07229 17.3105 2 17 2H7C6.68951 2 6.38328 2.07229 6.10557 2.21115C5.82786 2.35 5.58629 2.55161 5.4 2.8L3.4 5.467Z" stroke="#3D332B" stroke-width="1.45833" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </span>

                  <h1 class="nlk-cart-header__h">
                    <?php echo esc_html( sprintf('Carrito de compras (%d items)', (int) $items_count ) ); ?>
                  </h1>
                </div>
              </header>

              <div class="nlk-cart-items">
                <?php do_action('woocommerce_before_cart_contents'); ?>

                <?php foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
                  $_product   = $cart_item['data'];
                  $product_id = $cart_item['product_id'];

                  if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 ) continue;
                  if ( ! apply_filters('woocommerce_cart_item_visible', true, $cart_item, $cart_item_key) ) continue;

                  $product_permalink = $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '';
                  $thumbnail = $_product->get_image('woocommerce_thumbnail');
                  $product_name = $_product->get_name();
                  $product_subtotal = WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] );

                  $quantity_html = woocommerce_quantity_input(
                    array(
                      'input_name'  => "cart[{$cart_item_key}][qty]",
                      'input_value' => $cart_item['quantity'],
                      'min_value'   => 0,
                      'max_value'   => $_product->get_max_purchase_quantity(),
                      'product_name'=> $product_name,
                    ),
                    $_product,
                    false
                  );

                  $remove_url = wc_get_cart_remove_url( $cart_item_key );
                ?>
                  <article class="nlk-cart-item cart_item">
                    <div class="nlk-cart-item__thumb">
                      <?php if ( $product_permalink ) : ?>
                        <a href="<?php echo esc_url($product_permalink); ?>"><?php echo $thumbnail; ?></a>
                      <?php else : ?>
                        <?php echo $thumbnail; ?>
                      <?php endif; ?>
                    </div>

                    <div class="nlk-cart-item__body">
                      <div class="nlk-cart-item__top">
                        <div class="nlk-cart-item__info">
                          <h3 class="nlk-cart-item__name">
                            <?php if ( $product_permalink ) : ?>
                              <a href="<?php echo esc_url($product_permalink); ?>"><?php echo esc_html($product_name); ?></a>
                            <?php else : ?>
                              <?php echo esc_html($product_name); ?>
                            <?php endif; ?>
                          </h3>

                          <div class="nlk-cart-item__meta">
                            <?php echo wc_get_formatted_cart_item_data( $cart_item ); ?>
                          </div>
                        </div>

                        <div class="nlk-cart-item__price">
                          <?php echo $product_subtotal; ?>
                        </div>
                      </div>

                      <div class="nlk-cart-item__bottom">
                        <div class="nlk-cart-qty" data-cart-key="<?php echo esc_attr($cart_item_key); ?>">
                          <button type="button" class="nlk-qty-btn" data-action="minus" aria-label="Disminuir">−</button>
                          <div class="nlk-qty-input"><?php echo $quantity_html; ?></div>
                          <button type="button" class="nlk-qty-btn" data-action="plus" aria-label="Aumentar">+</button>
                        </div>

                        <div class="nlk-cart-item__remove">
                          <a href="<?php echo esc_url($remove_url); ?>" class="nlk-remove">
                            <span class="nlk-remove__icon" aria-hidden="true">
                              <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 17 17" fill="none">
                                <path d="M7.08398 7.79102V12.041" stroke="#D4183D" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M9.91602 7.79102V12.041" stroke="#D4183D" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M13.4577 4.25V14.1667C13.4577 14.5424 13.3084 14.9027 13.0428 15.1684C12.7771 15.4341 12.4167 15.5833 12.041 15.5833H4.95768C4.58196 15.5833 4.22162 15.4341 3.95595 15.1684C3.69027 14.9027 3.54102 14.5424 3.54102 14.1667V4.25" stroke="#D4183D" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M2.125 4.25H14.875" stroke="#D4183D" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M5.66602 4.24935V2.83268C5.66602 2.45696 5.81527 2.09662 6.08095 1.83095C6.34662 1.56527 6.70696 1.41602 7.08268 1.41602H9.91602C10.2917 1.41602 10.6521 1.56527 10.9177 1.83095C11.1834 2.09662 11.3327 2.45696 11.3327 2.83268V4.24935" stroke="#D4183D" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
                              </svg>
                            </span>
                            <span class="nlk-remove__text">Eliminar</span>
                          </a>
                        </div>
                      </div>

                    </div>
                  </article>
                <?php endforeach; ?>

                <?php do_action('woocommerce_cart_contents'); ?>
                <?php do_action('woocommerce_after_cart_contents'); ?>
              </div>
            </div>

            <!-- Botón manual de update (opcional, queda en la izquierda) -->
            <button type="submit" class="nlk-link-btn" name="update_cart" value="1">
              <?php esc_html_e('Actualizar Carrito','woocommerce'); ?>
            </button>

            <?php wp_nonce_field('woocommerce-cart','woocommerce-cart-nonce'); ?>
            <?php do_action('woocommerce_cart_actions'); ?>

          </form>

        </section>

        <!-- RIGHT (Cupón arriba + Totales/Envíos, TODO fuera del form del carrito) -->
        <aside class="nlk-cart-right">

          <?php if ( WC()->cart ) { WC()->cart->calculate_totals(); } ?>

          <!-- CUPÓN (derecha, arriba de envíos/totales) -->
          <?php if ( wc_coupons_enabled() ) : ?>
            <form class="nlk-coupon-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
              <div class="nlk-card nlk-card--coupon">
                <h2 class="nlk-card__title">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M12.586 2.586C12.211 2.2109 11.7024 2.00011 11.172 2H4C3.46957 2 2.96086 2.21071 2.58579 2.58579C2.21071 2.96086 2 3.46957 2 4V11.172C2.00011 11.7024 2.2109 12.211 2.586 12.586L11.29 21.29C11.7445 21.7416 12.3592 21.9951 13 21.9951C13.6408 21.9951 14.2555 21.7416 14.71 21.29L21.29 14.71C21.7416 14.2555 21.9951 13.6408 21.9951 13C21.9951 12.3592 21.7416 11.7445 21.29 11.29L12.586 2.586Z" stroke="#3D332B" stroke-width="1.45833" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M7.5 8C7.77614 8 8 7.77614 8 7.5C8 7.22386 7.77614 7 7.5 7C7.22386 7 7 7.22386 7 7.5C7 7.77614 7.22386 8 7.5 8Z" fill="#3D332B" stroke="#3D332B" stroke-width="1.45833" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  Promo Code
                </h2>

                <div class="nlk-coupon">
                  <div class="nlk-coupon__row">
                    <input type="text" name="coupon_code" class="input-text nlk-input" id="coupon_code" value=""
                      placeholder="<?php esc_attr_e('Código de Cupón','woocommerce'); ?>" />
                    <button type="submit" class="button nlk-btn nlk-btn--outline" name="apply_coupon" value="1">
                      <?php esc_html_e('Aplicar Cupón','woocommerce'); ?>
                    </button>
                  </div>
                </div>
              </div>

              <?php wp_nonce_field('woocommerce-cart','woocommerce-cart-nonce'); ?>
            </form>
          <?php endif; ?>

          <!-- TOTALES + ENVÍOS -->
          <div class="cart_totals nlk-card nlk-card--totals">
            <h2 class="nlk-card__title">Totales del carrito</h2>

            <div class="nlk-totals">

              <!-- Subtotal -->
              <div class="nlk-totals__row">
                <div class="nlk-totals__label"><?php esc_html_e('Subtotal','woocommerce'); ?></div>
                <div class="nlk-totals__value"><?php wc_cart_totals_subtotal_html(); ?></div>
              </div>

              <!-- Descuentos por cupón -->
              <?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
                <div class="nlk-totals__row nlk-totals__row--discount">
                  <div class="nlk-totals__label">
                    <?php echo esc_html( wc_cart_totals_coupon_label( $coupon ) ); ?>
                  </div>
                  <div class="nlk-totals__value"><?php wc_cart_totals_coupon_html( $coupon ); ?></div>
                </div>
              <?php endforeach; ?>

              <!-- Fees -->
              <?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
                <div class="nlk-totals__row nlk-totals__row--fee">
                  <div class="nlk-totals__label"><?php echo esc_html( $fee->name ); ?></div>
                  <div class="nlk-totals__value"><?php wc_cart_totals_fee_html( $fee ); ?></div>
                </div>
              <?php endforeach; ?>

              <!-- Envío -->
              <?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>
                <div class="nlk-totals__shipping">
                  <div class="nlk-totals__shipping-head">
                    <div class="nlk-totals__label"><?php esc_html_e('Envío','woocommerce'); ?></div>

                    <?php
                      $chosen_methods = WC()->session->get('chosen_shipping_methods');
                      $chosen_methods = is_array($chosen_methods) ? $chosen_methods : array();
                      $packages = WC()->shipping()->get_packages();

                      $summary_price = '';
                      if (!empty($packages)) {
                        $i = 0;
                        $chosen = $chosen_methods[$i] ?? '';
                        if (isset($packages[$i]['rates']) && $chosen && isset($packages[$i]['rates'][$chosen])) {
                          $rate = $packages[$i]['rates'][$chosen];
                          $summary_price = strip_tags( wc_cart_totals_shipping_method_label( $rate ) );
                        }
                      }
                    ?>

                    <?php if ($summary_price) : ?>
                      <div class="nlk-totals__ship-price"><?php echo esc_html($summary_price); ?></div>
                    <?php endif; ?>
                  </div>

                  <div class="nlk-totals__shipping-box">
                    <?php
                      $packages = WC()->shipping()->get_packages();
                      $chosen_methods = WC()->session->get('chosen_shipping_methods');
                      $chosen_methods = is_array($chosen_methods) ? $chosen_methods : array();

                      foreach ( $packages as $i => $package ) :
                        if ( empty($package['rates']) ) continue;

                        $chosen = $chosen_methods[$i] ?? '';

                        echo '<ul class="nlk-shipping-methods" data-package-index="' . esc_attr($i) . '">';

                        foreach ( $package['rates'] as $rate_id => $rate ) :
                          $input_id = 'nlk_ship_' . $i . '_' . sanitize_title($rate->id);

                          echo '<li class="nlk-shipping-method">';
                          echo '<input type="radio"
                                  name="shipping_method[' . esc_attr($i) . ']"
                                  data-index="' . esc_attr($i) . '"
                                  id="' . esc_attr($input_id) . '"
                                  value="' . esc_attr($rate->id) . '"
                                  class="shipping_method"
                                  ' . checked($rate->id, $chosen, false) . ' />';
                          echo '<label for="' . esc_attr($input_id) . '">';
                          echo wp_kses_post( wc_cart_totals_shipping_method_label( $rate ) );
                          echo '</label>';
                          echo '</li>';
                        endforeach;

                        echo '</ul>';

                        $dest_country = $package['destination']['country'] ?? '';
                        $dest_state   = $package['destination']['state'] ?? '';
                        $states       = $dest_country ? WC()->countries->get_states($dest_country) : array();
                        $dest_name    = ($dest_state && isset($states[$dest_state])) ? $states[$dest_state] : '';

                        if ($dest_name) {
                          echo '<p class="woocommerce-shipping-destination">Enviar a <strong>' . esc_html($dest_name) . '</strong>.</p>';
                        }

                      endforeach;

                      // Calculadora “Cambiar dirección” (ya NO está dentro del cart form)
                      if ( apply_filters('woocommerce_shipping_show_shipping_calculator', true) ) {
                        wc_get_template('cart/shipping-calculator.php');
                      }
                    ?>
                  </div>
                </div>
              <?php endif; ?>

              <!-- Impuestos -->
              <?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
                <?php if ( 'itemized' === get_option('woocommerce_tax_total_display') ) : ?>
                  <?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : ?>
                    <div class="nlk-totals__row nlk-totals__row--tax">
                      <div class="nlk-totals__label"><?php echo esc_html( $tax->label ); ?></div>
                      <div class="nlk-totals__value"><?php echo wp_kses_post( $tax->formatted_amount ); ?></div>
                    </div>
                  <?php endforeach; ?>
                <?php else : ?>
                  <div class="nlk-totals__row nlk-totals__row--tax">
                    <div class="nlk-totals__label"><?php esc_html_e('Impuestos','woocommerce'); ?></div>
                    <div class="nlk-totals__value"><?php wc_cart_totals_taxes_total_html(); ?></div>
                  </div>
                <?php endif; ?>
              <?php endif; ?>

              <!-- Total -->
              <div class="nlk-totals__row nlk-totals__row--total">
                <div class="nlk-totals__label"><?php esc_html_e('Total','woocommerce'); ?></div>
                <div class="nlk-totals__value nlk-totals__value--total"><?php wc_cart_totals_order_total_html(); ?></div>
              </div>

              <!-- Botón -->
              <div class="nlk-totals__cta">
                <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="checkout-button button alt wc-forward nlk-checkout-btn">
                  <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 22 22" fill="none">
                    <path d="M18.3281 4.58203H3.66146C2.64894 4.58203 1.82812 5.40284 1.82812 6.41536V15.582C1.82812 16.5946 2.64894 17.4154 3.66146 17.4154H18.3281C19.3406 17.4154 20.1615 16.5946 20.1615 15.582V6.41536C20.1615 5.40284 19.3406 4.58203 18.3281 4.58203Z" stroke="white" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M1.82812 9.16797H20.1615" stroke="white" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  Siguiente
                </a>
              </div>

            </div>
          </div>
          <div class="legend-text"><p>En Na Lakalú, cada pieza es elaborada bajo procesos cuidadosamente supervisados para garantizar la calidad y los acabados que distinguen a nuestra marca. Nuestros tiempo de entrega es de 30 a 45 días hábiles. Una de nuestras asesoras se comunicará con usted para coordinar los detalles de su pedido y brindarle el acompañamiento necesario durante el proceso.
</p></div>

        </aside>

      </div><!-- .nlk-cart-layout -->

      <?php do_action('woocommerce_after_cart'); ?>

    </div><!-- .woocommerce -->

    <!-- =========================
         SCRIPTS
         ========================= -->

    <script>
      // Botones +/-: actualizan el input qty y disparan change
      (function(){
        document.addEventListener("click", function(e){
          const btn = e.target.closest(".nlk-qty-btn");
          if (!btn) return;

          const wrap = btn.closest(".nlk-cart-qty");
          const input = wrap ? wrap.querySelector('input.qty') : null;
          if (!input) return;

          const step = parseFloat(input.getAttribute("step")) || 1;
          const min  = input.getAttribute("min") !== null ? parseFloat(input.getAttribute("min")) : 0;
          const max  = input.getAttribute("max") !== null && input.getAttribute("max") !== "" ? parseFloat(input.getAttribute("max")) : Infinity;

          let val = parseFloat(input.value) || 0;

          if (btn.dataset.action === "plus")  val = val + step;
          if (btn.dataset.action === "minus") val = val - step;

          val = Math.max(min, Math.min(max, val));
          input.value = String(val);

          input.dispatchEvent(new Event("change", { bubbles: true }));
        });
      })();
    </script>

    <script>
      (function($){
        if (typeof $ === 'undefined') return;

        let isUpdating = false;
        let qtyTimer = null;

        function setLoading(on){
          document.documentElement.classList.toggle('nlk-cart-loading', !!on);
        }

        function replaceFromHtml(html){
          const $html = $('<div>').append($.parseHTML(html));

          const $newNotices = $html.find('.woocommerce-notices-wrapper').first();
          const $newLayout  = $html.find('.nlk-cart-layout').first();

          if ($newNotices.length && $('.woocommerce-notices-wrapper').length) {
            $('.woocommerce-notices-wrapper').replaceWith($newNotices);
          }

          if ($newLayout.length && $('.nlk-cart-layout').length) {
            $('.nlk-cart-layout').replaceWith($newLayout);
          }

          $(document.body).trigger('updated_cart_totals');
        }

        function postHtml(url, dataArr){
          if (isUpdating) return $.Deferred().reject().promise();

          isUpdating = true;
          setLoading(true);

          return $.ajax({
            url: url,
            type: 'POST',
            data: $.param(dataArr || []),
          }).done(function(html){
            replaceFromHtml(html);
          }).always(function(){
            isUpdating = false;
            setLoading(false);
          });
        }

        function getHtml(url){
          if (isUpdating) return $.Deferred().reject().promise();

          isUpdating = true;
          setLoading(true);

          return $.get(url).done(function(html){
            replaceFromHtml(html);
          }).always(function(){
            isUpdating = false;
            setLoading(false);
          });
        }

        function getCartUrl(){
          const $form = $('form.woocommerce-cart-form').first();
          return ($form.length && $form.attr('action')) ? $form.attr('action') : window.location.href;
        }

        // 1) Qty change => UPDATE_CART por AJAX
        $(document).on('change', 'form.woocommerce-cart-form input.qty', function(){
          clearTimeout(qtyTimer);
          qtyTimer = setTimeout(function(){
            const $form = $('form.woocommerce-cart-form').first();
            if (!$form.length) return;

            const url = getCartUrl();
            let data = $form.serializeArray();

            data.push({name:'update_cart', value:'1'});

            postHtml(url, data);
          }, 300);
        });

        // 2) Submit del carrito => AJAX
        $(document).on('submit', 'form.woocommerce-cart-form', function(e){
          e.preventDefault();
          const $form = $(this);
          const url = getCartUrl();

          let data = $form.serializeArray();

          const submitter = e.originalEvent && e.originalEvent.submitter ? e.originalEvent.submitter : null;
          if (submitter && submitter.name) {
            const exists = data.some(x => x.name === submitter.name);
            if (!exists) data.push({name: submitter.name, value: submitter.value || '1'});
          }

          postHtml(url, data);
        });

        // 3) Cambio de método de envío => guardar por wc-ajax y refrescar TU template por GET
        function updateShippingAjax(){
          if (typeof window.wc_cart_params === 'undefined') {
            return getHtml(getCartUrl());
          }

          const shipping_method = {};

          $('input[name^="shipping_method"]:checked, select.shipping_method').each(function(){
            const name = this.name || '';
            const m = name.match(/\[(\d+)\]/);
            const idx = m ? m[1] : 0;
            shipping_method[idx] = $(this).val();
          });

          const data = {
            security: wc_cart_params.update_shipping_method_nonce,
            shipping_method: shipping_method
          };

          setLoading(true);

          $.post(
            wc_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'update_shipping_method'),
            data
          ).always(function(){
            getHtml(getCartUrl());
          });
        }

        // Evita que WooCommerce ejecute su refresh nativo de .cart_totals
document.addEventListener('change', function(e){
  const field = e.target.closest('.nlk-totals__shipping-box input[name^="shipping_method"], .nlk-totals__shipping-box select.shipping_method');
  if (!field) return;

  e.preventDefault();
  e.stopPropagation();
  e.stopImmediatePropagation();

  updateShippingAjax();
}, true);

        // 4) Shipping calculator submit => AJAX
        $(document).on('submit', 'form.woocommerce-shipping-calculator', function(e){
          e.preventDefault();
          const $calc = $(this);
          const url = getCartUrl();

          let data = $calc.serializeArray();

          const hasCalc = data.some(x => x.name === 'calc_shipping');
          if (!hasCalc) data.push({name:'calc_shipping', value:'1'});

          postHtml(url, data);
        });

        // 5) Cupón submit (form derecho) => AJAX
        $(document).on('submit', 'form.nlk-coupon-form', function(e){
          e.preventDefault();

          const $form = $(this);
          const url = getCartUrl();

          let data = $form.serializeArray();

          const submitter = e.originalEvent && e.originalEvent.submitter ? e.originalEvent.submitter : null;
          if (submitter && submitter.name) {
            const exists = data.some(x => x.name === submitter.name);
            if (!exists) data.push({name: submitter.name, value: submitter.value || '1'});
          }

          postHtml(url, data);
        });

      })(window.jQuery);
    </script>

  <?php endwhile; ?>
</main>

<?php get_footer(); ?>
