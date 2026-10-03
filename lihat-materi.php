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

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$materiId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

if ($materiId <= 0) {
    header('Location: materi-siswa.php');
    exit;
}

$materi = null;
$quiz = null;
$jumlahSoal = 0;
$pageError = '';
$youtubeId = '';

try {
    $stmt = $conn->prepare("
        SELECT m.id, m.judul, m.deskripsi, m.isi_materi, m.video_url,
               m.created_at, u.nama AS nama_guru
        FROM materi AS m
        INNER JOIN users AS u ON u.id = m.user_id
        WHERE m.id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new RuntimeException('Gagal menyiapkan query materi.');
    }

    $stmt->bind_param('i', $materiId);
    $stmt->execute();
    $materi = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$materi) {
        header('Location: materi-siswa.php');
        exit;
    }

    $stmtQuiz = $conn->prepare("
        SELECT id, judul, deskripsi, waktu
        FROM quizzes
        WHERE materi_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    if (!$stmtQuiz) {
        throw new RuntimeException('Gagal menyiapkan query quiz.');
    }

    $stmtQuiz->bind_param('i', $materiId);
    $stmtQuiz->execute();
    $quiz = $stmtQuiz->get_result()->fetch_assoc() ?: null;
    $stmtQuiz->close();

    if ($quiz) {
        $stmtQuestions = $conn->prepare(
            'SELECT COUNT(*) AS jumlah FROM questions WHERE quiz_id = ?'
        );

        if (!$stmtQuestions) {
            throw new RuntimeException('Gagal menyiapkan query soal.');
        }

        $quizId = (int) $quiz['id'];
        $stmtQuestions->bind_param('i', $quizId);
        $stmtQuestions->execute();
        $jumlahSoal = (int) (
            $stmtQuestions->get_result()->fetch_assoc()['jumlah'] ?? 0
        );
        $stmtQuestions->close();
    }
} catch (Throwable $error) {
    error_log('Lihat materi error: ' . $error->getMessage());
    $pageError = 'Materi belum dapat dimuat. Silakan coba lagi.';
}

$videoUrl = trim((string) ($materi['video_url'] ?? ''));

if ($videoUrl !== '') {
    $parts = parse_url($videoUrl);
    $host = strtolower($parts['host'] ?? '');
    $path = $parts['path'] ?? '';

    if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
        if (str_starts_with($path, '/watch')) {
            parse_str($parts['query'] ?? '', $query);
            $youtubeId = (string) ($query['v'] ?? '');
        } elseif (preg_match('~^/(?:embed|shorts)/([^/]+)~', $path, $matches)) {
            $youtubeId = $matches[1];
        }
    } elseif ($host === 'youtu.be') {
        $youtubeId = trim($path, '/');
    }

    if (!preg_match('/^[a-zA-Z0-9_-]{11}$/', $youtubeId)) {
        $youtubeId = '';
    }
}

