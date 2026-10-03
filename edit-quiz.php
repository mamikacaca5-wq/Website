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

$userId = (int) $_SESSION['user_id'];
$quizId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

if ($quizId <= 0) {
    header('Location: view/dashboard.php');
    exit;
}

$error = '';
$questions = [];

$stmt = $conn->prepare(
    'SELECT id, judul, deskripsi, waktu FROM quizzes WHERE id = ? AND user_id = ? LIMIT 1'
);

if (!$stmt) {
    http_response_code(500);
    exit('Quiz belum dapat dimuat.');
}

$stmt->bind_param('ii', $quizId, $userId);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quiz) {
    http_response_code(404);
    exit('Quiz tidak ditemukan atau Anda tidak memiliki akses.');
}

$stmt = $conn->prepare("
    SELECT pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, jawaban_benar
    FROM questions
    WHERE quiz_id = ?
    ORDER BY id ASC
");

if (!$stmt) {
    http_response_code(500);
    exit('Soal quiz belum dapat dimuat.');
}

$stmt->bind_param('i', $quizId);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $waktu = filter_input(INPUT_POST, 'waktu', FILTER_VALIDATE_INT);

    $fields = [
        'pertanyaan' => $_POST['pertanyaan'] ?? [],
        'opsi_a' => $_POST['opsi_a'] ?? [],
        'opsi_b' => $_POST['opsi_b'] ?? [],
        'opsi_c' => $_POST['opsi_c'] ?? [],
        'opsi_d' => $_POST['opsi_d'] ?? [],
        'jawaban_benar' => $_POST['jawaban_benar'] ?? [],
    ];

    $postedQuestions = [];
    $arraysValid = true;

    foreach ($fields as $field) {
        if (!is_array($field)) {
            $arraysValid = false;
            break;
        }
    }

    if ($arraysValid) {
        $count = count($fields['pertanyaan']);

        foreach ($fields as $field) {
            if (count($field) !== $count) {
                $arraysValid = false;
                break;
            }
        }

        if ($arraysValid) {
            for ($i = 0; $i < $count; $i++) {
                $postedQuestions[] = [
                    'pertanyaan' => trim((string) ($fields['pertanyaan'][$i] ?? '')),
                    'opsi_a' => trim((string) ($fields['opsi_a'][$i] ?? '')),
                    'opsi_b' => trim((string) ($fields['opsi_b'][$i] ?? '')),
                    'opsi_c' => trim((string) ($fields['opsi_c'][$i] ?? '')),
                    'opsi_d' => trim((string) ($fields['opsi_d'][$i] ?? '')),
                    'jawaban_benar' => strtoupper(trim((string) ($fields['jawaban_benar'][$i] ?? ''))),
                ];
            }
        }
    }

    if ($judul === '') {
        $error = 'Judul quiz wajib diisi.';
    } elseif ($waktu === false || $waktu < 5 || $waktu > 600) {
        $error = 'Waktu harus antara 5 sampai 600 detik.';
    } elseif (!$arraysValid) {
        $error = 'Data soal tidak valid. Silakan periksa kembali formulir.';
    } elseif (count($postedQuestions) < 1) {
        $error = 'Quiz harus memiliki minimal satu soal.';
    } else {
        foreach ($postedQuestions as $question) {
            if (
                $question['pertanyaan'] === '' ||
                $question['opsi_a'] === '' ||
                $question['opsi_b'] === '' ||
                $question['opsi_c'] === '' ||
                $question['opsi_d'] === '' ||
                !in_array($question['jawaban_benar'], ['A', 'B', 'C', 'D'], true)
            ) {
                $error = 'Lengkapi semua pertanyaan, pilihan jawaban, dan jawaban benar.';
                break;
            }
        }
    }

    if ($error === '') {
        try {
            $conn->begin_transaction();

            $stmtUpdate = $conn->prepare("
                UPDATE quizzes
                SET judul = ?, deskripsi = ?, waktu = ?
                WHERE id = ? AND user_id = ?
            ");

            if (!$stmtUpdate) {
                throw new RuntimeException('Gagal menyiapkan pembaruan quiz.');
            }

            $stmtUpdate->bind_param(
                'ssiii',
                $judul,
                $deskripsi,
                $waktu,
                $quizId,
                $userId
            );
            $stmtUpdate->execute();
            $stmtUpdate->close();

            $stmtDelete = $conn->prepare('DELETE FROM questions WHERE quiz_id = ?');

            if (!$stmtDelete) {
                throw new RuntimeException('Gagal menyiapkan pembaruan soal.');
            }

            $stmtDelete->bind_param('i', $quizId);
            $stmtDelete->execute();
            $stmtDelete->close();

            $stmtInsert = $conn->prepare("
                INSERT INTO questions
                    (quiz_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, jawaban_benar)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmtInsert) {
                throw new RuntimeException('Gagal menyiapkan penyimpanan soal.');
            }

            foreach ($postedQuestions as $question) {
                $stmtInsert->bind_param(
                    'issssss',
                    $quizId,
                    $question['pertanyaan'],
                    $question['opsi_a'],
                    $question['opsi_b'],
                    $question['opsi_c'],
                    $question['opsi_d'],
                    $question['jawaban_benar']
                );
                $stmtInsert->execute();
            }

            $stmtInsert->close();
            $conn->commit();

            header('Location: view/dashboard.php?updated=1');
            exit;
        } catch (Throwable $exception) {
            $conn->rollback();
            error_log('Edit quiz error: ' . $exception->getMessage());
            $error = 'Quiz gagal diperbarui. Silakan coba lagi.';
        }
    }

    $quiz['judul'] = $judul;
    $quiz['deskripsi'] = $deskripsi;
    $quiz['waktu'] = $waktu ?: $quiz['waktu'];
    $questions = $postedQuestions;
}

$nama = $_SESSION['nama'] ?? 'Guru';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Quiz - TKDSmart</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .edit-page {
            width: min(900px, calc(100% - 36px));
            margin: 32px auto 60px;
        }

        .edit-hero {
            margin-bottom: 18px;
            padding: clamp(24px, 4vw, 36px);
            border: 1px solid #e9e0f2;
            border-radius: 16px;
            background: linear-gradient(125deg, #f1eaff, #fff 75%);
        }

        .edit-hero h1 {
            margin: 0;
            color: #25243b;
            font-size: clamp(27px, 4vw, 35px);
        }

        .edit-hero p:last-child {
            margin: 8px 0 0;
            color: #777587;
        }

        .edit-card,
        .danger-card {
            margin-top: 18px;
            padding: clamp(20px, 4vw, 30px);
            border: 1px solid #ecebf1;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(37, 36, 59, .05);
        }

        .edit-card h2,
        .danger-card h2 {
            margin: 0 0 18px;
            color: #25243b;
        }

        .edit-field {
            margin-bottom: 17px;
        }

        .edit-field label {
            display: block;
            margin-bottom: 7px;
            color: #343348;
            font-size: 14px;
            font-weight: 700;
        }

        .edit-field input,
        .edit-field textarea,
        .edit-field select {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #dedce5;
            border-radius: 8px;
            background: #fff;
            color: #343348;
            font: inherit;
        }

        .edit-field textarea {
            min-height: 85px;
            resize: vertical;
        }

        .edit-field input:focus,
        .edit-field textarea:focus,
        .edit-field select:focus {
            border-color: #8750c5;
            outline: 3px solid rgba(135, 80, 197, .12);
        }

        .question-card {
            margin: 16px 0;
            padding: 20px;
            border: 1px solid #ece7f2;
            border-radius: 11px;
            background: #fcfbfd;
        }

        .question-card h3 {
            margin: 0 0 16px;
            color: #713cae;
            font-size: 17px;
        }

        .form-error {
            margin-bottom: 18px;
            padding: 12px 14px;
            border: 1px solid #f0c9c7;
            border-left: 4px solid #b42318;
            border-radius: 8px;
            background: #fff7f6;
            color: #a12622;
        }

        .edit-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }

        .edit-button {
            display: inline-flex;
            min-height: 42px;
            align-items: center;
            justify-content: center;
            padding: 10px 15px;
            border: 0;
            border-radius: 8px;
            background: #8750c5;
            color: #fff;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .edit-button:hover {
            background: #713cae;
        }

        .edit-button.secondary {
            border: 1px solid #e8e3ee;
            background: #f3eef9;
            color: #713cae;
        }

        .edit-button.danger {
            background: #b42318;
        }

        .edit-button.danger:hover {
            background: #912018;
        }

        .danger-card {
            border-color: #f0d4d1;
        }

        .danger-card p {
            color: #777587;
            line-height: 1.6;
        }

        @media (max-width: 600px) {
            .edit-page {
                margin-top: 20px;
            }

            .edit-actions {
                flex-direction: column;
            }

            .edit-button {
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
        <a href="view/dashboard.php">Dashboard</a>
        <a class="active" href="create-quiz.php">Kelola quiz</a>
        <a href="teacher-stats.php">Statistik</a>
    </nav>

    <div class="account-nav">
        <span class="user-name"><?= e($nama) ?></span>
        <a class="logout-link" href="logout.php">Logout</a>
    </div>
</header>

<main class="edit-page">
    <section class="edit-hero">
        <p class="eyebrow">PENGELOLAAN QUIZ</p>
        <h1>Edit quiz</h1>
        <p>Perbarui informasi quiz dan soal-soalnya.</p>
    </section>

    <?php if ($error !== ''): ?>
        <div class="form-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <section class="edit-card">
            <h2>Informasi quiz</h2>

            <div class="edit-field">
                <label for="judul">Judul quiz</label>
                <input
                    type="text"
                    id="judul"
                    name="judul"
                    value="<?= e($quiz['judul']) ?>"
                    maxlength="255"
                    required
                >
            </div>

            <div class="edit-field">
                <label for="deskripsi">Deskripsi</label>
                <textarea id="deskripsi" name="deskripsi"><?= e($quiz['deskripsi'] ?? '') ?></textarea>
            </div>

            <div class="edit-field">
                <label for="waktu">Waktu per soal (detik)</label>
                <input
                    type="number"
                    id="waktu"
                    name="waktu"
                    min="5"
                    max="600"
                    value="<?= (int) $quiz['waktu'] ?>"
                    required
                >
            </div>
        </section>

        <section class="edit-card">
            <h2>Daftar soal</h2>

            <div id="questions-container">
                <?php foreach ($questions as $index => $question): ?>
                    <article class="question-card">
                        <h3>Soal <?= $index + 1 ?></h3>

                        <div class="edit-field">
                            <label>Pertanyaan</label>
                            <textarea name="pertanyaan[]" required><?= e($question['pertanyaan'] ?? '') ?></textarea>
                        </div>

                        <?php foreach (['a', 'b', 'c', 'd'] as $option): ?>
                            <div class="edit-field">
                                <label>Pilihan <?= strtoupper($option) ?></label>
                                <input
                                    type="text"
                                    name="opsi_<?= $option ?>[]"
                                    value="<?= e($question['opsi_' . $option] ?? '') ?>"
                                    required
                                >
                            </div>
                        <?php endforeach; ?>

                        <div class="edit-field">
                            <label>Jawaban benar</label>
                            <select name="jawaban_benar[]" required>
                                <?php foreach (['A', 'B', 'C', 'D'] as $answer): ?>
                                    <option
                                        value="<?= $answer ?>"
                                        <?= ($question['jawaban_benar'] ?? '') === $answer ? 'selected' : '' ?>
                                    ><?= $answer ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="button" class="edit-button danger" onclick="hapusSoal(this)">
                            Hapus soal
                        </button>
                    </article>
                <?php endforeach; ?>
            </div>

            <button type="button" class="edit-button secondary" onclick="tambahSoal()">
                Tambah soal
            </button>

            <div class="edit-actions">
                <button type="submit" class="edit-button">Simpan perubahan</button>
                <a href="view/dashboard.php" class="edit-button secondary">Kembali ke dashboard</a>
            </div>
        </section>
    </form>

    <section class="danger-card">
        <h2>Zona berbahaya</h2>
        <p>Menghapus quiz juga dapat menghapus soal dan hasil pengerjaan quiz.</p>

        <form
            action="delete-quiz.php"
            method="POST"
            onsubmit="return confirm('Yakin ingin menghapus quiz ini beserta soal dan hasil pengerjaannya?')"
        >
            <input type="hidden" name="quiz_id" value="<?= (int) $quizId ?>">
            <button type="submit" class="edit-button danger">Hapus quiz</button>
        </form>
    </section>
</main>

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> TKDSmart. Platform Pembelajaran Digital.</p>
</footer>

<script>
function tambahSoal() {
    const container = document.getElementById('questions-container');
    const index = container.querySelectorAll('.question-card').length + 1;
    const card = document.createElement('article');

    card.className = 'question-card';
    card.innerHTML = `
        <h3>Soal ${index}</h3>
        <div class="edit-field">
            <label>Pertanyaan</label>
            <textarea name="pertanyaan[]" required></textarea>
        </div>
        <div class="edit-field">
            <label>Pilihan A</label>
            <input type="text" name="opsi_a[]" required>
        </div>
        <div class="edit-field">
            <label>Pilihan B</label>
            <input type="text" name="opsi_b[]" required>
        </div>
        <div class="edit-field">
            <label>Pilihan C</label>
            <input type="text" name="opsi_c[]" required>
        </div>
        <div class="edit-field">
            <label>Pilihan D</label>
            <input type="text" name="opsi_d[]" required>
        </div>
        <div class="edit-field">
            <label>Jawaban benar</label>
            <select name="jawaban_benar[]" required>
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="C">C</option>
                <option value="D">D</option>
            </select>
        </div>
        <button type="button" class="edit-button danger" onclick="hapusSoal(this)">
            Hapus soal
        </button>
    `;

    container.appendChild(card);
    updateNomorSoal();
}

function hapusSoal(button) {
    const container = document.getElementById('questions-container');

    if (container.querySelectorAll('.question-card').length <= 1) {
        alert('Quiz harus memiliki minimal satu soal.');
        return;
    }

    button.closest('.question-card').remove();
    updateNomorSoal();
}

function updateNomorSoal() {
    document.querySelectorAll('#questions-container .question-card h3')
        .forEach((heading, index) => {
            heading.textContent = `Soal ${index + 1}`;
        });
}
</script>
</body>
</html>