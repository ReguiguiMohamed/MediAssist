// Dark Mode Implementation
document.addEventListener('DOMContentLoaded', function () {
    const darkModeToggle = document.getElementById('dark-mode-toggle');
    const body = document.body;

    // Check for saved theme preference or use preferred color scheme
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        body.classList.add('dark-mode');
    }

    // Only add event listener if the button exists
    if (darkModeToggle) {
        darkModeToggle.addEventListener('click', function () {
            body.classList.toggle('dark-mode');
            // Save preference to localStorage
            if (body.classList.contains('dark-mode')) {
                localStorage.setItem('theme', 'dark');
            } else {
                localStorage.setItem('theme', 'light');
            }
        });
    }
});