$nama = $_SESSION['nama'] ?? 'Siswa';
$tanggal = '';
if (!empty($materi['created_at'])) {
    $time = strtotime((string) $materi['created_at']);
    if ($time !== false) {
        $tanggal = date('d M Y', $time);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($materi['judul'] ?? 'Materi') ?> - TKDSmart</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .lesson-page {
            width: min(1000px, calc(100% - 36px));
            margin: 32px auto 60px;
        }

        .lesson-hero {
            margin-bottom: 18px;
            padding: clamp(24px, 5vw, 40px);
            border: 1px solid #e9e0f2;
            border-radius: 16px;
            background:
                radial-gradient(ellipse at 90% 10%, rgba(135, 80, 197, .15), transparent 32%),
                linear-gradient(125deg, #f1eaff, #fff 72%);
        }

        .lesson-hero h1 {
            margin: 0;
            color: #25243b;
            font-size: clamp(26px, 4vw, 36px);
        }

        .lesson-meta {
            margin: 10px 0 0;
            color: #777587;
            font-size: 14px;
        }

        .lesson-card,
        .quiz-card {
            margin-top: 18px;
            padding: clamp(22px, 4vw, 32px);
            border: 1px solid #ecebf1;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(37, 36, 59, .05);
        }

        .lesson-description {
            margin-bottom: 25px;
            padding: 17px;
            border: 1px solid #e9e0f2;
            border-radius: 10px;
            background: #f8f4fc;
            color: #514b60;
            line-height: 1.7;
        }

        .lesson-card h2,
        .quiz-card h2 {
            margin: 0 0 15px;
            color: #25243b;
        }

        .lesson-content {
            color: #454356;
            font-size: 16px;
            line-height: 1.85;
            overflow-wrap: anywhere;
        }

        .video-section {
            margin-top: 30px;
        }

        .video-frame {
            position: relative;
            width: 100%;
            padding-bottom: 56.25%;
            overflow: hidden;
            border-radius: 12px;
            background: #17151d;
        }

        .video-frame iframe {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            border: 0;
        }

        .quiz-card {
            text-align: center;
        }

        .quiz-card > p {
            color: #777587;
            line-height: 1.65;
        }

        .quiz-info {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin: 22px 0;
        }

        .quiz-info-item {
            min-width: 140px;
            padding: 14px;
            border: 1px solid #ecebf1;
            border-radius: 10px;
            background: #f8f6fb;
            color: #777587;
            font-size: 13px;
        }

        .quiz-info-item strong {
            display: block;
            margin-bottom: 4px;
            color: #713cae;
            font-size: 20px;
        }

        .lesson-button {
            display: inline-flex;
            min-height: 42px;
            align-items: center;
            justify-content: center;
            padding: 10px 17px;
            border: 0;
            border-radius: 8px;
            background: #8750c5;
            color: #fff;
            font-weight: 700;
            text-decoration: none;
        }

        .lesson-button:hover {
            background: #713cae;
        }

        .lesson-button.secondary {
            border: 1px solid #e8e3ee;
            background: #f3eef9;
            color: #713cae;
        }

        .lesson-notice {
            margin-top: 16px;
            padding: 14px;
            border: 1px solid #ecebf1;
            border-radius: 9px;
            background: #f8f6fb;
            color: #777587;
        }

        .lesson-notice.error {
            border-color: #f0c9c7;
            background: #fff7f6;
            color: #a12622;
        }

        .lesson-actions {
            margin-top: 22px;
        }

        @media (max-width: 600px) {
            .lesson-page {
                margin-top: 20px;
            }

            .quiz-info {
                flex-direction: column;
            }

            .quiz-info-item {
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
        <a class="active" href="materi-siswa.php">Materi</a>
    </nav>
    <div class="account-nav">
        <span class="user-name"><?= e($nama) ?></span>
        <a class="logout-link" href="logout.php">Logout</a>
    </div>
</header>

<main class="lesson-page">
    <?php if ($pageError !== ''): ?>
        <div class="lesson-notice error" role="alert"><?= e($pageError) ?></div>
    <?php else: ?>
        <section class="lesson-hero">
            <p class="eyebrow">MATERI PEMBELAJARAN</p>
            <h1><?= e($materi['judul']) ?></h1>
            <p class="lesson-meta">
                Guru: <?= e($materi['nama_guru'] ?? 'Guru') ?>
                <?php if ($tanggal !== ''): ?> · <?= e($tanggal) ?><?php endif; ?>
            </p>
        </section>

        <article class="lesson-card">
            <?php if (trim((string) ($materi['deskripsi'] ?? '')) !== ''): ?>
                <div class="lesson-description">
                    <strong>Deskripsi materi</strong><br>
                    <?= nl2br(e($materi['deskripsi'])) ?>
                </div>
            <?php endif; ?>

            <h2>Isi materi</h2>
            <div class="lesson-content"><?= nl2br(e($materi['isi_materi'] ?? '')) ?></div>

            <?php if ($youtubeId !== ''): ?>
                <section class="video-section">
                    <h2>Video pembelajaran</h2>
                    <div class="video-frame">
                        <iframe
                            src="https://www.youtube-nocookie.com/embed/<?= e($youtubeId) ?>"
                            title="Video pembelajaran"
                            loading="lazy"
                            referrerpolicy="strict-origin-when-cross-origin"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            allowfullscreen
                        ></iframe>
                    </div>
                </section>
            <?php elseif ($videoUrl !== ''): ?>
                <div class="lesson-notice">
                    Tautan video tidak dikenali sebagai URL YouTube yang valid.
                </div>
            <?php endif; ?>
        </article>

        <section class="quiz-card">
            <p class="eyebrow">UJI PEMAHAMAN</p>
            <h2><?= $quiz ? e($quiz['judul']) : 'Quiz belum tersedia' ?></h2>

            <?php if ($quiz): ?>
                <?php if (trim((string) ($quiz['deskripsi'] ?? '')) !== ''): ?>
                    <p><?= nl2br(e($quiz['deskripsi'])) ?></p>
                <?php else: ?>
                    <p>Kerjakan quiz untuk menguji pemahaman materi ini.</p>
                <?php endif; ?>

                <div class="quiz-info">
                    <div class="quiz-info-item">
                        <strong><?= $jumlahSoal ?></strong>
                        soal
                    </div>
                    <div class="quiz-info-item">
                        <strong><?= (int) ($quiz['waktu'] ?? 0) ?></strong>
                        detik per soal
                    </div>
                </div>

                <?php if ($jumlahSoal > 0): ?>
                    <a class="lesson-button" href="quiz-lobby.php?id=<?= (int) $quiz['id'] ?>">
                        Mulai quiz
                    </a>
                <?php else: ?>
                    <div class="lesson-notice">Quiz belum memiliki soal.</div>
                <?php endif; ?>
            <?php else: ?>
                <p>Quiz untuk materi ini belum tersedia.</p>
            <?php endif; ?>
        </section>

    <?php endif; ?>
</main>

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> TKDSmart. Platform Pembelajaran Digital.</p>
</footer>
<script src="js/back-button.js" data-fallback="view/dashboard.php"></script>
</body>
</html>