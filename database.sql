CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `nama` varchar(100) NOT NULL,
    `email` varchar(100) NOT NULL,
    `password` varchar(255) NOT NULL,
    `role` enum('guru','siswa') DEFAULT 'siswa',
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `materi` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `judul` varchar(200) NOT NULL,
    `deskripsi` text DEFAULT NULL,
    `isi_materi` longtext NOT NULL,
    `video_url` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `materi_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `quizzes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `kode_quiz` varchar(10) DEFAULT NULL,
    `user_id` int(11) NOT NULL,
    `materi_id` int(11) DEFAULT NULL,
    `judul` varchar(150) NOT NULL,
    `deskripsi` text DEFAULT NULL,
    `waktu` int(11) DEFAULT 30,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `kode_quiz` (`kode_quiz`),
    KEY `user_id` (`user_id`),
    KEY `fk_quizzes_materi` (`materi_id`),
    CONSTRAINT `fk_quizzes_materi` FOREIGN KEY (`materi_id`) REFERENCES `materi` (`id`) ON DELETE SET NULL,
    CONSTRAINT `quizzes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `questions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `quiz_id` int(11) NOT NULL,
    `pertanyaan` text NOT NULL,
    `opsi_a` varchar(255) NOT NULL,
    `opsi_b` varchar(255) NOT NULL,
    `opsi_c` varchar(255) NOT NULL,
    `opsi_d` varchar(255) NOT NULL,
    `jawaban_benar` char(1) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `quiz_id` (`quiz_id`),
    CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `results` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `quiz_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL,
    `skor` int(11) NOT NULL,
    `jumlah_benar` int(11) NOT NULL,
    `jumlah_salah` int(11) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `quiz_id` (`quiz_id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `results_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`),
    CONSTRAINT `results_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;