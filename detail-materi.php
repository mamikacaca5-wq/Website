<?php
session_start();

require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';
$materiId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

if ($materiId <= 0) {
    header('Location: view/dashboard.php');
    exit;
}

$materi = null;
$quiz = null;
$jumlahSoal = 0;
$pageError = '';
$youtubeId = '';
$videoUrl = '';

try {
    $stmt = $conn->prepare("
        SELECT
            m.id, m.user_id, m.judul, m.deskripsi, m.isi_materi,
            m.video_url, m.created_at, u.nama AS nama_guru
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
        header('Location: view/dashboard.php');
        exit;
    }

    $stmtQuiz = $conn->prepare("
        SELECT id, kode_quiz, judul, deskripsi, waktu
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
    error_log('Detail materi error: ' . $error->getMessage());
    $pageError = 'Detail materi belum dapat dimuat. Silakan coba lagi.';
}

$isGuruPemilik = $materi
    && $role === 'guru'
    && (int) $materi['user_id'] === $userId;

if ($materi) {
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
}

$nama = $_SESSION['nama'] ?? 'Pengguna';
$tanggal = '';

if (!empty($materi['created_at'])) {
    $timestamp = strtotime((string) $materi['created_at']);
    if ($timestamp !== false) {
        $tanggal = date('d M Y', $timestamp);
    }
}

$dashboardUrl = 'view/dashboard.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($materi['judul'] ?? 'Detail Materi') ?> - TKDSmart</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .detail-page {
            width: min(1000px, calc(100% - 36px));
            margin: 32px auto 58px;
        }

        .detail-hero {
            margin-bottom: 18px;
            padding: clamp(24px, 4vw, 38px);
            border: 1px solid #e9e0f2;
            border-radius: 16px;
            background:
                radial-gradient(ellipse at 90% 10%, rgba(135, 80, 197, .15), transparent 32%),
                linear-gradient(125deg, #f1eaff, #fff 72%);
        }

        .detail-hero h1 {
            margin: 0;
            color: #25243b;
            font-size: clamp(27px, 4vw, 36px);
            line-height: 1.25;
        }

        .detail-meta {
            margin: 10px 0 0;
            color: #777587;
            font-size: 14px;
        }

        .detail-card,
        .quiz-card {
            margin-top: 18px;
            padding: clamp(22px, 4vw, 32px);
            border: 1px solid #ecebf1;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(37, 36, 59, .05);
        }

        .detail-card h2,
        .quiz-card h2 {
            margin: 0 0 15px;
            color: #25243b;
        }

        .detail-description {
            margin-bottom: 26px;
            padding: 17px;
            border: 1px solid #e9e0f2;
            border-radius: 10px;
            background: #f8f4fc;
            color: #514b60;
            line-height: 1.75;
            overflow-wrap: anywhere;
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

        .detail-notice {
            margin-top: 18px;
            padding: 14px 16px;
            border: 1px solid #ecebf1;
            border-radius: 9px;
            background: #f8f6fb;
            color: #777587;
            line-height: 1.6;
        }

        .detail-notice.error {
            border-color: #f0c9c7;
            background: #fff7f6;
            color: #a12622;
        }

        .detail-notice.success {
            border-color: #c9ead7;
            background: #f1fbf5;
            color: #17663b;
        }

        .quiz-card {
            background: linear-gradient(145deg, #fff, #fcfaff);
        }

        .quiz-description {
            color: #777587;
            line-height: 1.7;
        }

        .quiz-info {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 20px 0;
        }

        .quiz-info-item {
            min-width: 140px;
            padding: 14px 16px;
            border: 1px solid #ecebf1;
            border-radius: 10px;
            background: #f8f6fb;
            color: #777587;
            font-size: 13px;
        }

        .quiz-info-item strong {
            display: block;
            margin-bottom: 5px;
            color: #713cae;
            font-size: 18px;
        }

        .detail-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }

        .detail-button {
            display: inline-flex;
            min-height: 42px;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 8px;
            background: #8750c5;
            color: #fff;
            font-weight: 700;
            text-decoration: none;
        }

        .detail-button:hover {
            background: #713cae;
        }

        .detail-button.secondary {
            border: 1px solid #e8e3ee;
            background: #f3eef9;
            color: #713cae;
        }

        .detail-button.secondary:hover {
            background: #ece3f6;
        }

        @media (max-width: 600px) {
            .detail-page {
                margin-top: 20px;
            }

            .quiz-info {
                flex-direction: column;
            }

            .quiz-info-item {
                width: 100%;
            }

            .detail-actions {
                flex-direction: column;
            }

            .detail-button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php">TKD<span>Smart</span></a>

    <nav class="main-nav" aria-label="Navigasi utama">
        <a href="<?= e($dashboardUrl) ?>">Dashboard</a>
        <?php if ($role === 'siswa'): ?>
            <a href="materi-siswa.php">Materi</a>
            <a href="history.php">Riwayat</a>
        <?php elseif ($role === 'guru'): ?>
            <a href="create-quiz.php">Kelola quiz</a>
            <a href="hasil-belajar.php">Hasil belajar</a>
        <?php endif; ?>
    </nav>

    <div class="account-nav">
        <span class="user-name"><?= e($nama) ?></span>
        <a class="logout-link" href="logout.php">Logout</a>
    </div>
</header>

<main class="detail-page">
    <?php if ($pageError !== ''): ?>
        <div class="detail-notice error" role="alert"><?= e($pageError) ?></div>
        <a class="detail-button secondary" href="<?= e($dashboardUrl) ?>">Kembali ke dashboard</a>
    <?php elseif ($materi): ?>
        <?php if (isset($_GET['created']) && $_GET['created'] === '1'): ?>
            <div class="detail-notice success" role="status">
                Quiz berhasil dibuat dan dihubungkan dengan materi ini.
                <?php if (!empty($_GET['code'])): ?>
                    Kode quiz: <strong><?= e($_GET['code']) ?></strong>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <section class="detail-hero">
            <p class="eyebrow">MATERI PEMBELAJARAN</p>
            <h1><?= e($materi['judul']) ?></h1>
            <p class="detail-meta">
                Guru: <?= e($materi['nama_guru']) ?>
                <?php if ($tanggal !== ''): ?> · <?= e($tanggal) ?><?php endif; ?>
            </p>
        </section>

        <article class="detail-card">
            <?php if (trim((string) ($materi['deskripsi'] ?? '')) !== ''): ?>
                <div class="detail-description">
                    <strong>Deskripsi materi</strong><br><br>
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
                <div class="detail-notice">
                    Tautan video tidak dikenali sebagai URL YouTube yang valid.
                </div>
            <?php endif; ?>
        </article>

        <section class="quiz-card">
            <p class="eyebrow">UJI PEMAHAMAN</p>
            <h2>Quiz materi</h2>

            <?php if ($quiz): ?>
                <h3><?= e($quiz['judul']) ?></h3>

                <?php if (trim((string) ($quiz['deskripsi'] ?? '')) !== ''): ?>
                    <p class="quiz-description"><?= nl2br(e($quiz['deskripsi'])) ?></p>
                <?php else: ?>
                    <p class="quiz-description">
                        Kerjakan quiz untuk menguji pemahaman materi ini.
                    </p>
                <?php endif; ?>

                <div class="quiz-info">
                    <?php if (!empty($quiz['kode_quiz'])): ?>
                        <div class="quiz-info-item">
                            <strong><?= e($quiz['kode_quiz']) ?></strong>
                            Kode quiz
                        </div>
                    <?php endif; ?>
                    <div class="quiz-info-item">
                        <strong><?= $jumlahSoal ?></strong>
                        Soal
                    </div>
                    <div class="quiz-info-item">
                        <strong><?= (int) $quiz['waktu'] ?></strong>
                        Detik per soal
                    </div>
                </div>

                <?php if ($isGuruPemilik): ?>
                    <a class="detail-button" href="host-quiz.php?id=<?= (int) $quiz['id'] ?>">
                        Kelola quiz
                    </a>
                <?php elseif ($jumlahSoal > 0): ?>
                    <a class="detail-button" href="quiz-lobby.php?id=<?= (int) $quiz['id'] ?>">
                        Mulai quiz
                    </a>
                <?php else: ?>
                    <div class="detail-notice">Quiz belum memiliki soal.</div>
                <?php endif; ?>
            <?php elseif ($isGuruPemilik): ?>
                <p class="quiz-description">Belum ada quiz untuk materi ini.</p>
                <a
                    class="detail-button"
                    href="create-quiz.php?materi_id=<?= (int) $materiId ?>"
                >Buat quiz untuk materi ini</a>
            <?php else: ?>
                <p class="quiz-description">Quiz untuk materi ini belum tersedia.</p>
            <?php endif; ?>
        </section>

        <div class="detail-actions">
            <a class="detail-button secondary" href="<?= e($dashboardUrl) ?>">
                ← Kembali ke dashboard
            </a>
        </div>
    <?php endif; ?>
</main>

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> TKDSmart. Platform Pembelajaran Digital.</p>
</footer>
<script src="js/back-button.js" data-fallback="view/dashboard.php"></script>
</body>
</html>