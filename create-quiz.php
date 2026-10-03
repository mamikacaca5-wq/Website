<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/quiz-code.php';

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'guru'
) {
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
$dashboardUrl = 'view/dashboard.php';
$error = '';
$materi = null;

$materiIdInput = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['materi_id'] ?? 0)
    : ($_GET['materi_id'] ?? 0);

$materiId = filter_var($materiIdInput, FILTER_VALIDATE_INT);
$materiId = ($materiId !== false && $materiId > 0) ? $materiId : 0;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$judul = is_string($_POST['judul'] ?? null) ? trim($_POST['judul']) : '';
$deskripsi = is_string($_POST['deskripsi'] ?? null)
    ? trim($_POST['deskripsi'])
    : '';
$waktuInput = $_POST['waktu'] ?? '30';
$waktu = filter_var($waktuInput, FILTER_VALIDATE_INT);
$waktu = $waktu === false ? 30 : $waktu;

$questions = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fieldNames = [
        'pertanyaan',
        'opsi_a',
        'opsi_b',
        'opsi_c',
        'opsi_d',
        'jawaban_benar',
    ];

    $fields = [];
    $arraysValid = true;

    foreach ($fieldNames as $fieldName) {
        $value = $_POST[$fieldName] ?? [];

        if (!is_array($value)) {
            $arraysValid = false;
            break;
        }

        $fields[$fieldName] = $value;
    }

    if ($arraysValid) {
        $questionCount = count($fields['pertanyaan']);

        foreach ($fields as $field) {
            if (count($field) !== $questionCount) {
                $arraysValid = false;
                break;
            }
        }

        if ($questionCount < 1 || $questionCount > 100) {
            $arraysValid = false;
        }

        if ($arraysValid) {
            for ($i = 0; $i < $questionCount; $i++) {
                $question = [];

                foreach ($fieldNames as $fieldName) {
                    $value = $fields[$fieldName][$i] ?? '';

                    if (!is_scalar($value)) {
                        $value = '';
                    }

                    $question[$fieldName] = trim((string) $value);
                }

                $question['jawaban_benar'] = strtoupper(
                    $question['jawaban_benar']
                );

                $questions[] = $question;
            }
        }
    }

    if (!$arraysValid) {
        $error = 'Data soal tidak valid. Pastikan terdapat 1 sampai 100 soal.';
    }
} else {
    $questions[] = [
        'pertanyaan' => '',
        'opsi_a' => '',
        'opsi_b' => '',
        'opsi_c' => '',
        'opsi_d' => '',
        'jawaban_benar' => '',
    ];
}

