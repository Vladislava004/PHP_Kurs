<?php


require_once __DIR__ . '/../config.php';
requireTeacher();

header('Content-Type: application/json; charset=utf-8');

$pdo = getDBConnection();
$teacherId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Метод не поддерживается', 405);
}

$scheduledDatetime = $_POST['scheduled_datetime'] ?? null;
$durationMinutes = isset($_POST['duration_minutes']) ? (int)$_POST['duration_minutes'] : null;
$excludeLessonId = isset($_POST['exclude_lesson_id']) ? (int)$_POST['exclude_lesson_id'] : null;

if (!$scheduledDatetime || !$durationMinutes) {
    jsonResponse(['success' => true, 'conflicts' => []]);
}

require_once __DIR__ . '/../includes/check_time_conflicts.php';
$conflicts = checkTimeConflicts($pdo, $teacherId, $scheduledDatetime, $durationMinutes, $excludeLessonId);

jsonResponse([
    'success' => true,
    'conflicts' => $conflicts,
    'has_conflicts' => !empty($conflicts)
]);


