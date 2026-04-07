(function () {
  'use strict';

  function matches(el, sel) {
    if (!el || !sel) return false;
    var fn = el.matches || el.webkitMatchesSelector || el.msMatchesSelector;
    return fn ? fn.call(el, sel) : false;
  }

  function closest(el, sel) {
    while (el && el !== document) {
      if (matches(el, sel)) return el;
      el = el.parentNode;
    }
    return null;
  }

  function qs(scope, sel) {
    return scope ? scope.querySelector(sel) : null;
  }

  function qsa(scope, sel) {
    return scope ? Array.prototype.slice.call(scope.querySelectorAll(sel)) : [];
  }

  function toInt(v) {
    var n = parseInt(v || '0', 10);
    return isNaN(n) ? 0 : n;
  }

  function safeJSON64(str) {
    try {
      if (!str) return {};
      return JSON.parse(window.atob(str)) || {};
    } catch (e) {
      console.warn('[HS] JSON64 parse error', e, str);
      return {};
    }
  }

  function getMaps(slide) {
    if (!slide) return { tmap: {}, pmap: {} };

    return {
      tmap: safeJSON64(slide.getAttribute('data-tmap')),
      pmap: safeJSON64(slide.getAttribute('data-pmap'))
    };
  }

  function getSeedPid(slide) {
    var maps = getMaps(slide);
    var tKeys = Object.keys(maps.tmap || {});
    if (tKeys.length) return toInt(tKeys[0]);

    var pKeys = Object.keys(maps.pmap || {});
    if (pKeys.length) return toInt(pKeys[0]);

    return 0;
  }

  function initHotspots(root) {
    if (!root || root.dataset.hsInit === '1') return;
    root.dataset.hsInit = '1';

    var track      = qs(root, '[data-hs-track]');
    var dotsC      = qs(root, '[data-hs-dots]');
    var thumbsWrap = qs(root, '[data-hs-thumbs]');

    var tName    = qs(root, '[data-hs-tname]');
    var tRole    = qs(root, '[data-hs-trole]');
    var tText    = qs(root, '[data-hs-ttext]');

    var pImg     = qs(root, '[data-hs-pimg]');
    var pName    = qs(root, '[data-hs-pname]');
    var pPrice   = qs(root, '[data-hs-pprice]');
    var pLink    = qs(root, '[data-hs-plink]');
    var pVariant = qs(root, '[data-hs-pvariant]');

    var slides = qsa(root, '.carousel-slide');
    var thumbs = qsa(root, '.thumbnail');
    var navBtns = qsa(root, '[data-hs-nav]');

    if (!track || !slides.length) {
      console.warn('[HS] no track or no slides', root);
      return;
    }

    console.log('[HS] init ok', {
      root: root,
      slides: slides.length,
      thumbs: thumbs.length,
      navBtns: navBtns.length
    });

    var mq = window.matchMedia ? window.matchMedia('(max-width: 768px)') : null;
    var isMobile = mq ? mq.matches : (window.innerWidth <= 768);

    function syncMobile() {
      isMobile = mq ? mq.matches : (window.innerWidth <= 768);
    }

    if (mq) {
      if (mq.addEventListener) mq.addEventListener('change', syncMobile);
      else if (mq.addListener) mq.addListener(syncMobile);
    }

    /* Hardening por si el lookbook mete capas raras */
    if (thumbsWrap) {
      thumbsWrap.style.position = 'relative';
      thumbsWrap.style.zIndex = '10002';
      thumbsWrap.style.pointerEvents = 'auto';
    }

    qsa(root, '.thumbnail, .thumbnail img, .nav-buttons, .nav-btn').forEach(function (el) {
      el.style.pointerEvents = 'auto';
    });

    var currentIndex = 0;
    var dots = [];

    var modal = qs(root, '.hs-modal');
    if (!modal) {
      modal = document.createElement('div');
      modal.className = 'hs-modal';
      modal.innerHTML =
        '<div class="hs-modal__backdrop" data-close></div>' +
        '<div class="hs-modal__sheet" role="dialog" aria-modal="true" aria-label="Producto">' +
          '<div class="hs-modal__top">' +
            '<div class="font-overline">Producto</div>' +
            '<button class="hs-modal__close" type="button" aria-label="Cerrar" data-close>✕</button>' +
          '</div>' +
          '<a data-mlink href="#" aria-label="Ver producto">' +
            '<img class="hs-modal__img" data-mimg src="" alt="">' +
          '</a>' +
          '<div class="hs-modal__info">' +
            '<a class="hs-modal__name font-button" data-mname href="#"></a>' +
            '<div class="font-overline" data-mprice></div>' +
          '</div>' +
          '<a class="hs-modal__cta font-button" data-mcta href="#">Ver producto</a>' +
        '</div>';

      root.appendChild(modal);
    }

    var mImg   = qs(modal, '[data-mimg]');
    var mName  = qs(modal, '[data-mname]');
    var mPrice = qs(modal, '[data-mprice]');
    var mLink  = qs(modal, '[data-mlink]');
    var mCta   = qs(modal, '[data-mcta]');

    function closeModal() {
      modal.classList.remove('is-open');
      document.documentElement.classList.remove('hs-modal-open');
      document.body.classList.remove('hs-modal-open');
    }

    function openModalWithProduct(p) {
      if (!p) return;

      if (mImg) {
        if (p.img_url) mImg.src = p.img_url;
        else mImg.removeAttribute('src');
      }

      if (mName) {
        mName.textContent = p.name || '';
        if (p.permalink) mName.setAttribute('href', p.permalink);
        else mName.removeAttribute('href');
      }

      if (mPrice) mPrice.innerHTML = p.price_html || '';

      var href = p.permalink || '#';
      if (mLink) mLink.setAttribute('href', href);
      if (mCta)  mCta.setAttribute('href', href);

      modal.classList.add('is-open');
      document.documentElement.classList.add('hs-modal-open');
      document.body.classList.add('hs-modal-open');
    }

    modal.addEventListener('click', function (e) {
      var t = e.target;
      if (t && t.getAttribute && t.getAttribute('data-close') !== null) {
        closeModal();
      }
    });

    function rebuildDots() {
      dots = [];
      if (!dotsC) return;

      dotsC.innerHTML = '';

      slides.forEach(function (_, i) {
        var d = document.createElement('div');
        d.className = 'dot' + (i === currentIndex ? ' active' : '');
        d.setAttribute('data-index', String(i));

        d.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          console.log('[HS] dot click', i);
          goTo(i);
        });

        dotsC.appendChild(d);
        dots.push(d);
      });
    }

    function updatePanels(slide, pid) {
      if (!slide) return;

      var maps = getMaps(slide);
      var tmap = maps.tmap || {};
      var pmap = maps.pmap || {};

      var usePid = pid;
      if (!usePid || (!tmap[String(usePid)] && !pmap[String(usePid)])) {
        usePid = getSeedPid(slide);
      }

      var t = tmap[String(usePid)] || {};
      var p = pmap[String(usePid)] || {};

      if (tName) tName.textContent = t.nombre || '';
      if (tRole) tRole.textContent = t.rol || '';
      if (tText) tText.innerHTML = t.contenido || '';

      if (pImg) {
        if (p.img_url) pImg.src = p.img_url;
        else pImg.removeAttribute('src');
      }

      if (pName) {
        pName.textContent = p.name || '';
        if (p.permalink) pName.href = p.permalink;
        else pName.removeAttribute('href');
      }

      if (pLink) {
        if (p.permalink) pLink.href = p.permalink;
        else pLink.removeAttribute('href');
      }

      if (pPrice) pPrice.innerHTML = p.price_html || '';
      if (pVariant) pVariant.textContent = '';
    }

    function initFirstMarker(slide) {
      if (!slide) return;

      var marker = qs(slide, '.wlb-marker[data-pid]');
      if (marker) {
        updatePanels(slide, toInt(marker.getAttribute('data-pid')));
        return;
      }

      updatePanels(slide, getSeedPid(slide));
    }

    function setActiveUI() {
      track.style.transform = 'translateX(-' + (currentIndex * 100) + '%)';

      slides.forEach(function (slide, i) {
        if (i === currentIndex) slide.classList.add('is-active');
        else slide.classList.remove('is-active');
      });

      dots.forEach(function (dot, i) {
        if (i === currentIndex) dot.classList.add('active');
        else dot.classList.remove('active');
      });

      thumbs.forEach(function (thumb, i) {
        if (i === currentIndex) thumb.classList.add('active');
        else thumb.classList.remove('active');
      });

      initFirstMarker(slides[currentIndex]);
    }

    function goTo(i) {
      currentIndex = (i + slides.length) % slides.length;
      console.log('[HS] goTo', currentIndex);
      setActiveUI();
    }

    function nextSlide() {
      goTo(currentIndex + 1);
    }

    function prevSlide() {
      goTo(currentIndex - 1);
    }

    navBtns.forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var dir = btn.getAttribute('data-hs-nav');
        console.log('[HS] nav click', dir);

        if (dir === 'next') nextSlide();
        else prevSlide();
      });
    });

    thumbs.forEach(function (thumb, idx) {
      thumb.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        console.log('[HS] thumb click', idx);
        goTo(idx);

        if (thumbsWrap) {
          var tw = thumb.offsetWidth + 20;
          var targetLeft = (tw * idx) - (thumbsWrap.offsetWidth / 2) + (tw / 2);

          if (thumbsWrap.scrollTo) {
            thumbsWrap.scrollTo({ left: targetLeft, behavior: 'smooth' });
          } else {
            thumbsWrap.scrollLeft = targetLeft;
          }
        }
      });

      thumb.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          thumb.click();
        }
      });
    });

    root.addEventListener('click', function (e) {
      var marker = closest(e.target, '.wlb-marker[data-pid]');
      if (!marker || !root.contains(marker)) return;

      var pid = toInt(marker.getAttribute('data-pid'));
      if (!pid) return;

      var slide = closest(marker, '.carousel-slide') || slides[currentIndex];
      if (!slide) return;

      updatePanels(slide, pid);

      if (isMobile) {
        e.preventDefault();
        e.stopPropagation();

        var pmap = getMaps(slide).pmap || {};
        openModalWithProduct(pmap[String(pid)] || {});
      }
    }, true);

    function interceptHotspotTouch(e) {
      if (!isMobile) return;
      if (!root.contains(e.target)) return;
      if (closest(e.target, '.hs-modal')) return;
      if (closest(e.target, '[data-hs-nav]')) return;

      var marker = closest(e.target, '.wlb-marker[data-pid]');
      if (!marker) return;

      var pid = toInt(marker.getAttribute('data-pid'));
      if (!pid) return;

      var slide = closest(marker, '.carousel-slide') || slides[currentIndex];
      if (!slide) return;

      e.preventDefault();
      e.stopPropagation();
      if (e.stopImmediatePropagation) e.stopImmediatePropagation();

      updatePanels(slide, pid);

      var pmap = getMaps(slide).pmap || {};
      openModalWithProduct(pmap[String(pid)] || {});
    }

    try {
      document.addEventListener('touchstart', interceptHotspotTouch, { capture: true, passive: false });
    } catch (err) {
      document.addEventListener('touchstart', interceptHotspotTouch, true);
    }

    rebuildDots();
    setActiveUI();
  }

  function initAll(scope) {
    qsa(scope || document, '[data-hs-root]').forEach(initHotspots);
  }

  window.NLHotspotsInit = initHotspots;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initAll(document);
    });
  } else {
    initAll(document);
  }

  window.addEventListener('load', function () {
    initAll(document);
  });

  if ('MutationObserver' in window) {
    var mo = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (!node || node.nodeType !== 1) return;

          if (matches(node, '[data-hs-root]')) {
            initHotspots(node);
          } else if (node.querySelectorAll) {
            initAll(node);
          }
        });
      });
    });

    if (document.body) {
      mo.observe(document.body, { childList: true, subtree: true });
    } else {
      document.addEventListener('DOMContentLoaded', function () {
        mo.observe(document.body, { childList: true, subtree: true });
      });
    }
  }
})();