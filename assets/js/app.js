/* ============================================
   SCHEDULIN — Main Application JavaScript
   ============================================ */

'use strict';

const DAYS = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const COLORS = ['#3b82f6','#8b5cf6','#10b981','#f59e0b','#ef4444','#ec4899','#06b6d4','#84cc16','#f97316','#6366f1','#14b8a6','#f43f5e'];

let STATE = {
    matkul: [],        // [{id, nama, kelas, sks, dosen, hari, jam_mulai, jam_selesai, warna}]
    schedule: {},      // {Senin: [id,...], Selasa: [...], ..., unscheduled: [...]}
    dragData: null,
    colorIdx: 0,
    jadwalId: null,
};

/* ============ Utilities ============ */
function uid() { return '_' + Math.random().toString(36).substr(2, 9); }

function timeToMin(t) {
    if (!t) return 0;
    const parts = t.split(':');
    return parseInt(parts[0]) * 60 + parseInt(parts[1]);
}

function conflictsWithDay(id, day) {
    const m = STATE.matkul.find(x => x.id === id);
    if (!m) return false;
    return (STATE.schedule[day] || [])
        .filter(cid => cid !== id)
        .some(cid => {
            const o = STATE.matkul.find(x => x.id === cid);
            if (!o) return false;
            return timeToMin(m.jam_mulai) < timeToMin(o.jam_selesai) &&
                   timeToMin(o.jam_mulai) < timeToMin(m.jam_selesai);
        });
}

function countConflicts() {
    let count = 0;
    DAYS.forEach(day => {
        (STATE.schedule[day] || []).forEach(id => {
            if (conflictsWithDay(id, day)) count++;
        });
    });
    return Math.ceil(count / 2);
}

function totalSKS() {
    return STATE.matkul.reduce((a, m) => a + (parseInt(m.sks) || 0), 0);
}

function findDayOfId(id) {
    for (const day of [...DAYS, 'unscheduled']) {
        if ((STATE.schedule[day] || []).includes(id)) return day;
    }
    return null;
}

/* ============ State Mutators ============ */
function addToSchedule(id, day) {
    if (!STATE.schedule[day]) STATE.schedule[day] = [];
    if (!STATE.schedule[day].includes(id)) STATE.schedule[day].push(id);
}

function removeFromSchedule(id) {
    Object.keys(STATE.schedule).forEach(day => {
        STATE.schedule[day] = (STATE.schedule[day] || []).filter(x => x !== id);
    });
}

/* ============ CRUD Matkul ============ */
function addMatkul(data = null) {
    let nama, kelas, sks, dosen, hari, jam_mulai, jam_selesai;
    if (data) {
        ({ nama, kelas, sks, dosen, hari, jam_mulai, jam_selesai } = data);
    } else {
        nama      = document.getElementById('inp-nama').value.trim();
        kelas     = document.getElementById('inp-kelas').value.trim();
        sks       = document.getElementById('inp-sks').value || '3';
        dosen     = document.getElementById('inp-dosen').value.trim();
        hari      = document.getElementById('inp-hari').value;
        jam_mulai = document.getElementById('inp-mulai').value;
        jam_selesai = document.getElementById('inp-selesai').value;
    }

    if (!nama || !kelas || !jam_mulai || !jam_selesai) {
        toast('Nama matkul, kelas, dan jam wajib diisi!', 'error'); return;
    }
    if (timeToMin(jam_mulai) >= timeToMin(jam_selesai)) {
        toast('Jam mulai harus sebelum jam selesai!', 'error'); return;
    }

    const m = {
        id: data?.id || uid(),
        nama, kelas,
        sks: parseInt(sks) || 3,
        dosen: dosen || '',
        hari: hari || '',
        jam_mulai, jam_selesai,
        warna: data?.warna || COLORS[STATE.colorIdx % COLORS.length],
        saved: false,
    };
    STATE.colorIdx++;
    STATE.matkul.push(m);

    const targetDay = DAYS.includes(m.hari) ? m.hari : 'unscheduled';
    addToSchedule(m.id, targetDay);

    if (!data) clearForm();
    renderAll();
    saveToServer(m);
    toast(`${nama} (${kelas}) ditambahkan!`, 'success');
}

function deleteMatkul(id) {
    const m = STATE.matkul.find(x => x.id === id);
    if (!m) return;
    STATE.matkul = STATE.matkul.filter(x => x.id !== id);
    removeFromSchedule(id);
    renderAll();
    deleteFromServer(id);
    toast(`${m.nama} dihapus`, 'info');
}

