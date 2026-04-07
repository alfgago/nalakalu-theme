<?php
defined( 'ABSPATH' ) || exit;

$items_count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
?>

<div class="woocommerce-checkout-review-order-table nlk-checkout-review">

  <!-- LISTA DE ITEMS (como la imagen) -->
  <div class="nlk-checkout-items">
    <?php foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
      $_product = $cart_item['data'];
      if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 ) continue;

      $name      = $_product->get_name();
      $thumb     = $_product->get_image('woocommerce_thumbnail');
      $line_total = WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] );
      ?>
      <div class="nlk-checkout-item">
        <div class="nlk-checkout-item__thumb"><?php echo $thumb; ?></div>

        <div class="nlk-checkout-item__info">
          <div class="nlk-checkout-item__name"><?php echo esc_html($name); ?></div>

          <div class="nlk-checkout-item__meta">
            <?php echo wc_get_formatted_cart_item_data( $cart_item ); ?>
          </div>
        </div>

        <div class="nlk-checkout-item__price">
          <?php echo wp_kses_post($line_total); ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <hr class="nlk-checkout-sep" />

  <!-- TOTALES (reusando tus clases del cart) -->
  <div class="nlk-totals">

    <div class="nlk-totals__row">
      <div class="nlk-totals__label">
        <?php echo esc_html( sprintf( 'Subtotal (%d items)', (int) $items_count ) ); ?>
      </div>
      <div class="nlk-totals__value"><?php wc_cart_totals_subtotal_html(); ?></div>
    </div>

    <?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
      <div class="nlk-totals__row nlk-totals__row--discount">
        <div class="nlk-totals__label"><?php echo esc_html( wc_cart_totals_coupon_label( $coupon ) ); ?></div>
        <div class="nlk-totals__value"><?php wc_cart_totals_coupon_html( $coupon ); ?></div>
      </div>
    <?php endforeach; ?>

    <?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
      <div class="nlk-totals__row nlk-totals__row--fee">
        <div class="nlk-totals__label"><?php echo esc_html( $fee->name ); ?></div>
        <div class="nlk-totals__value"><?php wc_cart_totals_fee_html( $fee ); ?></div>
      </div>
    <?php endforeach; ?>

    <?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>
      <div class="nlk-totals__shipping">
        <div class="nlk-totals__shipping-head">
          <div class="nlk-totals__label"><?php esc_html_e('Envío','woocommerce'); ?></div>
          <div class="nlk-totals__ship-price"></div>
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
            endforeach;
          ?>
        </div>
      </div>
    <?php endif; ?>

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

    <div class="nlk-totals__row nlk-totals__row--total">
      <div class="nlk-totals__label"><?php esc_html_e('Total','woocommerce'); ?></div>
      <div class="nlk-totals__value nlk-totals__value--total"><?php wc_cart_totals_order_total_html(); ?></div>
    </div>

  </div>
</div>
