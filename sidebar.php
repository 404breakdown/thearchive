<?php
/**
 * Mobile-Friendly Sidebar Component for TheArchive
 * Usage: include 'sidebar.php';
 */

?>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay"></div>

<!-- Mobile Header -->
<div class="mobile-header">
    <button class="hamburger" aria-label="Toggle menu">
        <i class="bi bi-list"></i>
    </button>
    <h5 class="mb-0"><?php echo htmlspecialchars($site_name); ?></h5>
    <div style="width: 44px;"></div>
</div>

<!-- Sidebar -->
<div class="sidebar">
    <div class="p-4">
        <h4 class="mb-4"><i class="bi bi-archive-fill"></i> <?php echo htmlspecialchars($site_name); ?></h4>    
        <nav class="nav flex-column">
            <a class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" href="dashboard.php">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a class="nav-link <?php echo $currentPage === 'archive' ? 'active' : ''; ?>" href="archive.php">
                <i class="bi bi-folder2-open"></i> Archive
            </a>
            <a class="nav-link <?php echo $currentPage === 'settings' ? 'active' : ''; ?>" href="settings.php">
                <i class="bi bi-gear"></i> Settings
            </a>
        </nav>
    </div>
    
    <div class="sidebar-user">
        <div class="px-4 py-3">
            <div class="text-muted small mb-2">
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($username); ?>
            </div>
            <a href="logout.php" class="btn btn-outline-light btn-sm w-100">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
</div>
