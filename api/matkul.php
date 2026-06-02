<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
startSession();

if (!isLoggedIn()) {
    jsonResponse('error', 'Unauthorized', []);
}

$user = currentUser();
$db   = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

// Decode JSON body for non-GET requests
$body = [];
if ($method !== 'GET') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? [];
}

/* ============================================================
   GET — List matkul untuk jadwal tertentu
   GET /api/matkul.php?action=list&jadwal_id=X
   ============================================================ */
if ($method === 'GET') {
    $action    = $_GET['action'] ?? 'list';
    $jadwal_id = intval($_GET['jadwal_id'] ?? 0);

    // Pastikan jadwal milik user ini
    $stmt = $db->prepare("SELECT id FROM jadwal WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $jadwal_id, $user['id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $stmt->close();
        jsonResponse('error', 'Jadwal tidak ditemukan');
    }
    $stmt->close();

    $stmt = $db->prepare("SELECT * FROM matkul WHERE jadwal_id = ? AND user_id = ? ORDER BY posisi_urut ASC, id ASC");
    $stmt->bind_param('ii', $jadwal_id, $user['id']);
    $stmt->execute();
    $rows   = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    jsonResponse('success', 'OK', ['data' => $rows]);
}

/* ============================================================
   POST — Tambah matkul baru
   ============================================================ */
if ($method === 'POST') {
    $action    = $body['action'] ?? 'create';
    $jadwal_id = intval($body['jadwal_id'] ?? 0);
    $m         = $body['matkul'] ?? [];
    $hari      = $body['hari'] ?? '';

    if (!$jadwal_id || empty($m)) jsonResponse('error', 'Data tidak lengkap');

    // Validasi kepemilikan jadwal
    $stmt = $db->prepare("SELECT id FROM jadwal WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $jadwal_id, $user['id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) jsonResponse('error', 'Akses ditolak');
    $stmt->close();

    $nama       = sanitize($m['nama'] ?? '');
    $kelas      = sanitize($m['kelas'] ?? '');
    $sks        = intval($m['sks'] ?? 3);
    $dosen      = sanitize($m['dosen'] ?? '');
    $jam_mulai  = $m['jam_mulai'] ?? '';
    $jam_selesai= $m['jam_selesai'] ?? '';
    $warna      = $m['warna'] ?? '#3b82f6';
    $hari_clean = in_array($hari, ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu']) ? $hari : '';

    if (!$nama || !$kelas || !$jam_mulai || !$jam_selesai) {
        jsonResponse('error', 'Field wajib tidak lengkap');
    }

    // Validasi format jam
    if (!preg_match('/^\d{2}:\d{2}$/', $jam_mulai) || !preg_match('/^\d{2}:\d{2}$/', $jam_selesai)) {
        jsonResponse('error', 'Format jam tidak valid');
    }

    $stmt = $db->prepare("INSERT INTO matkul (jadwal_id, user_id, nama, kelas, sks, dosen, hari, jam_mulai, jam_selesai, warna) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param('iisssisss s', $jadwal_id, $user['id'], $nama, $kelas, $sks, $dosen, $hari_clean, $jam_mulai, $jam_selesai, $warna);

    // Fix bind param string
    $stmt->close();
    $stmt = $db->prepare("INSERT INTO matkul (jadwal_id, user_id, nama, kelas, sks, dosen, hari, jam_mulai, jam_selesai, warna) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param('iisssissss', $jadwal_id, $user['id'], $nama, $kelas, $sks, $dosen, $hari_clean, $jam_mulai, $jam_selesai, $warna);

    if ($stmt->execute()) {
        $server_id = $db->lastId();
        $stmt->close();
        jsonResponse('success', 'Matkul ditambahkan', ['server_id' => $server_id]);
    } else {
        $stmt->close();
        jsonResponse('error', 'Gagal menyimpan matkul');
    }
}

/* ============================================================
   PUT — Update hari / batch update
   ============================================================ */
if ($method === 'PUT') {
    $action = $body['action'] ?? 'update_hari';

    if ($action === 'update_hari') {
        $id   = sanitize($body['id'] ?? '');
        $hari = $body['hari'] ?? '';
        $hari_clean = in_array($hari, ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu']) ? $hari : '';

        $stmt = $db->prepare("UPDATE matkul SET hari = ? WHERE user_id = ? AND (id = ? OR CONCAT('_', id) = ?)");
        $stmt->bind_param('siis', $hari_clean, $user['id'], $id, $id);
        $stmt->execute();
        $stmt->close();
        jsonResponse('success', 'Hari diperbarui');
    }

    if ($action === 'batch_update') {
        $updates = $body['updates'] ?? [];
        $updated = 0;
        foreach ($updates as $u) {
            $raw_id = $u['id'] ?? '';
            $hari   = in_array($u['hari'] ?? '', ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu']) ? $u['hari'] : '';
            // id bisa berupa '_xxxxxxx' (local) atau integer (server)
            $server_id = intval(ltrim($raw_id, '_'));
            if ($server_id > 0) {
                $stmt = $db->prepare("UPDATE matkul SET hari = ? WHERE id = ? AND user_id = ?");
                $stmt->bind_param('sii', $hari, $server_id, $user['id']);
                $stmt->execute();
                $updated += $stmt->affected_rows;
                $stmt->close();
            }
        }
        jsonResponse('success', "Batch update: $updated baris");
    }
}

/* ============================================================
   DELETE — Hapus matkul atau reset semua
   ============================================================ */
if ($method === 'DELETE') {
    $action = $body['action'] ?? 'delete';

    if ($action === 'reset') {
        $jadwal_id = intval($body['jadwal_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM matkul WHERE jadwal_id = ? AND user_id = ?");
        $stmt->bind_param('ii', $jadwal_id, $user['id']);
        $stmt->execute();
        $count = $stmt->affected_rows;
        $stmt->close();
        jsonResponse('success', "$count matkul dihapus");
    }

    // Hapus satu matkul
    $raw_id    = $body['id'] ?? '';
    $server_id = intval(ltrim($raw_id, '_'));
    if ($server_id > 0) {
        $stmt = $db->prepare("DELETE FROM matkul WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $server_id, $user['id']);
        $stmt->execute();
        $stmt->close();
        jsonResponse('success', 'Matkul dihapus');
    }
    jsonResponse('error', 'ID tidak valid');
}

jsonResponse('error', 'Method tidak diizinkan');
