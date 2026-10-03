<?php
// filepath: c:\xampp\htdocs\quizmaster\register.php
session_start();

include "config/database.php";

$nama = '';
$email = '';

if (isset($_POST['register'])) {
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $plainPassword = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    if (!in_array($role, ['siswa', 'guru'], true)) {
        $error = 'Pilihan jenis akun tidak valid.';
    } else {
        $password = password_hash($plainPassword, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            "INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("ssss", $nama, $email, $password, $role);

        if ($stmt->execute()) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $conn->insert_id;
            $_SESSION['nama'] = $nama;
            $_SESSION['role'] = $role;

            header("Location: view/dashboard.php");
            exit;
        }

        $error = 'Pendaftaran gagal. Email mungkin sudah digunakan.';
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar - TKDSmart</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background:
                radial-gradient(ellipse at 12% 10%, rgba(135, 80, 197, .13), transparent 32%),
                #f8f8fb;
            color: #343348;
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.55;
        }

        .form-container {
            width: min(100%, 440px);
            padding: 38px;
            border: 1px solid #ecebf1;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 12px 34px rgba(37, 36, 59, .08);
        }

        .form-container::before {
            display: block;
            margin-bottom: 27px;
            color: #8750c5;
            content: "TKD";
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -.8px;
        }

        h1 {
            margin: 0 0 8px;
            color: #25243b;
            font-size: 29px;
            letter-spacing: -.5px;
        }

        .description {
            margin: 0 0 24px;
            color: #777587;
        }

        label {
            display: block;
            margin: 16px 0 7px;
            color: #343348;
            font-size: 14px;
            font-weight: 700;
        }

        input,
        select {
            width: 100%;
            min-height: 46px;
            padding: 11px 13px;
            border: 1px solid #dedce5;
            border-radius: 8px;
            background: #fff;
            color: #343348;
            font: inherit;
            transition: border-color .2s, box-shadow .2s;
        }

        input::placeholder {
            color: #a09daa;
        }

        input:focus,
        select:focus {
            border-color: #8750c5;
            outline: none;
            box-shadow: 0 0 0 3px rgba(135, 80, 197, .13);
        }

        .btn-primary {
            width: 100%;
            min-height: 46px;
            margin-top: 23px;
            border: 0;
            border-radius: 8px;
            background: #8750c5;
            color: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s, transform .2s, box-shadow .2s;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            background: #713cae;
            box-shadow: 0 5px 14px rgba(135, 80, 197, .22);
        }

        .error {
            margin: 0 0 18px;
            padding: 11px 13px;
            border: 1px solid #f0c9c7;
            border-left: 4px solid #b42318;
            border-radius: 8px;
            background: #fff7f6;
            color: #a12622;
            font-size: 14px;
        }

        .login-link {
            margin: 22px 0 0;
            color: #777587;
            text-align: center;
            font-size: 14px;
        }

        .login-link a {
            color: #8750c5;
            font-weight: 700;
            text-decoration: none;
        }

        .login-link a:hover {
            color: #713cae;
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            body {
                padding: 16px;
            }

            .form-container {
                padding: 29px 23px;
            }
        }
    </style>
</head>
<body>
    <main class="form-container">
        <h1>Daftar Akun</h1>
        <p class="description">Buat akun TKDSmart untuk mulai belajar.</p>

        <?php if (isset($error)): ?>
            <p class="error" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <form method="POST">
            <label for="nama">Nama lengkap</label>
            <input
                type="text"
                id="nama"
                name="nama"
                placeholder="Masukkan nama lengkap"
                value="<?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="name"
                required
            >

            <label for="email">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                placeholder="nama@email.com"
                value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="email"
                required
            >

            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                placeholder="Buat password"
                autocomplete="new-password"
                required
            >

            <label for="role">Jenis akun</label>
            <select id="role" name="role" required>
                <option value="siswa">Siswa</option>
                <option value="guru">Guru</option>
            </select>

            <button type="submit" name="register" class="btn-primary">Daftar</button>
        </form>

        <p class="login-link">
            Sudah punya akun? <a href="login.php">Login</a>
        </p>
    </main>
</body>
</html>