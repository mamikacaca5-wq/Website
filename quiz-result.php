<?php
session_start();

require_once "config/database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'siswa') {
    header('Location: view/dashboard.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Ambil quiz ID
|--------------------------------------------------------------------------
*/
$quiz_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

/*
|--------------------------------------------------------------------------
| Jika ID tidak ada, coba ambil dari session
|--------------------------------------------------------------------------
*/
if ($quiz_id <= 0 && isset($_SESSION['quiz_game']['quiz_id'])) {
    $quiz_id = (int) $_SESSION['quiz_game']['quiz_id'];
}

if ($quiz_id <= 0) {
    header("Location: materi-siswa.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Pastikan session quiz sesuai dengan quiz yang dikerjakan
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION['quiz_game']) ||
    (int) $_SESSION['quiz_game']['quiz_id'] !== $quiz_id
) {
    header("Location: materi-siswa.php");
    exit;
}

$game = $_SESSION['quiz_game'];

$questions = $game['questions'] ?? [];
$answers = $game['answers'] ?? [];

if (empty($questions)) {
    header("Location: materi-siswa.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Ambil informasi quiz
|--------------------------------------------------------------------------
*/
$stmt_quiz = $conn->prepare("
    SELECT
        q.id,
        q.kode_quiz,
        q.judul,
        q.deskripsi,
        q.materi_id,
        m.judul AS materi_judul
    FROM quizzes q
    LEFT JOIN materi m
        ON q.materi_id = m.id
    WHERE q.id = ?
    LIMIT 1
");

$stmt_quiz->bind_param("i", $quiz_id);
$stmt_quiz->execute();

$result_quiz = $stmt_quiz->get_result();

if ($result_quiz->num_rows === 0) {
    unset($_SESSION['quiz_game']);

    header("Location: materi-siswa.php");
    exit;
}

$quiz = $result_quiz->fetch_assoc();

/*
|--------------------------------------------------------------------------
| Hitung jawaban benar dan salah
|--------------------------------------------------------------------------
*/
$jumlah_benar = 0;
$jumlah_salah = 0;

foreach ($questions as $question) {

    $question_id = (int) $question['id'];

    $jawaban_siswa = $answers[$question_id] ?? '';

    $jawaban_benar = strtoupper(
        trim($question['jawaban_benar'])
    );

    if (
        $jawaban_siswa !== '' &&
        strtoupper($jawaban_siswa) === $jawaban_benar
    ) {
        $jumlah_benar++;
    } else {
        $jumlah_salah++;
    }
}

/*
|--------------------------------------------------------------------------
| Hitung jumlah soal
|--------------------------------------------------------------------------
*/
$jumlah_soal = count($questions);

/*
|--------------------------------------------------------------------------
| Hitung nilai
|--------------------------------------------------------------------------
*/
if ($jumlah_soal > 0) {
    $skor = round(
        ($jumlah_benar / $jumlah_soal) * 100
    );
} else {
    $skor = 0;
}

/*
|--------------------------------------------------------------------------
| Cek apakah hasil sudah pernah disimpan
|--------------------------------------------------------------------------
*/
$stmt_check = $conn->prepare("
    SELECT id
    FROM results
    WHERE quiz_id = ?
      AND user_id = ?
      AND created_at >= ?
    ORDER BY id DESC
    LIMIT 1
");

/*
|--------------------------------------------------------------------------
| Gunakan waktu mulai sebagai batas pengecekan
|--------------------------------------------------------------------------
*/
$started_at_timestamp = $game['started_at'] ?? time();

$started_at = date(
    'Y-m-d H:i:s',
    $started_at_timestamp
);

$stmt_check->bind_param(
    "iis",
    $quiz_id,
    $user_id,
    $started_at
);

$stmt_check->execute();

$result_check = $stmt_check->get_result();

$resultSaveError = '';

/*
|--------------------------------------------------------------------------
| Simpan hasil hanya jika belum tersimpan
|--------------------------------------------------------------------------
*/
if ($result_check->num_rows === 0) {

    $stmt_insert = $conn->prepare("
        INSERT INTO results
        (quiz_id, user_id, skor, jumlah_benar, jumlah_salah)
    VALUES (?, ?, ?, ?, ?)
");

    if (!$stmt_insert) {
        error_log('Gagal menyiapkan penyimpanan hasil quiz: ' . $conn->error);
        $resultSaveError = 'Hasil quiz belum berhasil disimpan.';
    } else {
        $stmt_insert->bind_param(
            "iiiii",
            $quiz_id,
            $user_id,
            $skor,
            $jumlah_benar,
            $jumlah_salah
        );

        if (!$stmt_insert->execute()) {
            error_log('Gagal menyimpan hasil quiz: ' . $stmt_insert->error);
            $resultSaveError = 'Hasil quiz belum berhasil disimpan.';
        }

        $stmt_insert->close();
    }

}

/*
|--------------------------------------------------------------------------
| Bersihkan session quiz
|--------------------------------------------------------------------------
|
| Penting:
| Setelah hasil disimpan, session quiz dihapus.
| Ini mencegah quiz sebelumnya terbawa ketika siswa
| mengerjakan quiz lain.
|
*/
unset($_SESSION['quiz_game']);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Hasil Quiz - TKDSmart</title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <link
        rel="stylesheet"
        href="css/dashboard.css"
    >

    <style>

        body {
            min-height: 100vh;
            background: #f8f8fb;
            color: #343348;
            font-family: Arial, Helvetica, sans-serif;
        }

        .result-container {
            width: min(760px, calc(100% - 32px));
            margin: 38px auto 60px;
        }

        .result-card {
            padding: clamp(24px, 5vw, 44px);
            border: 1px solid #ecebf1;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(37, 36, 59, .06);
            text-align: center;
        }

        .result-icon {
            margin-bottom: 10px;
            color: #8750c5;
            font-size: 52px;
        }

        .result-card h1 {
            margin: 0 0 8px;
            color: #25243b;
        }

        .quiz-title,
        .score-label {
            color: #777587;
        }

        .quiz-title {
            margin-bottom: 20px;
        }

        .materi-name {
            margin: 18px 0;
            padding: 13px 16px;
            border: 1px solid #e9e0f2;
            border-radius: 9px;
            background: #f7f2fc;
            color: #713cae;
        }

        .score {
            margin: 20px 0 0;
            color: #8750c5;
            font-size: clamp(56px, 12vw, 76px);
            font-weight: 800;
            line-height: 1.1;
        }

        .score-label {
            margin-bottom: 25px;
        }

        .result-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin: 25px 0;
        }

        .stat-box {
            padding: 17px 12px;
            border: 1px solid #ecebf1;
            border-radius: 10px;
            background: #f8f6fb;
        }

        .stat-number {
            color: #713cae;
            font-size: 27px;
            font-weight: 700;
        }

        .stat-label {
            margin-top: 4px;
            color: #777587;
            font-size: 13px;
        }

        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 26px;
        }

        .btn {
            flex: 1;
            display: inline-flex;
            min-height: 44px;
            align-items: center;
            justify-content: center;
            padding: 10px 14px;
            border-radius: 8px;
            font-weight: 700;
            text-decoration: none;
        }

        .btn-primary {
            background: #8750c5;
            color: #fff;
        }

        .btn-primary:hover {
            background: #713cae;
        }

        .btn-secondary {
            border: 1px solid #e8e3ee;
            background: #f3eef9;
            color: #713cae;
        }

        .save-warning {
            margin: 16px 0;
            padding: 12px;
            border: 1px solid #f0c9c7;
            border-radius: 8px;
            background: #fff7f6;
            color: #a12622;
        }

        @media (max-width: 600px) {

            .result-container {
                margin-top: 22px;
            }

            .result-stats {
                grid-template-columns: 1fr;
            }

            .button-group {
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
        <a class="active" href="history.php">Riwayat</a>
    </nav>
    <div class="account-nav">
        <a class="logout-link" href="logout.php">Logout</a>
    </div>
</header>

<div class="result-container">

    <div class="result-card">

        <div class="result-icon">
            🎉
        </div>

        <h1>
            Quiz Selesai!
        </h1>

        <div class="quiz-title">
            <?= htmlspecialchars($quiz['judul']) ?>
        </div>

        <?php if (!empty($quiz['materi_judul'])): ?>

            <div class="materi-name">

                📚 Materi:
                <strong>
                    <?= htmlspecialchars($quiz['materi_judul']) ?>
                </strong>

            </div>

        <?php endif; ?>

        <div class="score">
            <?= $skor ?>
        </div>

        <div class="score-label">
            Nilai Kamu
        </div>

        <div class="result-stats">

            <div class="stat-box">

                <div class="stat-number">
                    <?= $jumlah_soal ?>
                </div>

                <div class="stat-label">
                    Total Soal
                </div>

            </div>

            <div class="stat-box">

                <div class="stat-number">
                    <?= $jumlah_benar ?>
                </div>

                <div class="stat-label">
                    Jawaban Benar
                </div>

            </div>

            <div class="stat-box">

                <div class="stat-number">
                    <?= $jumlah_salah ?>
                </div>

                <div class="stat-label">
                    Jawaban Salah
                </div>

            </div>

        </div>

        <div class="button-group">

            <?php if (!empty($quiz['materi_id'])): ?>

                <a
                    href="lihat-materi.php?id=<?= (int) $quiz['materi_id'] ?>"
                    class="btn btn-primary"
                >
                    📚 Kembali ke Materi
                </a>

            <?php else: ?>

                <a
                    href="materi-siswa.php"
                    class="btn btn-primary"
                >
                    📚 Materi Pembelajaran
                </a>

            <?php endif; ?>

            <a
                href="history.php"
                class="btn btn-secondary"
            >
                📊 Riwayat Nilai
            </a>

        </div>

    </div>

</div>

<?php if ($resultSaveError !== ''): ?>
    <p class="save-warning" role="alert">
        <?= htmlspecialchars($resultSaveError, ENT_QUOTES, 'UTF-8') ?>
    </p>
<?php endif; ?>

<script src="js/back-button.js" data-fallback="view/dashboard.php"></script>
</body>

</html>