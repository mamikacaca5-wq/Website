<?php
// filepath: c:\xampp\htdocs\quizmaster\teacher-stats.php
session_start();

require_once __DIR__ . '/config/database.php';

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'guru'
) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$nama = $_SESSION['nama'] ?? 'Guru';
$statistik = [];
$pageError = '';

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

try {
    $sql = "
        SELECT
            q.id,
            q.judul,
            COUNT(r.id) AS jumlah_pengerjaan,
            COALESCE(ROUND(AVG(r.skor), 1), 0) AS rata_rata,
            COALESCE(MAX(r.skor), 0) AS skor_tertinggi
        FROM quizzes q
        LEFT JOIN results r ON r.quiz_id = q.id
        WHERE q.user_id = ?
        GROUP BY q.id, q.judul
        ORDER BY q.id DESC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException('Gagal menyiapkan query statistik.');
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();

    $result = $stmt->get_result();
    $statistik = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $stmt->close();
} catch (Throwable $error) {
    error_log('Teacher stats error: ' . $error->getMessage());
    $pageError = 'Statistik belum dapat dimuat. Silakan coba lagi.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Statistik Guru - TKDSmart</title>
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php">TKD<span>Smart</span></a>

    <nav class="main-nav" aria-label="Navigasi utama">
        <a href="view/dashboard.php">Dashboard</a>
        <a class="active" href="teacher-stats.php">Statistik</a>
        <a href="create-quiz.php">Buat quiz</a>
    </nav>

    <div class="account-nav">
        <span class="user-name"><?= e($nama) ?></span>
        <a class="logout-link" href="logout.php">Logout</a>
    </div>
</header>

<main class="page-content stats-page">
    <section class="stats-hero">
        <p class="eyebrow">RINGKASAN PERFORMA</p>
        <h1>Statistik quiz</h1>
        <p>Pantau pengerjaan dan skor siswa pada quiz yang Anda buat.</p>
    </section>

    <?php if ($pageError !== ''): ?>
        <div class="notice notice-error" role="alert"><?= e($pageError) ?></div>
    <?php elseif ($statistik): ?>
        <section class="teacher-stats-grid" aria-label="Statistik quiz">
            <?php foreach ($statistik as $row): ?>
                <article class="teacher-stat-card">
                    <div class="teacher-stat-card-heading">
                        <span class="teacher-stat-label">STATISTIK QUIZ</span>
                        <span class="teacher-stat-dot" aria-hidden="true"></span>
                    </div>

                    <h2><?= e($row['judul']) ?></h2>

                    <div class="teacher-stat-values">
                        <div class="teacher-stat-item">
                            <strong><?= (int) $row['jumlah_pengerjaan'] ?></strong>
                            <span>Pengerjaan</span>
                        </div>
                        <div class="teacher-stat-item">
                            <strong><?= e(number_format((float) $row['rata_rata'], 1)) ?></strong>
                            <span>Rata-rata skor</span>
                        </div>
                        <div class="teacher-stat-item">
                            <strong><?= e(number_format((float) $row['skor_tertinggi'], 1)) ?></strong>
                            <span>Skor tertinggi</span>
                        </div>
                    </div>

                    <a
                        class="card-button primary"
                        href="leaderboard.php?quiz_id=<?= (int) $row['id'] ?>"
                    >Lihat leaderboard</a>
                </article>
            <?php endforeach; ?>
        </section>
    <?php else: ?>
        <section class="empty-state">
            <h2>Belum ada statistik</h2>
            <p>Buat quiz terlebih dahulu. Statistik pengerjaan akan muncul di sini.</p>
            <a class="card-button primary" href="create-quiz.php">Buat quiz</a>
        </section>
    <?php endif; ?>
</main>

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> TKDSmart. Platform Pembelajaran Digital.</p>
</footer>
<script src="js/back-button.js" data-fallback="view/dashboard.php"></script>
</body>
</html>