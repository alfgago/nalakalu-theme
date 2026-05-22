<?php
/**
 * Bloque: Scroll Video (canvas + sticky + overlay + contenido)
 * Campos ACF:
 * - video_de_fondo (url)
 * - logo (image - return array)
 * - descripcion (textarea/text)
 */

if ( ! function_exists('get_field') ) {
  echo '<p><em>ACF plugin required.</em></p>';
  return;
}

$section_id = 'nl-scroll-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$class_name = 'hero';
if (!empty($block['className'])) $class_name .= ' ' . esc_attr($block['className']);
if (!empty($block['align']))     $class_name .= ' align' . esc_attr($block['align']);

$video_url  = (string) get_field('video_de_fondo');
$logo_field = get_field('logo'); // array (url, alt, sizes...)
$desc       = (string) get_field('descripcion');
$link_custom = 'https://stores.nalakalu.com/appointment';
$logo_url = '';
$logo_alt = '';
if (is_array($logo_field)) {
  $logo_url = !empty($logo_field['url']) ? esc_url($logo_field['url']) : '';
  $logo_alt = !empty($logo_field['alt']) ? esc_attr($logo_field['alt']) : '';
}
?>
<section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($class_name); ?>">
  <section class="video-section" id="<?php echo esc_attr($section_id); ?>-section">
    <div class="video-sticky">
      <div class="canvas-wrapper">
        <video class="nl-video" muted playsinline autoplay loop preload="auto"  <?php echo $video_url ? '' : 'style="display:none"'; ?>>
          <?php if ($video_url): ?>
            <?php
              // Si es mp4 le agregamos type (no es obligatorio)
              $type = (stripos($video_url, '.mp4') !== false) ? 'video/mp4' : '';
            ?>
            <source src="<?php echo esc_url($video_url); ?>" <?php echo $type ? 'type="'.$type.'"' : ''; ?> />
          <?php endif; ?>
        </video>
        <canvas></canvas>
      </div>

      <div class="overlay"></div>

      <div class="content">
        <div class="logo-container">
          <?php if ($logo_url): ?>
            <img class="logo-img" src="<?php echo $logo_url; ?>" alt="<?php echo $logo_alt; ?>" loading="lazy" decoding="async">
          <?php else: ?>
            <div class="logo"><?php echo esc_html( get_bloginfo('name') ); ?></div>
          <?php endif; ?>
        </div>

        <?php if ($desc): ?>
          <div class="font-body-small text-white">
            <p><?php echo wp_kses_post( $desc ); ?></p>
          </div>
          <a target="blank" class="mobile-only btn btn-blanco" href="<?php echo esc_url($link_custom); ?>">AGENDAR CITA</a>
        <?php endif; ?>
      </div>
    </div>
  </section>

