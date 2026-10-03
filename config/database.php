<?php

$config = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'user' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'database' => getenv('DB_NAME') ?: 'quizmaster',
];

$hostingConfigFile = __DIR__ . '/database.hosting.php';
if (is_file($hostingConfigFile)) {
    $hostingConfig = require $hostingConfigFile;
    if (is_array($hostingConfig)) {
        $config = array_replace($config, $hostingConfig);
    }
}

$conn = new mysqli(
    $config['host'],
    $config['user'],
    $config['password'],
    $config['database']
);

if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

?>