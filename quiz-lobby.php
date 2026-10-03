<?php
session_start();

require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'siswa') {
    header('Location: view/dashboard.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$quizId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

if ($quizId <= 0) {
    header('Location: view/dashboard.php');
    exit;
}

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$pageError = '';
$quiz = null;
$jumlahSoal = 0;

try {
    $stmt = $conn->prepare("
        SELECT
            q.id,
            q.materi_id,
            q.judul,
            q.deskripsi,
            q.waktu,
            m.judul AS materi_judul,
            u.nama AS nama_guru
        FROM quizzes q
        LEFT JOIN materi m ON q.materi_id = m.id
        INNER JOIN users u ON q.user_id = u.id
        WHERE q.id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new RuntimeException('Gagal menyiapkan query quiz.');
    }

    $stmt->bind_param('i', $quizId);
    $stmt->execute();
    $quiz = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$quiz) {
        header('Location: view/dashboard.php');
        exit;
    }

    $stmtSoal = $conn->prepare(
        'SELECT COUNT(*) AS jumlah_soal FROM questions WHERE quiz_id = ?'
    );

    if (!$stmtSoal) {
        throw new RuntimeException('Gagal menyiapkan query jumlah soal.');
    }

    $stmtSoal->bind_param('i', $quizId);
    $stmtSoal->execute();
    $jumlahSoal = (int) ($stmtSoal->get_result()->fetch_assoc()['jumlah_soal'] ?? 0);
    $stmtSoal->close();
} catch (Throwable $error) {
    error_log('Quiz lobby error: ' . $error->getMessage());
    $pageError = 'Informasi quiz belum dapat dimuat. Silakan coba lagi.';
}

$nama = $_SESSION['nama'] ?? 'Siswa';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($quiz['judul'] ?? 'Quiz') ?> - TKDSmart</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .lobby-main {
            width: min(900px, calc(100% - 40px));
            margin: 34px auto 60px;
        }

        .lobby-hero {
            position: relative;
            overflow: hidden;
            padding: clamp(26px, 5vw, 44px);
            border: 1px solid #e9e0f2;
            border-radius: 16px;
            background:
                radial-gradient(ellipse at 90% 10%, rgba(135, 80, 197, .16), transparent 32%),
                linear-gradient(125deg, #f1eaff, #fff 72%);
        }

        .lobby-hero h1 {
            max-width: 700px;
            margin: 0;
            color: #25243b;
            font-size: clamp(28px, 5vw, 38px);
            line-height: 1.2;
        }

        .lobby-subtitle {
            margin: 10px 0 0;
            color: #777587;
        }

        .lobby-card {
            margin-top: 18px;
            padding: clamp(22px, 4vw, 32px);
            border: 1px solid #ecebf1;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(37, 36, 59, .05);
        }

        .lobby-description {
            margin: 0;
            color: #5f5d70;
            line-height: 1.75;
            white-space: normal;
        }

        .lobby-materi {
            margin-bottom: 22px;
            padding: 15px 17px;
            border: 1px solid #e9e0f2;
            border-radius: 10px;
            background: #f8f4fc;
        }

        .lobby-materi span,
        .lobby-materi strong {
            display: block;
        }

        .lobby-materi span {
            margin-bottom: 3px;
            color: #8750c5;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .8px;
        }

        .lobby-materi strong {
            color: #343348;
        }

        .lobby-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin: 24px 0;
        }

        .lobby-stat {
            padding: 17px 12px;
            border: 1px solid #ecebf1;
            border-radius: 10px;
            background: #f8f6fb;
            text-align: center;
        }

        .lobby-stat strong,
        .lobby-stat span {
            display: block;
        }

        .lobby-stat strong {
            color: #713cae;
            font-size: 22px;
            line-height: 1.3;
        }

        .lobby-stat span {
            margin-top: 4px;
            color: #777587;
            font-size: 12px;
        }

        .lobby-warning,
        .lobby-error {
            margin: 20px 0;
            padding: 13px 15px;
            border-radius: 9px;
            font-size: 14px;
        }

        .lobby-warning {
            border: 1px solid #f0dfbb;
            background: #fffaf0;
            color: #805b19;
        }

        .lobby-error {
            border: 1px solid #f0c9c7;
            background: #fff7f6;
            color: #a12622;
        }

        .lobby-actions {
            display: flex;
            gap: 10px;
        }

        .lobby-actions .button {
            flex: 1;
        }

        .lobby-actions .button-primary {
            display: inline-flex;
            min-height: 42px;
            align-items: center;
            justify-content: center;
            padding: 10px 17px;
            border: 1px solid #b42318;
            border-radius: 8px;
            background: #b42318;
            color: #fff;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
        }

        .lobby-actions .button-primary:hover {
            border-color: #8f1d14;
            background: #8f1d14;
        }

        @media (max-width: 600px) {
            .lobby-main {
                width: min(100% - 30px, 900px);
                margin-top: 22px;
            }

            .lobby-stats {
                grid-template-columns: 1fr;
            }

            .lobby-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php">TKD<span>Smart</span></a>

    <nav class="main-nav" aria-label="Navigasi utama">
        <a href="view/dashboard.php">Dashboard</a>
        <a class="active" href="materi-siswa.php">Belajar</a>
    </nav>

    <div class="account-nav">
    </div>
</header>

<main class="lobby-main">
    <?php if ($pageError !== ''): ?>
        <div class="lobby-error" role="alert"><?= e($pageError) ?></div>
        <a class="button button-secondary" href="view/dashboard.php">Kembali ke dashboard</a>
    <?php else: ?>
        <section class="lobby-hero">
            <p class="eyebrow">DETAIL QUIZ</p>
            <h1><?= e($quiz['judul']) ?></h1>
            <p class="lobby-subtitle">
                Dibuat oleh <?= e($quiz['nama_guru'] ?? 'Guru') ?>
            </p>
        </section>

        <section class="lobby-card">
            <?php if (!empty($quiz['materi_judul'])): ?>
                <div class="lobby-materi">
                    <span>MATERI TERKAIT</span>
                    <strong><?= e($quiz['materi_judul']) ?></strong>
                </div>
            <?php endif; ?>

            <?php if (trim((string) ($quiz['deskripsi'] ?? '')) !== ''): ?>
                <p class="lobby-description"><?= nl2br(e($quiz['deskripsi'])) ?></p>
            <?php else: ?>
                <p class="lobby-description">Tidak ada deskripsi untuk quiz ini.</p>
            <?php endif; ?>

            <div class="lobby-stats">
                <div class="lobby-stat">
                    <strong><?= $jumlahSoal ?></strong>
                    <span>Jumlah soal</span>
                </div>
                <div class="lobby-stat">
                    <strong><?= (int) ($quiz['waktu'] ?? 0) ?></strong>
                    <span>Waktu (menit)</span>
                </div>
                <div class="lobby-stat">
                    <strong><?= e($quiz['nama_guru'] ?? 'Guru') ?></strong>
                    <span>Pengajar</span>
                </div>
            </div>

            <?php if ($jumlahSoal <= 0): ?>
                <div class="lobby-warning" role="status">
                    Quiz ini belum memiliki soal sehingga belum dapat dikerjakan.
                </div>
            <?php endif; ?>

            <div class="lobby-actions">
                <?php if ($jumlahSoal > 0): ?>
                    <a class="button button-primary" href="play-quiz.php?id=<?= (int) $quiz['id'] ?>">
                        Mulai quiz
                    </a>
                <?php else: ?>
                    <span class="button button-disabled" aria-disabled="true">Belum siap</span>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</main>

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> TKDSmart. Platform Pembelajaran Digital.</p>
</footer>
<script src="js/back-button.js" data-fallback="view/dashboard.php"></script>
</body>
</html>