<?php
require_once __DIR__ . '/../config.php';
requireAdmin();

$pageTitle = 'Управление курсами';
require_once __DIR__ . '/../includes/header.php';

$pdo = getDBConnection();
$message = '';
$error = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $categoryId = (int)$_POST['category_id'];
                $teacherId = (int)$_POST['teacher_id'];
                $status = $_POST['status'] ?? 'draft';
                
                if (empty($title)) {
                    $error = 'Название курса обязательно';
                } elseif (strlen($title) > 255) {
                    $error = 'Название курса не может быть длиннее 255 символов';
                } elseif (strlen($description) > 10000) {
                    $error = 'Описание не может быть длиннее 10000 символов';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO courses (title, description, category_id, teacher_id, status) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $description, $categoryId, $teacherId, $status]);
                    $message = 'Курс успешно создан';
                }
                break;
                
            case 'update':
                $id = (int)$_POST['id'];
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $categoryId = (int)$_POST['category_id'];
                $teacherId = (int)$_POST['teacher_id'];
                $status = $_POST['status'] ?? 'draft';
                
                if (empty($title)) {
                    $error = 'Название курса обязательно';
                } elseif (strlen($title) > 255) {
                    $error = 'Название курса не может быть длиннее 255 символов';
                } elseif (strlen($description) > 10000) {
                    $error = 'Описание не может быть длиннее 10000 символов';
                } else {
                    $stmt = $pdo->prepare("UPDATE courses SET title = ?, description = ?, category_id = ?, teacher_id = ?, status = ? WHERE id = ?");
                    $stmt->execute([$title, $description, $categoryId, $teacherId, $status, $id]);
                    $message = 'Курс успешно обновлен';
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Курс успешно удален';
                break;
        }
    }
}


$courses = $pdo->query("SELECT c.*, cat.name as category_name, u.full_name as teacher_name 
                       FROM courses c 
                       LEFT JOIN categories cat ON c.category_id = cat.id 
                       LEFT JOIN users u ON c.teacher_id = u.id 
                       ORDER BY c.created_at DESC")->fetchAll();


$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$teachers = $pdo->query("SELECT * FROM users WHERE role = 'teacher' ORDER BY full_name")->fetchAll();


$editCourse = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$id]);
    $editCourse = $stmt->fetch();
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
            <?php echo $editCourse ? 'Редактировать курс' : 'Добавить курс'; ?>
        </h2>
    </div>
    <form method="POST" action="">
        <input type="hidden" name="action" value="<?php echo $editCourse ? 'update' : 'create'; ?>">
        <?php if ($editCourse): ?>
            <input type="hidden" name="id" value="<?php echo $editCourse['id']; ?>">
        <?php endif; ?>
        <div class="form-group">
            <label for="title">Название курса *</label>
            <input type="text" id="title" name="title" required value="<?php echo $editCourse ? htmlspecialchars($editCourse['title']) : ''; ?>" maxlength="255" placeholder="Введите название курса">
        </div>
        <div class="form-group">
            <label for="description">Описание</label>
            <textarea id="description" name="description" maxlength="10000" placeholder="Введите описание курса (необязательно)"><?php echo $editCourse ? htmlspecialchars($editCourse['description']) : ''; ?></textarea>
            <small>Максимум 10000 символов</small>
        </div>
        <div class="form-group">
            <label for="category_id">Категория *</label>
            <select id="category_id" name="category_id" required>
                <option value="">Выберите категорию</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>" <?php echo ($editCourse && $editCourse['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="teacher_id">Преподаватель *</label>
            <select id="teacher_id" name="teacher_id" required>
                <option value="">Выберите преподавателя</option>
                <?php foreach ($teachers as $teacher): ?>
                    <option value="<?php echo $teacher['id']; ?>" <?php echo ($editCourse && $editCourse['teacher_id'] == $teacher['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($teacher['full_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="status">Статус</label>
            <select id="status" name="status">
                <option value="draft" <?php echo ($editCourse && $editCourse['status'] == 'draft') ? 'selected' : ''; ?>>Черновик</option>
                <option value="published" <?php echo ($editCourse && $editCourse['status'] == 'published') ? 'selected' : ''; ?>>Опубликован</option>
                <option value="archived" <?php echo ($editCourse && $editCourse['status'] == 'archived') ? 'selected' : ''; ?>>Архив</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo $editCourse ? 'Обновить' : 'Создать'; ?></button>
        <?php if ($editCourse): ?>
            <a href="courses.php" class="btn btn-secondary">Отмена</a>
        <?php endif; ?>
    </form>
</div>

<div class="table-container" style="margin-top: 2rem;">
    <h2>Список курсов</h2>
    <?php if (empty($courses)): ?>
        <div class="empty-state">
            <h3>Курсы отсутствуют</h3>
            <p>Создайте первый курс используя форму выше</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Категория</th>
                    <th>Преподаватель</th>
                    <th>Статус</th>
                    <th>Дата создания</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $course): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($course['title']); ?></td>
                        <td><?php echo htmlspecialchars($course['category_name'] ?? 'Не указана'); ?></td>
                        <td><?php echo htmlspecialchars($course['teacher_name'] ?? 'Не указан'); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $course['status']; ?>">
                                <?php 
                                $statusNames = ['draft' => 'Черновик', 'published' => 'Опубликован', 'archived' => 'Архив'];
                                echo $statusNames[$course['status']] ?? $course['status'];
                                ?>
                            </span>
                        </td>
                        <td><?php echo date('d.m.Y H:i', strtotime($course['created_at'])); ?></td>
                        <td>
                            <div class="actions">
                                <a href="?edit=<?php echo $course['id']; ?>" class="btn btn-sm btn-secondary">Редактировать</a>
                                <a href="../teacher/lessons.php?course_id=<?php echo $course['id']; ?>" class="btn btn-sm btn-primary">Уроки</a>
                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Вы уверены, что хотите удалить этот курс?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $course['id']; ?>">
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





