<?php
/**
 * Block: Blog Posts (custom layout)
 *
 * - Muestra:
 *   - 1 destacado (post más reciente)
 *   - 6 en grilla
 *   - 3 en lista con paginación (solo cambia esta parte)
 *
 * - Filtros:
 *   - Tabs de categorías (todas las categorías de posts existentes)
 *   - "TODOS" muestra todas
 *   - Filtro por texto con ?nl_s=
 */

if ( ! defined('ABSPATH') ) {
    exit;
}

// ID único para este bloque (para el JS tipo AJAX)
$section_id = 'nl_blog_' . ( isset( $block['id'] ) ? $block['id'] : uniqid() );

// ===========================
// Categorías del menú superior
// ===========================

// Siempre agregamos "todos"
$nl_blog_categories = array(
    'todos' => __('TODOS', 'your-textdomain'),
);

// Traemos todas las categorías de posts (solo las que tienen posts)
$terms = get_terms(
    array(
        'taxonomy'   => 'category',
        'hide_empty' => true,
    )
);

if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
    foreach ( $terms as $term ) {
        $nl_blog_categories[ $term->slug ] = $term->name;
    }
}

// Categoría actual (GET ?nl_cat=)
$current_cat = isset($_GET['nl_cat']) ? sanitize_title( wp_unslash($_GET['nl_cat']) ) : 'todos';
if ( ! array_key_exists($current_cat, $nl_blog_categories) ) {
    $current_cat = 'todos';
}

// Búsqueda actual (GET ?nl_s=)
$search_term = isset($_GET['nl_s']) ? sanitize_text_field( wp_unslash($_GET['nl_s']) ) : '';

// Paginación solo para la lista inferior (3 posts por página)
$paged = get_query_var('paged') ? absint(get_query_var('paged')) : 1;
if ($paged < 1) {
    $paged = 1;
}

$list_per_page   = 3; // 3 posts en la parte de lista
$initial_offset  = 7; // 1 destacado + 6 grilla = 7 posts "fijos"

// Base de argumentos para todas las consultas
$base_query_args = array(
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'ignore_sticky_posts' => true,
);

if ( $current_cat !== 'todos' ) {
    // Usa slug de categoría
    $base_query_args['category_name'] = $current_cat;
}

if ( $search_term ) {
    $base_query_args['s'] = $search_term;
}

/**
 * 1) Query para destacados + grilla (primeros 7 posts)
 */
$top_args = $base_query_args;
$top_args['posts_per_page'] = $initial_offset;

$featured_post_id = 0;
$grid_post_ids    = array();

$top_query = new WP_Query($top_args);

if ( $top_query->have_posts() ) {
    // Primer post = destacado
    $top_query->the_post();
    $featured_post_id = get_the_ID();

    // Resto de resultados = grilla
    while ( $top_query->have_posts() ) {
        $top_query->the_post();
        $grid_post_ids[] = get_the_ID();
    }
}
wp_reset_postdata();

/**
 * 2) Query para la lista inferior (paginada)
 *    Solo posts a partir del offset inicial (7)
 */
$list_args = $base_query_args;
$list_args['posts_per_page'] = $list_per_page;
$list_args['offset']         = $initial_offset + ( $paged - 1 ) * $list_per_page;
$list_args['no_found_rows']  = false; // Necesitamos found_posts para el total

$list_query = new WP_Query($list_args);

// Cálculo del total de páginas SOLO para el bloque inferior
$total_found    = (int) $list_query->found_posts; // total de posts que matchean la query (sin considerar offset)
$total_for_list = max(0, $total_found - $initial_offset);
$total_pages    = $total_for_list > 0 ? (int) ceil($total_for_list / $list_per_page) : 1;

if ( $paged > $total_pages ) {
    $paged = $total_pages;
}

?>
<section
  id="<?php echo esc_attr( $section_id ); ?>"
  class="nl_blog_block"
  data-nl-blog-block-id="<?php echo esc_attr( $section_id ); ?>"
