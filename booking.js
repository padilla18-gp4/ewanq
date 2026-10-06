// booking.js - multi-step DFA-style booking (and reschedule) flow using AJAX
(function () {
  const cfg = window.BOOK;
  const resched = cfg.mode === 'reschedule';
  const state = { course: cfg.course || null, service: cfg.service || null, date: null, slot: null };
  const $ = s => document.querySelector(s);
  const el = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; };

  async function get(params) {
    const r = await fetch('api.php?' + new URLSearchParams(params));
    return r.json();
  }
  async function post(data) {
    const fd = new FormData();
    Object.keys(data).forEach(k => fd.append(k, data[k]));
    fd.append('csrf_token', cfg.csrf);
    const r = await fetch('api.php', { method: 'POST', body: fd });
    return r.json();
  }

  function goStep(n) {
    document.querySelectorAll('.step-panel').forEach(p => p.hidden = +p.dataset.step !== n);
    document.querySelectorAll('.stepper .step').forEach(s => {
      const i = +s.dataset.step;
      s.classList.toggle('active', i === n);
      s.classList.toggle('done', i < n);
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function showMsg(step, text, type) {
    const box = $('.step-panel[data-step="' + step + '"] .msg');
    box.className = 'msg' + (text ? ' alert alert-' + (type || 'error') : '');
    box.textContent = text || '';
  }

  function fill(step, items, build, emptyText) {
    const wrap = $('.step-panel[data-step="' + step + '"] .list');
    wrap.innerHTML = '';
    showMsg(step, '');
    if (!items.length) { showMsg(step, emptyText, 'info'); return; }
    items.forEach(it => wrap.appendChild(build(it)));
  }

  function mark(btn) {
    btn.parentElement.querySelectorAll('.choice').forEach(b => b.classList.remove('sel'));
    btn.classList.add('sel');
  }

  // STEP 1: courses
  async function loadCourses() {
    const d = await get({ action: 'courses' });
    fill(1, d.courses || [], c => {
      const b = el('button', 'choice'); b.type = 'button';
      b.append(el('strong', '', c.name), el('small', '', c.code));
      b.onclick = () => { mark(b); state.course = c; state.service = state.date = state.slot = null; goStep(2); loadServices(); };
      return b;
    }, 'No courses are available right now.');
  }

  // STEP 2: services
  async function loadServices() {
    $('#pickedCourse').textContent = state.course.name;
    $('.step-panel[data-step="2"] .list').innerHTML = 'Loading...';
    const d = await get({ action: 'services', course_id: state.course.id });
    fill(2, d.services || [], s => {
      const b = el('button', 'choice'); b.type = 'button';
      const n = +s.available_dates;
      b.append(el('strong', '', s.name), el('small', '', s.description || ''),
               el('small', n ? '' : 'tag-full', n ? n + ' date(s) available' : 'No available schedules'));
      if (!n) b.disabled = true;
      b.onclick = () => { mark(b); state.service = s; state.date = state.slot = null; goStep(3); loadDates(); };
      return b;
    }, 'This service is currently unavailable.');
  }

  // STEP 3: dates
  async function loadDates() {
    const c = state.course.id, s = state.service.id;
    $('#pickedService').textContent = state.service.name;
    $('.step-panel[data-step="3"] .list').innerHTML = 'Loading...';
    const d = await get({ action: 'dates', course_id: c, service_id: s });
    fill(3, d.dates || [], x => {
      const b = el('button', 'choice'); b.type = 'button';
      b.append(el('strong', '', x.label), el('small', '', x.weekday),
               el('small', x.remaining ? '' : 'tag-full', x.remaining ? x.remaining + ' slot(s) left' : 'FULL'));
      if (!x.remaining) b.disabled = true;
      b.onclick = () => { mark(b); state.date = x; state.slot = null; goStep(4); loadSlots(); };
      return b;
    }, 'No available schedules for this service.');
  }

  // STEP 4: time slots
  async function loadSlots() {
    $('#pickedDate').textContent = state.date.label;
    $('.step-panel[data-step="4"] .list').innerHTML = 'Loading...';
    const d = await get({ action: 'slots', course_id: state.course.id, service_id: state.service.id, date: state.date.date });
    fill(4, d.slots || [], t => {
      const b = el('button', 'choice'); b.type = 'button';
      let status = t.remaining + ' of ' + t.capacity + ' left';
      let cls = '';
      if (t.mine) { status = 'Already booked by you'; cls = 'tag-full'; b.disabled = true; }
      else if (t.full) { status = 'FULL'; cls = 'tag-full'; b.disabled = true; }
      b.append(el('strong', '', t.label + ' — ' + (t.full ? 'FULL' : 'Available')), el('small', cls, status));
      b.onclick = () => { mark(b); state.slot = t; showReview(); };
      return b;
    }, 'No available time slots for this date.');
  }

  function summary(targetId) {
    const t = $(targetId);
    const rows = [['Course', state.course.name], ['Service', state.service.name], ['Date', state.date.label], ['Time', state.slot.label]];
    if (!resched) rows.push(['Student', cfg.studentName + ' (' + cfg.studentId + ')']);
    if (resched) rows.push(['Current schedule', cfg.current]);
    if (!resched && $('#notes').value.trim()) rows.push(['Notes', $('#notes').value.trim()]);
    t.innerHTML = '';
    rows.forEach(r => { t.append(el('dt', '', r[0]), el('dd', '', r[1])); });
  }

  function showReview() {
    if (resched) { summary('#finalSummary'); goStep(6); return; }
    summary('#reviewSummary');
    goStep(5);
  }

  async function confirm() {
    const btn = $('#confirmBtn');
    btn.disabled = true; btn.textContent = 'Saving...';
    try {
      const data = resched
        ? { action: 'reschedule', appointment_id: cfg.apptId, slot_id: state.slot.id }
        : { action: 'book', course_id: state.course.id, service_id: state.service.id, slot_id: state.slot.id, notes: $('#notes').value.trim() };
      const d = await post(data);
      if (d.ok) {
        window.location.href = resched ? 'my_appointments.php' : 'appointment_view.php?ref=' + encodeURIComponent(d.ref) + '&new=1';
        return;
      }
      showMsg(6, d.message, 'error');
      btn.disabled = false; btn.textContent = resched ? 'Confirm Reschedule' : 'Confirm Appointment';
    } catch (e) {
      showMsg(6, 'Network error. Please try again.', 'error');
      btn.disabled = false; btn.textContent = resched ? 'Confirm Reschedule' : 'Confirm Appointment';
    }
  }

  // wire buttons
  document.querySelectorAll('[data-goto]').forEach(b => b.addEventListener('click', () => {
    const n = +b.dataset.goto;
    if (n === 6 && !$('#agree').checked && !resched) { }
    if (n === 6) summary('#finalSummary');
    goStep(n);
  }));
  $('#confirmBtn').addEventListener('click', function () {
    if (!resched && !$('#agree').checked) { showMsg(6, 'Please tick the box to confirm your information is correct.', 'error'); return; }
    confirm();
  });
  const back6 = $('#back6');
  if (back6) back6.addEventListener('click', () => goStep(resched ? 4 : 5));

  if (resched) { goStep(3); loadDates(); } else { goStep(1); loadCourses(); }
})();
