<?php
// filepath: c:\xampp\htdocs\quizmaster\login.php
session_start();

include "config/database.php";

if (isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama'] = $user['nama'];
            $_SESSION['role'] = $user['role'];

            header("Location: view/dashboard.php");
            exit;
        }

        $error = "Password salah.";
    } else {
        $error = "Akun tidak ditemukan.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - TKDSmart</title>

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
                radial-gradient(ellipse at 12% 10%, rgba(135, 80, 197, .12), transparent 32%),
                #f8f8fb;
            color: #343348;
            font-family: Arial, Helvetica, sans-serif;
        }

        .form-container {
            width: min(100%, 430px);
            padding: 38px;
            border: 1px solid #ecebf1;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 12px 34px rgba(37, 36, 59, .08);
        }

        .form-container::before {
            display: block;
            margin-bottom: 28px;
            color: #8750c5;
            content: "TKD";
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -.8px;
        }

        .form-container::after {
            position: absolute;
            /* Tidak digunakan; logo dibuat melalui ::before. */
        }

        h1 {
            margin: 0 0 8px;
            color: #25243b;
            font-size: 29px;
            letter-spacing: -.5px;
        }

        .description {
            margin: 0 0 25px;
            color: #777587;
            line-height: 1.6;
        }

        label {
            display: block;
            margin: 17px 0 7px;
            color: #343348;
            font-size: 14px;
            font-weight: 700;
        }

        input {
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

        input:focus {
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
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s, transform .2s;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            background: #713cae;
        }

        .error {
            padding: 11px 13px;
            border: 1px solid #f0c9c7;
            border-left: 4px solid #b42318;
            border-radius: 8px;
            background: #fff7f6;
            color: #a12622;
            font-size: 14px;
        }

        .register-link {
            margin: 22px 0 0;
            color: #777587;
            text-align: center;
            font-size: 14px;
        }

        .register-link a {
            color: #8750c5;
            font-weight: 700;
            text-decoration: none;
        }

        .register-link a:hover {
            color: #713cae;
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .form-container {
                padding: 28px 23px;
            }
        }
    </style>
</head>
<body>
    <main class="form-container">
        <h1>Login</h1>
        <p class="description">Masuk ke akun TKDSmart Anda.</p>

        <?php if (isset($error)): ?>
            <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <form method="POST">
            <label for="email">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                placeholder="nama@email.com"
                autocomplete="email"
                required
            >

            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                placeholder="Masukkan password"
                autocomplete="current-password"
                required
            >

            <button type="submit" name="login" class="btn-primary">Login</button>
        </form>

        <p class="register-link">
            Belum punya akun? <a href="register.php">Daftar</a>
        </p>
    </main>
</body>
</html>