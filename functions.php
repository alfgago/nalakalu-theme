<?php

/**
 * nalakalu-2025 functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package nalakalu-2025
 */

if (! defined('_S_VERSION')) {
	// Replace the version number of the theme on each release.
	define('_S_VERSION', '1.0.0');
}

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function nalakalu_setup()
{
	/*
		* Make theme available for translation.
		* Translations can be filed in the /languages/ directory.
		* If you're building a theme based on nalakalu-2025, use a find and replace
		* to change 'nalakalu' to the name of your theme in all the template files.
		*/
	load_theme_textdomain('nalakalu', get_template_directory() . '/languages');

	// Add default posts and comments RSS feed links to head.
	add_theme_support('automatic-feed-links');

	/*
		* Let WordPress manage the document title.
		* By adding theme support, we declare that this theme does not use a
		* hard-coded <title> tag in the document head, and expect WordPress to
		* provide it for us.
		*/
	add_theme_support('title-tag');

	/*
		* Enable support for Post Thumbnails on posts and pages.
		*
		* @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
		*/
	add_theme_support('post-thumbnails');

	// This theme uses wp_nav_menu() in one location.
	register_nav_menus(
		array(
			'menu-1' => esc_html__('Primary', 'nalakalu'),
		)
	);

	/*
		* Switch default core markup for search form, comment form, and comments
		* to output valid HTML5.
		*/
	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		)
	);

	// Set up the WordPress core custom background feature.
	add_theme_support(
		'custom-background',
		apply_filters(
			'nalakalu_custom_background_args',
			array(
				'default-color' => 'ffffff',
				'default-image' => '',
			)
		)
	);

	// Add theme support for selective refresh for widgets.
	add_theme_support('customize-selective-refresh-widgets');

	/**
	 * Add support for core custom logo.
	 *
	 * @link https://codex.wordpress.org/Theme_Logo
	 */
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 250,
			'width'       => 250,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);
}
add_action('after_setup_theme', 'nalakalu_setup');

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function nalakalu_content_width()
{
	$GLOBALS['content_width'] = apply_filters('nalakalu_content_width', 640);
}
add_action('after_setup_theme', 'nalakalu_content_width', 0);

