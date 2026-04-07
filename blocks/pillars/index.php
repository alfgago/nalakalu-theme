<?php
/**
 * Block: Pillars
 * Campos ACF:
 * - pretitle (texto)                -> .pillars-header__label
 * - title (texto)                   -> .pillars-header__title
 * - button_text (texto)             -> .pillars-header__cta (label)
 * - button_url (url)                -> href del .pillars-header__cta
 * - description (textarea)          -> .pillars-header__description
 * - cards (group)
 *     - card1_image (image)         -> img card 1
 *     - card1_title (text)          -> título card 1
 *     - url_card_1 (url)            -> link card 1
 *     - card2_image (image)         -> img card 2
 *     - card2_title (text)          -> título card 2
 *     - url_card_2 (url)            -> link card 2
 *     - card3_image (image)         -> img card 3
 *     - card3_title (text)          -> título card 3
 *     - url_card_3 (url)            -> link card 3
 */

if ( ! function_exists('get_field') ) {
  echo '<p><em>ACF plugin required.</em></p>';
  return;
}

$section_id = 'pillars-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$classes    = 'pillars-section';
if (!empty($block['className'])) $classes .= ' ' . esc_attr($block['className']);
if (!empty($block['align']))     $classes .= ' align' . esc_attr($block['align']);

// Campos
$pretitle    = (string) get_field('pretitle');
$title       = (string) get_field('title');
$btn_text    = (string) get_field('button_text');
$btn_url     = (string) get_field('button_url');
$description = (string) get_field('description');
$cards_group = get_field('cards');
$arrow_url  = 'https://nalakalu.stag.host/wp-content/uploads/2025/10/arrow_forward.svg';


// --- Helper con guard para evitar redeclaración ---
if ( ! function_exists('nalakalu_pillars_img_data') ) {
  function nalakalu_pillars_img_data($img, $fallback_alt = ''){
    if (!is_array($img)) return ['url'=>'', 'alt'=>''];
    $url = !empty($img['url']) ? esc_url($img['url']) : '';
    $alt = !empty($img['alt']) ? esc_attr($img['alt']) : esc_attr($fallback_alt);
    return ['url'=>$url, 'alt'=>$alt];
  }
}

// Normalizo cards
$card_list = [];
if (is_array($cards_group)) {
  for ($i=1; $i<=3; $i++) {
    $img  = isset($cards_group["card{$i}_image"]) ? $cards_group["card{$i}_image"] : null;
    $ttl  = isset($cards_group["card{$i}_title"]) ? (string)$cards_group["card{$i}_title"] : '';
    $link = isset($cards_group["url_card_{$i}"])  ? (string)$cards_group["url_card_{$i}"]  : '';

    $imgd = nalakalu_pillars_img_data($img, $ttl);

    // Si no hay nada cargado para esa card, la salteo
    if (!$imgd['url'] && !$ttl && !$link) continue;

    $card_list[] = [
      'img'   => $imgd['url'],
      'alt'   => $imgd['alt'],
      'title' => $ttl,
      'link'  => $link,
    ];
  }
}

?>
<section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($classes); ?>">
  <header class="pillars-header">
    <div class="pillars-header__content">
      <?php if ($pretitle): ?>
        <p class="font-overline"><?php echo esc_html($pretitle); ?></p>
      <?php endif; ?>

      <?php if ($title): ?>
        <h1 class="font-heading-2"><?php echo esc_html($title); ?></h1>
      <?php endif; ?>
    </div>

    <div  class="pillars-button__content">
      <?php if ($btn_text && $btn_url): ?>
        <a class="desktop-only view-more btn btn-outline-cafe" href="<?php echo esc_url($btn_url); ?>">
          <?php echo esc_html($btn_text); ?> <img class="cta-arrow" src="<?php echo esc_url($arrow_url); ?>" alt="" aria-hidden="true" />
        </a>
      <?php endif; ?>

      <?php if ($description): ?>
        <p class="font-body-medium-light text-cafe">
          <?php echo wp_kses_post( nl2br($description) ); ?>
        </p>
      <?php endif; ?>
    </div>
  </header>

  <div class="pillars-grid">
    <?php if (!empty($card_list)): ?>
      <?php foreach ($card_list as $card): ?>
        <?php
          $img  = $card['img'];
          $alt  = $card['alt'];
          $ttl  = $card['title'];
          $href = $card['link'];
        ?>

        <?php if ($href): ?>
          <a class="pillar-card" href="<?php echo esc_url($href); ?>">
            <?php if ($img): ?>
              <img class="pillar-card__image" src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($alt); ?>" loading="lazy" decoding="async">
            <?php endif; ?>
            <div class="pillar-card__overlay">
              <?php if ($ttl): ?>
                <h3 class="font-button text-white"><?php echo esc_html($ttl); ?></h3>
              <?php endif; ?>
            </div>
          </a>
        <?php else: ?>
          <article class="pillar-card">
            <?php if ($img): ?>
              <img class="pillar-card__image" src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($alt); ?>" loading="lazy" decoding="async">
            <?php endif; ?>
            <div class="pillar-card__overlay">
              <?php if ($ttl): ?>
                <h3 class="pillar-card__title"><?php echo esc_html($ttl); ?></h3>
              <?php endif; ?>
            </div>
          </article>
        <?php endif; ?>
      <?php endforeach; ?>
    <?php else: ?>
      <?php if ( current_user_can('edit_posts') ): ?>
        <p style="opacity:.7;">Cargá las imágenes, títulos y URLs de las 3 cards en el grupo <em>cards</em>.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