>
  <div class="nl_blog_container">

    <!-- Header: categorías + buscador -->
    <header class="nl_blog_header">
      <nav class="nl_blog_nav" aria-label="<?php esc_attr_e('Categorías', 'your-textdomain'); ?>">
        <?php
        foreach ( $nl_blog_categories as $slug => $label ) {

            // Armamos la URL manteniendo búsqueda, etc.
            $link_args = array();

            if ( 'todos' !== $slug ) {
                $link_args['nl_cat'] = $slug;
            }

            if ( $search_term ) {
                $link_args['nl_s'] = $search_term;
            }

            // Base: permalink de la página donde está el bloque
            $url = ! empty($link_args)
                ? add_query_arg($link_args, get_permalink())
                : get_permalink();

            $is_active = ($slug === $current_cat);
            ?>
            <a
              href="<?php echo esc_url($url); ?>"
              class="nl_blog_nav-item<?php echo $is_active ? ' is-active' : ''; ?>"
            >
              <?php echo esc_html($label); ?>
            </a>
            <?php
        }
        ?>
      </nav>

      <form
        class="nl_blog_search-box"
        role="search"
        method="get"
        action="<?php echo esc_url(get_permalink()); ?>"
      >
        <input
          type="text"
          name="nl_s"
          value="<?php echo esc_attr($search_term); ?>"
          placeholder="<?php esc_attr_e('Buscar artículo', 'your-textdomain'); ?>"
        />
        <?php if ( $current_cat !== 'todos' ) : ?>
          <input type="hidden" name="nl_cat" value="<?php echo esc_attr($current_cat); ?>" />
        <?php endif; ?>
        <button
          type="submit"
          class="nl_blog_search-submit"
          aria-label="<?php esc_attr_e('Buscar', 'your-textdomain'); ?>"
        >
          🔍
        </button>
      </form>
    </header>

    <?php
    /**
     * Destacado (primer post)
     */
    if ( $featured_post_id ) :
        $post = get_post($featured_post_id);
        setup_postdata($post);

        $categories   = get_the_category($featured_post_id);
        $primary_cat  = ! empty($categories) ? $categories[0] : null;
        $cat_name     = $primary_cat ? $primary_cat->name : __('Sin categoría', 'your-textdomain');
        $cat_slug     = $primary_cat ? $primary_cat->slug : 'sin-categoria';
        ?>
        <article class="nl_blog_featured" data-category-slug="<?php echo esc_attr($cat_slug); ?>">
          <div class="nl_blog_featured-media">
            <?php if ( has_post_thumbnail($featured_post_id) ) : ?>
              <?php echo get_the_post_thumbnail($featured_post_id, 'large', array('class' => 'nl_blog_featured-image')); ?>
            <?php endif; ?>
          </div>

          <div class="nl_blog_featured-content">
            <div class="nl_blog_meta">
              <span class="nl_blog_category"><?php echo esc_html($cat_name); ?></span>
              <span class="font-caption-small"><?php echo esc_html( get_the_date('F d, Y', $featured_post_id) ); ?></span>
            </div>

            <h2 class="nl_blog_featured-title font-heading-2">
              <a href="<?php echo esc_url( get_permalink($featured_post_id) ); ?>">
                <?php echo esc_html( get_the_title($featured_post_id) ); ?>
              </a>
            </h2>

            <?php if ( has_excerpt($featured_post_id) ) : ?>
              <p class="nl_blog_featured-text">
                <?php echo esc_html( get_the_excerpt($featured_post_id) ); ?>
              </p>
            <?php endif; ?>

            <a href="<?php echo esc_url( get_permalink($featured_post_id) ); ?>" class="nl_blog_read-more font-button">
              <?php esc_html_e('Leer más', 'your-textdomain'); ?> <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
  <mask id="mask0_2277_2163" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="20" height="20">
    <rect width="20" height="20" fill="#D9D9D9"/>
  </mask>
  <g mask="url(#mask0_2277_2163)">
    <path d="M13.4779 10.832H3.33203V9.16536H13.4779L8.8112 4.4987L9.9987 3.33203L16.6654 9.9987L9.9987 16.6654L8.8112 15.4987L13.4779 10.832Z" fill="#3D332B"/>
  </g>
