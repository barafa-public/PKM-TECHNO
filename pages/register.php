<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

startSession();
requireGuest();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../includes/db.php';
    $db = Database::getInstance();

    $nama     = sanitize($_POST['nama']     ?? '');
    $nim      = sanitize($_POST['nim']      ?? '');
    $email    = sanitize($_POST['email']    ?? '');
    $prodi    = sanitize($_POST['prodi']    ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if (!$nama || !$nim || !$email || !$password) {
        $error = 'Semua field wajib diisi.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $confirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } else {
        // Cek duplikat
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? OR nim = ? LIMIT 1");
        $stmt->bind_param('ss', $email, $nim);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $error = 'Email atau NIM sudah terdaftar.';
        } else {
            $hash = hashPassword($password);
            $stmt = $db->prepare("INSERT INTO users (nim, nama, email, password, prodi) VALUES (?,?,?,?,?)");
            $stmt->bind_param('sssss', $nim, $nama, $email, $hash, $prodi);
            if ($stmt->execute()) {
                $user_id = $db->lastId();
                // Buat jadwal default
                $stmt2 = $db->prepare("INSERT INTO jadwal (user_id, semester, nama_jadwal) VALUES (?, 'Ganjil 2025/2026', 'KRS Saya')");
                $stmt2->bind_param('i', $user_id);
                $stmt2->execute();
                $stmt2->close();
                $stmt->close();
                header('Location: ' . APP_URL . '/pages/login.php?registered=1');
                exit;
            } else {
                $error = 'Gagal membuat akun. Coba lagi.';
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Daftar — SCHEDULIN</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-card" style="max-width:440px">
    <div class="auth-logo">SCHEDU<span>LIN</span></div>
    <div class="auth-subtitle">Buat akun untuk mulai merencanakan KRS</div>

    <?php if ($error): ?>
    <div class="alert alert-error" style="margin-bottom:14px">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="field" style="margin-bottom:10px">
        <label>Nama Lengkap *</label>
        <input type="text" name="nama" placeholder="Nama sesuai KTM" required value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>">
      </div>
      <div class="field-row" style="margin-bottom:10px">
        <div class="field">
          <label>NIM *</label>
          <input type="text" name="nim" placeholder="124240065" required value="<?= htmlspecialchars($_POST['nim'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Program Studi</label>
          <input type="text" name="prodi" placeholder="Sistem Informasi" value="<?= htmlspecialchars($_POST['prodi'] ?? '') ?>">
        </div>
      </div>
      <div class="field" style="margin-bottom:10px">
        <label>Email *</label>
        <input type="email" name="email" placeholder="nim@upnyk.ac.id" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="field-row" style="margin-bottom:16px">
        <div class="field">
          <label>Password *</label>
          <input type="password" name="password" placeholder="Min 6 karakter" required>
        </div>
        <div class="field">
          <label>Konfirmasi *</label>
          <input type="password" name="confirm" placeholder="Ulangi password" required>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-full">Buat Akun</button>
    </form>

    <div class="auth-footer" style="margin-top:14px">
      Sudah punya akun? <a href="<?= APP_URL ?>/pages/login.php">Masuk</a>
    </div>
  </div>
</div>
</body>
</html>
