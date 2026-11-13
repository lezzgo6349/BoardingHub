/* BoardingHub front-end helpers (vanilla JS) */

// ---- Pop-up confirmation for every major decision: add data-confirm="message" to a button, link or form.
(function () {
  const dlg = document.getElementById('bhConfirm');
  function ask(el) {
    return new Promise(res => {
      document.getElementById('bhConfirmTitle').textContent = el.dataset.confirmTitle || 'Please confirm';
      document.getElementById('bhConfirmMsg').textContent = el.dataset.confirm;
      const ok = document.getElementById('bhConfirmOk');
      ok.textContent = el.dataset.confirmBtn || 'Confirm';
      ok.className = 'btn ' + (el.dataset.confirmTone === 'danger' ? 'btn-danger' : 'btn-primary');
      dlg.returnValue = '';
      dlg.addEventListener('close', () => res(dlg.returnValue === 'ok'), { once: true });
      dlg.showModal();
    });
  }
  document.addEventListener('click', e => {
    const el = e.target.closest('[data-confirm]');
    if (!el || el.tagName === 'FORM') return;
    if (el.form && el.type === 'submit' && !el.form.reportValidity()) return;
    e.preventDefault();
    ask(el).then(ok => {
      if (!ok) return;
      if (el.tagName === 'A') location.href = el.href;
      else if (el.form) el.form.requestSubmit(el.type === 'submit' ? el : undefined);
    });
  });
  document.addEventListener('submit', e => {
    const f = e.target;
    if (!f.dataset.confirm || f.dataset.ok) return;
    e.preventDefault();
    ask(f).then(ok => { if (ok) { f.dataset.ok = '1'; f.requestSubmit(); } });
  });
})();

// ---- Maps (Leaflet + OpenStreetMap). No API key needed.
window.BH = {
  baseMap(id, center, zoom) {
    const map = L.map(id).setView(center || [12.88, 121.77], zoom || 5);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap contributors' }).addTo(map);
    return map;
  },
  viewMap(id, lat, lng, label) {
    const map = this.baseMap(id, [lat, lng], 16);
    L.marker([lat, lng]).addTo(map).bindPopup(label).openPopup();
  },
  pickMap(id, latInput, lngInput, searchInput, searchBtn, locateBtn) {
    const la = document.getElementById(latInput), ln = document.getElementById(lngInput);
    const has = la.value && ln.value;
    const map = this.baseMap(id, has ? [+la.value, +ln.value] : null, has ? 16 : 5);
    let marker = null;
    const put = (lat, lng, zoom) => {
      la.value = (+lat).toFixed(7); ln.value = (+lng).toFixed(7);
      if (marker) marker.setLatLng([lat, lng]);
      else { marker = L.marker([lat, lng], { draggable: true }).addTo(map); marker.on('dragend', () => { const p = marker.getLatLng(); put(p.lat, p.lng); }); }
      if (zoom) map.setView([lat, lng], zoom);
    };
    if (has) put(+la.value, +ln.value);
    map.on('click', e => put(e.latlng.lat, e.latlng.lng));
    document.getElementById(searchBtn).addEventListener('click', async () => {
      const q = document.getElementById(searchInput).value.trim(); if (!q) return;
      try {
        const r = await (await fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(q))).json();
        if (r[0]) put(+r[0].lat, +r[0].lon, 17); else alert('No place found. Try a nearby landmark, or click the map.');
      } catch (err) { alert('Search is unavailable right now. Click the map to set the pin.'); }
    });
    document.getElementById(searchInput).addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); document.getElementById(searchBtn).click(); } });
    document.getElementById(locateBtn).addEventListener('click', () => {
      if (!navigator.geolocation) return alert('Your browser cannot share its location.');
      navigator.geolocation.getCurrentPosition(p => put(p.coords.latitude, p.coords.longitude, 17), () => alert('Location permission was denied. Click the map instead.'));
    });
  }
};

// ---- Live table filter: <input data-filter="#tableId">
document.querySelectorAll('[data-filter]').forEach(inp => {
  inp.addEventListener('input', () => {
    const q = inp.value.toLowerCase();
    document.querySelectorAll(inp.dataset.filter + ' tbody tr').forEach(tr => { tr.hidden = !tr.textContent.toLowerCase().includes(q); });
  });
});

// ---- Receipt PDF download (html2pdf.js)
const pdfBtn = document.getElementById('downloadPdf');
if (pdfBtn) pdfBtn.addEventListener('click', () => {
  const el = document.getElementById('receiptSheet');
  html2pdf().set({ margin: 10, filename: pdfBtn.dataset.name || 'receipt.pdf', image: { type: 'jpeg', quality: 0.95 }, html2canvas: { scale: 2, useCORS: true }, jsPDF: { unit: 'mm', format: 'a4' } }).from(el).save();
});

// ---- Password fields: eye button to show / hide the text
document.querySelectorAll('input[type="password"]').forEach(inp => {
  const wrap = document.createElement('div');
  wrap.className = 'pw-wrap';
  inp.parentNode.insertBefore(wrap, inp);
  wrap.appendChild(inp);
  inp.classList.add('pw-input');
  const btn = document.createElement('button');
  btn.type = 'button'; btn.className = 'pw-eye'; btn.setAttribute('aria-label', 'Show password'); btn.setAttribute('aria-pressed', 'false');
  btn.innerHTML = '<i class="bi bi-eye"></i>';
  btn.addEventListener('click', () => {
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    btn.firstChild.className = 'bi ' + (show ? 'bi-eye-slash' : 'bi-eye');
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
  });
  wrap.appendChild(btn);
});

// ---- Email fields: "juan" becomes "juan@gmail.com" when you leave the field or submit
document.querySelectorAll('input[type="email"]').forEach(inp => {
  if (!inp.placeholder) inp.placeholder = 'yourname@gmail.com';
  const fix = () => { const v = inp.value.trim(); if (v && !v.includes('@')) inp.value = v + '@gmail.com'; };
  inp.addEventListener('blur', fix);
  if (inp.form) inp.form.addEventListener('submit', fix, true);
  if (inp.form) inp.form.addEventListener('click', e => { if (e.target.closest('button:not([type="button"])')) fix(); }, true);
});

// ---- Mobile numbers: digits only, 11 max (09XXXXXXXXX)
document.querySelectorAll('input[data-mobile]').forEach(inp => {
  inp.addEventListener('input', () => { inp.value = inp.value.replace(/\D/g, '').slice(0, 11); });
});

// ---- Live search over cards: <input data-filter-items="#listId"> filters the .filter-item children
document.querySelectorAll('[data-filter-items]').forEach(inp => {
  const list = document.querySelector(inp.dataset.filterItems);
  if (!list) return;
  const empty = document.getElementById(list.id + 'Empty');
  inp.addEventListener('input', () => {
    const q = inp.value.trim().toLowerCase(); let shown = 0;
    list.querySelectorAll('.filter-item').forEach(it => { const hit = !q || it.textContent.toLowerCase().includes(q); it.hidden = !hit; if (hit) shown++; });
    if (empty) empty.hidden = shown > 0;
  });
});
