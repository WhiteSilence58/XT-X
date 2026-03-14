// assets/js/theme.js
// Theme Management - wird von auth.php und dashboard.php genutzt

document.addEventListener('DOMContentLoaded', function() {
    // Theme aus localStorage laden oder Standard (light)
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.dataset.theme = savedTheme;

    // Icon aktualisieren
    const themeIcon = document.getElementById('theme-icon');
    if (themeIcon) {
        themeIcon.textContent = savedTheme === 'dark' ? '☀️' : '🌙';
    }
});

function toggleTheme() {
    const html = document.documentElement;
    const isDark = html.dataset.theme === 'dark';
    html.dataset.theme = isDark ? 'light' : 'dark';

    const themeIcon = document.getElementById('theme-icon');
    if (themeIcon) {
        themeIcon.textContent = isDark ? '🌙' : '☀️';
    }

    localStorage.setItem('theme', html.dataset.theme);
}