</svg>
            </a>
          </div>
        </article>
        <?php
        wp_reset_postdata();
    endif;
    ?>

    <?php
    /**
     * Grilla (los siguientes 6 posts)
     */
    if ( ! empty($grid_post_ids) ) : ?>
      <div class="nl_blog_articles-grid">
        <?php
        foreach ( $grid_post_ids as $grid_post_id ) :
            $post = get_post($grid_post_id);
            if ( ! $post ) {
                continue;
            }

            setup_postdata($post);

            $categories   = get_the_category($grid_post_id);
            $primary_cat  = ! empty($categories) ? $categories[0] : null;
            $cat_name     = $primary_cat ? $primary_cat->name : __('Sin categoría', 'your-textdomain');
            $cat_slug     = $primary_cat ? $primary_cat->slug : 'sin-categoria';
            ?>
            <article class="nl_blog_article-card" data-category-slug="<?php echo esc_attr($cat_slug); ?>">
              <div class="nl_blog_article-media">
                <?php if ( has_post_thumbnail($grid_post_id) ) : ?>
                  <?php echo get_the_post_thumbnail($grid_post_id, 'medium_large', array('class' => 'nl_blog_article-image')); ?>
                <?php endif; ?>
              </div>

              <div class="nl_blog_article-content">
                <div class="nl_blog_meta">
                  <span class="nl_blog_category"><?php echo esc_html($cat_name); ?></span>
                  <span class="nl_blog_date font-caption-small"><?php echo esc_html( get_the_date('F d, Y', $grid_post_id) ); ?></span>
                </div>

                <h3 class="nl_blog_article-title font-heading-4">
                  <a href="<?php echo esc_url( get_permalink($grid_post_id) ); ?>">
                    <?php echo esc_html( get_the_title($grid_post_id) ); ?>
                  </a>
                </h3>

                <p class="nl_blog_article-text font-body-medium-light">
                  <?php echo esc_html( get_the_excerpt($grid_post_id) ); ?>
                </p>

                <a href="<?php echo esc_url( get_permalink($grid_post_id) ); ?>" class="nl_blog_read-more font-button">
                  <?php esc_html_e('Leer más', 'your-textdomain'); ?> <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
  <mask id="mask0_2277_2180" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="20" height="20">
    <rect width="20" height="20" fill="#D9D9D9"/>
  </mask>
  <g mask="url(#mask0_2277_2180)">
    <path d="M13.4779 10.832H3.33203V9.16536H13.4779L8.8112 4.4987L9.9987 3.33203L16.6654 9.9987L9.9987 16.6654L8.8112 15.4987L13.4779 10.832Z" fill="#3D332B"/>
  </g>
</svg>
                </a>
              </div>
            </article>
            <?php
        endforeach;
        wp_reset_postdata();
        ?>
      </div>
    <?php endif; ?>

    <?php
    /**
     * Lista inferior (3 posts paginados)
     */
    if ( $list_query->have_posts() ) : ?>
      <div class="nl_blog_articles-list">
        <?php
        while ( $list_query->have_posts() ) :
            $list_query->the_post();

            $categories   = get_the_category( get_the_ID() );
            $primary_cat  = ! empty($categories) ? $categories[0] : null;
            $cat_name     = $primary_cat ? $primary_cat->name : __('Sin categoría', 'your-textdomain');
            $cat_slug     = $primary_cat ? $primary_cat->slug : 'sin-categoria';
            ?>
            <article class="nl_blog_article-list-item" data-category-slug="<?php echo esc_attr($cat_slug); ?>">
              <div class="nl_blog_list-media">
                <?php if ( has_post_thumbnail() ) : ?>
                  <?php the_post_thumbnail('large', array('class' => 'nl_blog_list-image')); ?>
                <?php endif; ?>
              </div>

              <div class="nl_blog_list-content">
                <div class="nl_blog_meta">
                  <span class="nl_blog_category"><?php echo esc_html($cat_name); ?></span>
                  <span class="nl_blog_date font-caption-small"><?php echo esc_html( get_the_date('F d, Y') ); ?></span>
                </div>

                <h3 class="nl_blog_list-title font-heading-4">
                  <a href="<?php the_permalink(); ?>">
                    <?php the_title(); ?>
                  </a>
                </h3>

                <p class="nl_blog_article-text font-body-medium-light">
                  <?php echo esc_html( get_the_excerpt() ); ?>
                </p>

                <a href="<?php the_permalink(); ?>" class="nl_blog_read-more font-button">
                  <?php esc_html_e('Leer más', 'your-textdomain'); ?> <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
  <mask id="mask0_2277_811" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="20" height="20">
    <rect width="20" height="20" fill="#D9D9D9"/>
  </mask>
  <g mask="url(#mask0_2277_811)">
    <path d="M13.4779 10.832H3.33203V9.16536H13.4779L8.8112 4.4987L9.9987 3.33203L16.6654 9.9987L9.9987 16.6654L8.8112 15.4987L13.4779 10.832Z" fill="#3D332B"/>
  </g>
