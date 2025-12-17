<?php

function checkTimeConflicts($pdo, $teacherId, $scheduledDatetime, $durationMinutes, $excludeLessonId = null) {
    if (!$scheduledDatetime || !$durationMinutes) {
        return [];
    }
    
    $startTime = strtotime($scheduledDatetime);
    $endTime = $startTime + ($durationMinutes * 60);
    
    $sql = "SELECT l.*, c.title as course_title 
            FROM lessons l 
            INNER JOIN courses c ON l.course_id = c.id 
            WHERE c.teacher_id = ? 
            AND l.scheduled_datetime IS NOT NULL 
            AND l.duration_minutes IS NOT NULL
            AND l.id != COALESCE(?, 0)";
    
    $params = [$teacherId];
    if ($excludeLessonId) {
        $sql = str_replace('COALESCE(?, 0)', '?', $sql);
        $params[] = $excludeLessonId;
    } else {
        $sql = str_replace('AND l.id != COALESCE(?, 0)', '', $sql);
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $existingLessons = $stmt->fetchAll();
    
    $conflicts = [];
    
    foreach ($existingLessons as $lesson) {
        if (!$lesson['scheduled_datetime'] || !$lesson['duration_minutes']) {
            continue;
        }
        
        $lessonStart = strtotime($lesson['scheduled_datetime']);
        $lessonEnd = $lessonStart + ($lesson['duration_minutes'] * 60);
        
        if (($startTime < $lessonEnd && $endTime > $lessonStart)) {
            $conflicts[] = [
                'lesson_id' => $lesson['id'],
                'title' => $lesson['title'],
                'course_title' => $lesson['course_title'],
                'scheduled_datetime' => $lesson['scheduled_datetime'],
                'duration_minutes' => $lesson['duration_minutes'],
                'start_time' => date('d.m.Y H:i', $lessonStart),
                'end_time' => date('H:i', $lessonEnd)
            ];
        }
    }
    
    return $conflicts;
}

function findAvailableTimeSlots($pdo, $teacherId, $originalDatetime, $durationMinutes, $lessonId, $daysToCheck = 7) {
    if (!$originalDatetime || !$durationMinutes) {
        return [];
    }
    
    $originalDate = date('Y-m-d', strtotime($originalDatetime));
    $originalTime = strtotime($originalDatetime);
    $availableSlots = [];
    
    $startDate = date('Y-m-d', strtotime($originalDate . ' -1 day'));
    $endDate = date('Y-m-d', strtotime($originalDate . " +{$daysToCheck} days"));
    
    $sql = "SELECT l.* 
            FROM lessons l 
            INNER JOIN courses c ON l.course_id = c.id 
            WHERE c.teacher_id = ? 
            AND l.scheduled_datetime IS NOT NULL 
            AND l.duration_minutes IS NOT NULL
            AND l.id != ?
            AND DATE(l.scheduled_datetime) >= ?
            AND DATE(l.scheduled_datetime) <= ?
            ORDER BY l.scheduled_datetime ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$teacherId, $lessonId, $startDate, $endDate]);
    $existingLessons = $stmt->fetchAll();
    
    $lessonsByDate = [];
    foreach ($existingLessons as $lesson) {
        $date = date('Y-m-d', strtotime($lesson['scheduled_datetime']));
        if (!isset($lessonsByDate[$date])) {
            $lessonsByDate[$date] = [];
        }
        $lessonsByDate[$date][] = $lesson;
    }

    for ($dayOffset = -1; $dayOffset <= $daysToCheck; $dayOffset++) {
        $checkDate = date('Y-m-d', strtotime($originalDate . " {$dayOffset} days"));
        $checkDateTimestamp = strtotime($checkDate);
        
        $dayLessons = $lessonsByDate[$checkDate] ?? [];
        
        $suggestedTimes = [];
        
        $earlyMorning = strtotime($checkDate . ' 08:00:00');
        if ($earlyMorning >= time()) {
            $conflicts = checkTimeConflicts($pdo, $teacherId, date('Y-m-d H:i:s', $earlyMorning), $durationMinutes, $lessonId);
            if (empty($conflicts)) {
                $suggestedTimes[] = $earlyMorning;
            }
        }
        
        $lunchTime = strtotime($checkDate . ' 13:00:00');
        if ($lunchTime >= time()) {
            $conflicts = checkTimeConflicts($pdo, $teacherId, date('Y-m-d H:i:s', $lunchTime), $durationMinutes, $lessonId);
            if (empty($conflicts)) {
                $suggestedTimes[] = $lunchTime;
            }
        }

        $eveningTime = strtotime($checkDate . ' 18:00:00');
        if ($eveningTime >= time()) {
            $conflicts = checkTimeConflicts($pdo, $teacherId, date('Y-m-d H:i:s', $eveningTime), $durationMinutes, $lessonId);
            if (empty($conflicts)) {
                $suggestedTimes[] = $eveningTime;
            }
        }
        
        if (!empty($dayLessons)) {
            usort($dayLessons, function($a, $b) {
                return strtotime($a['scheduled_datetime']) - strtotime($b['scheduled_datetime']);
            });
            
            for ($i = 0; $i < count($dayLessons) - 1; $i++) {
                $currentLessonEnd = strtotime($dayLessons[$i]['scheduled_datetime']) + ($dayLessons[$i]['duration_minutes'] * 60);
                $nextLessonStart = strtotime($dayLessons[$i + 1]['scheduled_datetime']);
                
                $gap = $nextLessonStart - $currentLessonEnd;
                $requiredTime = ($durationMinutes + 30) * 60;
                
                if ($gap >= $requiredTime) {
                    $suggestedSlot = $currentLessonEnd + (30 * 60);
                    if ($suggestedSlot >= time()) {
                        $conflicts = checkTimeConflicts($pdo, $teacherId, date('Y-m-d H:i:s', $suggestedSlot), $durationMinutes, $lessonId);
                        if (empty($conflicts)) {
                            $suggestedTimes[] = $suggestedSlot;
                        }
                    }
                }
            }
            $lastLesson = end($dayLessons);
            $lastLessonEnd = strtotime($lastLesson['scheduled_datetime']) + ($lastLesson['duration_minutes'] * 60);
            $suggestedAfterLast = $lastLessonEnd + (30 * 60);

            $maxTime = strtotime($checkDate . ' 21:00:00');
            if ($suggestedAfterLast <= $maxTime && $suggestedAfterLast >= time()) {
                $conflicts = checkTimeConflicts($pdo, $teacherId, date('Y-m-d H:i:s', $suggestedAfterLast), $durationMinutes, $lessonId);
                if (empty($conflicts)) {
                    $suggestedTimes[] = $suggestedAfterLast;
                }
            }
        } else {
        }
        
        foreach ($suggestedTimes as $slotTime) {
            $availableSlots[] = [
                'datetime' => date('Y-m-d H:i:s', $slotTime),
                'date' => date('d.m.Y', $slotTime),
                'time' => date('H:i', $slotTime),
                'display' => date('d.m.Y в H:i', $slotTime)
            ];
        }
    }
    
    usort($availableSlots, function($a, $b) {
        return strcmp($a['datetime'], $b['datetime']);
    });
    
    return array_slice($availableSlots, 0, 10);
}

