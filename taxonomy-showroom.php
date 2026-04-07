<?php
if (!defined('ABSPATH')) exit;

get_header();

$term = get_queried_object();

if (!$term || is_wp_error($term) || empty($term->term_id) || $term->taxonomy !== 'showroom') {
    echo '<div class="nlk-shop__empty" style="padding:40px 20px;">No se encontró el showroom.</div>';
    get_footer();
    return;
}

$root_id = 'nlk-showroom-' . (int) $term->term_id;

/**
 * Imagen ACF del término
 */
$banner_url = '';

if (function_exists('get_field')) {
    $background = get_field('background', $term);

    if (empty($background)) {
        $background = get_field('background', $term->taxonomy . '_' . $term->term_id);
    }

    if (is_array($background) && !empty($background['url'])) {
        $banner_url = $background['url'];
    } elseif (is_numeric($background)) {
        $img_url = wp_get_attachment_image_url((int) $background, 'full');
        $banner_url = $img_url ? $img_url : '';
    } elseif (is_string($background)) {
        $banner_url = $background;
    }
}

$title = $term->name;

$paged = max(
    1,
    (int) get_query_var('paged'),
    (int) get_query_var('page')
);

$per_page = 12;

$args = [
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => $per_page,
    'paged'          => $paged,
    'tax_query'      => [
        [
            'taxonomy' => 'showroom',
            'field'    => 'term_id',
            'terms'    => [$term->term_id],
        ],
    ],
];

$q = new WP_Query($args);

$count       = (int) $q->found_posts;
$total_pages = max(1, (int) $q->max_num_pages);
$current     = max(1, (int) $paged);

$prev_url = $current > 1 ? get_pagenum_link($current - 1) : '';
$next_url = $current < $total_pages ? get_pagenum_link($current + 1) : '';

$showroom_terms = get_terms([
    'taxonomy'   => 'showroom',
    'hide_empty' => false,
    'orderby'    => 'name',
    'order'      => 'ASC',
]);
?>

<section id="<?php echo esc_attr($root_id); ?>" class="nlk-shop">

    <div
        class="nlk-shop__banner"
        <?php if ($banner_url) : ?>
            style="background-image:url('<?php echo esc_url($banner_url); ?>');"
        <?php endif; ?>
    ></div>

    <div class="nlk-shop__mobile-actions" aria-label="Acciones">
        <button type="button" class="nlk-shop__mobile-btn" data-action="open-filter">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M3.75 5.83334H16.25M5.83333 10H14.1667M8.33333 14.1667H11.6667" stroke="#3D332B" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Filtrar
        </button>
    </div>

    <div class="nlk-shop__modal" data-modal="filter" aria-hidden="true">
        <div class="nlk-shop__modal-overlay" data-action="close-modal"></div>

        <div class="nlk-shop__modal-panel" role="dialog" aria-modal="true" aria-label="Filtrar">
            <div class="nlk-shop__modal-head">
                <div class="nlk-shop__modal-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="19" height="20" viewBox="0 0 19 20" fill="none">
                        <path d="M3.75 5.83334H16.25M5.83333 10H14.1667M8.33333 14.1667H11.6667" stroke="#3D332B" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Filtrar
                </div>

                <button
                    type="button"
                    class="nlk-shop__modal-close"
                    data-action="close-modal"
                    aria-label="Cerrar"
                >×</button>
            </div>

            <div class="nlk-shop__modal-body" data-role="filter-modal-body"></div>
        </div>
    </div>

    <div class="nlk-shop__page-header">
        <div class="nlk-shop__header">
            <div class="column-header-primary"></div>

            <div class="column-header-secondary">
                <h1 class="nlk-shop__title"><?php echo esc_html($title); ?></h1>
                <span class="nlk-shop__count">
                    <?php echo esc_html(sprintf(_n('%s Producto', '%s Productos', $count, 'textdomain'), number_format_i18n($count))); ?>
                </span>
            </div>
        </div>
    </div>

    <div class="nlk-shop__full-divider"></div>

    <div class="nlk-shop__container">
        <aside class="nlk-shop__sidebar">
            <div class="nlk-shop__filter-box">
                <h2 class="nlk-shop__filter-title">SHOWROOMS</h2>

                <div class="nlk-shop__filter-content">
                   <?php if (!empty($showroom_terms) && !is_wp_error($showroom_terms)) : ?>
  <?php foreach ($showroom_terms as $showroom_item) : ?>
    <a
      class="nlk-shop__filter-item nlk-shop__filter-link nlk-shop__filter-parent <?php echo ((int) $showroom_item->term_id === (int) $term->term_id) ? 'is-active' : ''; ?>"
      href="<?php echo esc_url(get_term_link($showroom_item)); ?>"
    >
      <span><?php echo esc_html($showroom_item->name); ?></span>
    </a>
  <?php endforeach; ?>
