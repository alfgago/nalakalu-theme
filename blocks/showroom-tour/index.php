<?php
/**
 * Bloque: Showroom Tour
 * Carpeta: showroom-tour
 *
 * Campos ACF:
 * - pretitle     (text)
 * - title        (text)
 * - description  (textarea)
 * - button_url   (url)
 * - carousel_item (repeater)
 *    - imagen_1  (image)
 *    - imagen_2  (image)
 *    - imagen_3  (image)
 *    - imagen_4  (image)
 *    - imagen_5  (image) [opcional]
 */

if ( ! function_exists('get_field') ) {
  echo '<p><em>ACF plugin required.</em></p>';
  return;
}

$section_id = 'sh-tour-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$classes    = 'sh_tour_block';
if ( ! empty($block['className']) ) $classes .= ' ' . esc_attr($block['className']);
if ( ! empty($block['align']) )     $classes .= ' align' . esc_attr($block['align']);

// Campos básicos
$pretitle    = (string) get_field('pretitle');
$title       = (string) get_field('title');
$description = (string) get_field('description');
$button_url  = (string) get_field('button_url');

// Armamos slides desde el repeater
$slides = [];
if ( have_rows('carousel_item') ) {
  while ( have_rows('carousel_item') ) {
    the_row();
    $images = [];
    for ($i = 1; $i <= 5; $i++) {
      $img = get_sub_field('imagen_' . $i);
      if ($img) {
        $images[] = $img;
      }
    }
    if (!empty($images)) {
      $slides[] = $images;
    }
  }
}
$total_slides = count($slides);
$has_carousel = $total_slides > 1;

