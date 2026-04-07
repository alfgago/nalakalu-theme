<?php
/**
 * Block: Text + Video (Na Lakalú)
 * Campos ACF:
 * - headline (texto)
 * - description (textarea)
 * - video (url)                   -> video principal (YouTube, Vimeo o MP4)
 * - background_video_source (url) -> video de fondo (YouTube o MP4)
 */

if ( ! function_exists('get_field') ) {
  echo '<p><em>ACF plugin required.</em></p>';
  return;
}

$section_id = 'text-video-' . ( isset($block['id']) ? $block['id'] : uniqid() );
$classes    = 'text-video-hero';
if (!empty($block['className'])) $classes .= ' ' . esc_attr($block['className']);
if (!empty($block['align']))     $classes .= ' align' . esc_attr($block['align']);

$headline = (string) get_field('headline');
$desc     = (string) get_field('description');
$main_url = (string) get_field('video');
$bg_url   = (string) get_field('background_video_source');

/** Helpers con guards para evitar “Cannot redeclare …” */
if (!function_exists('tv_is_youtube')) {
  function tv_is_youtube($url){ return (bool) preg_match('~(youtube\.com|youtu\.be)~i', (string)$url); }
}
if (!function_exists('tv_youtube_id')) {
  function tv_youtube_id($url){
    $url = trim((string)$url);
    if (preg_match('~youtu\.be/([^?&/]+)~i', $url, $m)) return $m[1];
    if (preg_match('~youtube\.com/embed/([^?&/]+)~i', $url, $m)) return $m[1];
    if (preg_match('~[?&]v=([^?&/]+)~i',         $url, $m)) return $m[1];
    return '';
  }
}
if (!function_exists('tv_is_vimeo')) {
  function tv_is_vimeo($url){ return (bool) preg_match('~vimeo\.com~i', (string)$url); }
}
if (!function_exists('tv_vimeo_id')) {
  function tv_vimeo_id($url){
    $url = trim((string)$url);
    if (preg_match('~vimeo\.com/(?:video/)?(\d+)~i', $url, $m)) return $m[1];
    return '';
  }
}
if (!function_exists('tv_is_mp4')) {
  function tv_is_mp4($url){ return (bool) preg_match('~\.mp4(\?.*)?$~i', (string)$url); }
}

$bg_is_yt   = tv_is_youtube($bg_url);
$bg_id      = $bg_is_yt ? tv_youtube_id($bg_url) : '';

$main_type  = 'none';   // youtube | vimeo | mp4 | none
$main_meta  = ['id'=>'', 'src'=>$main_url];

