<style>
.sidebar {
    transition: width 0.3s ease;
}

.sidebar.collapsed {
    width: 4rem;
}

.menu-text {
    transition: opacity 0.2s ease;
}

.sidebar.collapsed .menu-text {
    display: none;
}

#main-content {
    transition: margin-left 0.3s ease;
}

#main-content.sidebar-collapsed {
    margin-left: 4rem;
}
</style>

<div id="sidebar" class="sidebar fixed left-0 top-0 w-64 h-screen bg-gray-800 z-50">
    <div class="flex items-center justify-between h-16 bg-gray-900 px-4">
        <div class="flex items-center">
            <i class="fas fa-user-shield text-white text-2xl mr-2 menu-icon"></i>
            <span class="text-white text-xl font-bold menu-text">Admin Panel</span>
        </div>
        <button onclick="toggleSidebar()" class="text-white hover:text-gray-300">
            <i id="sidebarIcon" class="fas fa-chevron-left"></i>
        </button>
    </div>
    
    <nav class="mt-6">
        <a href="admin.php" class="flex items-center px-6 py-3 text-gray-100 hover:bg-gray-700">
            <i class="fas fa-users-cog menu-icon mr-3"></i>
            <span class="menu-text">Manajemen User</span>
        </a>
        
        <a href="admin_settings.php" class="flex items-center px-6 py-3 text-gray-100 hover:bg-gray-700">
            <i class="fas fa-user-cog menu-icon mr-3"></i>
            <span class="menu-text">Pengaturan Admin</span>
        </a>
        
        <a href="index.php" class="flex items-center px-6 py-3 text-gray-100 hover:bg-gray-700">
            <i class="fas fa-chart-line menu-icon mr-3"></i>
            <span class="menu-text">Lihat Website</span>
        </a>
        
        <div class="border-t border-gray-700 my-4"></div>
        
        <a href="src/auth/logout.php" class="flex items-center px-6 py-3 text-red-400 hover:bg-gray-700">
            <i class="fas fa-sign-out-alt menu-icon mr-3"></i>
            <span class="menu-text">Keluar</span>
        </a>
    </nav>
</div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const sidebarIcon = document.getElementById('sidebarIcon');
    
    sidebar.classList.toggle('collapsed');
    mainContent.classList.toggle('sidebar-collapsed');
    
    if (sidebar.classList.contains('collapsed')) {
        sidebarIcon.classList.replace('fa-chevron-left', 'fa-chevron-right');
        localStorage.setItem('adminSidebarCollapsed', 'true');
    } else {
        sidebarIcon.classList.replace('fa-chevron-right', 'fa-chevron-left');
        localStorage.setItem('adminSidebarCollapsed', 'false');
    }
}

// Load sidebar state from localStorage
document.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('adminSidebarCollapsed') === 'true') {
        toggleSidebar();
    }
});
</script>