function moveMatkul(id, toDay) {
    const fromDay = findDayOfId(id);
    if (fromDay === toDay) return;
    removeFromSchedule(id);
    addToSchedule(id, toDay);

    if (DAYS.includes(toDay)) {
        const m = STATE.matkul.find(x => x.id === id);
        if (m && conflictsWithDay(id, toDay)) {
            const rival = (STATE.schedule[toDay] || [])
                .filter(cid => cid !== id)
                .map(cid => STATE.matkul.find(x => x.id === cid))
                .find(o => o && timeToMin(m.jam_mulai) < timeToMin(o.jam_selesai) && timeToMin(o.jam_mulai) < timeToMin(m.jam_selesai));
            toast(`⚠ Konflik! ${m.nama} bertabrakan dengan ${rival ? rival.nama : 'matkul lain'}`, 'error');
        }
    }
    renderAll();
    updateOnServer(id, toDay);
}

function clearForm() {
    ['inp-nama','inp-kelas','inp-dosen'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('inp-sks').value = '3';
    document.getElementById('inp-hari').value = '';
    document.getElementById('inp-mulai').value = '08:00';
    document.getElementById('inp-selesai').value = '09:40';
}

/* ============ Auto-Schedule (Greedy) ============ */
function autoSchedule() {
    if (!STATE.matkul.length) { toast('Belum ada matkul!', 'error'); return; }

    const unplaced = STATE.matkul.filter(m => !DAYS.includes(findDayOfId(m.id)));
    const toPlace  = unplaced.length ? unplaced : [...STATE.matkul];

    if (!unplaced.length) {
        DAYS.forEach(d => { STATE.schedule[d] = []; });
        STATE.schedule['unscheduled'] = STATE.matkul.map(m => m.id);
    }

    toPlace.sort((a, b) => timeToMin(a.jam_mulai) - timeToMin(b.jam_mulai));

    let placed = 0;
    toPlace.forEach(m => {
        removeFromSchedule(m.id);
        for (const day of DAYS) {
            const existing = (STATE.schedule[day] || [])
                .map(cid => STATE.matkul.find(x => x.id === cid))
                .filter(Boolean);
            const clash = existing.some(o =>
                timeToMin(m.jam_mulai) < timeToMin(o.jam_selesai) &&
                timeToMin(o.jam_mulai) < timeToMin(m.jam_selesai)
            );
            if (!clash) {
                addToSchedule(m.id, day);
                placed++; break;
            }
        }
        if (!findDayOfId(m.id)) addToSchedule(m.id, 'unscheduled');
    });

    renderAll();
    const conflicts = countConflicts();
    if (conflicts === 0) {
        toast(`✓ Auto-jadwal berhasil! ${placed} matkul ditempatkan tanpa konflik.`, 'success');
    } else {
        toast(`Auto-jadwal: ${placed} ditempatkan, ${conflicts} konflik tidak bisa diselesaikan.`, 'warning');
    }
    batchUpdateServer();
}

function resetAll() {
    if (!confirm('Reset semua jadwal? Matkul yang tersimpan di database juga akan dihapus.')) return;
    STATE.matkul = [];
    STATE.schedule = {};
    STATE.colorIdx = 0;
    renderAll();
    fetch(APP_URL + '/api/matkul.php', { method: 'DELETE', headers: {'Content-Type':'application/json'}, body: JSON.stringify({action:'reset', jadwal_id: STATE.jadwalId}) })
        .then(r => r.json()).then(d => toast(d.message, d.status === 'success' ? 'success' : 'error'));
}

function clearGrid() {
    const all = [];
    Object.keys(STATE.schedule).forEach(k => all.push(...(STATE.schedule[k] || [])));
    STATE.schedule = { unscheduled: [...new Set(all)] };
    renderAll();
    toast('Grid dibersihkan, matkul ada di pool bawah', 'info');
}

/* ============ Render Functions ============ */
function renderAll() {
    renderGrid();
    renderPool();
    renderSidebar();
    updateStats();
}

function cardHTML(m, day) {
    const conflict = DAYS.includes(day) && conflictsWithDay(m.id, day);
    const bg = conflict ? '#fee2e2' : m.warna;
    return `
    <div class="scard${conflict ? ' conflict' : ''}"
         style="background:${bg};border-color:${conflict ? '#dc2626' : m.warna + 'cc'}"
         draggable="true"
         ondragstart="onDragStart(event,'${m.id}','${day}')"
         data-id="${m.id}">
      <button class="scard-del" onclick="deleteMatkul('${m.id}')" title="Hapus">✕</button>
      <div class="scard-name">${escHtml(m.nama)}</div>
      <div class="scard-detail">${escHtml(m.kelas)} · ${m.jam_mulai}–${m.jam_selesai}${m.dosen ? '<br>' + escHtml(m.dosen) : ''}</div>
      ${conflict ? '<div class="scard-conflict-tag">⚠ Jadwal bertabrakan!</div>' : ''}
    </div>`;
}

function renderGrid() {
    const grid = document.getElementById('schedule-grid');
    if (!grid) return;
    grid.innerHTML = DAYS.map(day => {
        const ids = STATE.schedule[day] || [];
        const cards = ids.map(id => {
            const m = STATE.matkul.find(x => x.id === id);
            return m ? cardHTML(m, day) : '';
        }).join('');
        return `
        <div class="day-col">
          <div class="day-header">${day}</div>
          <div class="day-drop" id="drop-${day}"
               ondragover="onDragOver(event,this)"
               ondragleave="onDragLeave(event,this)"
               ondrop="onDrop(event,'${day}')">
            ${cards || '<div class="day-empty">Drop matkul di sini</div>'}
          </div>
        </div>`;
    }).join('');
}

function renderPool() {
    const pool = document.getElementById('unscheduled-pool');
    if (!pool) return;
    const ids = STATE.schedule['unscheduled'] || [];
    if (!ids.length) {
        pool.innerHTML = '<span class="pool-empty">✓ Semua matkul sudah dijadwalkan</span>';
        return;
    }
    pool.innerHTML = ids.map(id => {
        const m = STATE.matkul.find(x => x.id === id);
        if (!m) return '';
        return `<div class="pool-card" style="background:${m.warna}"
                     draggable="true"
                     ondragstart="onDragStart(event,'${m.id}','unscheduled')"
                     data-id="${m.id}">
            ${escHtml(m.nama)} ${escHtml(m.kelas)}
        </div>`;
    }).join('');
}

function renderSidebar() {
    const el = document.getElementById('matkul-list');
    if (!el) return;
    if (!STATE.matkul.length) {
        el.innerHTML = '<div class="empty-state">Belum ada matkul ditambahkan</div>';
        return;
    }
    el.innerHTML = STATE.matkul.map(m => {
        const day = findDayOfId(m.id) || '–';
        const conflict = DAYS.includes(day) && conflictsWithDay(m.id, day);
        return `
        <div class="matkul-item${conflict ? ' conflict' : ''}" style="border-left-color:${m.warna}">
          <div class="matkul-actions">
            <button class="icon-btn" onclick="deleteMatkul('${m.id}')" title="Hapus">✕</button>
          </div>
          <div class="matkul-name">${escHtml(m.nama)} <span style="font-weight:400;color:var(--text-3)">${escHtml(m.kelas)}</span></div>
          <div class="matkul-meta">${day !== '–' && DAYS.includes(day) ? day + ' · ' : ''}${m.jam_mulai}–${m.jam_selesai} · ${m.sks} SKS${m.dosen ? ' · ' + escHtml(m.dosen) : ''}</div>
          ${conflict ? '<span class="conflict-tag">⚠ Konflik jadwal</span>' : ''}
        </div>`;
    }).join('');
}

function updateStats() {
    const el = (id, val) => { const e = document.getElementById(id); if(e) e.textContent = val; };
    el('stat-total', STATE.matkul.length);
    el('stat-sks', totalSKS());
    el('stat-konflik', countConflicts());
}

/* ============ Drag & Drop ============ */
function onDragStart(e, id, fromDay) {
    STATE.dragData = { id, from: fromDay };
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', id);
}

function onDragOver(e, el) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    el.classList.add('dragover');
}

