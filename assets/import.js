/* isprithul core · Starter content: runs the import in short steps until it is done */
(function () {
  'use strict';
  var btn = document.querySelector('[data-isp-import]');
  if (!btn || !window.wp || !wp.apiFetch) return;
  var log = document.querySelector('[data-isp-log]'), bar = document.querySelector('[data-isp-progress]');
  var photos = document.querySelector('[data-isp-photos]'), projects = document.querySelector('[data-isp-projects]');
  function write(line) { log.hidden = false; log.textContent += line + '\n'; log.scrollTop = log.scrollHeight; }
  function step(lastDone, stalls) {
    wp.apiFetch({ path: '/isprithul/v1/import', method: 'POST' }).then(function (r) {
      (r.log || []).forEach(write);
      photos.textContent = r.photos; projects.textContent = r.projects; bar.value = r.photos;
      if (r.done) { write('Done. Your projects are under Projects, and the homepage shows them once the isprithul theme is active.'); btn.textContent = 'Everything is imported'; return; }
      var stalled = r.photos === lastDone && r.failed > 0;
      if (stalled && stalls >= 2) { write('Stopped: some photos keep failing (see above). Try again later, or add them by hand.'); btn.disabled = false; btn.textContent = 'Try again'; return; }
      step(r.photos, stalled ? stalls + 1 : 0);
    }).catch(function (err) {
      write('Error: ' + ((err && err.message) || 'the step did not finish') + '. Click the button to continue where it stopped.');
      btn.disabled = false; btn.textContent = 'Continue import';
    });
  }
  btn.addEventListener('click', function () {
    btn.disabled = true; btn.textContent = 'Importing… keep this tab open';
    write('Starting…');
    step(-1, 0);
  });
})();
