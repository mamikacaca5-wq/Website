<?php
// filepath: c:\xampp\htdocs\quizmaster\view\quizzes.php
$quizzes = is_array($quizzes ?? null) ? $quizzes : [];
$isGuru = ($role ?? ($_SESSION['role'] ?? '')) === 'guru';

$validQuizzes = array_values(array_filter(
    $quizzes,
    static fn($quiz) => is_array($quiz) && (int) ($quiz['id'] ?? 0) > 0
));
?>

<section class="section-block dashboard-quiz-shelf" id="quiz-list">
    <div class="section-title">
        <div>
            <p class="eyebrow"><?= $isGuru ? 'RUANG GURU' : 'RUANG BELAJAR' ?></p>
            <h2><?= $isGuru ? 'Quiz yang Anda buat' : 'Quiz tersedia' ?></h2>
        </div>
    </div>

    <?php if ($validQuizzes): ?>
        <div class="quiz-grid">
            <?php foreach ($validQuizzes as $index => $quiz): ?>
                <?php
                $quizId = (int) $quiz['id'];
                $title = (string) ($quiz['judul'] ?? 'Quiz tanpa judul');
                $description = trim((string) ($quiz['deskripsi'] ?? ''));
                $searchText = mb_strtolower($title . ' ' . $description, 'UTF-8');
                ?>
                <article
                    class="quiz-card dashboard-quiz-card"
                    data-search="<?= e($searchText) ?>"
                >
                    <div class="quiz-cover cover-<?= $index % 5 ?>">
                        <span class="cover-tag">
                            <?= (int) ($quiz['jumlah_soal'] ?? 0) ?> Soal
                        </span>
                        <?php if (!empty($quiz['kode_quiz'])): ?>
                            <span class="cover-code"><?= e($quiz['kode_quiz']) ?></span>
                        <?php endif; ?>
                        <span class="cover-title"><?= e($title) ?></span>
                    </div>

                    <div class="quiz-content">
                        <h3><?= e($title) ?></h3>
                        <p class="quiz-description">
                            <?= $description !== '' ? e($description) : 'Quiz pembelajaran TKDSmart.' ?>
                        </p>

                        <?php if (!$isGuru && !empty($quiz['nama_guru'])): ?>
                            <p class="teacher-name">Guru: <?= e($quiz['nama_guru']) ?></p>
                        <?php endif; ?>

                        <div class="quiz-meta">
                            <span><?= (int) ($quiz['waktu'] ?? 0) ?> detik per soal</span>
                        </div>

                        <div class="quiz-actions">
                            <?php if ($isGuru): ?>
                                <a class="card-button primary" href="../edit-quiz.php?id=<?= $quizId ?>">
                                    Kelola quiz
                                </a>
                                <a class="card-button secondary" href="../teacher-stats.php">
                                    Statistik
                                </a>
                            <?php else: ?>
                                <a class="card-button primary" href="../quiz-lobby.php?id=<?= $quizId ?>">
                                    Lihat quiz
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="dashboard-no-results" hidden>Tidak ada quiz yang cocok dengan pencarian.</p>
    <?php else: ?>
        <div class="empty-state">
            <h3>Belum ada quiz</h3>
            <p>Quiz yang tersedia akan ditampilkan di sini.</p>
            <?php if ($isGuru): ?>
                <a class="card-button primary" href="../create-quiz.php">Buat quiz</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<script>
const quizSearchInput = document.getElementById('quizSearchInput');
const noQuizResults = document.querySelector('.dashboard-no-results');

if (quizSearchInput && noQuizResults) {
    quizSearchInput.addEventListener('input', () => {
        const visibleCards = [...document.querySelectorAll('.dashboard-quiz-card')]
            .some((card) => !card.hidden);

        noQuizResults.hidden = visibleCards;
    });
}
</script>