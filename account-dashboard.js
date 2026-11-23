(() => {
  const navigation = document.querySelector('.account-dashboard-nav');
  if (!navigation) return;

  const links = [...navigation.querySelectorAll('a[href^="#"]')];
  const sections = links
    .map(link => document.getElementById(decodeURIComponent(link.hash.slice(1))))
    .filter(Boolean);
  if (!sections.length) return;

  const revealTarget = id => {
    const target = document.getElementById(id);
    if (target instanceof HTMLDetailsElement) target.open = true;
  };

  const setCurrentSection = id => {
    links.forEach(link => {
      if (link.hash === `#${id}`) link.setAttribute('aria-current', 'location');
      else link.removeAttribute('aria-current');
    });
  };

  let scheduled = false;
  const updateCurrentSection = () => {
    scheduled = false;
    const activationLine = Math.min(220, Math.round(window.innerHeight * 0.22));
    const current = sections.find(section => {
      const bounds = section.getBoundingClientRect();
      return bounds.top <= activationLine && bounds.bottom > activationLine;
    }) || sections.find(section => section.getBoundingClientRect().top > activationLine) || sections.at(-1);
    if (current) setCurrentSection(current.id);
  };

  const scheduleUpdate = () => {
    if (scheduled) return;
    scheduled = true;
    window.requestAnimationFrame(updateCurrentSection);
  };

  navigation.addEventListener('click', event => {
    const link = event.target.closest('a[href^="#"]');
    if (!link) return;
    const section = document.getElementById(decodeURIComponent(link.hash.slice(1)));
    if (section) {
      revealTarget(section.id);
      setCurrentSection(section.id);
    }
  });
  window.addEventListener('scroll', scheduleUpdate, { passive: true });
  window.addEventListener('resize', scheduleUpdate, { passive: true });
  window.addEventListener('hashchange', () => {
    if (window.location.hash) revealTarget(decodeURIComponent(window.location.hash.slice(1)));
    scheduleUpdate();
  });
  if (window.location.hash) revealTarget(decodeURIComponent(window.location.hash.slice(1)));
  updateCurrentSection();
})();
