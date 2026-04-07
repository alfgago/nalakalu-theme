<?php
/**
 * Header Na Lakalú
 * Incluir con get_header()
 */
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
// ===== Helpers para imagen por menú (usando un item con clase 'nlk-thumb') =====
if (!function_exists('nlk_get_menu_image_by_location')) {
  function nlk_get_menu_image_by_location($location) {
    $locations = get_nav_menu_locations();
    if (empty($locations[$location])) return '';
    $menu_obj = wp_get_nav_menu_object($locations[$location]);
    if (!$menu_obj) return '';
    $items = wp_get_nav_menu_items($menu_obj->term_id, ['update_post_term_cache' => false]);
    if (!$items) return '';

    foreach ($items as $it) {
      $classes = is_array($it->classes) ? $it->classes : (array) $it->classes;
      if (in_array('nlk-thumb', $classes, true)) {
        return esc_url($it->url); // usamos la URL del item como URL de imagen
      }
    }
    return '';
  }
}

// Filtro para NO renderizar el item 'nlk-thumb' dentro del <ul>
if (!function_exists('nlk_filter_hide_thumb_items')) {
  function nlk_filter_hide_thumb_items($items, $args) {
    $targets = ['mega_nalakalu','mega_tienda','mega_showrooms','mega_mas','mega_ayuda'];
    if (!empty($args->theme_location) && in_array($args->theme_location, $targets, true)) {
      $items = array_values(array_filter($items, function($it) {
        $classes = is_array($it->classes) ? $it->classes : (array) $it->classes;
        return !in_array('nlk-thumb', $classes, true);
      }));
    }
    return $items;
  }
}
add_filter('wp_nav_menu_objects', 'nlk_filter_hide_thumb_items', 10, 2);

// ===== Enlace a “Agendar Cita” =====
$agenda_page = get_page_by_path('agendar-cita');
$agenda_url  = $agenda_page ? get_permalink($agenda_page) : '#';
?>

<!-- Header fijo (barra superior) -->
<header class="nlk-header header" role="banner">
  <div class="burguer-menu">
    <button class="menu-btn" id="nlkMenuBtn" aria-controls="nlkMenuOverlay" aria-expanded="false" aria-label="<?php esc_attr_e('Abrir menú', 'nalakalu'); ?>">
      <span></span><span></span><span></span>
    </button>
    <p class="font-button text-white desktop-only">MENU</p>
  </div>

  <div class="site-branding logo">
    <?php if (has_custom_logo()) { the_custom_logo(); } ?>
  </div>

  <div class="header-actions">
    <a class="font-button text-white desktop-only" href="<?php echo esc_url($agenda_url); ?>"><?php _e('AGENDAR CITA', 'nalakalu'); ?></a>

    <!-- Lupa -->
    <button class="icon-btn desktop-only nlk-search-trigger" type="button"
  aria-label="<?php esc_attr_e('Buscar', 'nalakalu'); ?>"
  aria-controls="nlkSearchOverlay" aria-expanded="false">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <circle cx="11" cy="11" r="8"></circle>
        <path d="m21 21-4.35-4.35"></path>
      </svg>
    </button>

    <!-- Carrito Woo Side Cart en el header -->
    <div class="xoo-wsc-sc-cont nlk-header-cart">
      <div class="xoo-wsc-cart-trigger" aria-label="<?php esc_attr_e('Ver carrito', 'nalakalu'); ?>">
        <div class="xoo-wsc-sc-bkcont">
          <span class="xoo-wsc-sc-bki xoo-wsc-icon-shopping-bag1"></span>
          <span class="xoo-wsc-sc-count">0</span>
        </div>
      </div>
    </div>

  </div>
