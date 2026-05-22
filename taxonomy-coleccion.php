
<?php
/**
 * Template para la taxonomía "coleccion"
 */

defined('ABSPATH') || exit;

get_header();

// ========= Datos del término actual =========
$term = get_queried_object();
if ( ! $term || is_wp_error( $term ) ) {
    $term_name  = '';
    $bg_field   = null;
    $logo_field = null;
} else {
    $term_name = $term->name;

    // Campo ACF en la taxonomía: background_image
    $bg_field = get_field( 'background_image', $term );

    if ( ! $bg_field ) {
        $bg_field = get_field( 'background_image', $term->taxonomy . '_' . $term->term_id );
    }

    // Campo ACF en la taxonomía: logo
    $logo_field = get_field( 'logo', $term );

    if ( ! $logo_field ) {
        $logo_field = get_field( 'logo', $term->taxonomy . '_' . $term->term_id );
    }
}
// Helper para sacar URL de imagen (evitamos redeclare)
if ( ! function_exists( 'nlk_coleccion_hero_img_url' ) ) {
    function nlk_coleccion_hero_img_url( $img, $size = 'full' ) {
        if ( is_array( $img ) ) {
            if ( ! empty( $img['url'] ) ) {
                return esc_url( $img['url'] );
            }
            if ( ! empty( $img['ID'] ) ) {
                $src = wp_get_attachment_image_src( (int) $img['ID'], $size );
                if ( $src && ! empty( $src[0] ) ) {
                    return esc_url( $src[0] );
                }
            }
        } elseif ( is_numeric( $img ) ) {
            $src = wp_get_attachment_image_src( (int) $img, $size );
            if ( $src && ! empty( $src[0] ) ) {
                return esc_url( $src[0] );
            }
        } elseif ( is_string( $img ) ) {
            return esc_url( $img );
        }
        return '';
    }
}

$bg_url   = nlk_coleccion_hero_img_url( $bg_field );
$logo_url = nlk_coleccion_hero_img_url( $logo_field, 'medium' );

// ID y clases del hero
$section_id = 'coleccion-hero-' . ( $term ? $term->term_id : uniqid() );
$classes    = 'nl-coleccion-hero';

// Style inline para gradiente + imagen
$bg_style = '';
if ( $bg_url ) {
    $bg_style = "background-image: linear-gradient(to bottom,
        rgba(60, 50, 40, 0.3) 0%,
        rgba(40, 35, 30, 0.4) 50%,
        rgba(30, 25, 20, 0.5) 100%
      ), url('" . esc_url( $bg_url ) . "');";
}
?>

<main id="primary" class="site-main">

  <!-- HERO DE COLECCIÓN -->
  <section id="<?php echo esc_attr( $section_id ); ?>" class="<?php echo esc_attr( $classes ); ?>">
    <div class="nl-coleccion-hero__background"<?php if ( $bg_style ) : ?> style="<?php echo esc_attr( $bg_style ); ?>"<?php endif; ?>></div>

    <div class="nl-coleccion-hero__content">
      <span class="nl-coleccion-hero__label font-heading-2 text-white">Colección</span>
<?php if ( $logo_url ) : ?>
  <img
    class="nl-coleccion-hero__logo"
    src="<?php echo esc_url( $logo_url ); ?>"
    alt="<?php echo esc_attr( $term_name ); ?>"
    loading="eager"
    decoding="async">
<?php endif; ?>
      
    </div>
  </section>
  
  

<?php
/**
 * Sección: Gallery Taxonomy (sin hero)
 * Campos ACF (asociados al término de la taxonomía "coleccion"):
 * - background1 (gallery)
 * - title1 (text)
 * - description1 (textarea)
 * - background2 (file/video)
 * - url_video (url - Vimeo opcional para sección 2)
 * - title2 (text)
 * - description2 (textarea)
 */

