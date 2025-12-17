<?php
require_once __DIR__ . '/../config.php';
requireAdmin();

$pageTitle = 'Управление пользователями';
require_once __DIR__ . '/../includes/header.php';

$pdo = getDBConnection();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $username = trim($_POST['username'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $fullName = trim($_POST['full_name'] ?? '');
                $role = $_POST['role'] ?? 'teacher';
                
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
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                    $stmt->execute([$username, $email]);
                    if ($stmt->fetch()) {
                        $error = 'Пользователь с таким именем или email уже существует';
                    } else {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$username, $email, $hashedPassword, $fullName, $role]);
                        $message = 'Пользователь успешно создан';
                    }
                }
                break;
                
            case 'update':
                $id = (int)$_POST['id'];
                $username = trim($_POST['username'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $fullName = trim($_POST['full_name'] ?? '');
                $role = $_POST['role'] ?? 'teacher';
                
                if (empty($username) || empty($email) || empty($fullName)) {
                    $error = 'Имя пользователя, email и ФИО обязательны';
                } elseif (strlen($username) < 3 || strlen($username) > 50) {
                    $error = 'Имя пользователя должно содержать от 3 до 50 символов';
                } elseif (strlen($email) > 255) {
                    $error = 'Email не может быть длиннее 255 символов';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Некорректный email адрес';
                } elseif (strlen($fullName) > 255) {
                    $error = 'ФИО не может быть длиннее 255 символов';
                } elseif (!empty($password) && (strlen($password) < 6 || strlen($password) > 128)) {
                    $error = 'Пароль должен содержать от 6 до 128 символов';
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                    $stmt->execute([$username, $email, $id]);
                    if ($stmt->fetch()) {
                        $error = 'Пользователь с таким именем или email уже существует';
                    } else {
                        if (!empty($password)) {
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, full_name = ?, role = ? WHERE id = ?");
                            $stmt->execute([$username, $email, $hashedPassword, $fullName, $role, $id]);
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, role = ? WHERE id = ?");
                            $stmt->execute([$username, $email, $fullName, $role, $id]);
                        }
                        $message = 'Пользователь успешно обновлен';
                    }
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                
                if ($id == $_SESSION['user_id']) {
                    $error = 'Нельзя удалить самого себя';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = 'Пользователь успешно удален';
                }
                break;
        }
    }
}

$users = $pdo->query("SELECT id, username, email, role, full_name, created_at FROM users ORDER BY created_at DESC")->fetchAll();

$editUser = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT id, username, email, role, full_name FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $editUser = $stmt->fetch();
}
?>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <?php echo $editUser ? 'Редактировать пользователя' : 'Добавить пользователя'; ?>
        </h2>
    </div>
    <form method="POST" action="">
        <input type="hidden" name="action" value="<?php echo $editUser ? 'update' : 'create'; ?>">
        <?php if ($editUser): ?>
            <input type="hidden" name="id" value="<?php echo $editUser['id']; ?>">
        <?php endif; ?>
        <div class="form-group">
            <label for="username">Имя пользователя *</label>
            <input type="text" id="username" name="username" required value="<?php echo $editUser ? htmlspecialchars($editUser['username']) : ''; ?>" minlength="3" maxlength="50" placeholder="Введите имя пользователя">
            <small>От 3 до 50 символов</small>
        </div>
        <div class="form-group">
            <label for="email">Email *</label>
            <input type="email" id="email" name="email" required value="<?php echo $editUser ? htmlspecialchars($editUser['email']) : ''; ?>" maxlength="255" placeholder="example@email.com">
        </div>
        <div class="form-group">
            <label for="password">Пароль <?php echo $editUser ? '(оставьте пустым, чтобы не менять)' : '*'; ?></label>
            <input type="password" id="password" name="password" <?php echo $editUser ? '' : 'required'; ?> minlength="6" maxlength="128" placeholder="<?php echo $editUser ? 'Оставьте пустым для сохранения текущего пароля' : 'Минимум 6 символов'; ?>">
            <?php if (!$editUser): ?>
                <small>От 6 до 128 символов</small>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label for="full_name">ФИО *</label>
            <input type="text" id="full_name" name="full_name" required value="<?php echo $editUser ? htmlspecialchars($editUser['full_name']) : ''; ?>" maxlength="255" placeholder="Иванов Иван Иванович">
        </div>
        <div class="form-group">
            <label for="role">Роль *</label>
            <select id="role" name="role" required>
                <option value="teacher" <?php echo ($editUser && $editUser['role'] == 'teacher') ? 'selected' : ''; ?>>Преподаватель</option>
                <option value="admin" <?php echo ($editUser && $editUser['role'] == 'admin') ? 'selected' : ''; ?>>Администратор</option>
            </select>
            <small style="color: #666; font-size: 0.875rem;">
                Администратор может управлять всеми курсами, категориями и пользователями
            </small>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo $editUser ? 'Обновить' : 'Создать'; ?></button>
        <?php if ($editUser): ?>
            <a href="users.php" class="btn btn-secondary">Отмена</a>
        <?php endif; ?>
    </form>
</div>

<div class="table-container" style="margin-top: 2rem;">
    <h2>Список пользователей</h2>
    <?php if (empty($users)): ?>
        <div class="empty-state">
            <h3>Пользователи отсутствуют</h3>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Имя пользователя</th>
                    <th>Email</th>
                    <th>ФИО</th>
                    <th>Роль</th>
                    <th>Дата создания</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td>
                            <span class="status-badge <?php echo $user['role'] == 'admin' ? 'status-published' : 'status-draft'; ?>">
                                <?php echo $user['role'] == 'admin' ? 'Администратор' : 'Преподаватель'; ?>
                            </span>
                        </td>
                        <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                        <td>
                            <div class="actions">
                                <a href="?edit=<?php echo $user['id']; ?>" class="btn btn-sm btn-secondary">Редактировать</a>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Вы уверены, что хотите удалить этого пользователя?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Удалить</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