</svg>
                </a>
              </div>
            </article>
        <?php endwhile; ?>
      </div>
      <?php wp_reset_postdata(); ?>
    <?php endif; ?>

    <?php
    /**
     * Paginación (solo afecta la lista inferior de 3 posts)
     */
    if ( $total_pages > 1 ) :

        $pagination_query_args = array();

        if ( $current_cat !== 'todos' ) {
            $pagination_query_args['nl_cat'] = $current_cat;
        }

        if ( $search_term ) {
            $pagination_query_args['nl_s'] = $search_term;
        }
        ?>
        <div class="nl_blog_pagination" aria-label="<?php esc_attr_e('Paginación de artículos', 'your-textdomain'); ?>">
          <div class="nl_blog_pagination-inner">
            <?php if ( $paged > 1 ) : ?>
              <a
                class="nl_blog_page-btn"
                href="<?php echo esc_url( add_query_arg( $pagination_query_args, get_pagenum_link($paged - 1) ) ); ?>"
                aria-label="<?php esc_attr_e('Página anterior', 'your-textdomain'); ?>"
              >
               <svg xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none">
  <path d="M6.96785 13.8533C7.01866 13.9011 7.0786 13.9387 7.14425 13.9638C7.2099 13.989 7.27997 14.0012 7.35047 13.9999C7.49284 13.9972 7.62829 13.9393 7.72701 13.839C7.82574 13.7386 7.87966 13.604 7.8769 13.4648C7.87415 13.3256 7.81496 13.1931 7.71234 13.0966L1.78861 7.52432L15.2131 7.52432C15.3555 7.52432 15.4921 7.46901 15.5927 7.37055C15.6934 7.27209 15.75 7.13854 15.75 6.9993C15.75 6.86005 15.6934 6.72651 15.5927 6.62805C15.4921 6.52959 15.3555 6.47427 15.2131 6.47427L1.79004 6.47427L7.71305 0.90341C7.76387 0.855607 7.80455 0.798484 7.83279 0.735305C7.86102 0.672125 7.87626 0.604126 7.87762 0.535189C7.87898 0.466252 7.86645 0.397728 7.84073 0.333528C7.81501 0.26933 7.77661 0.210712 7.72773 0.161024C7.67885 0.111336 7.62043 0.0715501 7.55582 0.0439384C7.49122 0.0163257 7.42168 0.00142741 7.35118 9.5129e-05C7.28069 -0.00123715 7.21062 0.0110214 7.14497 0.0361717C7.07931 0.061321 7.01937 0.09887 6.96856 0.146673L0.219443 6.49527C0.15005 6.56059 0.0948477 6.63892 0.0571413 6.72559C0.019435 6.81225 0 6.90547 0 6.99965C0 7.09383 0.019435 7.18705 0.0571413 7.27371C0.0948477 7.36038 0.15005 7.43871 0.219443 7.50402L6.96785 13.8533Z" fill="#3D332B"/>
</svg>
              </a>
            <?php else : ?>
              <span class="nl_blog_page-btn is-disabled" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none">
  <path d="M6.96785 13.8533C7.01866 13.9011 7.0786 13.9387 7.14425 13.9638C7.2099 13.989 7.27997 14.0012 7.35047 13.9999C7.49284 13.9972 7.62829 13.9393 7.72701 13.839C7.82574 13.7386 7.87966 13.604 7.8769 13.4648C7.87415 13.3256 7.81496 13.1931 7.71234 13.0966L1.78861 7.52432L15.2131 7.52432C15.3555 7.52432 15.4921 7.46901 15.5927 7.37055C15.6934 7.27209 15.75 7.13854 15.75 6.9993C15.75 6.86005 15.6934 6.72651 15.5927 6.62805C15.4921 6.52959 15.3555 6.47427 15.2131 6.47427L1.79004 6.47427L7.71305 0.90341C7.76387 0.855607 7.80455 0.798484 7.83279 0.735305C7.86102 0.672125 7.87626 0.604126 7.87762 0.535189C7.87898 0.466252 7.86645 0.397728 7.84073 0.333528C7.81501 0.26933 7.77661 0.210712 7.72773 0.161024C7.67885 0.111336 7.62043 0.0715501 7.55582 0.0439384C7.49122 0.0163257 7.42168 0.00142741 7.35118 9.5129e-05C7.28069 -0.00123715 7.21062 0.0110214 7.14497 0.0361717C7.07931 0.061321 7.01937 0.09887 6.96856 0.146673L0.219443 6.49527C0.15005 6.56059 0.0948477 6.63892 0.0571413 6.72559C0.019435 6.81225 0 6.90547 0 6.99965C0 7.09383 0.019435 7.18705 0.0571413 7.27371C0.0948477 7.36038 0.15005 7.43871 0.219443 7.50402L6.96785 13.8533Z" fill="#3D332B"/>
