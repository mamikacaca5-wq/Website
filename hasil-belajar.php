<?php
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

$guruId = (int) $_SESSION['user_id'];
$nama = $_SESSION['nama'] ?? 'Guru';
$results = [];
$pageError = '';

try {
    $stmt = $conn->prepare("
        SELECT
            r.id,
            r.skor,
            r.jumlah_benar,
            r.jumlah_salah,
            r.created_at,
            u.nama AS nama_siswa,
            u.email AS email_siswa,
            q.judul AS quiz_judul,
            m.judul AS materi_judul
        FROM results AS r
        INNER JOIN users AS u ON r.user_id = u.id
        INNER JOIN quizzes AS q ON r.quiz_id = q.id
        LEFT JOIN materi AS m ON q.materi_id = m.id
        WHERE q.user_id = ?
        ORDER BY r.created_at DESC, r.id DESC
    ");

    if (!$stmt) {
        throw new RuntimeException('Gagal menyiapkan query hasil belajar.');
    }

    $stmt->bind_param('i', $guruId);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $error) {
    error_log('Hasil belajar error: ' . $error->getMessage());
    $pageError = 'Hasil belajar belum dapat dimuat. Silakan coba lagi.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hasil Belajar - TKDSmart</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .results-page {
            width: min(1250px, calc(100% - 36px));
            margin: 32px auto 58px;
        }

        .results-hero {
            margin-bottom: 20px;
            padding: clamp(24px, 4vw, 36px);
            border: 1px solid #e9e0f2;
            border-radius: 16px;
            background:
                radial-gradient(ellipse at 90% 10%, rgba(135, 80, 197, .15), transparent 32%),
                linear-gradient(125deg, #f1eaff, #fff 72%);
        }

        .results-hero h1 {
            margin: 0;
            color: #25243b;
            font-size: clamp(27px, 4vw, 35px);
        }

        .results-hero p:last-child {
            margin: 8px 0 0;
            color: #777587;
        }

        .results-card {
            overflow: hidden;
            border: 1px solid #ecebf1;
            border-radius: 13px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(37, 36, 59, .05);
        }

        .results-table-wrap {
            overflow-x: auto;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .results-table th,
        .results-table td {
            padding: 14px 15px;
            border-bottom: 1px solid #ecebf1;
        }

        .results-table th {
            background: #f8f6fb;
            color: #656276;
            font-size: 12px;
            letter-spacing: .4px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .results-table td {
            color: #454356;
            font-size: 14px;
        }

        .results-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .results-table tbody tr:hover {
            background: #fbf9fe;
        }

        .student-name {
            color: #25243b;
            font-weight: 700;
        }

        .student-email {
            display: inline-block;
            margin-top: 3px;
            color: #777587;
            font-size: 12px;
        }

        .score {
            color: #713cae !important;
            font-size: 16px !important;
            font-weight: 800;
        }

        .results-message,
        .results-empty {
            padding: 30px 20px;
            color: #777587;
            text-align: center;
        }

        .results-message.error {
            margin-bottom: 18px;
            padding: 14px 16px;
            border: 1px solid #f0c9c7;
            border-radius: 9px;
            background: #fff7f6;
            color: #a12622;
            text-align: left;
        }

        @media (max-width: 700px) {
            .results-page {
                margin-top: 20px;
            }

            .results-table th,
            .results-table td {
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
        <a href="create-quiz.php">Buat quiz</a>
        <a class="active" href="hasil-belajar.php">Hasil belajar</a>
        <a href="teacher-stats.php">Statistik</a>
    </nav>

    <div class="account-nav">
        <span class="user-name"><?= e($nama) ?></span>
        <a class="logout-link" href="logout.php">Logout</a>
    </div>
</header>

<main class="results-page">
    <section class="results-hero">
        <p class="eyebrow">RINGKASAN PEMBELAJARAN</p>
        <h1>Hasil belajar siswa</h1>
        <p>Pantau hasil pengerjaan quiz yang telah Anda buat.</p>
    </section>

    <?php if ($pageError !== ''): ?>
        <div class="results-message error" role="alert"><?= e($pageError) ?></div>
    <?php endif; ?>

    <section class="results-card" aria-label="Daftar hasil belajar">
        <?php if ($pageError === ''): ?>
            <?php if ($results): ?>
                <div class="results-table-wrap">
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Siswa</th>
                                <th>Quiz</th>
                                <th>Materi</th>
                                <th>Benar</th>
                                <th>Salah</th>
                                <th>Nilai</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $index => $row): ?>
                                <?php
                                $timestamp = !empty($row['created_at'])
                                    ? strtotime((string) $row['created_at'])
                                    : false;
                                ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <span class="student-name"><?= e($row['nama_siswa']) ?></span><br>
                                        <span class="student-email"><?= e($row['email_siswa']) ?></span>
                                    </td>
                                    <td><?= e($row['quiz_judul']) ?></td>
                                    <td><?= e($row['materi_judul'] ?? '—') ?></td>
                                    <td><?= (int) $row['jumlah_benar'] ?></td>
                                    <td><?= (int) $row['jumlah_salah'] ?></td>
                                    <td class="score"><?= e(number_format((float) $row['skor'], 0)) ?></td>
                                    <td>
                                        <?= $timestamp !== false
                                            ? e(date('d-m-Y H:i', $timestamp))
                                            : '—' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="results-empty">
                    <h2>Belum ada hasil</h2>
                    <p>Hasil pengerjaan akan muncul setelah siswa mengerjakan quiz Anda.</p>
                    <a class="button button-primary" href="create-quiz.php">Buat quiz</a>
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