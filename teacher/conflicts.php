<?php
require_once __DIR__ . '/../config.php';
requireTeacher();

$pageTitle = 'Конфликты времени';
require_once __DIR__ . '/../includes/header.php';

$pdo = getDBConnection();
$teacherId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT l.*, c.title as course_title 
                       FROM lessons l 
                       INNER JOIN courses c ON l.course_id = c.id 
                       WHERE c.teacher_id = ? 
                       AND l.scheduled_datetime IS NOT NULL 
                       AND l.duration_minutes IS NOT NULL
                       ORDER BY l.scheduled_datetime ASC");
$stmt->execute([$teacherId]);
$lessons = $stmt->fetchAll();

require_once __DIR__ . '/../includes/check_time_conflicts.php';

$allConflicts = [];
$checkedLessons = [];

foreach ($lessons as $lesson) {
    if (in_array($lesson['id'], $checkedLessons)) {
        continue;
    }
    
    $conflicts = checkTimeConflicts($pdo, $teacherId, $lesson['scheduled_datetime'], $lesson['duration_minutes'], $lesson['id']);
    
    if (!empty($conflicts)) {
        $availableSlots = findAvailableTimeSlots($pdo, $teacherId, $lesson['scheduled_datetime'], $lesson['duration_minutes'], $lesson['id']);
        
        $allConflicts[] = [
            'lesson' => $lesson,
            'conflicts' => $conflicts,
            'available_slots' => $availableSlots
        ];
        
        foreach ($conflicts as $conflict) {
            if (!in_array($conflict['lesson_id'], $checkedLessons)) {
                $checkedLessons[] = $conflict['lesson_id'];
            }
        }
    }
    
    $checkedLessons[] = $lesson['id'];
}
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Конфликты времени</h2>
        <a href="lessons.php" class="btn btn-secondary">Назад к урокам</a>
    </div>
    
    <div style="padding: 1.5rem;">
        <?php if (empty($allConflicts)): ?>
            <div class="empty-state">
                <h3>Конфликтов времени не обнаружено</h3>
                <p>Все ваши уроки не пересекаются по времени</p>
            </div>
        <?php else: ?>
            <?php foreach ($allConflicts as $conflictGroup): ?>
                <div style="margin-bottom: 2rem; padding: 1.5rem; background: #fff3cd; border: 2px solid #ffc107; border-radius: 5px;">
                    <h3 style="color: #856404; margin-bottom: 1rem;">
                        Конфликт: <?php echo htmlspecialchars($conflictGroup['lesson']['title']); ?>
                    </h3>
                    
                    <div style="margin-bottom: 1rem;">
                        <strong>Урок:</strong> <?php echo htmlspecialchars($conflictGroup['lesson']['title']); ?><br>
                        <strong>Курс:</strong> <?php echo htmlspecialchars($conflictGroup['lesson']['course_title']); ?><br>
                        <strong>Время:</strong> <?php echo date('d.m.Y H:i', strtotime($conflictGroup['lesson']['scheduled_datetime'])); ?><br>
                        <strong>Длительность:</strong> <?php echo $conflictGroup['lesson']['duration_minutes']; ?> минут<br>
                        <strong>Окончание:</strong> <?php echo date('H:i', strtotime($conflictGroup['lesson']['scheduled_datetime']) + ($conflictGroup['lesson']['duration_minutes'] * 60)); ?>
                    </div>
                    
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #ffc107;">
                        <strong>Пересекается с:</strong>
                        <ul style="margin-top: 0.5rem; margin-left: 1.5rem;">
                            <?php foreach ($conflictGroup['conflicts'] as $conflict): ?>
                                <li style="margin-bottom: 0.5rem;">
                                    <a href="view_lesson.php?id=<?php echo $conflict['lesson_id']; ?>" style="color: #856404; text-decoration: underline;">
                                        <?php echo htmlspecialchars($conflict['title']); ?>
                                    </a>
                                    (<?php echo $conflict['start_time']; ?> - <?php echo $conflict['end_time']; ?>, 
                                    <?php echo $conflict['duration_minutes']; ?> мин.)<br>
                                    <small>Курс: <?php echo htmlspecialchars($conflict['course_title']); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #ffc107;">
                        <?php if (!empty($conflictGroup['available_slots'])): ?>
                            <strong style="display: block; margin-bottom: 0.75rem; color: #856404;">
                                Предложенные времена для переноса:
                            </strong>
                            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem;">
                                <?php foreach ($conflictGroup['available_slots'] as $slot): ?>
                                    <button 
                                        class="btn btn-sm btn-success reschedule-btn" 
                                        data-lesson-id="<?php echo $conflictGroup['lesson']['id']; ?>"
                                        data-new-datetime="<?php echo htmlspecialchars($slot['datetime']); ?>"
                                        style="margin: 0; white-space: nowrap;"
                                    >
                                        <?php echo htmlspecialchars($slot['display']); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <div id="reschedule-message-<?php echo $conflictGroup['lesson']['id']; ?>" style="margin-top: 0.5rem;"></div>
                        <?php else: ?>
                            <div style="color: #856404; font-style: italic; margin-bottom: 1rem;">
                                К сожалению, автоматические предложения времени не найдены. Попробуйте отредактировать урок вручную.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="margin-top: 1rem;">
                        <a href="lessons.php?edit=<?php echo $conflictGroup['lesson']['id']; ?>" class="btn btn-sm btn-secondary">Редактировать урок вручную</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const rescheduleButtons = document.querySelectorAll('.reschedule-btn');
    
    rescheduleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const lessonId = this.getAttribute('data-lesson-id');
            const newDatetime = this.getAttribute('data-new-datetime');
            const messageDiv = document.getElementById('reschedule-message-' + lessonId);
            
            this.disabled = true;
            this.textContent = 'Перенос...';
            messageDiv.innerHTML = '<span style="color: #856404;">Перенос урока...</span>';

            fetch('../api/reschedule_lesson.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    lesson_id: lessonId,
                    new_datetime: newDatetime
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const dateObj = new Date(newDatetime.replace(' ', 'T'));
                    const formattedDate = dateObj.toLocaleString('ru-RU', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    messageDiv.innerHTML = '<span style="color: #28a745; font-weight: bold;">Урок успешно перенесен на ' + formattedDate + '</span>';
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    messageDiv.innerHTML = '<span style="color: #dc3545;">✗ Ошибка: ' + (data.message || 'Не удалось перенести урок') + '</span>';
                    this.disabled = false;
                    this.textContent = this.getAttribute('data-display') || 'Перенести';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                messageDiv.innerHTML = '<span style="color: #dc3545;">✗ Ошибка при переносе урока</span>';
                this.disabled = false;
                this.textContent = this.getAttribute('data-display') || 'Перенести';
            });
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