function onDragLeave(e, el) {
    el.classList.remove('dragover');
}

function onDrop(e, toDay) {
    e.preventDefault();
    if (e.currentTarget) e.currentTarget.classList.remove('dragover');
    if (!STATE.dragData) return;
    moveMatkul(STATE.dragData.id, toDay);
    STATE.dragData = null;
}

/* ============ Excel Import ============ */
function initImportDrop() {
    const dz = document.getElementById('import-drop');
    if (!dz) return;
    dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('dragover'); });
    dz.addEventListener('dragleave', () => dz.classList.remove('dragover'));
    dz.addEventListener('drop', e => {
        e.preventDefault(); dz.classList.remove('dragover');
        const f = e.dataTransfer.files[0];
        if (f) processExcelFile(f);
    });
    dz.addEventListener('click', () => document.getElementById('excel-file').click());
}

function handleFileInput(input) {
    if (input.files[0]) processExcelFile(input.files[0]);
    input.value = '';
}

function processExcelFile(file) {
    if (!window.XLSX) { toast('Library Excel belum siap, coba lagi sesaat', 'error'); return; }
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const wb = XLSX.read(e.target.result, { type: 'binary' });
            const ws = wb.Sheets[wb.SheetNames[0]];
            const rows = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });
            let added = 0, skipped = 0;
            rows.forEach((row, i) => {
                // skip header row
                if (i === 0 && (String(row[0]).toLowerCase().includes('nama') || String(row[0]).toLowerCase().includes('matkul'))) return;
                const nama  = String(row[0] || '').trim();
                const kelas = String(row[1] || '').trim();
                if (!nama || !kelas) { skipped++; return; }
                const sks   = row[2] || 3;
                const dosen = String(row[3] || '').trim();
                const hari  = String(row[4] || '').trim();
                let mulai   = String(row[5] || '08:00').trim();
                let selesai = String(row[6] || '09:40').trim();
                if (typeof row[5] === 'number') mulai   = excelTimeStr(row[5]);
                if (typeof row[6] === 'number') selesai = excelTimeStr(row[6]);
                addMatkul({ nama, kelas, sks, dosen, hari: DAYS.includes(hari) ? hari : '', jam_mulai: mulai, jam_selesai: selesai });
                added++;
            });
            toast(`${added} matkul diimport${skipped ? ', ' + skipped + ' baris dilewati' : ''}`, 'success');
            switchTab('list');
        } catch(err) {
            toast('Gagal baca file: ' + err.message, 'error');
        }
    };
    reader.readAsBinaryString(file);
}

