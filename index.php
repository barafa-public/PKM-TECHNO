<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

startSession();
requireLogin();

$user = currentUser();
$db   = Database::getInstance();

// Ambil atau buat jadwal untuk user ini
$stmt = $db->prepare("SELECT id FROM jadwal WHERE user_id = ? ORDER BY id ASC LIMIT 1");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    $stmt = $db->prepare("INSERT INTO jadwal (user_id, semester, nama_jadwal) VALUES (?, 'Ganjil 2025/2026', 'KRS Saya')");
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $jadwal_id = $db->lastId();
    $stmt->close();
} else {
    $jadwal_id = $row['id'];
}

// Inisial nama user untuk avatar
$initials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', trim($user['nama']))));
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="id" data-appurl="<?= APP_URL ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SCHEDULIN — Perencana KRS</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.40.0/tabler-icons.min.css">
</head>
<body>

<div class="app-wrapper" id="app-root" data-jadwal-id="<?= $jadwal_id ?>">

  <!-- ===== TOP BAR ===== -->
  <header class="app-topbar">
    <div class="logo">SCHEDU<span>LIN</span></div>
    <span class="logo-tagline">Perencana KRS Mahasiswa</span>

    <div class="topbar-right">
      <div class="user-badge">
        <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
        <span><?= htmlspecialchars($user['nama']) ?></span>
        <span class="badge badge-blue"><?= htmlspecialchars($user['nim'] ?: 'Mahasiswa') ?></span>
      </div>
      <a href="<?= APP_URL ?>/pages/logout.php" class="btn btn-secondary btn-sm">
        <i class="ti ti-logout" aria-hidden="true"></i> Keluar
      </a>
    </div>
  </header>

  <!-- ===== SIDEBAR ===== -->
  <aside class="app-sidebar">
    <div class="sidebar-tabs">
      <button class="s-tab active" data-tab="input" onclick="switchTab('input')">
        <i class="ti ti-plus" aria-hidden="true"></i> Tambah
      </button>
      <button class="s-tab" data-tab="import" onclick="switchTab('import')">
        <i class="ti ti-file-spreadsheet" aria-hidden="true"></i> Excel
      </button>
      <button class="s-tab" data-tab="list" onclick="switchTab('list')">
        <i class="ti ti-list" aria-hidden="true"></i> Daftar
      </button>
    </div>

    <!-- Tab: Tambah Manual -->
    <div id="tab-input" class="tab-panel active">

      <div class="stats-row">
        <div class="stat-box">
          <div class="stat-val" id="stat-total">0</div>
          <div class="stat-lbl">Total Matkul</div>
        </div>
        <div class="stat-box sks">
          <div class="stat-val" id="stat-sks">0</div>
          <div class="stat-lbl">Total SKS</div>
        </div>
        <div class="stat-box conflict">
          <div class="stat-val" id="stat-konflik">0</div>
          <div class="stat-lbl">Konflik</div>
        </div>
      </div>

      <div class="field">
        <label>Nama Mata Kuliah *</label>
        <input id="inp-nama" type="text" placeholder="misal: Matematika Diskrit">
      </div>

      <div class="field-row">
        <div class="field">
          <label>Kelas *</label>
          <input id="inp-kelas" type="text" placeholder="A / B / C">
        </div>
        <div class="field">
          <label>SKS</label>
          <input id="inp-sks" type="number" value="3" min="1" max="6">
        </div>
      </div>

      <div class="field">
        <label>Dosen Pengampu</label>
        <input id="inp-dosen" type="text" placeholder="Nama dosen (opsional)">
      </div>

      <div class="field">
        <label>Hari (opsional)</label>
        <select id="inp-hari">
          <option value="">— Atur manual via drag —</option>
          <option>Senin</option><option>Selasa</option><option>Rabu</option>
          <option>Kamis</option><option>Jumat</option><option>Sabtu</option>
        </select>
      </div>

      <div class="field-row">
        <div class="field">
          <label>Jam Mulai *</label>
          <input id="inp-mulai" type="time" value="08:00">
        </div>
        <div class="field">
          <label>Jam Selesai *</label>
          <input id="inp-selesai" type="time" value="09:40">
        </div>
      </div>

      <button class="btn btn-primary btn-full" onclick="addMatkul()">
        <i class="ti ti-plus" aria-hidden="true"></i> Tambah Mata Kuliah
      </button>

      <hr class="divider">

      <div class="btn-row">
        <button class="btn btn-success" onclick="autoSchedule()">
          <i class="ti ti-sparkles" aria-hidden="true"></i> Auto-Jadwal
        </button>
        <button class="btn btn-danger" onclick="resetAll()">
          <i class="ti ti-refresh" aria-hidden="true"></i> Reset Semua
        </button>
      </div>
    </div>

    <!-- Tab: Import Excel -->
    <div id="tab-import" class="tab-panel">
      <div class="import-drop" id="import-drop">
        <i class="ti ti-file-spreadsheet import-icon" aria-hidden="true"></i>
        <div class="import-label">Klik atau drop file Excel di sini</div>
        <div class="import-sub">.xlsx / .xls / .csv</div>
      </div>
      <input type="file" id="excel-file" accept=".xlsx,.xls,.csv" style="display:none" onchange="handleFileInput(this)">

      <span class="template-link" onclick="downloadTemplate()">
        <i class="ti ti-download" aria-hidden="true"></i> Download template Excel
      </span>

      <div class="format-box">
        <strong>Format kolom Excel:</strong><br>
        A — Nama Matkul<br>
        B — Kelas (A/B/C)<br>
        C — SKS (angka)<br>
        D — Dosen<br>
        E — Hari (Senin/Selasa/dst)<br>
        F — Jam Mulai (08:00)<br>
        G — Jam Selesai (09:40)
      </div>

      <div class="alert alert-info" style="font-size:12px">
        Baris pertama adalah header dan akan dilewati otomatis. Kolom Dosen dan Hari bisa dikosongkan.
      </div>
    </div>

    <!-- Tab: Daftar Matkul -->
    <div id="tab-list" class="tab-panel">
      <div class="section-label">Mata Kuliah Tersimpan</div>
      <div id="matkul-list">
        <div class="empty-state">Belum ada matkul</div>
      </div>
    </div>
  </aside>

  <!-- ===== MAIN AREA ===== -->
  <main class="app-main">
    <div class="main-toolbar">
      <span class="toolbar-title">
        <i class="ti ti-calendar-week" aria-hidden="true"></i>
        Jadwal Mingguan — drag kartu antar hari
      </span>
      <button class="btn btn-success btn-sm" onclick="autoSchedule()">
        <i class="ti ti-sparkles" aria-hidden="true"></i> Auto-Jadwal
      </button>
      <button class="btn btn-secondary btn-sm" onclick="clearGrid()">
        <i class="ti ti-eraser" aria-hidden="true"></i> Bersihkan Grid
      </button>
      <button class="btn btn-amber btn-sm" onclick="exportSchedule()">
        <i class="ti ti-download" aria-hidden="true"></i> Export Excel
      </button>
    </div>

    <div class="schedule-wrap">
      <div class="schedule-grid" id="schedule-grid">
        <!-- Rendered by JS -->
      </div>
    </div>

    <!-- Pool area for unscheduled matkul -->
    <div class="pool-area">
      <div class="pool-title">
        <i class="ti ti-inbox" aria-hidden="true"></i>
        Belum Dijadwalkan — drag ke kolom hari di atas
      </div>
      <div class="pool-drop" id="unscheduled-pool">
        <!-- Rendered by JS -->
      </div>
    </div>
  </main>

</div>

<!-- Toast container -->
<div class="toast-wrap" id="toast-wrap"></div>

<!-- Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
