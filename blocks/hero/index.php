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
$link_custom = 'https://nalakalu.stag.host/cita';
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
        <video class="nl-video" muted playsinline preload="auto" crossorigin="anonymous" <?php echo $video_url ? '' : 'style="display:none"'; ?>>
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
          <a class="mobile-only btn btn-blanco" href="<?php echo esc_url($link_custom); ?>">AGENDAR CITA</a>
        <?php endif; ?>
      </div>
    </div>
  </section>

 <script>
(function(){
  var root    = document.getElementById('<?php echo esc_js($section_id); ?>');
  if (!root) return;

  var section = document.getElementById('<?php echo esc_js($section_id); ?>-section');
  var wrap    = root.querySelector('.canvas-wrapper');
  var video   = wrap ? wrap.querySelector('video.nl-video') : null;
  var canvas  = wrap ? wrap.querySelector('canvas') : null;
  if (!wrap || !canvas || !video) return;

  // Canvas eficiente
  var ctx = canvas.getContext('2d', { alpha: false, desynchronized: true });

  // === Tunings ===
  // Dibujo: dejalo en RAF / rVFC (60fps típico). El "suavizado" lo maneja TIME_CONST
  var TIME_CONST   = 0.10;      // amortiguación (0.08–0.18). Más alto = más pesado/suave
  var MAX_STEP     = 0.25;      // salto máx por tick (seg). Útil cuando scrolleás rápido
  // Seeks desacoplados del dibujo:
  var SEEK_FPS     = 30;        // como mucho 30 ajustes/seg (no está atado a FPS de canvas)
  var SEEK_EPS     = 1/28;      // umbral fijo (~36ms). Si diff < EPS, no hagas seek
  var SNAP_STEP    = 1/30;      // cuantización opcional al “frame” lógico del scrub

  var prog = 0, progTarget = 0;
  var lastT = performance.now() / 1000;
  var lastSeekAt = 0;

  // 1) Darle “longitud” a la sección según duración
  function setScrollLength(){
    var dur = video.duration || 0;
    var vhPerSec = (window.innerWidth < 768) ? 50 : 75; // un toque más largo = más “frena”
    var totalVH  = 100 + Math.max(220, dur * vhPerSec); // +100vh por el sticky
    section.style.height = totalVH + 'vh';
  }

  // 2) Canvas = viewport * DPR cap
  function fitCanvasToViewport(){
    var dpr = Math.min(window.devicePixelRatio || 1, 1.4);
    var w = Math.round(window.innerWidth  * dpr);
    var h = Math.round(window.innerHeight * dpr);
    if (canvas.width !== w || canvas.height !== h){
      canvas.width  = w;
      canvas.height = h;
    }
  }

  function getProgress(){
    var rect  = section.getBoundingClientRect();
    var start = window.scrollY + rect.top;
    var end   = start + section.offsetHeight - window.innerHeight;
    if (end <= start) return 0; // safety
    var t = (window.scrollY - start) / (end - start);
    return Math.max(0, Math.min(1, t || 0));
  }

  // “object-fit: cover” en canvas
  function drawCover(){
    var vW = video.videoWidth  || 16, vH = video.videoHeight || 9;
    var cW = canvas.width, cH = canvas.height;
    if (!cW || !cH) return;

    var vR = vW / vH, cR = cW / cH;
    var sx, sy, sw, sh;
    if (vR > cR){ // recortar laterales
      sh = vH; sw = vH * cR; sx = (vW - sw) * 0.5; sy = 0;
    } else {      // recortar arriba/abajo
      sw = vW; sh = vW / cR; sx = 0; sy = (vH - sh) * 0.5;
    }
    try { ctx.drawImage(video, sx, sy, sw, sh, 0, 0, cW, cH); } catch(e){}
  }

  // Pintado sincronizado si hay rVFC
  function onVideoFrame(){
    drawCover();
    if (video.requestVideoFrameCallback) video.requestVideoFrameCallback(onVideoFrame);
  }

  function tick(){
    var now = performance.now() / 1000;
    var dt  = Math.min(now - lastT, 0.25);
    lastT = now;

    // Suavizá el scroll -> progreso
    progTarget = getProgress();
    var alpha  = 1 - Math.exp(-dt / TIME_CONST);
    prog += (progTarget - prog) * alpha;

    var dur = video.duration || 0;
    if (dur > 0){
      // Tiempo “deseado” (snap a paso lógico de ~1/30s)
      var desired = dur * prog;
      desired = Math.round(desired / SNAP_STEP) * SNAP_STEP;

      var diff = desired - video.currentTime;

      // Rate-limit seeks y no corrijas micro-diferencias
      var canSeek = (now - lastSeekAt) >= (1 / SEEK_FPS);
      if (canSeek && Math.abs(diff) > SEEK_EPS){
        var step  = Math.max(-MAX_STEP, Math.min(MAX_STEP, diff));
        var nextT = Math.max(0, Math.min(dur, video.currentTime + step));
        try {
          if (typeof video.fastSeek === 'function') video.fastSeek(nextT);
          else video.currentTime = nextT;
        } catch(e){}
        lastSeekAt = now;
      }
    }

    requestAnimationFrame(tick);
  }

  function init(){
    // Asegura buena carga de frames
    try {
      video.preload = 'auto';
      video.muted = true;
      video.pause();
      video.currentTime = 0.001;
      video.playsInline = true;
    } catch(e){}

    fitCanvasToViewport();
    setScrollLength();

    if (video.requestVideoFrameCallback) video.requestVideoFrameCallback(onVideoFrame);
    else (function loop(){ drawCover(); requestAnimationFrame(loop); })();

    lastT = performance.now() / 1000;
    requestAnimationFrame(tick);
  }

  // Opcional: optimizá CPU cuando no está visible
  var visible = true;
  var io = new IntersectionObserver(function(entries){
    visible = entries.some(e => e.isIntersecting);
    if (visible) { lastT = performance.now() / 1000; }
  }, { threshold: 0.01 });
  io.observe(section);

  video.addEventListener('loadedmetadata', init);
  window.addEventListener('resize', function(){ fitCanvasToViewport(); setScrollLength(); });

  if (video.readyState >= 1) init();
})();
</script>


</section>
