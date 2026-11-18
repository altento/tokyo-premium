(() => {
  const nav = document.querySelector('.admin-jump-nav');
  const search = document.getElementById('admin-search-q');
  if (!nav) return;

  const links = [...nav.querySelectorAll('a[href^="#"]')];
  const sections = links
    .map((link) => document.getElementById(decodeURIComponent(link.hash.slice(1))))
    .filter(Boolean);

  const setCurrentSection = (id) => {
    for (const link of links) {
      if (link.hash === `#${id}`) link.setAttribute('aria-current', 'location');
      else link.removeAttribute('aria-current');
    }
  };

  if (location.hash) setCurrentSection(decodeURIComponent(location.hash.slice(1)));

  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      const current = entries
        .filter((entry) => entry.isIntersecting)
        .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
      if (current) setCurrentSection(current.target.id);
    }, { rootMargin: '-155px 0px -62% 0px', threshold: [0, 0.1, 0.35, 0.6] });

    for (const section of sections) observer.observe(section);
  }

  document.addEventListener('keydown', (event) => {
    if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey || event.isComposing) return;
    const target = event.target;
    if (target instanceof HTMLElement && (target.isContentEditable || target.closest('input, textarea, select, [contenteditable="true"]'))) return;
    if (!search) return;
    event.preventDefault();
    search.focus({ preventScroll: true });
    search.scrollIntoView({ block: 'center' });
  });
})();
