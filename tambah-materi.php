<?php
// filepath: c:\xampp\htdocs\quizmaster\tambah-materi.php
session_start();

require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

$stmt = $conn->prepare('SELECT nama, role FROM users WHERE id = ?');
if (!$stmt) {
    http_response_code(500);
    exit('Gagal menyiapkan pemeriksaan akun.');
}

$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if ($user['role'] !== 'guru') {
    header('Location: view/dashboard.php');
    exit;
}

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$judul = '';
$deskripsi = '';
$isiMateri = '';
$videoUrl = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $isiMateri = trim($_POST['isi_materi'] ?? '');
    $videoUrl = trim($_POST['video_url'] ?? '');

    if ($judul === '' || $isiMateri === '') {
        $error = 'Judul dan isi materi wajib diisi.';
    } elseif ($videoUrl !== '' && !filter_var($videoUrl, FILTER_VALIDATE_URL)) {
        $error = 'Format URL video tidak valid.';
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO materi (user_id, judul, deskripsi, isi_materi, video_url)
             VALUES (?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            $error = 'Materi belum dapat disimpan. Silakan coba lagi.';
        } else {
            $stmt->bind_param(
                'issss',
                $userId,
                $judul,
                $deskripsi,
                $isiMateri,
                $videoUrl
            );

            if ($stmt->execute()) {
                $stmt->close();
                header('Location: daftar-materi.php?success=1');
                exit;
            }

            error_log('Tambah materi gagal: ' . $stmt->error);
            $stmt->close();
            $error = 'Materi gagal disimpan. Silakan coba lagi.';
        }
    }
}

$nama = $user['nama'] ?? 'Guru';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tambah Materi - TKDSmart</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .materi-page {
            width: min(850px, calc(100% - 40px));
            margin: 0 auto;
            padding: 34px 0 60px;
        }

        .materi-heading {
            margin-bottom: 22px;
            padding: clamp(24px, 4vw, 36px);
            border: 1px solid #e9e0f2;
            border-radius: 16px;
            background: linear-gradient(125deg, #f1eaff, #fff 75%);
        }

        .materi-heading h1 {
            margin: 0;
            color: #25243b;
            font-size: clamp(26px, 4vw, 34px);
        }

        .materi-heading p:last-child {
            margin: 8px 0 0;
            color: #777587;
        }

        .materi-form {
            padding: clamp(22px, 4vw, 32px);
            border: 1px solid #ecebf1;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(37, 36, 59, .05);
        }

        .materi-field {
            margin-bottom: 19px;
        }

        .materi-field label {
            display: block;
            margin-bottom: 7px;
            color: #343348;
            font-size: 14px;
            font-weight: 700;
        }

        .materi-field input,
        .materi-field textarea {
            width: 100%;
            padding: 12px 13px;
            border: 1px solid #dedce5;
            border-radius: 8px;
            background: #fff;
            color: #343348;
            font: inherit;
        }

        .materi-field textarea {
            min-height: 130px;
            resize: vertical;
        }

        .materi-field textarea[name="isi_materi"] {
            min-height: 250px;
        }

        .materi-field input:focus,
        .materi-field textarea:focus {
            border-color: #8750c5;
            outline: 3px solid rgba(135, 80, 197, .12);
        }

        .materi-help {
            display: block;
            margin-top: 6px;
            color: #777587;
            font-size: 12px;
        }

        .materi-error {
            margin-bottom: 20px;
            padding: 12px 14px;
            border: 1px solid #f0c9c7;
            border-left: 4px solid #b42318;
            border-radius: 8px;
            background: #fff7f6;
            color: #a12622;
        }

        .materi-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 5px;
        }

        .materi-actions .button {
            min-width: 110px;
            cursor: pointer;
        }

        @media (max-width: 600px) {
            .materi-page {
                width: min(100% - 30px, 850px);
                padding-top: 22px;
            }

            .materi-actions {
                flex-direction: column-reverse;
            }

            .materi-actions .button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php">TKD<span>Smart</span></a>

    <nav class="main-nav" aria-label="Navigasi utama">
        <a href="view/dashboard.php">Dashboard</a>
        <a href="daftar-materi.php">Materi</a>
        <a class="active" href="tambah-materi.php">Tambah materi</a>
    </nav>

    <div class="account-nav">
        <span class="user-name"><?= e($nama) ?></span>
        <a class="logout-link" href="logout.php">Logout</a>
    </div>
</header>

<main class="materi-page">
    <section class="materi-heading">
        <p class="eyebrow">RUANG GURU</p>
        <h1>Tambah materi pembelajaran</h1>
        <p>Isi detail materi agar siswa dapat menemukannya dan belajar dengan mudah.</p>
    </section>

    <form class="materi-form" method="POST">
        <?php if ($error !== ''): ?>
            <div class="materi-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="materi-field">
            <label for="judul">Judul materi</label>
            <input
                type="text"
                id="judul"
                name="judul"
                value="<?= e($judul) ?>"
                placeholder="Contoh: Pengantar Teknik Kendali Digital"
                maxlength="255"
                required
            >
        </div>

        <div class="materi-field">
            <label for="deskripsi">Deskripsi</label>
            <textarea
                id="deskripsi"
                name="deskripsi"
                placeholder="Jelaskan isi materi secara singkat"
            ><?= e($deskripsi) ?></textarea>
        </div>

        <div class="materi-field">
            <label for="isi_materi">Isi materi</label>
            <textarea
                id="isi_materi"
                name="isi_materi"
                placeholder="Tuliskan materi pembelajaran di sini"
                required
            ><?= e($isiMateri) ?></textarea>
        </div>

        <div class="materi-field">
            <label for="video_url">Tautan video (opsional)</label>
            <input
                type="url"
                id="video_url"
                name="video_url"
                value="<?= e($videoUrl) ?>"
                placeholder="https://www.youtube.com/watch?v=..."
            >
            <small class="materi-help">Masukkan URL video pembelajaran jika tersedia.</small>
        </div>

        <div class="materi-actions">
            <a class="button button-secondary" href="view/dashboard.php">Batal</a>
            <button class="button button-primary" type="submit">Simpan materi</button>
        </div>
    </form>
</main>

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> TKDSmart. Platform Pembelajaran Digital.</p>
</footer>
<script src="js/back-button.js" data-fallback="view/dashboard.php"></script>
</body>
</html>