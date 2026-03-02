<!-- Mobile-Optimized Styles and Navigation -->
<style>
    body {
        background-color: #1a1a1a;
        color: #e0e0e0;
    }
    
    /* Sidebar - Desktop */
    .sidebar {
        min-height: 100vh;
        background: linear-gradient(180deg, #2d3748 0%, #1a202c 100%);
        color: white;
        position: fixed;
        left: 0;
        top: 0;
        width: 250px;
        z-index: 1000;
        transition: transform 0.3s ease;
    }
    
    /* Mobile Sidebar - Hidden by default */
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }
        
        .sidebar.show {
            transform: translateX(0);
        }
        
        .content-wrapper {
            margin-left: 0 !important;
        }
        
        /* Overlay when sidebar is open */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        
        .sidebar-overlay.show {
            display: block;
        }
    }
    
    /* Desktop - Push content */
    @media (min-width: 769px) {
        .content-wrapper {
            margin-left: 250px;
        }
    }
    
    .sidebar .nav-link {
        color: rgba(255,255,255,0.8);
        padding: 15px 20px;
        transition: all 0.3s;
        font-size: 16px;
        min-height: 48px; /* Touch-friendly */
        display: flex;
        align-items: center;
    }
    
    .sidebar .nav-link:hover {
        color: white;
        background-color: rgba(255,255,255,0.1);
        border-radius: 5px;
    }
    
    .sidebar .nav-link.active {
        color: white;
        background-color: rgba(255,255,255,0.2);
        border-radius: 5px;
    }
    
    .sidebar .nav-link i {
        margin-right: 10px;
        font-size: 18px;
    }
    
    /* Mobile Header */
    .mobile-header {
        display: none;
        background: #2d3748;
        padding: 15px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        position: sticky;
        top: 0;
        z-index: 998;
    }
    
    @media (max-width: 768px) {
        .mobile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .sidebar .sidebar-user {
            position: relative !important;
            bottom: auto !important;
        }
    }
    
    .hamburger {
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        padding: 8px;
        min-width: 44px;
        min-height: 44px;
    }
    
    .top-nav {
        background: #2d3748;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        padding: 15px 30px;
        margin-bottom: 30px;
        color: #e0e0e0;
    }
    
    @media (max-width: 768px) {
        .top-nav {
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .top-nav h3 {
            font-size: 1.5rem;
        }
        
        .top-nav .text-muted {
            font-size: 0.85rem;
        }
    }
    
    .card {
        border: 1px solid #374151;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        margin-bottom: 20px;
        background-color: #2d3748;
        color: #e0e0e0;
    }
    
    @media (max-width: 768px) {
        .card {
            margin-bottom: 15px;
        }
    }
    
    .card-header {
        background: #374151;
        border-bottom: 2px solid #4b5563;
        font-weight: 600;
        color: #e0e0e0;
        padding: 15px;
    }
    
    .form-control, .form-select {
        background-color: #374151;
        border: 1px solid #4b5563;
        color: #e0e0e0;
        min-height: 44px; /* Touch-friendly */
        font-size: 16px; /* Prevent zoom on iOS */
    }
    
    .form-control:focus, .form-select:focus {
        background-color: #4b5563;
        border-color: #6b7280;
        color: #e0e0e0;
    }
    
    .form-label {
        color: #e0e0e0;
        font-size: 16px;
    }
    
    .btn {
        min-height: 44px; /* Touch-friendly */
        font-size: 16px;
    }
    
    /* Exception for explicitly small buttons */
    .btn-sm {
        min-height: 32px !important;
        font-size: 14px !important;
        padding: 4px 8px !important;
    }
    
    /* Floating Action Button */
    .fab {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
        color: white;
        border: none;
        box-shadow: 0 4px 12px rgba(0,0,0,0.4);
        font-size: 24px;
        display: none; /* Hidden on desktop */
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 1000;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .fab:hover, .fab:active {
        transform: scale(1.1);
        box-shadow: 0 6px 16px rgba(0,0,0,0.5);
    }
    
    @media (max-width: 768px) {
        .fab {
            display: flex;
        }
        
        /* Hide add forms on mobile - they'll be in modals */
        .mobile-hide-form {
            display: none;
        }
    }
    
    /* Modal overrides for dark theme */
    .modal-content {
        background-color: #2d3748;
        color: #e0e0e0;
        border: 1px solid #374151;
    }
    
    .modal-header {
        border-bottom: 1px solid #374151;
    }
    
    .modal-footer {
        border-top: 1px solid #374151;
    }
    
    .btn-close {
        filter: invert(1);
    }
    
    .text-muted {
        color: #9ca3af !important;
    }
    
    .welcome-card {
        background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%);
        color: white;
        border: none;
    }
    
    /* Container padding on mobile */
    @media (max-width: 768px) {
        .container-fluid {
            padding-left: 10px;
            padding-right: 10px;
        }
    }
</style>

<!-- Mobile Navigation Toggle Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const hamburger = document.querySelector('.hamburger');
    
    if (hamburger) {
        hamburger.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }
    
    // Close sidebar when clicking a nav link on mobile
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            }
        });
    });
});
</script>
