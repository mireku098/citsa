// Sidebar Toggle Functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    const topNavbar = document.querySelector('.navbar');
    const sidebarToggle = document.getElementById('sidebarToggle');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            if (sidebar.classList.contains('active')) {
                // Sidebar hidden
                sidebar.style.transform = 'translateX(-100%)';
                mainContent.style.marginLeft = '0';
                topNavbar.style.marginLeft = '0';
            } else {
                // Sidebar shown
                sidebar.style.transform = 'translateX(0)';
                mainContent.style.marginLeft = '280px';
                topNavbar.style.marginLeft = '280px';
            }
        });
    }
});
