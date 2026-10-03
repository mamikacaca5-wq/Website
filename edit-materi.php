<?php
// filepath: c:\xampp\htdocs\quizmaster\detail-materi.php
session_start();

require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'guru') {
    header('Location: view/dashboard.php');
    exit;
}

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$userId = (int) $_SESSION['user_id'];
$materiId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

if ($materiId <= 0) {
    header('Location: daftar-materi.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$nama = $_SESSION['nama'] ?? 'Guru';

$stmt = $conn->prepare("
    SELECT id, judul, deskripsi, isi_materi, video_url
    FROM materi
    WHERE id = ? AND user_id = ?
    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);
    exit('Materi belum dapat dimuat.');
}

$stmt->bind_param('ii', $materiId, $userId);
$stmt->execute();
$materi = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$materi) {
    http_response_code(404);
    exit('Materi tidak ditemukan atau Anda tidak memiliki akses.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    $judul = is_string($_POST['judul'] ?? null)
        ? trim($_POST['judul'])
        : '';
    $deskripsi = is_string($_POST['deskripsi'] ?? null)
        ? trim($_POST['deskripsi'])
        : '';
    $isiMateri = is_string($_POST['isi_materi'] ?? null)
        ? trim($_POST['isi_materi'])
        : '';
    $videoUrl = is_string($_POST['video_url'] ?? null)
        ? trim($_POST['video_url'])
        : '';

    $materi['judul'] = $judul;
    $materi['deskripsi'] = $deskripsi;
    $materi['isi_materi'] = $isiMateri;
    $materi['video_url'] = $videoUrl;

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION['csrf_token'], $csrfToken)
    ) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif ($judul === '') {
        $error = 'Judul materi wajib diisi.';
    } elseif ($isiMateri === '') {
        $error = 'Isi materi wajib diisi.';
    } elseif (
        $videoUrl !== '' &&
        (
            filter_var($videoUrl, FILTER_VALIDATE_URL) === false ||
            !in_array(
                strtolower((string) parse_url($videoUrl, PHP_URL_SCHEME)),
                ['http', 'https'],
                true
            )
        )
    ) {
        $error = 'URL video harus berupa alamat HTTP atau HTTPS yang valid.';
    } else {
        $stmt = $conn->prepare("
            UPDATE materi
            SET judul = ?, deskripsi = ?, isi_materi = ?, video_url = ?
            WHERE id = ? AND user_id = ?
        ");

        if (!$stmt) {
            $error = 'Materi gagal diperbarui. Silakan coba lagi.';
        } else {
            $stmt->bind_param(
                'ssssii',
                $judul,
                $deskripsi,
                $isiMateri,
                $videoUrl,
                $materiId,
                $userId
            );

            if ($stmt->execute()) {
                $stmt->close();
                header('Location: detail-materi.php?id=' . $materiId . '&updated=1');
                exit;
            }

            error_log('Edit materi error: ' . $stmt->error);
            $stmt->close();
            $error = 'Materi gagal diperbarui. Silakan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Materi - TKDSmart</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .edit-page {
            width: min(900px, calc(100% - 36px));
            margin: 32px auto 58px;
        }

        .edit-hero,
        .edit-card {
            padding: clamp(22px, 4vw, 34px);
            border: 1px solid #ecebf1;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(37, 36, 59, .05);
        }

        .edit-hero {
            margin-bottom: 18px;
            border-color: #e9e0f2;
            background: linear-gradient(125deg, #f1eaff, #fff 75%);
        }

        .edit-hero h1 {
            margin: 0;
            color: #25243b;
            font-size: clamp(27px, 4vw, 35px);
        }

        .edit-hero p:last-child {
            margin: 8px 0 0;
            color: #777587;
        }

        .edit-card h2 {
            margin: 0 0 20px;
            color: #25243b;
        }

        .edit-field {
            margin-bottom: 18px;
        }

        .edit-field label {
            display: block;
            margin-bottom: 7px;
            color: #343348;
            font-size: 14px;
            font-weight: 700;
        }

        .edit-field input,
        .edit-field textarea {
            box-sizing: border-box;
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #dedce5;
            border-radius: 8px;
            background: #fff;
            color: #343348;
            font: inherit;
        }

        .edit-field textarea {
            min-height: 110px;
            resize: vertical;
        }

        .edit-field textarea[name="isi_materi"] {
            min-height: 240px;
        }

        .edit-field input:focus,
        .edit-field textarea:focus {
            border-color: #8750c5;
            outline: 3px solid rgba(135, 80, 197, .12);
        }

        .form-error {
            margin-bottom: 18px;
            padding: 12px 14px;
            border: 1px solid #f0c9c7;
            border-left: 4px solid #b42318;
            border-radius: 8px;
            background: #fff7f6;
            color: #a12622;
        }

        .edit-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 22px;
        }

        .edit-button {
            display: inline-flex;
            min-height: 42px;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border: 0;
            border-radius: 8px;
            background: #8750c5;
            color: #fff;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .edit-button:hover {
            background: #713cae;
        }

        .edit-button.secondary {
            border: 1px solid #e8e3ee;
            background: #f3eef9;
            color: #713cae;
        }

        @media (max-width: 600px) {
            .edit-page {
                margin-top: 20px;
            }

            .edit-actions {
                flex-direction: column;
            }

            .edit-button {
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
        <a class="active" href="daftar-materi.php">Materi</a>
        <a href="create-quiz.php">Kelola quiz</a>
        <a href="hasil-belajar.php">Hasil belajar</a>
    </nav>

    <div class="account-nav">
        <span class="user-name"><?= e($nama) ?></span>
        <a class="logout-link" href="logout.php">Logout</a>
    </div>
</header>

<main class="edit-page">
    <section class="edit-hero">
        <p class="eyebrow">PENGELOLAAN PEMBELAJARAN</p>
        <h1>Edit materi</h1>
        <p>Perbarui informasi dan isi materi pembelajaran.</p>
    </section>

    <?php if ($error !== ''): ?>
        <div class="form-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="edit-materi.php?id=<?= (int) $materiId ?>">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= e($_SESSION['csrf_token']) ?>"
        >

        <section class="edit-card">
            <h2>Informasi materi</h2>

            <div class="edit-field">
                <label for="judul">Judul materi</label>
                <input
                    type="text"
                    id="judul"
                    name="judul"
                    maxlength="255"
                    value="<?= e($materi['judul']) ?>"
                    required
                >
            </div>

            <div class="edit-field">
                <label for="deskripsi">Deskripsi</label>
                <textarea id="deskripsi" name="deskripsi"><?= e($materi['deskripsi'] ?? '') ?></textarea>
            </div>

            <div class="edit-field">
                <label for="isi_materi">Isi materi</label>
                <textarea id="isi_materi" name="isi_materi" required><?= e($materi['isi_materi'] ?? '') ?></textarea>
            </div>

            <div class="edit-field">
                <label for="video_url">URL video (opsional)</label>
                <input
                    type="url"
                    id="video_url"
                    name="video_url"
                    value="<?= e($materi['video_url'] ?? '') ?>"
                    placeholder="https://www.youtube.com/watch?v=..."
                >
            </div>

            <div class="edit-actions">
                <button type="submit" class="edit-button">Simpan perubahan</button>
                <a
                    class="edit-button secondary"
                    href="detail-materi.php?id=<?= (int) $materiId ?>"
                >Batal</a>
            </div>
        </section>
    </form>
</main>

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> TKDSmart. Platform Pembelajaran Digital.</p>
</footer>
<script src="js/back-button.js" data-fallback="view/dashboard.php"></script>
</body>
</html>