<?php endif; ?>
                </div>
            </div>
        </aside>

        <main class="nlk-shop__main">
            <div class="nlk-shop__grid">
                <?php if ($q->have_posts()) : ?>
                    <?php while ($q->have_posts()) : $q->the_post(); ?>
                        <?php
                        $product = function_exists('wc_get_product') ? wc_get_product(get_the_ID()) : null;
                        $img = get_the_post_thumbnail_url(get_the_ID(), 'large');

                        if (!$img && function_exists('wc_placeholder_img_src')) {
                            $img = wc_placeholder_img_src('large');
                        }
                        ?>
                        <a class="nlk-shop__card" href="<?php the_permalink(); ?>">
                            <?php if ($img) : ?>
                                <img
                                    class="nlk-shop__img"
                                    src="<?php echo esc_url($img); ?>"
                                    alt="<?php echo esc_attr(get_the_title()); ?>"
                                >
                            <?php endif; ?>

                            <div class="nlk-shop__info">
                                <div class="nlk-shop__name"><?php the_title(); ?></div>
                                <div class="nlk-shop__price">
                                    <?php echo $product ? wp_kses_post($product->get_price_html()) : ''; ?>
                                </div>
                            </div>
                        </a>
                    <?php endwhile; wp_reset_postdata(); ?>
                <?php else : ?>
                    <div class="nlk-shop__empty">Todavía no hay productos disponibles</div>
                <?php endif; ?>
            </div>

            <?php if ($total_pages > 1) : ?>
                <div class="nlk-shop__pagination">
                    <?php if ($prev_url) : ?>
                        <a class="nlk-shop__page-btn nlk-shop__pagination-btn" href="<?php echo esc_url($prev_url); ?>" aria-label="Página anterior">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none">
                                <path d="M6.96785 13.8533C7.01866 13.9011 7.0786 13.9387 7.14425 13.9638C7.2099 13.989 7.27997 14.0012 7.35047 13.9999C7.49284 13.9972 7.62829 13.9393 7.72701 13.839C7.82574 13.7386 7.87966 13.604 7.8769 13.4648C7.87415 13.3256 7.81496 13.1931 7.71234 13.0966L1.78861 7.52432L15.2131 7.52432C15.3555 7.52432 15.4921 7.46901 15.5927 7.37055C15.6934 7.27209 15.75 7.13854 15.75 6.9993C15.75 6.86005 15.6934 6.72651 15.5927 6.62805C15.4921 6.52959 15.3555 6.47427 15.2131 6.47427L1.79004 6.47427L7.71305 0.90341C7.76387 0.855607 7.80455 0.798484 7.83279 0.735305C7.86102 0.672125 7.87626 0.604126 7.87762 0.535189C7.87898 0.466252 7.86645 0.397728 7.84073 0.333528C7.81501 0.26933 7.77661 0.210712 7.72773 0.161024C7.67885 0.111336 7.62043 0.0715501 7.55582 0.0439384C7.49122 0.0163257 7.42168 0.00142741 7.35118 9.5129e-05C7.28069 -0.00123715 7.21062 0.0110214 7.14497 0.0361717C7.07931 0.061321 7.01937 0.09887 6.96856 0.146673L0.219443 6.49527C0.15005 6.56059 0.0948477 6.63892 0.0571413 6.72559C0.019435 6.81225 0 6.90547 0 6.99965C0 7.09383 0.019435 7.18705 0.0571413 7.27371C0.0948477 7.36038 0.15005 7.43871 0.219443 7.50402L6.96785 13.8533Z" fill="#3D332B"/>
                            </svg>
                        </a>
                    <?php else : ?>
                        <button class="nlk-shop__page-btn nlk-shop__pagination-btn" disabled aria-label="Página anterior">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none">
                                <path d="M6.96785 13.8533C7.01866 13.9011 7.0786 13.9387 7.14425 13.9638C7.2099 13.989 7.27997 14.0012 7.35047 13.9999C7.49284 13.9972 7.62829 13.9393 7.72701 13.839C7.82574 13.7386 7.87966 13.604 7.8769 13.4648C7.87415 13.3256 7.81496 13.1931 7.71234 13.0966L1.78861 7.52432L15.2131 7.52432C15.3555 7.52432 15.4921 7.46901 15.5927 7.37055C15.6934 7.27209 15.75 7.13854 15.75 6.9993C15.75 6.86005 15.6934 6.72651 15.5927 6.62805C15.4921 6.52959 15.3555 6.47427 15.2131 6.47427L1.79004 6.47427L7.71305 0.90341C7.76387 0.855607 7.80455 0.798484 7.83279 0.735305C7.86102 0.672125 7.87626 0.604126 7.87762 0.535189C7.87898 0.466252 7.86645 0.397728 7.84073 0.333528C7.81501 0.26933 7.77661 0.210712 7.72773 0.161024C7.67885 0.111336 7.62043 0.0715501 7.55582 0.0439384C7.49122 0.0163257 7.42168 0.00142741 7.35118 9.5129e-05C7.28069 -0.00123715 7.21062 0.0110214 7.14497 0.0361717C7.07931 0.061321 7.01937 0.09887 6.96856 0.146673L0.219443 6.49527C0.15005 6.56059 0.0948477 6.63892 0.0571413 6.72559C0.019435 6.81225 0 6.90547 0 6.99965C0 7.09383 0.019435 7.18705 0.0571413 7.27371C0.0948477 7.36038 0.15005 7.43871 0.219443 7.50402L6.96785 13.8533Z" fill="#3D332B"/>
                            </svg>
                        </button>
                    <?php endif; ?>

                    <span class="nlk-shop__page-info"><?php echo esc_html($current . ' de ' . $total_pages); ?></span>

                    <?php if ($next_url) : ?>
                        <a class="nlk-shop__page-btn nlk-shop__pagination-btn" href="<?php echo esc_url($next_url); ?>" aria-label="Página siguiente">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none">
                                <path d="M8.78215 13.8533C8.73134 13.9011 8.6714 13.9387 8.60575 13.9638C8.5401 13.989 8.47003 14.0012 8.39953 13.9999C8.25716 13.9972 8.12171 13.9393 8.02299 13.839C7.92426 13.7386 7.87034 13.604 7.8731 13.4648C7.87585 13.3256 7.93504 13.1931 8.03766 13.0966L13.9614 7.52432L0.536892 7.52432C0.394499 7.52432 0.257937 7.46901 0.15725 7.37055C0.0565633 7.27209 -1.93715e-06 7.13854 -1.93715e-06 6.9993C-1.93715e-06 6.86005 0.0565633 6.72651 0.15725 6.62805C0.257937 6.52959 0.394499 6.47427 0.536892 6.47427L13.96 6.47427L8.03695 0.90341C7.98613 0.855607 7.94545 0.798484 7.91721 0.735305C7.88898 0.672125 7.87374 0.604126 7.87238 0.535189C7.87102 0.466252 7.88355 0.397728 7.90927 0.333528C7.93499 0.26933 7.97339 0.210712 8.02227 0.161024C8.07115 0.111336 8.12957 0.0715501 8.19418 0.0439384C8.25878 0.0163257 8.32832 0.00142741 8.39882 9.5129e-05C8.46931 -0.00123715 8.53938 0.0110214 8.60503 0.0361717C8.67069 0.061321 8.73063 0.09887 8.78144 0.146673L15.5306 6.49527C15.6 6.56059 15.6552 6.63892 15.6929 6.72559C15.7306 6.81225 15.75 6.90547 15.75 6.99965C15.75 7.09383 15.7306 7.18705 15.6929 7.27371C15.6552 7.36038 15.6 7.43871 15.5306 7.50402L8.78215 13.8533Z" fill="#3D332B"/>
                            </svg>
                        </a>
                    <?php else : ?>
                        <button class="nlk-shop__page-btn nlk-shop__pagination-btn" disabled aria-label="Página siguiente">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none">
                                <path d="M8.78215 13.8533C8.73134 13.9011 8.6714 13.9387 8.60575 13.9638C8.5401 13.989 8.47003 14.0012 8.39953 13.9999C8.25716 13.9972 8.12171 13.9393 8.02299 13.839C7.92426 13.7386 7.87034 13.604 7.8731 13.4648C7.87585 13.3256 7.93504 13.1931 8.03766 13.0966L13.9614 7.52432L0.536892 7.52432C0.394499 7.52432 0.257937 7.46901 0.15725 7.37055C0.0565633 7.27209 -1.93715e-06 7.13854 -1.93715e-06 6.9993C-1.93715e-06 6.86005 0.0565633 6.72651 0.15725 6.62805C0.257937 6.52959 0.394499 6.47427 0.536892 6.47427L13.96 6.47427L8.03695 0.90341C7.98613 0.855607 7.94545 0.798484 7.91721 0.735305C7.88898 0.672125 7.87374 0.604126 7.87238 0.535189C7.87102 0.466252 7.88355 0.397728 7.90927 0.333528C7.93499 0.26933 7.97339 0.210712 8.02227 0.161024C8.07115 0.111336 8.12957 0.0715501 8.19418 0.0439384C8.25878 0.0163257 8.32832 0.00142741 8.39882 9.5129e-05C8.46931 -0.00123715 8.53938 0.0110214 8.60503 0.0361717C8.67069 0.061321 8.73063 0.09887 8.78144 0.146673L15.5306 6.49527C15.6 6.56059 15.6552 6.63892 15.6929 6.72559C15.7306 6.81225 15.75 6.90547 15.75 6.99965C15.75 7.09383 15.7306 7.18705 15.6929 7.27371C15.6552 7.36038 15.6 7.43871 15.5306 7.50402L8.78215 13.8533Z" fill="#3D332B"/>
                            </svg>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</section>

