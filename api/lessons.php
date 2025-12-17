<?php
require_once __DIR__ . '/../config.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            
            if ($userRole === 'teacher') {
                $stmt = $pdo->prepare("SELECT l.*, c.title as course_title 
                                       FROM lessons l 
                                       INNER JOIN courses c ON l.course_id = c.id 
                                       WHERE l.id = ? AND c.teacher_id = ?");
                $stmt->execute([$id, $userId]);
            } else {
                $stmt = $pdo->prepare("SELECT l.*, c.title as course_title 
                                       FROM lessons l 
                                       INNER JOIN courses c ON l.course_id = c.id 
                                       WHERE l.id = ?");
                $stmt->execute([$id]);
            }
            
            $lesson = $stmt->fetch();
            
            if ($lesson) {
                $stmt = $pdo->prepare("SELECT * FROM materials WHERE lesson_id = ?");
                $stmt->execute([$id]);
                $lesson['materials'] = $stmt->fetchAll();
                
                jsonSuccess('Урок найден', $lesson);
            } else {
                jsonError('Урок не найден', 404);
            }
        } elseif (isset($_GET['course_id'])) {
            $courseId = (int)$_GET['course_id'];
            
            if ($userRole === 'teacher') {
                $stmt = $pdo->prepare("SELECT l.*, c.title as course_title 
                                       FROM lessons l 
                                       INNER JOIN courses c ON l.course_id = c.id 
                                       WHERE l.course_id = ? AND c.teacher_id = ? 
                                       ORDER BY l.created_at DESC");
                $stmt->execute([$courseId, $userId]);
            } else {
                $stmt = $pdo->prepare("SELECT l.*, c.title as course_title 
                                       FROM lessons l 
                                       INNER JOIN courses c ON l.course_id = c.id 
                                       WHERE l.course_id = ? 
                                       ORDER BY l.created_at DESC");
                $stmt->execute([$courseId]);
            }
            
            $lessons = $stmt->fetchAll();
            jsonSuccess('Список уроков', $lessons);
        } else {
            if ($userRole === 'teacher') {
                $stmt = $pdo->prepare("SELECT l.*, c.title as course_title 
                                       FROM lessons l 
                                       INNER JOIN courses c ON l.course_id = c.id 
                                       WHERE c.teacher_id = ? 
                                       ORDER BY l.created_at DESC");
                $stmt->execute([$userId]);
            } else {
                $stmt = $pdo->query("SELECT l.*, c.title as course_title 
                                    FROM lessons l 
                                    INNER JOIN courses c ON l.course_id = c.id 
                                    ORDER BY l.created_at DESC");
            }
            
            $lessons = $stmt->fetchAll();
            jsonSuccess('Список уроков', $lessons);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['title']) || empty(trim($data['title']))) {
            jsonError('Название урока обязательно');
        }
        
        if (!isset($data['content']) || empty(trim($data['content']))) {
            jsonError('Содержание урока обязательно');
        }
        
        if (!isset($data['course_id'])) {
            jsonError('ID курса обязателен');
        }
        
        $title = trim($data['title']);
        $content = trim($data['content']);
        $courseId = (int)$data['course_id'];
        
        if (strlen($title) > 255) {
            jsonError('Название урока не может быть длиннее 255 символов');
        }
        
        if (strlen($content) > 50000) {
            jsonError('Содержание урока не может быть длиннее 50000 символов');
        }
        
        $durationMinutes = !empty($data['duration_minutes']) ? (int)$data['duration_minutes'] : null;
        if ($durationMinutes !== null && ($durationMinutes < 1 || $durationMinutes > 1440)) {
            jsonError('Длительность урока должна быть от 1 до 1440 минут (24 часа)');
        }
        
        if ($userRole === 'teacher') {
            $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$courseId, $userId]);
            if (!$stmt->fetch()) {
                jsonError('Курс не найден или нет доступа', 404);
            }
        } else {
            $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ?");
            $stmt->execute([$courseId]);
            if (!$stmt->fetch()) {
                jsonError('Курс не найден', 404);
            }
        }
        
        $lessonType = $data['lesson_type'] ?? 'lecture';
        $status = $data['status'] ?? 'draft';
        $scheduledDatetime = !empty($data['scheduled_datetime']) ? $data['scheduled_datetime'] : null;
        $isCompleted = isset($data['is_completed']) ? (int)$data['is_completed'] : 0;
        
        $stmt = $pdo->prepare("INSERT INTO lessons (course_id, title, content, lesson_type, status, scheduled_datetime, duration_minutes, is_completed) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$courseId, $title, $content, $lessonType, $status, $scheduledDatetime, $durationMinutes, $isCompleted]);
        
        $lessonId = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("SELECT l.*, c.title as course_title 
                               FROM lessons l 
                               INNER JOIN courses c ON l.course_id = c.id 
                               WHERE l.id = ?");
        $stmt->execute([$lessonId]);
        $lesson = $stmt->fetch();
        
        jsonSuccess('Урок создан', $lesson);
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id'])) {
            jsonError('ID урока обязателен');
        }
        
        $id = (int)$data['id'];

        if ($userRole === 'teacher') {
            $stmt = $pdo->prepare("SELECT c.teacher_id FROM lessons l 
                                   INNER JOIN courses c ON l.course_id = c.id 
                                   WHERE l.id = ?");
            $stmt->execute([$id]);
            $lesson = $stmt->fetch();
            
            if (!$lesson) {
                jsonError('Урок не найден', 404);
            }
            
            if ($lesson['teacher_id'] != $userId) {
                jsonError('Нет доступа к этому уроку', 403);
            }
        } else {
            $stmt = $pdo->prepare("SELECT id FROM lessons WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                jsonError('Урок не найден', 404);
            }
        }
        
        $updateFields = [];
        $params = [];
        
        if (isset($data['title'])) {
            $title = trim($data['title']);
            if (strlen($title) > 255) {
                jsonError('Название урока не может быть длиннее 255 символов');
            }
            $updateFields[] = 'title = ?';
            $params[] = $title;
        }
        
        if (isset($data['content'])) {
            $content = trim($data['content']);
            if (strlen($content) > 50000) {
                jsonError('Содержание урока не может быть длиннее 50000 символов');
            }
            $updateFields[] = 'content = ?';
            $params[] = $content;
        }
        
        if (isset($data['lesson_type'])) {
            $updateFields[] = 'lesson_type = ?';
            $params[] = $data['lesson_type'];
        }
        
        
        if (isset($data['status'])) {
            $updateFields[] = 'status = ?';
            $params[] = $data['status'];
        }
        
        if (isset($data['scheduled_datetime'])) {
            $updateFields[] = 'scheduled_datetime = ?';
            $params[] = !empty($data['scheduled_datetime']) ? $data['scheduled_datetime'] : null;
        }
        
        if (isset($data['is_completed'])) {
            $updateFields[] = 'is_completed = ?';
            $params[] = (int)$data['is_completed'];
        }
        
        if (isset($data['duration_minutes'])) {
            $durationMinutes = !empty($data['duration_minutes']) ? (int)$data['duration_minutes'] : null;
            if ($durationMinutes !== null && ($durationMinutes < 1 || $durationMinutes > 1440)) {
                jsonError('Длительность урока должна быть от 1 до 1440 минут (24 часа)');
            }
            $updateFields[] = 'duration_minutes = ?';
            $params[] = $durationMinutes;
        }
        
        if (empty($updateFields)) {
            jsonError('Нет данных для обновления');
        }
        
        $params[] = $id;
        $sql = "UPDATE lessons SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $stmt = $pdo->prepare("SELECT l.*, c.title as course_title 
                               FROM lessons l 
                               INNER JOIN courses c ON l.course_id = c.id 
                               WHERE l.id = ?");
        $stmt->execute([$id]);
        $updatedLesson = $stmt->fetch();
        
        jsonSuccess('Урок обновлен', $updatedLesson);
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id'])) {
            jsonError('ID урока обязателен');
        }
        
        $id = (int)$data['id'];
        
        if ($userRole === 'teacher') {
            $stmt = $pdo->prepare("SELECT c.teacher_id FROM lessons l 
                                   INNER JOIN courses c ON l.course_id = c.id 
                                   WHERE l.id = ?");
            $stmt->execute([$id]);
            $lesson = $stmt->fetch();
            
            if (!$lesson) {
                jsonError('Урок не найден', 404);
            }
            
            if ($lesson['teacher_id'] != $userId) {
                jsonError('Нет доступа к этому уроку', 403);
            }
        } else {
            $stmt = $pdo->prepare("SELECT id FROM lessons WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                jsonError('Урок не найден', 404);
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM lessons WHERE id = ?");
        $stmt->execute([$id]);
        
        jsonSuccess('Урок удален');
        break;
        
    default:
        jsonError('Метод не поддерживается', 405);
        break;
}

