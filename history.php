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

$userId = (int) $_SESSION['user_id'];
$nama = $_SESSION['nama'] ?? 'Siswa';
$riwayat = [];
$pageError = '';

try {
    $stmt = $conn->prepare("
        SELECT
            r.id,
            r.skor,
            r.jumlah_benar,
            r.jumlah_salah,
            r.created_at,
            q.judul AS quiz_judul,
            m.id AS materi_id,
            m.judul AS materi_judul
        FROM results AS r
        INNER JOIN quizzes AS q ON q.id = r.quiz_id
        LEFT JOIN materi AS m ON m.id = q.materi_id
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC, r.id DESC
    ");

    if (!$stmt) {
        throw new RuntimeException('Gagal menyiapkan query riwayat.');
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $riwayat = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $error) {
    error_log('History page error: ' . $error->getMessage());
    $pageError = 'Riwayat quiz belum dapat dimuat. Silakan coba lagi.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Riwayat Nilai - TKDSmart</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .history-page {
            width: min(1200px, calc(100% - 36px));
            margin: 32px auto 58px;
        }

        .history-hero {
            margin-bottom: 20px;
            padding: clamp(24px, 4vw, 36px);
            border: 1px solid #e9e0f2;
            border-radius: 16px;
            background:
                radial-gradient(ellipse at 90% 10%, rgba(135, 80, 197, .15), transparent 32%),
                linear-gradient(125deg, #f1eaff, #fff 72%);
        }

        .history-hero h1 {
            margin: 0;
            color: #25243b;
            font-size: clamp(27px, 4vw, 35px);
        }

        .history-hero p:last-child {
            margin: 8px 0 0;
            color: #777587;
        }

        .history-card {
            overflow: hidden;
            border: 1px solid #ecebf1;
            border-radius: 13px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(37, 36, 59, .05);
        }

        .history-table-wrap {
            overflow-x: auto;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .history-table th,
        .history-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #ecebf1;
        }

        .history-table th {
            background: #f8f6fb;
            color: #656276;
            font-size: 12px;
            letter-spacing: .4px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .history-table td {
            color: #454356;
            font-size: 14px;
        }

        .history-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .history-table tbody tr:hover {
            background: #fbf9fe;
        }

        .score {
            font-weight: 800;
        }

        .score-good {
            color: #16804a;
        }

        .score-medium {
            color: #a86608;
        }

        .score-low {
            color: #b42318;
        }

        .materi-link {
            color: #713cae;
            font-weight: 700;
            text-decoration: none;
        }

        .materi-link:hover {
            text-decoration: underline;
        }

        .history-message,
        .empty-state {
            padding: 30px 20px;
            color: #777587;
            text-align: center;
        }

        .history-message.error {
            margin-bottom: 18px;
            padding: 14px 16px;
            border: 1px solid #f0c9c7;
            border-radius: 9px;
            background: #fff7f6;
            color: #a12622;
            text-align: left;
        }

        .history-action {
            display: inline-flex;
            min-height: 40px;
            align-items: center;
            justify-content: center;
            margin-top: 10px;
            padding: 9px 15px;
            border-radius: 8px;
            background: #8750c5;
            color: #fff;
            font-weight: 700;
            text-decoration: none;
        }

        .history-action:hover {
            background: #713cae;
        }

        @media (max-width: 700px) {
            .history-page {
                margin-top: 20px;
            }

            .history-table th,
            .history-table td {
                padding: 12px;
                white-space: nowrap;
            }
        }
    </style>
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php">TKD<span>Smart</span></a>

    <nav class="main-nav" aria-label="Navigasi utama">
        <a href="view/dashboard.php">Dashboard</a>
        <a class="active" href="history.php">Riwayat</a>
        <a href="materi-siswa.php">Materi</a>
    </nav>

    <div class="account-nav">
        <span class="user-name"><?= e($nama) ?></span>
        <a class="logout-link" href="logout.php">Logout</a>
    </div>
</header>

<main class="history-page">
    <section class="history-hero">
        <p class="eyebrow">PERFORMA BELAJAR</p>
        <h1>Riwayat nilai</h1>
        <p>Lihat hasil quiz yang telah kamu kerjakan.</p>
    </section>

    <?php if ($pageError !== ''): ?>
        <div class="history-message error" role="alert"><?= e($pageError) ?></div>
    <?php endif; ?>

    <section class="history-card" aria-label="Riwayat pengerjaan quiz">
        <?php if ($pageError === ''): ?>
            <?php if ($riwayat): ?>
                <div class="history-table-wrap">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Quiz</th>
                                <th>Materi</th>
                                <th>Benar</th>
                                <th>Salah</th>
                                <th>Nilai</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($riwayat as $index => $row): ?>
                                <?php
                                $skor = (float) ($row['skor'] ?? 0);
                                $scoreClass = $skor >= 80
                                    ? 'score-good'
                                    : ($skor >= 60 ? 'score-medium' : 'score-low');

                                $timestamp = !empty($row['created_at'])
                                    ? strtotime((string) $row['created_at'])
                                    : false;
                                ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><strong><?= e($row['quiz_judul']) ?></strong></td>
                                    <td>
                                        <?php if (!empty($row['materi_id'])): ?>
                                            <a
                                                class="materi-link"
                                                href="lihat-materi.php?id=<?= (int) $row['materi_id'] ?>"
                                            ><?= e($row['materi_judul'] ?? 'Lihat materi') ?></a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td><?= (int) ($row['jumlah_benar'] ?? 0) ?></td>
                                    <td><?= (int) ($row['jumlah_salah'] ?? 0) ?></td>
                                    <td>
                                        <span class="score <?= e($scoreClass) ?>">
                                            <?= e(number_format($skor, 0)) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $timestamp !== false ? e(date('d-m-Y H:i', $timestamp)) : '—' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <h2>Belum ada riwayat quiz</h2>
                    <p>Hasil quiz yang kamu kerjakan akan muncul di sini.</p>
                    <a class="history-action" href="materi-siswa.php">Lihat materi</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> TKDSmart. Platform Pembelajaran Digital.</p>
</footer>
<script src="js/back-button.js" data-fallback="view/dashboard.php"></script>
</body>
</html>