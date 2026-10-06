// app.js - sidebar, modals, confirm dialogs, edit-form filling, appointment details modal
(function () {
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const $ = (s, r) => (r || document).querySelector(s);

  // mobile sidebar
  const menuBtn = $('#menuBtn'), sidebar = $('#sidebar');
  if (menuBtn) menuBtn.addEventListener('click', () => sidebar.classList.toggle('open'));

  // modals
  function openModal(id) { const m = document.getElementById(id); if (m) m.classList.add('show'); return m; }
  function closeModals() { document.querySelectorAll('.modal.show').forEach(m => m.classList.remove('show')); }
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModals(); });

  document.addEventListener('click', function (e) {
    const closeBtn = e.target.closest('[data-modal-close]');
    if (closeBtn) { closeModals(); return; }
    if (e.target.classList && e.target.classList.contains('modal')) { closeModals(); return; }

    const opener = e.target.closest('[data-modal-open]');
    if (opener) {
      const m = openModal(opener.dataset.modalOpen);
      const form = m && $('form', m);
      if (form) {
        form.reset();
        const title = $('[data-title]', m);
        if (title && opener.dataset.title) title.textContent = opener.dataset.title;
        if (opener.dataset.fill) {
          const data = JSON.parse(opener.dataset.fill);
          Object.keys(data).forEach(k => {
            const f = form.elements[k];
            if (!f) return;
            if (f.type === 'checkbox') f.checked = !!+data[k]; else f.value = data[k] == null ? '' : data[k];
          });
        }
      }
      return;
    }

    const view = e.target.closest('[data-view-appt]');
    if (view) showAppointment(view.dataset.viewAppt);
  });

  // confirm dialog for forms with data-confirm
  let pendingForm = null;
  document.addEventListener('submit', function (e) {
    const f = e.target;
    if (!f.dataset || !f.dataset.confirm || f.dataset.ok) return;
    e.preventDefault();
    pendingForm = f;
    $('#confirmText').textContent = f.dataset.confirm;
    $('#confirmNote').value = '';
    $('#confirmNoteWrap').hidden = !f.dataset.note;
    openModal('confirmModal');
  });
  const ok = $('#confirmOk');
  if (ok) ok.addEventListener('click', function () {
    if (!pendingForm) return;
    const note = pendingForm.elements['note'];
    if (note) note.value = $('#confirmNote').value.trim();
    pendingForm.dataset.ok = '1';
    pendingForm.submit();
  });

  // appointment details modal
  async function showAppointment(id) {
    const body = $('#viewBody');
    body.textContent = 'Loading...';
    openModal('viewModal');
    try {
      const r = await fetch('api.php?action=appointment&id=' + encodeURIComponent(id));
      const d = await r.json();
      if (!d.ok) { body.innerHTML = '<div class="alert alert-error">' + esc(d.message) + '</div>'; return; }
      const a = d.appointment;
      let h = '<dl class="dl">'
        + '<dt>Reference No.</dt><dd><strong>' + esc(a.reference_no) + '</strong></dd>'
        + '<dt>Status</dt><dd><span class="badge badge-' + esc(a.status) + '">' + esc(a.status_label) + '</span></dd>'
        + '<dt>Course</dt><dd>' + esc(a.course) + '</dd>'
        + '<dt>Service</dt><dd>' + esc(a.service) + '</dd>'
        + '<dt>Date</dt><dd>' + esc(a.date) + '</dd>'
        + '<dt>Time</dt><dd>' + esc(a.time) + '</dd>'
        + '<dt>Notes</dt><dd>' + esc(a.notes || '—') + '</dd>';
      if (a.cancel_reason) h += '<dt>Cancel reason</dt><dd>' + esc(a.cancel_reason) + '</dd>';
      if (a.student) {
        h += '<dt>Student</dt><dd>' + esc(a.student.name) + '</dd>'
          + '<dt>Student ID</dt><dd>' + esc(a.student.student_id) + '</dd>'
          + '<dt>Email</dt><dd>' + esc(a.student.email) + '</dd>'
          + '<dt>Phone</dt><dd>' + esc(a.student.phone) + '</dd>';
      }
      h += '</dl><h3 style="margin-top:16px">History</h3>';
      h += a.history.map(x => '<div class="notif">' + esc(x.when) + ' — <strong>' + esc(x.status) + '</strong>' + (x.note ? ' · ' + esc(x.note) : '') + '</div>').join('');
      h += '<div class="modal-actions"><a class="btn btn-light" href="appointment_view.php?ref=' + encodeURIComponent(a.reference_no) + '">Open / Print</a><button class="btn btn-primary" data-modal-close>Close</button></div>';
      body.innerHTML = h;
    } catch (err) {
      body.innerHTML = '<div class="alert alert-error">Could not load the appointment.</div>';
    }
  }
})();
