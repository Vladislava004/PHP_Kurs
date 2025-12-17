<?php
require_once __DIR__ . '/../config.php';
requireTeacher();

$pageTitle = 'Мои курсы';
require_once __DIR__ . '/../includes/header.php';

$pdo = getDBConnection();
$message = '';
$error = '';
$teacherId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $categoryId = (int)$_POST['category_id'];
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
                $stmt = $pdo->prepare("SELECT teacher_id FROM courses WHERE id = ?");
                $stmt->execute([$id]);
                $course = $stmt->fetch();
                
                if (!$course || $course['teacher_id'] != $teacherId) {
                    $error = 'Нет доступа к этому курсу';
                } else {
                    $title = trim($_POST['title'] ?? '');
                    $description = trim($_POST['description'] ?? '');
                    $categoryId = (int)$_POST['category_id'];
                    $status = $_POST['status'] ?? 'draft';
                    
                    if (empty($title)) {
                        $error = 'Название курса обязательно';
                    } elseif (strlen($title) > 255) {
                        $error = 'Название курса не может быть длиннее 255 символов';
                    } elseif (strlen($description) > 10000) {
                        $error = 'Описание не может быть длиннее 10000 символов';
                    } else {
                        $stmt = $pdo->prepare("UPDATE courses SET title = ?, description = ?, category_id = ?, status = ? WHERE id = ?");
                        $stmt->execute([$title, $description, $categoryId, $status, $id]);
                        $message = 'Курс успешно обновлен';
                    }
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("SELECT teacher_id FROM courses WHERE id = ?");
                $stmt->execute([$id]);
                $course = $stmt->fetch();
                
                if (!$course || $course['teacher_id'] != $teacherId) {
                    $error = 'Нет доступа к этому курсу';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = 'Курс успешно удален';
                }
                break;
        }
    }
}

// Получение курсов преподавателя
$courses = $pdo->prepare("SELECT c.*, cat.name as category_name 
                         FROM courses c 
                         LEFT JOIN categories cat ON c.category_id = cat.id 
                         WHERE c.teacher_id = ? 
                         ORDER BY c.created_at DESC");
$courses->execute([$teacherId]);
$courses = $courses->fetchAll();

// Получение категорий для формы
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Получение курса для редактирования
$editCourse = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$id, $teacherId]);
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
    <h2>Мои курсы</h2>
    <?php if (empty($courses)): ?>
        <div class="empty-state">
            <h3>У вас пока нет курсов</h3>
            <p>Создайте первый курс используя форму выше</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Категория</th>
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
                                <a href="lessons.php?course_id=<?php echo $course['id']; ?>" class="btn btn-sm btn-primary">Уроки</a>
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
