<?php
/**
 * Source-site product export snippet for nalakalu.com.
 *
 * Add this to the active theme's functions.php on nalakalu.com, or load it with a snippets plugin.
 * Replace the token before using the endpoint.
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! defined('NLK_PRODUCT_EXPORT_TOKEN')) {
	define('NLK_PRODUCT_EXPORT_TOKEN', 'replace-this-with-a-long-random-token');
}

if (! class_exists('NLK_Source_Product_Export_Snippet')) {
	class NLK_Source_Product_Export_Snippet {
		const REST_NAMESPACE = 'nlk/v1';
		const REST_ROUTE     = '/product-export';

		public static function init() {
			add_action('rest_api_init', array(__CLASS__, 'register_rest_route'));
		}

		public static function register_rest_route() {
			register_rest_route(
				self::REST_NAMESPACE,
				self::REST_ROUTE,
				array(
					'methods'             => 'GET',
					'callback'            => array(__CLASS__, 'export_products'),
					'permission_callback' => array(__CLASS__, 'can_export_products'),
				)
			);
		}

		public static function can_export_products($request) {
			$token = (string) $request->get_param('token');
			return strlen(NLK_PRODUCT_EXPORT_TOKEN) >= 16 && hash_equals(NLK_PRODUCT_EXPORT_TOKEN, $token);
		}

		public static function export_products() {
			if (! class_exists('WooCommerce')) {
				return new WP_Error('nlk_no_woocommerce', 'WooCommerce is not active.', array('status' => 500));
			}

			return rest_ensure_response(
				array(
					'schema_version' => 1,
					'generated_at'   => gmdate('c'),
					'source_url'     => home_url('/'),
					'products'       => self::get_products(),
				)
			);
		}

		protected static function get_products() {
			$products = wc_get_products(
				array(
					'type'    => array('simple', 'variable', 'grouped', 'external'),
					'limit'   => -1,
					'status'  => array('publish', 'private', 'draft', 'pending'),
					'return'  => 'objects',
					'orderby' => 'ID',
					'order'   => 'ASC',
				)
			);

			$records = array();
			foreach ($products as $product) {
				$records[] = self::export_product($product);
			}

			return $records;
		}

		protected static function export_product(WC_Product $product) {
			$data = array(
				'id'                 => $product->get_id(),
				'type'               => $product->get_type(),
				'name'               => $product->get_name(),
				'slug'               => $product->get_slug(),
				'status'             => $product->get_status(),
				'sku'                => $product->get_sku(),
				'description'        => $product->get_description(),
				'short_description'  => $product->get_short_description(),
				'catalog_visibility' => $product->get_catalog_visibility(),
				'featured'           => $product->get_featured(),
				'regular_price'      => $product->get_regular_price(),
				'sale_price'         => $product->get_sale_price(),
				'date_on_sale_from'  => self::datetime_to_string($product->get_date_on_sale_from()),
				'date_on_sale_to'    => self::datetime_to_string($product->get_date_on_sale_to()),
				'tax_status'         => $product->get_tax_status(),
				'tax_class'          => $product->get_tax_class(),
				'manage_stock'       => $product->get_manage_stock(),
				'stock_quantity'     => $product->get_stock_quantity(),
				'stock_status'       => $product->get_stock_status(),
				'backorders'         => $product->get_backorders(),
				'sold_individually'  => $product->get_sold_individually(),
				'weight'             => $product->get_weight(),
				'length'             => $product->get_length(),
				'width'              => $product->get_width(),
				'height'             => $product->get_height(),
				'purchase_note'      => $product->get_purchase_note(),
				'menu_order'         => $product->get_menu_order(),
				'attributes'         => self::export_attributes($product),
				'default_attributes' => method_exists($product, 'get_default_attributes') ? $product->get_default_attributes() : array(),
				'taxonomies'         => self::export_product_taxonomies($product->get_id()),
				'variations'         => array(),
			);

			if ($product->is_type('variable')) {
				foreach ($product->get_children() as $variation_id) {
					$variation = wc_get_product($variation_id);
					if ($variation instanceof WC_Product_Variation) {
						$data['variations'][] = self::export_variation($variation);
					}
				}
			}

			return $data;
		}

		protected static function export_variation(WC_Product_Variation $variation) {
			return array(
				'id'                => $variation->get_id(),
				'name'              => $variation->get_name(),
				'status'            => $variation->get_status(),
				'sku'               => $variation->get_sku(),
				'regular_price'     => $variation->get_regular_price(),
				'sale_price'        => $variation->get_sale_price(),
				'date_on_sale_from' => self::datetime_to_string($variation->get_date_on_sale_from()),
				'date_on_sale_to'   => self::datetime_to_string($variation->get_date_on_sale_to()),
				'tax_status'        => $variation->get_tax_status(),
				'tax_class'         => $variation->get_tax_class(),
				'manage_stock'      => $variation->get_manage_stock(),
				'stock_quantity'    => $variation->get_stock_quantity(),
				'stock_status'      => $variation->get_stock_status(),
				'backorders'        => $variation->get_backorders(),
				'weight'            => $variation->get_weight(),
				'length'            => $variation->get_length(),
				'width'             => $variation->get_width(),
				'height'            => $variation->get_height(),
				'attributes'        => $variation->get_attributes(),
			);
		}

		protected static function export_attributes(WC_Product $product) {
			$out = array();

			foreach ($product->get_attributes() as $attribute) {
				if (! $attribute instanceof WC_Product_Attribute) {
					continue;
				}

				$options = array();
				if ($attribute->is_taxonomy() && taxonomy_exists($attribute->get_name())) {
					foreach ($attribute->get_options() as $term_id) {
						$term = get_term((int) $term_id, $attribute->get_name());
						if ($term && ! is_wp_error($term)) {
							$options[] = array(
								'id'   => (int) $term->term_id,
								'name' => $term->name,
								'slug' => $term->slug,
							);
						}
					}
				} else {
					foreach ($attribute->get_options() as $option) {
						$options[] = array(
							'name' => (string) $option,
							'slug' => sanitize_title($option),
						);
					}
				}

				$out[] = array(
					'name'      => $attribute->get_name(),
					'label'     => wc_attribute_label($attribute->get_name()),
					'taxonomy'  => $attribute->is_taxonomy(),
					'visible'   => $attribute->get_visible(),
					'variation' => $attribute->get_variation(),
					'position'  => $attribute->get_position(),
					'options'   => $options,
				);
			}

			return $out;
		}

		protected static function export_product_taxonomies($product_id) {
			$out        = array();
			$taxonomies = get_object_taxonomies('product', 'names');

			foreach ($taxonomies as $taxonomy) {
				if (0 === strpos($taxonomy, 'pa_')) {
					continue;
				}

				$terms = get_the_terms($product_id, $taxonomy);
				if (! $terms || is_wp_error($terms)) {
					continue;
				}

				foreach ($terms as $term) {
					$out[] = array(
						'taxonomy' => $taxonomy,
						'name'     => $term->name,
						'slug'     => $term->slug,
					);
				}
			}

			return $out;
		}

		protected static function datetime_to_string($datetime) {
			return $datetime instanceof WC_DateTime ? $datetime->date('Y-m-d H:i:s') : '';
		}
	}

	NLK_Source_Product_Export_Snippet::init();
}