function excelTimeStr(v) {
    const totalMin = Math.round(v * 24 * 60);
    const h = Math.floor(totalMin / 60), m = totalMin % 60;
    return (h < 10 ? '0' + h : h) + ':' + (m < 10 ? '0' + m : m);
}

function downloadTemplate() {
    if (!window.XLSX) { toast('Library belum siap', 'error'); return; }
    const data = [
        ['Nama Matkul', 'Kelas', 'SKS', 'Dosen', 'Hari', 'Jam Mulai', 'Jam Selesai'],
        ['Algoritma & Pemrograman', 'A', 3, 'Dr. Budi Santoso', 'Senin', '08:00', '09:40'],
        ['Basis Data', 'B', 3, 'Dr. Sari Dewi', 'Selasa', '10:00', '11:40'],
        ['Matematika Diskrit', 'A', 3, 'Dr. Hani Pratiwi', 'Rabu', '13:00', '14:40'],
        ['Jaringan Komputer', 'C', 2, 'Dr. Rizky Putra', '', '08:00', '09:20'],
    ];
    const ws = XLSX.utils.aoa_to_sheet(data);
    ws['!cols'] = [20,8,5,20,10,12,12].map(w => ({ wch: w }));
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Template');
    XLSX.writeFile(wb, 'template_schedulin.xlsx');
}

function exportSchedule() {
    if (!window.XLSX) { toast('Library belum siap', 'error'); return; }
    const rows = [['Nama Matkul', 'Kelas', 'SKS', 'Dosen', 'Hari', 'Jam Mulai', 'Jam Selesai', 'Status Konflik']];
    DAYS.forEach(day => {
        (STATE.schedule[day] || []).forEach(id => {
            const m = STATE.matkul.find(x => x.id === id);
            if (m) rows.push([m.nama, m.kelas, m.sks, m.dosen, day, m.jam_mulai, m.jam_selesai, conflictsWithDay(id, day) ? 'KONFLIK' : 'OK']);
        });
    });
    (STATE.schedule['unscheduled'] || []).forEach(id => {
        const m = STATE.matkul.find(x => x.id === id);
        if (m) rows.push([m.nama, m.kelas, m.sks, m.dosen, 'Belum Dijadwalkan', m.jam_mulai, m.jam_selesai, '—']);
    });
    const ws = XLSX.utils.aoa_to_sheet(rows);
    ws['!cols'] = [20,8,5,20,12,12,12,14].map(w => ({ wch: w }));
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'KRS');
    XLSX.writeFile(wb, 'KRS_schedulin_' + new Date().toISOString().slice(0,10) + '.xlsx');
    toast('Jadwal berhasil diekspor!', 'success');
}

