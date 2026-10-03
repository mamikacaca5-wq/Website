<?php
// filepath: c:\xampp\htdocs\quizmaster\view\dashboard.php
session_start();

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$nama = $_SESSION['nama'] ?? 'Pengguna';
$role = $_SESSION['role'] ?? '';

if (!in_array($role, ['guru', 'siswa'], true)) {
    header('Location: ../index.php');
    exit;
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$quizzes = [];
$stats = [];
$pageError = '';

try {
    if ($role === 'guru') {
        $stmt = $conn->prepare("
            SELECT q.id, q.kode_quiz, q.judul, q.deskripsi, q.waktu,
                   COUNT(qu.id) AS jumlah_soal
            FROM quizzes q
            LEFT JOIN questions qu ON qu.quiz_id = q.id
            WHERE q.user_id = ?
            GROUP BY q.id, q.kode_quiz, q.judul, q.deskripsi, q.waktu
            ORDER BY q.id DESC
        ");
        $stmt->bind_param('i', $userId);
    } else {
        $stmt = $conn->prepare("
            SELECT q.id, q.kode_quiz, q.judul, q.deskripsi, q.waktu,
                   u.nama AS nama_guru,
                   COUNT(qu.id) AS jumlah_soal
            FROM quizzes q
            LEFT JOIN questions qu ON qu.quiz_id = q.id
            LEFT JOIN users u ON u.id = q.user_id
            GROUP BY q.id, q.kode_quiz, q.judul, q.deskripsi,
                     q.waktu, u.nama
            ORDER BY q.id DESC
        ");
    }

    if (!$stmt) {
        throw new RuntimeException('Query quiz gagal disiapkan.');
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $quizzes = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    if ($role === 'guru') {
        $stmt = $conn->prepare("
            SELECT
                (SELECT COUNT(*) FROM quizzes WHERE user_id = ?) AS total_quiz,
                (SELECT COUNT(qu.id)
                 FROM questions qu
                 INNER JOIN quizzes q ON q.id = qu.quiz_id
                 WHERE q.user_id = ?) AS total_soal,
                (SELECT COUNT(r.id)
                 FROM results r
                 INNER JOIN quizzes q ON q.id = r.quiz_id
                 WHERE q.user_id = ?) AS total_pengerjaan
        ");
        $stmt->bind_param('iii', $userId, $userId, $userId);
    } else {
        $stmt = $conn->prepare("
            SELECT COUNT(id) AS total_dikerjakan,
                   COALESCE(AVG(skor), 0) AS rata_rata
            FROM results
            WHERE user_id = ?
        ");
        $stmt->bind_param('i', $userId);
    }

    if (!$stmt) {
        throw new RuntimeException('Gagal menyiapkan query statistik.');
    }

    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    if ($role === 'siswa') {
        $stats['total_quiz'] = count($quizzes);
    }
} catch (Throwable $error) {
    error_log('Dashboard error: ' . $error->getMessage());
    $pageError = 'Data dashboard gagal dimuat. Periksa nama tabel dan kolom database.';
}

require __DIR__ . '/layout.php';