<script>
(function(){
    var root = document.getElementById('<?php echo esc_js($root_id); ?>');
    if (!root) return;

    var movedFilterBox = null;
    var sidebarPlaceholder = null;

    function qs(selector, ctx) {
        return (ctx || root).querySelector(selector);
    }

    function qsa(selector, ctx) {
        return Array.prototype.slice.call((ctx || root).querySelectorAll(selector));
    }

    function isMobile() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    function getModal(name) {
        return qs('.nlk-shop__modal[data-modal="' + name + '"]');
    }

    function openModal(name) {
        var modal = getModal(name);
        if (!modal) return;

        if (name === 'filter' && isMobile()) {
            var modalBody = qs('[data-role="filter-modal-body"]');
            var filterBox = qs('.nlk-shop__sidebar .nlk-shop__filter-box');

            if (modalBody && filterBox) {
                if (!sidebarPlaceholder) {
                    sidebarPlaceholder = document.createElement('div');
                    sidebarPlaceholder.className = 'nlk-shop__sidebar-placeholder';
                    filterBox.parentNode.insertBefore(sidebarPlaceholder, filterBox);
                }

                movedFilterBox = filterBox;
                movedFilterBox.classList.add('is-in-modal');
                modalBody.appendChild(movedFilterBox);
            }
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('nlk-shop-modal-open');
        document.body.classList.add('nlk-shop-modal-open');
    }

    function closeModals() {
        qsa('.nlk-shop__modal.is-open').forEach(function(modal){
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        });

        if (movedFilterBox && sidebarPlaceholder && sidebarPlaceholder.parentNode) {
            movedFilterBox.classList.remove('is-in-modal');
            sidebarPlaceholder.parentNode.insertBefore(movedFilterBox, sidebarPlaceholder);
        }

        document.documentElement.classList.remove('nlk-shop-modal-open');
        document.body.classList.remove('nlk-shop-modal-open');
    }

    qsa('[data-action="open-filter"]').forEach(function(btn){
        btn.addEventListener('click', function(){
            openModal('filter');
        });
    });

    root.addEventListener('click', function(ev){
        var closeBtn = ev.target.closest('[data-action="close-modal"]');
        if (!closeBtn) return;
        closeModals();
    });

    document.addEventListener('keydown', function(ev){
        if (ev.key === 'Escape') {
            closeModals();
        }
    });

    window.addEventListener('resize', function(){
        if (!isMobile()) {
            closeModals();
        }
    });
})();
</script>

<?php get_footer(); ?>