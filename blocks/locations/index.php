<?php
/**
 * Render: Locations
 * Campos:
 * - pretitle (text)
 * - headline (text)
 * - description (textarea)
 * - ubications (repeater):
 *    - title_ubications (text)
 *    - image_ubications (image array)
 *    - url_ubications (url)
 *    - location_description (textarea)
 */

if ( ! function_exists('get_field') ) {
  echo '<p><em>ACF plugin required.</em></p>';
  return;
}

$section_id = 'locations-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$classes    = 'locations-section';
if (!empty($block['className'])) $classes .= ' ' . esc_attr($block['className']);
if (!empty($block['align']))     $classes .= ' align' . esc_attr($block['align']);

$pretitle   = (string) get_field('pretitle');
$headline   = (string) get_field('headline');
$desc       = (string) get_field('description');
$rows       = get_field('ubications');
$arrow_url = 'https://nalakalu.stag.host/wp-content/uploads/2025/10/arrow_forward.svg';

$items      = is_array($rows) ? array_values($rows) : array();
$count      = count($items);
$classes   .= ' has-' . $count;
?>
<section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($classes); ?>">
  <div class="locations">
    <header class="locations__header">
      <div class="locations__intro">
        <?php if ($pretitle): ?>
          <h2 class="font-overline"><?php echo esc_html($pretitle); ?></h2>
        <?php endif; ?>

        <?php if ($headline): ?>
          <h1 class="font-heading-2"><?php echo esc_html($headline); ?></h1>
        <?php endif; ?>
      </div>

      <?php if ($desc): ?>
        
         <div class="locations__aside">
  <a href="<?php echo esc_url( home_url('/showrooms/') ); ?>" class="desktop-solo btn btn-outline-cafe">Ver ubicaciones <img class="cta-arrow" src="<?php echo esc_url($arrow_url); ?>" alt="" aria-hidden="true" /></a>
  <?php if ($desc): ?>
    <p class="font-body-medium-light"><?php echo wp_kses_post( $desc ); ?></p>
  <?php endif; ?>
</div>

      <?php endif; ?>
    </header>

    <div class="locations__grid">
      <?php if ($count): ?>
        <?php foreach ($items as $i => $row): ?>
          <?php
            $title   = isset($row['title_ubications']) ? (string) $row['title_ubications'] : '';
            $url     = isset($row['url_ubications']) ? (string) $row['url_ubications'] : '';
            $img     = (isset($row['image_ubications']) && is_array($row['image_ubications'])) ? $row['image_ubications'] : null;
            $excerpt = isset($row['location_description']) ? (string) $row['location_description'] : '';

            $img_url = $img && !empty($img['url']) ? esc_url($img['url']) : '';
            $img_alt = $img && !empty($img['alt']) ? esc_attr($img['alt']) : esc_attr($title);
            $thumb   = $img_url;

            // clase para layout especial de los 3 primeros
            $item_class = 'location-card';
            if ($i === 0) $item_class .= ' is-first';
            if ($i === 1) $item_class .= ' is-second';
            if ($i === 2) $item_class .= ' is-featured';
          ?>
          <a class="<?php echo esc_attr($item_class); ?>" <?php echo $url ? 'href="'.esc_url($url).'"' : ''; ?> aria-label="<?php echo esc_attr($title ?: 'Location'); ?>">
            <?php if ($thumb): ?>
              <img class="location-card__img" src="<?php echo $thumb; ?>" alt="<?php echo $img_alt; ?>" loading="lazy" decoding="async">
            <?php else: ?>
              <div class="location-card__img placeholder" aria-hidden="true"></div>
            <?php endif; ?>

            <div class="location-card__overlay"></div>

            <?php if ($title): ?>
              <span class="location-card__tag font-button text-cafe"><?php echo esc_html($title); ?></span>
            <?php endif; ?>

            <?php if ($excerpt): ?>
              <div class="font-body-medium-light text-blanco-hueso">
                <?php echo wp_kses_post( wpautop($excerpt) ); ?>
              </div>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php else: ?>
        <?php if ( current_user_can('edit_posts') ) : ?>
          <div style="opacity:.7;">Agregá ubicaciones en el repetidor del bloque.</div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <a href="<?php echo esc_url( home_url('/showrooms/') ); ?>" class="mobile-only btn btn-outline-cafe">Ver ubicaciones </a>
  </div>
</section>
