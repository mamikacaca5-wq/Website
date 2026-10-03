<?php
// filepath: c:\xampp\htdocs\quizmaster\materi-siswa.php
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
$user = null;
$materi = [];
$pageError = '';

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
        session_destroy();
        header('Location: login.php');
        exit;
    }

    if ($user['role'] !== 'siswa') {
        header('Location: view/dashboard.php');
        exit;
    }

    $stmtMateri = $conn->prepare("
        SELECT m.id, m.judul, m.deskripsi, m.created_at, u.nama AS nama_guru
        FROM materi AS m
        INNER JOIN users AS u ON u.id = m.user_id
        ORDER BY m.created_at DESC, m.id DESC
    ");

    if (!$stmtMateri) {
        throw new RuntimeException('Gagal menyiapkan query materi.');
    }

    $stmtMateri->execute();
    $materi = $stmtMateri->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtMateri->close();
} catch (Throwable $error) {
    error_log('Materi siswa error: ' . $error->getMessage());
    $pageError = 'Materi belum dapat dimuat. Silakan coba lagi.';
}

$nama = $user['nama'] ?? ($_SESSION['nama'] ?? 'Siswa');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Materi Pembelajaran - TKDSmart</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .materi-page {
            width: min(1320px, calc(100% - 40px));
            margin: 0 auto;
            padding: 32px 0 58px;
        }

        .materi-heading {
            margin-bottom: 24px;
            padding: clamp(24px, 4vw, 36px);
            border: 1px solid #e9e0f2;
            border-radius: 16px;
            background:
                radial-gradient(ellipse at 90% 10%, rgba(135, 80, 197, .15), transparent 32%),
                linear-gradient(125deg, #f1eaff, #fff 72%);
        }

        .materi-heading h1 {
            margin: 0;
            color: #25243b;
            font-size: clamp(27px, 4vw, 35px);
        }

        .materi-heading p:last-child {
            margin: 8px 0 0;
            color: #777587;
        }

        .materi-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .materi-card {
            overflow: hidden;
            border: 1px solid #ecebf1;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 3px 10px rgba(37, 36, 59, .06);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .materi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 9px 20px rgba(37, 36, 59, .10);
        }

        .materi-cover {
            display: flex;
            min-height: 125px;
            align-items: flex-end;
            padding: 17px;
            background:
                linear-gradient(rgba(255,255,255,.17) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.17) 1px, transparent 1px),
                linear-gradient(135deg, #e4cfe4, #bda5d3);
            background-size: 16px 16px, 16px 16px, auto;
            color: #fff;
            font-size: 21px;
            font-weight: 800;
            text-shadow: 0 2px 7px rgba(40, 30, 50, .3);
        }

        .materi-card:nth-child(3n + 2) .materi-cover {
            background:
                linear-gradient(rgba(255,255,255,.17) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.17) 1px, transparent 1px),
                linear-gradient(135deg, #ccebe5, #80bdb0);
            background-size: 16px 16px, 16px 16px, auto;
        }

        .materi-card:nth-child(3n) .materi-cover {
            background:
                linear-gradient(rgba(255,255,255,.17) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.17) 1px, transparent 1px),
                linear-gradient(135deg, #f5e5c8, #d9ae69);
            background-size: 16px 16px, 16px 16px, auto;
        }

        .materi-card-content {
            padding: 17px;
        }

        .materi-card h2 {
            margin: 0 0 8px;
            color: #25243b;
            font-size: 18px;
            line-height: 1.4;
        }

        .materi-description {
            min-height: 60px;
            margin: 0;
            color: #777587;
            font-size: 13px;
            line-height: 1.6;
        }

        .materi-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin: 15px 0;
        }

        .materi-meta span {
            padding: 4px 8px;
            border-radius: 5px;
            background: #f3f1f7;
            color: #777084;
            font-size: 11px;
        }

        .materi-link {
            display: inline-flex;
            min-height: 38px;
            align-items: center;
            justify-content: center;
            padding: 8px 13px;
            border-radius: 8px;
            background: #8750c5;
            color: #fff;
            font-size: 13px;
            font-weight: 700;
        }

        .materi-link:hover {
            background: #713cae;
        }

        .materi-notice {
            padding: 22px;
            border: 1px solid #ecebf1;
            border-radius: 11px;
            background: #fff;
            color: #777587;
        }

        .materi-notice.error {
            border-color: #f0c9c7;
            background: #fff7f6;
            color: #a12622;
        }

        @media (max-width: 900px) {
            .materi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .materi-page {
                width: min(100% - 30px, 1320px);
                padding-top: 22px;
            }

            .materi-grid {
                grid-template-columns: 1fr;
            }

            .materi-cover {
                min-height: 155px;
            }
        }
    </style>
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php">TKD<span>Smart</span></a>

    <nav class="main-nav" aria-label="Navigasi utama">
        <a href="view/dashboard.php">Dashboard</a>
        <a class="active" href="materi-siswa.php">Materi</a>
        <a href="history.php">Riwayat</a>
    </nav>

    <div class="account-nav">
        <span class="user-name"><?= e($nama) ?></span>
        <a class="logout-link" href="logout.php">Logout</a>
    </div>
</header>

<main class="materi-page">
    <section class="materi-heading">
        <p class="eyebrow">RUANG BELAJAR</p>
        <h1>Materi pembelajaran</h1>
        <p>Jelajahi materi yang telah disiapkan oleh guru.</p>
    </section>

    <?php if ($pageError !== ''): ?>
        <div class="materi-notice error" role="alert"><?= e($pageError) ?></div>
    <?php elseif ($materi): ?>
        <section class="materi-grid" aria-label="Daftar materi">
            <?php foreach ($materi as $index => $row): ?>
                <?php
                $deskripsi = trim((string) ($row['deskripsi'] ?? ''));
                if ($deskripsi === '') {
                    $deskripsi = 'Belum ada deskripsi materi.';
                } elseif (function_exists('mb_strlen') && mb_strlen($deskripsi) > 130) {
                    $deskripsi = mb_substr($deskripsi, 0, 130) . '…';
                } elseif (strlen($deskripsi) > 130) {
                    $deskripsi = substr($deskripsi, 0, 130) . '…';
                }

                $tanggal = '';
                if (!empty($row['created_at'])) {
                    $timestamp = strtotime((string) $row['created_at']);
                    if ($timestamp !== false) {
                        $tanggal = date('d M Y', $timestamp);
                    }
                }
                ?>
                <article class="materi-card">
                    <div class="materi-cover" aria-hidden="true">
                        Materi <?= (int) $index + 1 ?>
                    </div>

                    <div class="materi-card-content">
                        <h2><?= e($row['judul'] ?? 'Materi') ?></h2>
                        <p class="materi-description"><?= e($deskripsi) ?></p>

                        <div class="materi-meta">
                            <span>Guru: <?= e($row['nama_guru'] ?? 'Guru') ?></span>
                            <?php if ($tanggal !== ''): ?>
                                <span><?= e($tanggal) ?></span>
                            <?php endif; ?>
                        </div>

                        <a class="materi-link" href="lihat-materi.php?id=<?= (int) $row['id'] ?>">
                            Pelajari materi
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php else: ?>
        <section class="materi-notice">
            <h2>Belum ada materi</h2>
            <p>Materi pembelajaran yang tersedia akan muncul di sini.</p>
        </section>
    <?php endif; ?>
</main>

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> TKDSmart. Platform Pembelajaran Digital.</p>
</footer>
<script src="js/back-button.js" data-fallback="view/dashboard.php"></script>
</body>
</html>