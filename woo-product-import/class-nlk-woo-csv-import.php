<?php
/**
 * WooCommerce CSV batch importer.
 *
 * @package nalakalu-2025
 */

if (! defined('ABSPATH')) {
	exit;
}

class NLK_Woo_CSV_Import
{
	const PAGE_SLUG                 = 'nlk-woo-csv-import';
	const NONCE_ACTION              = 'nlk_woo_csv_import';
	const SOURCE_META_KEY           = '_nlk_woo_import_source_id';
	const SOURCE_SKU_META_KEY       = '_nlk_woo_import_source_sku';
	const SOURCE_PARENT_META_KEY    = '_nlk_woo_import_source_parent_id';
	const SOURCE_FILE_META_KEY      = '_nlk_woo_import_source_file';
	const BRANDS_META_KEY           = '_nlk_woo_import_marcas';
	const SWATCHES_META_KEY         = 'swatches_attributes';
	const DEFAULT_BATCH_SIZE        = 30;
	const SOURCE_ID_META_COLUMN     = 'Meta: _nlk_woo_import_source_id';
	const SOURCE_SKU_META_COLUMN    = 'Meta: _nlk_woo_import_source_sku';
	const SOURCE_PARENT_META_COLUMN = 'Meta: _nlk_woo_import_source_parent_id';
	const SOURCE_FILE_META_COLUMN   = 'Meta: _nlk_woo_import_source_file';

	protected static $source_id_cache = array();
	protected static $summary_cache   = array();
	protected static $fallback_cache  = array();

	public static function init()
	{
		if (defined('WP_CLI') && WP_CLI) {
			self::register_cli_command();
		}
	}

	protected static function register_cli_command()
	{
		WP_CLI::add_command('nlk import-woo-csv', array(__CLASS__, 'cli_import_command'));
	}

	/**
	 * Imports WooCommerce products from a CSV in batches.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<file>]
	 * : CSV basename inside the active theme, or an absolute path.
	 *
	 * [--batch-size=<number>]
	 * : Rows to process per batch. Default: 30.
	 *
	 * [--update-existing=<bool>]
	 * : Update existing products matched by imported source ID or SKU. Default: true.
	 *
	 * [--skip-existing]
	 * : Do not update matches. Only create new products and variations.
	 *
	 * ## EXAMPLES
	 *
	 *     wp nlk import-woo-csv --file=export-2-woo.csv --batch-size=25
	 *
	 * @when after_wp_load
	 */
	public static function cli_import_command($args, $assoc_args)
	{
		self::include_importer_dependencies();

		$file_arg        = isset($assoc_args['file']) ? (string) $assoc_args['file'] : '';
		$file            = self::resolve_import_file($file_arg);
		$batch_size      = isset($assoc_args['batch-size']) ? max(5, min(100, absint($assoc_args['batch-size']))) : self::DEFAULT_BATCH_SIZE;
		$update_existing = ! isset($assoc_args['skip-existing']);

		if (isset($assoc_args['update-existing'])) {
			$update_existing = filter_var($assoc_args['update-existing'], FILTER_VALIDATE_BOOLEAN);
		}

		if (! $file) {
			$available = array_keys(self::get_detected_csv_files());
			WP_CLI::error(
				'No encontre el archivo CSV solicitado.' .
				(! empty($available) ? ' Disponibles: ' . implode(', ', $available) : '')
			);
		}

		if (! $file['supported']) {
			WP_CLI::error('Ese CSV no tiene el formato soportado. Use export-2-woo.csv o un CSV con columnas Tipo y Superior.');
		}

		$total_rows = self::count_total_rows($file['path']);
		$position   = 0;
		$batch_no   = 0;
		$totals     = self::empty_import_stats();
		$summary    = $file['summary'];

		WP_CLI::log(sprintf('Archivo: %s', $file['basename']));
		WP_CLI::log(sprintf('Ruta: %s', $file['path']));
		WP_CLI::log(sprintf('Filas totales: %d', $total_rows));
		WP_CLI::log(sprintf('Tamano de lote: %d', $batch_size));
		WP_CLI::log(sprintf('Actualizar existentes: %s', $update_existing ? 'si' : 'no'));

		if (! empty($summary['type_counts'])) {
			$type_bits = array();
			foreach ($summary['type_counts'] as $type => $count) {
				$type_bits[] = sprintf('%s=%d', $type, intval($count));
			}
			WP_CLI::log('Tipos: ' . implode(', ', $type_bits));
		}

		if (! empty($summary['swatch_rows'])) {
			WP_CLI::log(sprintf('Filas con swatches: %d', intval($summary['swatch_rows'])));
		}

		while (true) {
			$batch_no++;
			$result = self::process_import_batch($file, $position, $batch_size, $update_existing);

			$totals   = self::merge_import_stats($totals, $result['stats']);
			$from_row = $result['processed_rows'] > 0 ? $position + 1 : $position;
			$to_row   = $result['next_position'];

			WP_CLI::log(
				sprintf(
					'Lote %d | filas %d-%d/%d | imported=%d | imported_variations=%d | updated=%d | failed=%d | skipped=%d | swatch_terms=%d',
					$batch_no,
					$from_row,
					$to_row,
					$total_rows,
					$result['stats']['imported'],
					$result['stats']['imported_variations'],
					$result['stats']['updated'],
					$result['stats']['failed'],
					$result['stats']['skipped'],
					$result['stats']['swatch_terms_updated']
				)
			);

			foreach ($result['messages'] as $message) {
				WP_CLI::warning($message);
			}

			if ($result['done']) {
				break;
			}

			$position = $result['next_position'];
		}

		WP_CLI::success(
			sprintf(
				'Importacion terminada. imported=%d | imported_variations=%d | updated=%d | failed=%d | skipped=%d | swatch_terms=%d',
				$totals['imported'],
				$totals['imported_variations'],
				$totals['updated'],
				$totals['failed'],
				$totals['skipped'],
				$totals['swatch_terms_updated']
			)
		);
	}