if ( ! function_exists('nl_tour_img_url') ) {
  function nl_tour_img_url( $img, $size = 'large' ) {
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

  <div class="sh_tour_container">
    <!-- Header -->
    <div class="sh_tour_header">
      <?php if ($pretitle): ?>
        <h3 class="font-overline"><?php echo esc_html($pretitle); ?></h3>
      <?php elseif ( current_user_can('edit_posts') ): ?>
        <h3 class="font-heading-1" style="opacity:.6;">Asigná el campo “pretitle”.</h3>
      <?php endif; ?>

      <div class="sh_tour_counter font-overline">
        <span class="sh_tour_counter-current">
          <?php echo $total_slides ? sprintf('%02d', 1) : '00'; ?>
        </span>
        <span class="sh_tour_counter-separator"> / </span>
        <span class="sh_tour_counter-total">
          <?php echo sprintf('%02d', $total_slides); ?>
        </span>
      </div>
    </div>

    <!-- Contenido -->
    <div class="sh_tour_content">
      <div class="sh_tour_left">
        <?php if ($title): ?>
          <h4 class="font-heading-1"><?php echo esc_html($title); ?></h4>
        <?php elseif ( current_user_can('edit_posts') ): ?>
          <h1 class="font-heading-1" style="opacity:.6;">Asigná el campo “title”.</h1>
        <?php endif; ?>

        <?php if ($description): ?>
          <div class="font-body-small">
            <?php echo wp_kses_post( wpautop($description) ); ?>
          </div>
        <?php elseif ( current_user_can('edit_posts') ): ?>
          <p class="font-body-small" style="opacity:.6;">Asigná el campo “description”.</p>
        <?php endif; ?>

        <?php if ($button_url): ?>
         <button class="btn btn-cafe only-desktop" <a href="<?php echo esc_url($button_url); ?>">
            Registrarse<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
  <mask id="mask0_2013_2279" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="20" height="20">
    <rect width="20" height="20" fill="#D9D9D9"/>
  </mask>
  <g mask="url(#mask0_2013_2279)">
    <path d="M13.4779 10.832H3.33203V9.16536H13.4779L8.8112 4.4987L9.9987 3.33203L16.6654 9.9987L9.9987 16.6654L8.8112 15.4987L13.4779 10.832Z" fill="white"/>
  </g>
</svg>
          </a></button>
        <?php endif; ?>
      </div>

      <div class="sh_tour_right">
        <div class="sh_tour_carousel-wrapper">
          <?php if ($total_slides): ?>
            <?php foreach ($slides as $slide_index => $images): ?>
              <div class="sh_tour_carousel-item <?php echo $slide_index === 0 ? 'is-active' : ''; ?>">
                <div class="sh_tour_image-stack">
                  <?php
                  $img_pos = 1;
                  foreach ($images as $img) :
                    $src = nl_tour_img_url($img, 'large') ?: nl_tour_img_url($img, 'full');
                    if (!$src) continue;
                    if ($img_pos > 5) break;
                  ?>
                    <div class="sh_tour_image-container img-<?php echo (int) $img_pos; ?>">
                      <img src="<?php echo esc_url($src); ?>" alt="">
                    </div>
                  <?php
                    $img_pos++;
                  endforeach;
                  ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php elseif ( current_user_can('edit_posts') ): ?>
            <p style="opacity:.6;">Agregá items en el repeater “carousel_item”.</p>
          <?php endif; ?>
        </div>

      <?php if ($has_carousel): ?>
  <div class="sh_tour_nav-buttons">
    <button type="button" class="sh_tour_nav-btn sh_tour_nav-btn--prev" aria-label="Anterior">←</button>
    <button type="button" class="sh_tour_nav-btn sh_tour_nav-btn--next" aria-label="Siguiente">→</button>
  </div>
<?php endif; ?>
<?php if ($button_url): ?>
         <button class="btn btn-cafe only-mobile" <a href="<?php echo esc_url($button_url); ?>">
            Registrarse<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
  <mask id="mask0_2013_2279" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="20" height="20">
    <rect width="20" height="20" fill="#D9D9D9"/>
  </mask>
  <g mask="url(#mask0_2013_2279)">
    <path d="M13.4779 10.832H3.33203V9.16536H13.4779L8.8112 4.4987L9.9987 3.33203L16.6654 9.9987L9.9987 16.6654L8.8112 15.4987L13.4779 10.832Z" fill="white"/>
  </g>
</svg>
          </a></button>
        <?php endif; ?>
          <div class="sh_tour_logo">
              <div class="sh_logo">
    <?php if (has_custom_logo()) { the_custom_logo(); } ?>
  </div>
          </div>
        </div>
      </div>
    </div>

    <div class="sh_tour_footer"></div>
  </div>

  <?php if ($total_slides > 0): ?>
    <script>
      (function(){
        const block   = document.getElementById('<?php echo esc_js($section_id); ?>');
        if (!block) return;

        const items        = block.querySelectorAll('.sh_tour_carousel-item');
        if (!items.length) return;

        let current        = 0;
const total        = items.length;
const hasCarousel  = total > 1;
const currentSpan  = block.querySelector('.sh_tour_counter-current');
const totalSpan    = block.querySelector('.sh_tour_counter-total');
const prevBtn      = block.querySelector('.sh_tour_nav-btn--prev');
const nextBtn      = block.querySelector('.sh_tour_nav-btn--next');

        if (totalSpan) totalSpan.textContent = String(total).padStart(2, '0');

        function showSlide(index){
          items.forEach((item, i) => {
            if (i === index) {
              item.classList.add('is-active');
            } else {
              item.classList.remove('is-active');
            }
          });
          if (currentSpan) currentSpan.textContent = String(index + 1).padStart(2, '0');
        }

        function nextSlide(){
          current = (current + 1) % total;
          showSlide(current);
        }

        function prevSlide(){
          current = (current - 1 + total) % total;
          showSlide(current);
        }

        if (hasCarousel) {
  if (nextBtn) nextBtn.addEventListener('click', nextSlide);
  if (prevBtn) prevBtn.addEventListener('click', prevSlide);
}

showSlide(current);

let auto = null;

if (hasCarousel) {
  auto = setInterval(nextSlide, 5000);

  block.addEventListener('mouseenter', function(){
    if (auto) {
      clearInterval(auto);
      auto = null;
    }
  });

  block.addEventListener('mouseleave', function(){
    if (!auto) {
      auto = setInterval(nextSlide, 5000);
    }
  });
}
      })();
    </script>
  <?php endif; ?>

</section>
