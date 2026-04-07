<?php
/**
 * Block: Pilares Carousel
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('get_field')) {
  echo '<p><em>ACF plugin required.</em></p>';
  return;
}

$section_id = 'nlk-pilares-' . (isset($block['id']) ? $block['id'] : uniqid());
$classes    = 'nlk-pilares';

if (!empty($block['className'])) $classes .= ' ' . esc_attr($block['className']);
if (!empty($block['align']))     $classes .= ' align' . esc_attr($block['align']);

/**
 * Helpers locales
 */
if (!function_exists('nlk_pilares_get_image_url')) {
  function nlk_pilares_get_image_url($image, $size = 'full') {
    if (empty($image)) return '';
    if (is_array($image)) {
      if (!empty($image['sizes'][$size])) return $image['sizes'][$size];
      if (!empty($image['url'])) return $image['url'];
    }
    if (is_numeric($image)) {
      $url = wp_get_attachment_image_url((int)$image, $size);
      return $url ? $url : '';
    }
    if (is_string($image)) return $image;
    return '';
  }
}

if (!function_exists('nlk_pilares_get_image_alt')) {
  function nlk_pilares_get_image_alt($image) {
    if (empty($image)) return '';
    if (is_array($image)) {
      if (!empty($image['alt'])) return $image['alt'];
      if (!empty($image['ID'])) {
        $alt = get_post_meta((int)$image['ID'], '_wp_attachment_image_alt', true);
        if ($alt) return $alt;
      }
    }
    if (is_numeric($image)) {
      $alt = get_post_meta((int)$image, '_wp_attachment_image_alt', true);
      if ($alt) return $alt;
    }
    return '';
  }
}

if (!function_exists('nlk_pilares_get_file_url')) {
  function nlk_pilares_get_file_url($file) {
    if (empty($file)) return '';
    if (is_array($file) && !empty($file['url'])) return $file['url'];
    if (is_numeric($file)) {
      $url = wp_get_attachment_url((int)$file);
      return $url ? $url : '';
    }
    if (is_string($file)) return $file;
    return '';
  }
}

if (!function_exists('nlk_pilares_get_file_mime')) {
  function nlk_pilares_get_file_mime($file) {
    if (empty($file)) return 'video/mp4';
    if (is_array($file) && !empty($file['mime_type'])) return $file['mime_type'];
    if (is_numeric($file)) {
      $mime = get_post_mime_type((int)$file);
      return $mime ? $mime : 'video/mp4';
    }
    return 'video/mp4';
  }
}

/**
 * Campos
 */
$video_de_fondo = get_field('video_de_fondo');
$carousel       = get_field('carousel');

if (empty($carousel) || !is_array($carousel)) {
  return;
}

/**
 * Slides
 */
$slides = [];

foreach ($carousel as $row) {
  $img_field = $row['imagen_izquierda'] ?? '';
  $img_url   = nlk_pilares_get_image_url($img_field, 'full');
  $img_alt   = nlk_pilares_get_image_alt($img_field);

  $pretitle  = isset($row['pretitle']) ? trim((string)$row['pretitle']) : '';
  $titulo    = isset($row['titulo']) ? trim((string)$row['titulo']) : '';
  $contenido = isset($row['contenido']) ? $row['contenido'] : '';

  if (!$img_url && !$pretitle && !$titulo && !$contenido) {
    continue;
  }

  $slides[] = [
    'image'      => $img_url,
    'image_alt'  => $img_alt ?: $titulo,
    'pretitle'   => $pretitle,
    'title'      => $titulo,
    'content'    => wpautop(wp_kses_post($contenido)),
  ];
}

if (empty($slides)) {
  return;
}

$first_slide = $slides[0];
$video_url   = nlk_pilares_get_file_url($video_de_fondo);
$video_mime  = nlk_pilares_get_file_mime($video_de_fondo);
?>