</svg></span>
            <?php endif; ?>

            <span class="nl_blog_page-info font-body-small">
              <span class="nl_blog_current-page"><?php echo esc_html($paged); ?></span>
              <?php esc_html_e(' of ', 'your-textdomain'); ?>
              <span class="nl_blog_total-pages "><?php echo esc_html($total_pages); ?></span>
            </span>

            <?php if ( $paged < $total_pages ) : ?>
              <a
                class="nl_blog_page-btn"
                href="<?php echo esc_url( add_query_arg( $pagination_query_args, get_pagenum_link($paged + 1) ) ); ?>"
                aria-label="<?php esc_attr_e('Página siguiente', 'your-textdomain'); ?>"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none">
  <path d="M8.78215 13.8533C8.73134 13.9011 8.6714 13.9387 8.60575 13.9638C8.5401 13.989 8.47003 14.0012 8.39953 13.9999C8.25716 13.9972 8.12171 13.9393 8.02299 13.839C7.92426 13.7386 7.87034 13.604 7.8731 13.4648C7.87585 13.3256 7.93504 13.1931 8.03766 13.0966L13.9614 7.52432L0.536892 7.52432C0.394499 7.52432 0.257937 7.46901 0.15725 7.37055C0.0565633 7.27209 -1.93715e-06 7.13854 -1.93715e-06 6.9993C-1.93715e-06 6.86005 0.0565633 6.72651 0.15725 6.62805C0.257937 6.52959 0.394499 6.47427 0.536892 6.47427L13.96 6.47427L8.03695 0.90341C7.98613 0.855607 7.94545 0.798484 7.91721 0.735305C7.88898 0.672125 7.87374 0.604126 7.87238 0.535189C7.87102 0.466252 7.88355 0.397728 7.90927 0.333528C7.93499 0.26933 7.97339 0.210712 8.02227 0.161024C8.07115 0.111336 8.12957 0.0715501 8.19418 0.0439384C8.25878 0.0163257 8.32832 0.00142741 8.39882 9.5129e-05C8.46931 -0.00123715 8.53938 0.0110214 8.60503 0.0361717C8.67069 0.061321 8.73063 0.09887 8.78144 0.146673L15.5306 6.49527C15.6 6.56059 15.6552 6.63892 15.6929 6.72559C15.7306 6.81225 15.75 6.90547 15.75 6.99965C15.75 7.09383 15.7306 7.18705 15.6929 7.27371C15.6552 7.36038 15.6 7.43871 15.5306 7.50402L8.78215 13.8533Z" fill="#3D332B"/>
</svg>
              </a>
            <?php else : ?>
              <span class="nl_blog_page-btn is-disabled" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none">
  <path d="M8.78215 13.8533C8.73134 13.9011 8.6714 13.9387 8.60575 13.9638C8.5401 13.989 8.47003 14.0012 8.39953 13.9999C8.25716 13.9972 8.12171 13.9393 8.02299 13.839C7.92426 13.7386 7.87034 13.604 7.8731 13.4648C7.87585 13.3256 7.93504 13.1931 8.03766 13.0966L13.9614 7.52432L0.536892 7.52432C0.394499 7.52432 0.257937 7.46901 0.15725 7.37055C0.0565633 7.27209 -1.93715e-06 7.13854 -1.93715e-06 6.9993C-1.93715e-06 6.86005 0.0565633 6.72651 0.15725 6.62805C0.257937 6.52959 0.394499 6.47427 0.536892 6.47427L13.96 6.47427L8.03695 0.90341C7.98613 0.855607 7.94545 0.798484 7.91721 0.735305C7.88898 0.672125 7.87374 0.604126 7.87238 0.535189C7.87102 0.466252 7.88355 0.397728 7.90927 0.333528C7.93499 0.26933 7.97339 0.210712 8.02227 0.161024C8.07115 0.111336 8.12957 0.0715501 8.19418 0.0439384C8.25878 0.0163257 8.32832 0.00142741 8.39882 9.5129e-05C8.46931 -0.00123715 8.53938 0.0110214 8.60503 0.0361717C8.67069 0.061321 8.73063 0.09887 8.78144 0.146673L15.5306 6.49527C15.6 6.56059 15.6552 6.63892 15.6929 6.72559C15.7306 6.81225 15.75 6.90547 15.75 6.99965C15.75 7.09383 15.7306 7.18705 15.6929 7.27371C15.6552 7.36038 15.6 7.43871 15.5306 7.50402L8.78215 13.8533Z" fill="#3D332B"/>