/* ============ Tab switching ============ */
function switchTab(name) {
    document.querySelectorAll('.s-tab').forEach(el => el.classList.toggle('active', el.dataset.tab === name));
    document.querySelectorAll('.tab-panel').forEach(el => el.classList.toggle('active', el.id === 'tab-' + name));
}

/* ============ Toast ============ */
function toast(msg, type = 'info') {
    const wrap = document.getElementById('toast-wrap');
    if (!wrap) return;
    const el = document.createElement('div');
    el.className = 'toast ' + type;
    el.textContent = msg;
    wrap.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; setTimeout(() => el.remove(), 300); }, 3000);
}

/* ============ Server API calls ============ */
const APP_URL = document.documentElement.dataset.appurl || '';

function saveToServer(m) {
    fetch(APP_URL + '/api/matkul.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'create', jadwal_id: STATE.jadwalId, matkul: m, hari: findDayOfId(m.id) })
    }).then(r => r.json()).then(d => {
        if (d.status === 'success' && d.server_id) {
            const local = STATE.matkul.find(x => x.id === m.id);
            if (local) local.server_id = d.server_id;
        }
    }).catch(() => {});
}

function deleteFromServer(id) {
    fetch(APP_URL + '/api/matkul.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', id })
    }).catch(() => {});
}

function updateOnServer(id, newDay) {
    fetch(APP_URL + '/api/matkul.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_hari', id, hari: DAYS.includes(newDay) ? newDay : '' })
    }).catch(() => {});
}

function batchUpdateServer() {
    const data = [];
    DAYS.forEach(day => {
        (STATE.schedule[day] || []).forEach(id => data.push({ id, hari: day }));
    });
    (STATE.schedule['unscheduled'] || []).forEach(id => data.push({ id, hari: '' }));
    fetch(APP_URL + '/api/matkul.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'batch_update', updates: data })
    }).catch(() => {});
}

function loadFromServer() {
    fetch(APP_URL + '/api/matkul.php?action=list&jadwal_id=' + STATE.jadwalId)
        .then(r => r.json())
        .then(d => {
            if (d.status !== 'success') return;
            STATE.matkul = [];
            STATE.schedule = {};
            (d.data || []).forEach(row => {
                const m = {
                    id: '_' + row.id,
                    server_id: row.id,
                    nama: row.nama,
                    kelas: row.kelas,
                    sks: parseInt(row.sks),
                    dosen: row.dosen || '',
                    hari: row.hari || '',
                    jam_mulai: row.jam_mulai.substring(0,5),
                    jam_selesai: row.jam_selesai.substring(0,5),
                    warna: row.warna || COLORS[STATE.colorIdx % COLORS.length],
                };
                STATE.colorIdx++;
                STATE.matkul.push(m);
                const target = DAYS.includes(m.hari) ? m.hari : 'unscheduled';
                if (!STATE.schedule[target]) STATE.schedule[target] = [];
                STATE.schedule[target].push(m.id);
            });
            renderAll();
        }).catch(() => {});
}

/* ============ Helper ============ */
function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ============ Init ============ */
document.addEventListener('DOMContentLoaded', function() {
    initImportDrop();

    // Set jadwal_id from page data attribute
    const root = document.getElementById('app-root');
    if (root && root.dataset.jadwalId) {
        STATE.jadwalId = root.dataset.jadwalId;
        loadFromServer();
    }

    // Pool drop zone
    const pool = document.getElementById('unscheduled-pool');
    if (pool) {
        pool.addEventListener('dragover', e => { e.preventDefault(); pool.classList.add('dragover'); });
        pool.addEventListener('dragleave', () => pool.classList.remove('dragover'));
        pool.addEventListener('drop', e => { e.preventDefault(); pool.classList.remove('dragover'); if (STATE.dragData) { moveMatkul(STATE.dragData.id, 'unscheduled'); STATE.dragData = null; } });
    }
});
