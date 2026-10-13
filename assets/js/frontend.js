/* Phagebiome Publications — per-instance live filtering (vanilla JS, no deps). */
(function () {
  'use strict';

  function txt(el) { return ((el.textContent || '').trim()); }

  function init(root) {
    if (root.dataset.pbpInit) return;
    root.dataset.pbpInit = '1';
    var form = root.querySelector('.pbp-search-form');
    var input = root.querySelector('.pbp-search input');
    var btns = Array.prototype.slice.call(root.querySelectorAll('.pbp-fbtn'));
    var rows = Array.prototype.slice.call(root.querySelectorAll('.pbp-row'));
    var count = root.querySelector('.pbp-count');
    var empty = root.querySelector('.pbp-empty');
    var reset = root.querySelector('.pbp-reset');
    if (!form || !input || !count) return;
    var total = rows.length;
    var year = 'all';
    var q = '';
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    // Pick up server-rendered initial state (no-JS fallback query).
    (function () {
      for (var i = 0; i < btns.length; i++) {
        if (btns[i].getAttribute('aria-pressed') === 'true' && btns[i].value) { year = btns[i].value; break; }
      }
      q = input.value.replace(/^\s+|\s+$/g, '').toLowerCase();
    })();

    root.classList.add('pbp-js');

    function paint() {
      btns.forEach(function (b) {
        var on = (b.value || 'all') === year;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
    }

    function render() {
      var n = 0;
      rows.forEach(function (r) {
        var hay = (r.getAttribute('data-search') || '').toLowerCase();
        var okY = 'all' === year || r.getAttribute('data-year') === year;
        var ok = okY && (!q || hay.indexOf(q) !== -1);
        r.hidden = !ok;
        if (ok) {
          n++;
          r.classList.add('is-in');
        }
      });
      count.textContent = 'Showing ' + n + ' of ' + total + ' publications';
      if (empty) empty.hidden = n !== 0;
    }

    var deb = null;
    input.addEventListener('input', function () {
      if (deb) clearTimeout(deb);
      deb = setTimeout(function () {
        q = input.value.replace(/^\s+|\s+$/g, '').toLowerCase();
        render();
      }, 180);
    });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      q = input.value.replace(/^\s+|\s+$/g, '').toLowerCase();
      render();
    });
    btns.forEach(function (b) {
      b.addEventListener('click', function (e) {
        e.preventDefault();
        year = b.value || 'all';
        paint();
        render();
      });
    });
    if (reset) {
      reset.addEventListener('click', function (e) {
        e.preventDefault();
        input.value = '';
        q = '';
        year = 'all';
        paint();
        render();
        input.focus();
      });
    }

    // Reveal-on-scroll for rows (progressive enhancement only).
    if (!reduceMotion && 'IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
        });
      }, { threshold: 0.1 });
      rows.forEach(function (r) { io.observe(r); });
    } else {
      rows.forEach(function (r) { r.classList.add('is-in'); });
    }
    paint();
    render();
  }

  function boot() {
    document.querySelectorAll('.phagebiome-publications').forEach(init);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