if ( ! function_exists('get_field') ) {
    echo '<p><em>ACF plugin required.</em></p>';
} else {

    $term = get_queried_object();
    if ( ! ( $term instanceof WP_Term ) ) {
        $term = null;
    }

    /**
     * Helper para obtener URL de imagen
     */
    if ( ! function_exists('nl_tax_gallery_img_url') ) {
        function nl_tax_gallery_img_url( $img, $size = 'large' ) {
            if ( is_array($img) ) {
                if ( ! empty($img['sizes'][$size]) ) {
                    return esc_url($img['sizes'][$size]);
                }

                if ( ! empty($img['url']) ) {
                    return esc_url($img['url']);
                }

                if ( ! empty($img['ID']) ) {
                    $src = wp_get_attachment_image_src((int) $img['ID'], $size);
                    if ( $src && ! empty($src[0]) ) {
                        return esc_url($src[0]);
                    }
                }
            } elseif ( is_numeric($img) ) {
                $src = wp_get_attachment_image_src((int) $img, $size);
                if ( $src && ! empty($src[0]) ) {
                    return esc_url($src[0]);
                }
            } elseif ( is_string($img) && filter_var($img, FILTER_VALIDATE_URL) ) {
                return esc_url($img);
            }

            return '';
        }
    }

    /**
     * Helper para obtener URLs de una galería ACF
     */
    if ( ! function_exists('nl_tax_gallery_gallery_urls') ) {
        function nl_tax_gallery_gallery_urls( $gallery, $size = 'large' ) {
            $urls = [];

            if ( is_array($gallery) ) {
                foreach ( $gallery as $item ) {
                    $url = nl_tax_gallery_img_url($item, $size);

                    if ( ! $url ) {
                        $url = nl_tax_gallery_img_url($item, 'full');
                    }

                    if ( $url ) {
                        $urls[] = $url;
                    }
                }
            } else {
                // Fallback por si ACF devuelve una sola imagen
                $single = nl_tax_gallery_img_url($gallery, $size);

                if ( ! $single ) {
                    $single = nl_tax_gallery_img_url($gallery, 'full');
                }

                if ( $single ) {
                    $urls[] = $single;
                }
            }

            return array_values(array_unique(array_filter($urls)));
        }
    }

    /**
     * Helper para obtener datos de archivo/video
     */
    if ( ! function_exists('nl_tax_gallery_media_data') ) {
        function nl_tax_gallery_media_data( $file ) {
            $url  = '';
            $mime = '';
            $ext  = '';

            if ( is_array($file) ) {
                if ( ! empty($file['url']) ) {
                    $url = $file['url'];
                }

                if ( ! empty($file['mime_type']) ) {
                    $mime = $file['mime_type'];
                } elseif ( ! empty($file['type']) && ! empty($file['subtype']) ) {
                    $mime = $file['type'] . '/' . $file['subtype'];
                }

                if ( empty($mime) && ! empty($file['filename']) ) {
                    $wp_filetype = wp_check_filetype($file['filename']);

                    if ( ! empty($wp_filetype['type']) ) {
                        $mime = $wp_filetype['type'];
                    }

                    if ( ! empty($wp_filetype['ext']) ) {
                        $ext = strtolower($wp_filetype['ext']);
                    }
                }

                if ( empty($ext) && ! empty($url) ) {
                    $path = parse_url($url, PHP_URL_PATH);

                    if ( $path ) {
                        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    }
                }
            } elseif ( is_numeric($file) ) {
                $file_id = (int) $file;
                $url     = wp_get_attachment_url($file_id);
                $mime    = get_post_mime_type($file_id);

                $file_path = get_attached_file($file_id);

                if ( $file_path ) {
                    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                }
            } elseif ( is_string($file) && filter_var($file, FILTER_VALIDATE_URL) ) {
                $url = $file;

                $wp_filetype = wp_check_filetype($url);

                if ( ! empty($wp_filetype['type']) ) {
                    $mime = $wp_filetype['type'];
                }

                if ( ! empty($wp_filetype['ext']) ) {
                    $ext = strtolower($wp_filetype['ext']);
                }

                if ( empty($ext) ) {
                    $path = parse_url($url, PHP_URL_PATH);

                    if ( $path ) {
                        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    }
                }
            }

            $is_video = false;

            if ( $mime && strpos($mime, 'video/') === 0 ) {
                $is_video = true;
            } elseif ( in_array($ext, ['mp4', 'webm', 'ogg', 'ogv', 'mov', 'm4v'], true) ) {
                $is_video = true;
            }

            return [
                'url'      => $url ? esc_url($url) : '',
                'mime'     => $mime ? esc_attr($mime) : '',
                'ext'      => $ext,
                'is_video' => $is_video,
            ];
        }
    }

    /**
     * Helper para convertir URL normal de Vimeo a embed de fondo
     */
    if ( ! function_exists('nl_tax_gallery_vimeo_embed_url') ) {
        function nl_tax_gallery_vimeo_embed_url( $url ) {
            if ( ! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL) ) {
                return '';
            }

            $host = parse_url($url, PHP_URL_HOST);
            $path = parse_url($url, PHP_URL_PATH);

            if ( ! $host || ! $path ) {
                return '';
            }

            $host = strtolower($host);

            if ( strpos($host, 'vimeo.com') === false ) {
                return '';
            }

            // Soporta:
            // https://vimeo.com/1190486686
            // https://player.vimeo.com/video/1190486686
            if ( preg_match('~/(?:video/)?([0-9]+)~', $path, $matches) ) {
                $video_id = $matches[1];

                return esc_url(
                    'https://player.vimeo.com/video/' . $video_id . '?background=1&autopause=0'
                );
            }

            return '';
        }
    }

    /**
     * Helper para renderizar el media.
     * Se usa dos veces:
     * - una copia detrás del blur
     * - una copia en el costado visible
     */
    if ( ! function_exists('nl_tax_gallery_render_media') ) {
        function nl_tax_gallery_render_media( $media_type, $media_url, $media_mime = '', $gallery_urls = [], $copy = 'visual' ) {
            ob_start();

            if ( $media_type === 'gallery' && ! empty($gallery_urls) ) : ?>
                <div class="tax_gallery_bg-gallery" data-gallery-interval="3000">
                    <?php foreach ( $gallery_urls as $index => $url ) : ?>
                        <img
                            class="tax_gallery_bg-slide<?php echo $index === 0 ? ' is-active' : ''; ?>"
                            src="<?php echo esc_url($url); ?>"
                            alt=""
                            <?php echo $index === 0 && $copy === 'visual' ? 'fetchpriority="high"' : 'loading="lazy"'; ?>
                            decoding="async">
                    <?php endforeach; ?>
                </div>

            <?php elseif ( $media_type === 'video' && $media_url ) : ?>
                <video
                    class="tax_gallery_bg-video"
                    autoplay
                    muted
                    loop
                    playsinline
                    preload="metadata">
                    <source src="<?php echo esc_url($media_url); ?>"<?php if ( $media_mime ) : ?> type="<?php echo esc_attr($media_mime); ?>"<?php endif; ?>>
                </video>

            <?php elseif ( $media_type === 'vimeo' && $media_url ) : ?>
                <iframe
                    class="tax_gallery_bg-video tax_gallery_bg-vimeo"
                    src="<?php echo esc_url($media_url); ?>"
                    frameborder="0"
                    allow="autoplay; fullscreen; picture-in-picture"
                    allowfullscreen
                    loading="lazy">
                </iframe>

            <?php elseif ( $media_type === 'image' && $media_url ) : ?>
                <img
                    class="tax_gallery_bg-image"
                    src="<?php echo esc_url($media_url); ?>"
                    alt=""
                    loading="lazy"
                    decoding="async">
            <?php endif;

            return ob_get_clean();
        }
    }

    $section_id = 'tax-gallery-' . ( $term ? $term->term_id : uniqid() );
    $classes    = 'tax_gallery_block';

    $sections = [];
    $has_any  = false;

    if ( $term ) {
        $term_key = $term->taxonomy . '_' . $term->term_id;

        for ( $i = 1; $i <= 2; $i++ ) {
            $bg_raw = get_field("background{$i}", $term);

            if ( ! $bg_raw ) {
                $bg_raw = get_field("background{$i}", $term_key);
            }

            /**
             * Solo para sección 2:
             * Primero mantiene compatibilidad con background2.
             * Si no hay archivo cargado en background2, usa url_video.
             */
            if ( $i === 2 && ! $bg_raw ) {
                $bg_raw = get_field('url_video', $term);

                if ( ! $bg_raw ) {
                    $bg_raw = get_field('url_video', $term_key);
                }
            }

            $t = (string) get_field("title{$i}", $term);

            if ( $t === '' ) {
                $t = (string) get_field("title{$i}", $term_key);
            }

            $d = (string) get_field("description{$i}", $term);

            if ( $d === '' ) {
                $d = (string) get_field("description{$i}", $term_key);
            }

            $t = trim($t);
            $d = trim($d);

            $media_type   = '';
            $media_url    = '';
            $media_mime   = '';
            $gallery_urls = [];

            if ( $i === 1 ) {
                // background1 ahora es una GALERÍA ACF
                $gallery_urls = nl_tax_gallery_gallery_urls($bg_raw, 'large');

                if ( empty($gallery_urls) ) {
                    $gallery_urls = nl_tax_gallery_gallery_urls($bg_raw, 'full');
                }

                if ( ! empty($gallery_urls) ) {
                    $media_type = 'gallery';
                }
            } else {
                // background2 sigue siendo file/video.
                // Si background2 está vacío, puede venir url_video.
                $media = nl_tax_gallery_media_data($bg_raw);

                if ( ! empty($media['url']) && ! empty($media['is_video']) ) {
                    $media_type = 'video';
                    $media_url  = $media['url'];
                    $media_mime = $media['mime'];
                } else {
                    $vimeo_embed = nl_tax_gallery_vimeo_embed_url($bg_raw);

                    if ( $vimeo_embed ) {
                        $media_type = 'vimeo';
                        $media_url  = $vimeo_embed;
                    } else {
                        $img_url = nl_tax_gallery_img_url($bg_raw, 'large') ?: nl_tax_gallery_img_url($bg_raw, 'full');

                        if ( $img_url ) {
                            $media_type = 'image';
                            $media_url  = $img_url;
                        }
                    }
                }
            }

            $sections[$i] = [
                'media_type'   => $media_type,
                'media_url'    => $media_url,
                'media_mime'   => $media_mime,
                'gallery_urls' => $gallery_urls,
                't'            => $t,
                'd'            => $d,
            ];

            if ( $media_url !== '' || ! empty($gallery_urls) || $t !== '' || $d !== '' ) {
                $has_any = true;
            }
        }
    }

    if ( $has_any ) : ?>
        <section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($classes); ?>">
            <?php for ( $i = 1; $i <= 2; $i++ ) :
                $media_type   = $sections[$i]['media_type'] ?? '';
                $media_url    = $sections[$i]['media_url'] ?? '';
                $media_mime   = $sections[$i]['media_mime'] ?? '';
                $gallery_urls = $sections[$i]['gallery_urls'] ?? [];
                $t            = $sections[$i]['t'] ?? '';
                $d            = $sections[$i]['d'] ?? '';

                if ( $media_url === '' && empty($gallery_urls) && $t === '' && $d === '' ) {
                    continue;
                }
            ?>
                <div class="tax_gallery_section tax_gallery_section-<?php echo (int) $i; ?>">
                    <div class="tax_gallery_section-background" aria-hidden="true">

                        <div class="tax_gallery_background-part tax_gallery_background-part-blur" data-tax-gallery-copy="blur">
                            <?php
                            echo nl_tax_gallery_render_media(
                                $media_type,
                                $media_url,
                                $media_mime,
                                $gallery_urls,
                                'blur'
                            );
                            ?>
                        </div>

                        <div class="tax_gallery_background-part tax_gallery_background-part-visual" data-tax-gallery-copy="visual">
                            <?php
                            echo nl_tax_gallery_render_media(
                                $media_type,
                                $media_url,
                                $media_mime,
                                $gallery_urls,
                                'visual'
                            );
                            ?>
                        </div>

                    </div>

                    <div class="tax_gallery_content-wrapper">
                        <div class="tax_gallery_content-column">
                            <div class="tax_gallery_content-box">
                                <?php if ( $t ) : ?>
                                    <h2 class="font-overline text-white">
                                        <?php echo esc_html($t); ?>
                                    </h2>
                                <?php endif; ?>

                                <?php if ( $d ) : ?>
                                    <p class="font-body-medium-light text-white">
                                        <?php echo wp_kses_post(nl2br($d)); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>
        </section>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const blocks = document.querySelectorAll('.tax_gallery_block');

                blocks.forEach(function (block) {
                    if (block.dataset.taxGallerySynced === 'true') return;
                    block.dataset.taxGallerySynced = 'true';

                    const sections = block.querySelectorAll('.tax_gallery_section');

                    sections.forEach(function (section) {
                        const galleries = section.querySelectorAll('.tax_gallery_bg-gallery');

                        if (!galleries.length) return;

                        const firstGallery = galleries[0];
                        const firstSlides = firstGallery.querySelectorAll('.tax_gallery_bg-slide');

                        if (firstSlides.length <= 1) return;

                        const interval = parseInt(firstGallery.dataset.galleryInterval || '3000', 10);
                        let currentIndex = 0;

                        function setActiveSlide(index) {
                            galleries.forEach(function (gallery) {
                                const slides = gallery.querySelectorAll('.tax_gallery_bg-slide');

                                slides.forEach(function (slide, slideIndex) {
                                    slide.classList.toggle('is-active', slideIndex === index);
                                });
                            });
                        }

                        setInterval(function () {
                            currentIndex = (currentIndex + 1) % firstSlides.length;
                            setActiveSlide(currentIndex);
                        }, interval);
                    });
                });
            });
        </script>

        <script>
        (function(){
          var root = document.getElementById('<?php echo esc_js($section_id); ?>');
          if (!root) return;

          var galleries = root.querySelectorAll('.tax_gallery_bg-gallery[data-gallery-interval]');

          galleries.forEach(function(gallery){
            var slides   = gallery.querySelectorAll('.tax_gallery_bg-slide');
            var interval = parseInt(gallery.getAttribute('data-gallery-interval'), 10) || 3000;

            if (!slides.length) return;

            if (slides.length === 1) {
              slides[0].classList.add('is-active');
              return;
            }

            var current = 0;

            slides.forEach(function(slide, index){
              slide.classList.toggle('is-active', index === 0);
            });

            window.setInterval(function(){
              slides[current].classList.remove('is-active');
              current = (current + 1) % slides.length;
              slides[current].classList.add('is-active');
            }, interval);
          });
        })();
        </script>
    <?php
    endif;
}
?>


