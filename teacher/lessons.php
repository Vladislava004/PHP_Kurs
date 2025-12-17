<?php
require_once __DIR__ . '/../config.php';
requireTeacher();

$pageTitle = 'Мои уроки';
require_once __DIR__ . '/../includes/header.php';

$pdo = getDBConnection();
$message = '';
$error = '';
$teacherId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $courseId = (int)$_POST['course_id'];
                $title = trim($_POST['title'] ?? '');
                $content = trim($_POST['content'] ?? '');
                $lessonType = $_POST['lesson_type'] ?? 'lecture';
                $status = $_POST['status'] ?? 'draft';
                $scheduledDate = !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : '';
                $scheduledHour = !empty($_POST['scheduled_hour']) ? str_pad((int)$_POST['scheduled_hour'], 2, '0', STR_PAD_LEFT) : '';
                $scheduledMinute = !empty($_POST['scheduled_minute']) ? str_pad((int)$_POST['scheduled_minute'], 2, '0', STR_PAD_LEFT) : '';
                $scheduledDatetime = ($scheduledDate && $scheduledHour !== '' && $scheduledMinute !== '') ? $scheduledDate . ' ' . $scheduledHour . ':' . $scheduledMinute . ':00' : null;
                $durationMinutes = !empty($_POST['duration_minutes']) ? (int)$_POST['duration_minutes'] : null;
                $isCompleted = isset($_POST['is_completed']) ? 1 : 0;
                
                $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
                $stmt->execute([$courseId, $teacherId]);
                
                if (!$stmt->fetch()) {
                    $error = 'Курс не найден или нет доступа';
                } elseif (empty($title) || empty($content)) {
                    $error = 'Название и содержание урока обязательны';
                } elseif (strlen($title) > 255) {
                    $error = 'Название урока не может быть длиннее 255 символов';
                } elseif (strlen($content) > 50000) {
                    $error = 'Содержание урока не может быть длиннее 50000 символов';
                } elseif ($durationMinutes !== null && ($durationMinutes < 1 || $durationMinutes > 1440)) {
                    $error = 'Длительность урока должна быть от 1 до 1440 минут (24 часа)';
                } else {
                    // Проверка конфликтов времени
                    require_once __DIR__ . '/../includes/check_time_conflicts.php';
                    $conflicts = [];
                    if ($scheduledDatetime && $durationMinutes) {
                        $conflicts = checkTimeConflicts($pdo, $teacherId, $scheduledDatetime, $durationMinutes);
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO lessons (course_id, title, content, lesson_type, status, scheduled_datetime, duration_minutes, is_completed) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$courseId, $title, $content, $lessonType, $status, $scheduledDatetime, $durationMinutes, $isCompleted]);
                    
                    if (!empty($conflicts)) {
                        $conflictMessage = 'Внимание! Обнаружен конфликт времени с другими уроками:<br>';
                        foreach ($conflicts as $conflict) {
                            $conflictMessage .= '- ' . htmlspecialchars($conflict['title']) . ' (' . $conflict['start_time'] . ' - ' . $conflict['end_time'] . ', ' . $conflict['duration_minutes'] . ' мин.)<br>';
                        }
                        $message = 'Урок успешно создан, но ' . $conflictMessage;
                    } else {
                        $message = 'Урок успешно создан';
                    }
                    $lessonId = $pdo->lastInsertId();
                    
                    // Обработка загрузки файлов
                    if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
                        $uploadDir = __DIR__ . '/../uploads/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        
                        $allowedTypes = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar', 'ppt', 'pptx', 'xls', 'xlsx'];
                        $maxFileSize = 10 * 1024 * 1024; // 10 MB
                        
                        foreach ($_FILES['files']['name'] as $key => $fileName) {
                            if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
                                $fileSize = $_FILES['files']['size'][$key];
                                $fileTmp = $_FILES['files']['tmp_name'][$key];
                                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                                
                                if (!in_array($fileExt, $allowedTypes)) {
                                    $error = "Тип файла '{$fileExt}' не разрешен";
                                    continue;
                                }
                                
                                if ($fileSize > $maxFileSize) {
                                    $error = "Файл '{$fileName}' слишком большой (максимум 10 MB)";
                                    continue;
                                }
                                
                                $newFileName = uniqid() . '_' . time() . '.' . $fileExt;
                                $filePath = $uploadDir . $newFileName;
                                
                                if (move_uploaded_file($fileTmp, $filePath)) {
                                    $relativePath = 'uploads/' . $newFileName;
                                    $stmt = $pdo->prepare("INSERT INTO materials (lesson_id, title, file_path, file_type, description) VALUES (?, ?, ?, ?, ?)");
                                    $stmt->execute([$lessonId, $fileName, $relativePath, $fileExt, '']);
                                }
                            }
                        }
                    }
                    
                    $message = 'Урок успешно создан';
                }
                break;
                
            case 'update':
                $id = (int)$_POST['id'];
                
                // Проверка прав доступа
                $stmt = $pdo->prepare("SELECT c.teacher_id FROM lessons l 
                                       INNER JOIN courses c ON l.course_id = c.id 
                                       WHERE l.id = ?");
                $stmt->execute([$id]);
                $lesson = $stmt->fetch();
                
                if (!$lesson || $lesson['teacher_id'] != $teacherId) {
                    $error = 'Нет доступа к этому уроку';
                } else {
                    $title = trim($_POST['title'] ?? '');
                    $content = trim($_POST['content'] ?? '');
                    $lessonType = $_POST['lesson_type'] ?? 'lecture';
                    $status = $_POST['status'] ?? 'draft';
                    // Объединяем дату и время
                    $scheduledDate = !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : '';
                    $scheduledHour = !empty($_POST['scheduled_hour']) ? str_pad((int)$_POST['scheduled_hour'], 2, '0', STR_PAD_LEFT) : '';
                    $scheduledMinute = !empty($_POST['scheduled_minute']) ? str_pad((int)$_POST['scheduled_minute'], 2, '0', STR_PAD_LEFT) : '';
                    $scheduledDatetime = ($scheduledDate && $scheduledHour !== '' && $scheduledMinute !== '') ? $scheduledDate . ' ' . $scheduledHour . ':' . $scheduledMinute . ':00' : null;
                    $durationMinutes = !empty($_POST['duration_minutes']) ? (int)$_POST['duration_minutes'] : null;
                    $isCompleted = isset($_POST['is_completed']) ? 1 : 0;
                    
                    if (empty($title) || empty($content)) {
                        $error = 'Название и содержание урока обязательны';
                    } elseif (strlen($title) > 255) {
                        $error = 'Название урока не может быть длиннее 255 символов';
                    } elseif (strlen($content) > 50000) {
                        $error = 'Содержание урока не может быть длиннее 50000 символов';
                    } elseif ($durationMinutes !== null && ($durationMinutes < 1 || $durationMinutes > 1440)) {
                        $error = 'Длительность урока должна быть от 1 до 1440 минут (24 часа)';
                    } else {
                        // Проверка конфликтов времени
                        require_once __DIR__ . '/../includes/check_time_conflicts.php';
                        $conflicts = [];
                        if ($scheduledDatetime && $durationMinutes) {
                            $conflicts = checkTimeConflicts($pdo, $teacherId, $scheduledDatetime, $durationMinutes, $id);
                        }
                        
                        $stmt = $pdo->prepare("UPDATE lessons SET title = ?, content = ?, lesson_type = ?, status = ?, scheduled_datetime = ?, duration_minutes = ?, is_completed = ? WHERE id = ?");
                        $stmt->execute([$title, $content, $lessonType, $status, $scheduledDatetime, $durationMinutes, $isCompleted, $id]);
                        
                        if (!empty($conflicts)) {
                            $conflictMessage = 'Внимание! Обнаружен конфликт времени с другими уроками:<br>';
                            foreach ($conflicts as $conflict) {
                                $conflictMessage .= '- ' . htmlspecialchars($conflict['title']) . ' (' . $conflict['start_time'] . ' - ' . $conflict['end_time'] . ', ' . $conflict['duration_minutes'] . ' мин.)<br>';
                            }
                            $message = 'Урок успешно обновлен, но ' . $conflictMessage;
                        } else {
                            $message = 'Урок успешно обновлен';
                        }
                        
                        // Обработка загрузки новых файлов
                        if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
                            $uploadDir = __DIR__ . '/../uploads/';
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0755, true);
                            }
                            
                            $allowedTypes = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar', 'ppt', 'pptx', 'xls', 'xlsx'];
                            $maxFileSize = 10 * 1024 * 1024; // 10 MB
                            
                            foreach ($_FILES['files']['name'] as $key => $fileName) {
                                if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
                                    $fileSize = $_FILES['files']['size'][$key];
                                    $fileTmp = $_FILES['files']['tmp_name'][$key];
                                    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                                    
                                    if (!in_array($fileExt, $allowedTypes)) {
                                        continue;
                                    }
                                    
                                    if ($fileSize > $maxFileSize) {
                                        continue;
                                    }
                                    
                                    $newFileName = uniqid() . '_' . time() . '.' . $fileExt;
                                    $filePath = $uploadDir . $newFileName;
                                    
                                    if (move_uploaded_file($fileTmp, $filePath)) {
                                        $relativePath = 'uploads/' . $newFileName;
                                        $stmt = $pdo->prepare("INSERT INTO materials (lesson_id, title, file_path, file_type, description) VALUES (?, ?, ?, ?, ?)");
                                        $stmt->execute([$id, $fileName, $relativePath, $fileExt, '']);
                                    }
                                }
                            }
                        }
                        
                        $message = 'Урок успешно обновлен';
                    }
                }
                break;
                
            case 'toggle_completed':
                $id = (int)$_POST['id'];
                
                // Проверка прав доступа
                $stmt = $pdo->prepare("SELECT c.teacher_id, l.is_completed FROM lessons l 
                                       INNER JOIN courses c ON l.course_id = c.id 
                                       WHERE l.id = ?");
                $stmt->execute([$id]);
                $lesson = $stmt->fetch();
                
                if (!$lesson || $lesson['teacher_id'] != $teacherId) {
                    $error = 'Нет доступа к этому уроку';
                } else {
                    $newStatus = $lesson['is_completed'] ? 0 : 1;
                    $stmt = $pdo->prepare("UPDATE lessons SET is_completed = ? WHERE id = ?");
                    $stmt->execute([$newStatus, $id]);
                    $message = $newStatus ? 'Урок отмечен как проведенный' : 'Урок отмечен как непроведенный';
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                
                // Проверка прав доступа
                $stmt = $pdo->prepare("SELECT c.teacher_id FROM lessons l 
                                       INNER JOIN courses c ON l.course_id = c.id 
                                       WHERE l.id = ?");
                $stmt->execute([$id]);
                $lesson = $stmt->fetch();
                
                if (!$lesson || $lesson['teacher_id'] != $teacherId) {
                    $error = 'Нет доступа к этому уроку';
                } else {
                    // Удаляем файлы материалов
                    $stmt = $pdo->prepare("SELECT file_path FROM materials WHERE lesson_id = ?");
                    $stmt->execute([$id]);
                    $materials = $stmt->fetchAll();
                    foreach ($materials as $material) {
                        if ($material['file_path'] && file_exists(__DIR__ . '/../' . $material['file_path'])) {
                            @unlink(__DIR__ . '/../' . $material['file_path']);
                        }
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM lessons WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = 'Урок успешно удален';
                }
                break;
                
            case 'delete_file':
                $materialId = (int)$_POST['material_id'];
                
                // Проверка прав доступа
                $stmt = $pdo->prepare("SELECT m.*, c.teacher_id FROM materials m 
                                       INNER JOIN lessons l ON m.lesson_id = l.id 
                                       INNER JOIN courses c ON l.course_id = c.id 
                                       WHERE m.id = ?");
                $stmt->execute([$materialId]);
                $material = $stmt->fetch();
                
                if (!$material || $material['teacher_id'] != $teacherId) {
                    $error = 'Нет доступа к этому файлу';
                } else {
                    // Удаляем файл
                    if ($material['file_path'] && file_exists(__DIR__ . '/../' . $material['file_path'])) {
                        @unlink(__DIR__ . '/../' . $material['file_path']);
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM materials WHERE id = ?");
                    $stmt->execute([$materialId]);
                    $message = 'Файл успешно удален';
                }
                break;
        }
    }
}

// Получение курсов преподавателя для фильтра
$courses = $pdo->prepare("SELECT id, title FROM courses WHERE teacher_id = ? ORDER BY title");
$courses->execute([$teacherId]);
$courses = $courses->fetchAll();

// Фильтр по курсу
$filterCourseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;

// Поиск по названию
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Сортировка по статусу
$sortByStatus = isset($_GET['sort_status']) ? $_GET['sort_status'] : 'all'; // all, draft, published, archived

// Построение запроса с фильтрами
$whereConditions = ["c.teacher_id = ?"];
$params = [$teacherId];

if ($filterCourseId) {
    $whereConditions[] = "l.course_id = ?";
    $params[] = $filterCourseId;
}

if (!empty($searchQuery)) {
    $whereConditions[] = "l.title LIKE ?";
    $params[] = '%' . $searchQuery . '%';
}

if ($sortByStatus !== 'all') {
    $whereConditions[] = "l.status = ?";
    $params[] = $sortByStatus;
}

$whereClause = implode(' AND ', $whereConditions);

// Определение сортировки
$orderBy = "l.created_at DESC";
if ($sortByStatus !== 'all') {
    $orderBy = "l.status ASC, l.created_at DESC";
}

// Получение уроков
if ($filterCourseId) {
    // Проверка прав доступа к курсу
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$filterCourseId, $teacherId]);
    if (!$stmt->fetch()) {
        $lessons = [];
        $error = 'Курс не найден или нет доступа';
    } else {
        $stmt = $pdo->prepare("SELECT l.*, c.title as course_title 
                              FROM lessons l 
                              INNER JOIN courses c ON l.course_id = c.id 
                              WHERE {$whereClause}
                              ORDER BY {$orderBy}");
        $stmt->execute($params);
        $lessons = $stmt->fetchAll();
    }
} else {
    $stmt = $pdo->prepare("SELECT l.*, c.title as course_title 
                          FROM lessons l 
                          INNER JOIN courses c ON l.course_id = c.id 
                          WHERE {$whereClause}
                          ORDER BY {$orderBy}");
    $stmt->execute($params);
    $lessons = $stmt->fetchAll();
}

// Получение урока для редактирования
$editLesson = null;
$editLessonMaterials = [];
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT l.* FROM lessons l 
                          INNER JOIN courses c ON l.course_id = c.id 
                          WHERE l.id = ? AND c.teacher_id = ?");
    $stmt->execute([$id, $teacherId]);
    $editLesson = $stmt->fetch();
    
    if ($editLesson) {
        $stmt = $pdo->prepare("SELECT * FROM materials WHERE lesson_id = ? ORDER BY created_at DESC");
        $stmt->execute([$id]);
        $editLessonMaterials = $stmt->fetchAll();
    }
}
?>

<?php if ($message): ?>
    <div class="alert <?php echo (strpos($message, 'конфликт') !== false) ? 'alert-error' : 'alert-success'; ?>">
        <?php echo strpos($message, 'конфликт') !== false ? $message : htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <?php echo $editLesson ? 'Редактировать урок' : 'Добавить урок'; ?>
        </h2>
    </div>
    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="action" value="<?php echo $editLesson ? 'update' : 'create'; ?>">
        <?php if ($editLesson): ?>
            <input type="hidden" name="id" value="<?php echo $editLesson['id']; ?>">
        <?php endif; ?>
        <div class="form-group">
            <label for="course_id">Курс *</label>
            <select id="course_id" name="course_id" required <?php echo $editLesson ? 'disabled' : ''; ?>>
                <option value="">Выберите курс</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>" <?php echo ($editLesson && $editLesson['course_id'] == $course['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($course['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($editLesson): ?>
                <input type="hidden" name="course_id" value="<?php echo $editLesson['course_id']; ?>">
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label for="title">Название урока *</label>
            <input type="text" id="title" name="title" required value="<?php echo $editLesson ? htmlspecialchars($editLesson['title']) : ''; ?>" maxlength="255" placeholder="Введите название урока">
        </div>
        <div class="form-group">
            <label for="content">Содержание урока *</label>
            <textarea id="content" name="content" required style="min-height: 200px;" maxlength="50000" placeholder="Введите содержание урока"><?php echo $editLesson ? htmlspecialchars($editLesson['content']) : ''; ?></textarea>
            <small>Максимум 50000 символов</small>
        </div>
        <div class="form-group">
            <label for="files">Прикрепленные файлы</label>
            <input type="file" id="files" name="files[]" multiple accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.gif,.zip,.rar,.ppt,.pptx,.xls,.xlsx">
            <small style="color: #666; font-size: 0.875rem; display: block; margin-top: 0.5rem;">
                Можно загрузить несколько файлов. Максимальный размер файла: 10 MB. Разрешенные типы: PDF, DOC, DOCX, TXT, JPG, PNG, GIF, ZIP, RAR, PPT, PPTX, XLS, XLSX
            </small>
            <?php if ($editLesson && !empty($editLessonMaterials)): ?>
                <div style="margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 5px;">
                    <strong>Загруженные файлы:</strong>
                    <ul style="margin-top: 0.5rem; margin-left: 1.5rem;">
                        <?php foreach ($editLessonMaterials as $material): ?>
                            <li style="margin-bottom: 0.5rem;">
                                <a href="../<?php echo htmlspecialchars($material['file_path']); ?>" target="_blank" style="color: #667eea; text-decoration: none;">
                                    <?php echo htmlspecialchars($material['title']); ?>
                                </a>
                                <form method="POST" action="" style="display: inline; margin-left: 0.5rem;" onsubmit="return confirm('Удалить этот файл?');">
                                    <input type="hidden" name="action" value="delete_file">
                                    <input type="hidden" name="material_id" value="<?php echo $material['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Удалить</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label for="lesson_type">Тип урока</label>
            <select id="lesson_type" name="lesson_type">
                <option value="lecture" <?php echo ($editLesson && $editLesson['lesson_type'] == 'lecture') ? 'selected' : ''; ?>>Лекция</option>
                <option value="material" <?php echo ($editLesson && $editLesson['lesson_type'] == 'material') ? 'selected' : ''; ?>>Материал</option>
                <option value="practice" <?php echo ($editLesson && $editLesson['lesson_type'] == 'practice') ? 'selected' : ''; ?>>Практика</option>
            </select>
        </div>
        <div class="form-group">
            <label for="status">Статус</label>
            <select id="status" name="status">
                <option value="draft" <?php echo ($editLesson && $editLesson['status'] == 'draft') ? 'selected' : ''; ?>>Черновик</option>
                <option value="published" <?php echo ($editLesson && $editLesson['status'] == 'published') ? 'selected' : ''; ?>>Опубликован</option>
                <option value="archived" <?php echo ($editLesson && $editLesson['status'] == 'archived') ? 'selected' : ''; ?>>Архив</option>
            </select>
        </div>
        <div class="form-group">
            <label for="scheduled_datetime">Дата и время проведения урока</label>
            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                <input type="date" id="scheduled_date" name="scheduled_date" 
                       value="<?php echo ($editLesson && $editLesson['scheduled_datetime']) ? date('Y-m-d', strtotime($editLesson['scheduled_datetime'])) : ''; ?>"
                       style="flex: 1; min-width: 150px;">
                <div style="display: flex; align-items: center; gap: 0.25rem; flex: 1; min-width: 200px;">
                    <select id="scheduled_hour" name="scheduled_hour" style="flex: 1; padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 5px;">
                        <option value="">--</option>
                        <?php 
                        $currentHour = ($editLesson && $editLesson['scheduled_datetime']) ? (int)date('H', strtotime($editLesson['scheduled_datetime'])) : '';
                        for ($i = 0; $i < 24; $i++): 
                            $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
                        ?>
                            <option value="<?php echo $hour; ?>" <?php echo $currentHour === $i ? 'selected' : ''; ?>>
                                <?php echo $hour; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <span style="font-size: 1.2rem; font-weight: bold;">:</span>
                    <select id="scheduled_minute" name="scheduled_minute" style="flex: 1; padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 5px;">
                        <option value="">--</option>
                        <?php 
                        $currentMinute = ($editLesson && $editLesson['scheduled_datetime']) ? (int)date('i', strtotime($editLesson['scheduled_datetime'])) : '';
                        for ($i = 0; $i < 60; $i += 1): 
                            $minute = str_pad($i, 2, '0', STR_PAD_LEFT);
                        ?>
                            <option value="<?php echo $minute; ?>" <?php echo $currentMinute === $i ? 'selected' : ''; ?>>
                                <?php echo $minute; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <input type="hidden" id="scheduled_datetime" name="scheduled_datetime" 
                   value="<?php echo ($editLesson && $editLesson['scheduled_datetime']) ? date('Y-m-d\TH:i', strtotime($editLesson['scheduled_datetime'])) : ''; ?>">
            <small style="color: #666; font-size: 0.875rem; display: block; margin-top: 0.5rem;">
                Формат времени: 00:00 - 23:59.
            </small>
        </div>
        <div class="form-group">
            <label for="duration_minutes">Длительность урока (в минутах)</label>
            <input type="number" id="duration_minutes" name="duration_minutes" 
                   value="<?php echo ($editLesson && $editLesson['duration_minutes']) ? $editLesson['duration_minutes'] : ''; ?>"
                   min="1" max="1440" placeholder="Например: 90">
            <small style="color: #666; font-size: 0.875rem; display: block; margin-top: 0.5rem;">
                Укажите, сколько минут будет длиться урок (например: 45, 60, 90)
            </small>
            <div id="conflict-warning" style="display: none; margin-top: 0.5rem; padding: 0.75rem; background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; color: #856404;">
                <strong>Внимание!</strong> <span id="conflict-message"></span>
            </div>
        </div>
        <script>
        // Проверка конфликтов времени при изменении даты/времени или длительности
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('scheduled_date');
            const hourSelect = document.getElementById('scheduled_hour');
            const minuteSelect = document.getElementById('scheduled_minute');
            const durationInput = document.getElementById('duration_minutes');
            const conflictWarning = document.getElementById('conflict-warning');
            const conflictMessage = document.getElementById('conflict-message');
            
            let checkTimeout;
            
            function checkConflicts() {
                clearTimeout(checkTimeout);
                checkTimeout = setTimeout(function() {
                    const date = dateInput.value;
                    const hour = hourSelect.value;
                    const minute = minuteSelect.value;
                    const duration = durationInput.value;
                    
                    if (!date || hour === '' || minute === '' || !duration) {
                        conflictWarning.style.display = 'none';
                        return;
                    }
                    
                    const scheduledDatetime = date + ' ' + hour.padStart(2, '0') + ':' + minute.padStart(2, '0') + ':00';
                    
                    // AJAX запрос для проверки конфликтов
                    const formData = new FormData();
                    formData.append('scheduled_datetime', scheduledDatetime);
                    formData.append('duration_minutes', duration);
                    <?php if ($editLesson): ?>
                    formData.append('exclude_lesson_id', <?php echo $editLesson['id']; ?>);
                    <?php endif; ?>
                    
                    fetch('check_conflicts.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.conflicts && data.conflicts.length > 0) {
                            let message = 'Обнаружен конфликт времени с уроками:<br>';
                            data.conflicts.forEach(function(conflict) {
                                message += '- ' + conflict.title + ' (' + conflict.start_time + ' - ' + conflict.end_time + ', ' + conflict.duration_minutes + ' мин.)<br>';
                            });
                            conflictMessage.innerHTML = message;
                            conflictWarning.style.display = 'block';
                        } else {
                            conflictWarning.style.display = 'none';
                        }
                    })
                    .catch(error => {
                        console.error('Ошибка при проверке конфликтов:', error);
                    });
                }, 500); // Задержка 500мс для уменьшения количества запросов
            }
            
            if (dateInput) dateInput.addEventListener('change', checkConflicts);
            if (hourSelect) hourSelect.addEventListener('change', checkConflicts);
            if (minuteSelect) minuteSelect.addEventListener('change', checkConflicts);
            if (durationInput) durationInput.addEventListener('input', checkConflicts);
        });
        </script>
        <script>
        // Объединяем дату и время в одно поле
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('scheduled_date');
            const hourSelect = document.getElementById('scheduled_hour');
            const minuteSelect = document.getElementById('scheduled_minute');
            const datetimeInput = document.getElementById('scheduled_datetime');
            
            function updateDateTime() {
                const date = dateInput.value || '';
                const hour = hourSelect.value || '';
                const minute = minuteSelect.value || '';
                
                if (date && hour !== '' && minute !== '') {
                    const time = hour.padStart(2, '0') + ':' + minute.padStart(2, '0');
                    datetimeInput.value = date + 'T' + time;
                } else {
                    datetimeInput.value = '';
                }
            }
            
            dateInput.addEventListener('change', updateDateTime);
            hourSelect.addEventListener('change', updateDateTime);
            minuteSelect.addEventListener('change', updateDateTime);
            
            // Инициализация при загрузке
            updateDateTime();
        });
        </script>
        <?php if ($editLesson): ?>
        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" id="is_completed" name="is_completed" value="1" 
                       <?php echo ($editLesson && $editLesson['is_completed']) ? 'checked' : ''; ?>>
                <span>Урок проведен</span>
            </label>
        </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary"><?php echo $editLesson ? 'Обновить' : 'Создать'; ?></button>
        <?php if ($editLesson): ?>
            <a href="lessons.php<?php echo $filterCourseId ? '?course_id=' . $filterCourseId : ''; ?>" class="btn btn-secondary">Отмена</a>
        <?php endif; ?>
    </form>