<script>
(function(){
  var root = document.getElementById('<?php echo esc_js($section_id); ?>');
  if (!root) return;

  var section = document.getElementById('<?php echo esc_js($section_id); ?>-section');
  var wrap    = root.querySelector('.canvas-wrapper');
  var video   = wrap ? wrap.querySelector('video.nl-video') : null;
  var canvas  = wrap ? wrap.querySelector('canvas') : null;

  if (!section || !wrap || !canvas || !video) return;

  var isMobile = window.matchMedia('(max-width: 767px)').matches;

  if (isMobile) {
    root.classList.add('nl-scroll-mobile-canvas');
  }

  /**
   * Importante:
   * En desktop y mobile usamos SIEMPRE:
   * video oculto + canvas visible + scroll controla currentTime.
   */
  video.setAttribute('muted', '');
  video.setAttribute('playsinline', '');
  video.setAttribute('webkit-playsinline', '');
  video.setAttribute('preload', 'auto');

  video.muted = true;
  video.playsInline = true;
  video.preload = 'auto';

  canvas.style.display = 'block';

  // El video queda cargado pero no visible.
  // No usamos display:none porque en iPhone puede dejar de decodificar frames.
  video.style.display = 'block';
  video.style.opacity = '0';
  video.style.visibility = 'hidden';
  video.style.position = 'absolute';
  video.style.width = '1px';
  video.style.height = '1px';
  video.style.pointerEvents = 'none';

  var ctx = canvas.getContext('2d', {
    alpha: false,
    desynchronized: true
  });

  // =========================
  // Tunings
  // =========================
  var TIME_CONST = isMobile ? 0.08 : 0.10;
  var MAX_STEP   = isMobile ? 0.18 : 0.25;
  var SEEK_FPS   = isMobile ? 24 : 30;
  var SEEK_EPS   = 1 / 28;
  var SNAP_STEP  = 1 / 30;

  var prog = 0;
  var progTarget = 0;
  var lastT = performance.now() / 1000;
  var lastSeekAt = 0;
  var started = false;
  var rafId = null;

  // =========================
  // Altura de scroll
  // =========================
  function setScrollLength(){
    var dur = video.duration || 0;

    /**
     * Mobile necesita menos recorrido que desktop,
     * pero suficiente para que se note el scrub.
     */
    var vhPerSec = isMobile ? 60 : 75;
    var minVH    = isMobile ? 180 : 220;
    var totalVH  = 100 + Math.max(minVH, dur * vhPerSec);

    section.style.height = totalVH + 'vh';
  }

  // =========================
  // Canvas responsive
  // =========================
  function fitCanvasToViewport(){
    var dprLimit = isMobile ? 1.15 : 1.4;
    var dpr = Math.min(window.devicePixelRatio || 1, dprLimit);

    var w = Math.round(window.innerWidth * dpr);
    var h = Math.round(window.innerHeight * dpr);

    if (canvas.width !== w || canvas.height !== h) {
      canvas.width = w;
      canvas.height = h;
    }

    drawCover();
  }

  // =========================
  // Progreso según scroll
  // =========================
  function getProgress(){
    var rect = section.getBoundingClientRect();
    var start = window.scrollY + rect.top;
    var end = start + section.offsetHeight - window.innerHeight;

    if (end <= start) return 0;

    var t = (window.scrollY - start) / (end - start);

    return Math.max(0, Math.min(1, t || 0));
  }

  // =========================
  // Dibujar video como object-fit: cover
  // =========================
  function drawCover(){
    if (!ctx || !canvas.width || !canvas.height) return;
    if (!video.videoWidth || !video.videoHeight) return;

    var vW = video.videoWidth;
    var vH = video.videoHeight;
    var cW = canvas.width;
    var cH = canvas.height;

    var vR = vW / vH;
    var cR = cW / cH;

    var sx, sy, sw, sh;

    if (vR > cR) {
      sh = vH;
      sw = vH * cR;
      sx = (vW - sw) * 0.5;
      sy = 0;
    } else {
      sw = vW;
      sh = vW / cR;
      sx = 0;
      sy = (vH - sh) * 0.5;
    }

    try {
      ctx.drawImage(video, sx, sy, sw, sh, 0, 0, cW, cH);
    } catch(e) {}
  }

  // =========================
  // Desbloqueo para mobile real
  // =========================
  function unlockVideoForCanvas(){
    try {
      video.currentTime = 0.001;
    } catch(e) {}

    /**
     * En celulares reales, sobre todo iPhone/Safari,
     * ayuda hacer play() y pausar para que el video decodifique frames.
     */
    try {
      var playPromise = video.play();

      if (playPromise && typeof playPromise.then === 'function') {
        playPromise.then(function(){
          try {
            video.pause();
            video.currentTime = 0.001;
          } catch(e) {}

          drawCover();
          startLoop();
        }).catch(function(){
          drawCover();
          startLoop();
        });
      } else {
        try {
          video.pause();
          video.currentTime = 0.001;
        } catch(e) {}

        drawCover();
        startLoop();
      }
    } catch(e) {
      drawCover();
      startLoop();
    }
  }

  // =========================
  // Loop principal
  // =========================
  function tick(){
    var now = performance.now() / 1000;
    var dt = Math.min(now - lastT, 0.25);
    lastT = now;

    progTarget = getProgress();

    var alpha = 1 - Math.exp(-dt / TIME_CONST);
    prog += (progTarget - prog) * alpha;

    var dur = video.duration || 0;

    if (dur > 0) {
      var desired = dur * prog;

      desired = Math.round(desired / SNAP_STEP) * SNAP_STEP;
      desired = Math.max(0, Math.min(dur, desired));

      var diff = desired - video.currentTime;
      var canSeek = (now - lastSeekAt) >= (1 / SEEK_FPS);

      if (canSeek && Math.abs(diff) > SEEK_EPS) {
        var step = Math.max(-MAX_STEP, Math.min(MAX_STEP, diff));
        var nextT = Math.max(0, Math.min(dur, video.currentTime + step));

        try {
          if (typeof video.fastSeek === 'function') {
            video.fastSeek(nextT);
          } else {
            video.currentTime = nextT;
          }
        } catch(e) {}

        lastSeekAt = now;
      }
    }

    drawCover();

    rafId = requestAnimationFrame(tick);
  }

  function startLoop(){
    if (started) return;

    started = true;
    lastT = performance.now() / 1000;

    fitCanvasToViewport();
    setScrollLength();
    drawCover();

    rafId = requestAnimationFrame(tick);
  }

  // =========================
  // Init
  // =========================
  function init(){
    fitCanvasToViewport();
    setScrollLength();
    unlockVideoForCanvas();
  }

  // Eventos que ayudan en mobile para repintar el canvas
  video.addEventListener('loadedmetadata', function(){
    fitCanvasToViewport();
    setScrollLength();
  });

  video.addEventListener('loadeddata', function(){
    drawCover();
  });

  video.addEventListener('canplay', function(){
    drawCover();
  });

  video.addEventListener('seeked', function(){
    drawCover();
  });

  window.addEventListener('resize', function(){
    fitCanvasToViewport();
    setScrollLength();
  });

  window.addEventListener('orientationchange', function(){
    setTimeout(function(){
      fitCanvasToViewport();
      setScrollLength();
      drawCover();
    }, 300);
  });

  // Arranque
  if (video.readyState >= 1) {
    init();
  } else {
    video.addEventListener('loadedmetadata', init, { once: true });
  }

})();
</script>


</section>