if ($materiId > 0) {
    try {
        $stmtMateri = $conn->prepare("
            SELECT id, judul, deskripsi
            FROM materi
            WHERE id = ? AND user_id = ?
            LIMIT 1
        ");

        if (!$stmtMateri) {
            throw new RuntimeException('Gagal menyiapkan query materi.');
        }

        $stmtMateri->bind_param('ii', $materiId, $userId);
        $stmtMateri->execute();
        $materi = $stmtMateri->get_result()->fetch_assoc();
        $stmtMateri->close();

        if (!$materi) {
            $error = 'Materi tidak ditemukan atau bukan milik Anda.';
        }
    } catch (Throwable $exception) {
        error_log('Create quiz material lookup error: ' . $exception->getMessage());
        $error = 'Materi belum dapat diperiksa. Silakan coba lagi.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION['csrf_token'], $csrfToken)
    ) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif ($judul === '') {
        $error = 'Judul quiz wajib diisi.';
    } elseif (mb_strlen($judul) > 150) {
        $error = 'Judul quiz maksimal 150 karakter.';
    } elseif ($waktu < 5 || $waktu > 600) {
        $error = 'Durasi harus antara 5 sampai 600 detik.';
    } elseif (count($questions) < 1) {
        $error = 'Tambahkan minimal satu soal.';
    } else {
        foreach ($questions as $index => $question) {
            if (
                $question['pertanyaan'] === '' ||
                $question['opsi_a'] === '' ||
                $question['opsi_b'] === '' ||
                $question['opsi_c'] === '' ||
                $question['opsi_d'] === '' ||
                !in_array(
                    $question['jawaban_benar'],
                    ['A', 'B', 'C', 'D'],
                    true
                )
            ) {
                $error = 'Lengkapi semua kolom soal nomor ' . ($index + 1) . '.';
                break;
            }
        }
    }

    if ($error === '') {
        try {
            $conn->begin_transaction();

            $kodeQuiz = generateQuizCode($conn);

            $stmtQuiz = $conn->prepare("
                INSERT INTO quizzes
                    (kode_quiz, user_id, materi_id, judul, deskripsi, waktu)
                VALUES (?, ?, NULLIF(?, 0), ?, ?, ?)
            ");

            if (!$stmtQuiz) {
                throw new RuntimeException('Gagal menyiapkan penyimpanan quiz.');
            }

            $stmtQuiz->bind_param(
                'siissi',
                $kodeQuiz,
                $userId,
                $materiId,
                $judul,
                $deskripsi,
                $waktu
            );
            $stmtQuiz->execute();
            $quizId = (int) $stmtQuiz->insert_id;
            $stmtQuiz->close();

            $stmtQuestion = $conn->prepare("
                INSERT INTO questions
                    (quiz_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, jawaban_benar)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmtQuestion) {
                throw new RuntimeException('Gagal menyiapkan penyimpanan soal.');
            }

            foreach ($questions as $question) {
                $stmtQuestion->bind_param(
                    'issssss',
                    $quizId,
                    $question['pertanyaan'],
                    $question['opsi_a'],
                    $question['opsi_b'],
                    $question['opsi_c'],
                    $question['opsi_d'],
                    $question['jawaban_benar']
                );
                $stmtQuestion->execute();
            }

            $stmtQuestion->close();
            $conn->commit();

            if ($materiId > 0) {
                header(
                    'Location: detail-materi.php?id=' . $materiId
                    . '&created=1&code=' . urlencode($kodeQuiz)
                );
            } else {
                header(
                    'Location: ' . $dashboardUrl
                    . '?created=1&code=' . urlencode($kodeQuiz)
                );
            }
            exit;
        } catch (Throwable $exception) {
            try {
                $conn->rollback();
            } catch (Throwable $rollbackError) {
                error_log('Create quiz rollback error: ' . $rollbackError->getMessage());
            }

            error_log('Create quiz error: ' . $exception->getMessage());
            $error = 'Quiz gagal disimpan. Silakan coba lagi.';
        }
    }
}

$nama = $_SESSION['nama'] ?? 'Guru';
$cancelUrl = $materiId > 0
    ? 'detail-materi.php?id=' . $materiId
    : $dashboardUrl;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buat Quiz - TKDSmart</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .create-page {
            width: min(950px, calc(100% - 36px));
            margin: 32px auto 58px;
        }

        .create-hero {
            margin-bottom: 18px;
            padding: clamp(24px, 4vw, 36px);
            border: 1px solid #e9e0f2;
            border-radius: 16px;
            background:
                radial-gradient(ellipse at 90% 10%, rgba(135, 80, 197, .15), transparent 32%),
                linear-gradient(125deg, #f1eaff, #fff 72%);
        }

        .create-hero h1 {
            margin: 0;
            color: #25243b;
            font-size: clamp(27px, 4vw, 35px);
        }

        .create-hero p:last-child {
            margin: 8px 0 0;
            color: #777587;
        }

        .create-card {
            margin-top: 18px;
            padding: clamp(20px, 4vw, 30px);
            border: 1px solid #ecebf1;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(37, 36, 59, .05);
        }

        .create-card h2 {
            margin: 0 0 18px;
            color: #25243b;
        }

        .materi-info,
        .form-alert {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 9px;
            line-height: 1.6;
        }

        .materi-info {
            border: 1px solid #e9e0f2;
            background: #f8f4fc;
            color: #514b60;
        }

        .materi-info strong {
            color: #713cae;
        }

        .form-alert {
            border: 1px solid #f0c9c7;
            border-left: 4px solid #b42318;
            background: #fff7f6;
            color: #a12622;
        }

        .create-field {
            margin-bottom: 17px;
        }

        .create-field label {
            display: block;
            margin-bottom: 7px;
            color: #343348;
            font-size: 14px;
            font-weight: 700;
        }

        .create-field input,
        .create-field textarea,
        .create-field select {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #dedce5;
            border-radius: 8px;
            background: #fff;
            color: #343348;
            font: inherit;
        }

        .create-field textarea {
            min-height: 90px;
            resize: vertical;
        }

        .create-field input:focus,
        .create-field textarea:focus,
        .create-field select:focus {
            border-color: #8750c5;
            outline: 3px solid rgba(135, 80, 197, .12);
        }

        .field-hint {
            display: block;
            margin-top: 6px;
            color: #777587;
            font-size: 13px;
        }

        .question-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .question-header h2 {
            margin-bottom: 0;
        }

        .question-card {
            margin-top: 16px;
            padding: 20px;
            border: 1px solid #ece7f2;
            border-radius: 11px;
            background: #fcfbfd;
        }

        .question-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }

        .question-card-header h3 {
            margin: 0;
            color: #713cae;
            font-size: 17px;
        }

        .options-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0 16px;
        }

        .create-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .create-button {
            display: inline-flex;
            min-height: 42px;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border: 0;
            border-radius: 8px;
            background: #8750c5;
            color: #fff;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .create-button:hover {
            background: #713cae;
        }

        .create-button.secondary {
            border: 1px solid #e8e3ee;
            background: #f3eef9;
            color: #713cae;
        }

        .create-button.secondary:hover {
            background: #ece3f6;
        }

        .create-button.danger {
            background: #b42318;
        }

        @media (max-width: 600px) {
            .create-page {
                margin-top: 20px;
            }

            .question-header,
            .question-card-header {
                align-items: stretch;
                flex-direction: column;
            }

            .options-grid {
                grid-template-columns: 1fr;
            }

            .create-actions {
                flex-direction: column-reverse;
            }

            .create-button {
                width: 100%;
            }

            .question-card {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php">TKD<span>Smart</span></a>

    <nav class="main-nav" aria-label="Navigasi utama">
        <a href="<?= e($dashboardUrl) ?>">Dashboard</a>
        <a class="active" href="create-quiz.php">Kelola quiz</a>
        <a href="daftar-materi.php">Materi</a>
        <a href="hasil-belajar.php">Hasil belajar</a>
    </nav>

    <div class="account-nav">
        <span class="user-name"><?= e($nama) ?></span>
        <a class="logout-link" href="logout.php">Logout</a>
    </div>
</header>

<main class="create-page">
    <section class="create-hero">
        <p class="eyebrow">PENGELOLAAN PEMBELAJARAN</p>
        <h1>Buat quiz baru</h1>
        <p>Buat quiz dan tambahkan soal pilihan ganda untuk siswa.</p>
    </section>

    <?php if ($materi): ?>
        <div class="materi-info">
            Quiz ini akan dihubungkan dengan materi:
            <strong><?= e($materi['judul']) ?></strong>
            <?php if (!empty($materi['deskripsi'])): ?>
                <br><?= e($materi['deskripsi']) ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="form-alert" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="create-quiz.php">
        <input type="hidden" name="materi_id" value="<?= (int) $materiId ?>">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= e($_SESSION['csrf_token']) ?>"
        >

        <section class="create-card">
            <h2>Informasi quiz</h2>

            <div class="create-field">
                <label for="judul">Judul quiz</label>
                <input
                    type="text"
                    id="judul"
                    name="judul"
                    maxlength="150"
                    value="<?= e($judul) ?>"
                    placeholder="Contoh: Quiz Pengantar Teknik Kendali Digital"
                    required
                >
            </div>

            <div class="create-field">
                <label for="deskripsi">Deskripsi</label>
                <textarea
                    id="deskripsi"
                    name="deskripsi"
                    placeholder="Tuliskan deskripsi singkat quiz..."
                ><?= e($deskripsi) ?></textarea>
            </div>

            <div class="create-field">
                <label for="waktu">Durasi setiap soal (detik)</label>
                <input
                    type="number"
                    id="waktu"
                    name="waktu"
                    min="5"
                    max="600"
                    value="<?= (int) $waktu ?>"
                    required
                >
                <span class="field-hint">Durasi harus antara 5 sampai 600 detik.</span>
            </div>
        </section>

        <section class="create-card">
            <div class="question-header">
                <h2>Daftar soal</h2>
                <button type="button" id="addQuestion" class="create-button secondary">
                    + Tambah soal
                </button>
            </div>

            <div id="questionList">
                <?php foreach ($questions as $index => $question): ?>
                    <article class="question-card">
                        <div class="question-card-header">
                            <h3>Soal <span class="question-number"><?= $index + 1 ?></span></h3>
                            <?php if ($index > 0): ?>
                                <button type="button" class="create-button danger remove-question">
                                    Hapus soal
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="create-field">
                            <label for="pertanyaan-<?= $index ?>">Pertanyaan</label>
                            <textarea
                                id="pertanyaan-<?= $index ?>"
                                name="pertanyaan[]"
                                required
                            ><?= e($question['pertanyaan']) ?></textarea>
                        </div>

                        <div class="options-grid">
                            <?php foreach (['a', 'b', 'c', 'd'] as $option): ?>
                                <div class="create-field">
                                    <label for="opsi-<?= $option ?>-<?= $index ?>">
                                        Pilihan <?= strtoupper($option) ?>
                                    </label>
                                    <input
                                        type="text"
                                        id="opsi-<?= $option ?>-<?= $index ?>"
                                        name="opsi_<?= $option ?>[]"
                                        value="<?= e($question['opsi_' . $option]) ?>"
                                        required
                                    >
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="create-field">
                            <label for="jawaban-<?= $index ?>">Jawaban benar</label>
                            <select
                                id="jawaban-<?= $index ?>"
                                name="jawaban_benar[]"
                                required
                            >
                                <option value="">Pilih jawaban benar</option>
                                <?php foreach (['A', 'B', 'C', 'D'] as $answer): ?>
                                    <option
                                        value="<?= $answer ?>"
                                        <?= $question['jawaban_benar'] === $answer ? 'selected' : '' ?>
                                    ><?= $answer ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="create-actions">
                <a class="create-button secondary" href="<?= e($cancelUrl) ?>">Batal</a>
                <button type="submit" class="create-button">Simpan quiz</button>
            </div>
        </section>
    </form>
</main>

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> TKDSmart. Platform Pembelajaran Digital.</p>
</footer>

<script>
const questionList = document.getElementById('questionList');
const addQuestionButton = document.getElementById('addQuestion');

function updateQuestionNumbers() {
    questionList.querySelectorAll('.question-card').forEach((card, index) => {
        card.querySelector('.question-number').textContent = index + 1;
    });
}

addQuestionButton.addEventListener('click', () => {
    const count = questionList.querySelectorAll('.question-card').length;

    if (count >= 100) {
        alert('Maksimal 100 soal dalam satu quiz.');
        return;
    }

    const card = document.createElement('article');
    card.className = 'question-card';
    card.innerHTML = `
        <div class="question-card-header">
            <h3>Soal <span class="question-number"></span></h3>
            <button type="button" class="create-button danger remove-question">Hapus soal</button>
        </div>
        <div class="create-field">
            <label>Pertanyaan</label>
            <textarea name="pertanyaan[]" required></textarea>
        </div>
        <div class="options-grid">
            <div class="create-field">
                <label>Pilihan A</label>
                <input type="text" name="opsi_a[]" required>
            </div>
            <div class="create-field">
                <label>Pilihan B</label>
                <input type="text" name="opsi_b[]" required>
            </div>
            <div class="create-field">
                <label>Pilihan C</label>
                <input type="text" name="opsi_c[]" required>
            </div>
            <div class="create-field">
                <label>Pilihan D</label>
                <input type="text" name="opsi_d[]" required>
            </div>
        </div>
        <div class="create-field">
            <label>Jawaban benar</label>
            <select name="jawaban_benar[]" required>
                <option value="">Pilih jawaban benar</option>
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="C">C</option>
                <option value="D">D</option>
            </select>
        </div>
    `;

    questionList.appendChild(card);
    updateQuestionNumbers();
});

questionList.addEventListener('click', (event) => {
    if (!event.target.classList.contains('remove-question')) {
        return;
    }

    const cards = questionList.querySelectorAll('.question-card');

    if (cards.length <= 1) {
        alert('Quiz harus memiliki minimal satu soal.');
        return;
    }

    event.target.closest('.question-card').remove();
    updateQuestionNumbers();
});
</script>
</body>
</html>