</div>

<div class="card" style="margin-top: 2rem;">
    <div class="card-header">
        <h2 class="card-title">Фильтр по курсу</h2>
    </div>
    <form method="GET" action="">
        <div class="form-group">
            <select name="course_id" onchange="this.form.submit()">
                <option value="">Все курсы</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>" <?php echo ($filterCourseId == $course['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($course['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<div class="table-container" style="margin-top: 2rem;">
    <h2>Мои уроки</h2>
    
    <!-- Форма поиска и фильтрации -->
    <div style="padding: 1.5rem 2rem; background: rgba(255, 255, 255, 0.5); border-bottom: 2px solid rgba(4, 120, 87, 0.1);">
        <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 1; min-width: 200px;">
                <label for="search" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem; color: var(--text-secondary);">Поиск по названию</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" 
                       placeholder="Введите название урока..." 
                       style="width: 100%; padding: 0.75rem 1rem; border: 2px solid rgba(4, 120, 87, 0.25); border-radius: 8px; font-size: 0.9375rem;">
                <?php if ($filterCourseId): ?>
                    <input type="hidden" name="course_id" value="<?php echo $filterCourseId; ?>">
                <?php endif; ?>
            </div>
            <div style="flex: 0 0 auto; min-width: 180px;">
                <label for="sort_status" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem; color: var(--text-secondary);">Сортировка по статусу</label>
                <select id="sort_status" name="sort_status" 
                        style="width: 100%; padding: 0.75rem 1rem; border: 2px solid rgba(4, 120, 87, 0.25); border-radius: 8px; font-size: 0.9375rem; cursor: pointer;"
                        onchange="this.form.submit()">
                    <option value="all" <?php echo $sortByStatus === 'all' ? 'selected' : ''; ?>>Все статусы</option>
                    <option value="draft" <?php echo $sortByStatus === 'draft' ? 'selected' : ''; ?>>Черновик</option>
                    <option value="published" <?php echo $sortByStatus === 'published' ? 'selected' : ''; ?>>Опубликован</option>
                    <option value="archived" <?php echo $sortByStatus === 'archived' ? 'selected' : ''; ?>>Архив</option>
                </select>
            </div>
            <div style="flex: 0 0 auto; display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem; font-size: 0.9375rem;">Найти</button>
                <?php if (!empty($searchQuery) || $sortByStatus !== 'all'): ?>
                    <a href="?<?php echo $filterCourseId ? 'course_id=' . $filterCourseId : ''; ?>" class="btn btn-secondary" style="padding: 0.75rem 1.5rem; font-size: 0.9375rem; text-decoration: none; display: inline-flex; align-items: center;">Сбросить</a>
                <?php endif; ?>
            </div>
        </form>
        <?php if (!empty($searchQuery) || $sortByStatus !== 'all'): ?>
            <div style="margin-top: 1rem; padding: 0.75rem; background: rgba(4, 120, 87, 0.1); border-radius: 8px; font-size: 0.875rem; color: var(--text-secondary);">
                <?php if (!empty($searchQuery)): ?>
                    <strong>Поиск:</strong> "<?php echo htmlspecialchars($searchQuery); ?>"
                <?php endif; ?>
                <?php if ($sortByStatus !== 'all'): ?>
                    <?php if (!empty($searchQuery)): ?> | <?php endif; ?>
                    <strong>Статус:</strong> 
                    <?php 
                    $statusNames = ['draft' => 'Черновик', 'published' => 'Опубликован', 'archived' => 'Архив'];
                    echo $statusNames[$sortByStatus] ?? $sortByStatus;
                    ?>
                <?php endif; ?>
                <span style="color: var(--text-light);"> (найдено: <?php echo count($lessons); ?>)</span>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (empty($lessons)): ?>
        <div class="empty-state">
            <h3>У вас пока нет уроков</h3>
            <p>Создайте первый урок используя форму выше</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Курс</th>
                    <th>Тип</th>
                    <th>Дата/время проведения</th>
                    <th>Проведен</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lessons as $lesson): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($lesson['title']); ?></td>
                        <td><?php echo htmlspecialchars($lesson['course_title']); ?></td>
                        <td>
                            <?php 
                            $typeNames = ['lecture' => 'Лекция', 'material' => 'Материал', 'practice' => 'Практика'];
                            echo $typeNames[$lesson['lesson_type']] ?? $lesson['lesson_type'];
                            ?>
                        </td>
                        <td>
                            <?php if ($lesson['scheduled_datetime']): ?>
                                <?php echo date('d.m.Y в H:i', strtotime($lesson['scheduled_datetime'])); ?> (24ч формат)
                                <?php if ($lesson['duration_minutes']): ?>
                                    <br><small style="color: #666;">Длительность: <?php echo $lesson['duration_minutes']; ?> мин.</small>
                                <?php endif; ?>
                                <?php 
                                $now = time();
                                $lessonTime = strtotime($lesson['scheduled_datetime']);
                                $diff = $lessonTime - $now;
                                if ($diff < 0):
                                    echo '<br><small style="color: #999;">Прошедший урок</small>';
                                endif;
                                ?>
                            <?php else: ?>
                                <span style="color: #999;">Не запланирован</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($lesson['is_completed']): ?>
                                <span class="status-badge status-published">Проведен</span>
                            <?php else: ?>
                                <span class="status-badge status-draft">Не проведен</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $lesson['status']; ?>">
                                <?php 
                                $statusNames = ['draft' => 'Черновик', 'published' => 'Опубликован', 'archived' => 'Архив'];
                                echo $statusNames[$lesson['status']] ?? $lesson['status'];
                                ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="view_lesson.php?id=<?php echo $lesson['id']; ?>" class="btn btn-sm btn-primary">Содержание</a>
                                <a href="?edit=<?php echo $lesson['id']; ?><?php echo $filterCourseId ? '&course_id=' . $filterCourseId : ''; ?><?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?><?php echo $sortByStatus !== 'all' ? '&sort_status=' . $sortByStatus : ''; ?>" class="btn btn-sm btn-secondary">Редактировать</a>
                                <?php if (!$lesson['is_completed'] && $lesson['scheduled_datetime']): ?>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_completed">
                                        <input type="hidden" name="id" value="<?php echo $lesson['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success">Отметить проведенным</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Вы уверены, что хотите удалить этот урок?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $lesson['id']; ?>">
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