</header>
<div class="nlk-search-overlay" id="nlkSearchOverlay" aria-hidden="true">
  <div class="nlk-search-panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Buscar productos', 'nalakalu'); ?>">
    <div class="nlk-search-header">
      <span class="nlk-search-label"><?php echo esc_html('¿QUÉ ESTÁS BUSCANDO?'); ?></span>

      <button class="nlk-search-close-btn" type="button" aria-label="<?php esc_attr_e('Cerrar búsqueda', 'nalakalu'); ?>">
        <?php echo esc_html('CERRAR'); ?> <span class="nlk-search-close-icon" aria-hidden="true">✕</span>
      </button>
    </div>

    <div class="nlk-search-container">
      <div class="nlk-search-box">
        <input type="text" class="nlk-search-input" placeholder="Buscar producto" />
        <span class="nlk-search-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="33" height="33" viewBox="0 0 33 33" fill="none">
  <path d="M23.375 23.375L28.875 28.875M4.125 15.125C4.125 18.0424 5.28393 20.8403 7.34683 22.9032C9.40973 24.9661 12.2076 26.125 15.125 26.125C18.0424 26.125 20.8403 24.9661 22.9032 22.9032C24.9661 20.8403 26.125 18.0424 26.125 15.125C26.125 12.2076 24.9661 9.40973 22.9032 7.34683C20.8403 5.28393 18.0424 4.125 15.125 4.125C12.2076 4.125 9.40973 5.28393 7.34683 7.34683C5.28393 9.40973 4.125 12.2076 4.125 15.125Z" stroke="#3D332B" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
</svg></span>
      </div>
    </div>

    <div class="nlk-search-results" aria-live="polite"></div>
  </div>
</div>


