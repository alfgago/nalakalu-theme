<?php
/**
 * Block: FAQs (ACF)
 * Campos:
 * - faqs (repeater)
 *   - question (text)
 *   - content (wysiwyg)
 * - background (file) -> video mp4 (hoja)
 */

defined('ABSPATH') || exit;

if ( ! function_exists('get_field') ) {
  echo '<p><em>ACF plugin required.</em></p>';
  return;
}

$uid = 'nlk-faqs-' . ( isset($block['id']) ? $block['id'] : uniqid() );

$classes = 'nlk-faqs';
if ( ! empty($block['className']) ) $classes .= ' ' . esc_attr($block['className']);
if ( ! empty($block['align']) )     $classes .= ' align' . esc_attr($block['align']);

$faqs = get_field('faqs');
$bg   = get_field('background'); // puede venir como array o id o url según config ACF

// Normalizador de video URL (evitamos redeclare)
if ( ! function_exists('nlk_faqs_video_url') ) {
  function nlk_faqs_video_url($bg) {
    if (empty($bg)) return '';
    if (is_string($bg)) return esc_url($bg);

    // array de ACF file
    if (is_array($bg)) {
      if (!empty($bg['url'])) return esc_url($bg['url']);
      if (!empty($bg['ID'])) {
        $url = wp_get_attachment_url((int)$bg['ID']);
        return $url ? esc_url($url) : '';
      }
    }

    // id
    if (is_numeric($bg)) {
      $url = wp_get_attachment_url((int)$bg);
      return $url ? esc_url($url) : '';
    }

    return '';
  }
}

$video_url = nlk_faqs_video_url($bg);

// Primer item abierto por defecto
$default_open_index = 0;
?>

<section id="<?php echo esc_attr($uid); ?>" class="<?php echo esc_attr($classes); ?>" aria-label="Preguntas frecuentes">
  <div class="nlk-faqs__inner">

    <?php if ($video_url): ?>
      <div class="nlk-faqs__bg" aria-hidden="true">
        <video class="nlk-faqs__bg-video" autoplay muted loop playsinline preload="metadata">
          <source src="<?php echo esc_url($video_url); ?>" type="video/mp4">
        </video>
      </div>
    <?php endif; ?>

    <div class="nlk-faqs__container">
      <h2 class="nlk-faqs__title">Preguntas Frecuentes</h2>

      <?php if ( ! empty($faqs) && is_array($faqs) ): ?>
        <div class="nlk-faqs__list">
          <?php foreach ($faqs as $i => $row):
            $q = isset($row['question']) ? trim((string)$row['question']) : '';
            $c = isset($row['content']) ? $row['content'] : '';

            if ($q === '' && trim(wp_strip_all_tags((string)$c)) === '') continue;

            $is_open = ($i === $default_open_index);
            $item_id = $uid . '-item-' . $i;
            $btn_id  = $uid . '-btn-' . $i;
            $panel_id= $uid . '-panel-' . $i;
          ?>
            <div class="nlk-faqs__item<?php echo $is_open ? ' is-active' : ''; ?>" id="<?php echo esc_attr($item_id); ?>">
              <button
                type="button"
                class="nlk-faqs__question"
                id="<?php echo esc_attr($btn_id); ?>"
                aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>"
                aria-controls="<?php echo esc_attr($panel_id); ?>"
              >
                <span class="nlk-faqs__question-text"><?php echo esc_html($q ?: ''); ?></span>
                <span class="nlk-faqs__icon" aria-hidden="true">+</span>
              </button>

              <div
                class="nlk-faqs__answer"
                id="<?php echo esc_attr($panel_id); ?>"
                role="region"
                aria-labelledby="<?php echo esc_attr($btn_id); ?>"
                <?php echo $is_open ? '' : 'hidden'; ?>
              >
                <div class="nlk-faqs__answer-content">
                  <?php
                    // WYSIWYG: permitir HTML seguro
                    echo wp_kses_post($c);
                  ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="nlk-faqs__empty">No hay FAQs cargadas.</p>
      <?php endif; ?>

    </div>
  </div>

 <script>
(function(){
  const root = document.getElementById(<?php echo json_encode($uid); ?>);
  if(!root) return;

  const items = Array.from(root.querySelectorAll('.nlk-faqs__item'));

  function setPanelHeight(panel, h){
    panel.style.height = typeof h === 'number' ? `${h}px` : h; // number -> px, string -> 'auto'
  }

  function closeItem(item){
    const btn = item.querySelector('.nlk-faqs__question');
    const panel = item.querySelector('.nlk-faqs__answer');
    if(!btn || !panel || panel.hidden) {
      item.classList.remove('is-active');
      if(btn) btn.setAttribute('aria-expanded','false');
      if(panel) { panel.hidden = true; setPanelHeight(panel, 0); }
      return;
    }

    item.classList.remove('is-active');
    btn.setAttribute('aria-expanded','false');

    // si estaba en auto, primero fijamos el alto actual para poder animar a 0
    const start = panel.scrollHeight;
    setPanelHeight(panel, start);

    requestAnimationFrame(() => {
      setPanelHeight(panel, 0);
    });

    const onEnd = (e) => {
      if(e.propertyName !== 'height') return;
      panel.hidden = true;
      panel.removeEventListener('transitionend', onEnd);
    };
    panel.addEventListener('transitionend', onEnd);
  }

  function openItem(item){
    const btn = item.querySelector('.nlk-faqs__question');
    const panel = item.querySelector('.nlk-faqs__answer');
    if(!btn || !panel) return;

    // cerrar otras
    items.forEach(it => { if(it !== item) closeItem(it); });

    item.classList.add('is-active');
    btn.setAttribute('aria-expanded','true');

    panel.hidden = false;

    // arrancar desde 0 y animar al scrollHeight
    setPanelHeight(panel, 0);

    // siguiente frame: medir y animar
    requestAnimationFrame(() => {
      const target = panel.scrollHeight;
      setPanelHeight(panel, target);

      const onEnd = (e) => {
        if(e.propertyName !== 'height') return;
        // al terminar, dejamos auto para que si el contenido cambia no se corte
        setPanelHeight(panel, 'auto');
        panel.removeEventListener('transitionend', onEnd);
      };
      panel.addEventListener('transitionend', onEnd);
    });
  }

  // Init: panel abierto -> height auto, cerrado -> 0 y hidden
  items.forEach(item => {
    const panel = item.querySelector('.nlk-faqs__answer');
    if(!panel) return;

    if(panel.hidden){
      setPanelHeight(panel, 0);
    } else {
      setPanelHeight(panel, 'auto');
    }

    const btn = item.querySelector('.nlk-faqs__question');
    if(btn){
      btn.addEventListener('click', () => {
        const isActive = item.classList.contains('is-active');
        if(isActive) closeItem(item);
        else openItem(item);
      });
    }
  });
})();
</script>

</section>
