<?php
require_once __DIR__ . '/../config.php';
requireTeacher();

$pageTitle = 'Содержание урока';
require_once __DIR__ . '/../includes/header.php';

$pdo = getDBConnection();
$teacherId = $_SESSION['user_id'];

if (!isset($_GET['id'])) {
    header('Location: lessons.php');
    exit;
}

$lessonId = (int)$_GET['id'];

// Получение урока с проверкой прав доступа
$stmt = $pdo->prepare("SELECT l.*, c.title as course_title 
                       FROM lessons l 
                       INNER JOIN courses c ON l.course_id = c.id 
                       WHERE l.id = ? AND c.teacher_id = ?");
$stmt->execute([$lessonId, $teacherId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    header('Location: lessons.php');
    exit;
}

// Получение материалов урока
$stmt = $pdo->prepare("SELECT * FROM materials WHERE lesson_id = ? ORDER BY created_at DESC");
$stmt->execute([$lessonId]);
$materials = $stmt->fetchAll();
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><?php echo htmlspecialchars($lesson['title']); ?></h2>
        <a href="lessons.php" class="btn btn-secondary">Назад к списку</a>
    </div>
    
    <div style="padding: 1.5rem;">
        <div style="margin-bottom: 1.5rem;">
            <strong>Курс:</strong> <?php echo htmlspecialchars($lesson['course_title']); ?>
        </div>
        
        <div style="margin-bottom: 1.5rem;">
            <strong>Тип урока:</strong> 
            <?php 
            $typeNames = ['lecture' => 'Лекция', 'material' => 'Материал', 'practice' => 'Практика'];
            echo $typeNames[$lesson['lesson_type']] ?? $lesson['lesson_type'];
            ?>
        </div>
        
        <?php if ($lesson['scheduled_datetime']): ?>
        <div style="margin-bottom: 1.5rem;">
            <strong>Дата и время проведения:</strong> 
            <?php echo date('d.m.Y в H:i', strtotime($lesson['scheduled_datetime'])); ?>
            <?php if ($lesson['duration_minutes']): ?>
                <br><strong>Длительность:</strong> <?php echo $lesson['duration_minutes']; ?> минут
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div style="margin-bottom: 1.5rem;">
            <strong>Статус:</strong> 
            <span class="status-badge status-<?php echo $lesson['status']; ?>">
                <?php 
                $statusNames = ['draft' => 'Черновик', 'published' => 'Опубликован', 'archived' => 'Архив'];
                echo $statusNames[$lesson['status']] ?? $lesson['status'];
                ?>
            </span>
        </div>
        
        <div style="margin-bottom: 1.5rem;">
            <strong>Проведен:</strong> 
            <?php if ($lesson['is_completed']): ?>
                <span class="status-badge status-published">Проведен</span>
            <?php else: ?>
                <span class="status-badge status-draft">Не проведен</span>
            <?php endif; ?>
        </div>
        
        <hr style="margin: 2rem 0; border: none; border-top: 2px solid #e0e0e0;">
        
        <div style="margin-bottom: 1.5rem;">
            <h3 style="margin-bottom: 1rem;">Содержание урока</h3>
            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 5px; white-space: pre-wrap; line-height: 1.8;">
                <?php echo nl2br(htmlspecialchars($lesson['content'])); ?>
            </div>
        </div>
        
        <?php if (!empty($materials)): ?>
        <div style="margin-top: 2rem;">
            <h3 style="margin-bottom: 1rem;">Прикрепленные файлы</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Название файла</th>
                            <th>Тип</th>
                            <th>Дата загрузки</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materials as $material): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($material['title']); ?></td>
                                <td><?php echo strtoupper($material['file_type'] ?? 'неизвестно'); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($material['created_at'])); ?></td>
                                <td>
                                    <a href="../<?php echo htmlspecialchars($material['file_path']); ?>" target="_blank" class="btn btn-sm btn-primary">Скачать</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div style="margin-top: 2rem; padding: 2rem; text-align: center; color: #999;">
            <p>Нет прикрепленных файлов</p>
        </div>
        <?php endif; ?>
        
        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #e0e0e0;">
            <a href="?edit=<?php echo $lesson['id']; ?>" class="btn btn-secondary">Редактировать урок</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

