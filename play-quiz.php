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
$quizId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

if ($quizId <= 0) {
    header('Location: view/dashboard.php');
    exit;
}

$pageError = '';
$quiz = null;
$game = $_SESSION['quiz_game'] ?? null;

try {
    // Mulai sesi quiz baru jika quiz atau pengguna berbeda.
    if (
        !is_array($game) ||
        (int) ($game['quiz_id'] ?? 0) !== $quizId ||
        (int) ($game['user_id'] ?? 0) !== $userId
    ) {
        $stmtQuiz = $conn->prepare(
            'SELECT id, judul, waktu FROM quizzes WHERE id = ? LIMIT 1'
        );

        if (!$stmtQuiz) {
            throw new RuntimeException('Gagal menyiapkan query quiz.');
        }

        $stmtQuiz->bind_param('i', $quizId);
        $stmtQuiz->execute();
        $quiz = $stmtQuiz->get_result()->fetch_assoc();
        $stmtQuiz->close();

        if (!$quiz) {
            header('Location: view/dashboard.php');
            exit;
        }

        $stmtQuestions = $conn->prepare("
            SELECT id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d
            FROM questions
            WHERE quiz_id = ?
            ORDER BY id ASC
        ");

        if (!$stmtQuestions) {
            throw new RuntimeException('Gagal menyiapkan query soal.');
        }

        $stmtQuestions->bind_param('i', $quizId);
        $stmtQuestions->execute();
        $questions = $stmtQuestions->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtQuestions->close();

        if (!$questions) {
            header('Location: quiz-lobby.php?id=' . $quizId);
            exit;
        }

        $_SESSION['quiz_game'] = [
            'quiz_id' => $quizId,
            'user_id' => $userId,
            'judul' => $quiz['judul'],
            'current' => 0,
            'questions' => $questions,
            'answers' => [],
            'started_at' => time(),
            'waktu' => max(1, (int) $quiz['waktu']),
        ];
    }

    $game = $_SESSION['quiz_game'];
    $questions = $game['questions'] ?? [];
    $questionCount = count($questions);
    $currentIndex = (int) ($game['current'] ?? 0);

    if ($questionCount === 0 || $currentIndex >= $questionCount) {
        header('Location: quiz-result.php?id=' . $quizId);
        exit;
    }

    $limitSeconds = max(1, (int) ($game['waktu'] ?? 1)) * 60;
    $elapsed = time() - (int) ($game['started_at'] ?? time());
    $remainingSeconds = $limitSeconds - $elapsed;

    // Proses jawaban sebelum menampilkan soal berikutnya.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($remainingSeconds <= 0) {
            header('Location: quiz-result.php?id=' . $quizId . '&timeout=1');
            exit;
        }

        $answer = strtoupper(trim($_POST['jawaban'] ?? ''));

        if (!in_array($answer, ['A', 'B', 'C', 'D'], true)) {
            $pageError = 'Pilih salah satu jawaban sebelum melanjutkan.';
        } else {
            $question = $questions[$currentIndex];

            $_SESSION['quiz_game']['answers'][(int) $question['id']] = $answer;
            $_SESSION['quiz_game']['current'] = $currentIndex + 1;

            if ($_SESSION['quiz_game']['current'] >= $questionCount) {
                header('Location: quiz-result.php?id=' . $quizId);
            } else {
                header('Location: play-quiz.php?id=' . $quizId);
            }
            exit;
        }
    }

    // Waktu habis: arahkan ke halaman hasil, bukan submit form kosong.
    if ($remainingSeconds <= 0) {
        header('Location: quiz-result.php?id=' . $quizId . '&timeout=1');
        exit;
    }

    $question = $questions[$currentIndex];
    $questionNumber = $currentIndex + 1;
    $progress = ($questionNumber / $questionCount) * 100;
    $quizTitle = $game['judul'] ?? 'Quiz';
} catch (Throwable $error) {
    error_log('Play quiz error: ' . $error->getMessage());
    $pageError = 'Quiz belum dapat dimuat. Silakan coba lagi.';
    $remainingSeconds = 0;
    $question = null;
    $questionNumber = 0;
    $questionCount = 0;
    $progress = 0;
    $quizTitle = 'Quiz';
}