/**
 * Register widget area.
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 */
function nalakalu_widgets_init()
{
	register_sidebar(
		array(
			'name'          => esc_html__('Sidebar', 'nalakalu'),
			'id'            => 'sidebar-1',
			'description'   => esc_html__('Add widgets here.', 'nalakalu'),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action('widgets_init', 'nalakalu_widgets_init');

/**
 * Enqueue scripts and styles.
 */
function nalakalu_scripts()
{
	wp_enqueue_style('nalakalu-style', get_stylesheet_uri(), array(), _S_VERSION);
	wp_style_add_data('nalakalu-style', 'rtl', 'replace');

	// Enqueue Google Fonts - Fraunces
	wp_enqueue_style('fraunces-font', 'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,100..900&display=swap', array(), null);

	// Enqueue custom utilities CSS
	wp_enqueue_style('nalakalu-utilities', get_template_directory_uri() . '/assets/css/custom-utilities.css', array('fraunces-font'), _S_VERSION);

	// Enqueue Tailwind CSS from CDN
	wp_enqueue_script('tailwindcss', 'https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4', array(), '4.0.0', false);

	wp_enqueue_script('nalakalu-navigation', get_template_directory_uri() . '/js/navigation.js', array(), _S_VERSION, true);

	if (is_singular() && comments_open() && get_option('thread_comments')) {
		wp_enqueue_script('comment-reply');
	}
}
add_action('wp_enqueue_scripts', 'nalakalu_scripts');

/**
 * Implement the Custom Header feature.
 */
require get_template_directory() . '/inc/custom-header.php';

/**
 * Custom template tags for this theme.
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * Functions which enhance the theme by hooking into WordPress.
 */
require get_template_directory() . '/inc/template-functions.php';

/**
 * Customizer additions.
 */
require get_template_directory() . '/inc/customizer.php';

/**
 * Load Jetpack compatibility file.
 */
if (defined('JETPACK__VERSION')) {
	require get_template_directory() . '/inc/jetpack.php';
}

/**
 * Load WooCommerce compatibility file.
 */
if (class_exists('WooCommerce')) {
	require get_template_directory() . '/inc/woocommerce.php';
}

/**
 * Sincronización de precios CRC → USD para WooCommerce.
 */
if (class_exists('WooCommerce')) {
	require get_template_directory() . '/sincronizacion-crc-usd/init.php';
}

add_filter('block_categories_all', function ($categories, $editor_context) {
	error_log('[Blocks] Registering custom block category: nalakalu');

	// Prepend your custom category
	return array_merge(
		[
			[
				'slug'  => 'nalakalu',
				'title' => __('Nalakalu Blocks', 'nalakalu'),
				'icon'  => 'layout', // optional
			],
		],
		$categories
	);
}, 10, 2);

/**
 * Auto-load blocks + their ACF field groups
 */
function nalakalu_register_blocks()
{
	$blocks_dir = get_stylesheet_directory() . '/blocks';

	if (! is_dir($blocks_dir)) {
		error_log('[Blocks] Blocks directory not found: ' . $blocks_dir);
		return;
	}

	error_log('[Blocks] Starting block registration from: ' . $blocks_dir);

	foreach (glob($blocks_dir . '/*', GLOB_ONLYDIR) as $block_dir) {
		$name = basename($block_dir);
		error_log("[Blocks] Processing block directory: {$name}");

		// Register block.json
		if (file_exists($block_dir . '/block.json')) {
			$result = register_block_type($block_dir);
			if ($result) {
				error_log("[Blocks] Successfully registered block: {$name}");
			} else {
				error_log("[Blocks] Failed to register block: {$name}");
			}
		} else {
			error_log("[Blocks] Skipped {$name}, no block.json found");
		}
	}
}

// Register blocks on init (works regardless of ACF status)
add_action('init', 'nalakalu_register_blocks');

// Load ACF field groups if ACF is active
add_action('acf/init', function () {
	$blocks_dir = get_stylesheet_directory() . '/blocks';

	if (! is_dir($blocks_dir)) {
		return;
	}

	foreach (glob($blocks_dir . '/*', GLOB_ONLYDIR) as $block_dir) {
		$name = basename($block_dir);

		// Include ACF definitions if present
		if (file_exists($block_dir . '/acf.php')) {
			include_once $block_dir . '/acf.php';
			error_log("[Blocks] Loaded ACF fields for: {$name}");
		}
	}
});


add_action('wp_enqueue_scripts', function () {
    // CSS
    $css = get_stylesheet_directory() . '/custom.css';
    if ( file_exists($css) ) {
        wp_enqueue_style(
            'custom-css',
            get_stylesheet_directory_uri() . '/custom.css',
            [],
            filemtime($css),
            'all'
        );
    }

    // JS
    $js = get_stylesheet_directory() . '/custom.js';
    if ( file_exists($js) ) {
        $handle  = 'custom-js';
        $src     = get_stylesheet_directory_uri() . '/custom.js';
        $version = filemtime($js);

        // Si depende de jQuery u otros, ponelos en el array deps, ej: ['jquery']
        wp_enqueue_script($handle, $src, [], $version, true); // true => footer
        // WP 6.3+: no bloquea el parseo
        wp_script_add_data($handle, 'strategy', 'defer');
    }
}, PHP_INT_MAX); 


add_action('after_setup_theme', function () {
  // (opcional, por las dudas)
  add_theme_support('menus');

  // Registrá las 5 ubicaciones del mega menú
  register_nav_menus([
    'mega_nalakalu'  => __('Mega: Na Lakalú', 'nalakalu'),
    'mega_tienda'    => __('Mega: Tienda', 'nalakalu'),
    'mega_showrooms' => __('Mega: Showrooms', 'nalakalu'),
    'mega_mas'       => __('Mega: Más', 'nalakalu'),
    'mega_ayuda'     => __('Mega: Ayuda', 'nalakalu'),
  ]);
});




add_filter('nav_menu_link_attributes', function($atts, $item, $args, $depth){
  $targets = ['mega_nalakalu','mega_tienda','mega_showrooms','mega_mas','mega_ayuda'];
  if (!empty($args->theme_location) && in_array($args->theme_location, $targets, true)) {
    $extra = 'font-body-medium-light'; // 
    $atts['class'] = isset($atts['class']) ? trim($atts['class'].' '.$extra) : $extra;
  }
  return $atts;
}, 10, 4);

// --- HOTFIX: corrige "hostblocks" -> "host/blocks" en assets encolados ---
add_filter('script_loader_src', 'nlk_fix_hostblocks_src', 999);
add_filter('style_loader_src',  'nlk_fix_hostblocks_src', 999);
function nlk_fix_hostblocks_src($src){
  // Si por error quedó sin la barra
  return preg_replace('#^(https?://[^/]+)blocks/#', '$1/blocks/', $src);
}


// ========== Personalizador: Redes Sociales ==========
add_action('customize_register', function ($wp_customize) {
  // Sección
  $wp_customize->add_section('nlk_socials', [
    'title'       => __('Redes Sociales', 'nalakalu'),
    'priority'    => 160,
    'description' => __('Cargá las URLs de tus redes. Dejá vacío para ocultar.', 'nalakalu'),
  ]);

  // Redes disponibles (podés agregar/quitar)
  $networks = [
    'facebook'  => 'Facebook',
    'instagram' => 'Instagram',
    'tiktok'    => 'TikTok',
    'x'         => 'X (Twitter)',
    'youtube'   => 'YouTube',
    'linkedin'  => 'LinkedIn',
    'pinterest' => 'Pinterest',
  ];

  foreach ($networks as $key => $label) {
    $setting_id = "nlk_social_{$key}";

    $wp_customize->add_setting($setting_id, [
      'default'           => '',
      'sanitize_callback' => 'esc_url_raw',
      'transport'         => 'refresh', // simple y efectivo
    ]);

    $wp_customize->add_control($setting_id, [
      'label'   => sprintf(__('%s URL', 'nalakalu'), $label),
      'section' => 'nlk_socials',
      'type'    => 'url',
    ]);
  }
});

// Helper: devuelve array de redes con url + clase FA
function nlk_get_socials() {
  $map = [
    'facebook'  => ['label' => 'Facebook',  'icon' => 'fab fa-facebook-f'],
    'instagram' => ['label' => 'Instagram', 'icon' => 'fab fa-instagram'],
    'tiktok'    => ['label' => 'TikTok',    'icon' => 'fab fa-tiktok'],
    'x'         => ['label' => 'X',         'icon' => 'fab fa-x-twitter'], // Font Awesome 6
    'youtube'   => ['label' => 'YouTube',   'icon' => 'fab fa-youtube'],
    'linkedin'  => ['label' => 'LinkedIn',  'icon' => 'fab fa-linkedin-in'],
    'pinterest' => ['label' => 'Pinterest', 'icon' => 'fab fa-pinterest-p'],
  ];

  $out = [];
  foreach ($map as $key => $meta) {
    $url = get_theme_mod("nlk_social_{$key}");
    if ($url) {
      $out[$key] = [
        'url'   => esc_url($url),
        'label' => $meta['label'],
        'icon'  => $meta['icon'],
      ];
    }
  }
  return $out;
}

// Render: imprime los <a> con <i> de Font Awesome
function nlk_render_social_icons() {
  $items = nlk_get_socials();
  if (!$items) return;
  foreach ($items as $it) {
    printf(
      '<a href="%1$s" target="_blank" rel="noopener noreferrer" aria-label="%2$s"><i class="%3$s" aria-hidden="true"></i></a>',
      esc_url($it['url']),
      esc_attr($it['label']),
      esc_attr($it['icon'])
    );
  }
}

// (Opcional) Encolar Font Awesome 
add_action('wp_enqueue_scripts', function () {
  wp_enqueue_style(
    'fa-6',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    [],
    '6.4.0'
  );
});



add_action('after_setup_theme', function () {
  // Soporte de menús
  add_theme_support('menus');

  register_nav_menus([
    'footer_nalakalu'  => __('Footer: Na lakalú', 'nalakalu'),
    'footer_showrooms' => __('Footer: Showrooms',  'nalakalu'),
    'footer_productos' => __('Footer: Productos',  'nalakalu'),
    'footer_mas'       => __('Footer: Más',        'nalakalu'),
  ]);
});

add_action('customize_register', function ($wp_customize) {
  $wp_customize->add_section('nlk_footer', [
    'title'       => __('Ajustes de Footer', 'nalakalu'),
    'priority'    => 165,
    'description' => __('Textos del newsletter y leyenda inferior.', 'nalakalu'),
  ]);

  // Título newsletter
  $wp_customize->add_setting('nlk_news_title', [
    'default'           => 'Recibí inspiración y novedades directamente en tu correo.',
    'sanitize_callback' => 'wp_kses_post',
    'transport'         => 'refresh',
  ]);
  $wp_customize->add_control('nlk_news_title', [
    'label'   => __('Título newsletter', 'nalakalu'),
    'section' => 'nlk_footer',
    'type'    => 'text',
  ]);

  // Título del formulario
  $wp_customize->add_setting('nlk_news_form_title', [
    'default'           => 'Suscríbete',
    'sanitize_callback' => 'sanitize_text_field',
  ]);
  $wp_customize->add_control('nlk_news_form_title', [
    'label'   => __('Título del formulario', 'nalakalu'),
    'section' => 'nlk_footer',
    'type'    => 'text',
  ]);

  // Placeholder del email
  $wp_customize->add_setting('nlk_news_placeholder', [
    'default'           => 'Correo Electrónico',
    'sanitize_callback' => 'sanitize_text_field',
  ]);
  $wp_customize->add_control('nlk_news_placeholder', [
    'label'   => __('Placeholder del email', 'nalakalu'),
    'section' => 'nlk_footer',
    'type'    => 'text',
  ]);

  // Leyenda inferior
  $wp_customize->add_setting('nlk_footer_legal', [
    'default'           => '© ' . date_i18n('Y') . ' Todos los derechos reservados',
    'sanitize_callback' => 'wp_kses_post',
  ]);
  $wp_customize->add_control('nlk_footer_legal', [
    'label'   => __('Leyenda inferior', 'nalakalu'),
    'section' => 'nlk_footer',
    'type'    => 'text',
  ]);
});



add_action('wp_enqueue_scripts', function () {
  if ( ! class_exists('WooCommerce') ) return;

  if ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() || is_shop() || is_product_taxonomy() || is_product() ) {
    $rel  = '/woocommerce/woo.css';
    $abs  = get_stylesheet_directory() . $rel;
    $uri  = get_stylesheet_directory_uri() . $rel;
    $ver  = file_exists($abs) ? filemtime($abs) : null;

    wp_enqueue_style('nl-woo', $uri, [], $ver);
  }
});

// ========= Lookbook: detectar callback del plugin y reubicar =========
if (!function_exists('nl_render_lookbook_relocated')) {
  function nl_render_lookbook_relocated($args = []) {
    $args = array_merge([
      'debug'    => true,   // muestra panel de debug solo a admins
      'relocate' => true,   // si true, desengancha el hook original para que no duplique
    ], (array)$args);

    if (!function_exists('shortcode_exists') || !shortcode_exists('woocommerce_lookbook')) {
      if ($args['debug'] && current_user_can('manage_options')) {
        echo '<p style="background:#fff3cd;padding:10px;border:1px solid #ffeeba">⚠️ Shortcode <code>woocommerce_lookbook</code> no registrado.</p>';
      }
      return;
    }

    global $wp_filter;
    $hooks_to_scan = [
      'woocommerce_after_single_product_summary',
      'woocommerce_single_product_summary',
      'woocommerce_before_single_product_summary',
      'the_content',
    ];

    $found = null; // ['hook'=>..., 'priority'=>..., 'callback'=>..., 'label'=>...]
    $scanlog = [];

    foreach ($hooks_to_scan as $hook) {
      if (empty($wp_filter[$hook])) { $scanlog[] = "$hook: vacío"; continue; }
      $scanlog[] = "$hook: OK";

      // WP_Hook
      $callbacks = $wp_filter[$hook]->callbacks ?? [];
      foreach ($callbacks as $prio => $bucket) {
        foreach ($bucket as $idx => $data) {
          $cb = $data['function'];
          $label = '(desconocido)';

          if (is_array($cb) && is_object($cb[0])) {
            $label = get_class($cb[0]).'::'.$cb[1];
          } elseif (is_array($cb) && is_string($cb[0])) {
            $label = $cb[0].'::'.$cb[1];
          } elseif (is_string($cb)) {
            $label = $cb;
          } elseif ($cb instanceof Closure) {
            $label = 'Closure';
          }

          $haystack = strtolower($label);
          if (strpos($haystack, 'lookbook') !== false || strpos($haystack, 'wlbk') !== false) {
            $found = [
              'hook'     => $hook,
              'priority' => (int)$prio,
              'callback' => $cb,
              'label'    => $label,
            ];
            break 2;
          }
        }
      }
    }

    if (!$found) {
      if ($args['debug'] && current_user_can('manage_options')) {
        echo '<details open style="margin:1rem 0;padding:1rem;border:1px dashed #c00;background:#fff7f7">
                <summary style="cursor:pointer;font-weight:600;color:#c00">DEBUG Lookbook</summary>
                <pre style="white-space:pre-wrap;font:12px/1.5 ui-monospace">'
              . esc_html("No encontré callbacks con 'lookbook'/'wlbk'.\nEscaneo:\n- ".implode("\n- ", $scanlog))
              . '</pre></details>';
      }
      return;
    }

    if ($args['relocate']) {
      remove_action($found['hook'], $found['callback'], $found['priority']);
    }


    ob_start();
    try {
      call_user_func($found['callback']);
    } catch (\Throwable $e) {
      if ($args['debug'] && current_user_can('manage_options')) {
        echo '<p style="background:#f8d7da;padding:10px;border:1px solid #f5c2c7">❌ Error al invocar el callback: '
             . esc_html($e->getMessage()) . '</p>';
      }
    }
    $html = ob_get_clean();

    // Salida + Debug
    echo $html;

    if ($args['debug'] && current_user_can('manage_options')) {
      $len = strlen(trim(wp_strip_all_tags($html)));
      echo '<details open style="margin:1rem 0;padding:1rem;border:1px dashed #0a0;background:#f4fff4">
              <summary style="cursor:pointer;font-weight:600;color:#0a0">DEBUG Lookbook (reubicado)</summary>
              <pre style="white-space:pre-wrap;font:12px/1.5 ui-monospace">'
            . esc_html(
                "Hook original: {$found['hook']}\n".
                "Prioridad: {$found['priority']}\n".
                "Callback: {$found['label']}\n".
                "Relocate: ".($args['relocate']?'sí':'no')."\n".
                "HTML len: $len"
              )
            . '</pre></details>';
    }
  }
}

// ===== Handler formulario de contacto bloque "contact-form" =====
if ( ! function_exists('nlk_handle_contact_form') ) {
  function nlk_handle_contact_form() {

    // 1) Chequeo de seguridad (nonce)
    if (
      ! isset($_POST['nlk_contact_form_nonce']) ||
      ! wp_verify_nonce( $_POST['nlk_contact_form_nonce'], 'nlk_contact_form' )
    ) {
      wp_die('Security check failed', 403);
    }

    // 2) Sanitizar campos
    $nombre   = isset($_POST['nlk_nombre'])   ? sanitize_text_field($_POST['nlk_nombre'])   : '';
    $apellido = isset($_POST['nlk_apellido']) ? sanitize_text_field($_POST['nlk_apellido']) : '';
    $email    = isset($_POST['nlk_email'])    ? sanitize_email($_POST['nlk_email'])         : '';
    $tel      = isset($_POST['nlk_telefono']) ? sanitize_text_field($_POST['nlk_telefono']) : '';
    $asunto   = isset($_POST['nlk_asunto'])   ? sanitize_text_field($_POST['nlk_asunto'])   : '';
    $mensaje  = isset($_POST['nlk_mensaje'])  ? wp_kses_post($_POST['nlk_mensaje'])         : '';

    // 3) Email destino (desde campo oculto + ACF form_responses)
    $to = isset($_POST['nlk_cf_to']) ? sanitize_email($_POST['nlk_cf_to']) : '';
    if ( ! $to ) {
      $to = get_option('admin_email');
    }

    if ( ! $to ) {
      wp_die('No se encontró un email de destino para el formulario.', 500);
    }

    // 4) Armar asunto y cuerpo
    $subject = sprintf(
      '[Contacto Nalakalu] %s %s',
      $nombre ?: 'Sin nombre',
      $apellido
    );

    $body  = "Nombre: {$nombre} {$apellido}\n";
    $body .= "Email: {$email}\n";
    $body .= "Teléfono: {$tel}\n";
    $body .= "Asunto: {$asunto}\n\n";
    $body .= "Mensaje:\n{$mensaje}\n";

    $headers = [];
    if ( $email ) {
      $headers[] = 'Reply-To: ' . $email;
    }

    // 5) Enviar
    wp_mail( $to, $subject, $body, $headers );

    // 6) Redirect de vuelta con query de éxito
    $redirect = wp_get_referer() ?: home_url('/');
    $redirect = add_query_arg( 'nlk_cf_success', '1', $redirect );

    wp_safe_redirect( $redirect );
    exit;
  }

  add_action( 'admin_post_nlk_contact_form',        'nlk_handle_contact_form' );
  add_action( 'admin_post_nopriv_nlk_contact_form', 'nlk_handle_contact_form' );
}


// Quitar woo.css (de carpeta /woocommerce/) SOLO en la taxonomía "coleccion"
add_action( 'wp_enqueue_scripts', function() {

    // Solo en archivos de archivo de la taxonomía "coleccion"
    if ( ! is_tax( 'coleccion' ) ) {
        return;
    }

    global $wp_styles;

    if ( ! $wp_styles || empty( $wp_styles->queue ) ) {
        return;
    }

    foreach ( $wp_styles->queue as $handle ) {
        if ( empty( $wp_styles->registered[ $handle ] ) ) {
            continue;
        }

        $style = $wp_styles->registered[ $handle ];
        $src   = isset( $style->src ) ? (string) $style->src : '';

        if ( strpos( $src, 'woocommerce/woo.css' ) !== false || 
             substr( $src, -8 ) === 'woo.css' ) {

            wp_dequeue_style( $handle );
            wp_deregister_style( $handle );
        }
    }

}, 200);


//Filtro tienda

// Helpers compartidos (para que el AJAX también tenga la default)
if (!function_exists('nlk_shop__get_term_thumb_url')) {
  function nlk_shop__get_term_thumb_url($term_id){
    $thumb_id = (int) get_term_meta($term_id, 'thumbnail_id', true);
    if ($thumb_id) {
      $url = wp_get_attachment_image_url($thumb_id, 'full');
      return $url ? $url : '';
    }
    return '';
  }
}

if (!function_exists('nlk_shop__get_default_banner_url')) {
  function nlk_shop__get_default_banner_url(){
    $parents = get_terms([
      'taxonomy'   => 'product_cat',
      'hide_empty' => false,
      'parent'     => 0,
      'orderby'    => 'name',
      'order'      => 'ASC',
    ]);

    if (!empty($parents) && !is_wp_error($parents)) {
      foreach ($parents as $p) {
        $u = nlk_shop__get_term_thumb_url($p->term_id);
        if ($u) return $u;

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

// AJAX Handler
if (!function_exists('nlk_shop_ajax_filter')) {
  function nlk_shop_ajax_filter() {
    check_ajax_referer('nlk_shop_nonce', 'nonce');

    if (!class_exists('WooCommerce')) {
      wp_send_json_error(['message' => 'WooCommerce no está activo.']);
    }

    $term_id  = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
    $page     = isset($_POST['page']) ? max(1, absint($_POST['page'])) : 1;
    $per_page = isset($_POST['per_page']) ? max(1, absint($_POST['per_page'])) : 12;
    $order    = isset($_POST['order']) ? sanitize_text_field(wp_unslash($_POST['order'])) : 'stock';

    $args = [
      'post_type'      => 'product',
      'post_status'    => 'publish',
      'posts_per_page' => $per_page,
      'paged'          => $page,
    ];

    if ($term_id) {
      $args['tax_query'] = [[
        'taxonomy' => 'product_cat',
        'field'    => 'term_id',
        'terms'    => [$term_id],
      ]];
    }

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
        $order = 'stock';
        break;
    }

    $q = new WP_Query($args);

    $title = 'TIENDA';
    $banner_url = '';

    if ($term_id) {
      $term = get_term($term_id, 'product_cat');
      if ($term && !is_wp_error($term)) $title = $term->name;

      $banner_url = nlk_shop__get_term_thumb_url($term_id);
    }

    // ✅ fallback default banner (primera categoría)
    if (!$banner_url) {
      $banner_url = nlk_shop__get_default_banner_url();
    }

    $count = (int) ($q->found_posts ?? 0);
    $count_label = sprintf(_n('%s Producto', '%s Productos', $count, 'textdomain'), number_format_i18n($count));

    // Grid HTML
    ob_start();
    if ($q->have_posts()) :
      while ($q->have_posts()) : $q->the_post();
        $product = wc_get_product(get_the_ID());
        $img = get_the_post_thumbnail_url(get_the_ID(), 'large');
        if (!$img) $img = wc_placeholder_img_src('large');
        ?>
        <a class="nlk-shop__card" href="<?php the_permalink(); ?>">
          <img class="nlk-shop__img" src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr(get_the_title()); ?>">
          <div class="nlk-shop__info">
            <div class="nlk-shop__name"><?php the_title(); ?></div>
            <div class="nlk-shop__price"><?php echo wp_kses_post($product->get_price_html()); ?></div>
          </div>
        </a>
        <?php
      endwhile;
      wp_reset_postdata();
    else:
      echo '<div class="nlk-shop__empty">No hay productos para esta categoría.</div>';
    endif;
    $grid_html = ob_get_clean();

    // Pagination HTML
    $total_pages = max(1, (int) $q->max_num_pages);
    $current     = max(1, (int) $page);

    ob_start(); ?>
      <button class="nlk-shop__page-btn nlk-shop__pagination-btn" data-page="<?php echo esc_attr(max(1, $current-1)); ?>" <?php disabled($current <= 1); ?>><svg xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none">
  <path d="M6.96785 13.8533C7.01866 13.9011 7.0786 13.9387 7.14425 13.9638C7.2099 13.989 7.27997 14.0012 7.35047 13.9999C7.49284 13.9972 7.62829 13.9393 7.72701 13.839C7.82574 13.7386 7.87966 13.604 7.8769 13.4648C7.87415 13.3256 7.81496 13.1931 7.71234 13.0966L1.78861 7.52432L15.2131 7.52432C15.3555 7.52432 15.4921 7.46901 15.5927 7.37055C15.6934 7.27209 15.75 7.13854 15.75 6.9993C15.75 6.86005 15.6934 6.72651 15.5927 6.62805C15.4921 6.52959 15.3555 6.47427 15.2131 6.47427L1.79004 6.47427L7.71305 0.90341C7.76387 0.855607 7.80455 0.798484 7.83279 0.735305C7.86102 0.672125 7.87626 0.604126 7.87762 0.535189C7.87898 0.466252 7.86645 0.397728 7.84073 0.333528C7.81501 0.26933 7.77661 0.210712 7.72773 0.161024C7.67885 0.111336 7.62043 0.0715501 7.55582 0.0439384C7.49122 0.0163257 7.42168 0.00142741 7.35118 9.5129e-05C7.28069 -0.00123715 7.21062 0.0110214 7.14497 0.0361717C7.07931 0.061321 7.01937 0.09887 6.96856 0.146673L0.219443 6.49527C0.15005 6.56059 0.0948477 6.63892 0.0571413 6.72559C0.019435 6.81225 0 6.90547 0 6.99965C0 7.09383 0.019435 7.18705 0.0571413 7.27371C0.0948477 7.36038 0.15005 7.43871 0.219443 7.50402L6.96785 13.8533Z" fill="#3D332B"/>
</svg></button>
      <span class="nlk-shop__page-info"><?php echo esc_html($current . ' of ' . $total_pages); ?></span>
      <button class="nlk-shop__page-btn nlk-shop__pagination-btn" data-page="<?php echo esc_attr(min($total_pages, $current+1)); ?>" <?php disabled($current >= $total_pages); ?>><svg xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none">
  <path d="M8.78215 13.8533C8.73134 13.9011 8.6714 13.9387 8.60575 13.9638C8.5401 13.989 8.47003 14.0012 8.39953 13.9999C8.25716 13.9972 8.12171 13.9393 8.02299 13.839C7.92426 13.7386 7.87034 13.604 7.8731 13.4648C7.87585 13.3256 7.93504 13.1931 8.03766 13.0966L13.9614 7.52432L0.536892 7.52432C0.394499 7.52432 0.257937 7.46901 0.15725 7.37055C0.0565633 7.27209 -1.93715e-06 7.13854 -1.93715e-06 6.9993C-1.93715e-06 6.86005 0.0565633 6.72651 0.15725 6.62805C0.257937 6.52959 0.394499 6.47427 0.536892 6.47427L13.96 6.47427L8.03695 0.90341C7.98613 0.855607 7.94545 0.798484 7.91721 0.735305C7.88898 0.672125 7.87374 0.604126 7.87238 0.535189C7.87102 0.466252 7.88355 0.397728 7.90927 0.333528C7.93499 0.26933 7.97339 0.210712 8.02227 0.161024C8.07115 0.111336 8.12957 0.0715501 8.19418 0.0439384C8.25878 0.0163257 8.32832 0.00142741 8.39882 9.5129e-05C8.46931 -0.00123715 8.53938 0.0110214 8.60503 0.0361717C8.67069 0.061321 8.73063 0.09887 8.78144 0.146673L15.5306 6.49527C15.6 6.56059 15.6552 6.63892 15.6929 6.72559C15.7306 6.81225 15.75 6.90547 15.75 6.99965C15.75 7.09383 15.7306 7.18705 15.6929 7.27371C15.6552 7.36038 15.6 7.43871 15.5306 7.50402L8.78215 13.8533Z" fill="#3D332B"/>
</svg></button>
    <?php
    $pagination_html = ob_get_clean();

    wp_send_json_success([
      'active_term_id'   => $term_id,
      'title'            => $title,
      'count'            => $count,
      'count_label'      => $count_label,
      'banner_url'       => $banner_url,
      'grid_html'        => $grid_html,
      'pagination_html'  => $pagination_html,
      'order'            => $order,
    ]);
  }

  add_action('wp_ajax_nlk_shop_filter', 'nlk_shop_ajax_filter');
  add_action('wp_ajax_nopriv_nlk_shop_filter', 'nlk_shop_ajax_filter');
}


add_action('wp_enqueue_scripts', function () {
  if ( is_singular('post') ) {
    wp_enqueue_style(
      'nlk-single-post',
      get_stylesheet_directory_uri() . '/assets/css/single-post.css',
      [],
      filemtime(get_stylesheet_directory() . '/assets/css/single-post.css')
    );
  }
});


add_filter('term_link', function($url, $term, $taxonomy){
  if ($taxonomy !== 'product_cat') return $url;
  return add_query_arg(['cat' => $term->slug], home_url('/tienda/'));
}, 10, 3);

//Cargar template de colección evitando que Woo lo pise

add_filter('template_include', function($template) {
  if ( is_admin() || wp_doing_ajax() ) return $template;

  if ( is_tax('coleccion') ) {
    $term = get_queried_object();

    // respeta templates más específicos por si algun dia necesitan ser cambiados
    if ( $term instanceof WP_Term ) {
      $forced = locate_template([
        "taxonomy-{$term->taxonomy}-{$term->slug}.php",
        "taxonomy-{$term->taxonomy}.php",
      ]);
      if ($forced) return $forced;
    } else {
      $forced = locate_template("taxonomy-coleccion.php");
      if ($forced) return $forced;
    }
  }

  return $template;
}, 999);

add_filter('wpseo_title', function($title){
  if ( is_tax('coleccion') ) {
    $term = get_queried_object();
    if ( $term instanceof WP_Term ) {
    return $term->name . ' - Colección';
    }
  }
  return $title;
}, 99);

add_action('wp_ajax_nlk_product_search', 'nlk_product_search');
add_action('wp_ajax_nopriv_nlk_product_search', 'nlk_product_search');

function nlk_product_search() {
  if (isset($_GET['nonce']) && !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'nlk_product_search')) {
    wp_send_json_error(['message' => 'Invalid nonce'], 403);
  }

  $term = isset($_GET['term']) ? sanitize_text_field(wp_unslash($_GET['term'])) : '';
  $term = trim($term);

  if ($term === '') {
    wp_send_json_success(['products' => []]);
  }

  $q = new WP_Query([
    'post_type'      => 'product',
    'post_status'    => 'publish',
    's'              => $term,
    'posts_per_page' => 12,
    'no_found_rows'  => true,
  ]);

  $out = [];
  foreach ($q->posts as $p) {
    $product = wc_get_product($p->ID);
    if (!$product) continue;

    $desc = $product->get_short_description();
    if (!$desc) $desc = get_the_excerpt($p->ID);
    $desc = wp_strip_all_tags($desc);

    $img = get_the_post_thumbnail_url($p->ID, 'woocommerce_thumbnail');
    if (!$img && function_exists('wc_placeholder_img_src')) $img = wc_placeholder_img_src('woocommerce_thumbnail');

    $out[] = [
      'id'         => $p->ID,
      'name'       => get_the_title($p->ID),
      'description'=> $desc,
      'price_html' => $product->get_price_html(),
      'url'        => get_permalink($p->ID),
      'image'      => $img ?: '',
    ];
  }

  wp_send_json_success(['products' => $out]);
}

add_filter('template_include', function ($template) {
    if (is_tax('showroom')) {
        $custom = get_stylesheet_directory() . '/taxonomy-showroom.php';

        if (file_exists($custom)) {
            return $custom;
        }
    }

    return $template;
}, 999);

add_action('wp_enqueue_scripts', function () {
    if (!is_tax('showroom')) {
        return;
    }

    $css_rel_path = '/blocks/shop/style.css';
    $css_abs_path = get_stylesheet_directory() . $css_rel_path;
    $css_uri      = get_stylesheet_directory_uri() . $css_rel_path;

    if (file_exists($css_abs_path)) {
        wp_enqueue_style(
            'nlk-shop-taxonomy-style',
            $css_uri,
            [],
            filemtime($css_abs_path)
        );
    }
}, 30);

if ( ! function_exists('nlk_shop__apply_order_to_args') ) {
  function nlk_shop__apply_order_to_args(array $args, $order){
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

    return $args;
  }
}

if ( ! function_exists('nlk_shop__get_breadcrumbs_html') ) {
  function nlk_shop__get_breadcrumbs_html($selected_term_id = 0){
    $crumbs = [];
    $pos    = 1;

    $shop_url = function_exists('wc_get_page_permalink')
      ? wc_get_page_permalink('shop')
      : get_post_type_archive_link('product');

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

if ( ! function_exists('nlk_shop__get_count_label') ) {
  function nlk_shop__get_count_label($count){
    return sprintf(_n('%s Producto', '%s Productos', (int)$count, 'textdomain'), number_format_i18n((int)$count));
  }
}

if ( ! function_exists('nlk_shop__get_grid_html') ) {
  function nlk_shop__get_grid_html($q){
    ob_start();

    if ($q->have_posts()) :
      while ($q->have_posts()) : $q->the_post();
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
        <?php
      endwhile;
      wp_reset_postdata();
    else :
      ?>
      <div class="nlk-shop__empty">No hay productos para esta categoría.</div>
      <?php
    endif;

    return ob_get_clean();
  }
}

if ( ! function_exists('nlk_shop__get_pagination_html') ) {
  function nlk_shop__get_pagination_html($current, $total_pages){
    $current     = max(1, (int) $current);
    $total_pages = max(1, (int) $total_pages);

    ob_start();
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
    <?php
    return ob_get_clean();
  }
}

function nlk_shop_filter_breadcrumbs() {
  check_ajax_referer('nlk_shop_nonce', 'nonce');

  $term_id  = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
  $page     = isset($_POST['page']) ? max(1, absint($_POST['page'])) : 1;
  $per_page = isset($_POST['per_page']) ? max(1, absint($_POST['per_page'])) : 12;
  $order    = isset($_POST['order']) ? sanitize_text_field(wp_unslash($_POST['order'])) : 'stock';

  $args = [
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => $per_page,
    'paged'          => $page,
  ];

  if ($term_id) {
    $args['tax_query'] = [[
      'taxonomy' => 'product_cat',
      'field'    => 'term_id',
      'terms'    => [$term_id],
    ]];
  }

  $args = nlk_shop__apply_order_to_args($args, $order);

  $q = new WP_Query($args);

  $selected_term = $term_id ? get_term($term_id, 'product_cat') : null;
  $title_raw     = ($selected_term && !is_wp_error($selected_term)) ? $selected_term->name : 'TIENDA';
  $count         = (int) ($q->found_posts ?? 0);

  wp_send_json_success([
    'title'            => $title_raw,
    'count_label'      => nlk_shop__get_count_label($count),
    'grid_html'        => nlk_shop__get_grid_html($q),
    'pagination_html'  => nlk_shop__get_pagination_html($page, (int) $q->max_num_pages),
    'active_term_id'   => $term_id,
    'order'            => $order,
    'breadcrumbs_html' => nlk_shop__get_breadcrumbs_html($term_id),
  ]);
}

add_action('wp_ajax_nlk_shop_filter_breadcrumbs', 'nlk_shop_filter_breadcrumbs');
add_action('wp_ajax_nopriv_nlk_shop_filter_breadcrumbs', 'nlk_shop_filter_breadcrumbs');