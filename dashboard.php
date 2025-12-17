<?php
require_once 'config.php';
requireLogin();

$pageTitle = 'Панель управления';
require_once 'includes/header.php';

$pdo = getDBConnection();

// Получение статистики
$stats = [];

if (isAdmin()) {
    $stats['categories'] = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    $stats['courses'] = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $stats['lessons'] = $pdo->query("SELECT COUNT(*) FROM lessons")->fetchColumn();
    $stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
} else {
    $teacherId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE teacher_id = ?");
    $stmt->execute([$teacherId]);
    $stats['courses'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons l INNER JOIN courses c ON l.course_id = c.id WHERE c.teacher_id = ?");
    $stmt->execute([$teacherId]);
    $stats['lessons'] = $stmt->fetchColumn();
}
?>

<div class="welcome-section">
    <h1 style="margin-bottom: 0.5rem;">Добро пожаловать, <?php echo htmlspecialchars($_SESSION['user_full_name']); ?>!</h1>
    <p style="color: var(--text-secondary); font-size: 1.125rem; margin-bottom: 2rem;">Вот краткий обзор вашей статистики</p>
</div>

<div class="dashboard-grid">
    <?php if (isAdmin()): ?>
        <div class="dashboard-card">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                <h3>Категории</h3>
            </div>
            <div class="number"><?php echo $stats['categories']; ?></div>
            <p style="color: var(--text-secondary); font-size: 0.875rem; margin-top: 0.5rem;">Всего категорий</p>
        </div>
        <div class="dashboard-card">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                <h3>Курсы</h3>
            </div>
            <div class="number"><?php echo $stats['courses']; ?></div>
            <p style="color: var(--text-secondary); font-size: 0.875rem; margin-top: 0.5rem;">Всего курсов</p>
        </div>
        <div class="dashboard-card">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                <h3>Уроки</h3>
            </div>
            <div class="number"><?php echo $stats['lessons']; ?></div>
            <p style="color: var(--text-secondary); font-size: 0.875rem; margin-top: 0.5rem;">Всего уроков</p>
        </div>
        <div class="dashboard-card">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                <h3>Пользователи</h3>
            </div>
            <div class="number"><?php echo $stats['users']; ?></div>
            <p style="color: var(--text-secondary); font-size: 0.875rem; margin-top: 0.5rem;">Всего пользователей</p>
        </div>
    <?php else: ?>
        <div class="dashboard-card">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                <h3>Мои курсы</h3>
            </div>
            <div class="number"><?php echo $stats['courses']; ?></div>
            <p style="color: var(--text-secondary); font-size: 0.875rem; margin-top: 0.5rem;">Активных курсов</p>
        </div>
        <div class="dashboard-card">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                <h3>Мои уроки</h3>
            </div>
            <div class="number"><?php echo $stats['lessons']; ?></div>
            <p style="color: var(--text-secondary); font-size: 0.875rem; margin-top: 0.5rem;">Всего уроков</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>