if ($main_url) {
  if (tv_is_youtube($main_url) && ($id = tv_youtube_id($main_url))) {
    $main_type = 'youtube'; $main_meta['id'] = $id;
  } elseif (tv_is_vimeo($main_url) && ($id = tv_vimeo_id($main_url))) {
    $main_type = 'vimeo';   $main_meta['id'] = $id;
  } elseif (tv_is_mp4($main_url)) {
    $main_type = 'mp4';
  }
}
?>
<section id="<?php echo esc_attr($section_id); ?>" class="<?php echo esc_attr($classes); ?>">
  <div class="hero-section">

    <!-- Video de fondo (pegado arriba-derecha) -->
    <?php if ($bg_url): ?>
      <?php if ($bg_is_yt && $bg_id): ?>
        <iframe
          class="background-video"
          src="https://www.youtube.com/embed/<?php echo esc_attr($bg_id); ?>?autoplay=1&mute=1&controls=0&loop=1&playlist=<?php echo esc_attr($bg_id); ?>&modestbranding=1&playsinline=1&rel=0"
          title="Background video"
          frameborder="0"
          allow="autoplay; encrypted-media; picture-in-picture"
          loading="lazy"
        ></iframe>
      <?php else: ?>
        <video class="background-video" autoplay muted loop playsinline preload="auto">
          <source src="<?php echo esc_url($bg_url); ?>" type="video/mp4">
        </video>
      <?php endif; ?>
    <?php endif; ?>

    <!-- Encabezado -->
    <div class="header-container">
      <?php if ($headline): ?>
        <h1 class="font-heading-2-light"><?php echo esc_html($headline); ?></h1>
      <?php endif; ?>

      <?php if ($desc): ?>
        <div class="text-box">
          <p class="font-body-medium-light"><?php echo wp_kses_post( nl2br($desc) ); ?></p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Player principal con UI propia -->
    <div class="video-container">
      <div
        class="nl-player"
        data-type="<?php echo esc_attr($main_type); ?>"
        data-video-id="<?php echo esc_attr($main_meta['id']); ?>"
        data-src="<?php echo esc_url($main_meta['src']); ?>"
      >
        <?php if ($main_type === 'youtube'): ?>
          <iframe
            class="nl-player__media"
            id="<?php echo esc_attr($section_id); ?>-yt"
            src="https://www.youtube.com/embed/<?php echo esc_attr($main_meta['id']); ?>?enablejsapi=1&controls=0&autoplay=0&mute=1&loop=1&playlist=<?php echo esc_attr($main_meta['id']); ?>&modestbranding=1&playsinline=1&rel=0"
            title="Main video (YouTube)"
            frameborder="0"
            allow="autoplay; encrypted-media; picture-in-picture"
            loading="lazy"
          ></iframe>
        <?php elseif ($main_type === 'vimeo'): ?>
          <iframe
            class="nl-player__media"
            id="<?php echo esc_attr($section_id); ?>-vm"
            src="https://player.vimeo.com/video/<?php echo esc_attr($main_meta['id']); ?>?background=0&autoplay=0&muted=1&loop=1&byline=0&title=0&portrait=0&controls=0&dnt=1"
            title="Main video (Vimeo)"
            frameborder="0"
            allow="autoplay; encrypted-media; picture-in-picture"
            loading="lazy"
          ></iframe>
        <?php elseif ($main_type === 'mp4'): ?>
          <video class="nl-player__media" preload="metadata" playsinline muted>
            <source src="<?php echo esc_url($main_meta['src']); ?>" type="video/mp4">
          </video>
        <?php else: ?>
          <?php if ( current_user_can('edit_posts') ): ?>
            <div style="padding:2rem;opacity:.7;">Asigná una URL válida (YouTube, Vimeo o MP4) en el campo <em>video</em>.</div>
          <?php endif; ?>
        <?php endif; ?>

        <!-- Overlay de play -->
        <button class="nl-player__overlay" aria-label="Reproducir/Pausar"></button>

        <!-- Controles -->
        <div class="nl-controls">
          <div class="nl-progress" role="slider" aria-label="Progreso">
            <div class="nl-progress__fill"></div>
          </div>

          <div class="nl-controls__row">
            <button class="nl-btn nl-btn--toggle" aria-label="Play/Pause">
              <span class="nl-icon nl-icon--play" aria-hidden="true"></span>
              <span class="nl-icon nl-icon--pause" aria-hidden="true"></span>
            </button>

            <span class="nl-time font-body-small text-blanco-hueso">0:00 / 0:00</span>

            <div class="nl-volume">
              <button class="nl-btn nl-btn--mute" aria-label="Mute/Unmute">
                <span class="nl-icon nl-icon--vol" aria-hidden="true"></span>
                <span class="nl-icon nl-icon--muted" aria-hidden="true"></span>
              </button>
              <div class="nl-volume__bar">
                <div class="nl-volume__fill"></div>
              </div>
            </div>
          </div>
        </div>
      </div><!-- /nl-player -->
    </div>
  </div>

  <script>
  (function(){
    var root = document.getElementById('<?php echo esc_js($section_id); ?>');
    if(!root) return;

    var shell   = root.querySelector('.nl-player');
    if(!shell) return;

    var type    = shell.getAttribute('data-type');
    var vidId   = shell.getAttribute('data-video-id') || '';
    var src     = shell.getAttribute('data-src') || '';
    var media   = shell.querySelector('.nl-player__media');

    var overlay = shell.querySelector('.nl-player__overlay');
    var btnTgl  = shell.querySelector('.nl-btn--toggle');
    var btnMute = shell.querySelector('.nl-btn--mute');
    var prog    = shell.querySelector('.nl-progress');
    var progFill= shell.querySelector('.nl-progress__fill');
    var timeLbl = shell.querySelector('.nl-time');
    var volBar  = shell.querySelector('.nl-volume__bar');
    var volFill = shell.querySelector('.nl-volume__fill');

    var state = { duration: 0, current: 0, muted: true, volume: 1, playing: false };
    var rafId = null, pollId = null;

    function fmt(s){ s = Math.max(0, Math.floor(s)); return Math.floor(s/60)+':'+String(s%60).padStart(2,'0'); }
    function updateTimeUI(){
      var pct = state.duration ? (state.current/state.duration)*100 : 0;
      progFill.style.width = pct + '%';
      timeLbl.textContent = fmt(state.current) + ' / ' + fmt(state.duration||0);
    }
    function updateMuteUI(){ shell.classList.toggle('is-muted', !!state.muted); }
    function updatePlayUI(){
      shell.classList.toggle('is-playing', !!state.playing);
      overlay.style.opacity = state.playing ? '0' : '1';
      overlay.style.pointerEvents = state.playing ? 'none' : 'auto';
    }
    function updateVolUI(){ volFill.style.width = (state.volume*100) + '%'; }

    var api = null;

    function useHTML5(){
      var v = media;
      v.muted = true;
      v.playsInline = true;

      v.addEventListener('loadedmetadata', function(){
        state.duration = v.duration || 0;
        updateTimeUI();
      });
      v.addEventListener('timeupdate', function(){
        state.current = v.currentTime || 0;
        updateTimeUI();
      });
      v.addEventListener('play', function(){ state.playing=true; updatePlayUI(); });
      v.addEventListener('pause', function(){ state.playing=false; updatePlayUI(); });

      api = {
        play: function(){ v.play(); },
        pause: function(){ v.pause(); },
        toggle: function(){ v.paused ? v.play() : v.pause(); },
        seek: function(sec){ v.currentTime = Math.max(0, Math.min(sec, v.duration||sec)); },
        setMuted: function(m){ v.muted = !!m; state.muted = v.muted; updateMuteUI(); },
        setVolume: function(x){ v.volume = x; state.volume = x; if(x>0) { v.muted=false; state.muted=false; } updateVolUI(); updateMuteUI(); },
        getCurrent: function(){ return v.currentTime||0; },
        getDuration: function(){ return v.duration||0; }
      };
    }

    function loadScriptOnce(src, id, cb){
      if(id && document.getElementById(id)){ cb(); return; }
      var s = document.createElement('script');
      if(id) s.id = id;
      s.src = src; s.async = true; s.onload = cb;
      document.head.appendChild(s);
    }

    function useYouTube(){
      loadScriptOnce('https://www.youtube.com/iframe_api', 'yt-iframe-api', function(){});
      window.onYouTubeIframeAPIReady = window.onYouTubeIframeAPIReady || function(){};
      (function wait(){
        if(!window.YT || !window.YT.Player){ return requestAnimationFrame(wait); }
        var player = new YT.Player(media.id, {
          events:{
            'onReady': function(e){
              e.target.mute();
              state.muted = true; updateMuteUI();
              state.duration = Math.floor(e.target.getDuration()||0);
              updateTimeUI();
              pollId = setInterval(function(){
                try{
                  state.current = e.target.getCurrentTime()||0;
                  state.duration = e.target.getDuration()||state.duration;
                  updateTimeUI();
                }catch(_){}
              }, 200);
            },
            'onStateChange': function(ev){
              var s = ev.data;
              if(s === YT.PlayerState.PLAYING){ state.playing = true; }
              else if(s === YT.PlayerState.PAUSED || s === YT.PlayerState.BUFFERING || s === YT.PlayerState.ENDED){ state.playing = false; }
              updatePlayUI();
            }
          }
        });

        api = {
          play: function(){ player.playVideo(); },
          pause:function(){ player.pauseVideo(); },
          toggle:function(){ state.playing ? player.pauseVideo() : player.playVideo(); },
          seek: function(sec){ player.seekTo(sec, true); },
          setMuted: function(m){ m ? player.mute() : player.unMute(); state.muted = !!m; updateMuteUI(); },
          setVolume:function(x){ player.setVolume(Math.round(x*100)); state.volume=x; if(x>0){ player.unMute(); state.muted=false; } updateVolUI(); updateMuteUI(); },
          getCurrent:function(){ return state.current; },
          getDuration:function(){ return state.duration; }
        };
      })();
    }

    function useVimeo(){
      loadScriptOnce('https://player.vimeo.com/api/player.js', 'vimeo-player-api', function(){
        var player = new Vimeo.Player(media);
        player.setMuted(true);
        player.getDuration().then(function(d){ state.duration = Math.floor(d||0); updateTimeUI(); });
        player.on('timeupdate', function(data){
          state.current = data.seconds||0;
          state.duration = data.duration||state.duration;
          updateTimeUI();
        });
        player.on('play',  function(){ state.playing=true;  updatePlayUI(); });
        player.on('pause', function(){ state.playing=false; updatePlayUI(); });

        api = {
          play: function(){ player.play(); },
          pause:function(){ player.pause(); },
          toggle:function(){ state.playing ? player.pause() : player.play(); },
          seek: function(sec){ player.setCurrentTime(sec); },
          setMuted: function(m){ player.setMuted(!!m); state.muted=!!m; updateMuteUI(); },
          setVolume:function(x){ player.setVolume(x).then(function(){ state.volume=x; if(x>0){ state.muted=false; player.setMuted(false); } updateVolUI(); updateMuteUI(); }); },
          getCurrent:function(){ return state.current; },
          getDuration:function(){ return state.duration; }
        };
      });
    }

    if (type === 'mp4')      useHTML5();
    else if (type === 'youtube') useYouTube();
    else if (type === 'vimeo')   useVimeo();

    function tryAPI(fn){ if(api && typeof api[fn]==='function'){ api[fn].apply(null, Array.prototype.slice.call(arguments,1)); } }
    function onToggle(){ tryAPI('toggle'); }
    function onOverlay(){ onToggle(); }
    function onMute(){ tryAPI('setMuted', !state.muted); }
    function onProgressClick(e){
      var r = prog.getBoundingClientRect();
      var pct = Math.min(1, Math.max(0, (e.clientX - r.left)/r.width));
      var sec = (state.duration||0)*pct;
      tryAPI('seek', sec);
    }
    function onVolumeClick(e){
      var r = volBar.getBoundingClientRect();
      var pct = Math.min(1, Math.max(0, (e.clientX - r.left)/r.width));
      tryAPI('setVolume', pct);
    }

    overlay.addEventListener('click', onOverlay);
    btnTgl.addEventListener('click', onToggle);
    btnMute.addEventListener('click', onMute);
    prog.addEventListener('click', onProgressClick);
    volBar.addEventListener('click', onVolumeClick);

    updateMuteUI(); updateVolUI(); updatePlayUI(); updateTimeUI();
  })();
  </script>
</section>
