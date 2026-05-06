// Dark mode script: injects toggle into navbars, persists preference
(function(){
    function createToggle() {
        const wrap = document.createElement('div');
        wrap.className = 'theme-toggle';
        wrap.id = 'themeToggle';
        wrap.innerHTML = '<span class="theme-toggle-icon moon">🌙</span>' +
                         '<span class="theme-toggle-icon sun">☀️</span>' +
                         '<div class="theme-toggle-slider"></div>';
        return wrap;
    }

    function ensureToggle() {
        // find an existing nav-links list
        const navLinks = document.querySelector('.nav-links') || document.querySelector('.d-nav-links') || document.querySelector('nav ul');
        if (!navLinks) return null;

        // avoid inserting multiple toggles
        if (document.getElementById('themeToggle')) return document.getElementById('themeToggle');

        const li = document.createElement('li');
        li.style.listStyle = 'none';
        li.appendChild(createToggle());
        navLinks.insertBefore(li, navLinks.lastElementChild || null);
        return document.getElementById('themeToggle');
    }

    function applySavedTheme(toggleEl) {
        const html = document.documentElement;
        const saved = localStorage.getItem('theme-preference') || 'light';
        if (saved === 'dark') { html.classList.add('dark-mode'); toggleEl.classList.add('dark'); }
        else { html.classList.remove('dark-mode'); toggleEl.classList.remove('dark'); }
    }

    function setup() {
        const toggleEl = ensureToggle();
        if (!toggleEl) return;
        applySavedTheme(toggleEl);
        toggleEl.addEventListener('click', function(){
            const html = document.documentElement;
            if (html.classList.contains('dark-mode')){
                html.classList.remove('dark-mode'); toggleEl.classList.remove('dark'); localStorage.setItem('theme-preference','light');
            } else {
                html.classList.add('dark-mode'); toggleEl.classList.add('dark'); localStorage.setItem('theme-preference','dark');
            }
        });
    }

    // Run on DOM ready
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setup);
    else setup();
})();