<!-- Capa de interacción + Panel -->
<div class="menu-overlay" id="nlkMenuOverlay" aria-hidden="true">
  <div class="mega-panel" role="dialog" aria-modal="true" aria-labelledby="nlkMegaTitle">
    <div class="menu-content">
      <div class="menu-header">
        <button class="font-button close-btn" id="nlkCloseBtn" aria-label="<?php esc_attr_e('Cerrar menú', 'nalakalu'); ?>">
          <svg xmlns="http://www.w3.org/2000/svg" width=18 height="18" viewBox="0 0 26 26" fill="none">
            <path d="M1.5498 0.25C1.89099 0.250109 2.1931 0.393334 2.44922 0.649414L12.9824 11.1826L23.5166 0.649414C23.7528 0.413382 24.0473 0.287016 24.3857 0.270508C24.7407 0.253121 25.0542 0.38832 25.3154 0.649414C25.5717 0.905654 25.7158 1.20841 25.7158 1.5498C25.7157 1.89104 25.5716 2.19308 25.3154 2.44922L14.7812 12.9824L25.3154 23.5166C25.5516 23.7528 25.6778 24.0472 25.6943 24.3857C25.7117 24.7408 25.5767 25.0541 25.3154 25.3154C25.0592 25.5717 24.7574 25.7158 24.416 25.7158C24.0747 25.7158 23.7728 25.5717 23.5166 25.3154L12.9824 14.7812L2.44922 25.3154C2.21327 25.5514 1.91954 25.6778 1.58203 25.6943C1.22585 25.7118 0.911084 25.5771 0.649414 25.3154C0.39338 25.0593 0.25 24.7573 0.25 24.416C0.25002 24.0748 0.393355 23.7728 0.649414 23.5166L11.1826 12.9824L0.649414 2.44922C0.413528 2.21329 0.28705 1.91949 0.270508 1.58203C0.253063 1.22587 0.387752 0.911076 0.649414 0.649414C0.905654 0.393174 1.20841 0.25 1.5498 0.25Z" fill="#3D332B" stroke="#3D332B" stroke-width="0.5"/>
          </svg> <?php _e('CERRAR', 'nalakalu'); ?>
        </button>

        <div class="menu-logo" id="nlkMegaTitle">
          <div class="black-logo site-branding logo">
            <?php if (has_custom_logo()) { the_custom_logo(); } ?>
          </div>
        </div>
  
        <div class="header-actions-cafe">
          <a class="text-cafe font-button menu-agendar mobile-agendar" href="<?php echo esc_url($agenda_url); ?>">
            <?php _e('AGENDAR CITA', 'nalakalu'); ?>
          </a>

          <!-- Lupa -->
          <button class="icon-btn nlk-search-trigger" type="button"
  aria-label="<?php esc_attr_e('Buscar', 'nalakalu'); ?>"
  aria-controls="nlkSearchOverlay" aria-expanded="false">
            <svg xmlns="http://www.w3.org/2000/svg" width="33" height="33" viewBox="0 0 33 33" fill="none">
              <path d="M23.375 23.375L28.875 28.875M4.125 15.125C4.125 18.0424 5.28393 20.8403 7.34683 22.9032C9.40973 24.9661 12.2076 26.125 15.125 26.125C18.0424 26.125 20.8403 24.9661 22.9032 22.9032C24.9661 20.8403 26.125 18.0424 26.125 15.125C26.125 12.2076 24.9661 9.40973 22.9032 7.34683C20.8403 5.28393 18.0424 4.125 15.125 4.125C12.2076 4.125 9.40973 5.28393 7.34683 7.34683C5.28393 9.40973 4.125 12.2076 4.125 15.125Z" stroke="#3D332B" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>

          <!-- Carrito Woo Side Cart también en el mega menú (opcional) -->
          <div class="xoo-wsc-sc-cont nlk-header-cart">
            <div class="xoo-wsc-cart-trigger" aria-label="<?php esc_attr_e('Ver carrito', 'nalakalu'); ?>">
              <div class="xoo-wsc-sc-bkcont">
                <span class="xoo-wsc-sc-bki xoo-wsc-icon-shopping-bag1"></span>
                <span class="xoo-wsc-sc-count">0</span>
              </div>
            </div>
          </div>

        </div>
      </div>

      <div class="menu-grid">
        <div class="menu-column">
          <h3 class="font-heading-4">Na Lakalú</h3>
          <?php
          wp_nav_menu([
            'theme_location' => 'mega_nalakalu',
            'container'      => false,
            'menu_class'     => 'menu menu--mega',
            'fallback_cb'    => function () {
              echo '<ul class="menu menu--mega"><li><a href="' . esc_url(admin_url('nav-menus.php')) . '">'
                . esc_html__('Configurar menú "Mega: Na Lakalú"', 'nalakalu') . '</a></li></ul>';
            },
          ]);
          $img = nlk_get_menu_image_by_location('mega_nalakalu');
          if ($img) {
            echo '<div class="menu-thumb"><img src="' . esc_url($img) . '" alt="" class="menu-image" loading="lazy"></div>';
          }
          ?>
        </div>

        <div class="menu-column">
          <h3 class="font-heading-4"><?php _e('Tienda', 'nalakalu'); ?></h3>
          <?php
          wp_nav_menu([
            'theme_location' => 'mega_tienda',
            'container'      => false,
            'menu_class'     => 'menu menu--mega',
            'fallback_cb'    => function () {
              echo '<ul class="menu menu--mega"><li><a href="' . esc_url(admin_url('nav-menus.php')) . '">'
                . esc_html__('Configurar menú "Mega: Tienda"', 'nalakalu') . '</a></li></ul>';
            },
          ]);
          $img = nlk_get_menu_image_by_location('mega_tienda');
          if ($img) {
            echo '<div class="menu-thumb"><img src="' . esc_url($img) . '" alt="" class="menu-image" loading="lazy"></div>';
          }
          ?>
        </div>

        <div class="menu-column">
          <h3 class="font-heading-4"><?php _e('Showrooms', 'nalakalu'); ?></h3>
          <?php
          wp_nav_menu([
            'theme_location' => 'mega_showrooms',
            'container'      => false,
            'menu_class'     => 'menu menu--mega',
            'fallback_cb'    => function () {
              echo '<ul class="menu menu--mega"><li><a href="' . esc_url(admin_url('nav-menus.php')) . '">'
                . esc_html__('Configurar menú "Mega: Showrooms"', 'nalakalu') . '</a></li></ul>';
            },
          ]);
          $img = nlk_get_menu_image_by_location('mega_showrooms');
          if ($img) {
            echo '<div class="menu-thumb"><img src="' . esc_url($img) . '" alt="" class="menu-image" loading="lazy"></div>';
          }
          ?>
        </div>

        <div class="menu-column">
          <h3 class="font-heading-4"><?php _e('Más', 'nalakalu'); ?></h3>
          <?php
          wp_nav_menu([
            'theme_location' => 'mega_mas',
            'container'      => false,
            'menu_class'     => 'menu menu--mega',
            'fallback_cb'    => function () {
              echo '<ul class="menu menu--mega"><li><a href="' . esc_url(admin_url('nav-menus.php')) . '">'
                . esc_html__('Configurar menú "Mega: Más"', 'nalakalu') . '</a></li></ul>';
            },
          ]);
          $img = nlk_get_menu_image_by_location('mega_mas');
          if ($img) {
            echo '<div class="menu-thumb"><img src="' . esc_url($img) . '" alt="" class="menu-image" loading="lazy"></div>';
          }
          ?>
        </div>

        <div class="menu-column">
          <h3 class="font-heading-4"><?php _e('Ayuda', 'nalakalu'); ?></h3>
          <?php
          wp_nav_menu([
            'theme_location' => 'mega_ayuda',
            'container'      => false,
            'menu_class'     => 'menu menu--mega',
            'fallback_cb'    => function () {
              echo '<ul class="menu menu--mega"><li><a href="' . esc_url(admin_url('nav-menus.php')) . '">'
                . esc_html__('Configurar menú "Mega: Ayuda"', 'nalakalu') . '</a></li></ul>';
            },
          ]);
          $img = nlk_get_menu_image_by_location('mega_ayuda');
          if ($img) {
            echo '<div class="menu-thumb"><img src="' . esc_url($img) . '" alt="" class="menu-image" loading="lazy"></div>';
          }
          ?>
        </div>

        <div class="menu-footer-cta">
          <a class="text-cafe font-button menu-agendar mobile-only" href="<?php echo esc_url($agenda_url); ?>">
            <?php _e('AGENDAR CITA', 'nalakalu'); ?>
          </a>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