$nama = $_SESSION['nama'] ?? 'Siswa';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($quizTitle) ?> - TKDSmart</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .play-page {
            width: min(850px, calc(100% - 40px));
            margin: 32px auto 58px;
        }

        .play-heading,
        .question-card {
            border: 1px solid #ecebf1;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(37, 36, 59, .05);
        }

        .play-heading {
            margin-bottom: 16px;
            padding: 22px 25px;
            background: linear-gradient(125deg, #f1eaff, #fff 75%);
        }

        .play-heading h1 {
            margin: 0;
            color: #25243b;
            font-size: clamp(21px, 4vw, 27px);
        }

        .play-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
        }

        .question-number {
            color: #343348;
            font-size: 14px;
            font-weight: 700;
        }

        .timer {
            min-width: 88px;
            padding: 8px 12px;
            border-radius: 8px;
            background: #f3eef9;
            color: #713cae;
            font-variant-numeric: tabular-nums;
            font-weight: 800;
            text-align: center;
        }

        .timer.is-low {
            background: #fff0ef;
            color: #a12622;
        }

        .progress-track {
            height: 8px;
            margin-top: 17px;
            overflow: hidden;
            border-radius: 20px;
            background: #efedf3;
        }

        .progress-value {
            height: 100%;
            border-radius: inherit;
            background: #8750c5;
            transition: width .25s ease;
        }

        .question-card {
            padding: clamp(22px, 5vw, 34px);
        }

        .question-text {
            margin: 0 0 24px;
            color: #25243b;
            font-size: clamp(18px, 3vw, 22px);
            font-weight: 700;
            line-height: 1.55;
            overflow-wrap: anywhere;
        }

        .answer-option {
            display: block;
            margin-top: 11px;
        }

        .answer-option input {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
        }

        .answer-option label {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 15px;
            border: 1px solid #e5e2eb;
            border-radius: 10px;
            background: #fff;
            color: #343348;
            cursor: pointer;
            line-height: 1.55;
            transition: border-color .2s, background .2s, box-shadow .2s;
        }

        .answer-option label:hover {
            border-color: #cbb5e8;
            background: #fbf9fe;
        }

        .answer-option input:checked + label {
            border-color: #8750c5;
            background: #f7f2fc;
            box-shadow: 0 0 0 2px rgba(135, 80, 197, .1);
        }

        .answer-option input:focus-visible + label {
            outline: 3px solid rgba(135, 80, 197, .25);
            outline-offset: 2px;
        }

        .option-letter {
            display: inline-flex;
            width: 30px;
            height: 30px;
            flex: 0 0 30px;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: #f1eef5;
            color: #713cae;
            font-size: 13px;
            font-weight: 800;
        }

        .next-button {
            width: 100%;
            min-height: 46px;
            margin-top: 22px;
            border: 0;
            border-radius: 8px;
            background: #8750c5;
            color: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s, transform .2s;
        }

        .next-button:hover {
            transform: translateY(-1px);
            background: #713cae;
        }

        .play-error {
            margin-bottom: 16px;
            padding: 12px 14px;
            border: 1px solid #f0c9c7;
            border-left: 4px solid #b42318;
            border-radius: 8px;
            background: #fff7f6;
            color: #a12622;
        }

        @media (max-width: 600px) {
            .play-page {
                width: min(100% - 30px, 850px);
                margin-top: 20px;
            }

            .play-heading {
                padding: 18px;
            }

            .play-top {
                align-items: flex-start;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                transition-duration: .01ms !important;
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
        <span class="user-name"><?= e($nama) ?></span>
        <a class="logout-link" href="logout.php">Logout</a>
    </div>
</header>

<main class="play-page">
    <?php if ($pageError !== ''): ?>
        <div class="play-error" role="alert"><?= e($pageError) ?></div>
        <a class="button button-secondary" href="view/dashboard.php">Kembali ke dashboard</a>
    <?php elseif ($question !== null): ?>
        <section class="play-heading">
            <div class="play-top">
                <div>
                    <p class="eyebrow"><?= e($quizTitle) ?></p>
                    <div class="question-number">
                        Soal <?= (int) $questionNumber ?> dari <?= (int) $questionCount ?>
                    </div>
                </div>
                <div class="timer" id="timer" role="timer" aria-live="off">--:--</div>
            </div>

            <div
                class="progress-track"
                role="progressbar"
                aria-label="Progres quiz"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="<?= (int) round($progress) ?>"
            >
                <div class="progress-value" style="width: <?= (float) $progress ?>%"></div>
            </div>
        </section>

        <section class="question-card">
            <?php if ($pageError !== ''): ?>
                <div class="play-error" role="alert"><?= e($pageError) ?></div>
            <?php endif; ?>

            <h2 class="question-text"><?= nl2br(e($question['pertanyaan'] ?? '')) ?></h2>

            <form method="POST" id="quizForm">
                <?php
                $options = [
                    'A' => $question['opsi_a'] ?? '',
                    'B' => $question['opsi_b'] ?? '',
                    'C' => $question['opsi_c'] ?? '',
                    'D' => $question['opsi_d'] ?? '',
                ];
                ?>

                <?php foreach ($options as $letter => $optionText): ?>
                    <div class="answer-option">
                        <input
                            type="radio"
                            name="jawaban"
                            value="<?= e($letter) ?>"
                            id="option<?= e($letter) ?>"
                            required
                        >
                        <label for="option<?= e($letter) ?>">
                            <span class="option-letter"><?= e($letter) ?></span>
                            <span><?= e($optionText) ?></span>
                        </label>
                    </div>
                <?php endforeach; ?>

                <button class="next-button" type="submit">
                    <?= $questionNumber < $questionCount ? 'Soal berikutnya' : 'Selesaikan quiz' ?>
                </button>
            </form>
        </section>
    <?php endif; ?>
</main>

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> TKDSmart. Platform Pembelajaran Digital.</p>
</footer>

<?php if ($pageError === '' && isset($remainingSeconds) && $remainingSeconds > 0): ?>
<script>
(() => {
    let remainingSeconds = <?= (int) $remainingSeconds ?>;
    const timer = document.getElementById('timer');
    const resultUrl = <?= json_encode(
        'quiz-result.php?id=' . $quizId . '&timeout=1',
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;

    function updateTimer() {
        if (remainingSeconds <= 0) {
            window.location.replace(resultUrl);
            return;
        }

        const minutes = Math.floor(remainingSeconds / 60);
        const seconds = remainingSeconds % 60;

        timer.textContent =
            String(minutes).padStart(2, '0') + ':' +
            String(seconds).padStart(2, '0');

        if (remainingSeconds <= 30) {
            timer.classList.add('is-low');
        }

        remainingSeconds -= 1;
    }

    updateTimer();
    window.setInterval(updateTimer, 1000);
})();
</script>
<?php endif; ?>
<script src="js/back-button.js" data-fallback="view/dashboard.php"></script>
</body>
</html>