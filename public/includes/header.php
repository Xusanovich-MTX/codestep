<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Dasturlash o'rganing</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Highlight.js for code highlighting -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/atom-one-dark.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/<?php echo CURRENT_THEME; ?>-theme.css" id="theme-style">
</head>
<body class="<?php echo CURRENT_THEME; ?>-mode">
    
    <!-- Loading animation -->
    <?php if (!isset($_COOKIE['visited'])): ?>
    <div class="splash-screen" id="splash">
        <div class="splash-content">
            <img src="<?php echo ASSETS_URL; ?>/images/unnamed.jpg" alt="CodeStep" class="splash-logo">
            <h1 class="splash-title">CodeStep</h1>
            <div class="rainbow-border"></div>
        </div>
    </div>
    <?php 
        setcookie('visited', 'true', time() + 3600, "/");
    endif; 
    ?>
    
    <!-- Navigation Drawer -->
    <div class="drawer-overlay" id="drawerOverlay"></div>
    <nav class="navigation-drawer" id="navigationDrawer">
        <div class="drawer-header">
            <img src="<?php echo ASSETS_URL; ?>/images/unnamed.jpg" alt="Profile" class="profile-image">
            <h3>CodeStep</h3>
            <p>Dasturlashni o'rganing</p>
        </div>
        
        <div class="drawer-menu">
            <a href="?page=topics" class="menu-item" data-nav-item="topics" draggable="true">
                <span class="drag-handle">☰</span>
                <i class="fas fa-book"></i>
                <span>Mavzular</span>
            </a>
            
            <a href="?page=code-editor" class="menu-item" data-nav-item="editor" draggable="true">
                <span class="drag-handle">☰</span>
                <i class="fas fa-code"></i>
                <span>Kod muharriri</span>
            </a>
            
            <a href="?page=contact" class="menu-item" data-nav-item="contact" draggable="true">
                <span class="drag-handle">☰</span>
                <i class="fas fa-envelope"></i>
                <span>Aloqa</span>
            </a>
            
            <a href="?page=settings" class="menu-item" data-nav-item="settings" draggable="true">
                <span class="drag-handle">☰</span>
                <i class="fas fa-cog"></i>
                <span>Sozlamalar</span>
            </a>
            
            <div class="menu-item theme-switch-container" data-nav-item="theme" draggable="true">
                <span class="drag-handle">☰</span>
                <i class="fas fa-moon"></i>
                <span>Tun/Kun rejimi</span>
                <label class="switch">
                    <input type="checkbox" id="themeToggle" <?php echo CURRENT_THEME == 'light' ? 'checked' : ''; ?>>
                    <span class="slider round"></span>
                </label>
            </div>
            
        </div>
    </nav>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Toolbar -->
        <header class="toolbar">
            <button class="menu-button" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="logo-container">
                <img src="<?php echo ASSETS_URL; ?>/images/unnamed.jpg" alt="CodeStep" class="toolbar-logo">
                <span class="toolbar-title">CodeStep</span>
            </div>
            
            <div class="toolbar-actions">
            </div>
        </header>
        
        <!-- Page Content -->
        <div class="container">