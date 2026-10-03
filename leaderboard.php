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

$nama = $_SESSION['nama'] ?? 'Pengguna';
$quizId = filter_input(INPUT_GET, 'quiz_id', FILTER_VALIDATE_INT);
$quizId = ($quizId !== false && $quizId !== null && $quizId > 0) ? $quizId : null;

$daftarKuis = [];
$peringkat = [];
$pageError = '';

try {
    $quizResult = $conn->query('SELECT id, judul FROM quizzes ORDER BY id DESC');
    if (!$quizResult) {
        throw new RuntimeException('Gagal mengambil daftar quiz.');
    }
    $daftarKuis = $quizResult->fetch_all(MYSQLI_ASSOC);
    $quizResult->free();

    if ($quizId !== null) {
        $stmt = $conn->prepare("
            SELECT u.nama, r.skor, r.jumlah_benar, r.jumlah_salah
            FROM results AS r
            INNER JOIN users AS u ON u.id = r.user_id
            WHERE r.quiz_id = ?
            ORDER BY r.skor DESC, r.jumlah_benar DESC, r.id ASC
            LIMIT 100
        ");

        if (!$stmt) {
            throw new RuntimeException('Gagal menyiapkan query peringkat.');
        }

        $stmt->bind_param('i', $quizId);
        $stmt->execute();
        $peringkat = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $result = $conn->query("
            SELECT u.nama, q.judul AS judul_quiz,
                   r.skor, r.jumlah_benar, r.jumlah_salah
            FROM results AS r
            INNER JOIN users AS u ON u.id = r.user_id
            INNER JOIN quizzes AS q ON q.id = r.quiz_id
            ORDER BY r.skor DESC, r.jumlah_benar DESC, r.id ASC
            LIMIT 100
        ");

        if (!$result) {
            throw new RuntimeException('Gagal mengambil data peringkat.');
        }

        $peringkat = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
    }
} catch (Throwable $error) {
    error_log('Leaderboard error: ' . $error->getMessage());
    $pageError = 'Data leaderboard belum dapat dimuat. Silakan coba lagi.';
}

$selectedQuizExists = false;
if ($quizId !== null) {
    foreach ($daftarKuis as $quiz) {
        if ((int) $quiz['id'] === $quizId) {
            $selectedQuizExists = true;
            break;
        }
    }

    if (!$selectedQuizExists && $pageError === '') {
        $quizId = null;
        $pageError = 'Quiz yang dipilih tidak ditemukan.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leaderboard - TKDSmart</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .leaderboard-page {
            width: min(1100px, calc(100% - 36px));
            margin: 32px auto 58px;
        }

        .leaderboard-hero {
            margin-bottom: 20px;
            padding: clamp(24px, 4vw, 36px);
            border: 1px solid #e9e0f2;
            border-radius: 16px;
            background:
                radial-gradient(ellipse at 90% 10%, rgba(135, 80, 197, .15), transparent 32%),
                linear-gradient(125deg, #f1eaff, #fff 72%);
        }

        .leaderboard-hero h1 {
            margin: 0;
            color: #25243b;
            font-size: clamp(27px, 4vw, 35px);
        }

        .leaderboard-hero p:last-child {
            margin: 8px 0 0;
            color: #777587;
        }

        .leaderboard-filter,
        .leaderboard-table-card {
            padding: 20px;
            border: 1px solid #ecebf1;
            border-radius: 13px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(37, 36, 59, .05);
        }

        .leaderboard-filter {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            margin-bottom: 18px;
        }

        .filter-field {
            flex: 1;
        }

        .filter-field label {
            display: block;
            margin-bottom: 7px;
            color: #343348;
            font-size: 14px;
            font-weight: 700;
        }

        .filter-field select {
            width: 100%;
            min-height: 42px;
            padding: 9px 12px;
            border: 1px solid #dedce5;
            border-radius: 8px;
            background: #fff;
            color: #343348;
            font: inherit;
        }

        .filter-field select:focus {
            border-color: #8750c5;
            outline: 3px solid rgba(135, 80, 197, .12);
        }

        .leaderboard-button {
            display: inline-flex;
            min-height: 42px;
            align-items: center;
            justify-content: center;
            padding: 9px 16px;
            border: 0;
            border-radius: 8px;
            background: #8750c5;
            color: #fff;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .leaderboard-button:hover {
            background: #713cae;
        }

        .leaderboard-table-card {
            overflow: hidden;
            padding: 0;
        }

        .table-scroll {
            overflow-x: auto;
        }

        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .leaderboard-table th,
        .leaderboard-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #ecebf1;
            white-space: nowrap;
        }

        .leaderboard-table th {
            background: #f8f6fb;
            color: #656276;
            font-size: 12px;
            letter-spacing: .4px;
            text-transform: uppercase;
        }

        .leaderboard-table td {
            color: #454356;
            font-size: 14px;
        }

        .leaderboard-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .leaderboard-table tbody tr:hover {
            background: #fbf9fe;
        }

        .rank-cell {
            color: #713cae !important;
            font-weight: 800;
        }

        .score-cell {
            color: #713cae !important;
            font-weight: 800;
        }

        .leaderboard-message {
            margin-bottom: 18px;
            padding: 14px 16px;
            border: 1px solid #f0c9c7;
            border-radius: 9px;
            background: #fff7f6;
            color: #a12622;
        }

        .empty-row {
            padding: 30px !important;
            color: #777587 !important;
            text-align: center;
            white-space: normal !important;
        }

        @media (max-width: 600px) {
            .leaderboard-page {
                margin-top: 20px;
            }

            .leaderboard-filter {
                align-items: stretch;
                flex-direction: column;
            }

            .leaderboard-button {
                width: 100%;
            }

            .leaderboard-table th,
            .leaderboard-table td {
                padding: 12px;
            }
        }
    </style>
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php">TKD<span>Smart</span></a>

    <nav class="main-nav" aria-label="Navigasi utama">
        <a href="view/dashboard.php">Dashboard</a>
        <a class="active" href="leaderboard.php">Leaderboard</a>
    </nav>

    <div class="account-nav">
        <span class="user-name"><?= e($nama) ?></span>
        <a class="logout-link" href="logout.php">Logout</a>
    </div>
</header>

<main class="leaderboard-page">
    <section class="leaderboard-hero">
        <p class="eyebrow">HASIL PEMBELAJARAN</p>
        <h1>Leaderboard</h1>
        <p>Lihat peringkat peserta berdasarkan skor dan jumlah jawaban benar.</p>
    </section>

    <?php if ($pageError !== ''): ?>
        <div class="leaderboard-message" role="alert"><?= e($pageError) ?></div>
    <?php endif; ?>

    <form method="GET" class="leaderboard-filter">
        <div class="filter-field">
            <label for="quiz_id">Filter berdasarkan quiz</label>
            <select name="quiz_id" id="quiz_id">
                <option value="">Semua quiz</option>
                <?php foreach ($daftarKuis as $quiz): ?>
                    <option
                        value="<?= (int) $quiz['id'] ?>"
                        <?= $quizId === (int) $quiz['id'] ? 'selected' : '' ?>
                    >
                        <?= e($quiz['judul']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="leaderboard-button">Tampilkan</button>
    </form>

    <section class="leaderboard-table-card">
        <div class="table-scroll">
            <table class="leaderboard-table">
                <thead>
                    <tr>
                        <th>Peringkat</th>
                        <th>Nama peserta</th>
                        <?php if ($quizId === null): ?>
                            <th>Nama quiz</th>
                        <?php endif; ?>
                        <th>Skor</th>
                        <th>Benar</th>
                        <th>Salah</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($pageError === ''): ?>
                        <?php if ($peringkat): ?>
                            <?php foreach ($peringkat as $index => $row): ?>
                                <?php
                                $rank = $index + 1;
                                $medals = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
                                ?>
                                <tr>
                                    <td class="rank-cell">
                                        <?= $medals[$rank] ?? (string) $rank ?>
                                    </td>
                                    <td><?= e($row['nama']) ?></td>
                                    <?php if ($quizId === null): ?>
                                        <td><?= e($row['judul_quiz']) ?></td>
                                    <?php endif; ?>
                                    <td class="score-cell">
                                        <?= e(number_format((float) $row['skor'], 0)) ?>
                                    </td>
                                    <td><?= (int) $row['jumlah_benar'] ?></td>
                                    <td><?= (int) $row['jumlah_salah'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td
                                    class="empty-row"
                                    colspan="<?= $quizId === null ? 6 : 5 ?>"
                                >
                                    Belum ada hasil pengerjaan quiz.
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> TKDSmart. Platform Pembelajaran Digital.</p>
</footer>
<script src="js/back-button.js" data-fallback="view/dashboard.php"></script>
</body>
</html>