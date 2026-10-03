<?php

session_start();

require_once __DIR__ . '/config/database.php';

$dashboardUrl = 'view/dashboard.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'guru') {
    header('Location: ' . $dashboardUrl);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $dashboardUrl);
    exit;
}

$quizId = filter_input(INPUT_POST, 'quiz_id', FILTER_VALIDATE_INT);
$quizId = ($quizId !== false && $quizId !== null && $quizId > 0)
    ? $quizId
    : 0;

if ($quizId <= 0) {
    header('Location: ' . $dashboardUrl . '?delete_error=1');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$csrfToken = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !is_string($csrfToken) ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    header('Location: ' . $dashboardUrl . '?delete_error=1');
    exit;
}

try {
    $conn->begin_transaction();

    // Pastikan quiz memang milik guru yang sedang login.
    $stmt = $conn->prepare(
        'SELECT id FROM quizzes WHERE id = ? AND user_id = ? FOR UPDATE'
    );

    if (!$stmt) {
        throw new RuntimeException('Gagal memeriksa kepemilikan quiz.');
    }

    $stmt->bind_param('ii', $quizId, $userId);
    $stmt->execute();
    $quiz = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$quiz) {
        throw new RuntimeException('Quiz tidak ditemukan.');
    }

    $stmt = $conn->prepare('DELETE FROM results WHERE quiz_id = ?');
    if (!$stmt) {
        throw new RuntimeException('Gagal menghapus hasil quiz.');
    }
    $stmt->bind_param('i', $quizId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM questions WHERE quiz_id = ?');
    if (!$stmt) {
        throw new RuntimeException('Gagal menghapus soal quiz.');
    }
    $stmt->bind_param('i', $quizId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare(
        'DELETE FROM quizzes WHERE id = ? AND user_id = ?'
    );

    if (!$stmt) {
        throw new RuntimeException('Gagal menghapus quiz.');
    }

    $stmt->bind_param('ii', $quizId, $userId);
    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        $stmt->close();
        throw new RuntimeException('Quiz tidak berhasil dihapus.');
    }

    $stmt->close();
    $conn->commit();

    header('Location: ' . $dashboardUrl . '?deleted=1');
    exit;
} catch (Throwable $error) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
        error_log('Rollback delete quiz gagal: ' . $rollbackError->getMessage());
    }

    error_log('Delete quiz error: ' . $error->getMessage());
    header('Location: ' . $dashboardUrl . '?delete_error=1');
    exit;
}