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
	 * Imports PRODUCTNAME.webp / PRODUCTNAME_AMBIENTE.webp / PRODUCTNAME_DETALLE.webp images.
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
			self::sync_product_images_from_dir($product->get_id(), $product->get_name(), $images_dir, $dry_run, $stats);
		}

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
		$name_map   = self::build_target_name_map();
		$stats      = self::empty_stats();

		foreach ($payload['products'] as $record) {
			if (empty($record['name'])) {
				$stats['skipped']++;
				continue;
			}

			$key         = self::normalize_name_key($record['name']);
			$existing_id = isset($name_map[ $key ]) ? (int) $name_map[ $key ] : 0;

			if ($existing_id) {
				self::update_existing_product_from_record($existing_id, $record, $dry_run, $stats);
				self::sync_product_images_from_dir($existing_id, $record['name'], $images_dir, $dry_run, $stats);
				continue;
			}

			$product_id = self::create_product_from_record($record, $dry_run, $stats);
			if ($product_id) {
				$name_map[ $key ] = $product_id;
				self::sync_product_images_from_dir($product_id, $record['name'], $images_dir, $dry_run, $stats);
			}
		}

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

	protected static function sync_product_images_from_dir($product_id, $product_name, $images_dir, $dry_run, &$stats)
	{
		$matches = self::find_image_matches($product_name, $images_dir);
		if (empty($matches)) {
			return;
		}

		if ($dry_run) {
			foreach ($matches as $kind => $paths) {
				$stats['images_' . $kind] += count($paths);
			}
			return;
		}

		if (! empty($matches['featured'][0])) {
			$attachment_id = self::import_attachment($matches['featured'][0], $product_id, $product_name);
			if ($attachment_id) {
				set_post_thumbnail($product_id, $attachment_id);
				$stats['images_featured']++;
			}
		}

		if (! empty($matches['banner'][0])) {
			$attachment_id = self::import_attachment($matches['banner'][0], $product_id, $product_name . ' banner');
			if ($attachment_id) {
				self::update_banner_image($product_id, $attachment_id);
				$stats['images_banner']++;
			}
		}

		if (! empty($matches['detail'])) {
			$gallery_ids = array_filter(array_map('absint', explode(',', (string) get_post_meta($product_id, '_product_image_gallery', true))));
			foreach ($matches['detail'] as $path) {
				$attachment_id = self::import_attachment($path, $product_id, $product_name . ' detalle');
				if ($attachment_id && ! in_array($attachment_id, $gallery_ids, true)) {
					$gallery_ids[] = $attachment_id;
					$stats['images_detail']++;
				}
			}
			update_post_meta($product_id, '_product_image_gallery', implode(',', array_values(array_unique($gallery_ids))));
		}
	}

	protected static function find_image_matches($product_name, $images_dir)
	{
		$map = self::get_image_map($images_dir);
		$key = self::normalize_name_key($product_name);

		return isset($map[ $key ])
			? $map[ $key ]
			: array('featured' => array(), 'banner' => array(), 'detail' => array());
	}

	protected static function get_image_map($images_dir)
	{
		if (null !== self::$image_map) {
			return self::$image_map;
		}

		$map = array();
		if (! is_dir($images_dir)) {
			self::$image_map = $map;
			return $map;
		}

		$files = glob(trailingslashit($images_dir) . '*.{webp,WEBP,web,WEB,jpg,JPG,jpeg,JPEG,png,PNG}', GLOB_BRACE);
		foreach ((array) $files as $path) {
			$stem = pathinfo($path, PATHINFO_FILENAME);
			$kind = 'featured';

			if (preg_match('/_AMBIENTE$/i', $stem)) {
				$kind = 'banner';
				$stem = preg_replace('/_AMBIENTE$/i', '', $stem);
			} elseif (preg_match('/_DETALLE(?:_\d+)?$/i', $stem)) {
				$kind = 'detail';
				$stem = preg_replace('/_DETALLE(?:_\d+)?$/i', '', $stem);
			}

			$key = self::normalize_name_key($stem);
			if (! isset($map[ $key ])) {
				$map[ $key ] = array('featured' => array(), 'banner' => array(), 'detail' => array());
			}
			$map[ $key ][ $kind ][] = $path;
		}

		self::$image_map = $map;
		return $map;
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

	protected static function build_target_name_map()
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
			$key = self::normalize_name_key(get_the_title($id));
			if ($key && ! isset($map[ $key ])) {
				$map[ $key ] = (int) $id;
			}
		}

		return $map;
	}

	protected static function normalize_name_key($value)
	{
		$value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$value = remove_accents($value);
		$value = strtolower($value);
		$value = preg_replace('/[^a-z0-9]+/', '', $value);

		return trim($value);
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
		);
	}

	protected static function log_stats($label, $stats)
	{
		WP_CLI::success(
			sprintf(
				'%s. created=%d | updated=%d | skipped=%d | variations_created=%d | variations_updated=%d | featured=%d | banners=%d | detail=%d',
				$label,
				$stats['created'],
				$stats['updated'],
				$stats['skipped'],
				$stats['variations_created'],
				$stats['variations_updated'],
				$stats['images_featured'],
				$stats['images_banner'],
				$stats['images_detail']
			)
		);
	}
}