<section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($classes); ?>">
  
  <?php if ($video_url): ?>
    <div class="nlk-pilares__bg-video" aria-hidden="true">
      <video autoplay muted loop playsinline>
        <source src="<?php echo esc_url($video_url); ?>" type="<?php echo esc_attr($video_mime); ?>">
      </video>
    </div>
  <?php endif; ?>

  <div class="nlk-pilares__grid">

    <div class="nlk-pilares__media nlk-pilares__media--animate">
      <?php if (!empty($first_slide['image'])): ?>
        <img
          class="nlk-pilares__image"
          id="<?php echo esc_attr($section_id); ?>-image"
          src="<?php echo esc_url($first_slide['image']); ?>"
          alt="<?php echo esc_attr($first_slide['image_alt']); ?>"
        >
      <?php endif; ?>
    </div>

    <div class="nlk-pilares__content">

      <p class="font-overline nlk-pilares__animate-in" id="<?php echo esc_attr($section_id); ?>-pretitle">
        <?php echo esc_html($first_slide['pretitle']); ?>
      </p>

      <h2 class="font-heading-2 nlk-pilares__animate-in" id="<?php echo esc_attr($section_id); ?>-title">
        <?php echo nl2br(esc_html($first_slide['title'])); ?>
      </h2>

      <?php if (count($slides) > 1): ?>
        <div class="nlk-pilares__controls nlk-pilares__animate-in">
          <button class="nlk-pilares__btn nlk-pilares__btn--prev" id="<?php echo esc_attr($section_id); ?>-prev" type="button" aria-label="Anterior">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none">
  <path d="M6.96785 13.8533C7.01866 13.9011 7.0786 13.9387 7.14425 13.9638C7.2099 13.989 7.27997 14.0012 7.35047 13.9999C7.49284 13.9972 7.62829 13.9393 7.72701 13.839C7.82574 13.7386 7.87966 13.604 7.8769 13.4648C7.87415 13.3256 7.81496 13.1931 7.71234 13.0966L1.78861 7.52432L15.2131 7.52432C15.3555 7.52432 15.4921 7.46901 15.5927 7.37055C15.6934 7.27209 15.75 7.13854 15.75 6.9993C15.75 6.86005 15.6934 6.72651 15.5927 6.62805C15.4921 6.52959 15.3555 6.47427 15.2131 6.47427L1.79004 6.47427L7.71305 0.90341C7.76387 0.855607 7.80455 0.798484 7.83279 0.735305C7.86102 0.672125 7.87626 0.604126 7.87762 0.535189C7.87898 0.466252 7.86645 0.397728 7.84073 0.333528C7.81501 0.26933 7.77661 0.210712 7.72773 0.161024C7.67885 0.111336 7.62043 0.0715501 7.55582 0.0439384C7.49122 0.0163257 7.42168 0.00142741 7.35118 9.5129e-05C7.28069 -0.00123715 7.21062 0.0110214 7.14497 0.0361717C7.07931 0.061321 7.01937 0.09887 6.96856 0.146673L0.219443 6.49527C0.15005 6.56059 0.0948477 6.63892 0.0571413 6.72559C0.019435 6.81225 0 6.90547 0 6.99965C0 7.09383 0.019435 7.18705 0.0571413 7.27371C0.0948477 7.36038 0.15005 7.43871 0.219443 7.50402L6.96785 13.8533Z" fill="#3D332B"/>
