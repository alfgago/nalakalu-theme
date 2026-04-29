<?php
/**
 * Product sync helpers for moving products between Nalakalu installs.
 *
 * @package nalakalu-2025
 */

if (! defined('ABSPATH')) {
	exit;
}

class NLK_Woo_Product_Site_Sync
{
	const REST_NAMESPACE        = 'nlk/v1';
	const REST_ROUTE            = '/product-export';
	const TOKEN_OPTION          = 'nlk_product_sync_export_token';
	const BANNER_FIELD_NAME     = 'product_banner_image';
	const BANNER_FIELD_KEY      = 'field_nlk_product_banner_image';
	const BANNER_META_KEY       = 'product_banner_image';
	const IMAGE_SOURCE_META_KEY = '_nlk_new_webp_source_file';

	protected static $image_map = null;
	protected static $matched_image_paths = array();

	public static function init()
	{
		add_action('rest_api_init', array(__CLASS__, 'register_rest_route'));
		add_action('acf/init', array(__CLASS__, 'register_acf_fields'));

		if (defined('WP_CLI') && WP_CLI) {
			self::register_cli_commands();
		}
	}

	public static function register_rest_route()
	{
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array(__CLASS__, 'rest_export_products'),
				'permission_callback' => array(__CLASS__, 'rest_can_export_products'),
			)
		);
	}

	public static function register_acf_fields()
	{
		if (! function_exists('acf_add_local_field_group')) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'                   => 'group_nlk_product_media',
				'title'                 => 'Nalakalu product media',
				'fields'                => array(
					array(
						'key'           => self::BANNER_FIELD_KEY,
						'label'         => 'Banner image',
						'name'          => self::BANNER_FIELD_NAME,
						'type'          => 'image',
						'return_format' => 'id',
						'preview_size'  => 'medium',
						'library'       => 'all',
					),
				),
				'location'              => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'product',
						),
					),
				),
				'position'              => 'side',
				'style'                 => 'default',
				'label_placement'       => 'top',
				'instruction_placement' => 'label',
				'active'                => true,
			)
		);
	}

	protected static function register_cli_commands()
	{
		WP_CLI::add_command('nlk product-sync-token', array(__CLASS__, 'cli_product_sync_token'));
		WP_CLI::add_command('nlk export-products-json', array(__CLASS__, 'cli_export_products_json'));
		WP_CLI::add_command('nlk import-products-json-sync', array(__CLASS__, 'cli_import_products_json'));
		WP_CLI::add_command('nlk pull-products-from-site', array(__CLASS__, 'cli_pull_products_from_site'));
		WP_CLI::add_command('nlk import-new-webp-images', array(__CLASS__, 'cli_import_new_webp_images'));
	}

	/**
	 * Creates or shows the source export token.
	 *
	 * ## OPTIONS
	 *
	 * [--set=<token>]
	 * : Store a specific token instead of generating one.
	 *
	 * [--show]
	 * : Show the currently stored token.
	 *
	 * @when after_wp_load
	 */
	public static function cli_product_sync_token($args, $assoc_args)
	{
		if (! empty($assoc_args['set'])) {
			$token = trim((string) $assoc_args['set']);
		} elseif (! empty($assoc_args['show'])) {
			$token = (string) get_option(self::TOKEN_OPTION, '');
		} else {
			$token = wp_generate_password(48, false, false);
		}

		if (strlen($token) < 16) {
			WP_CLI::error('Use a token de al menos 16 caracteres.');
		}

		if (empty($assoc_args['show'])) {
			update_option(self::TOKEN_OPTION, $token, false);
		}

		$route = rest_url(self::REST_NAMESPACE . self::REST_ROUTE);
		WP_CLI::log('Token: ' . $token);
		WP_CLI::success('Endpoint: ' . add_query_arg('token', rawurlencode($token), $route));
	}

	/**
	 * Exports products to JSON on the current site.
	 *
	 * ## OPTIONS
	 *
	 * [--output=<file>]
	 * : Output path. Default: wp-content/uploads/nlk-products-export.json.
	 *
	 * [--published-only]
	 * : Export only published products. By default all normal product statuses are exported.
	 *
	 * @when after_wp_load
	 */
	public static function cli_export_products_json($args, $assoc_args)
	{
		$output = ! empty($assoc_args['output'])
			? (string) $assoc_args['output']
			: trailingslashit(wp_upload_dir()['basedir']) . 'nlk-products-export.json';

		$payload = self::build_export_payload(empty($assoc_args['published-only']));
		$json    = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if (false === $json || false === file_put_contents($output, $json)) {
			WP_CLI::error('No pude escribir el archivo JSON: ' . $output);
		}

		WP_CLI::success(sprintf('Exportados %d productos a %s', count($payload['products']), $output));
	}

	/**
	 * Pulls product JSON from another Nalakalu site and imports it.
	 *
	 * ## OPTIONS
	 *
	 * --source=<url>
	 * : Source site URL, for example https://nalakalu.com.
	 *
	 * --token=<token>
	 * : Token stored on the source with `wp nlk product-sync-token`.
	 *
	 * [--images-dir=<dir>]
	 * : Directory on this site containing PRODUCTNAME.webp files. Default: wp-content/uploads/new-webp.
	 *
	 * [--dry-run]
	 * : Report intended changes without saving products or importing media.
	 *
	 * @when after_wp_load
	 */
	public static function cli_pull_products_from_site($args, $assoc_args)
	{
		$source = ! empty($assoc_args['source']) ? untrailingslashit((string) $assoc_args['source']) : '';
		$token  = ! empty($assoc_args['token']) ? (string) $assoc_args['token'] : '';

		if (! $source || ! $token) {
			WP_CLI::error('Use --source=https://nalakalu.com y --token=<token>.');
		}

		$url      = add_query_arg('token', rawurlencode($token), $source . '/wp-json/' . self::REST_NAMESPACE . self::REST_ROUTE);
		$response = wp_remote_get($url, array('timeout' => 120));

		if (is_wp_error($response)) {
			WP_CLI::error('No pude leer el sitio fuente: ' . $response->get_error_message());
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		if ($code < 200 || $code >= 300) {
			WP_CLI::error(sprintf('El sitio fuente respondio HTTP %d.', $code));
		}

		$payload = json_decode(wp_remote_retrieve_body($response), true);
		if (! is_array($payload)) {
			WP_CLI::error('La respuesta del sitio fuente no es JSON valido.');
		}

		self::import_payload($payload, $assoc_args);
	}

	/**
	 * Imports a previously exported product JSON file.
	 *
	 * ## OPTIONS
	 *
	 * --file=<file>
	 * : JSON file from `wp nlk export-products-json`.
	 *
	 * [--images-dir=<dir>]
	 * : Directory on this site containing PRODUCTNAME.webp files. Default: wp-content/uploads/new-webp.
	 *
	 * [--dry-run]
	 * : Report intended changes without saving products or importing media.
	 *
	 * @when after_wp_load
	 */
	public static function cli_import_products_json($args, $assoc_args)
	{
		$file = ! empty($assoc_args['file']) ? (string) $assoc_args['file'] : '';
		if (! $file || ! is_readable($file)) {
			WP_CLI::error('No pude leer el JSON indicado con --file.');
		}

		$payload = json_decode(file_get_contents($file), true);
		if (! is_array($payload)) {
			WP_CLI::error('El archivo JSON no es valido.');
		}

		self::import_payload($payload, $assoc_args);
	}

	/**
	 * Imports product images from a local folder using the suffix convention:
	 *   PRODUCTNAME.webp or PRODUCTNAME_1.webp -> featured (post thumbnail)
	 *   PRODUCTNAME_2.webp                     -> detalle (gallery)
	 *   PRODUCTNAME_3.webp                     -> ambiente (banner / ACF field)
	 *
	 * For each kind that has a new file, the product's existing assignment is replaced.
	 * Old attachments that originated from this importer (tracked via _nlk_new_webp_source_file)
	 * are deleted when no other post still references them. Manually-uploaded media is left
	 * in the media library, just unlinked from the product. Kinds without a new file are
	 * left untouched.
	 *
	 * ## OPTIONS
	 *
	 * [--images-dir=<dir>]
	 * : Directory on this site containing the files. Default: wp-content/uploads/new-webp.
	 *
	 * [--dry-run]
	 * : Report intended changes without importing media.
	 *
	 * @when after_wp_load
	 */
	public static function cli_import_new_webp_images($args, $assoc_args)
	{
		$dry_run    = ! empty($assoc_args['dry-run']);
		$images_dir = self::resolve_images_dir($assoc_args);
		$products   = wc_get_products(
			array(
				'type'   => array('simple', 'variable', 'grouped', 'external'),
				'limit'  => -1,
				'status' => array('publish', 'private', 'draft', 'pending'),
				'return' => 'objects',
			)
		);

		$stats = self::empty_stats();
		foreach ($products as $product) {
			self::sync_product_images_from_dir($product->get_id(), $product->get_name(), $product->get_slug(), $images_dir, $dry_run, $stats);
		}

		self::report_unmatched_images($images_dir);
		self::log_stats('Imagenes procesadas', $stats);
	}

	public static function rest_can_export_products($request)
	{
		$stored = (string) get_option(self::TOKEN_OPTION, '');
		$token  = (string) $request->get_param('token');

		return strlen($stored) >= 16 && hash_equals($stored, $token);
	}

	public static function rest_export_products($request)
	{
		return rest_ensure_response(self::build_export_payload(true));
	}

	protected static function build_export_payload($include_private)
	{
		$statuses = $include_private ? array('publish', 'private', 'draft', 'pending') : array('publish');
		$products = wc_get_products(
			array(
				'type'   => array('simple', 'variable', 'grouped', 'external'),
				'limit'  => -1,
				'status' => $statuses,
				'return' => 'objects',
				'orderby' => 'ID',
				'order'  => 'ASC',
			)
		);

		$records = array();
		foreach ($products as $product) {
			$records[] = self::export_product($product);
		}

		return array(
			'schema_version' => 1,
			'generated_at'   => gmdate('c'),
			'source_url'     => home_url('/'),
			'products'       => $records,
		);
	}

	protected static function export_product(WC_Product $product)
	{
		$data = array(
			'id'                  => $product->get_id(),
			'type'                => $product->get_type(),
			'name'                => $product->get_name(),
			'slug'                => $product->get_slug(),
			'status'              => $product->get_status(),
			'sku'                 => $product->get_sku(),
			'description'         => $product->get_description(),
			'short_description'   => $product->get_short_description(),
			'catalog_visibility'  => $product->get_catalog_visibility(),
			'featured'            => $product->get_featured(),
			'regular_price'       => $product->get_regular_price(),
			'sale_price'          => $product->get_sale_price(),
			'date_on_sale_from'   => self::datetime_to_string($product->get_date_on_sale_from()),
			'date_on_sale_to'     => self::datetime_to_string($product->get_date_on_sale_to()),
			'tax_status'          => $product->get_tax_status(),
			'tax_class'           => $product->get_tax_class(),
			'manage_stock'        => $product->get_manage_stock(),
			'stock_quantity'      => $product->get_stock_quantity(),
			'stock_status'        => $product->get_stock_status(),
			'backorders'          => $product->get_backorders(),
			'sold_individually'   => $product->get_sold_individually(),
			'weight'              => $product->get_weight(),
			'length'              => $product->get_length(),
			'width'               => $product->get_width(),
			'height'              => $product->get_height(),
			'purchase_note'       => $product->get_purchase_note(),
			'menu_order'          => $product->get_menu_order(),
			'attributes'          => self::export_attributes($product),
			'default_attributes'  => method_exists($product, 'get_default_attributes') ? $product->get_default_attributes() : array(),
			'taxonomies'          => self::export_product_taxonomies($product->get_id()),
			'variations'          => array(),
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

	protected static function export_variation(WC_Product_Variation $variation)
	{
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

	protected static function export_attributes(WC_Product $product)
	{
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
							'image' => self::export_term_image_data((int) $term->term_id),
						);
					}
				}
			} else {
				foreach ($attribute->get_options() as $option) {
					$options[] = array('name' => (string) $option, 'slug' => sanitize_title($option));
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

	protected static function export_term_image_data($term_id)
	{
		$term_id = absint($term_id);
		if (! $term_id) {
			return array();
		}

		foreach (array('thumbnail_id', 'image_id', 'product_attribute_image', 'swatch_image', 'term_image', 'image') as $meta_key) {
			$value = get_term_meta($term_id, $meta_key, true);
			$image = self::normalize_exported_image_value($value, $meta_key);
			if (! empty($image['url'])) {
				return $image;
			}
		}

		if (function_exists('get_field')) {
			foreach (array('imagen', 'image', 'swatch', 'thumbnail') as $field_key) {
				$value = get_field($field_key, 'term_' . $term_id);
				$image = self::normalize_exported_image_value($value, $field_key);
				if (! empty($image['url'])) {
					return $image;
				}
			}
		}

		return array();
	}

	protected static function normalize_exported_image_value($value, $source_key)
	{
		if (is_numeric($value) && (int) $value > 0) {
			$url = wp_get_attachment_image_url((int) $value, 'full');
			return $url ? array('url' => $url, 'source_key' => $source_key) : array();
		}

		if (is_string($value) && preg_match('~^https?://~', $value)) {
			return array('url' => esc_url_raw($value), 'source_key' => $source_key);
		}

		if (is_array($value)) {
			if (! empty($value['url'])) {
				return array('url' => esc_url_raw($value['url']), 'source_key' => $source_key);
			}
			if (! empty($value['ID']) || ! empty($value['id'])) {
				$id = ! empty($value['ID']) ? (int) $value['ID'] : (int) $value['id'];
				$url = wp_get_attachment_image_url($id, 'full');
				return $url ? array('url' => $url, 'source_key' => $source_key) : array();
			}
		}

		return array();
	}

	protected static function export_product_taxonomies($product_id)
	{
		$out = array();
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

	protected static function import_payload($payload, $assoc_args)
	{
		if (empty($payload['products']) || ! is_array($payload['products'])) {
			WP_CLI::error('El JSON no contiene productos.');
		}

		$dry_run    = ! empty($assoc_args['dry-run']);
		$images_dir = self::resolve_images_dir($assoc_args);
		$product_map = self::build_target_product_map();
		$stats      = self::empty_stats();

		foreach ($payload['products'] as $record) {
			if (empty($record['name'])) {
				$stats['skipped']++;
				continue;
			}

			$match_keys  = self::product_match_keys_from_record($record);
			$existing_id = self::find_product_id_in_map($product_map, $match_keys);

			if ($existing_id) {
				self::update_existing_product_from_record($existing_id, $record, $dry_run, $stats);
				self::sync_product_images_from_dir($existing_id, $record['name'], get_post_field('post_name', $existing_id), $images_dir, $dry_run, $stats);
				continue;
			}

			$product_id = self::create_product_from_record($record, $dry_run, $stats);
			if ($product_id) {
				foreach ($match_keys as $key) {
					$product_map[ $key ] = $product_id;
				}
				self::sync_product_images_from_dir($product_id, $record['name'], get_post_field('post_name', $product_id), $images_dir, $dry_run, $stats);
			} elseif ($dry_run) {
				self::sync_product_images_from_dir(0, $record['name'], isset($record['slug']) ? (string) $record['slug'] : '', $images_dir, true, $stats);
			}
		}

		self::report_unmatched_images($images_dir);
		self::log_stats($dry_run ? 'Vista previa terminada' : 'Importacion terminada', $stats);
	}

	protected static function update_existing_product_from_record($product_id, $record, $dry_run, &$stats)
	{
		$product = wc_get_product($product_id);
		if (! $product) {
			$stats['skipped']++;
			return;
		}

		if ($dry_run) {
			if ($product->is_type('variable')) {
				self::sync_variations($product->get_id(), $record, false, true, $stats);
			}
			$stats['updated']++;
			return;
		}

		self::apply_prices($product, $record);
		self::apply_product_attributes($product, $record);
		$product->save();

		if ($product->is_type('variable')) {
			self::sync_variations($product->get_id(), $record, false, $dry_run, $stats);
			WC_Product_Variable::sync($product->get_id());
		}

		$stats['updated']++;
	}

	protected static function create_product_from_record($record, $dry_run, &$stats)
	{
		if ($dry_run) {
			$stats['created']++;
			foreach ((array) ($record['variations'] ?? array()) as $variation_record) {
				$stats['variations_created']++;
			}
			return 0;
		}

		$type = ! empty($record['type']) ? (string) $record['type'] : 'simple';
		$product = 'variable' === $type ? new WC_Product_Variable() : new WC_Product_Simple();

		$product->set_name((string) $record['name']);
		$product->set_status(! empty($record['status']) ? (string) $record['status'] : 'publish');
		$product->set_description(isset($record['description']) ? (string) $record['description'] : '');
		$product->set_short_description(isset($record['short_description']) ? (string) $record['short_description'] : '');
		$product->set_catalog_visibility(! empty($record['catalog_visibility']) ? (string) $record['catalog_visibility'] : 'visible');
		$product->set_featured(! empty($record['featured']));
		$product->set_menu_order(isset($record['menu_order']) ? (int) $record['menu_order'] : 0);

		if (! empty($record['sku']) && ! wc_get_product_id_by_sku((string) $record['sku'])) {
			$product->set_sku((string) $record['sku']);
		}

		self::apply_prices($product, $record);
		self::apply_inventory($product, $record);
		self::apply_shipping_fields($product, $record);
		self::apply_product_attributes($product, $record);

		$product_id = $product->save();
		self::apply_product_attributes($product, $record);
		$product->save();

		self::apply_taxonomies($product_id, isset($record['taxonomies']) ? $record['taxonomies'] : array());

		if ('variable' === $type) {
			self::sync_variations($product_id, $record, true, $dry_run, $stats);
			WC_Product_Variable::sync($product_id);
		}

		$stats['created']++;

		return $product_id;
	}

	protected static function apply_prices(WC_Product $product, $record)
	{
		$product->set_regular_price(isset($record['regular_price']) ? (string) $record['regular_price'] : '');
		$product->set_sale_price(isset($record['sale_price']) ? (string) $record['sale_price'] : '');

		if (isset($record['date_on_sale_from'])) {
			$product->set_date_on_sale_from($record['date_on_sale_from'] ? wc_string_to_datetime($record['date_on_sale_from']) : null);
		}

		if (isset($record['date_on_sale_to'])) {
			$product->set_date_on_sale_to($record['date_on_sale_to'] ? wc_string_to_datetime($record['date_on_sale_to']) : null);
		}
	}

	protected static function apply_inventory(WC_Product $product, $record)
	{
		$product->set_tax_status(isset($record['tax_status']) ? (string) $record['tax_status'] : 'taxable');
		$product->set_tax_class(isset($record['tax_class']) ? (string) $record['tax_class'] : '');
		$product->set_manage_stock(! empty($record['manage_stock']));
		$product->set_stock_quantity(isset($record['stock_quantity']) && null !== $record['stock_quantity'] ? (int) $record['stock_quantity'] : null);
		$product->set_stock_status(! empty($record['stock_status']) ? (string) $record['stock_status'] : 'instock');
		$product->set_backorders(! empty($record['backorders']) ? (string) $record['backorders'] : 'no');
		$product->set_sold_individually(! empty($record['sold_individually']));
	}

	protected static function apply_shipping_fields(WC_Product $product, $record)
	{
		foreach (array('weight', 'length', 'width', 'height') as $field) {
			if (isset($record[ $field ])) {
				$setter = 'set_' . $field;
				$product->$setter((string) $record[ $field ]);
			}
		}

		if (isset($record['purchase_note'])) {
			$product->set_purchase_note((string) $record['purchase_note']);
		}
	}

	protected static function apply_product_attributes(WC_Product $product, $record)
	{
		$attributes = array();

		foreach ((array) ($record['attributes'] ?? array()) as $attr_record) {
			$attribute = self::build_product_attribute($attr_record, $product->get_id());
			if ($attribute) {
				$attributes[] = $attribute;
			}
		}

		$product->set_attributes($attributes);

		if (method_exists($product, 'set_default_attributes') && isset($record['default_attributes']) && is_array($record['default_attributes'])) {
			$product->set_default_attributes($record['default_attributes']);
		}
	}

	protected static function build_product_attribute($attr_record, $product_id)
	{
		$name = isset($attr_record['name']) ? (string) $attr_record['name'] : '';
		if ('' === $name) {
			return null;
		}

		$is_taxonomy = ! empty($attr_record['taxonomy']) && taxonomy_exists($name);
		$options    = array();

		if ($is_taxonomy) {
			foreach ((array) ($attr_record['options'] ?? array()) as $option) {
				$term_id = self::ensure_term_from_record($name, $option);
				if ($term_id) {
					$options[] = $term_id;
					self::sync_attribute_term_image($term_id, $option);
				}
			}

			if ($product_id && ! empty($options)) {
				wp_set_object_terms($product_id, $options, $name, false);
			}
		} else {
			foreach ((array) ($attr_record['options'] ?? array()) as $option) {
				$options[] = is_array($option) && isset($option['name']) ? (string) $option['name'] : (string) $option;
			}
		}

		$attribute = new WC_Product_Attribute();
		$attribute->set_id($is_taxonomy ? wc_attribute_taxonomy_id_by_name($name) : 0);
		$attribute->set_name($is_taxonomy ? $name : (! empty($attr_record['label']) ? (string) $attr_record['label'] : $name));
		$attribute->set_options(array_values(array_unique(array_filter($options))));
		$attribute->set_position(isset($attr_record['position']) ? (int) $attr_record['position'] : 0);
		$attribute->set_visible(! empty($attr_record['visible']));
		$attribute->set_variation(! empty($attr_record['variation']));

		return $attribute;
	}

	protected static function sync_variations($product_id, $record, $parent_was_created, $dry_run, &$stats)
	{
		$source_variations = isset($record['variations']) && is_array($record['variations']) ? $record['variations'] : array();
		if (empty($source_variations)) {
			return;
		}

		$existing = self::build_variation_maps($product_id);

		foreach ($source_variations as $variation_record) {
			$variation_id = self::match_variation_id($variation_record, $existing);

			if ($dry_run) {
				$stats[ $variation_id ? 'variations_updated' : 'variations_created' ]++;
				continue;
			}

			$variation = $variation_id ? wc_get_product($variation_id) : new WC_Product_Variation();
			if (! $variation instanceof WC_Product_Variation) {
				$variation = new WC_Product_Variation();
			}

			if (! $variation_id) {
				$variation->set_parent_id($product_id);
				if (! empty($variation_record['sku']) && ! wc_get_product_id_by_sku((string) $variation_record['sku'])) {
					$variation->set_sku((string) $variation_record['sku']);
				}
				$variation->set_status(! empty($variation_record['status']) ? (string) $variation_record['status'] : 'publish');
				self::apply_inventory($variation, $variation_record);
				self::apply_shipping_fields($variation, $variation_record);
			}

			self::apply_prices($variation, $variation_record);
			$variation->set_attributes(self::normalize_variation_attributes($variation_record['attributes'] ?? array()));
			$variation->save();

			$stats[ $variation_id ? 'variations_updated' : 'variations_created' ]++;
		}
	}

	protected static function build_variation_maps($product_id)
	{
		$maps = array('by_sku' => array(), 'by_signature' => array());
		$product = wc_get_product($product_id);

		if (! $product || ! $product->is_type('variable')) {
			return $maps;
		}

		foreach ($product->get_children() as $child_id) {
			$variation = wc_get_product($child_id);
			if (! $variation instanceof WC_Product_Variation) {
				continue;
			}

			if ($variation->get_sku()) {
				$maps['by_sku'][ $variation->get_sku() ] = $child_id;
			}

			$maps['by_signature'][ self::variation_signature($variation->get_attributes()) ] = $child_id;
		}

		return $maps;
	}

	protected static function match_variation_id($variation_record, $existing)
	{
		$sku = isset($variation_record['sku']) ? (string) $variation_record['sku'] : '';
		if ($sku && isset($existing['by_sku'][ $sku ])) {
			return (int) $existing['by_sku'][ $sku ];
		}

		$signature = self::variation_signature($variation_record['attributes'] ?? array());
		return isset($existing['by_signature'][ $signature ]) ? (int) $existing['by_signature'][ $signature ] : 0;
	}

	protected static function normalize_variation_attributes($attributes)
	{
		$out = array();
		foreach ((array) $attributes as $name => $value) {
			$out[ sanitize_title(str_replace('attribute_', '', (string) $name)) ] = sanitize_title((string) $value);
		}
		return $out;
	}

	protected static function variation_signature($attributes)
	{
		$normalized = self::normalize_variation_attributes($attributes);
		ksort($normalized);
		return md5(wp_json_encode($normalized));
	}

	protected static function apply_taxonomies($product_id, $records)
	{
		$by_taxonomy = array();
		foreach ((array) $records as $record) {
			$taxonomy = isset($record['taxonomy']) ? (string) $record['taxonomy'] : '';
			if (! $taxonomy || ! taxonomy_exists($taxonomy)) {
				continue;
			}

			$term_id = self::ensure_term_from_record($taxonomy, $record);
			if ($term_id) {
				$by_taxonomy[ $taxonomy ][] = $term_id;
			}
		}

		foreach ($by_taxonomy as $taxonomy => $term_ids) {
			wp_set_object_terms($product_id, array_values(array_unique($term_ids)), $taxonomy, false);
		}
	}

	protected static function ensure_term_from_record($taxonomy, $record)
	{
		$name = is_array($record) && isset($record['name']) ? (string) $record['name'] : (string) $record;
		$slug = is_array($record) && isset($record['slug']) ? (string) $record['slug'] : sanitize_title($name);

		$term = $slug ? get_term_by('slug', $slug, $taxonomy) : null;
		if (! $term && $name) {
			$term = get_term_by('name', $name, $taxonomy);
		}

		if ($term && ! is_wp_error($term)) {
			return (int) $term->term_id;
		}

		if (! $name) {
			return 0;
		}

		$created = wp_insert_term($name, $taxonomy, array('slug' => $slug));
		if (is_wp_error($created)) {
			return 0;
		}

		return isset($created['term_id']) ? (int) $created['term_id'] : 0;
	}

	protected static function sync_attribute_term_image($term_id, $option)
	{
		$term_id = absint($term_id);
		if (! $term_id || empty($option['image']['url'])) {
			return;
		}

		$term = get_term($term_id);
		$title = $term && ! is_wp_error($term) ? $term->name : 'attribute image';
		$attachment_id = self::import_remote_attachment((string) $option['image']['url'], 0, $title);
		if (! $attachment_id) {
			return;
		}

		foreach (array('thumbnail_id', 'image_id', 'product_attribute_image', 'swatch_image', 'term_image', 'image') as $meta_key) {
			update_term_meta($term_id, $meta_key, $attachment_id);
		}
	}

	protected static function import_remote_attachment($url, $post_id, $title)
	{
		$url = esc_url_raw((string) $url);
		if (! $url) {
			return 0;
		}

		$existing = self::find_attachment_by_source_url($url);
		if ($existing) {
			return $existing;
		}

		if (! function_exists('media_handle_sideload')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$tmp = download_url($url, 60);
		if (is_wp_error($tmp)) {
			return 0;
		}

		$filename = sanitize_file_name(wp_basename(wp_parse_url($url, PHP_URL_PATH)));
		if (! $filename) {
			$filename = sanitize_title($title) . '.jpg';
		}

		$filetype = wp_check_filetype($filename);
		$file = array(
			'name'     => $filename,
			'type'     => ! empty($filetype['type']) ? $filetype['type'] : 'image/jpeg',
			'tmp_name' => $tmp,
			'error'    => 0,
			'size'     => filesize($tmp),
		);

		$attachment_id = media_handle_sideload($file, $post_id, $title);
		if (is_wp_error($attachment_id)) {
			@unlink($tmp);
			return 0;
		}

		update_post_meta($attachment_id, '_nlk_source_media_url', $url);
		update_post_meta($attachment_id, '_wp_attachment_image_alt', $title);

		return (int) $attachment_id;
	}

	protected static function find_attachment_by_source_url($url)
	{
		global $wpdb;

		$url = esc_url_raw((string) $url);
		if (! $url) {
			return 0;
		}

		$found = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_nlk_source_media_url' AND meta_value = %s LIMIT 1",
				$url
			)
		);

		if ($found) {
			return $found;
		}

		$basename = sanitize_file_name(wp_basename(wp_parse_url($url, PHP_URL_PATH)));
		if (! $basename) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = '_wp_attached_file'
				AND (meta_value = %s OR meta_value LIKE %s)
				ORDER BY post_id DESC
				LIMIT 1",
				$basename,
				'%/' . $wpdb->esc_like($basename)
			)
		);
	}

	protected static function sync_product_images_from_dir($product_id, $product_name, $product_slug, $images_dir, $dry_run, &$stats)
	{
		$matches = self::find_image_matches($product_name, $product_slug, $images_dir);
		if (! self::has_image_matches($matches)) {
			return;
		}

		self::mark_image_matches($product_id, $product_name, $matches);

		// Dry-run for the "would create a new product" path (product_id = 0): just count.
		if (! $product_id) {
			foreach (array('featured', 'banner', 'detail') as $kind) {
				if (! empty($matches[$kind][0])) {
					$stats['images_' . $kind]++;
				}
			}
			return;
		}

		foreach (array('featured', 'banner', 'detail') as $kind) {
			if (! empty($matches[$kind])) {
				self::replace_kind_with_match($product_id, $product_name, $kind, $matches[$kind], $dry_run, $stats);
			}
		}
	}

	/**
	 * Sideloads $new_paths for $kind on $product_id, replacing the existing assignment.
	 * For 'featured' and 'banner' kinds only the first path is used (single field).
	 * For 'detail', all paths become the gallery (in array order).
	 *
	 * Old attachments that came from this importer (have IMAGE_SOURCE_META_KEY) and are
	 * not referenced by any other post are deleted. Manually-uploaded old attachments are
	 * left in the media library, just unlinked from this product.
	 */
	protected static function replace_kind_with_match($product_id, $product_name, $kind, $new_paths, $dry_run, &$stats)
	{
		$new_paths = array_values(array_filter((array) $new_paths));
		if (empty($new_paths)) {
			return;
		}

		// featured/banner are single-valued fields.
		if ('featured' === $kind || 'banner' === $kind) {
			$new_paths = array(reset($new_paths));
		}

		$old_ids = self::current_attachment_ids_for_kind($product_id, $kind);

		if ($dry_run) {
			$stats['images_' . $kind] += count($new_paths);
			foreach ($old_ids as $old_id) {
				$stats['images_replaced']++;
				if (self::should_delete_old_attachment($old_id, $product_id)) {
					$stats['images_deleted']++;
				}
			}
			return;
		}

		$title   = $product_name . self::kind_title_suffix($kind);
		$new_ids = array();
		foreach ($new_paths as $path) {
			$id = self::import_attachment($path, $product_id, $title);
			if ($id) {
				$new_ids[] = (int) $id;
				$stats['images_' . $kind]++;
			}
		}

		$new_ids = array_values(array_unique($new_ids));
		if (empty($new_ids)) {
			return;
		}

		self::set_kind_attachment($product_id, $kind, $new_ids);

		foreach ($old_ids as $old_id) {
			$old_id = (int) $old_id;
			if (! $old_id || in_array($old_id, $new_ids, true)) {
				continue;
			}
			$stats['images_replaced']++;
			if (self::should_delete_old_attachment($old_id, $product_id)) {
				wp_delete_attachment($old_id, true);
				$stats['images_deleted']++;
			}
		}
	}

	protected static function current_attachment_ids_for_kind($product_id, $kind)
	{
		$product_id = (int) $product_id;
		if (! $product_id) {
			return array();
		}

		if ('featured' === $kind) {
			$id = (int) get_post_thumbnail_id($product_id);
			return $id ? array($id) : array();
		}

		if ('banner' === $kind) {
			$id = (int) get_post_meta($product_id, self::BANNER_META_KEY, true);
			return $id ? array($id) : array();
		}

		if ('detail' === $kind) {
			$raw = (string) get_post_meta($product_id, '_product_image_gallery', true);
			$ids = array_filter(array_map('absint', explode(',', $raw)));
			return array_values(array_unique($ids));
		}

		return array();
	}

	protected static function set_kind_attachment($product_id, $kind, $new_ids)
	{
		$new_ids = array_values(array_unique(array_filter(array_map('intval', (array) $new_ids))));
		if (empty($new_ids)) {
			return;
		}
		$first = (int) $new_ids[0];

		if ('featured' === $kind) {
			set_post_thumbnail($product_id, $first);
			return;
		}

		if ('banner' === $kind) {
			self::update_banner_image($product_id, $first);
			return;
		}

		if ('detail' === $kind) {
			update_post_meta($product_id, '_product_image_gallery', implode(',', $new_ids));
		}
	}

	protected static function kind_title_suffix($kind)
	{
		if ('banner' === $kind) {
			return ' banner';
		}
		if ('detail' === $kind) {
			return ' detalle';
		}
		return '';
	}

	protected static function should_delete_old_attachment($attachment_id, $product_id)
	{
		$attachment_id = (int) $attachment_id;
		if (! $attachment_id) {
			return false;
		}

		if ('' === (string) get_post_meta($attachment_id, self::IMAGE_SOURCE_META_KEY, true)) {
			return false;
		}

		return ! self::attachment_referenced_elsewhere($attachment_id, $product_id);
	}

	protected static function attachment_referenced_elsewhere($attachment_id, $exclude_post_id)
	{
		global $wpdb;

		$attachment_id    = (int) $attachment_id;
		$exclude_post_id  = (int) $exclude_post_id;
		if (! $attachment_id) {
			return false;
		}

		$thumb_or_banner = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta}
				 WHERE meta_key IN ('_thumbnail_id', %s)
				   AND meta_value = %s
				   AND post_id <> %d",
				self::BANNER_META_KEY,
				(string) $attachment_id,
				$exclude_post_id
			)
		);
		if ($thumb_or_banner > 0) {
			return true;
		}

		$id_str  = (string) $attachment_id;
		$gallery = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta}
				 WHERE meta_key = '_product_image_gallery'
				   AND post_id <> %d
				   AND (
					   meta_value = %s
					   OR meta_value LIKE %s
					   OR meta_value LIKE %s
					   OR meta_value LIKE %s
				   )",
				$exclude_post_id,
				$id_str,
				$wpdb->esc_like($id_str . ',') . '%',
				'%' . $wpdb->esc_like(',' . $id_str . ',') . '%',
				'%' . $wpdb->esc_like(',' . $id_str)
			)
		);

		return $gallery > 0;
	}

	protected static function find_image_matches($product_name, $product_slug, $images_dir)
	{
		$map = self::get_image_map($images_dir);
		$keys = array_values(
			array_unique(
				array_filter(
					array(
						self::normalize_name_key($product_name),
						self::normalize_slug_key($product_name),
						self::normalize_slug_key($product_slug),
					)
				)
			)
		);

		foreach ($keys as $key) {
			if (isset($map['by_key'][ $key ])) {
				return $map['by_key'][ $key ];
			}
		}

		return array('featured' => array(), 'banner' => array(), 'detail' => array());
	}

	protected static function get_image_map($images_dir)
	{
		if (null !== self::$image_map) {
			return self::$image_map;
		}

		$map = array(
			'by_key' => array(),
			'files'  => array(),
		);
		if (! is_dir($images_dir)) {
			self::$image_map = $map;
			return $map;
		}

		$files = glob(trailingslashit($images_dir) . '*.{webp,WEBP,web,WEB,jpg,JPG,jpeg,JPEG,png,PNG}', GLOB_BRACE);
		foreach ((array) $files as $path) {
			$original_stem = pathinfo($path, PATHINFO_FILENAME);
			$stem          = $original_stem;
			$kind          = null;

			if (preg_match('/^(.+)_([123])$/', $stem, $m)) {
				$stem = $m[1];
				// _1 -> featured (post thumbnail). _2 and _3 both go to gallery (carousel).
				// The 'banner' kind is preserved in the rest of the code, just no longer
				// produced by this parser while the banner template is hidden via CSS.
				$kind = ('1' === $m[2]) ? 'featured' : 'detail';
			} elseif (preg_match('/_(AMBIENTE|DETALLE)(?:_\d+)?$/i', $stem)) {
				// Legacy convention - no longer recognized.
				$kind = null;
			} elseif (preg_match('/_(\d+_\d+|[4-9]|\d{2,})$/', $stem)) {
				// Numeric suffix outside the supported _1 / _2 / _3 range.
				$kind = null;
			} else {
				// No recognized trailing suffix - whole stem is the product name.
				$kind = 'featured';
			}

			$map['files'][] = array(
				'path'          => $path,
				'stem'          => $stem,
				'original_stem' => $original_stem,
				'kind'          => $kind,
				'slug_key'      => self::normalize_slug_key($stem),
			);

			if (null === $kind) {
				continue;
			}

			$keys = array_values(
				array_unique(
					array_filter(
						array(
							self::normalize_name_key($stem),
							self::normalize_slug_key($stem),
						)
					)
				)
			);

			foreach ($keys as $key) {
				if (! isset($map['by_key'][ $key ])) {
					$map['by_key'][ $key ] = array('featured' => array(), 'banner' => array(), 'detail' => array());
				}
				$map['by_key'][ $key ][ $kind ][] = $path;
			}
		}

		self::$image_map = $map;
		return $map;
	}

	protected static function mark_image_matches($product_id, $product_name, $matches)
	{
		$files = array();

		foreach ($matches as $kind => $paths) {
			foreach ((array) $paths as $path) {
				self::$matched_image_paths[ wp_normalize_path($path) ] = true;
				$files[] = sprintf('%s:%s', $kind, wp_basename($path));
			}
		}

		if (! empty($files) && defined('WP_CLI') && WP_CLI) {
			WP_CLI::log(sprintf('Imagenes matched | #%d %s | %s', $product_id, $product_name, implode(', ', $files)));
		}
	}

	protected static function has_image_matches($matches)
	{
		foreach ((array) $matches as $paths) {
			if (! empty($paths)) {
				return true;
			}
		}

		return false;
	}

	protected static function report_unmatched_images($images_dir)
	{
		if (! defined('WP_CLI') || ! WP_CLI) {
			return;
		}

		$map = self::get_image_map($images_dir);
		if (empty($map['files'])) {
			WP_CLI::warning('No encontre imagenes en: ' . $images_dir);
			return;
		}

		$unmatched = array();
		foreach ($map['files'] as $file) {
			if (empty(self::$matched_image_paths[ wp_normalize_path($file['path']) ])) {
				$unmatched[] = $file;
			}
		}

		if (empty($unmatched)) {
			WP_CLI::log('Todas las imagenes de new-webp tuvieron match.');
			return;
		}

		$candidates = self::build_product_slug_candidates();
		WP_CLI::warning(sprintf('Imagenes sin match: %d', count($unmatched)));

		foreach (array_slice($unmatched, 0, 40) as $file) {
			$hint = '';
			$original = isset($file['original_stem']) ? (string) $file['original_stem'] : '';
			if ($original && preg_match('/_(AMBIENTE|DETALLE)(?:_\d+)?$/i', $original)) {
				$hint = ' | sufijo legacy, renombra a _3 (ambiente) o _2 (detalle)';
			} elseif ($original && preg_match('/_(\d+_\d+|[4-9]|\d{2,})$/', $original)) {
				$hint = ' | sufijo numerico no soportado, usa _1, _2 o _3';
			} else {
				$suggestion = self::closest_slug_candidate($file['slug_key'], $candidates);
				if ($suggestion) {
					$hint = ' | posible producto: ' . $suggestion;
				}
			}
			WP_CLI::warning(sprintf('- %s%s', wp_basename($file['path']), $hint));
		}

		if (count($unmatched) > 40) {
			WP_CLI::warning(sprintf('... y %d imagenes mas sin match.', count($unmatched) - 40));
		}
	}

	protected static function import_attachment($path, $post_id, $title)
	{
		$existing = self::find_attachment_by_source_file($path);
		if ($existing) {
			return $existing;
		}

		if (! function_exists('media_handle_sideload')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$filename = wp_basename($path);
		if ('web' === strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
			$filename = preg_replace('/\.web$/i', '.webp', $filename);
		}
		$filetype = wp_check_filetype($filename);

		$tmp = wp_tempnam($filename);
		if (! $tmp || ! copy($path, $tmp)) {
			return 0;
		}

		$file = array(
			'name'     => $filename,
			'type'     => ! empty($filetype['type']) ? $filetype['type'] : 'image/webp',
			'tmp_name' => $tmp,
			'error'    => 0,
			'size'     => filesize($tmp),
		);

		$attachment_id = media_handle_sideload($file, $post_id, $title);
		if (is_wp_error($attachment_id)) {
			@unlink($tmp);
			return 0;
		}

		update_post_meta($attachment_id, self::IMAGE_SOURCE_META_KEY, self::source_file_hash($path));
		update_post_meta($attachment_id, '_wp_attachment_image_alt', $title);

		return (int) $attachment_id;
	}

	protected static function find_attachment_by_source_file($path)
	{
		global $wpdb;

		$hash = self::source_file_hash($path);
		$found = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				self::IMAGE_SOURCE_META_KEY,
				$hash
			)
		);

		if ($found) {
			return $found;
		}

		$basename = wp_basename($path);
		if ('web' === strtolower(pathinfo($basename, PATHINFO_EXTENSION))) {
			$basename = preg_replace('/\.web$/i', '.webp', $basename);
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = '_wp_attached_file'
				AND (meta_value = %s OR meta_value LIKE %s)
				ORDER BY post_id DESC
				LIMIT 1",
				$basename,
				'%/' . $wpdb->esc_like($basename)
			)
		);
	}

	protected static function update_banner_image($product_id, $attachment_id)
	{
		update_post_meta($product_id, self::BANNER_META_KEY, (int) $attachment_id);

		if (function_exists('update_field')) {
			update_field(self::BANNER_FIELD_KEY, (int) $attachment_id, $product_id);
		}
	}

	protected static function build_target_product_map()
	{
		$map = array();
		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array('publish', 'private', 'draft', 'pending'),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ($ids as $id) {
			foreach (self::product_match_keys_from_values(get_the_title($id), get_post_field('post_name', $id)) as $key) {
				if ($key && ! isset($map[ $key ])) {
					$map[ $key ] = (int) $id;
				}
			}
		}

		return $map;
	}

	protected static function product_match_keys_from_record($record)
	{
		return self::product_match_keys_from_values(
			isset($record['name']) ? (string) $record['name'] : '',
			isset($record['slug']) ? (string) $record['slug'] : ''
		);
	}

	protected static function product_match_keys_from_values($name, $slug)
	{
		$keys = array();

		$slug_key = self::normalize_slug_key($slug);
		if ($slug_key) {
			$keys[] = 'slug:' . $slug_key;
		}

		$name_slug_key = self::normalize_slug_key($name);
		if ($name_slug_key) {
			$keys[] = 'slug:' . $name_slug_key;
		}

		$name_key = self::normalize_name_key($name);
		if ($name_key) {
			$keys[] = 'name:' . $name_key;
		}

		return array_values(array_unique($keys));
	}

	protected static function find_product_id_in_map($product_map, $keys)
	{
		foreach ((array) $keys as $key) {
			if (isset($product_map[ $key ])) {
				return (int) $product_map[ $key ];
			}
		}

		return 0;
	}

	protected static function normalize_name_key($value)
	{
		$value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$value = remove_accents($value);
		$value = strtolower($value);
		$value = preg_replace('/[^a-z0-9]+/', '', $value);

		return trim($value);
	}

	protected static function normalize_slug_key($value)
	{
		return sanitize_title(remove_accents(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
	}

	protected static function build_product_slug_candidates()
	{
		$candidates = array();
		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array('publish', 'private', 'draft', 'pending'),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ($ids as $id) {
			$title = get_the_title($id);
			$slug = get_post_field('post_name', $id);

			foreach (array(self::normalize_slug_key($title), self::normalize_slug_key($slug)) as $key) {
				if ($key && ! isset($candidates[ $key ])) {
					$candidates[ $key ] = sprintf('#%d %s (%s)', $id, $title, $slug);
				}
			}
		}

		return $candidates;
	}

	protected static function closest_slug_candidate($needle, $candidates)
	{
		$needle = (string) $needle;
		if ('' === $needle || empty($candidates)) {
			return '';
		}

		$best_label = '';
		$best_score = PHP_INT_MAX;

		foreach ($candidates as $candidate => $label) {
			$score = levenshtein($needle, (string) $candidate);
			if ($score < $best_score) {
				$best_score = $score;
				$best_label = $label;
			}
		}

		return $best_score <= max(2, (int) floor(strlen($needle) * 0.25)) ? $best_label : '';
	}

	protected static function resolve_images_dir($assoc_args)
	{
		$dir = ! empty($assoc_args['images-dir']) ? (string) $assoc_args['images-dir'] : trailingslashit(WP_CONTENT_DIR) . 'uploads/new-webp';
		return wp_normalize_path($dir);
	}

	protected static function source_file_hash($path)
	{
		return md5(wp_normalize_path($path) . '|' . (is_readable($path) ? filesize($path) : '0'));
	}

	protected static function datetime_to_string($datetime)
	{
		return $datetime instanceof WC_DateTime ? $datetime->date('Y-m-d H:i:s') : '';
	}

	protected static function empty_stats()
	{
		return array(
			'created'            => 0,
			'updated'            => 0,
			'skipped'            => 0,
			'variations_created' => 0,
			'variations_updated' => 0,
			'images_featured'    => 0,
			'images_banner'      => 0,
			'images_detail'      => 0,
			'images_replaced'    => 0,
			'images_deleted'     => 0,
		);
	}

	protected static function log_stats($label, $stats)
	{
		WP_CLI::success(
			sprintf(
				'%s. created=%d | updated=%d | skipped=%d | variations_created=%d | variations_updated=%d | featured=%d | banners=%d | detail=%d | replaced=%d | deleted=%d',
				$label,
				$stats['created'],
				$stats['updated'],
				$stats['skipped'],
				$stats['variations_created'],
				$stats['variations_updated'],
				$stats['images_featured'],
				$stats['images_banner'],
				$stats['images_detail'],
				$stats['images_replaced'],
				$stats['images_deleted']
			)
		);
	}
}
