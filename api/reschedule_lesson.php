<?php
require_once __DIR__ . '/../config.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

if ($method !== 'POST') {
    jsonError('Метод не поддерживается', 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['lesson_id']) || !isset($data['new_datetime'])) {
    jsonError('ID урока и новое время обязательны');
}

$lessonId = (int)$data['lesson_id'];
$newDatetime = trim($data['new_datetime']);

$stmt = $pdo->prepare("SELECT l.*, c.teacher_id 
                       FROM lessons l 
                       INNER JOIN courses c ON l.course_id = c.id 
                       WHERE l.id = ?");
$stmt->execute([$lessonId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    jsonError('Урок не найден', 404);
}

if ($userRole === 'teacher') {
    if ($lesson['teacher_id'] != $userId) {
        jsonError('Нет доступа к этому уроку', 403);
    }
}

if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $newDatetime)) {
    jsonError('Неверный формат даты и времени');
}

require_once __DIR__ . '/../includes/check_time_conflicts.php';

$teacherId = $lesson['teacher_id'];
$durationMinutes = $lesson['duration_minutes'] ?? null;

if ($durationMinutes) {
    $conflicts = checkTimeConflicts($pdo, $teacherId, $newDatetime, $durationMinutes, $lessonId);
    
    if (!empty($conflicts)) {
        jsonError('Выбранное время конфликтует с другими уроками', 400);
    }
}

$stmt = $pdo->prepare("UPDATE lessons SET scheduled_datetime = ? WHERE id = ?");
$stmt->execute([$newDatetime, $lessonId]);

$stmt = $pdo->prepare("SELECT l.*, c.title as course_title 
                       FROM lessons l 
                       INNER JOIN courses c ON l.course_id = c.id 
                       WHERE l.id = ?");
$stmt->execute([$lessonId]);
$updatedLesson = $stmt->fetch();

jsonSuccess('Урок успешно перенесен', $updatedLesson);