</svg>
          </button>

          <button class="nlk-pilares__btn nlk-pilares__btn--next" id="<?php echo esc_attr($section_id); ?>-next" type="button" aria-label="Siguiente">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none">
  <path d="M8.78215 13.8533C8.73134 13.9011 8.6714 13.9387 8.60575 13.9638C8.5401 13.989 8.47003 14.0012 8.39953 13.9999C8.25716 13.9972 8.12171 13.9393 8.02299 13.839C7.92426 13.7386 7.87034 13.604 7.8731 13.4648C7.87585 13.3256 7.93504 13.1931 8.03766 13.0966L13.9614 7.52432L0.536892 7.52432C0.394499 7.52432 0.257937 7.46901 0.15725 7.37055C0.0565633 7.27209 -1.93715e-06 7.13854 -1.93715e-06 6.9993C-1.93715e-06 6.86005 0.0565633 6.72651 0.15725 6.62805C0.257937 6.52959 0.394499 6.47427 0.536892 6.47427L13.96 6.47427L8.03695 0.90341C7.98613 0.855607 7.94545 0.798484 7.91721 0.735305C7.88898 0.672125 7.87374 0.604126 7.87238 0.535189C7.87102 0.466252 7.88355 0.397728 7.90927 0.333528C7.93499 0.26933 7.97339 0.210712 8.02227 0.161024C8.07115 0.111336 8.12957 0.0715501 8.19418 0.0439384C8.25878 0.0163257 8.32832 0.00142741 8.39882 9.5129e-05C8.46931 -0.00123715 8.53938 0.0110214 8.60503 0.0361717C8.67069 0.061321 8.73063 0.09887 8.78144 0.146673L15.5306 6.49527C15.6 6.56059 15.6552 6.63892 15.6929 6.72559C15.7306 6.81225 15.75 6.90547 15.75 6.99965C15.75 7.09383 15.7306 7.18705 15.6929 7.27371C15.6552 7.36038 15.6 7.43871 15.5306 7.50402L8.78215 13.8533Z" fill="#3D332B"/>
</svg>
          </button>
        </div>
      <?php endif; ?>

      <div class="font-body-small nlk-pilares__animate-in" id="<?php echo esc_attr($section_id); ?>-text">
        <?php echo $first_slide['content']; ?>
      </div>

    </div>

  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const section = document.getElementById('<?php echo esc_js($section_id); ?>');
  if (!section) return;

  const slides = <?php echo wp_json_encode($slides, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  if (!slides || !slides.length) return;

  const image    = document.getElementById('<?php echo esc_js($section_id); ?>-image');
  const pretitle = document.getElementById('<?php echo esc_js($section_id); ?>-pretitle');
  const title    = document.getElementById('<?php echo esc_js($section_id); ?>-title');
  const text     = document.getElementById('<?php echo esc_js($section_id); ?>-text');
  const prevBtn  = document.getElementById('<?php echo esc_js($section_id); ?>-prev');
  const nextBtn  = document.getElementById('<?php echo esc_js($section_id); ?>-next');

  let current = 0;
  let transitioning = false;

  [pretitle, title, text].forEach(function(el){
    if (el) el.style.transition = 'opacity .5s ease, transform .5s ease';
  });

  function goTo(newIndex) {
    if (transitioning || newIndex === current || !slides[newIndex]) return;
    transitioning = true;

    if (image) image.classList.add('is-exiting');
    if (pretitle) {
      pretitle.style.opacity = '0';
      pretitle.style.transform = 'translateY(10px)';
    }
    if (title) {
      title.style.opacity = '0';
      title.style.transform = 'translateY(10px)';
    }
    if (text) {
      text.style.opacity = '0';
      text.style.transform = 'translateY(10px)';
    }

    setTimeout(function () {
      current = newIndex;
      const slide = slides[current];

      if (image && slide.image) {
        image.src = slide.image;
        image.alt = slide.image_alt || slide.title || '';
      }

      if (pretitle) pretitle.textContent = slide.pretitle || '';
      if (title) title.innerHTML = (slide.title || '').replace(/\n/g, '<br>');
      if (text) text.innerHTML = slide.content || '';

      if (image) {
        image.classList.remove('is-exiting');
        image.classList.add('is-entering');
      }

      requestAnimationFrame(function () {
        setTimeout(function () {
          if (image) image.classList.remove('is-entering');

          if (pretitle) {
            pretitle.style.opacity = '1';
            pretitle.style.transform = 'translateY(0)';
          }
          if (title) {
            title.style.opacity = '1';
            title.style.transform = 'translateY(0)';
          }
          if (text) {
            text.style.opacity = '1';
            text.style.transform = 'translateY(0)';
          }

          transitioning = false;
        }, 50);
      });
    }, 500);
  }

  if (prevBtn) {
    prevBtn.addEventListener('click', function () {
      goTo((current - 1 + slides.length) % slides.length);
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', function () {
      goTo((current + 1) % slides.length);
    });
  }
});
</script>