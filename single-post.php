<?php
/**
 * Template para entradas individuales del blog
 * Archivo: single-post.php
 */

get_header();

if (!defined('ABSPATH')) {
  exit;
}

if (!function_exists('single_post_get_date')) {
  function single_post_get_date($post_id = null) {
    $post_id = $post_id ?: get_the_ID();
    $date = date_i18n('F d, Y', get_the_time('U', $post_id));

    if (function_exists('mb_convert_case')) {
      return mb_convert_case($date, MB_CASE_TITLE, 'UTF-8');
    }

    return ucfirst($date);
  }
}

if (!function_exists('single_post_get_main_category')) {
  function single_post_get_main_category($post_id = null) {
    $post_id = $post_id ?: get_the_ID();
    $cats = get_the_category($post_id);

    if (!empty($cats) && !is_wp_error($cats)) {
      return $cats[0];
    }

    return null;
  }
}

if (have_posts()) :
  while (have_posts()) :
    the_post();

    $current_id = get_the_ID();
    $main_cat   = single_post_get_main_category($current_id);

    $hero_img = has_post_thumbnail($current_id)
      ? get_the_post_thumbnail_url($current_id, 'full')
      : '';

    $related_posts = new WP_Query([
      'post_type'           => 'post',
      'post_status'         => 'publish',
      'posts_per_page'      => 4,
      'post__not_in'        => [$current_id],
      'ignore_sticky_posts' => true,
      'orderby'             => 'date',
      'order'               => 'DESC',
    ]);
?>

<main class="single_post">

  <section
    class="single_post__hero <?php echo empty($hero_img) ? 'single_post__hero--no-image' : ''; ?>"
    <?php if (!empty($hero_img)) : ?>
      style="--single-post-hero-img: url('<?php echo esc_url($hero_img); ?>');"
    <?php endif; ?>
  >
    <div class="single_post__hero_overlay"></div>

    <div class="single_post__hero_content">
      <div class="single_post__meta">
        <?php if ($main_cat) : ?>
          <span class="font-caption-small">
            <?php echo esc_html($main_cat->name); ?>
          </span>
        <?php endif; ?>

        <span class="single_post__date">
          <?php echo esc_html(single_post_get_date($current_id)); ?>
        </span>
      </div>

      <h1 class="single-post font-heading-3"><?php the_title(); ?></h1>
    </div>
  </section>

  <section class="single_post__layout">
    <article class="single_post__content">
      <?php the_content(); ?>

      <?php
      wp_link_pages([
        'before' => '<div class="single_post__pages">',
        'after'  => '</div>',
      ]);
      ?>
    </article>

    <?php if ($related_posts->have_posts()) : ?>
      <aside class="single_post__sidebar" aria-label="Otros artículos">
        <?php
        while ($related_posts->have_posts()) :
          $related_posts->the_post();

          $side_id  = get_the_ID();
          $side_cat = single_post_get_main_category($side_id);
          $side_img = has_post_thumbnail($side_id)
            ? get_the_post_thumbnail_url($side_id, 'medium_large')
            : '';
        ?>

          <article class="single_post__side_card">
            <a href="<?php the_permalink(); ?>" class="single_post__side_image" aria-label="<?php echo esc_attr(get_the_title()); ?>">
              <?php if (!empty($side_img)) : ?>
                <img
                  src="<?php echo esc_url($side_img); ?>"
                  alt="<?php echo esc_attr(get_the_title()); ?>"
                  loading="lazy"
                >
              <?php else : ?>
                <span class="single_post__side_placeholder"></span>
              <?php endif; ?>
            </a>

            <div class="single_post__side_meta">
              <?php if ($side_cat) : ?>
                <span><?php echo esc_html($side_cat->name); ?></span>
              <?php endif; ?>

              <small><?php echo esc_html(single_post_get_date($side_id)); ?></small>
            </div>

            <h3 class="single_post__side_title">
              <a href="<?php the_permalink(); ?>">
                <?php the_title(); ?>
              </a>
            </h3>

            <a href="<?php the_permalink(); ?>" class="single_post__side_link">
              Leer más
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
  <mask id="mask0_3544_375" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="20" height="20">
    <rect width="20" height="20" fill="#D9D9D9"/>
  </mask>
  <g mask="url(#mask0_3544_375)">
    <path d="M13.4779 10.832H3.33203V9.16536H13.4779L8.8112 4.4987L9.9987 3.33203L16.6654 9.9987L9.9987 16.6654L8.8112 15.4987L13.4779 10.832Z" fill="#3D332B"/>
  </g>
</svg>
            </a>
          </article>

        <?php
        endwhile;
        wp_reset_postdata();
        ?>
      </aside>
    <?php endif; ?>
  </section>

</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const content = document.querySelector('.single_post__content');

  if (!content) return;

  const children = Array.from(content.children);
  let currentGrid = null;

  function isStandaloneImageBlock(el) {
    if (!el) return false;

    const isFigureImage = el.matches('figure.wp-block-image') && el.querySelector('img');

    const isParagraphImage =
      el.tagName === 'P' &&
      el.children.length === 1 &&
      el.children[0].tagName === 'IMG' &&
      el.textContent.trim() === '';

    return isFigureImage || isParagraphImage;
  }

  children.forEach(function (el) {
    if (isStandaloneImageBlock(el)) {
      if (!currentGrid) {
        currentGrid = document.createElement('div');
        currentGrid.className = 'single_post__image_grid';
        content.insertBefore(currentGrid, el);
      }

      currentGrid.appendChild(el);
    } else {
      currentGrid = null;
    }
  });
});
</script>

<?php
  endwhile;
endif;

get_footer();