<?php
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - Образовательная платформа</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h1>Вход в систему</h1>
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="username">Имя пользователя или Email</label>
                    <input type="text" id="username" name="username" required autofocus maxlength="255" placeholder="Введите имя пользователя или email">
                </div>
                <div class="form-group">
                    <label for="password">Пароль</label>
                    <input type="password" id="password" name="password" required maxlength="128" placeholder="Введите пароль">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: var(--spacing-sm);">Войти</button>
            </form>
            <div style="margin-top: 25px; padding-top: var(--spacing-md); border-top: 1px solid var(--border); text-align: center;">
                <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: var(--spacing-xs);">
                    Нет аккаунта? <a href="register.php" style="color: var(--primary); text-decoration: none; font-weight: 600;">Зарегистрироваться</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>

