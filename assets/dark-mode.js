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
        const existingToggle = document.getElementById('themeToggle');
        if (existingToggle) {
            const existingItem = existingToggle.parentElement;
            if (existingItem && existingItem.parentElement === navLinks && navLinks.lastElementChild !== existingItem) {
                navLinks.appendChild(existingItem);
            }
            return existingToggle;
        }

        const li = document.createElement('li');
        li.style.listStyle = 'none';
        li.appendChild(createToggle());
        navLinks.appendChild(li);
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
            // Dispatch event so pages can respond to theme change
            window.dispatchEvent(new Event('theme-changed'));
            // Also reload page to ensure all charts re-initialize with correct colors
            setTimeout(function() {
                location.reload();
            }, 100);
        });
    }

    // Run on DOM ready
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setup);
    else setup();
})();