(function(){
  var menuBtn  = document.getElementById('nlkMenuBtn');
  var closeBtn = document.getElementById('nlkCloseBtn');
  var overlay  = document.getElementById('nlkMenuOverlay');
  var panel    = document.querySelector('.mega-panel');
  var header   = document.querySelector('.nlk-header.header');

  if(!menuBtn || !closeBtn || !overlay || !header) return;

  function openMenu(){
    document.body.classList.add('menu-open');
    menuBtn.classList.add('active');
    menuBtn.setAttribute('aria-expanded','true');
    overlay.classList.add('active');
    overlay.setAttribute('aria-hidden','false');
  }

  function closeMenu(){
    document.body.classList.remove('menu-open');
    menuBtn.classList.remove('active');
    menuBtn.setAttribute('aria-expanded','false');
    overlay.classList.remove('active');
    overlay.setAttribute('aria-hidden','true');
  }

  menuBtn.addEventListener('click', openMenu);
  closeBtn.addEventListener('click', closeMenu);

  overlay.addEventListener('click', function(e){
    if (e.target === overlay) closeMenu();
  });

  document.addEventListener('mousedown', function(e){
    if (!overlay.classList.contains('active')) return;
    if (panel && !panel.contains(e.target) && !menuBtn.contains(e.target)) closeMenu();
  });

  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && overlay.classList.contains('active')) closeMenu();
  });

  // ===== Header glass on scroll =====
  var THRESHOLD = 10; // px
  function applyHeaderState(){
    var y = window.pageYOffset || document.documentElement.scrollTop;
    header.classList.toggle('scrolled', y > THRESHOLD);
  }
  applyHeaderState();
  window.addEventListener('scroll', applyHeaderState, { passive: true });
})();
</script>

