<?php
// filepath: c:\xampp\htdocs\quizmaster\view\layout.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$role = $role ?? ($_SESSION['role'] ?? '');
$nama = $nama ?? ($_SESSION['nama'] ?? 'Pengguna');
$quizzes = is_array($quizzes ?? null) ? $quizzes : [];
$pageError = is_string($pageError ?? null) ? $pageError : '';

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$isGuru = $role === 'guru';
$isSiswa = $role === 'siswa';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - TKDSmart</title>
    <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="../index.php">TKD<span>Smart</span></a>

    <form class="dashboard-search" id="quizSearch" role="search">
        <label class="visually-hidden" for="quizSearchInput">Cari quiz</label>
        <input
            type="search"
            id="quizSearchInput"
            placeholder="Temukan quiz"
            autocomplete="off"
        >
        <button type="submit" aria-label="Cari">⌕</button>
    </form>

    <nav class="main-nav" aria-label="Navigasi utama">
        <a class="active" href="dashboard.php">Beranda</a>
        <?php if ($isSiswa): ?>
            <a href="../history.php">Aktivitas</a>
            <a href="../materi-siswa.php">Materi</a>
        <?php elseif ($isGuru): ?>
            <a href="../hasil-belajar.php">Aktivitas</a>
            <a href="../daftar-materi.php">Materi</a>
        <?php endif; ?>
    </nav>

    <div class="account-nav">
        <span class="user-name"><?= e($nama) ?></span>
        <a class="logout-link" href="../logout.php">Logout</a>
    </div>
</header>

<main class="page-content" id="beranda">
    <section class="feature-row">
        <div class="join-panel">
            <p class="eyebrow"><?= $isGuru ? 'KELOLA PEMBELAJARAN' : 'MULAI BELAJAR' ?></p>
            <h1><?= $isGuru ? 'Buat quiz untuk siswa.' : 'Belajar jadi lebih seru.' ?></h1>
            <p class="panel-description">
                <?= $isGuru
                    ? 'Buat quiz dan pantau hasil belajar siswa.'
                    : 'Masukkan kode quiz dari guru untuk mulai mengerjakan.' ?>
            </p>

            <?php if ($isSiswa): ?>
                <form class="join-form" action="../join-quiz.php" method="POST">
                    <label class="visually-hidden" for="kode_quiz">Kode quiz</label>
                    <input
                        type="text"
                        id="kode_quiz"
                        name="kode_quiz"
                        placeholder="Masukkan kode quiz"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        inputmode="numeric"
                        autocomplete="off"
                        required
                    >
                    <button type="submit">Gabung</button>
                </form>
            <?php else: ?>
                <a class="banner-button" href="../create-quiz.php">Buat quiz</a>
            <?php endif; ?>
        </div>

        <aside class="welcome-card">
            <div>
                <p class="eyebrow">TKDSMART</p>
                <h2><?= $isGuru ? 'Ruang Guru' : 'Ruang Belajar' ?></h2>
                <p>
                    <?= $isGuru
                        ? 'Kelola materi dan quiz dalam satu tempat.'
                        : 'Temukan materi dan quiz untuk mendukung belajarmu.' ?>
                </p>
                <a
                    class="banner-button"
                    href="<?= $isGuru ? '../daftar-materi.php' : '../materi-siswa.php' ?>"
                ><?= $isGuru ? 'Kelola materi' : 'Lihat materi' ?></a>
            </div>
            <span class="banner-mark" aria-hidden="true">T</span>
        </aside>
    </section>

    <?php if ($pageError !== ''): ?>
        <div class="notice notice-error" role="alert"><?= e($pageError) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['created'])): ?>
        <div class="notice" role="status">Quiz berhasil dibuat.</div>
    <?php elseif (isset($_GET['updated'])): ?>
        <div class="notice" role="status">Quiz berhasil diperbarui.</div>
    <?php elseif (isset($_GET['deleted'])): ?>
        <div class="notice" role="status">Quiz berhasil dihapus.</div>
    <?php elseif (isset($_GET['delete_error'])): ?>
        <div class="notice notice-error" role="alert">
            Quiz gagal dihapus. Pastikan quiz tersebut milik Anda lalu coba lagi.
        </div>
    <?php endif; ?>

    <?php require __DIR__ . '/quizzes.php'; ?>

    <section class="section-block" id="menu">
        <div class="section-title">
            <div>
                <p class="eyebrow">AKSES CEPAT</p>
                <h2><?= $isGuru ? 'Menu Guru' : 'Menu Siswa' ?></h2>
            </div>
        </div>

        <div class="menu-grid">
            <?php if ($isGuru): ?>
                <a class="menu-card" href="../create-quiz.php">
                    <span class="menu-accent purple"></span>
                    <h3>Buat quiz</h3>
                    <p>Siapkan quiz baru untuk siswa.</p>
                </a>
                <a class="menu-card" href="../hasil-belajar.php">
                    <span class="menu-accent blue"></span>
                    <h3>Hasil belajar</h3>
                    <p>Pantau hasil pengerjaan quiz.</p>
                </a>
                <a class="menu-card" href="../daftar-materi.php">
                    <span class="menu-accent orange"></span>
                    <h3>Materi saya</h3>
                    <p>Kelola materi pembelajaran.</p>
                </a>
            <?php else: ?>
                <a class="menu-card" href="../materi-siswa.php">
                    <span class="menu-accent purple"></span>
                    <h3>Materi belajar</h3>
                    <p>Buka materi dan video pembelajaran.</p>
                </a>
                <a class="menu-card" href="../history.php">
                    <span class="menu-accent blue"></span>
                    <h3>Riwayat quiz</h3>
                    <p>Lihat quiz yang pernah dikerjakan.</p>
                </a>
                <a class="menu-card" href="../leaderboard.php">
                    <span class="menu-accent orange"></span>
                    <h3>Leaderboard</h3>
                    <p>Lihat peringkat hasil quiz.</p>
                </a>
            <?php endif; ?>
        </div>
    </section>
</main>

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> TKDSmart. Platform Pembelajaran Digital.</p>
</footer>

<script>
const searchForm = document.getElementById('quizSearch');
const searchInput = document.getElementById('quizSearchInput');

searchForm.addEventListener('submit', (event) => {
    event.preventDefault();
    document.getElementById('quiz-list').scrollIntoView({ behavior: 'smooth' });
});

searchInput.addEventListener('input', () => {
    const query = searchInput.value.trim().toLocaleLowerCase('id');

    document.querySelectorAll('.dashboard-quiz-card').forEach((card) => {
        card.hidden = !card.dataset.search.includes(query);
    });
});
</script>
</body>
</html>