// HypeBang CRM
(function () {
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));
  const api = (data) => fetch('index.php?p=api', {
    method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': window.CSRF }, body: JSON.stringify(data)
  }).then(r => { if (!r.ok) throw new Error(); return r.json(); });
  const toNum = (v) => {
    v = String(v || '').replace(/[€\s]/g, '');
    if (v.includes(',')) v = v.replace(/\./g, '').replace(',', '.');
    else if (/^-?\d{1,3}(\.\d{3})+$/.test(v)) v = v.replace(/\./g, '');
    const n = parseFloat(v); return isNaN(n) ? 0 : n;
  };
  const eur = (n) => '€ ' + n.toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const toast = (msg) => {
    const d = document.createElement('div'); d.className = 'flash flash-err'; d.textContent = msg;
    d.style.cssText = 'position:fixed;bottom:16px;left:50%;transform:translateX(-50%);z-index:99;box-shadow:0 8px 24px rgba(0,0,0,.15)';
    document.body.appendChild(d); setTimeout(() => d.remove(), 3500);
  };

  // QR per la verifica in due passaggi (generato qui, nessun servizio esterno)
  const qr = $('#qr');
  if (qr && window.qrcode) { const q = window.qrcode(0, 'M'); q.addData(qr.dataset.uri); q.make(); qr.innerHTML = q.createSvgTag({ cellSize: 4, margin: 2 }); }


  // Selezione multipla: caselle, "seleziona tutti" e barra delle azioni
  $$('[data-bulk]').forEach(scope => {
    const bar = $('.bulk-bar', scope);
    if (!bar) return;
    const sels = () => $$('.sel', scope).filter(c => {
      if (c.classList.contains('sel-card') && !scope.classList.contains('selecting')) return false;
      const g = c.closest('details.task-group'); return !(g && !g.open); // niente elementi in gruppi chiusi
    });
    const update = () => {
      const all = sels(), on = all.filter(c => c.checked);
      $('.bulk-count b', bar).textContent = new Set(on.map(c => c.value)).size;
      bar.hidden = on.length === 0 && !scope.classList.contains('selecting');
      $$('.sel-all', scope).forEach(a => { a.checked = all.length > 0 && on.length === all.length; a.indeterminate = on.length > 0 && on.length < all.length; });
      $$('.sel', scope).forEach(c => { const row = c.closest('tr,.kcard,.proj-card,.task,.cal-i'); if (row) row.classList.toggle('is-selected', c.checked); });
      document.body.classList.toggle('has-bulk', !bar.hidden);
    };
    scope.addEventListener('change', (e) => {
      if (e.target.classList.contains('sel-all')) { const v = e.target.checked; sels().forEach(c => c.checked = v); }
      if (e.target.classList.contains('sel')) $$('.sel', scope).forEach(c => { if (c.value === e.target.value) c.checked = e.target.checked; }); // stesse ripetizioni
      if (e.target.matches('.sel, .sel-all')) update();
    });
    // in modalità selezione, un clic sulla scheda la seleziona invece di aprirla
    scope.addEventListener('click', (e) => {
      if (!scope.classList.contains('selecting')) return;
      if (e.target.closest('.cal-other, .cal-n')) { e.preventDefault(); e.stopPropagation(); return; } // in selezione si scelgono solo gli appuntamenti
      const card = e.target.closest('.kcard, .proj-card, .task, .cal-ev');
      if (!card || e.target.closest('select, .bulk-bar')) return;
      if (e.target.closest('.sel')) { e.stopPropagation(); return; }
      e.preventDefault(); e.stopPropagation();
      const c = $('.sel', card); if (c) { c.checked = !c.checked; $$('.sel', scope).forEach(x => { if (x.value === c.value) x.checked = c.checked; }); update(); }
    }, true);
    const setMode = (on) => {
      scope.classList.toggle('selecting', on);
      $$('.kcard', scope).forEach(k => k.draggable = !on);
      $$('.sel-toggle').forEach(b => {
        b.classList.toggle('btn-primary', on);
        const s = $('span', b); if (!s) return;
        if (!b.dataset.label) b.dataset.label = s.textContent;
        s.textContent = on ? 'Fine' : b.dataset.label;
      });
      if (!on) $$('.sel', scope).forEach(c => c.checked = false);
      update();
    };
    $$('.sel-toggle').forEach(b => b.addEventListener('click', () => setMode(!scope.classList.contains('selecting'))));
    $('.bulk-cancel', bar).addEventListener('click', () => { $$('.sel', scope).forEach(c => c.checked = false); setMode(false); });
    // invio: aggiunge gli id scelti e chiede conferma
    let clicked = null;
    $$('button[name=op]', bar).forEach(b => b.addEventListener('click', () => clicked = b));
    bar.addEventListener('submit', async (e) => {
      if (bar.dataset.ok) { delete bar.dataset.ok; return; } // confermato: invio vero
      e.preventDefault();
      const ids = [...new Set($$('.sel', scope).filter(c => c.checked).map(c => c.value))];
      if (!ids.length) { await chiedi('Non hai selezionato niente: tocca prima gli elementi da scegliere.', { soloOk: true }); return; }
      const b = e.submitter || clicked;
      if (b && b.value === 'sposta' && !$('.bulk-date', bar).value && !await chiedi('Nessuna data scelta: i task resteranno senza scadenza. Continuare?')) return;
      if (b && b.dataset.confirm && !await chiedi(b.dataset.confirm.replace('{n}', ids.length))) return;
      if (b && b.dataset.typeConfirm && !await chiedi('Per sicurezza scrivi ' + b.dataset.typeConfirm + ' (in maiuscolo) qui sotto.', { campo: b.dataset.typeConfirm })) return;
      $$('input[name="ids[]"]', bar).forEach(i => i.remove());
      ids.forEach(id => { const i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = id; bar.appendChild(i); });
      bar.dataset.ok = '1';
      bar.requestSubmit(b || undefined);
    });
    update();
  });

  // Finestra di conferma disegnata da Pia (il browser non la può bloccare, al contrario di confirm())
  const chiedi = (msg, opt = {}) => new Promise((resolve) => {
    const wrap = document.createElement('div');
    wrap.className = 'dlg-wrap';
    const pericolo = /elimin|togli|disattiv/i.test(msg) || opt.campo;
    wrap.innerHTML = '<div class="dlg" role="dialog" aria-modal="true"><p></p>' +
      (opt.campo ? '<input class="dlg-input" autocomplete="off" spellcheck="false">' : '') +
      '<div class="dlg-btns">' + (opt.soloOk ? '' : '<button type="button" class="btn btn-ghost dlg-no">Annulla</button>') +
      '<button type="button" class="btn ' + (pericolo && !opt.soloOk ? 'btn-danger' : 'btn-primary') + ' dlg-si">' + (opt.soloOk ? 'Ok' : (pericolo ? 'Sì, procedi' : 'Conferma')) + '</button></div></div>';
    $('p', wrap).textContent = msg;
    document.body.appendChild(wrap);
    const inp = $('.dlg-input', wrap), si = $('.dlg-si', wrap);
    const chiudi = (v) => { wrap.remove(); document.removeEventListener('keydown', onKey, true); resolve(v); };
    const ok = () => { if (inp && inp.value.trim() !== opt.campo) { inp.classList.add('err'); inp.focus(); return; } chiudi(true); };
    const onKey = (e) => { if (e.key === 'Escape') { e.preventDefault(); chiudi(false); } if (e.key === 'Enter') { e.preventDefault(); ok(); } };
    document.addEventListener('keydown', onKey, true);
    si.addEventListener('click', ok);
    const no = $('.dlg-no', wrap); if (no) no.addEventListener('click', () => chiudi(false));
    wrap.addEventListener('click', (e) => { if (e.target === wrap) chiudi(false); });
    (inp || si).focus();
  });
  window.chiedi = chiedi;

  // Pulsanti e moduli con conferma (data-conferma="testo")
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('button[data-conferma]');
    if (!b || b.dataset.ok) return;
    e.preventDefault(); e.stopPropagation();
    if (await chiedi(b.dataset.conferma)) {
      b.dataset.ok = '1';
      if (b.form && b.form.requestSubmit) b.form.requestSubmit(b); else b.click();
    }
  }, true);
  document.addEventListener('submit', async (e) => {
    const f = e.target;
    if (!f.matches('form[data-conferma]') || f.dataset.ok) return;
    e.preventDefault();
    if (await chiedi(f.dataset.conferma)) { f.dataset.ok = '1'; e.submitter ? f.requestSubmit(e.submitter) : f.requestSubmit(); }
  }, true);

  // Righe cliccabili
  document.addEventListener('click', (e) => {
    const tr = e.target.closest('.row-link');
    if (tr && !e.target.closest('a,button,input,select,form,.sel-cell')) location.href = tr.dataset.href;
    // chiudi menu aperti
    $$('details.menu[open]').forEach(m => { if (!m.contains(e.target)) m.open = false; });
  });

  // Copia al clic (P.IVA, SDI…)
  $$('.copyable').forEach(el => el.addEventListener('click', () => {
    navigator.clipboard && navigator.clipboard.writeText(el.textContent.trim()).then(() => {
      const o = el.textContent; el.textContent = 'Copiato ✓'; setTimeout(() => el.textContent = o, 900);
    });
  }));

  // Spunta task
  $$('.task-check').forEach(cb => cb.addEventListener('change', () => {
    const row = cb.closest('.task');
    row.classList.toggle('done', cb.checked);
    api({ azione: 'spunta_task', id: cb.dataset.id, fatto: cb.checked }).catch(() => { cb.checked = !cb.checked; row.classList.toggle('done', cb.checked); toast('Non salvato: controlla la connessione.'); });
  }));

  // Bacheche trascinabili
  $$('[data-board]').forEach(board => {
    const kind = board.dataset.board;
    const save = (card, col) => {
      const ids = $$('.kcard', col).map(c => c.dataset.id);
      const payload = kind === 'task'
        ? { azione: 'sposta_task', id: card.dataset.id, stato: col.dataset.col, ordine: ids }
        : { azione: 'sposta_trattativa', id: card.dataset.id, fase: col.dataset.col, ordine: ids };
      const sel = $('.move-select', card); if (sel) sel.value = col.dataset.col;
      updateCounts();
      return api(payload).catch(() => { toast('Non salvato: ricarica la pagina.'); });
    };
    const updateCounts = () => $$('.col', board).forEach(col => {
      const s = $('.col-head span', col); const n = $$('.kcard', col).length;
      if (s) s.textContent = s.textContent.replace(/^\d+/, n);
    });
    let dragged = null;
    $$('.kcard', board).forEach(card => {
      card.addEventListener('dragstart', (e) => { dragged = card; card.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', card.dataset.id); });
      card.addEventListener('dragend', () => { card.classList.remove('dragging'); $$('.col', board).forEach(c => c.classList.remove('drag-over')); });
      const sel = $('.move-select', card);
      if (sel) sel.addEventListener('change', () => {
        const col = $(`.col[data-col="${sel.value}"]`, board);
        if (!col) return;
        $('.col-body', col).prepend(card);
        save(card, col);
        card.scrollIntoView({ behavior: 'smooth', inline: 'start', block: 'nearest' });
      });
    });
    $$('.col', board).forEach(col => {
      const body = $('.col-body', col);
      col.addEventListener('dragover', (e) => {
        if (!dragged) return; e.preventDefault(); col.classList.add('drag-over');
        const after = $$('.kcard:not(.dragging)', body).find(c => e.clientY < c.getBoundingClientRect().top + c.offsetHeight / 2);
        after ? body.insertBefore(dragged, after) : body.appendChild(dragged);
      });
      col.addEventListener('dragleave', (e) => { if (!col.contains(e.relatedTarget)) col.classList.remove('drag-over'); });
      col.addEventListener('drop', (e) => { e.preventDefault(); col.classList.remove('drag-over'); if (dragged) save(dragged, col); dragged = null; });
    });
  });

  // Trattativa: probabilità automatica dalla fase
  const fase = $('select[data-prob]');
  if (fase) {
    const prob = $('input[name=probabilita]');
    const persa = $('.if-persa');
    const upd = (first) => { if (!first && window.FASI_PROB) prob.value = window.FASI_PROB[fase.value]; if (persa) persa.style.display = fase.value === 'persa' ? '' : 'none'; };
    fase.addEventListener('change', () => upd(false)); upd(true);
  }

  // Appuntamento: campi in base al tipo e alla ripetizione
  const evt = $('#ev-tipo'), evr = $('#ev-ripeti');
  if (evt) {
    const upd = () => {
      $$('.if-lavoro').forEach(el => el.style.display = evt.value === 'personale' ? 'none' : '');
      if (evr) $$('.if-ripeti').forEach(el => el.style.display = evr.value === 'no' ? 'none' : '');
    };
    evt.addEventListener('change', upd); if (evr) evr.addEventListener('change', upd); upd();
  }

  // Preventivo: righe e totali
  const lines = $('#lines');
  if (lines) {
    let idx = $$('.line:not(.line-head)', lines).length + 100;
    const calc = () => {
      let sub = 0;
      $$('.line:not(.line-head)', lines).forEach(l => {
        const t = toNum($('.q', l).value) * toNum($('.pz', l).value); sub += t;
        $('.line-tot', l).textContent = eur(t);
      });
      const imp = Math.max(0, sub - toNum($('#sconto').value));
      const iva = Math.round(imp * toNum($('#iva').value)) / 100;
      $('#t-sub').textContent = eur(sub); $('#t-imp').textContent = eur(imp);
      $('#t-iva').textContent = eur(iva); $('#t-tot').textContent = eur(imp + iva);
    };
    const addLine = (desc = '', price = '') => {
      const tpl = $('.line:not(.line-head)', lines).cloneNode(true); idx++;
      $$('[name]', tpl).forEach(i => { i.name = i.name.replace(/righe\[\d+\]/, `righe[${idx}]`); });
      $('textarea', tpl).value = desc; $('.q', tpl).value = '1'; $('.pz', tpl).value = price;
      lines.appendChild(tpl); $('textarea', tpl).focus(); calc();
    };
    const grow = (t) => { t.style.height = 'auto'; t.style.height = (t.scrollHeight + 2) + 'px'; };
    $$('textarea', lines).forEach(grow);
    lines.addEventListener('input', (e) => { if (e.target.tagName === 'TEXTAREA') grow(e.target); calc(); });
    lines.addEventListener('click', (e) => {
      const rm = e.target.closest('.rm'); if (!rm) return;
      const all = $$('.line:not(.line-head)', lines);
      if (all.length > 1) rm.closest('.line').remove(); else { $$('textarea,input', all[0]).forEach(i => i.value = ''); }
      calc();
    });
    $('#add-line').addEventListener('click', () => addLine());
    const lst = $('#listino');
    if (lst) lst.addEventListener('change', () => {
      const o = lst.selectedOptions[0]; if (!o.value) return;
      const empty = $$('.line:not(.line-head)', lines).find(l => !$('textarea', l).value.trim());
      if (empty) { $('textarea', empty).value = o.value; $('.pz', empty).value = String(o.dataset.p).replace('.', ','); $('.q', empty).value = '1'; calc(); }
      else addLine(o.value, String(o.dataset.p).replace('.', ','));
      lst.value = '';
    });
    $('#sconto').addEventListener('input', calc); $('#iva').addEventListener('input', calc);
    calc();
  }

  // Fattura: calcolo totale
  const fc = $('#fatt-calc');
  if (fc) {
    const imp = $('input[name=imponibile]'), iva = $('input[name=iva_percentuale]'), rit = $('input[name=ritenuta_percentuale]');
    const upd = () => {
      const i = toNum(imp.value); if (!i) { fc.textContent = ''; return; }
      const v = i * toNum(iva.value) / 100, r = i * toNum(rit.value) / 100;
      fc.textContent = `IVA ${eur(v)}` + (r ? ` · ritenuta −${eur(r)}` : '') + ` · da ricevere ${eur(i + v - r)}`;
    };
    $$('[data-calc]').forEach(i => i.addEventListener('input', upd)); upd();
  }

  // Avviso se si lascia un modulo modificato senza salvare
  $$('form.form').forEach(f => {
    let dirty = false;
    f.addEventListener('input', () => dirty = true);
    f.addEventListener('submit', () => dirty = false);
    window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  });
})();