<?php
// =============================
// Productos de la colección paginados
// =============================
$coleccion_term = get_queried_object();

if ( $coleccion_term instanceof WP_Term ) {

  $sec_id = 'nl-coleccion-products-' . $coleccion_term->term_id;

  // Cantidad de productos por página
  $products_per_page = 12;

  // Página actual propia de este bloque
  $nlc_page = isset($_GET['nlc_page']) ? max(1, absint($_GET['nlc_page'])) : 1;

  // Categoría activa por URL
  $selected_cat = isset($_GET['nlc_cat']) ? sanitize_title(wp_unslash($_GET['nlc_cat'])) : 'all';

  // URL base de la colección
  $nlc_base_url = get_term_link($coleccion_term);
  if ( is_wp_error($nlc_base_url) ) {
    $nlc_base_url = home_url('/');
  }

  // =============================
  // Primero buscamos TODAS las categorías disponibles dentro de esta colección
  // Esto es solo para armar los filtros, no para mostrar productos
  // =============================
  $nlc_all_ids_query = new WP_Query([
    'post_type'              => 'product',
    'post_status'            => 'publish',
    'posts_per_page'         => -1,
    'fields'                 => 'ids',
    'no_found_rows'          => true,
    'ignore_sticky_posts'    => true,
    'tax_query'              => [
      [
        'taxonomy' => 'coleccion',
        'field'    => 'term_id',
        'terms'    => $coleccion_term->term_id,
      ],
    ],
  ]);

  $nlc_cats_map = [];
  $blacklist_slugs = [ 'uncategorized', 'sin-categoria' ];

  if ( ! empty($nlc_all_ids_query->posts) ) {
    foreach ( $nlc_all_ids_query->posts as $pid ) {
      $cats = get_the_terms($pid, 'product_cat');

      if ( $cats && ! is_wp_error($cats) ) {
        foreach ( $cats as $c ) {
          if ( in_array($c->slug, $blacklist_slugs, true) ) {
            continue;
          }

          $nlc_cats_map[$c->slug] = [
            'slug' => $c->slug,
            'name' => $c->name,
          ];
        }
      }
    }
  }

  wp_reset_postdata();

  if ( ! empty($nlc_cats_map) ) {
    uasort($nlc_cats_map, function($a, $b) {
      return strcasecmp($a['name'], $b['name']);
    });
  }

  // Si viene una categoría inválida por URL, volvemos a "all"
  if ( $selected_cat !== 'all' && ! isset($nlc_cats_map[$selected_cat]) ) {
    $selected_cat = 'all';
  }

  // Helper para URLs de filtro
  $nlc_filter_url = function($cat_slug) use ($nlc_base_url, $sec_id) {
    $args = [];

    if ( $cat_slug !== 'all' ) {
      $args['nlc_cat'] = $cat_slug;
    }

    return esc_url( add_query_arg($args, $nlc_base_url) . '#' . $sec_id );
  };

  // =============================
  // Query paginada real
  // =============================
  $tax_query = [
    [
      'taxonomy' => 'coleccion',
      'field'    => 'term_id',
      'terms'    => $coleccion_term->term_id,
    ],
  ];

  if ( $selected_cat !== 'all' ) {
    $tax_query[] = [
      'taxonomy' => 'product_cat',
      'field'    => 'slug',
      'terms'    => $selected_cat,
    ];
  }

  $nlc_args = [
    'post_type'           => 'product',
    'post_status'         => 'publish',
    'posts_per_page'      => $products_per_page,
    'paged'               => $nlc_page,
    'ignore_sticky_posts' => true,
    'tax_query'           => $tax_query,
    'orderby'             => 'date',
    'order'               => 'DESC',
  ];

  $nlc_query = new WP_Query($nlc_args);

  // Si no hay productos en la colección, no mostramos sección
  if ( $nlc_query->have_posts() || ! empty($nlc_cats_map) ) :
?>

<section id="<?php echo esc_attr($sec_id); ?>" class="nl-coleccion-products">

  <div class="coleccion-products__header">
    <h1 class="font-heading-2">
      <?php esc_html_e('Explora la colección', 'nalakalu'); ?>
    </h1>
  </div>

  <div class="nl-coleccion-products__inner">

    <div class="nl-coleccion-products__mobile-filter-bar">
      <button
        type="button"
        class="nl-coleccion-products__mobile-filter-toggle"
        aria-controls="nl-coleccion-products-filter-modal"
        aria-expanded="false"
      >
        <span><?php esc_html_e('FILTROS', 'nalakalu'); ?></span>
        <span class="nl-coleccion-products__mobile-filter-icon" aria-hidden="true">+</span>
      </button>
    </div>

    <aside class="nl-coleccion-products__header nl-coleccion-products__aside">
      <div class="nl-coleccion-products__filter-box">
        <h2 class="nl-coleccion-products__filter-title">
          <?php esc_html_e('FILTROS', 'nalakalu'); ?>
        </h2>

        <div
          class="nl-coleccion-products__tabs nl-coleccion-products__filter-content font-overline"
          role="tablist"
          aria-label="<?php esc_attr_e('Categorías de productos', 'nalakalu'); ?>"
        >
          <a
            href="<?php echo $nlc_filter_url('all'); ?>"
            class="nl-coleccion-products__tab nl-coleccion-products__filter-item <?php echo $selected_cat === 'all' ? 'is-active' : ''; ?>"
            role="tab"
            aria-selected="<?php echo $selected_cat === 'all' ? 'true' : 'false'; ?>"
          >
            <span class="nl-coleccion-products__filter-label">
              <?php esc_html_e('TODOS', 'nalakalu'); ?>
            </span>
          </a>

          <?php if ( ! empty($nlc_cats_map) ) : ?>
            <?php foreach ( $nlc_cats_map as $cat ) : ?>
              <a
                href="<?php echo $nlc_filter_url($cat['slug']); ?>"
                class="nl-coleccion-products__tab nl-coleccion-products__filter-item <?php echo $selected_cat === $cat['slug'] ? 'is-active' : ''; ?>"
                role="tab"
                aria-selected="<?php echo $selected_cat === $cat['slug'] ? 'true' : 'false'; ?>"
              >
                <span class="nl-coleccion-products__filter-label">
                  <?php echo esc_html(mb_strtoupper($cat['name'], 'UTF-8')); ?>
                </span>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </aside>

    <div
      id="nl-coleccion-products-filter-modal"
      class="nl-coleccion-products__filter-modal"
      aria-hidden="true"
    >
      <div class="nl-coleccion-products__filter-modal-overlay" data-nlc-filter-close></div>

      <div
        class="nl-coleccion-products__filter-modal-panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby="nl-coleccion-products-filter-modal-title"
      >
        <div class="nl-coleccion-products__filter-modal-head">
          <h2
            id="nl-coleccion-products-filter-modal-title"
            class="nl-coleccion-products__filter-modal-title"
          >
            <?php esc_html_e('FILTROS', 'nalakalu'); ?>
          </h2>

          <button
            type="button"
            class="nl-coleccion-products__filter-modal-close"
            aria-label="<?php esc_attr_e('Cerrar filtros', 'nalakalu'); ?>"
            data-nlc-filter-close
          >
            ×
          </button>
        </div>

        <div
          class="nl-coleccion-products__tabs nl-coleccion-products__filter-content nl-coleccion-products__filter-content--modal font-overline"
          role="tablist"
          aria-label="<?php esc_attr_e('Categorías de productos', 'nalakalu'); ?>"
        >
          <a
            href="<?php echo $nlc_filter_url('all'); ?>"
            class="nl-coleccion-products__tab nl-coleccion-products__filter-item <?php echo $selected_cat === 'all' ? 'is-active' : ''; ?>"
            role="tab"
            aria-selected="<?php echo $selected_cat === 'all' ? 'true' : 'false'; ?>"
          >
            <span class="nl-coleccion-products__filter-label">
              <?php esc_html_e('TODOS', 'nalakalu'); ?>
            </span>
          </a>

          <?php if ( ! empty($nlc_cats_map) ) : ?>
            <?php foreach ( $nlc_cats_map as $cat ) : ?>
              <a
                href="<?php echo $nlc_filter_url($cat['slug']); ?>"
                class="nl-coleccion-products__tab nl-coleccion-products__filter-item <?php echo $selected_cat === $cat['slug'] ? 'is-active' : ''; ?>"
                role="tab"
                aria-selected="<?php echo $selected_cat === $cat['slug'] ? 'true' : 'false'; ?>"
              >
                <span class="nl-coleccion-products__filter-label">
                  <?php echo esc_html(mb_strtoupper($cat['name'], 'UTF-8')); ?>
                </span>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="nl-coleccion-products__grid-wrapper">

      <?php if ( $nlc_query->have_posts() ) : ?>

        <div class="nl-coleccion-products__grid">

          <?php while ( $nlc_query->have_posts() ) : $nlc_query->the_post(); ?>
            <?php
              $pid   = get_the_ID();
              $plink = get_permalink($pid);
              $pname = get_the_title($pid);

              $thumb_id   = get_post_thumbnail_id($pid);
              $thumb_html = '';

              if ( $thumb_id ) {
                $thumb_html = wp_get_attachment_image($thumb_id, 'medium_large', false, [
                  'class'    => 'nl-coleccion-products__img',
                  'alt'      => $pname,
                  'loading'  => 'lazy',
                  'decoding' => 'async',
                  'sizes'    => '(max-width: 480px) 85vw, (max-width: 768px) 45vw, (max-width: 1200px) 33vw, 25vw',
                ]);
              }

              if ( ! $thumb_html && function_exists('wc_placeholder_img_src') ) {
                $thumb_html = '<img class="nl-coleccion-products__img" src="' . esc_url(wc_placeholder_img_src('medium_large')) . '" alt="' . esc_attr($pname) . '" loading="lazy" decoding="async">';
              }

              $price_html = '';

              if ( function_exists('wc_get_product') ) {
                $product_obj = wc_get_product($pid);

                if ( $product_obj ) {
                  $price_html = $product_obj->get_price_html();
                }
              }
            ?>

            <a
              href="<?php echo esc_url($plink); ?>"
              class="nl-coleccion-products__card"
            >
              <div class="nl-coleccion-products__image">
                <?php echo $thumb_html; ?>
              </div>

              <div class="nl-coleccion-products__info">
                <div class="nl-coleccion-products__details">
                  <div class="nl-coleccion-products__name">
                    <?php echo esc_html($pname); ?>
                  </div>
                </div>

                <?php if ( $price_html ) : ?>
                  <div class="nl-coleccion-products__price">
                    <?php echo wp_kses_post($price_html); ?>
                  </div>
                <?php endif; ?>
              </div>
            </a>

          <?php endwhile; ?>

        </div>

        <?php
          $pagination_base = add_query_arg('nlc_page', '%#%', $nlc_base_url);

          if ( $selected_cat !== 'all' ) {
            $pagination_base = add_query_arg('nlc_cat', $selected_cat, $pagination_base);
          }

          $pagination = paginate_links([
            'base'      => $pagination_base,
            'format'    => '',
            'current'   => $nlc_page,
            'total'     => max(1, (int) $nlc_query->max_num_pages),
            'mid_size'  => 1,
            'end_size'  => 1,
            'prev_text' => '‹',
            'next_text' => '›',
            'type'      => 'list',
            'add_fragment' => '#' . $sec_id,
          ]);
        ?>

        <?php if ( $pagination ) : ?>
          <nav class="nl-coleccion-products__pagination" aria-label="<?php esc_attr_e('Paginación de productos', 'nalakalu'); ?>">
            <?php echo $pagination; ?>
          </nav>
        <?php endif; ?>

      <?php else : ?>

        <p class="nl-coleccion-products__empty">
          <?php esc_html_e('No hay productos para este filtro.', 'nalakalu'); ?>
        </p>

      <?php endif; ?>

      <?php wp_reset_postdata(); ?>

    </div>
  </div>

  <script>
  (function () {
    var modal = document.getElementById('nl-coleccion-products-filter-modal');
    var openBtn = document.querySelector('.nl-coleccion-products__mobile-filter-toggle');

    if (!modal || !openBtn) return;

    var closeEls = modal.querySelectorAll('[data-nlc-filter-close]');

    function openModal() {
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      openBtn.setAttribute('aria-expanded', 'true');
      document.documentElement.classList.add('nl-coleccion-products-modal-open');
      document.body.classList.add('nl-coleccion-products-modal-open');
    }

    function closeModal() {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      openBtn.setAttribute('aria-expanded', 'false');
      document.documentElement.classList.remove('nl-coleccion-products-modal-open');
      document.body.classList.remove('nl-coleccion-products-modal-open');
    }

    openBtn.addEventListener('click', function () {
      if (modal.classList.contains('is-open')) {
        closeModal();
      } else {
        openModal();
      }
    });

    closeEls.forEach(function (el) {
      el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal.classList.contains('is-open')) {
        closeModal();
      }
    });
  })();
  </script>

