<?php
require_once __DIR__ . '/../config.php';
requireAdmin();

$pageTitle = 'Управление категориями';
require_once __DIR__ . '/../includes/header.php';

$pdo = getDBConnection();
$message = '';
$error = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name)) {
                    $error = 'Название категории обязательно';
                } elseif (strlen($name) > 255) {
                    $error = 'Название категории не может быть длиннее 255 символов';
                } elseif (strlen($description) > 10000) {
                    $error = 'Описание не может быть длиннее 10000 символов';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                    $stmt->execute([$name, $description]);
                    $message = 'Категория успешно создана';
                }
                break;
                
            case 'update':
                $id = (int)$_POST['id'];
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name)) {
                    $error = 'Название категории обязательно';
                } elseif (strlen($name) > 255) {
                    $error = 'Название категории не может быть длиннее 255 символов';
                } elseif (strlen($description) > 10000) {
                    $error = 'Описание не может быть длиннее 10000 символов';
                } else {
                    $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $id]);
                    $message = 'Категория успешно обновлена';
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE category_id = ?");
                $stmt->execute([$id]);
                $coursesCount = $stmt->fetchColumn();
                
                if ($coursesCount > 0) {
                    $error = 'Невозможно удалить категорию: в ней есть курсы';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = 'Категория успешно удалена';
                }
                break;
        }
    }
}


$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();


$editCategory = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $editCategory = $stmt->fetch();
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
            <?php echo $editCategory ? 'Редактировать категорию' : 'Добавить категорию'; ?>
        </h2>
    </div>
    <form method="POST" action="">
        <input type="hidden" name="action" value="<?php echo $editCategory ? 'update' : 'create'; ?>">
        <?php if ($editCategory): ?>
            <input type="hidden" name="id" value="<?php echo $editCategory['id']; ?>">
        <?php endif; ?>
        <div class="form-group">
            <label for="name">Название категории *</label>
            <input type="text" id="name" name="name" required value="<?php echo $editCategory ? htmlspecialchars($editCategory['name']) : ''; ?>" maxlength="255" placeholder="Введите название категории">
        </div>
        <div class="form-group">
            <label for="description">Описание</label>
            <textarea id="description" name="description" maxlength="10000" placeholder="Введите описание категории (необязательно)"><?php echo $editCategory ? htmlspecialchars($editCategory['description']) : ''; ?></textarea>
            <small>Максимум 10000 символов</small>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo $editCategory ? 'Обновить' : 'Создать'; ?></button>
        <?php if ($editCategory): ?>
            <a href="categories.php" class="btn btn-secondary">Отмена</a>
        <?php endif; ?>
    </form>
</div>

<div class="table-container" style="margin-top: 2rem;">
    <h2>Список категорий</h2>
    <?php if (empty($categories)): ?>
        <div class="empty-state">
            <h3>Категории отсутствуют</h3>
            <p>Создайте первую категорию используя форму выше</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Описание</th>
                    <th>Дата создания</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                        <td><?php echo htmlspecialchars($category['description'] ?? ''); ?></td>
                        <td><?php echo date('d.m.Y H:i', strtotime($category['created_at'])); ?></td>
                        <td>
                            <div class="actions">
                                <a href="?edit=<?php echo $category['id']; ?>" class="btn btn-sm btn-secondary">Редактировать</a>
                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Вы уверены, что хотите удалить эту категорию?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Удалить</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>





