<?php
session_start();
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>TKDSmart - Platform Pembelajaran Digital</title>

    <style>
        :root {
            --navy: #172554;
            --blue: #3159c9;
            --blue-dark: #2447a8;
            --text: #202938;
            --muted: #667085;
            --surface: #ffffff;
            --background: #f6f8fc;
            --border: #e6eaf1;
            --purple: #8750c5;
            --purple-dark: #713cae;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--background);
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .container {
            width: min(1100px, calc(100% - 40px));
            margin: 0 auto;
        }

        .main-header {
            position: sticky;
            z-index: 10;
            top: 0;
            background: rgba(255, 255, 255, .96);
            border-bottom-color: #ecebf1;
        }

        .header-content {
            min-height: 72px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .logo {
            color: var(--purple);
            font-size: 21px;
            font-weight: 700;
            letter-spacing: -0.6px;
        }

        .logo span {
            color: var(--blue);
        }

        nav {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-link {
            padding: 9px 13px;
            color: #475467;
            font-size: 14px;
            font-weight: 600;
        }

        .nav-link:hover {
            color: var(--purple);
        }

        .nav-button {
            padding: 9px 15px;
            border: 1px solid var(--border);
            border-radius: 7px;
            border-color: #e8e3ee;
            background: #f3eef9;
            color: var(--purple-dark);
        }

        .hero {
            padding: 68px 0;
            background:
                radial-gradient(ellipse at 88% 15%, rgba(135, 80, 197, .17), transparent 32%),
                linear-gradient(125deg, #f1eaff, #fff 66%, #f7f2fc);
        }

        .hero-content {
            max-width: 760px;
            padding: clamp(26px, 5vw, 44px);
            border: 1px solid #e9e0f2;
            border-radius: 16px;
            background: rgba(255, 255, 255, .78);
            box-shadow: 0 8px 24px rgba(37, 36, 59, .06);
        }

        .eyebrow {
            display: inline-block;
            margin-bottom: 16px;
            color: var(--purple);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }

        h1 {
            max-width: 650px;
            margin: 0;
            color: #25243b;
            font-size: clamp(36px, 6vw, 54px);
            line-height: 1.12;
            letter-spacing: -1.8px;
        }

        h1 span {
            color: var(--purple);
        }

        .hero-content > p {
            max-width: 600px;
            margin: 20px 0 28px;
            color: var(--muted);
            font-size: 17px;
            line-height: 1.7;
        }

        .hero-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .btn {
            display: inline-flex;
            min-height: 46px;
            align-items: center;
            justify-content: center;
            padding: 11px 18px;
            border: 1px solid transparent;
            border-radius: 7px;
            font-size: 14px;
            font-weight: 700;
            transition: background 0.2s ease, border-color 0.2s ease;
        }

        .btn-primary {
            background: var(--purple);
            box-shadow: 0 4px 10px rgba(135, 80, 197, .18);
            color: #fff;
        }

        .btn-primary:hover {
            background: var(--purple-dark);
        }

        .btn-secondary {
            border-color: #e3d9ef;
            background: #fff;
            color: var(--navy);
        }

        .btn-secondary:hover {
            border-color: var(--purple);
            color: var(--purple);
        }

        .features {
            padding: 64px 0 76px;
            padding-top: 56px;
        }

        .section-heading {
            max-width: 600px;
            margin-bottom: 28px;
        }

        .section-heading h2 {
            margin: 0 0 8px;
            color: var(--navy);
            font-size: 28px;
            letter-spacing: -0.6px;
        }

        .section-heading p {
            margin: 0;
            color: var(--muted);
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
        }

        .feature-card {
            min-height: 190px;
            padding: 23px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--surface);
            box-shadow: 0 5px 18px rgba(23, 37, 84, 0.035);
            border-color: #ecebf1;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(37, 36, 59, .05);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .feature-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 22px rgba(37, 36, 59, .09);
        }

        .feature-number {
            display: inline-block;
            margin-bottom: 17px;
            color: var(--purple);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.8px;
        }

        .feature-card h3 {
            margin: 0 0 9px;
            color: var(--navy);
            font-size: 17px;
        }

        .feature-card p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }

        footer {
            padding: 22px 20px;
            border-top: 1px solid var(--border);
            background: #fff;
            color: var(--muted);
            text-align: center;
            font-size: 13px;
            border-top-color: #ecebf1;
        }

        footer p {
            margin: 0;
        }

        @media (max-width: 800px) {
            .feature-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 520px) {
            .container {
                width: min(100% - 32px, 1100px);
            }

            .header-content {
                min-height: 66px;
                gap: 10px;
            }

            .logo {
                font-size: 19px;
            }

            nav {
                gap: 2px;
            }

            .nav-link {
                padding: 8px;
                font-size: 13px;
            }

            .nav-button {
                padding: 8px 10px;
            }

            .hero {
                padding: 38px 0;
            }

            .hero-content > p {
                font-size: 15px;
            }

            .features {
                padding: 48px 0 56px;
            }

            .feature-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .feature-card {
                min-height: auto;
            }
        }
    </style>
</head>
<body>

<header class="main-header">
    <div class="container header-content">
        <a class="logo" href="index.php">TKD<span>Smart</span></a>

        <nav aria-label="Navigasi utama">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a class="nav-link" href="view/dashboard.php">Dashboard</a>
                <a class="nav-button" href="logout.php">Logout</a>
            <?php else: ?>
                <a class="nav-link" href="login.php">Login</a>
                <a class="nav-button" href="register.php">Daftar</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main>
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <span class="eyebrow">Belajar dengan cara yang lebih terarah</span>
                <h1>Selamat datang di <span>TKDSmart</span></h1>
                <p>
                    Satu tempat untuk mengakses materi, video pembelajaran,
                    dan kuis yang membantu kegiatan belajar menjadi lebih mudah.
                </p>

                <div class="hero-buttons">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="view/dashboard.php" class="btn btn-primary">Masuk ke Dashboard</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary">Mulai Belajar</a>
                        <a href="register.php" class="btn btn-secondary">Buat Akun</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="features">
        <div class="container">
            <div class="section-heading">
                <h2>Fitur untuk mendukung belajar</h2>
                <p>Semua kebutuhan belajar tersusun dalam satu platform yang sederhana.</p>
            </div>

            <div class="feature-grid">
                <article class="feature-card">
                    <span class="feature-number">01</span>
                    <h3>Materi Pembelajaran</h3>
                    <p>Akses materi yang disiapkan guru dan pelajari sesuai kebutuhan.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-number">02</span>
                    <h3>Video Pembelajaran</h3>
                    <p>Pelajari topik melalui video sebagai sumber belajar tambahan.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-number">03</span>
                    <h3>Kuis Pembelajaran</h3>
                    <p>Uji pemahaman materi melalui kuis yang tersedia.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-number">04</span>
                    <h3>Hasil Belajar</h3>
                    <p>Lihat hasil kuis dan pantau perkembangan belajar Anda.</p>
                </article>
            </div>
        </div>
    </section>
</main>

<footer>
    <p>&copy; <?= date('Y') ?> TKDSmart. Platform Pembelajaran Digital.</p>
</footer>

<script src="js/back-button.js" data-fallback="index.php"></script>
</body>
</html>