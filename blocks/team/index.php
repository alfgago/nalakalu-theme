<?php
/**
 * Bloque: Team
 *
 * Repeater principal: team_member[]
 *  - titulo (text)
 *  - miembro (repeater)
 *      - name_member (text)
 *      - rol_member (text)
 *      - member_description (textarea)
 *      - secondary_description (textarea)
 *      - imagen_1 (image)
 *      - button_url (url)
 */

if ( ! function_exists('get_field') ) {
  echo '<p><em>ACF plugin required.</em></p>';
  return;
}

$section_id = 'nl-team-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$classes    = 'nl-team';
if (!empty($block['className'])) $classes .= ' ' . esc_attr($block['className']);
if (!empty($block['align']))     $classes .= ' align' . esc_attr($block['align']);

$sections = get_field('team_member');

// Helpers protegidos
if (!function_exists('nl_team_img_url')) {
  function nl_team_img_url($img, $size = 'large') {
    if (is_array($img)) {
      if (!empty($img['sizes'][$size])) return esc_url($img['sizes'][$size]);
      if (!empty($img['url']))          return esc_url($img['url']);
    } elseif (is_numeric($img)) {
      $src = wp_get_attachment_image_src((int)$img, $size);
      if ($src && !empty($src[0])) return esc_url($src[0]);
    } elseif (is_string($img) && filter_var($img, FILTER_VALIDATE_URL)) {
      return esc_url($img);
    }
    return '';
  }
}
if (!function_exists('nl_team_firstname')) {
  function nl_team_firstname($full) {
    $parts = preg_split('/\s+/', trim((string)$full));
    return $parts && isset($parts[0]) ? $parts[0] : trim((string)$full);
  }
}

// Flecha SVG (reusar en mobile prev/next y desktop next)
$arrow_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="14" viewBox="0 0 16 14" fill="none">
  <path d="M8.78215 13.8533C8.73134 13.9011 8.6714 13.9387 8.60575 13.9638C8.5401 13.989 8.47003 14.0012 8.39953 13.9999C8.25716 13.9972 8.12171 13.9393 8.02299 13.839C7.92426 13.7386 7.87034 13.604 7.8731 13.4648C7.87585 13.3256 7.93504 13.1931 8.03766 13.0966L13.9614 7.52432L0.536892 7.52432C0.394499 7.52432 0.257937 7.46901 0.15725 7.37055C0.0565633 7.27209 -1.93715e-06 7.13854 -1.93715e-06 6.9993C-1.93715e-06 6.86005 0.0565633 6.72651 0.15725 6.62805C0.257937 6.52959 0.394499 6.47427 0.536892 6.47427L13.96 6.47427L8.03695 0.90341C7.98613 0.855607 7.94545 0.798484 7.91721 0.735305C7.88898 0.672125 7.87374 0.604126 7.87238 0.535189C7.87102 0.466252 7.88355 0.397728 7.90927 0.333528C7.93499 0.26933 7.97339 0.210712 8.02227 0.161024C8.07115 0.111336 8.12957 0.0715501 8.19418 0.0439384C8.25878 0.0163257 8.32832 0.00142741 8.39882 9.5129e-05C8.46931 -0.00123715 8.53938 0.0110214 8.60503 0.0361717C8.67069 0.061321 8.73063 0.09887 8.78144 0.146673L15.5306 6.49527C15.6 6.56059 15.6552 6.63892 15.6929 6.72559C15.7306 6.81225 15.75 6.90547 15.75 6.99965C15.75 7.09383 15.7306 7.18705 15.6929 7.27371C15.6552 7.36038 15.6 7.43871 15.5306 7.50402L8.78215 13.8533Z" fill="#3D332B"/>
</svg>';
?>

