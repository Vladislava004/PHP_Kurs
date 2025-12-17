<?php
require_once 'config.php';

// Если уже авторизован, перенаправляем на dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$pdo = getDBConnection();
$message = '';
$error = '';

// Обработка регистрации
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');
    
    // Валидация
    if (empty($username) || empty($email) || empty($password) || empty($fullName)) {
        $error = 'Все поля обязательны для заполнения';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = 'Имя пользователя должно содержать от 3 до 50 символов';
    } elseif (strlen($email) > 255) {
        $error = 'Email не может быть длиннее 255 символов';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Некорректный email адрес';
    } elseif (strlen($fullName) > 255) {
        $error = 'ФИО не может быть длиннее 255 символов';
    } elseif (strlen($password) < 6 || strlen($password) > 128) {
        $error = 'Пароль должен содержать от 6 до 128 символов';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Пароли не совпадают';
    } else {
        // Проверка уникальности username и email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = 'Пользователь с таким именем или email уже существует';
        } else {
            // Регистрация нового пользователя (по умолчанию роль - преподаватель)
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, 'teacher')");
            $stmt->execute([$username, $email, $hashedPassword, $fullName]);
            
            $message = 'Регистрация успешна! Теперь вы можете войти в систему.';
            // Перенаправляем на страницу входа через 2 секунды
            header('Refresh: 2; url=index.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация - Образовательная платформа</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h1>Регистрация преподавателя</h1>
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <p style="text-align: center; margin-top: var(--spacing-md);">
                    <a href="index.php" class="btn btn-primary" style="width: 100%;">Перейти к входу</a>
                </p>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="register">
                    <div class="form-group">
                        <label for="username">Имя пользователя *</label>
                        <input type="text" id="username" name="username" required autofocus 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                               minlength="3" maxlength="50" placeholder="Введите имя пользователя">
                        <small>От 3 до 50 символов</small>
                    </div>
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                               maxlength="255" placeholder="example@email.com">
                    </div>
                    <div class="form-group">
                        <label for="full_name">ФИО *</label>
                        <input type="text" id="full_name" name="full_name" required 
                               value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                               maxlength="255" placeholder="Иванов Иван Иванович">
                    </div>
                    <div class="form-group">
                        <label for="password">Пароль *</label>
                        <input type="password" id="password" name="password" required minlength="6" maxlength="128" placeholder="Минимум 6 символов">
                        <small>От 6 до 128 символов</small>
                    </div>
                    <div class="form-group">
                        <label for="password_confirm">Подтверждение пароля *</label>
                        <input type="password" id="password_confirm" name="password_confirm" required minlength="6" maxlength="128" placeholder="Повторите пароль">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: var(--spacing-sm);">Зарегистрироваться</button>
                </form>
                <div style="margin-top: 25px; padding-top: var(--spacing-md); border-top: 1px solid var(--border); text-align: center;">
                    <p style="color: var(--text-secondary); font-size: 0.875rem;">
                        Уже есть аккаунт? <a href="index.php" style="color: var(--primary); text-decoration: none; font-weight: 600;">Войти</a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>