<script>
(function(){
  const cols = document.querySelectorAll('.mega-panel .menu-column');
  if(!cols.length) return;

  // sólo en mobile
  const isMobile = () => window.matchMedia('(max-width: 768px)').matches;

  function wire(){
    cols.forEach(col=>{
      const title = col.querySelector('h3');
      if(!title || title.__wired) return;
      title.__wired = true;
      title.addEventListener('click', function(){
        if(!isMobile()) return; // evita interferir en desktop
        col.classList.toggle('is-open');
      });
    });
  }
  wire();
  window.addEventListener('resize', wire);
})();
</script>
<script>
(function(){
  var overlay = document.getElementById('nlkSearchOverlay');
  if(!overlay) return;

  var panel   = overlay.querySelector('.nlk-search-panel');
  var input   = overlay.querySelector('.nlk-search-input');
  var results = overlay.querySelector('.nlk-search-results');
  var closeBtn= overlay.querySelector('.nlk-search-close-btn');
  var triggers= document.querySelectorAll('.nlk-search-trigger');

  var ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
  var nonce   = '<?php echo esc_js(wp_create_nonce('nlk_product_search')); ?>';

  function openSearch(){
    overlay.classList.add('is-open');
    overlay.setAttribute('aria-hidden','false');
    document.body.classList.add('nlk-search-open');
    triggers.forEach(function(t){ t.setAttribute('aria-expanded','true'); });
    setTimeout(function(){ if(input) input.focus(); }, 150);
  }

  function closeSearch(){
    overlay.classList.remove('is-open');
    overlay.setAttribute('aria-hidden','true');
    document.body.classList.remove('nlk-search-open');
    triggers.forEach(function(t){ t.setAttribute('aria-expanded','false'); });
  }

  triggers.forEach(function(btn){
    btn.addEventListener('click', function(e){
      e.preventDefault();
      openSearch();
    });
  });

  closeBtn && closeBtn.addEventListener('click', function(e){
    e.preventDefault();
    closeSearch();
  });

  // click afuera del panel (fondo)
  overlay.addEventListener('mousedown', function(e){
    if (e.target === overlay) closeSearch();
  });

  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && overlay.classList.contains('is-open')) closeSearch();
  });

  function renderEmpty(){
    results.innerHTML = '<div class="nlk-search-empty"></div>';
  }

  function renderNoResults(){
    results.innerHTML = '<div class="nlk-search-no-results">No se encontraron productos</div>';
  }

  function escHtml(str){
    return String(str || '').replace(/[&<>"']/g, function(m){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]);
    });
  }

  // ✅ Render SIN descripción (ocultamos .nlk-search-product-description)
  function renderProducts(items){
    var html = '<div class="nlk-search-results-grid">';
    html += items.map(function(p){
      return ''+
      '<a class="nlk-search-product-card" href="'+escHtml(p.url)+'">'+
        '<img class="nlk-search-product-image" src="'+escHtml(p.image)+'" alt="'+escHtml(p.name)+'" loading="lazy" decoding="async" />'+
        '<div class="nlk-search-product-info">'+
          '<div class="nlk-search-product-details">'+
            '<div class="nlk-search-product-name">'+escHtml(p.name)+'</div>'+
          '</div>'+
          '<div class="nlk-search-product-price">'+(p.price_html || '')+'</div>'+
        '</div>'+
      '</a>';
    }).join('');
    html += '</div>';
    results.innerHTML = html;
  }

  var debounceTimer = null;
  function debounce(fn, wait){
    return function(){
      var args = arguments;
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function(){ fn.apply(null, args); }, wait);
    };
  }

  async function fetchProducts(term){
    var url = ajaxUrl + '?action=nlk_product_search&nonce=' + encodeURIComponent(nonce) + '&term=' + encodeURIComponent(term);
    var res = await fetch(url, { credentials: 'same-origin' });
    var json = await res.json();
    if (!json || !json.success) return [];
    return (json.data && json.data.products) ? json.data.products : [];
  }

  var onInput = debounce(async function(){
    var term = (input && input.value ? input.value : '').toLowerCase().trim();

    if (!term) { renderEmpty(); return; }

    // loading suave
    results.innerHTML = '<div class="nlk-search-empty"></div>';

    try{
      var items = await fetchProducts(term);
      if (!items.length) renderNoResults();
      else renderProducts(items);
    }catch(err){
      renderNoResults();
    }
  }, 200);

  // ✅ Buscar mientras escribís
  if (input) {
    input.addEventListener('input', onInput);

    // ✅ Evitar que Enter haga submit o frene el flujo
    input.addEventListener('keydown', function(e){
      if (e.key === 'Enter') e.preventDefault();
    });

    // ✅ Si por algún motivo está dentro de un <form>, cancelamos submit
    var form = input.closest && input.closest('form');
    if (form) {
      form.addEventListener('submit', function(e){ e.preventDefault(); });
    }
  }

  // estado inicial
  renderEmpty();
})();
</script>
