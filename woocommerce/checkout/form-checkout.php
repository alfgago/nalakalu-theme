<?php
defined( 'ABSPATH' ) || exit;

/** @var WC_Checkout $checkout */

do_action( 'woocommerce_before_checkout_form', $checkout );

// Si el checkout requiere registro y está deshabilitado
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout nlk-checkout-form"
      action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">

  <div class="nlk-checkout-layout">

    <!-- LEFT -->
    <section class="nlk-checkout-left">
      <div class="nlk-card nlk-card--checkout">
        <header class="nlk-checkout-header">
          <h2 class="nlk-checkout-title"><?php esc_html_e('Datos de envío','woocommerce'); ?></h2>
        </header>

        <div class="nlk-checkout-fields">
          <?php
            // Billing + Shipping + Notas (order notes suelen venir dentro del shipping template)
            do_action( 'woocommerce_checkout_before_customer_details' );

            if ( $checkout->get_checkout_fields() ) {
              do_action( 'woocommerce_checkout_billing' );
              do_action( 'woocommerce_checkout_shipping' );
            }

            do_action( 'woocommerce_checkout_after_customer_details' );
          ?>
        </div>
      </div>
    </section>

    <!-- RIGHT -->
    <aside class="nlk-checkout-right">
      <div class="nlk-card nlk-card--totals">
        <h2 class="nlk-card__title"><?php esc_html_e('Totales del carrito','woocommerce'); ?></h2>
        <?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

        <div id="order_review" class="woocommerce-checkout-review-order">
          <?php do_action( 'woocommerce_checkout_order_review' ); ?>
        </div>

        <?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
      </div>
    </aside>

  </div>

</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
