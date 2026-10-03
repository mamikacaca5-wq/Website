<?php
// filepath: c:\xampp\htdocs\quizmaster\join-quiz.php
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
$error = '';
$kodeQuiz = '';

try {
    $stmtUser = $conn->prepare(
        'SELECT id, nama, role FROM users WHERE id = ? LIMIT 1'
    );

    if (!$stmtUser) {
        throw new RuntimeException('Gagal menyiapkan query pengguna.');
    }

    $stmtUser->bind_param('i', $userId);
    $stmtUser->execute();
    $user = $stmtUser->get_result()->fetch_assoc();
    $stmtUser->close();

    if (!$user) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit;
    }

    $_SESSION['nama'] = $user['nama'];
    $_SESSION['role'] = $user['role'];

    if ($user['role'] !== 'siswa') {
        header('Location: view/dashboard.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $kodeQuiz = trim($_POST['kode_quiz'] ?? '');

        if (!preg_match('/^[0-9]{6}$/', $kodeQuiz)) {
            $error = 'Kode quiz harus terdiri dari 6 angka.';
        } else {
            $stmtQuiz = $conn->prepare(
                'SELECT id FROM quizzes WHERE kode_quiz = ? LIMIT 1'
            );

            if (!$stmtQuiz) {
                throw new RuntimeException('Gagal menyiapkan pencarian quiz.');
            }

            $stmtQuiz->bind_param('s', $kodeQuiz);
            $stmtQuiz->execute();
            $quiz = $stmtQuiz->get_result()->fetch_assoc();
            $stmtQuiz->close();

            if (!$quiz) {
                $error = 'Kode quiz tidak ditemukan.';
            } else {
                $quizId = (int) $quiz['id'];
                $stmtQuestions = $conn->prepare(
                    'SELECT COUNT(*) AS jumlah_soal FROM questions WHERE quiz_id = ?'
                );

                if (!$stmtQuestions) {
                    throw new RuntimeException('Gagal memeriksa soal quiz.');
                }

                $stmtQuestions->bind_param('i', $quizId);
                $stmtQuestions->execute();
                $questionData = $stmtQuestions->get_result()->fetch_assoc();
                $stmtQuestions->close();

                if ((int) ($questionData['jumlah_soal'] ?? 0) < 1) {
                    $error = 'Quiz ini belum memiliki soal.';
                } else {
                    header('Location: quiz-lobby.php?id=' . $quizId);
                    exit;
                }
            }
        }
    }
} catch (Throwable $exception) {
    error_log('Join quiz error: ' . $exception->getMessage());
    $error = 'Quiz belum dapat diperiksa. Silakan coba lagi.';
}

$nama = $_SESSION['nama'] ?? 'Siswa';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gabung Quiz - TKDSmart</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .join-page {
            width: min(760px, calc(100% - 36px));
            margin: 34px auto 60px;
        }

        .join-hero {
            margin-bottom: 18px;
            padding: clamp(24px, 5vw, 38px);
            border: 1px solid #e9e0f2;
            border-radius: 16px;
            background:
                radial-gradient(ellipse at 90% 10%, rgba(135, 80, 197, .15), transparent 32%),
                linear-gradient(125deg, #f1eaff, #fff 72%);
        }

        .join-hero h1 {
            margin: 0;
            color: #25243b;
            font-size: clamp(27px, 4vw, 35px);
        }

        .join-hero p:last-child {
            margin: 8px 0 0;
            color: #777587;
        }

        .join-card {
            padding: clamp(22px, 5vw, 36px);
            border: 1px solid #ecebf1;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(37, 36, 59, .05);
        }

        .join-card label {
            display: block;
            margin-bottom: 9px;
            color: #343348;
            font-size: 14px;
            font-weight: 700;
        }

        .code-input {
            width: 100%;
            min-height: 58px;
            padding: 12px;
            border: 1px solid #dedce5;
            border-radius: 9px;
            background: #fff;
            color: #713cae;
            font: inherit;
            font-size: 25px;
            font-weight: 800;
            letter-spacing: 8px;
            text-align: center;
        }

        .code-input:focus {
            border-color: #8750c5;
            outline: 3px solid rgba(135, 80, 197, .13);
        }

        .join-hint {
            margin: 8px 0 0;
            color: #777587;
            font-size: 13px;
        }

        .join-error {
            margin-bottom: 18px;
            padding: 12px 14px;
            border: 1px solid #f0c9c7;
            border-left: 4px solid #b42318;
            border-radius: 8px;
            background: #fff7f6;
            color: #a12622;
            font-size: 14px;
        }

        .join-actions {
            display: flex;
            gap: 11px;
            margin-top: 22px;
        }

        .join-button {
            display: inline-flex;
            min-height: 44px;
            flex: 1;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border: 1px solid #b42318;
            border-radius: 8px;
            background: #b42318;
            color: #fff;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .join-button:hover {
            border-color: #8f1d14;
            background: #8f1d14;
        }

        .join-button.secondary {
            border: 1px solid #e8e3ee;
            background: #f3eef9;
            color: #713cae;
        }

        .join-button.secondary:hover {
            background: #ece3f6;
        }

        @media (max-width: 520px) {
            .join-page {
                margin-top: 22px;
            }

            .join-actions {
                flex-direction: column-reverse;
            }
        }
    </style>
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php">TKD<span>Smart</span></a>

    <nav class="main-nav" aria-label="Navigasi utama">
        <a href="view/dashboard.php">Dashboard</a>
        <a class="active" href="join-quiz.php">Gabung quiz</a>
        <a href="materi-siswa.php">Materi</a>
    </nav>

    <div class="account-nav">
        <span class="user-name"><?= e($nama) ?></span>
        <a class="logout-link" href="logout.php">Logout</a>
    </div>
</header>

<main class="join-page">
    <section class="join-hero">
        <p class="eyebrow">RUANG BELAJAR</p>
        <h1>Gabung quiz</h1>
        <p>Masukkan kode enam angka yang diberikan oleh guru untuk memulai.</p>
    </section>

    <section class="join-card">
        <?php if ($error !== ''): ?>
            <div class="join-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="join-quiz.php">
            <label for="kode_quiz">Kode quiz</label>
            <input
                class="code-input"
                type="text"
                id="kode_quiz"
                name="kode_quiz"
                value="<?= e($kodeQuiz) ?>"
                placeholder="000000"
                maxlength="6"
                pattern="[0-9]{6}"
                inputmode="numeric"
                autocomplete="off"
                aria-describedby="kode-hint"
                required
            >
            <p class="join-hint" id="kode-hint">Kode harus terdiri dari 6 angka.</p>

            <div class="join-actions">
                <a class="join-button secondary" href="view/dashboard.php">Kembali</a>
                <button class="join-button" type="submit">Gabung quiz</button>
            </div>
        </form>
    </section>
</main>

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> TKDSmart. Platform Pembelajaran Digital.</p>
</footer>
<script src="js/back-button.js" data-fallback="view/dashboard.php"></script>
</body>
</html>