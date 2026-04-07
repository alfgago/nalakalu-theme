<?php
defined( 'ABSPATH' ) || exit;

if ( ! is_ajax() ) {
	do_action( 'woocommerce_review_order_before_payment' );
}
?>

<div id="payment" class="woocommerce-checkout-payment">

	<?php if ( WC()->cart->needs_payment() ) : ?>
		<ul class="wc_payment_methods payment_methods methods">
			<?php
			if ( ! empty( $available_gateways ) ) {
				foreach ( $available_gateways as $gateway ) {
					wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $gateway ) );
				}
			} else {
				echo '<li class="woocommerce-notice woocommerce-notice--info woocommerce-info">';
				echo esc_html( apply_filters( 'woocommerce_no_available_payment_methods_message', __( 'Sorry, it seems that there are no available payment methods. Please contact us if you require assistance or wish to make alternate arrangements.', 'woocommerce' ) ) );
				echo '</li>';
			}
			?>
		</ul>
	<?php endif; ?>

	<div class="form-row place-order">
		<noscript>
			<?php
			printf(
				/* translators: $1 and $2 opening and closing emphasis tags respectively */
				esc_html__( 'Since your browser does not support JavaScript, or it is disabled, please ensure you click the %1$sUpdate Totals%2$s button before placing your order.', 'woocommerce' ),
				'<em>',
				'</em>'
			);
			?>
			<br/>
			<button type="submit" class="button alt" name="woocommerce_checkout_update_totals" value="<?php esc_attr_e( 'Update totals', 'woocommerce' ); ?>">
				<?php esc_html_e( 'Update totals', 'woocommerce' ); ?>
			</button>
		</noscript>

		<?php wc_get_template( 'checkout/terms.php' ); ?>

		<?php do_action( 'woocommerce_review_order_before_submit' ); ?>

		<?php
			$order_button_text = apply_filters( 'woocommerce_order_button_text', __( 'Finalizar compra', 'woocommerce' ) );
		?>

		<div class="nlk-totals__cta">
			<button type="submit"
			        class="button alt nlk-checkout-btn"
			        name="woocommerce_checkout_place_order"
			        id="place_order"
			        value="<?php echo esc_attr( $order_button_text ); ?>"
			        data-value="<?php echo esc_attr( $order_button_text ); ?>">

        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 22 22" fill="none">
          <path d="M18.3281 4.58203H3.66146C2.64894 4.58203 1.82812 5.40284 1.82812 6.41536V15.582C1.82812 16.5946 2.64894 17.4154 3.66146 17.4154H18.3281C19.3406 17.4154 20.1615 16.5946 20.1615 15.582V6.41536C20.1615 5.40284 19.3406 4.58203 18.3281 4.58203Z" stroke="white" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M1.82812 9.16797H20.1615" stroke="white" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>

				<?php echo esc_html( $order_button_text ); ?>
			</button>
		</div>

		<?php do_action( 'woocommerce_review_order_after_submit' ); ?>

		<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
	</div>

</div>

<?php
if ( ! is_ajax() ) {
	do_action( 'woocommerce_review_order_after_payment' );
}
?>
