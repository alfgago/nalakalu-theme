document.documentElement.classList.add('js');

document.addEventListener('DOMContentLoaded', function () {
  const blocks = document.querySelectorAll('.animated_container');
  if (!blocks.length) return;

  const observer = new IntersectionObserver((entries, obs) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;

      entry.target.classList.add('is-visible');
      obs.unobserve(entry.target);
    });
  }, {
    threshold: 0.22,
    rootMargin: '0px 0px -8% 0px'
  });

  blocks.forEach((block) => {
    block.classList.add('js-reveal-ready');
    observer.observe(block);
  });
});