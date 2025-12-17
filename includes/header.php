<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Панель управления';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Образовательная платформа</title>
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/teacher/') !== false) ? '../styles.css' : 'styles.css'; ?>">
    <style>
    /* Стили для input[type="time"] - принудительно 24-часовой формат */
    input[type="time"] {
        font-family: inherit;
    }
    input[type="time"]::-webkit-calendar-picker-indicator {
        cursor: pointer;
    }
    </style>
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <?php 
    $basePath = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/teacher/') !== false) ? '../' : '';
    $currentPage = basename($_SERVER['PHP_SELF']);
    ?>
    <div class="app-layout">
        <button class="mobile-menu-toggle" onclick="toggleSidebar()" aria-label="Открыть меню">☰</button>
        
        <aside class="sidebar" id="sidebar">
            <button class="sidebar-close" onclick="toggleSidebar()" aria-label="Закрыть меню">×</button>
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-text">
                        <h2>
                            <img src="<?php echo $basePath; ?>images/logo.png" alt="" class="title-icon"><br>
                            Образовательная платформа
                        </h2>
                        <span class="logo-subtitle">Система управления</span>
                    </div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="<?php echo $basePath; ?>dashboard.php" class="nav-item <?php echo ($currentPage == 'dashboard.php') ? 'active' : ''; ?>">
                    <img src="<?php echo $basePath; ?>images/dashboard.png" alt="" class="nav-icon">
                    <span class="nav-text">Панель управления</span>
                </a>
                
                <?php if (isAdmin()): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">Управление</div>
                        <a href="<?php echo $basePath; ?>admin/categories.php" class="nav-item <?php echo ($currentPage == 'categories.php') ? 'active' : ''; ?>">
                            <span class="nav-text">Категории</span>
                        </a>
                        <a href="<?php echo $basePath; ?>admin/courses.php" class="nav-item <?php echo ($currentPage == 'courses.php') ? 'active' : ''; ?>">
                            <img src="<?php echo $basePath; ?>images/courses.png" alt="" class="nav-icon">
                            <span class="nav-text">Курсы</span>
                        </a>
                        <a href="<?php echo $basePath; ?>admin/users.php" class="nav-item <?php echo ($currentPage == 'users.php') ? 'active' : ''; ?>">
                            <img src="<?php echo $basePath; ?>images/users.png" alt="" class="nav-icon">
                            <span class="nav-text">Пользователи</span>
                        </a>
                        <a href="<?php echo $basePath; ?>admin/calendar.php" class="nav-item <?php echo ($currentPage == 'calendar.php') ? 'active' : ''; ?>">
                            <img src="<?php echo $basePath; ?>images/calendar.png" alt="" class="nav-icon">
                            <span class="nav-text">Календарь</span>
                        </a>
                    </div>
                <?php endif; ?>
                
                <?php if (isTeacher()): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">Преподавание</div>
                        <a href="<?php echo $basePath; ?>teacher/courses.php" class="nav-item <?php echo ($currentPage == 'courses.php') ? 'active' : ''; ?>">
                            <img src="<?php echo $basePath; ?>images/courses.png" alt="" class="nav-icon">
                            <span class="nav-text">Мои курсы</span>
                        </a>
                        <a href="<?php echo $basePath; ?>teacher/lessons.php" class="nav-item <?php echo ($currentPage == 'lessons.php') ? 'active' : ''; ?>">
                            <img src="<?php echo $basePath; ?>images/lessons.png" alt="" class="nav-icon">
                            <span class="nav-text">Мои уроки</span>
                        </a>
                        <a href="<?php echo $basePath; ?>teacher/calendar.php" class="nav-item <?php echo ($currentPage == 'calendar.php') ? 'active' : ''; ?>">
                            <img src="<?php echo $basePath; ?>images/calendar.png" alt="" class="nav-icon">
                            <span class="nav-text">Календарь</span>
                        </a>
                        <a href="<?php echo $basePath; ?>teacher/conflicts.php" class="nav-item <?php echo ($currentPage == 'conflicts.php') ? 'active' : ''; ?>">
                            <img src="<?php echo $basePath; ?>images/conflicts.png" alt="" class="nav-icon">
                            <span class="nav-text">Конфликты</span>
                        </a>
                    </div>
                <?php endif; ?>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar"><?php echo strtoupper(mb_substr($_SESSION['user_full_name'], 0, 1)); ?></div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_full_name']); ?></div>
                        <div class="user-role"><?php echo htmlspecialchars($_SESSION['user_role'] === 'admin' ? 'Администратор' : 'Преподаватель'); ?></div>
                    </div>
                </div>
                <a href="<?php echo $basePath; ?>index.php?logout=1" class="logout-btn">
                    <span>Выход</span>
                </a>
            </div>
        </aside>
        
        <div class="main-wrapper">
            <header class="top-header">
                <div class="page-title-section">
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                </div>
            </header>
            <main class="main-content">
    <?php else: ?>
    <main class="main-content">
    <?php endif; ?>

