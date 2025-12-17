<?php
require_once __DIR__ . '/../config.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Получить все категории или одну по ID
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $category = $stmt->fetch();
            
            if ($category) {
                jsonSuccess('Категория найдена', $category);
            } else {
                jsonError('Категория не найдена', 404);
            }
        } else {
            $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
            $categories = $stmt->fetchAll();
            jsonSuccess('Список категорий', $categories);
        }
        break;
        
    case 'POST':
        // Создать новую категорию
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['name']) || empty(trim($data['name']))) {
            jsonError('Название категории обязательно');
        }
        
        $name = trim($data['name']);
        $description = $data['description'] ?? null;
        
        $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
        
        $categoryId = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        $category = $stmt->fetch();
        
        jsonSuccess('Категория создана', $category);
        break;
        
    case 'PUT':
        // Обновить категорию
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id'])) {
            jsonError('ID категории обязателен');
        }
        
        $id = (int)$data['id'];
        
        if (!isset($data['name']) || empty(trim($data['name']))) {
            jsonError('Название категории обязательно');
        }
        
        $name = trim($data['name']);
        $description = $data['description'] ?? null;
        
        if (strlen($name) > 255) {
            jsonError('Название категории не может быть длиннее 255 символов');
        }
        
        if ($description !== null && strlen($description) > 10000) {
            jsonError('Описание не может быть длиннее 10000 символов');
        }
        
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $description, $id]);
        
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $category = $stmt->fetch();
            jsonSuccess('Категория обновлена', $category);
        } else {
            jsonError('Категория не найдена', 404);
        }
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id'])) {
            jsonError('ID категории обязателен');
        }
        
        $id = (int)$data['id'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE category_id = ?");
        $stmt->execute([$id]);
        $coursesCount = $stmt->fetchColumn();
        
        if ($coursesCount > 0) {
            jsonError('Невозможно удалить категорию: в ней есть курсы', 400);
        }
        
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            jsonSuccess('Категория удалена');
        } else {
            jsonError('Категория не найдена', 404);
        }
        break;
        
    default:
        jsonError('Метод не поддерживается', 405);
        break;
}





