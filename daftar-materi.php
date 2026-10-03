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

$userId = (int) $_SESSION['user_id'];
$nama = $_SESSION['nama'] ?? 'Guru';
$materi = [];
$pageError = '';

try {
    $stmtUser = $conn->prepare(
        'SELECT nama, role FROM users WHERE id = ? LIMIT 1'
    );

    if (!$stmtUser) {
        throw new RuntimeException('Gagal memeriksa akun.');
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

    if ($user['role'] !== 'guru') {
        header('Location: view/dashboard.php');
        exit;
    }

    $nama = $user['nama'];
    $_SESSION['nama'] = $nama;
    $_SESSION['role'] = $user['role'];

    $stmt = $conn->prepare("
        SELECT id, judul, deskripsi, created_at
        FROM materi
        WHERE user_id = ?
        ORDER BY created_at DESC, id DESC
    ");

    if (!$stmt) {
        throw new RuntimeException('Gagal mengambil daftar materi.');
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $materi = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $error) {
    error_log('Daftar materi error: ' . $error->getMessage());
    $pageError = 'Daftar materi belum dapat dimuat. Silakan coba lagi.';
}

$success = isset($_GET['success']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Materi Saya - TKDSmart</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .materials-page {
            width: min(1150px, calc(100% - 36px));
            margin: 32px auto 58px;
        }

        .materials-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 20px;
            padding: clamp(24px, 4vw, 36px);
            border: 1px solid #e9e0f2;
            border-radius: 16px;
            background:
                radial-gradient(ellipse at 90% 10%, rgba(135, 80, 197, .15), transparent 32%),
                linear-gradient(125deg, #f1eaff, #fff 72%);
        }

        .materials-hero h1 {
            margin: 0;
            color: #25243b;
            font-size: clamp(27px, 4vw, 35px);
        }

        .materials-hero p:last-child {
            margin: 8px 0 0;
            color: #777587;
        }

        .materials-button {
            display: inline-flex;
            min-height: 42px;
            flex-shrink: 0;
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
        }

        .materials-button:hover {
            background: #713cae;
        }

        .materials-button.secondary {
            border: 1px solid #e8e3ee;
            background: #f3eef9;
            color: #713cae;
        }

        .materials-button.secondary:hover {
            background: #ece3f6;
        }

        .materials-alert {
            margin-bottom: 18px;
            padding: 13px 16px;
            border: 1px solid #c9ead7;
            border-radius: 9px;
            background: #f1fbf5;
            color: #17663b;
        }

        .materials-alert.error {
            border-color: #f0c9c7;
            background: #fff7f6;
            color: #a12622;
        }

        .materials-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .material-card {
            display: flex;
            min-height: 245px;
            flex-direction: column;
            padding: 23px;
            border: 1px solid #ecebf1;
            border-radius: 13px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(37, 36, 59, .05);
        }

        .material-card h2 {
            margin: 0 0 10px;
            color: #25243b;
            font-size: 19px;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .material-description {
            display: -webkit-box;
            overflow: hidden;
            margin: 0;
            color: #777587;
            line-height: 1.65;
            overflow-wrap: anywhere;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
        }

        .material-date {
            margin-top: auto;
            padding-top: 17px;
            color: #888699;
            font-size: 13px;
        }

        .material-card .materials-button {
            align-self: flex-start;
            margin-top: 14px;
        }

        .materials-empty {
            padding: 48px 20px;
            border: 1px solid #ecebf1;
            border-radius: 13px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(37, 36, 59, .05);
            text-align: center;
        }

        .materials-empty h2 {
            margin: 0 0 8px;
            color: #25243b;
        }

        .materials-empty p {
            margin: 0 0 18px;
            color: #777587;
        }

        @media (max-width: 850px) {
            .materials-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .materials-page {
                margin-top: 20px;
            }

            .materials-hero {
                align-items: flex-start;
                flex-direction: column;
            }

            .materials-grid {
                grid-template-columns: 1fr;
            }

            .materials-hero .materials-button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php">TKD<span>Smart</span></a>

    <nav class="main-nav" aria-label="Navigasi utama">
        <a href="view/dashboard.php">Dashboard</a>
        <a class="active" href="daftar-materi.php">Materi</a>
        <a href="create-quiz.php">Kelola quiz</a>
        <a href="hasil-belajar.php">Hasil belajar</a>
    </nav>

    <div class="account-nav">
        <span class="user-name"><?= e($nama) ?></span>
        <a class="logout-link" href="logout.php">Logout</a>
    </div>
</header>

<main class="materials-page">
    <section class="materials-hero">
        <div>
            <p class="eyebrow">PENGELOLAAN PEMBELAJARAN</p>
            <h1>Materi saya</h1>
            <p>Kelola materi pembelajaran yang telah Anda buat.</p>
        </div>
        <a class="materials-button" href="tambah-materi.php">+ Tambah materi</a>
    </section>

    <?php if ($success): ?>
        <div class="materials-alert" role="status">
            Materi berhasil ditambahkan.
        </div>
    <?php endif; ?>

    <?php if ($pageError !== ''): ?>
        <div class="materials-alert error" role="alert">
            <?= e($pageError) ?>
        </div>
    <?php elseif ($materi): ?>
        <section class="materials-grid" aria-label="Daftar materi">
            <?php foreach ($materi as $row): ?>
                <?php
                $deskripsi = trim((string) ($row['deskripsi'] ?? ''));
                $createdAt = !empty($row['created_at'])
                    ? strtotime((string) $row['created_at'])
                    : false;
                ?>
                <article class="material-card">
                    <h2><?= e($row['judul']) ?></h2>

                    <p class="material-description">
                        <?= $deskripsi !== ''
                            ? e($deskripsi)
                            : 'Belum ada deskripsi untuk materi ini.' ?>
                    </p>

                    <div class="material-date">
                        Dibuat:
                        <?= $createdAt !== false ? e(date('d M Y', $createdAt)) : '—' ?>
                    </div>

                    <a
                        class="materials-button secondary"
                        href="detail-materi.php?id=<?= (int) $row['id'] ?>"
                    >Lihat materi →</a>

                    <a
                        class="materials-button"
                        href="edit-materi.php?id=<?= (int) $row['id'] ?>"
                    >Edit materi</a>
                </article>
            <?php endforeach; ?>
        </section>
    <?php else: ?>
        <section class="materials-empty">
            <h2>Belum ada materi</h2>
            <p>Materi yang Anda buat akan ditampilkan di halaman ini.</p>
            <a class="materials-button" href="tambah-materi.php">Buat materi pertama</a>
        </section>
    <?php endif; ?>
</main>

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> TKDSmart. Platform Pembelajaran Digital.</p>
</footer>
<script src="js/back-button.js" data-fallback="view/dashboard.php"></script>
</body>
</html>