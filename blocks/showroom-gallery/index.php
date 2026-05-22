<?php
/**
 * Bloque: Showroom Gallery
 * Carpeta: showroom-gallery
 * Campos ACF:
 * - title (text)                      -> título del hero
 * - url_background (url)              -> video de fondo (MP4)
 * - title1 (text), description1 (textarea), section1_background (image)
 * - title2 (text), description2 (textarea), section2_background (image)
 * - title3 (text), description3 (textarea), section3_background (image)
 */

if ( ! function_exists('get_field') ) {
  echo '<p><em>ACF plugin required.</em></p>';
  return;
}

$section_id = 'sh-gallery-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$classes    = 'sh_gallery_block';
if ( ! empty($block['className']) ) $classes .= ' ' . esc_attr($block['className']);
if ( ! empty($block['align']) )     $classes .= ' align' . esc_attr($block['align']);

$title  = (string) get_field('title');
$bg_url = (string) get_field('url_background');

// Secciones (1..3)
$sec = [];
for ($i = 1; $i <= 3; $i++) {
  $sec[$i] = [
    'title' => (string) get_field("title{$i}"),
    'desc'  => (string) get_field("description{$i}"),
    'bg'    => get_field("section{$i}_background"),
  ];
}


if ( ! function_exists('nl_shg_img_url') ) {
  function nl_shg_img_url($img, $size = 'large') {
    if (is_array($img)) {
      if (!empty($img['sizes'][$size])) return esc_url($img['sizes'][$size]);
      if (!empty($img['url']))          return esc_url($img['url']);
    } elseif (is_numeric($img)) {
      $src = wp_get_attachment_image_src((int) $img, $size);
      if ($src && !empty($src[0])) return esc_url($src[0]);
    } elseif (is_string($img) && filter_var($img, FILTER_VALIDATE_URL)) {
      return esc_url($img);
    }
    return '';
  }
}
?>

<section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($classes); ?>">

  <!-- Hero Section con video de fondo - STICKY -->
  <div class="sh_gallery_hero-section">
    <?php if ($bg_url): ?>
      <video class="sh_gallery_hero-video" autoplay muted loop playsinline preload="auto">
        <source src="<?php echo esc_url($bg_url); ?>" type="video/mp4">
      </video>
    <?php endif; ?>

    <div class="sh_gallery_hero-overlay"></div>

    <div class="sh_gallery_hero-content">
      <?php if ($title): ?>
        <h1 class="font-heading-display">
          <?php echo esc_html($title); ?>
        </h1>
      <?php elseif ( current_user_can('edit_posts') ): ?>
        <h1 class="font-heading-display" style="opacity:.6">
          Asigná el campo “title”.
        </h1>
      <?php endif; ?>

      <div class="sh_gallery_scroll-indicator" aria-hidden="true"> <svg xmlns="http://www.w3.org/2000/svg" width="14" height="16" viewBox="0 0 14 16" fill="none">
  <path d="M13.8533 6.96785C13.9011 7.01866 13.9387 7.0786 13.9638 7.14425C13.989 7.2099 14.0012 7.27997 13.9999 7.35047C13.9972 7.49284 13.9393 7.62829 13.839 7.72701C13.7386 7.82574 13.604 7.87966 13.4648 7.8769C13.3256 7.87415 13.1931 7.81496 13.0966 7.71234L7.52432 1.78861L7.52432 15.2131C7.52432 15.3555 7.46901 15.4921 7.37055 15.5927C7.27209 15.6934 7.13854 15.75 6.9993 15.75C6.86005 15.75 6.72651 15.6934 6.62805 15.5927C6.52959 15.4921 6.47427 15.3555 6.47427 15.2131L6.47427 1.79004L0.90341 7.71305C0.855607 7.76387 0.798484 7.80455 0.735305 7.83279C0.672125 7.86102 0.604126 7.87626 0.535189 7.87762C0.466252 7.87898 0.397728 7.86645 0.333528 7.84073C0.26933 7.81501 0.210712 7.77661 0.161024 7.72773C0.111336 7.67885 0.0715501 7.62043 0.0439384 7.55582C0.0163257 7.49122 0.00142741 7.42168 9.5129e-05 7.35118C-0.00123715 7.28069 0.0110214 7.21062 0.0361717 7.14497C0.061321 7.07931 0.09887 7.01937 0.146673 6.96856L6.49527 0.219443C6.56059 0.15005 6.63892 0.0948477 6.72559 0.0571413C6.81225 0.019435 6.90547 0 6.99965 0C7.09383 0 7.18705 0.019435 7.27371 0.0571413C7.36038 0.0948477 7.43871 0.15005 7.50402 0.219443L13.8533 6.96785Z" fill="#3D332B"/>
</svg></div>
      <p class="font-body-small">Scroll Down</p>
    </div>
  </div>

  <?php
  // Secciones 1..3
  for ($i = 1; $i <= 3; $i++):
    $t = trim($sec[$i]['title']);
    $d = trim($sec[$i]['desc']);
    $b = nl_shg_img_url($sec[$i]['bg'], 'large') ?: nl_shg_img_url($sec[$i]['bg'], 'full');
    if ($t === '' && $d === '' && $b === '') continue;
  ?>
    <div class="sh_gallery_content-section sh_gallery_section-<?php echo (int) $i; ?>">

     <!-- Imagen de fondo STICKY dividida en 2 partes -->
<div class="sh_gallery_section-background" aria-hidden="true">

  <div class="sh_gallery_background-part sh_gallery_background-part-blur">
    <?php if ($b): ?>
      <img
        class="sh_gallery_bg-image"
        src="<?php echo esc_url($b); ?>"
        alt=""
        loading="lazy"
        decoding="async">
    <?php endif; ?>
  </div>

  <div class="sh_gallery_background-part sh_gallery_background-part-visual">
    <?php if ($b): ?>
      <img
        class="sh_gallery_bg-image"
        src="<?php echo esc_url($b); ?>"
        alt=""
        loading="lazy"
        decoding="async">
    <?php endif; ?>
  </div>

</div>

      <div class="sh_gallery_content-wrapper">
        <div class="sh_gallery_content-column">
          <div class="sh_gallery_content-box">
            <?php if ($t): ?>
              <h2 class="font-overline text-white">
                <?php echo esc_html($t); ?>
              </h2>
            <?php endif; ?>

            <?php if ($d): ?>
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