<section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($classes); ?>">
  <div class="nl-team-inner">
    <?php
    if (!empty($sections) && is_array($sections)) {
      foreach ($sections as $i => $row) {

        $title    = (string)($row['titulo'] ?? '');
        $members  = isset($row['miembro']) && is_array($row['miembro']) ? $row['miembro'] : [];
        if (empty($members)) continue;

        $sec_id    = $section_id . '-member-' . $i;
        $sec_class = 'nl-team-section';
        if ($i % 2 === 0) { $sec_class .= ' nl-team-section--bg'; }
        ?>
        <div id="<?php echo esc_attr($sec_id); ?>" class="<?php echo esc_attr($sec_class); ?>">
          <?php if ($title): ?>
            <h1 class="font-heading-2"><?php echo esc_html($title); ?></h1>
          <?php endif; ?>

          <?php /* =========================
                 MOBILE CAROUSEL (solo visible por CSS en mobile)
                 ========================= */ ?>
          <div class="nl-team-carousel" aria-label="Equipo (mobile carousel)">
            <div class="nl-team-carousel-stage">
              <?php if (count($members) > 1): ?>
                <button class="arrow-button arrow-button--prev" type="button" aria-label="Miembro anterior">
                  <?php echo $arrow_svg; ?>
                </button>
              <?php endif; ?>

              <div class="nl-team-carousel-image-wrap">
                <?php foreach ($members as $m_idx => $m_row):
                  $name_member = (string)($m_row['name_member'] ?? '');
                  $img_url     = nl_team_img_url($m_row['imagen_1'] ?? null, 'large') ?: nl_team_img_url($m_row['imagen_1'] ?? null, 'full');
                  if (!$img_url) $img_url = 'https://via.placeholder.com/600x750?text=Foto';
                  ?>
                  <img
                    src="<?php echo esc_url($img_url); ?>"
                    alt="<?php echo esc_attr($name_member ?: 'Miembro del equipo'); ?>"
                    class="nl-team-carousel-image<?php echo $m_idx === 0 ? ' active' : ''; ?>"
                    data-index="<?php echo esc_attr($m_idx); ?>"
                    loading="<?php echo $m_idx === 0 ? 'eager' : 'lazy'; ?>"
                    decoding="async">
                <?php endforeach; ?>
              </div>

              <?php if (count($members) > 1): ?>
                <button class="arrow-button arrow-button--next" type="button" aria-label="Siguiente miembro">
                  <?php echo $arrow_svg; ?>
                </button>
              <?php endif; ?>
            </div>

            <div class="nl-team-carousel-content">
              <?php foreach ($members as $m_idx => $m_row):
                $name  = (string)($m_row['name_member'] ?? '');
                $role  = (string)($m_row['rol_member'] ?? '');
                $desc1 = (string)($m_row['member_description'] ?? '');
                $desc2 = (string)($m_row['secondary_description'] ?? '');
                $btnurl = (string)($m_row['button_url'] ?? '');
                $first  = nl_team_firstname($name);
                $href   = $btnurl ? esc_url($btnurl) : '#';
                $disabled_attr = empty($btnurl) ? ' aria-disabled="true" tabindex="-1" style="pointer-events:none;opacity:.6"' : '';
                $avatar_img  = nl_team_img_url($m_row['imagen_1'] ?? null, 'thumbnail') ?: nl_team_img_url($m_row['imagen_1'] ?? null, 'large');
                if (!$avatar_img) $avatar_img = 'https://via.placeholder.com/80x80?text='.$first;
                ?>
                <div class="nl-team-slide<?php echo $m_idx === 0 ? ' active' : ''; ?>" data-index="<?php echo esc_attr($m_idx); ?>">
                  <?php if ($name): ?>
                    <h2 class="font-heading-4"><?php echo esc_html($name); ?></h2>
                  <?php endif; ?>
                  <?php if ($role): ?>
                    <p class="role font-button"><?php echo esc_html($role); ?></p>
                  <?php endif; ?>

                  <div class="nl-team-slide-text">
                    <?php if ($desc1): ?>
                      <p class="font-body-medium-light"><?php echo wp_kses_post($desc1); ?></p>
                    <?php endif; ?>
                    <?php if ($desc2): ?>
                      <p class="font-body-medium-light"><?php echo wp_kses_post($desc2); ?></p>
                    <?php endif; ?>
                  </div>

                  <a class="contact-button nl-team-contact-mobile"
                     href="<?php echo $href; ?>"<?php echo $disabled_attr; ?>
                     aria-label="<?php echo esc_attr('Contactar a '.$first); ?>">
                    <img src="<?php echo esc_url($avatar_img); ?>" alt="<?php echo esc_attr($first); ?>">
                    <?php echo esc_html('Contactar a '.$first); ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 15 15" fill="none">
                      <path d="M11.7211 3.99004L1.65438 14.0567L0.000717624 12.4031L10.0674 2.33638L0.806879 2.33638L0.82755 0.000575236H14.0569V13.2299L11.7211 13.2506L11.7211 3.99004Z" fill="#3D332B"/>
                    </svg>
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <?php /* =========================
                 DESKTOP ORIGINAL
                 ========================= */ ?>
          <div class="profile-section">
            <div class="left-column">
              <?php if (count($members) > 1): ?>
                <button class="arrow-button" type="button" aria-label="Siguiente miembro">
                  <?php echo $arrow_svg; ?>
                </button>
              <?php endif; ?>

              <div class="main-image-wrapper">
                <?php foreach ($members as $m_idx => $m_row):
                  $name_member = (string)($m_row['name_member'] ?? '');
                  $img_url     = nl_team_img_url($m_row['imagen_1'] ?? null, 'large') ?: nl_team_img_url($m_row['imagen_1'] ?? null, 'full');
                  if (!$img_url) $img_url = 'https://via.placeholder.com/600x750?text=Foto';
                  ?>
                  <img
                    src="<?php echo esc_url($img_url); ?>"
                    alt="<?php echo esc_attr($name_member ?: 'Miembro del equipo'); ?>"
                    class="main-image<?php echo $m_idx === 0 ? ' active' : ''; ?>"
                    data-index="<?php echo esc_attr($m_idx); ?>">
                <?php endforeach; ?>
              </div>
            </div>

            <div class="right-column">
              <div class="member-descriptions">
                <?php foreach ($members as $m_idx => $m_row):
                  $desc1 = (string)($m_row['member_description'] ?? '');
                  ?>
                  <p class="font-body-medium-light member-desc<?php echo $m_idx === 0 ? ' active' : ''; ?>"
                     data-index="<?php echo esc_attr($m_idx); ?>">
                    <?php echo wp_kses_post($desc1); ?>
                  </p>
                <?php endforeach; ?>
              </div>

              <div class="gallery-button-row">
                <div class="thumbnail-gallery">
                  <?php foreach ($members as $m_idx => $m_row):
                    $thumb_name = (string)($m_row['name_member'] ?? '');
                    $thumb_img  = nl_team_img_url($m_row['imagen_1'] ?? null, 'thumbnail') ?: nl_team_img_url($m_row['imagen_1'] ?? null, 'large');
                    if (!$thumb_img) $thumb_img = 'https://via.placeholder.com/300x300?text=Foto';
                    ?>
                    <div class="thumbnail<?php echo $m_idx === 0 ? ' active' : ''; ?>" data-index="<?php echo esc_attr($m_idx); ?>">
                      <img src="<?php echo esc_url($thumb_img); ?>" alt="<?php echo esc_attr($thumb_name ?: 'Miniatura '.($m_idx+1)); ?>">
                    </div>
                  <?php endforeach; ?>
                </div>

                <div class="contact-buttons">
                  <?php foreach ($members as $m_idx => $m_row):
                    $name_member = (string)($m_row['name_member'] ?? '');
                    $first_name  = nl_team_firstname($name_member);
                    $btnurl      = (string)($m_row['button_url'] ?? '');
                    $href        = $btnurl ? esc_url($btnurl) : '#';
                    $is_disabled = empty($btnurl);
                    $disabled_attr = $is_disabled ? ' aria-disabled="true" tabindex="-1" style="pointer-events:none;opacity:.6"' : '';
                    $avatar_img  = nl_team_img_url($m_row['imagen_1'] ?? null, 'thumbnail') ?: nl_team_img_url($m_row['imagen_1'] ?? null, 'large');
                    if (!$avatar_img) $avatar_img = 'https://via.placeholder.com/80x80?text='.$first_name;
                    ?>
                    <a target="blank" class="contact-button<?php echo $is_disabled ? ' is-disabled' : ''; ?><?php echo $m_idx === 0 ? ' active' : ''; ?>"
                       data-index="<?php echo esc_attr($m_idx); ?>"
                       href="<?php echo $href; ?>"<?php echo $disabled_attr; ?>
                       aria-label="<?php echo esc_attr('Contactar a '.$first_name); ?>">
                      <img src="<?php echo esc_url($avatar_img); ?>" alt="<?php echo esc_attr($first_name); ?>">
                      <?php echo esc_html('Contactar a '.$first_name); ?>
                      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 15 15" fill="none">
                        <path d="M11.7211 3.99004L1.65438 14.0567L0.000717624 12.4031L10.0674 2.33638L0.806879 2.33638L0.82755 0.000575236H14.0569V13.2299L11.7211 13.2506L11.7211 3.99004Z" fill="#3D332B"/>
                      </svg>
                    </a>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="profile-info-section">
                <div class="profile-info">
                  <?php foreach ($members as $m_idx => $m_row):
                    $name = (string)($m_row['name_member'] ?? '');
                    $role = (string)($m_row['rol_member'] ?? '');
                    ?>
                    <div class="profile-info-item<?php echo $m_idx === 0 ? ' active' : ''; ?>" data-index="<?php echo esc_attr($m_idx); ?>">
                      <?php if ($name): ?>
                        <h2 class="font-heading-4"><?php echo esc_html($name); ?></h2>
                      <?php endif; ?>
                      <?php if ($role): ?>
                        <p class="role font-button"><?php echo esc_html($role); ?></p>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>

                <?php foreach ($members as $m_idx => $m_row):
                  $desc2 = (string)($m_row['secondary_description'] ?? '');
                  ?>
                  <p class="font-body-medium-light profile-secondary<?php echo $m_idx === 0 ? ' active' : ''; ?>"
                     data-index="<?php echo esc_attr($m_idx); ?>">
                    <?php echo wp_kses_post($desc2); ?>
                  </p>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
        <?php
      }
    } else {
      if ( current_user_can('edit_posts') ) {
        echo '<div class="nl-team-section nl-team-section--bg"><div class="nl-team-placeholder" style="opacity:.7">Agregá miembros en el repetidor <em>Team Members</em>.</div></div>';
      }
    }
    ?>
  </div>

  <script>
  (function(){
    var root = document.getElementById('<?php echo esc_js($section_id); ?>');
    if(!root) return;

    root.querySelectorAll('.nl-team-section').forEach(function(section){
      // Desktop
      var mainImages      = section.querySelectorAll('.main-image');
      var thumbs          = section.querySelectorAll('.thumbnail');
      var nextBtnDesktop  = section.querySelector('.profile-section .left-column .arrow-button');
      var descs           = section.querySelectorAll('.member-desc');
      var infoItems       = section.querySelectorAll('.profile-info-item');
      var secondaryDescs  = section.querySelectorAll('.profile-secondary');
      var contactButtons  = section.querySelectorAll('.profile-section .contact-button');

      // Mobile
      var mobileImages    = section.querySelectorAll('.nl-team-carousel-image');
      var mobileSlides    = section.querySelectorAll('.nl-team-slide');
      var prevBtnMobile   = section.querySelector('.nl-team-carousel .arrow-button--prev');
      var nextBtnMobile   = section.querySelector('.nl-team-carousel .arrow-button--next');

      var total = (mainImages && mainImages.length) ? mainImages.length : (mobileImages ? mobileImages.length : 0);
      var current = 0;

      if (!total) return;

      function deact(list, idx){ if(list && list[idx]) list[idx].classList.remove('active'); }
      function act(list, idx){ if(list && list[idx]) list[idx].classList.add('active'); }

      function setActive(i){
        i = (i + total) % total;
        if (i === current) return;

        deact(mainImages, current);
        deact(thumbs, current);
        deact(descs, current);
        deact(infoItems, current);
        deact(secondaryDescs, current);
        deact(contactButtons, current);
        deact(mobileImages, current);
        deact(mobileSlides, current);

        current = i;

        requestAnimationFrame(function(){
          act(mainImages, current);
          act(thumbs, current);
          act(descs, current);
          act(infoItems, current);
          act(secondaryDescs, current);
          act(contactButtons, current);
          act(mobileImages, current);
          act(mobileSlides, current);
        });
      }

      thumbs.forEach(function(th, idx){
        th.addEventListener('click', function(){ setActive(idx); });
      });

      if (nextBtnDesktop){
        nextBtnDesktop.addEventListener('click', function(){ setActive(current + 1); });
      }
      if (prevBtnMobile){
        prevBtnMobile.addEventListener('click', function(){ setActive(current - 1); });
      }
      if (nextBtnMobile){
        nextBtnMobile.addEventListener('click', function(){ setActive(current + 1); });
      }
    });
  })();
  </script>
</section>
