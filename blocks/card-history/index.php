<?php
/**
 * Bloque: Card History
 * Campos:
 * - background_image (image)
 * - card (repeater) -> pretititle, age, title, description, image
 */

if ( ! function_exists('get_field') ) {
  echo '<p><em>ACF plugin required.</em></p>';
  return;
}

$section_id = 'card-history-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$classes    = 'card-history';
if (!empty($block['className'])) $classes .= ' ' . esc_attr($block['className']);
if (!empty($block['align']))     $classes .= ' align' . esc_attr($block['align']);

$bg_img = get_field('background_image');
$cards  = get_field('card');

if (!function_exists('nl_ch_img_url')) {
  function nl_ch_img_url($img, $size = 'large') {
    if (is_array($img)) {
      if (!empty($img['sizes'][$size])) return esc_url($img['sizes'][$size]);
      if (!empty($img['url'])) return esc_url($img['url']);
    } elseif (is_numeric($img)) {
      $src = wp_get_attachment_image_src((int)$img, $size);
      if ($src && !empty($src[0])) return esc_url($src[0]);
    } elseif (is_string($img) && filter_var($img, FILTER_VALIDATE_URL)) {
      return esc_url($img);
    }
    return '';
  }
}

$bg_url   = nl_ch_img_url($bg_img, '1920x1080') ?: nl_ch_img_url($bg_img, 'full');
$style_bg = $bg_url ? "--ch-bg:url('{$bg_url}');" : '';
?>

<section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($classes); ?>">
  <div class="background-wrapper" style="<?php echo esc_attr($style_bg); ?>">
    <div class="background-section" aria-hidden="true"></div>

    <div class="ch-stack">
      <?php
      if (!empty($cards) && is_array($cards)) {
        $i = 0;

        foreach ($cards as $row) {
          $pre   = isset($row['pretititle']) ? (string)$row['pretititle'] : '';
          $age   = isset($row['age']) ? (string)$row['age'] : '';
          $title = isset($row['title']) ? (string)$row['title'] : '';
          $desc  = isset($row['description']) ? (string)$row['description'] : '';
          $img   = isset($row['image']) ? $row['image'] : '';

          $img_url = nl_ch_img_url($img, 'large') ?: nl_ch_img_url($img, 'full');

          $image_html = '';
            if ($img_url) {
            
              $mobile_header_html = '';
              if ($pre !== '' || $age !== '') {
                $mobile_header_html = '
                  <div class="ch-content-1 only-mobile">
                    <div class="header-history font-overline">
                      <span>' . esc_html($pre) . '</span>
                      <span>' . esc_html($age) . '</span>
                    </div>
                  </div>
                ';
              }
            
              $image_html = $mobile_header_html . '
                <div class="image-wrapper">
                  <div class="image-container">
                    <img src="' . esc_url($img_url) . '" alt="' . esc_attr($title ?: 'Historia') . '" loading="lazy" decoding="async">
                  </div>
                </div>
              ';
            }


          $content_html = '<div class="content">
            <div class="ch-content-1 only-desktop">
              <div class="header-history font-overline">
                <span>'.esc_html($pre).'</span>
                <span>'.esc_html($age).'</span>
              </div>
            </div>
            <div class="ch-content-2">
              <h2 class="font-heading-2 text-cafe">'.wp_kses_post($title).'</h2>
              <p class="font-body-medium-light">'.wp_kses_post($desc).'</p>
            </div>
          </div>';

          $reverse_class = ($i % 2 === 1) ? ' is-reverse' : '';
          $z = $i + 1;

          echo '<div class="ch-card bg-blanco-hueso'.$reverse_class.'" style="--ch-z:'.$z.'">';

          if ($i === 0) echo $image_html . $content_html;
          else          echo $content_html . $image_html;

          echo '</div>';

          $i++;
        }
      } else {
        if ( current_user_can('edit_posts') ) {
          echo '<div class="ch-card bg-blanco-hueso"><div class="content"><p style="opacity:.7">Agregá al menos una card en el repetidor.</p></div></div>';
        }
      }
      ?>
    </div>
  </div>
</section>