</svg></span>
            <?php endif; ?>
          </div>
        </div>
    <?php endif; ?>

  </div>
</section>

<script>
(function() {
  // fallback: si no hay fetch, que funcione como links normales
  if (typeof window.fetch !== 'function') {
    return;
  }

  var sectionId = <?php echo wp_json_encode( $section_id ); ?>;
  var root = document.querySelector('[data-nl-blog-block-id="' + sectionId + '"]');
  if (!root) return;

  // Cache de respuestas por URL (categoría/página ya visitada)
  var cache = {};

  function smoothScrollToBlock() {
    var rect = root.getBoundingClientRect();
    var offset = window.pageYOffset + rect.top - 120; // ajustá el 120 si tenés header sticky
    window.scrollTo({
      top: offset,
      behavior: 'smooth'
    });
  }

  function swapContent(html) {
    // metemos el nuevo HTML
    root.innerHTML = html;

    // re-enganchamos eventos
    attachHandlers();

    // forzamos reflow para que la animación se aplique bien
    void root.offsetWidth;

    root.classList.remove('nl_blog_is-loading');
    root.classList.add('nl_blog_is-loaded');

    // limpiamos la clase de "entrada" después de un ratito
    setTimeout(function() {
      root.classList.remove('nl_blog_is-loaded');
    }, 300);
  }

  function loadUrl(url) {
    if (!url) return;

    smoothScrollToBlock();
    root.classList.add('nl_blog_is-loading');

    // Si ya tenemos la respuesta en cache, evitamos otra request
    if (cache[url]) {
      swapContent(cache[url]);
      return;
    }

    fetch(url, { credentials: 'same-origin' })
      .then(function(response) {
        return response.text();
      })
      .then(function(html) {
        var tmp = document.createElement('div');
        tmp.innerHTML = html;

        // Buscamos el mismo bloque en la respuesta nueva
        var newBlock = tmp.querySelector('[data-nl-blog-block-id="' + sectionId + '"]');
        if (!newBlock) {
          root.classList.remove('nl_blog_is-loading');
          return;
        }

        var inner = newBlock.innerHTML;

        // guardamos en cache para futuras visitas
        cache[url] = inner;

        swapContent(inner);
      })
      .catch(function() {
        root.classList.remove('nl_blog_is-loading');
      });
  }

  function attachHandlers() {
    // Tabs de categorías (links del nav)
    var navLinks = root.querySelectorAll('.nl_blog_nav-item');
    for (var i = 0; i < navLinks.length; i++) {
      (function(link) {
        link.addEventListener('click', function(e) {
          if (typeof window.fetch !== 'function') return; // fallback total
          e.preventDefault();
          var url = link.getAttribute('href');
          loadUrl(url);
        });
      })(navLinks[i]);
    }

    // Buscador
    var searchForm = root.querySelector('.nl_blog_search-box');
    if (searchForm) {
      searchForm.addEventListener('submit', function(e) {
        if (typeof window.fetch !== 'function') return;
        e.preventDefault();
        var action = searchForm.getAttribute('action') || window.location.href;
        var formData = new FormData(searchForm);
        var params = new URLSearchParams(formData);
        var url = action.split('?')[0] + '?' + params.toString();
        loadUrl(url);
      });
    }

    // Paginación (solo la lista de abajo)
    var pageLinks = root.querySelectorAll('.nl_blog_page-btn[href]');
    for (var j = 0; j < pageLinks.length; j++) {
      (function(a) {
        a.addEventListener('click', function(e) {
          if (typeof window.fetch !== 'function') return;
          e.preventDefault();
          var url = a.getAttribute('href');
          loadUrl(url);
        });
      })(pageLinks[j]);
    }
  }

  // Init en primer render del bloque
  attachHandlers();
})();
</script>

