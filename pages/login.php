<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

startSession();
requireGuest();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../includes/db.php';
    $db = Database::getInstance();

    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Email dan password wajib diisi.';
    } else {
        $stmt = $db->prepare("SELECT id, nama, nim, email, password FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        if ($user && verifyPassword($password, $user['password'])) {
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_nama']  = $user['nama'];
            $_SESSION['user_nim']   = $user['nim'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['last_activity'] = time();
            header('Location: ' . APP_URL . '/index.php');
            exit;
        } else {
            $error = 'Email atau password salah.';
        }
    }
}

if (isset($_GET['timeout'])) $error = 'Sesi habis, silakan login kembali.';
if (isset($_GET['registered'])) $success = 'Akun berhasil dibuat! Silakan login.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — SCHEDULIN</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">SCHEDU<span>LIN</span></div>
    <div class="auth-subtitle">Sistem Penjadwalan KRS Mahasiswa</div>

    <?php if ($error): ?>
    <div class="alert alert-error" style="margin-bottom:14px">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success" style="margin-bottom:14px">✓ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="field" style="margin-bottom:12px">
        <label>Email</label>
        <input type="email" name="email" placeholder="nim@upnyk.ac.id" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="field" style="margin-bottom:16px">
        <label>Password</label>
        <input type="password" name="password" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary btn-full">Masuk</button>
    </form>

    <div class="auth-divider">atau</div>
    <div class="auth-footer">
      Belum punya akun? <a href="<?= APP_URL ?>/pages/register.php">Daftar sekarang</a>
    </div>
  </div>
</div>
</body>
</html>