	public static function add_menu_page()
	{
		add_submenu_page(
			'woocommerce',
			'Importador CSV Woo',
			'Importador CSV',
			'manage_woocommerce',
			self::PAGE_SLUG,
			array(__CLASS__, 'render_page')
		);
	}

	public static function enqueue_scripts($hook)
	{
		if (strpos($hook, self::PAGE_SLUG) === false) {
			return;
		}

		wp_enqueue_script(
			'nlk-woo-import-admin',
			get_template_directory_uri() . '/woo-product-import/admin.js',
			array(),
			filemtime(NLK_WOO_IMPORT_PATH . '/admin.js'),
			true
		);

		wp_localize_script(
			'nlk-woo-import-admin',
			'nlkWooImport',
			array(
				'ajaxUrl'          => admin_url('admin-ajax.php'),
				'nonce'            => wp_create_nonce(self::NONCE_ACTION),
				'defaultBatchSize' => self::DEFAULT_BATCH_SIZE,
			)
		);
	}

	public static function render_page()
	{
		if (! current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'nalakalu'));
		}

		$files            = self::get_detected_csv_files();
		$recommended_file = self::get_recommended_file($files);
		?>
		<div class="wrap">
			<h1>Importador Woo CSV</h1>
			<p>Usa <code>export-2-woo.csv</code> como fuente principal. Ese archivo separa productos <code>variable</code> y <code>variation</code>, por eso ves 800+ filas aunque tengas alrededor de 287 productos padre.</p>

			<?php if (empty($files)) : ?>
				<div class="notice notice-warning"><p>No encontre archivos CSV en la raiz del tema.</p></div>
			<?php else : ?>
				<table class="widefat striped" style="max-width: 960px; margin-bottom: 20px;">
					<thead>
						<tr>
							<th>Archivo</th>
							<th>Soportado</th>
							<th>Filas</th>
							<th>Detalle</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($files as $file) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html($file['basename']); ?></strong>
									<?php if ($recommended_file === $file['basename']) : ?>
										<span style="color:#2271b1;">(recomendado)</span>
									<?php endif; ?>
								</td>
								<td><?php echo $file['supported'] ? 'Si' : 'No'; ?></td>
								<td><?php echo esc_html(number_format_i18n($file['summary']['total_rows'])); ?></td>
								<td>
									<?php
									$details = array();

									if (! empty($file['summary']['type_counts'])) {
										foreach ($file['summary']['type_counts'] as $type => $count) {
											$details[] = sprintf('%s: %d', $type, intval($count));
										}
									}

									if (! empty($file['summary']['swatch_rows'])) {
										$details[] = sprintf('swatches: %d', intval($file['summary']['swatch_rows']));
									}

									if (! empty($file['summary']['image_rows'])) {
										$details[] = sprintf('filas con imagenes: %d', intval($file['summary']['image_rows']));
									}

									if (! empty($file['summary']['notes'])) {
										$details[] = $file['summary']['notes'];
									}

									echo esc_html(implode(' | ', array_filter($details)));
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div class="card" style="max-width: 960px; padding: 20px;">
					<h2 style="margin-top: 0;">Ejecutar importacion</h2>
					<p>El importador procesa el archivo en lotes, corrige los IDs de origen antes de pasar cada chunk al importador nativo de WooCommerce y luego sincroniza los term meta de swatches para este tema.</p>

					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="nlk-woo-import-file">Archivo CSV</label></th>
								<td>
									<select id="nlk-woo-import-file">
										<?php foreach ($files as $file) : ?>
											<option
												value="<?php echo esc_attr($file['basename']); ?>"
												data-supported="<?php echo $file['supported'] ? '1' : '0'; ?>"
												<?php selected($recommended_file, $file['basename']); ?>
											>
												<?php echo esc_html($file['basename']); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<p class="description">Solo los CSV con columnas <code>Tipo</code> y <code>Superior</code> se pueden importar con este boton.</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="nlk-woo-import-batch-size">Tamano del lote</label></th>
								<td>
									<input id="nlk-woo-import-batch-size" type="number" min="5" max="100" value="<?php echo esc_attr(self::DEFAULT_BATCH_SIZE); ?>" />
									<p class="description">30 es un valor seguro para hosting compartido.</p>
								</td>
							</tr>
							<tr>
								<th scope="row">Actualizar existentes</th>
								<td>
									<label>
										<input id="nlk-woo-import-update-existing" type="checkbox" checked="checked" />
										Actualizar productos o variaciones existentes cuando encuentre el mismo SKU o un ID de origen ya importado.
									</label>
								</td>
							</tr>
						</tbody>
					</table>

					<p>
						<button type="button" class="button button-primary" id="nlk-woo-import-start">Iniciar importacion</button>
					</p>

					<div id="nlk-woo-import-progress-wrap" style="display:none; max-width: 720px;">
						<div style="height: 18px; border-radius: 999px; overflow: hidden; background: #dcdcde;">
							<div id="nlk-woo-import-progress-bar" style="width:0%; height:100%; background:#2271b1; transition:width 0.2s ease;"></div>
						</div>
						<p id="nlk-woo-import-progress-text" style="margin:10px 0 0;">Esperando...</p>
					</div>

					<pre id="nlk-woo-import-log" style="display:none; margin-top:16px; max-width: 920px; max-height: 360px; overflow:auto; background:#0f172a; color:#e2e8f0; padding:16px; border-radius:8px;"></pre>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function ajax_import_batch()
	{
		check_ajax_referer(self::NONCE_ACTION, 'nonce');

		if (! current_user_can('manage_woocommerce')) {
			wp_send_json_error(array('message' => 'No tiene permisos suficientes.'), 403);
		}

		$file_basename   = isset($_POST['file']) ? sanitize_file_name(wp_unslash($_POST['file'])) : '';
		$position        = isset($_POST['position']) ? max(0, absint($_POST['position'])) : 0;
		$batch_size      = isset($_POST['batch_size']) ? max(5, min(100, absint($_POST['batch_size']))) : self::DEFAULT_BATCH_SIZE;
		$update_existing = ! empty($_POST['update_existing']);

		$file = self::resolve_import_file($file_basename);

		if (! $file) {
			wp_send_json_error(array('message' => 'No encontre el archivo CSV seleccionado.'), 400);
		}

		if (! $file['supported']) {
			wp_send_json_error(array('message' => 'Ese CSV no tiene el formato soportado por este importador. Use export-2-woo.csv.'), 400);
		}

		wp_send_json_success(self::process_import_batch($file, $position, $batch_size, $update_existing));
	}

	protected static function process_import_batch($file, $position, $batch_size, $update_existing)
	{
		self::include_importer_dependencies();

		$chunk = self::read_csv_chunk($file['path'], $position, $batch_size);
		$chunk['rows'] = self::supplement_rows_with_fallback_data($chunk['rows'], $file['basename']);
		$total = self::count_total_rows($file['path']);

		if (empty($chunk['rows'])) {
			return array(
				'done'             => true,
				'position'         => $position,
				'next_position'    => $position,
				'total_rows'       => $total,
				'processed_rows'   => 0,
				'progress_percent' => 100,
				'stats'            => self::empty_import_stats(),
				'messages'         => array('No quedan filas pendientes en el archivo.'),
			);
		}

		$parents_and_simples = array();
		$variations          = array();

		foreach ($chunk['rows'] as $row) {
			$type = strtolower(trim((string) self::row_value($row, 'Tipo')));

			if ('variation' === $type) {
				$variations[] = $row;
			} else {
				$parents_and_simples[] = $row;
			}
		}

		$stats    = self::empty_import_stats();
		$messages = array();

		if (! empty($parents_and_simples)) {
			$prepared_parents = self::prepare_rows_for_import($chunk['headers'], $parents_and_simples, false, $update_existing, $file['basename']);
			$parent_results   = self::run_importer_for_rows($chunk['headers'], $prepared_parents['rows'], $update_existing);
			self::sync_source_keys_for_rows($parents_and_simples);

			$stats = self::merge_import_stats($stats, $parent_results['stats']);

			if (! empty($prepared_parents['messages'])) {
				$messages = array_merge($messages, $prepared_parents['messages']);
			}

			if (! empty($parent_results['messages'])) {
				$messages = array_merge($messages, $parent_results['messages']);
			}

			$swatch_results = self::sync_swatches_from_rows($parents_and_simples);
			$stats['swatch_terms_updated'] += $swatch_results['updated_terms'];

			if (! empty($swatch_results['messages'])) {
				$messages = array_merge($messages, $swatch_results['messages']);
			}
		}

		if (! empty($variations)) {
			$prepared_variations = self::prepare_rows_for_import($chunk['headers'], $variations, true, $update_existing, $file['basename']);
			$variation_results   = self::run_importer_for_rows($chunk['headers'], $prepared_variations['rows'], $update_existing);

			$stats = self::merge_import_stats($stats, $variation_results['stats']);

			if (! empty($prepared_variations['messages'])) {
				$messages = array_merge($messages, $prepared_variations['messages']);
			}

			if (! empty($variation_results['messages'])) {
				$messages = array_merge($messages, $variation_results['messages']);
			}
		}

		$processed_rows = count($chunk['rows']);
		$next_position  = min($total, $position + $processed_rows);
		$done           = $next_position >= $total;

		if ($done && empty($messages)) {
			$messages[] = 'Importacion terminada.';
		}

		return array(
			'done'             => $done,
			'position'         => $position,
			'next_position'    => $next_position,
			'total_rows'       => $total,
			'processed_rows'   => $processed_rows,
			'progress_percent' => $total > 0 ? min(100, (int) round(($next_position / $total) * 100)) : 100,
			'stats'            => $stats,
			'messages'         => array_values(array_unique(array_filter($messages))),
		);
	}

	protected static function include_importer_dependencies()
	{
		if (! class_exists('WC_Product_CSV_Importer') && defined('WC_ABSPATH')) {
			require_once WC_ABSPATH . 'includes/import/abstract-wc-product-importer.php';
			require_once WC_ABSPATH . 'includes/import/class-wc-product-csv-importer.php';
		}
	}

	protected static function get_detected_csv_files()
	{
		$files = array();

		foreach (glob(get_template_directory() . '/*.csv') as $path) {
			$basename = wp_basename($path);
			$summary  = self::summarize_csv($path);

			$files[ $basename ] = array(
				'basename'  => $basename,
				'path'      => $path,
				'supported' => self::is_supported_headers($summary['headers']),
				'summary'   => $summary,
			);
		}

		uksort(
			$files,
			function ($left, $right) {
				if ('export-2-woo.csv' === $left) {
					return -1;
				}
				if ('export-2-woo.csv' === $right) {
					return 1;
				}
				return strnatcasecmp($left, $right);
			}
		);

		return $files;
	}

	protected static function get_recommended_file($files)
	{
		if (isset($files['export-2-woo.csv']) && $files['export-2-woo.csv']['supported']) {
			return 'export-2-woo.csv';
		}

		foreach ($files as $file) {
			if ($file['supported']) {
				return $file['basename'];
			}
		}

		return '';
	}

	protected static function resolve_import_file($file_arg = '')
	{
		$files = self::get_detected_csv_files();

		if ('' === $file_arg) {
			$recommended = self::get_recommended_file($files);
			return $recommended && isset($files[ $recommended ]) ? $files[ $recommended ] : null;
		}

		if (isset($files[ $file_arg ])) {
			return $files[ $file_arg ];
		}

		$basename = sanitize_file_name(wp_basename($file_arg));
		if ($basename && isset($files[ $basename ])) {
			return $files[ $basename ];
		}

		if (is_readable($file_arg)) {
			$summary = self::summarize_csv($file_arg);

			return array(
				'basename'  => wp_basename($file_arg),
				'path'      => $file_arg,
				'supported' => self::is_supported_headers($summary['headers']),
				'summary'   => $summary,
			);
		}

		return null;
	}

	protected static function summarize_csv($path)
	{
		$key = md5($path . '|' . filemtime($path) . '|' . filesize($path));

		if (isset(self::$summary_cache[ $key ])) {
			return self::$summary_cache[ $key ];
		}

		$headers = self::read_csv_headers($path);
		$summary = array(
			'headers'      => $headers,
			'total_rows'   => 0,
			'type_counts'  => array(),
			'swatch_rows'  => 0,
			'image_rows'   => 0,
			'notes'        => '',
		);

		$file = self::open_csv_file($path);

		if (! $file) {
			return $summary;
		}

		$file->rewind();
		$file->fgetcsv();

		while (! $file->eof()) {
			$row = $file->fgetcsv();
			$row = self::normalize_csv_row($row, $headers);

			if (null === $row) {
				continue;
			}

			$summary['total_rows']++;

			$type = trim((string) self::row_value($row, 'Tipo'));
			if ('' === $type) {
				$type = trim((string) self::row_value($row, 'Product Type'));
			}

			if ('' !== $type) {
				if (! isset($summary['type_counts'][ $type ])) {
					$summary['type_counts'][ $type ] = 0;
				}
				$summary['type_counts'][ $type ]++;
			}

			if ('' !== trim((string) self::row_value($row, 'Swatches Attributes'))) {
				$summary['swatch_rows']++;
			}

			if ('' !== trim((string) self::row_value($row, 'Imágenes'))) {
				$summary['image_rows']++;
				continue;
			}

			if (
				'' !== trim((string) self::row_value($row, 'Imágenes')) ||
				'' !== trim((string) self::row_value($row, 'Image URL'))
			) {
				$summary['image_rows']++;
			}
		}

		if (! self::is_supported_headers($headers)) {
			$summary['notes'] = 'Solo sirve como referencia; este boton no puede importar ese formato.';
		}

		self::$summary_cache[ $key ] = $summary;

		return $summary;
	}

	protected static function is_supported_headers($headers)
	{
		return in_array('Tipo', $headers, true) && in_array('Superior', $headers, true);
	}

	protected static function read_csv_headers($path)
	{
		$file = self::open_csv_file($path);

		if (! $file) {
			return array();
		}

		$file->rewind();
		$headers = $file->fgetcsv();

		if (! is_array($headers)) {
			return array();
		}

		foreach ($headers as $index => $header) {
			$headers[ $index ] = self::remove_utf8_bom(trim((string) $header));
		}

		return $headers;
	}

	protected static function count_total_rows($path)
	{
		$summary = self::summarize_csv($path);
		return intval($summary['total_rows']);
	}

	protected static function read_csv_chunk($path, $offset, $limit)
	{
		$headers = self::read_csv_headers($path);
		$file    = self::open_csv_file($path);
		$rows    = array();
		$index   = 0;

		if (! $file || empty($headers)) {
			return array(
				'headers' => $headers,
				'rows'    => $rows,
			);
		}

		$file->rewind();
		$file->fgetcsv();

		while (! $file->eof() && count($rows) < $limit) {
			$row = $file->fgetcsv();
			$row = self::normalize_csv_row($row, $headers);

			if (null === $row) {
				continue;
			}

			if ($index < $offset) {
				$index++;
				continue;
			}

			$rows[] = $row;
			$index++;
		}

		return array(
			'headers' => $headers,
			'rows'    => $rows,
		);
	}

	protected static function open_csv_file($path)
	{
		if (! is_readable($path)) {
			return null;
		}

		$file = new SplFileObject($path, 'r');
		$file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
		$file->setCsvControl(',', '"', '\\');

		return $file;
	}

	protected static function supplement_rows_with_fallback_data($rows, $primary_basename)
	{
		if (empty($rows) || 'export-2-woo.csv' !== $primary_basename) {
			return $rows;
		}

		$fallback = self::get_fallback_lookup('export-1-woo.csv');

		if (empty($fallback)) {
			return $rows;
		}

		foreach ($rows as $index => $row) {
			$source_id = trim((string) self::row_value($row, 'ID'));
			$sku       = trim((string) self::row_value($row, 'SKU'));
			$matched   = null;

			if ($source_id && isset($fallback['by_id'][ $source_id ])) {
				$matched = $fallback['by_id'][ $source_id ];
			} elseif ($sku && isset($fallback['by_sku'][ $sku ])) {
				$matched = $fallback['by_sku'][ $sku ];
			}

			if (! $matched) {
				continue;
			}

			if ('' === trim((string) self::row_value($row, 'Imágenes')) && ! empty($matched['images'])) {
				$rows[ $index ]['Imágenes'] = $matched['images'];
			}

			if ('' === trim((string) self::row_value($row, 'Descripción')) && ! empty($matched['description'])) {
				$rows[ $index ]['Descripción'] = $matched['description'];
			}

			if ('' === trim((string) self::row_value($row, 'Descripción corta')) && ! empty($matched['short_description'])) {
				$rows[ $index ]['Descripción corta'] = $matched['short_description'];
			}

			if ('' === trim((string) self::row_value($row, 'Imágenes')) && ! empty($matched['images'])) {
				$rows[ $index ]['Imágenes'] = $matched['images'];
			}

			if ('' === trim((string) self::row_value($row, 'Nombre')) && ! empty($matched['name'])) {
				$rows[ $index ]['Nombre'] = $matched['name'];
			}

			if ('' === trim((string) self::row_value($row, 'Descripción')) && ! empty($matched['description'])) {
				$rows[ $index ]['Descripción'] = $matched['description'];
			}

			if ('' === trim((string) self::row_value($row, 'Descripción corta')) && ! empty($matched['short_description'])) {
				$rows[ $index ]['Descripción corta'] = $matched['short_description'];
			}
		}

		return $rows;
	}

	protected static function get_fallback_lookup($basename)
	{
		if (isset(self::$fallback_cache[ $basename ])) {
			return self::$fallback_cache[ $basename ];
		}

		$path = get_template_directory() . '/' . $basename;
		if (! is_readable($path)) {
			self::$fallback_cache[ $basename ] = array();
			return self::$fallback_cache[ $basename ];
		}

		$headers = self::read_csv_headers($path);
		$file    = self::open_csv_file($path);
		$lookup  = array(
			'by_id'  => array(),
			'by_sku' => array(),
		);

		if (! $file || empty($headers)) {
			self::$fallback_cache[ $basename ] = $lookup;
			return $lookup;
		}

		$file->rewind();
		$file->fgetcsv();

		while (! $file->eof()) {
			$row = self::normalize_csv_row($file->fgetcsv(), $headers);
			if (null === $row) {
				continue;
			}

			$record = array(
				'images'            => self::convert_export1_images($row),
				'name'              => trim((string) self::row_value($row, 'Title')),
				'description'       => (string) self::row_value($row, 'Content'),
				'short_description' => (string) self::row_value($row, 'Short Description'),
			);

			$source_id = trim((string) self::row_value($row, 'ID'));
			$sku       = trim((string) self::row_value($row, 'Sku'));

			if ($source_id && ! isset($lookup['by_id'][ $source_id ])) {
				$lookup['by_id'][ $source_id ] = $record;
			}

			if ($sku && ! isset($lookup['by_sku'][ $sku ])) {
				$lookup['by_sku'][ $sku ] = $record;
			}
		}

		self::$fallback_cache[ $basename ] = $lookup;

		return $lookup;
	}

	protected static function convert_export1_images($row)
	{
		$images = trim((string) self::row_value($row, 'Image URL'));

		if ('' === $images) {
			$featured = trim((string) self::row_value($row, 'Image Featured'));
			return $featured;
		}

		$parts = array_values(array_filter(array_map('trim', explode('|', $images))));

		return implode(', ', $parts);
	}

	protected static function normalize_csv_row($row, $headers)
	{
		if (! is_array($row)) {
			return null;
		}

		if (1 === count($row) && (null === $row[0] || '' === trim((string) $row[0]))) {
			return null;
		}

		if (count($row) < count($headers)) {
			$row = array_pad($row, count($headers), '');
		} elseif (count($row) > count($headers)) {
			$row = array_slice($row, 0, count($headers));
		}

		$assoc = array_combine($headers, $row);

		if (false === $assoc) {
			return null;
		}

		return $assoc;
	}

	protected static function prepare_rows_for_import($headers, $rows, $is_variation, $update_existing, $source_file)
	{
		$prepared             = array();
		$messages             = array();
		$unresolved_variation = array();

		foreach ($rows as $row) {
			$source_id     = trim((string) self::row_value($row, 'ID'));
			$source_sku    = trim((string) self::row_value($row, 'SKU'));
			$source_parent = trim((string) self::row_value($row, 'Superior'));
			$existing_id   = $update_existing ? self::resolve_existing_target_id($row) : 0;
			$prepared_row  = $row;

			$prepared_row['ID'] = $existing_id ? (string) $existing_id : '';
			$prepared_row[ self::SOURCE_ID_META_COLUMN ] = $source_id;
			$prepared_row[ self::SOURCE_SKU_META_COLUMN ] = $source_sku;
			$prepared_row[ self::SOURCE_PARENT_META_COLUMN ] = $source_parent;
			$prepared_row[ self::SOURCE_FILE_META_COLUMN ] = $source_file;

			if ($is_variation) {
				$local_parent_id = self::find_local_product_id_by_reference($source_parent);

				if (! $local_parent_id) {
					if (! isset($unresolved_variation[ $source_parent ])) {
						$unresolved_variation[ $source_parent ] = array(
							'count' => 0,
							'skus'  => array(),
						);
					}

					$unresolved_variation[ $source_parent ]['count']++;
					if ($source_sku) {
						$unresolved_variation[ $source_parent ]['skus'][] = $source_sku;
					}
					continue;
				}

				$prepared_row['Superior'] = 'id:' . $local_parent_id;
			}

			$prepared[] = $prepared_row;
		}

		if (! empty($unresolved_variation)) {
			foreach ($unresolved_variation as $parent_ref => $data) {
				$sample_skus = array_slice(array_values(array_unique($data['skus'])), 0, 6);
				$messages[]  = sprintf(
					'Se omitieron %d variaciones porque no encontre su padre local para la referencia %s%s.',
					intval($data['count']),
					$parent_ref !== '' ? $parent_ref : '(vacio)',
					! empty($sample_skus) ? ' | SKUs: ' . implode(', ', $sample_skus) : ''
				);
			}
		}

		return array(
			'rows'     => $prepared,
			'messages' => $messages,
		);
	}

	protected static function resolve_existing_target_id($row)
	{
		$source_id = trim((string) self::row_value($row, 'ID'));
		$sku       = trim((string) self::row_value($row, 'SKU'));

		if ($source_id) {
			$matched = self::find_local_product_id_by_reference($source_id);
			if ($matched) {
				return $matched;
			}
		}

		if ($sku) {
			$matched = self::find_local_product_id_by_reference($sku);
			if ($matched) {
				return intval($matched);
			}
		}

		return 0;
	}

	protected static function run_importer_for_rows($headers, $rows, $update_existing)
	{
		if (empty($rows)) {
			return array(
				'stats'    => self::empty_import_stats(),
				'messages' => array(),
			);
		}

		$temp_headers  = self::build_temp_headers($headers);
		$temp_file_raw = wp_tempnam('nlk-woo-import');
		$temp_file     = $temp_file_raw ? $temp_file_raw . '.csv' : '';

		if (! $temp_file_raw || ! rename($temp_file_raw, $temp_file)) {
			if ($temp_file_raw) {
				wp_delete_file($temp_file_raw);
			}
			return array(
				'stats'    => array_merge(
					self::empty_import_stats(),
					array('failed' => count($rows))
				),
				'messages' => array(sprintf('No se pudo crear archivo temporal para escritura. %d filas no importadas.', count($rows))),
			);
		}

		$handle = fopen($temp_file, 'w');

		if (! $handle) {
			wp_delete_file($temp_file);
			return array(
				'stats'    => array_merge(
					self::empty_import_stats(),
					array('failed' => count($rows))
				),
				'messages' => array(sprintf('No se pudo abrir el archivo temporal "%s". %d filas no importadas.', $temp_file, count($rows))),
			);
		}

		fputcsv($handle, $temp_headers);

		foreach ($rows as $row) {
			$line = array();

			foreach ($temp_headers as $header) {
				$line[] = isset($row[ $header ]) ? $row[ $header ] : '';
			}

			fputcsv($handle, $line);
		}

		fclose($handle);

		$mapping  = self::build_mapping($temp_headers);
		$importer = new WC_Product_CSV_Importer(
			$temp_file,
			array(
				'delimiter'          => ',',
				'mapping'            => $mapping,
				'parse'              => true,
				'update_existing'    => $update_existing,
				'character_encoding' => 'UTF-8',
			)
		);

		$image_failures = array();
		$image_callback = function ($product, $data) use (&$image_failures) {
			if (! empty($data['images']) && ! $product->get_image_id()) {
				$image_failures[] = sprintf(
					'Producto "%s" (SKU: %s) quedó sin imagen — verifique que la URL del CSV sea accesible desde el servidor.',
					$product->get_name(),
					$product->get_sku() ?: 'sin SKU'
				);
			}
		};

		add_action('woocommerce_product_import_inserted_product_object', $image_callback, 10, 2);

		try {
			$raw_results = $importer->import();
		} catch (Exception $e) {
			remove_action('woocommerce_product_import_inserted_product_object', $image_callback, 10);
			wp_delete_file($temp_file);
			return array(
				'stats'    => array_merge(
					self::empty_import_stats(),
					array('failed' => count($rows))
				),
				'messages' => array('Error en el importador de WooCommerce: ' . $e->getMessage()),
			);
		} catch (Throwable $e) {
			remove_action('woocommerce_product_import_inserted_product_object', $image_callback, 10);
			wp_delete_file($temp_file);
			return array(
				'stats'    => array_merge(
					self::empty_import_stats(),
					array('failed' => count($rows))
				),
				'messages' => array('Error fatal en el importador de WooCommerce: ' . $e->getMessage()),
			);
		}

		remove_action('woocommerce_product_import_inserted_product_object', $image_callback, 10);
		wp_delete_file($temp_file);

		return array(
			'stats'    => self::normalize_import_results($raw_results),
			'messages' => $image_failures,
		);
	}

	protected static function build_temp_headers($headers)
	{
		$temp_headers = $headers;
		$extras       = array(
			self::SOURCE_ID_META_COLUMN,
			self::SOURCE_SKU_META_COLUMN,
			self::SOURCE_PARENT_META_COLUMN,
			self::SOURCE_FILE_META_COLUMN,
		);

		foreach ($extras as $header) {
			if (! in_array($header, $temp_headers, true)) {
				$temp_headers[] = $header;
			}
		}

		return $temp_headers;
	}

	protected static function build_mapping($headers)
	{
		$mapping = array(
			'from' => array(),
			'to'   => array(),
		);

		foreach ($headers as $header) {
			$mapping['from'][] = $header;
			$mapping['to'][]   = self::map_header_to_import_key($header);
		}

		return $mapping;
	}

	protected static function map_header_to_import_key($header)
	{
		$header     = self::remove_utf8_bom(trim((string) $header));
		$normalized = self::normalize_string($header);
		$mapped     = self::lookup_default_header_mapping($normalized);

		if ('' !== $mapped) {
			return $mapped;
		}

		$default = self::default_header_mapping();

		if (isset($default[ $normalized ])) {
			return $default[ $normalized ];
		}

		if (preg_match('/^Nombre del atributo\s+(\d+)$/u', $header, $matches)) {
			return 'attributes:name' . $matches[1];
		}

		if (preg_match('/^Valor\(es\) del atributo\s+(\d+)$/u', $header, $matches)) {
			return 'attributes:value' . $matches[1];
		}

		if (preg_match('/^Atributo visible\s+(\d+)$/u', $header, $matches)) {
			return 'attributes:visible' . $matches[1];
		}

		if (preg_match('/^Atributo global\s+(\d+)$/u', $header, $matches)) {
			return 'attributes:taxonomy' . $matches[1];
		}

		if (preg_match('/^Atributo por defecto\s+(\d+)$/u', $header, $matches)) {
			return 'attributes:default' . $matches[1];
		}

		if (preg_match('/^Meta:\s*(.+)$/u', $header, $matches)) {
			return 'meta:' . trim($matches[1]);
		}

		if ('Swatches Attributes' === $header) {
			return 'meta:' . apply_filters('nlk_woo_import_swatches_meta_key', self::SWATCHES_META_KEY);
		}

		if ('Marcas' === $header) {
			return 'meta:' . self::BRANDS_META_KEY;
		}

		return '';
	}

	protected static function lookup_default_header_mapping($normalized)
	{
		$map = array(
			'id' => 'id',
			'tipo' => 'type',
			'sku' => 'sku',
			'gtin upc ean o isbn' => 'global_unique_id',
			'nombre' => 'name',
			'publicado' => 'published',
			'esta destacado' => 'featured',
			'visibilidad en el catalogo' => 'catalog_visibility',
			'descripcion corta' => 'short_description',
			'descripcion' => 'description',
			'dia en que empieza el precio rebajado' => 'date_on_sale_from',
			'dia en que termina el precio rebajado' => 'date_on_sale_to',
			'estado del impuesto' => 'tax_status',
			'clase de impuesto' => 'tax_class',
			'existencias' => 'stock_status',
			'inventario' => 'stock_quantity',
			'cantidad de bajo inventario' => 'low_stock_amount',
			'permitir reservas de productos agotados' => 'backorders',
			'vendido individualmente' => 'sold_individually',
			'peso kg' => 'weight',
			'longitud cm' => 'length',
			'anchura cm' => 'width',
			'altura cm' => 'height',
			'permitir valoraciones de clientes' => 'reviews_allowed',
			'nota de compra' => 'purchase_note',
			'precio rebajado' => 'sale_price',
			'precio normal' => 'regular_price',
			'categorias' => 'category_ids',
			'etiquetas' => 'tag_ids',
			'clase de envio' => 'shipping_class_id',
			'imagenes' => 'images',
			'limite de descargas' => 'download_limit',
			'dias de caducidad de la descarga' => 'download_expiry',
			'superior' => 'parent_id',
			'productos agrupados' => 'grouped_products',
			'ventas dirigidas' => 'upsell_ids',
			'ventas cruzadas' => 'cross_sell_ids',
			'url externa' => 'product_url',
			'texto del boton' => 'button_text',
			'posicion' => 'menu_order',
		);

		return isset($map[ $normalized ]) ? $map[ $normalized ] : '';
	}

	protected static function default_header_mapping()
	{
		return array(
			self::normalize_string('ID') => 'id',
			self::normalize_string('Tipo') => 'type',
			self::normalize_string('SKU') => 'sku',
			self::normalize_string('GTIN, UPC, EAN o ISBN') => 'global_unique_id',
			self::normalize_string('Nombre') => 'name',
			self::normalize_string('Publicado') => 'published',
			self::normalize_string('¿Está destacado?') => 'featured',
			self::normalize_string('Visibilidad en el catálogo') => 'catalog_visibility',
			self::normalize_string('Descripción corta') => 'short_description',
			self::normalize_string('Descripción') => 'description',
			self::normalize_string('Día en que empieza el precio rebajado') => 'date_on_sale_from',
			self::normalize_string('Día en que termina el precio rebajado') => 'date_on_sale_to',
			self::normalize_string('Estado del impuesto') => 'tax_status',
			self::normalize_string('Clase de impuesto') => 'tax_class',
			self::normalize_string('¿Existencias?') => 'stock_status',
			self::normalize_string('Inventario') => 'stock_quantity',
			self::normalize_string('Cantidad de bajo inventario') => 'low_stock_amount',
			self::normalize_string('¿Permitir reservas de productos agotados?') => 'backorders',
			self::normalize_string('¿Vendido individualmente?') => 'sold_individually',
			self::normalize_string('Peso (kg)') => 'weight',
			self::normalize_string('Longitud (cm)') => 'length',
			self::normalize_string('Anchura (cm)') => 'width',
			self::normalize_string('Altura (cm)') => 'height',
			self::normalize_string('¿Permitir valoraciones de clientes?') => 'reviews_allowed',
			self::normalize_string('Nota de compra') => 'purchase_note',
			self::normalize_string('Precio rebajado') => 'sale_price',
			self::normalize_string('Precio normal') => 'regular_price',
			self::normalize_string('Categorías') => 'category_ids',
			self::normalize_string('Etiquetas') => 'tag_ids',
			self::normalize_string('Clase de envío') => 'shipping_class_id',
			self::normalize_string('Imágenes') => 'images',
			self::normalize_string('Límite de descargas') => 'download_limit',
			self::normalize_string('Días de caducidad de la descarga') => 'download_expiry',
			self::normalize_string('Superior') => 'parent_id',
			self::normalize_string('Productos agrupados') => 'grouped_products',
			self::normalize_string('Ventas dirigidas') => 'upsell_ids',
			self::normalize_string('Ventas cruzadas') => 'cross_sell_ids',
			self::normalize_string('URL externa') => 'product_url',
			self::normalize_string('Texto del botón') => 'button_text',
			self::normalize_string('Posición') => 'menu_order',
		);
	}

	protected static function normalize_import_results($results)
	{
		$stats = self::empty_import_stats();

		if (! is_array($results)) {
			return $stats;
		}

		foreach (array('imported', 'imported_variations', 'updated', 'failed', 'skipped') as $key) {
			if (isset($results[ $key ])) {
				$stats[ $key ] = intval($results[ $key ]);
			}
		}

		return $stats;
	}

	protected static function sync_swatches_from_rows($rows)
	{
		$updated_terms = 0;
		$messages      = array();

		foreach ($rows as $row) {
			$raw_json = trim((string) self::row_value($row, 'Swatches Attributes'));

			if ('' === $raw_json) {
				continue;
			}

			$decoded = json_decode($raw_json, true);

			if (! is_array($decoded)) {
				$messages[] = sprintf(
					'No pude leer el JSON de swatches para el producto fuente %s.',
					trim((string) self::row_value($row, 'ID'))
				);
				continue;
			}

			$taxonomy_map = self::get_row_attribute_taxonomy_map($row);

			foreach ($decoded as $attribute_name => $attribute_data) {
				$taxonomy = self::resolve_swatch_taxonomy($attribute_name, $attribute_data, $taxonomy_map);

				if (! $taxonomy || ! taxonomy_exists($taxonomy)) {
					continue;
				}

				$terms = isset($attribute_data['terms']) && is_array($attribute_data['terms']) ? $attribute_data['terms'] : array();

				foreach ($terms as $term_name => $term_data) {
					$term = get_term_by('name', $term_name, $taxonomy);

					if (! $term || is_wp_error($term)) {
						$term = get_term_by('slug', sanitize_title($term_name), $taxonomy);
					}

					if (! $term || is_wp_error($term)) {
						continue;
					}

					$image = isset($term_data['image']) ? trim((string) $term_data['image']) : '';
					$color = isset($term_data['color']) ? trim((string) $term_data['color']) : '';

					if ($image) {
						foreach (array('product_attribute_image', 'swatch_image', 'term_image', 'image') as $meta_key) {
							update_term_meta($term->term_id, $meta_key, $image);
						}
						$updated_terms++;
					}

					if ($color) {
						foreach (array('product_attribute_color', 'swatch_color', 'color') as $meta_key) {
							update_term_meta($term->term_id, $meta_key, $color);
						}
					}

					if (! empty($term_data['tooltip_text'])) {
						update_term_meta($term->term_id, 'tooltip_text', sanitize_text_field($term_data['tooltip_text']));
					}

					if (! empty($term_data['tooltip_image'])) {
						update_term_meta($term->term_id, 'tooltip_image', esc_url_raw($term_data['tooltip_image']));
					}
				}
			}
		}

		return array(
			'updated_terms' => $updated_terms,
			'messages'      => $messages,
		);
	}

	protected static function resolve_swatch_taxonomy($attribute_name, $attribute_data, $taxonomy_map)
	{
		$key = sanitize_title($attribute_name);

		if (isset($taxonomy_map[ $key ])) {
			return $taxonomy_map[ $key ];
		}

		if (! empty($attribute_data['name'])) {
			$data_key = sanitize_title($attribute_data['name']);
			if (isset($taxonomy_map[ $data_key ])) {
				return $taxonomy_map[ $data_key ];
			}
		}

		return wc_attribute_taxonomy_name(wc_sanitize_taxonomy_name($attribute_name));
	}

	protected static function get_row_attribute_taxonomy_map($row)
	{
		$map = array();

		foreach ($row as $header => $value) {
			if (! preg_match('/^Nombre del atributo\s+(\d+)$/u', $header, $matches)) {
				continue;
			}

			$index          = $matches[1];
			$attribute_name = trim((string) $value);
			$is_global      = trim((string) self::row_value($row, 'Atributo global ' . $index));

			if ('' === $attribute_name || '1' !== $is_global) {
				continue;
			}

			$map[ sanitize_title($attribute_name) ] = wc_attribute_taxonomy_name(wc_sanitize_taxonomy_name($attribute_name));
		}

		return $map;
	}

	/**
	 * After importing a batch of parent rows, ensure every product has
	 * SOURCE_META_KEY written and cached. We look up by SKU (reliable even
	 * when WC skipped the row because the product already existed) and fall
	 * back to the existing meta query. This guarantees variations processed
	 * later in the same or subsequent batches can always resolve their parent.
	 */
	protected static function sync_source_ids_for_rows($rows)
	{
		foreach ($rows as $row) {
			$source_id = trim((string) self::row_value($row, 'ID'));

			if ('' === $source_id) {
				continue;
			}

			// Already cached from this request — nothing to do.
			if (isset(self::$source_id_cache[ $source_id ])) {
				continue;
			}

			$product_id = 0;

			// Prefer SKU lookup: works whether the product was just created
			// or was skipped by WC because it already existed.
			$sku = trim((string) self::row_value($row, 'SKU'));
			if ('' !== $sku) {
				$product_id = (int) wc_get_product_id_by_sku($sku);
			}

			// Fall back to the meta query (handles products imported without a SKU).
			if (! $product_id) {
				$product_id = self::find_local_product_id_by_reference($source_id);
			}

			if ($product_id > 0) {
				update_post_meta($product_id, self::SOURCE_META_KEY, $source_id);
				self::$source_id_cache[ $source_id ] = $product_id;
			}
		}
	}

	protected static function sync_source_keys_for_rows($rows)
	{
		foreach ($rows as $row) {
			$source_id  = trim((string) self::row_value($row, 'ID'));
			$source_sku = trim((string) self::row_value($row, 'SKU'));

			if ('' === $source_id && '' === $source_sku) {
				continue;
			}

			$product_id = 0;

			if ('' !== $source_sku) {
				$product_id = self::find_local_product_id_by_reference($source_sku);
			}

			if (! $product_id && '' !== $source_id) {
				$product_id = self::find_local_product_id_by_reference($source_id);
			}

			if ($product_id > 0) {
				if ('' !== $source_id) {
					update_post_meta($product_id, self::SOURCE_META_KEY, $source_id);
					self::$source_id_cache[ $source_id ] = $product_id;
				}

				if ('' !== $source_sku) {
					update_post_meta($product_id, self::SOURCE_SKU_META_KEY, $source_sku);
					self::$source_id_cache[ $source_sku ] = $product_id;
				}
			}
		}
	}

	protected static function find_local_product_id_by_reference($reference)
	{
		$reference = trim((string) $reference);

		if ('' === $reference) {
			return 0;
		}

		if (0 === strpos($reference, 'id:')) {
			$local_id = absint(substr($reference, 3));
			if ($local_id && wc_get_product($local_id)) {
				return $local_id;
			}
		}

		if (isset(self::$source_id_cache[ $reference ])) {
			return self::$source_id_cache[ $reference ];
		}

		$product_id = self::find_local_product_id_by_meta(self::SOURCE_META_KEY, $reference);

		if (! $product_id) {
			$product_id = self::find_local_product_id_by_meta(self::SOURCE_SKU_META_KEY, $reference);
		}

		if (! $product_id) {
			$product_id = (int) wc_get_product_id_by_sku($reference);
		}

		if ($product_id > 0) {
			self::$source_id_cache[ $reference ] = $product_id;
		}

		return $product_id;
	}

	protected static function find_local_product_id_by_meta($meta_key, $meta_value)
	{
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s ORDER BY post_id DESC LIMIT 1",
				$meta_key,
				$meta_value
			)
		);
	}

	protected static function clear_source_cache_for_rows($rows)
	{
		foreach ($rows as $row) {
			$source_id = trim((string) self::row_value($row, 'ID'));
			if ($source_id && isset(self::$source_id_cache[ $source_id ])) {
				unset(self::$source_id_cache[ $source_id ]);
			}
		}
	}

	protected static function row_value($row, $key)
	{
		return isset($row[ $key ]) ? $row[ $key ] : '';
	}

	protected static function remove_utf8_bom($value)
	{
		return preg_replace('/^\xEF\xBB\xBF/', '', (string) $value);
	}

	protected static function normalize_string($value)
	{
		$value = self::remove_utf8_bom((string) $value);

		if (function_exists('iconv')) {
			$converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
			if (false !== $converted) {
				$value = $converted;
			}
		}

		if (function_exists('mb_strtolower')) {
			$value = mb_strtolower($value, 'UTF-8');
		} else {
			$value = strtolower($value);
		}

		$value = preg_replace('/[^a-z0-9]+/', ' ', $value);
		$value = preg_replace('/\s+/', ' ', $value);

		return trim($value);
	}

	protected static function empty_import_stats()
	{
		return array(
			'imported'             => 0,
			'imported_variations'  => 0,
			'updated'              => 0,
			'failed'               => 0,
			'skipped'              => 0,
			'swatch_terms_updated' => 0,
		);
	}

	protected static function merge_import_stats($base, $incoming)
	{
		foreach ($incoming as $key => $value) {
			if (! isset($base[ $key ])) {
				$base[ $key ] = 0;
			}
			$base[ $key ] += intval($value);
		}

		return $base;
	}
}