</section>

<?php
  endif;
}
?>


<?php
// =============================
// Sección: Lanzamiento (carousel)
// Campos ACF en la taxonomía "coleccion":
//  - Grupo: lanzamiento
//      - title_lanzamiento (text)
//      - descripcionlanzamiento (textarea)
//      - imagen1_lanzamiento ... imagen6_lanzamiento (image)
// =============================

if ( function_exists('get_field') ) {

    // Reutilizamos $term si ya existe, o lo obtenemos de nuevo
    $lanz_term = ( isset($term) && $term instanceof WP_Term )
        ? $term
        : get_queried_object();

    if ( $lanz_term instanceof WP_Term ) {

        $term_key = $lanz_term->taxonomy . '_' . $lanz_term->term_id;

        // Leemos el GROUP "lanzamiento" desde el término
        $lanz = get_field('lanzamiento', $lanz_term);
        if ( ! $lanz ) {
            $lanz = get_field('lanzamiento', $term_key);
        }

        if ( is_array($lanz) ) {

            $title = isset($lanz['title_lanzamiento'])
                ? trim((string) $lanz['title_lanzamiento'])
                : '';

            $desc  = isset($lanz['descripcionlanzamiento'])
                ? trim((string) $lanz['descripcionlanzamiento'])
                : '';

            // Helper para imágenes
            if ( ! function_exists('tax_lanz_img_url') ) {
                function tax_lanz_img_url( $img, $size = 'large' ) {
                    if ( is_array($img) ) {
                        if ( ! empty($img['sizes'][$size]) ) return esc_url($img['sizes'][$size]);
                        if ( ! empty($img['url']) )         return esc_url($img['url']);
                    } elseif ( is_numeric($img) ) {
                        $src = wp_get_attachment_image_src((int) $img, $size);
                        if ( $src && ! empty($src[0]) )      return esc_url($src[0]);
                    } elseif ( is_string($img) && filter_var($img, FILTER_VALIDATE_URL) ) {
                        return esc_url($img);
                    }
                    return '';
                }
            }

            // Imágenes del carrusel (1..6)
            $images = [];
            for ($i = 1; $i <= 6; $i++) {
                $key = "imagen{$i}_lanzamiento";
                if ( ! empty($lanz[$key]) ) {
                    $url = tax_lanz_img_url($lanz[$key], 'full') ?: tax_lanz_img_url($lanz[$key], 'full');
                    if ( $url ) {
                        $images[] = $url;
                    }
                }
            }

            // Si no hay nada en ningún campo, no mostramos la sección
            if ( $title !== '' || $desc !== '' || ! empty($images) ) :

                $sec_id       = 'tax-lanzamiento-' . $lanz_term->term_id;
                $carousel_id  = $sec_id . '-carousel';
                $prev_id      = $sec_id . '-prev';
                $next_id      = $sec_id . '-next';
                ?>
                
                <section id="<?php echo esc_attr($sec_id); ?>" class="tax_lanz_block">
                    <div class="tax_lanz_container">
                        <div class="tax_lanz_header">
                            <?php if ( $title ) : ?>
                                <div class="tax_lanz_title-section">
                                    <h1 class="tax_lanz_title font-heading-1">
                                        <?php echo esc_html( $title ); ?>
                                    </h1>
                                </div>
                            <?php endif; ?>

                            <?php if ( $desc || count($images) > 1 ) : ?>
                                <div class="tax_lanz_description-section">
                                    <?php if ( $desc ) : ?>
                                        <p class="tax_lanz_description font-body-small">
                                            <?php echo wp_kses_post( nl2br( $desc ) ); ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if ( count($images) > 1 ) : ?>
                                        <div class="tax_lanz_nav-buttons">
                                            <button
                                                class="tax_lanz_nav-btn"
                                                id="<?php echo esc_attr($prev_id); ?>"
                                                type="button"
                                                aria-label="<?php esc_attr_e('Anterior', 'nalakalu'); ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none">
  <path d="M6.96785 13.8533C7.01866 13.9011 7.0786 13.9387 7.14425 13.9638C7.2099 13.989 7.27997 14.0012 7.35047 13.9999C7.49284 13.9972 7.62829 13.9393 7.72701 13.839C7.82574 13.7386 7.87966 13.604 7.8769 13.4648C7.87415 13.3256 7.81496 13.1931 7.71234 13.0966L1.78861 7.52432L15.2131 7.52432C15.3555 7.52432 15.4921 7.46901 15.5927 7.37055C15.6934 7.27209 15.75 7.13854 15.75 6.9993C15.75 6.86005 15.6934 6.72651 15.5927 6.62805C15.4921 6.52959 15.3555 6.47427 15.2131 6.47427L1.79004 6.47427L7.71305 0.90341C7.76387 0.855607 7.80455 0.798484 7.83279 0.735305C7.86102 0.672125 7.87626 0.604126 7.87762 0.535189C7.87898 0.466252 7.86645 0.397728 7.84073 0.333528C7.81501 0.26933 7.77661 0.210712 7.72773 0.161024C7.67885 0.111336 7.62043 0.0715501 7.55582 0.0439384C7.49122 0.0163257 7.42168 0.00142741 7.35118 9.5129e-05C7.28069 -0.00123715 7.21062 0.0110214 7.14497 0.0361717C7.07931 0.061321 7.01937 0.09887 6.96856 0.146673L0.219443 6.49527C0.15005 6.56059 0.0948477 6.63892 0.0571413 6.72559C0.019435 6.81225 0 6.90547 0 6.99965C0 7.09383 0.019435 7.18705 0.0571413 7.27371C0.0948477 7.36038 0.15005 7.43871 0.219443 7.50402L6.96785 13.8533Z" fill="#3D332B"/>
</svg>
                                            </button>
                                            <button
                                                class="tax_lanz_nav-btn"
                                                id="<?php echo esc_attr($next_id); ?>"
                                                type="button"
                                                aria-label="<?php esc_attr_e('Siguiente', 'nalakalu'); ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none">
  <path d="M8.78215 13.8533C8.73134 13.9011 8.6714 13.9387 8.60575 13.9638C8.5401 13.989 8.47003 14.0012 8.39953 13.9999C8.25716 13.9972 8.12171 13.9393 8.02299 13.839C7.92426 13.7386 7.87034 13.604 7.8731 13.4648C7.87585 13.3256 7.93504 13.1931 8.03766 13.0966L13.9614 7.52432L0.536892 7.52432C0.394499 7.52432 0.257937 7.46901 0.15725 7.37055C0.0565633 7.27209 -1.93715e-06 7.13854 -1.93715e-06 6.9993C-1.93715e-06 6.86005 0.0565633 6.72651 0.15725 6.62805C0.257937 6.52959 0.394499 6.47427 0.536892 6.47427L13.96 6.47427L8.03695 0.90341C7.98613 0.855607 7.94545 0.798484 7.91721 0.735305C7.88898 0.672125 7.87374 0.604126 7.87238 0.535189C7.87102 0.466252 7.88355 0.397728 7.90927 0.333528C7.93499 0.26933 7.97339 0.210712 8.02227 0.161024C8.07115 0.111336 8.12957 0.0715501 8.19418 0.0439384C8.25878 0.0163257 8.32832 0.00142741 8.39882 9.5129e-05C8.46931 -0.00123715 8.53938 0.0110214 8.60503 0.0361717C8.67069 0.061321 8.73063 0.09887 8.78144 0.146673L15.5306 6.49527C15.6 6.56059 15.6552 6.63892 15.6929 6.72559C15.7306 6.81225 15.75 6.90547 15.75 6.99965C15.75 7.09383 15.7306 7.18705 15.6929 7.27371C15.6552 7.36038 15.6 7.43871 15.5306 7.50402L8.78215 13.8533Z" fill="#3D332B"/>
</svg>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ( ! empty($images) ) : ?>
                            <div class="tax_lanz_carousel-wrapper">
                                <div class="tax_lanz_carousel-container" id="<?php echo esc_attr($carousel_id); ?>">
                                    <?php foreach ( $images as $url ) : ?>
                                        <div class="tax_lanz_carousel-item">
                                            <img src="<?php echo esc_url($url); ?>" alt="" loading="lazy" decoding="async">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ( count($images) > 1 ) : ?>
                        <script>
                        (function() {
                            var carousel = document.getElementById('<?php echo esc_js($carousel_id); ?>');
                            var prevBtn  = document.getElementById('<?php echo esc_js($prev_id); ?>');
                            var nextBtn  = document.getElementById('<?php echo esc_js($next_id); ?>');

                            if (!carousel || !prevBtn || !nextBtn) return;

                            var items = carousel.querySelectorAll('.tax_lanz_carousel-item');
                            if (!items.length) return;

                            var currentIndex = 0;
                            var totalItems   = items.length;
                            var maxIndex     = Math.max(0, totalItems - 2); // mostramos ~2 ítems

                            function updateCarousel() {
                                if (!items[0]) return;
                                var itemWidth = items[0].offsetWidth;
                                var gap       = 30;
                                var offset    = currentIndex * (itemWidth + gap);
                                carousel.style.transform = 'translateX(-' + offset + 'px)';
                            }

                            prevBtn.addEventListener('click', function() {
                                if (currentIndex > 0) {
                                    currentIndex--;
                                    updateCarousel();
                                }
                            });

                            nextBtn.addEventListener('click', function() {
                                if (currentIndex < maxIndex) {
                                    currentIndex++;
                                    updateCarousel();
                                }
                            });

                            window.addEventListener('resize', updateCarousel);
                            updateCarousel();
                        })();
                        </script>
                    <?php endif; ?>
                </section>

                <?php
            endif; // hay contenido
        } // is_array( $lanz )
    } // $lanz_term instanceof WP_Term
} // function_exists('get_field')
?>

