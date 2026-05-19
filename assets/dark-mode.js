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
        const existingToggle = document.getElementById('themeToggle');
        if (existingToggle) return existingToggle;

        const container = document.getElementById('darkModeContainer');
        if (container) {
            const toggle = createToggle();
            container.appendChild(toggle);
            return toggle;
        }
        return null;
    }

    function applySavedTheme(toggleEl) {
        const html = document.documentElement;
        const saved = localStorage.getItem('theme-preference') || 'light';
        if (saved === 'dark') { html.classList.add('dark-mode'); toggleEl.classList.add('dark'); }
        else { html.classList.remove('dark-mode'); toggleEl.classList.remove('dark'); }
    }

    function setup() {
        const toggleEl = ensureToggle();
        if (!toggleEl) return false;
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
        return true;
    }

    // Run on DOM ready with robust polling to ensure darkModeContainer is present
    function init() {
        let attempts = 0;
        const maxAttempts = 100; // 1 second maximum wait time
        
        const tryInit = function() {
            attempts++;
            const success = setup();
            if (!success && attempts < maxAttempts) {
                setTimeout(tryInit, 10);
            }
        };
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', tryInit);
        } else {
            tryInit();
        }
    }
    
    init();
})();
