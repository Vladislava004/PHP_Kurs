<?php
require_once __DIR__ . '/../config.php';
requireAdmin();

$pageTitle = 'Календарь занятий';
require_once __DIR__ . '/../includes/header.php';

$pdo = getDBConnection();

$filterTeacherId = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : null;


$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

$teachers = $pdo->query("SELECT id, full_name FROM users WHERE role = 'teacher' ORDER BY full_name")->fetchAll();


if ($filterTeacherId) {
    $stmt = $pdo->prepare("SELECT l.*, c.title as course_title, c.teacher_id, u.full_name as teacher_name, cat.name as category_name
                           FROM lessons l 
                           INNER JOIN courses c ON l.course_id = c.id 
                           INNER JOIN users u ON c.teacher_id = u.id
                           LEFT JOIN categories cat ON c.category_id = cat.id
                           WHERE c.teacher_id = ?
                           AND l.scheduled_datetime IS NOT NULL
                           ORDER BY l.scheduled_datetime ASC");
    $stmt->execute([$filterTeacherId]);
} else {
    $stmt = $pdo->query("SELECT l.*, c.title as course_title, c.teacher_id, u.full_name as teacher_name, cat.name as category_name
                        FROM lessons l 
                        INNER JOIN courses c ON l.course_id = c.id 
                        INNER JOIN users u ON c.teacher_id = u.id
                        LEFT JOIN categories cat ON c.category_id = cat.id
                        WHERE l.scheduled_datetime IS NOT NULL
                        ORDER BY l.scheduled_datetime ASC");
}
$lessons = $stmt->fetchAll();


$lessonsByDate = [];
foreach ($lessons as $lesson) {
    $date = date('Y-m-d', strtotime($lesson['scheduled_datetime']));
    if (!isset($lessonsByDate[$date])) {
        $lessonsByDate[$date] = [];
    }
    $lessonsByDate[$date][] = $lesson;
}


$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDay);
$dayOfWeek = date('w', $firstDay);
$dayOfWeek = $dayOfWeek == 0 ? 7 : $dayOfWeek;

$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

$monthNames = [
    1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
    5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
    9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь'
];
?>

<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h2 class="card-title">Фильтр по преподавателю</h2>
    </div>
    <form method="GET" action="" style="padding: 1.5rem;">
        <input type="hidden" name="month" value="<?php echo $month; ?>">
        <input type="hidden" name="year" value="<?php echo $year; ?>">
        <div class="form-group">
            <label for="teacher_id">Преподаватель</label>
            <select id="teacher_id" name="teacher_id" onchange="this.form.submit()">
                <option value="">Все преподаватели</option>
                <?php foreach ($teachers as $teacher): ?>
                    <option value="<?php echo $teacher['id']; ?>" <?php echo ($filterTeacherId == $teacher['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($teacher['full_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Календарь занятий <?php echo $filterTeacherId ? '(преподаватель: ' . htmlspecialchars($lessons[0]['teacher_name'] ?? '') . ')' : '(все преподаватели)'; ?></h2>
        <div style="display: flex; gap: 1rem; align-items: center;">
            <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?><?php echo $filterTeacherId ? '&teacher_id=' . $filterTeacherId : ''; ?>" class="btn btn-sm btn-secondary">← Предыдущий месяц</a>
            <span style="font-weight: 600; font-size: 1.1rem;"><?php echo $monthNames[$month]; ?> <?php echo $year; ?></span>
            <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?><?php echo $filterTeacherId ? '&teacher_id=' . $filterTeacherId : ''; ?>" class="btn btn-sm btn-secondary">Следующий месяц →</a>
            <a href="?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?><?php echo $filterTeacherId ? '&teacher_id=' . $filterTeacherId : ''; ?>" class="btn btn-sm btn-primary">Текущий месяц</a>
        </div>
    </div>
    
    <div class="calendar-wrapper" style="padding: 1.5rem;">
        <table class="calendar-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="padding: 0.75rem; background: #f8f9fa; border: 1px solid #e0e0e0;">Пн</th>
                    <th style="padding: 0.75rem; background: #f8f9fa; border: 1px solid #e0e0e0;">Вт</th>
                    <th style="padding: 0.75rem; background: #f8f9fa; border: 1px solid #e0e0e0;">Ср</th>
                    <th style="padding: 0.75rem; background: #f8f9fa; border: 1px solid #e0e0e0;">Чт</th>
                    <th style="padding: 0.75rem; background: #f8f9fa; border: 1px solid #e0e0e0;">Пт</th>
                    <th style="padding: 0.75rem; background: #f8f9fa; border: 1px solid #e0e0e0;">Сб</th>
                    <th style="padding: 0.75rem; background: #f8f9fa; border: 1px solid #e0e0e0;">Вс</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $day = 1;
                $currentWeek = 0;
                $weeks = ceil(($daysInMonth + $dayOfWeek - 1) / 7);
                
                for ($week = 0; $week < $weeks; $week++):
                ?>
                    <tr>
                        <?php for ($dow = 1; $dow <= 7; $dow++): ?>
                            <td style="padding: 0.5rem; border: 1px solid #e0e0e0; vertical-align: top; min-height: 100px; height: 120px;">
                                <?php
                                if (($week == 0 && $dow < $dayOfWeek) || $day > $daysInMonth):
                                    
                                    echo '&nbsp;';
                                else:
                                    $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                                    $isToday = ($currentDate == date('Y-m-d'));
                                    $hasLessons = isset($lessonsByDate[$currentDate]);
                                    
                                    echo '<div style="font-weight: ' . ($isToday ? 'bold' : 'normal') . '; color: ' . ($isToday ? '#667eea' : '#333') . '; margin-bottom: 0.5rem;">';
                                    echo $day;
                                    echo '</div>';
                                    
                                    if ($hasLessons):
                                        foreach ($lessonsByDate[$currentDate] as $lesson):
                                            $time = date('H:i', strtotime($lesson['scheduled_datetime']));
                                            $duration = $lesson['duration_minutes'] ? ' (' . $lesson['duration_minutes'] . ' мин)' : '';
                                            $isCompleted = $lesson['is_completed'] ? 'Проведен: ' : '';
                                            
                                            echo '<div style="font-size: 0.75rem; padding: 0.25rem; margin-bottom: 0.25rem; background: ' . ($lesson['is_completed'] ? '#d4edda' : '#e3f2fd') . '; border-left: 3px solid ' . ($lesson['is_completed'] ? '#28a745' : '#2196f3') . '; border-radius: 3px;">';
                                            echo '<strong>' . $isCompleted . $time . $duration . '</strong><br>';
                                            echo '<span style="color: #666; font-size: 0.7rem;">' . htmlspecialchars($lesson['teacher_name']) . '</span><br>';
                                            echo '<a href="../teacher/view_lesson.php?id=' . $lesson['id'] . '" style="color: #333; text-decoration: none;">';
                                            echo htmlspecialchars(mb_substr($lesson['title'], 0, 25)) . (mb_strlen($lesson['title']) > 25 ? '...' : '');
                                            echo '</a>';
                                            echo '</div>';
                                        endforeach;
                                    endif;
                                    
                                    $day++;
                                endif;
                                ?>
                            </td>
                        <?php endfor; ?>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 5px;">
            <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 20px; height: 20px; background: #e3f2fd; border-left: 3px solid #2196f3;"></div>
                    <span>Запланированный урок</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 20px; height: 20px; background: #d4edda; border-left: 3px solid #28a745;"></div>
                    <span>Проведенный урок</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


