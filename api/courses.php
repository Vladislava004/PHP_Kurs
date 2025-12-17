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
        // Получить все курсы или один по ID
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            
            if ($userRole === 'teacher') {
                $stmt = $pdo->prepare("SELECT c.*, cat.name as category_name, u.full_name as teacher_name 
                                       FROM courses c 
                                       LEFT JOIN categories cat ON c.category_id = cat.id 
                                       LEFT JOIN users u ON c.teacher_id = u.id 
                                       WHERE c.id = ? AND c.teacher_id = ?");
                $stmt->execute([$id, $userId]);
            } else {
                $stmt = $pdo->prepare("SELECT c.*, cat.name as category_name, u.full_name as teacher_name 
                                       FROM courses c 
                                       LEFT JOIN categories cat ON c.category_id = cat.id 
                                       LEFT JOIN users u ON c.teacher_id = u.id 
                                       WHERE c.id = ?");
                $stmt->execute([$id]);
            }
            
            $course = $stmt->fetch();
            
            if ($course) {
                jsonSuccess('Курс найден', $course);
            } else {
                jsonError('Курс не найден', 404);
            }
        } else {
            if ($userRole === 'teacher') {
                $stmt = $pdo->prepare("SELECT c.*, cat.name as category_name, u.full_name as teacher_name 
                                       FROM courses c 
                                       LEFT JOIN categories cat ON c.category_id = cat.id 
                                       LEFT JOIN users u ON c.teacher_id = u.id 
                                       WHERE c.teacher_id = ? 
                                       ORDER BY c.created_at DESC");
                $stmt->execute([$userId]);
            } else {
                $stmt = $pdo->query("SELECT c.*, cat.name as category_name, u.full_name as teacher_name 
                                    FROM courses c 
                                    LEFT JOIN categories cat ON c.category_id = cat.id 
                                    LEFT JOIN users u ON c.teacher_id = u.id 
                                    ORDER BY c.created_at DESC");
            }
            
            $courses = $stmt->fetchAll();
            jsonSuccess('Список курсов', $courses);
        }
        break;
        
    case 'POST':
        // Создать новый курс
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['title']) || empty(trim($data['title']))) {
            jsonError('Название курса обязательно');
        }
        
        if (!isset($data['category_id'])) {
            jsonError('Категория обязательна');
        }
        
        $title = trim($data['title']);
        $description = $data['description'] ?? null;
        $categoryId = (int)$data['category_id'];
        $status = $data['status'] ?? 'draft';
        
        if (strlen($title) > 255) {
            jsonError('Название курса не может быть длиннее 255 символов');
        }
        
        if ($description !== null && strlen($description) > 10000) {
            jsonError('Описание не может быть длиннее 10000 символов');
        }
        
        // Преподаватель может создавать курсы только для себя
        $teacherId = ($userRole === 'teacher') ? $userId : ($data['teacher_id'] ?? $userId);
        
        // Проверка существования категории
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        if (!$stmt->fetch()) {
            jsonError('Категория не найдена', 404);
        }
        
        $stmt = $pdo->prepare("INSERT INTO courses (title, description, category_id, teacher_id, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $categoryId, $teacherId, $status]);
        
        $courseId = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT c.*, cat.name as category_name, u.full_name as teacher_name 
                               FROM courses c 
                               LEFT JOIN categories cat ON c.category_id = cat.id 
                               LEFT JOIN users u ON c.teacher_id = u.id 
                               WHERE c.id = ?");
        $stmt->execute([$courseId]);
        $course = $stmt->fetch();
        
        jsonSuccess('Курс создан', $course);
        break;
        
    case 'PUT':
        // Обновить курс
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id'])) {
            jsonError('ID курса обязателен');
        }
        
        $id = (int)$data['id'];
        
        // Проверка прав доступа
        $stmt = $pdo->prepare("SELECT teacher_id FROM courses WHERE id = ?");
        $stmt->execute([$id]);
        $course = $stmt->fetch();
        
        if (!$course) {
            jsonError('Курс не найден', 404);
        }
        
        if ($userRole === 'teacher' && $course['teacher_id'] != $userId) {
            jsonError('Нет доступа к этому курсу', 403);
        }
        
        if (!isset($data['title']) || empty(trim($data['title']))) {
            jsonError('Название курса обязательно');
        }
        
        $title = trim($data['title']);
        $description = $data['description'] ?? null;
        $categoryId = isset($data['category_id']) ? (int)$data['category_id'] : null;
        $status = $data['status'] ?? null;
        
        if (strlen($title) > 255) {
            jsonError('Название курса не может быть длиннее 255 символов');
        }
        
        if ($description !== null && strlen($description) > 10000) {
            jsonError('Описание не может быть длиннее 10000 символов');
        }
        
        $updateFields = ['title = ?'];
        $params = [$title];
        
        if ($description !== null) {
            $updateFields[] = 'description = ?';
            $params[] = $description;
        }
        
        if ($categoryId !== null) {
            $updateFields[] = 'category_id = ?';
            $params[] = $categoryId;
        }
        
        if ($status !== null) {
            $updateFields[] = 'status = ?';
            $params[] = $status;
        }
        
        if ($userRole === 'admin' && isset($data['teacher_id'])) {
            $updateFields[] = 'teacher_id = ?';
            $params[] = (int)$data['teacher_id'];
        }
        
        $params[] = $id;
        $sql = "UPDATE courses SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $stmt = $pdo->prepare("SELECT c.*, cat.name as category_name, u.full_name as teacher_name 
                               FROM courses c 
                               LEFT JOIN categories cat ON c.category_id = cat.id 
                               LEFT JOIN users u ON c.teacher_id = u.id 
                               WHERE c.id = ?");
        $stmt->execute([$id]);
        $updatedCourse = $stmt->fetch();
        
        jsonSuccess('Курс обновлен', $updatedCourse);
        break;
        
    case 'DELETE':
        // Удалить курс
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id'])) {
            jsonError('ID курса обязателен');
        }
        
        $id = (int)$data['id'];
        
        // Проверка прав доступа
        $stmt = $pdo->prepare("SELECT teacher_id FROM courses WHERE id = ?");
        $stmt->execute([$id]);
        $course = $stmt->fetch();
        
        if (!$course) {
            jsonError('Курс не найден', 404);
        }
        
        if ($userRole === 'teacher' && $course['teacher_id'] != $userId) {
            jsonError('Нет доступа к этому курсу', 403);
        }
        
        $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->execute([$id]);
        
        jsonSuccess('Курс удален');
        break;
        
    default:
        jsonError('Метод не поддерживается', 405);
        break;
}





