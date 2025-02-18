<div id="sidebar" class="sidebar fixed left-0 top-0 w-64 h-screen bg-gray-800 z-50">
    <div class="flex items-center justify-between h-16 bg-gray-900 px-4">
        <div class="flex items-center">
            <i class="fas fa-wallet text-white text-2xl mr-2 menu-icon"></i>
            <span class="text-white text-xl font-bold menu-text">Pencatat Keuangan</span>
        </div>
        <button onclick="toggleSidebar()" class="text-white hover:text-gray-300">
            <i id="sidebarIcon" class="fas fa-chevron-left"></i>
        </button>
    </div>
    
    <nav class="mt-6">
        <a href="index.php" class="flex items-center px-6 py-3 text-gray-100 hover:bg-gray-700">
            <i class="fas fa-home menu-icon mr-3"></i>
            <span class="menu-text">Dashboard</span>
        </a>
        
        <a href="debts.php" class="flex items-center px-6 py-3 text-gray-100 hover:bg-gray-700">
            <i class="fas fa-hand-holding-usd menu-icon mr-3"></i>
            <span class="menu-text">Hutang & Piutang</span>
        </a>
        
        <a href="exchange.php" class="flex items-center px-6 py-3 text-gray-100 hover:bg-gray-700">
            <i class="fas fa-exchange-alt menu-icon mr-3"></i>
            <span class="menu-text">Kurs Mata Uang</span>
        </a>
        
        <a href="targets.php" class="flex items-center px-6 py-3 text-gray-100 hover:bg-gray-700">
            <i class="fas fa-bullseye menu-icon mr-3"></i>
            <span class="menu-text">Target Keuangan</span>
        </a>
        
        <a href="settings.php" class="flex items-center px-6 py-3 text-gray-100 hover:bg-gray-700">
            <i class="fas fa-cog menu-icon mr-3"></i>
            <span class="menu-text">Pengaturan</span>
        </a>
        
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
        localStorage.setItem('sidebarCollapsed', 'true');
    } else {
        sidebarIcon.classList.replace('fa-chevron-right', 'fa-chevron-left');
        localStorage.setItem('sidebarCollapsed', 'false');
    }
}

// Load sidebar state from localStorage
document.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        toggleSidebar();
    }
});
</script>