<?php // Script mobile del carousel de lanzamiento: fuera del bloque de productos para no depender del orden. ?>
<script>
      (function () {
        var mq = window.matchMedia("(max-width: 768px)");

        function safeAddMqListener(mql, fn){
          if (mql.addEventListener) mql.addEventListener("change", fn);
          else if (mql.addListener) mql.addListener(fn);
        }

        function initMobileCarousel(block){
          if (!block || block.__taxMobileInit) return;

          var wrapper = block.querySelector(".tax_lanz_carousel-wrapper");
          var track   = block.querySelector(".tax_lanz_carousel-container");
          var items   = track ? track.querySelectorAll(".tax_lanz_carousel-item") : null;
          var nav     = block.querySelector(".tax_lanz_nav-buttons");

          if (!wrapper || !track || !items || !items.length || !nav) return;

          block.__taxMobileInit = true;

          // Guardar ubicación original del nav para restaurar en desktop
          if (!nav.__taxStored) {
            nav.__taxStored = true;
            nav.__taxOrigParent = nav.parentNode;
            nav.__taxOrigNext = nav.nextSibling;
          }

          function placeNavMobile(){
            // pone las flechas debajo del carrusel
            if (nav.parentNode !== wrapper.parentNode || nav.previousElementSibling !== wrapper) {
              wrapper.insertAdjacentElement("afterend", nav);
            }
          }

          function restoreNavDesktop(){
            var p = nav.__taxOrigParent;
            if (!p) return;

            // si sigue existiendo el nextSibling original, lo insertamos antes
            if (nav.__taxOrigNext && nav.__taxOrigNext.parentNode === p) {
              p.insertBefore(nav, nav.__taxOrigNext);
            } else {
              p.appendChild(nav);
            }

            // sacamos el transform inline (no pisamos lógica desktop)
            track.style.transform = "";
          }

          var current = 0;

          function getSlideW(){
            return wrapper.getBoundingClientRect().width || wrapper.clientWidth || 0;
          }

          function clamp(n, min, max){
            return Math.max(min, Math.min(max, n));
          }

          function goTo(i){
            i = clamp(i, 0, items.length - 1);
            current = i;

            var w = getSlideW();
            track.style.transform = "translate3d(" + (-current * w) + "px, 0, 0)";

            updateBtns();
          }

          function updateBtns(){
            var btns = nav.querySelectorAll(".tax_lanz_nav-btn");
            if (btns.length >= 1) btns[0].disabled = (current === 0);
            if (btns.length >= 2) btns[1].disabled = (current === items.length - 1);
          }

          function bindBtns(){
            var btns = nav.querySelectorAll(".tax_lanz_nav-btn");
            if (!btns.length) return;

            // Detecta por data-dir="prev|next" si existe, si no asume 1ro prev / 2do next
            var prev = null, next = null;

            for (var k = 0; k < btns.length; k++){
              var d = (btns[k].getAttribute("data-dir") || "").toLowerCase();
              if (d === "prev") prev = btns[k];
              if (d === "next") next = btns[k];
            }
            if (!prev && btns.length >= 1) prev = btns[0];
            if (!next && btns.length >= 2) next = btns[1];

            if (prev && !prev.__taxBound){
              prev.__taxBound = true;
              prev.addEventListener("click", function(e){
                e.preventDefault();
                goTo(current - 1);
              });
            }

            if (next && !next.__taxBound){
              next.__taxBound = true;
              next.addEventListener("click", function(e){
                e.preventDefault();
                goTo(current + 1);
              });
            }
          }

          function bindSwipe(){
            var startX = 0, startY = 0, active = false;

            wrapper.addEventListener("touchstart", function(e){
              if (!mq.matches) return;
              if (!e.touches || e.touches.length !== 1) return;
              active = true;
              startX = e.touches[0].clientX;
              startY = e.touches[0].clientY;
            }, {passive:true});

            wrapper.addEventListener("touchend", function(e){
              if (!mq.matches) return;
              if (!active) return;
              active = false;

              var t = (e.changedTouches && e.changedTouches[0]) ? e.changedTouches[0] : null;
              if (!t) return;

              var dx = t.clientX - startX;
              var dy = t.clientY - startY;

              // evita interferir con scroll vertical
              if (Math.abs(dx) < 35) return;
              if (Math.abs(dx) < Math.abs(dy)) return;

              if (dx < 0) goTo(current + 1);
              else goTo(current - 1);
            }, {passive:true});
          }

          function onMode(){
            if (mq.matches){
              placeNavMobile();
              bindBtns();
              goTo(current);
            } else {
              restoreNavDesktop();
            }
          }

          window.addEventListener("resize", function(){
            if (!mq.matches) return;
            goTo(current);
          });

          bindSwipe();
          safeAddMqListener(mq, onMode);
          onMode();
        }

        function boot(){
          var blocks = document.querySelectorAll(".tax_lanz_block");
          for (var i = 0; i < blocks.length; i++){
            initMobileCarousel(blocks[i]);
          }
        }

        if (document.readyState === "loading") {
          document.addEventListener("DOMContentLoaded", boot);
        } else {
          boot();
        }
      })();
      </script>

</main>

<?php
get_footer();