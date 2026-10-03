<?php

function generateQuizCode($conn)
{
    do {

        $code = str_pad(
            random_int(100000, 999999),
            6,
            '0',
            STR_PAD_LEFT
        );

        $stmt = $conn->prepare("
            SELECT id
            FROM quizzes
            WHERE kode_quiz = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $code);

        $stmt->execute();

        $result = $stmt->get_result();

    } while ($result->num_rows > 0);

    return $code;
}