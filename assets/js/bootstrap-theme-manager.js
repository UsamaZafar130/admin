// Bootstrap Theme Manager - Handles Bootswatch themes only
// Theme-specific functionality separated from main.js to avoid duplication

// Bootswatch themes configuration - All 20 available themes
const BOOTSWATCH_THEMES = {
    'cerulean': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/cerulean/bootstrap.min.css',
    'cosmo': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/cosmo/bootstrap.min.css',
    'cyborg': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/cyborg/bootstrap.min.css',
    'darkly': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/darkly/bootstrap.min.css',
    'flatly': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/flatly/bootstrap.min.css',
    'journal': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/journal/bootstrap.min.css',
    'litera': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/litera/bootstrap.min.css',
    'lumen': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/lumen/bootstrap.min.css',
    'lux': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/lux/bootstrap.min.css',
    'materia': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/materia/bootstrap.min.css',
    'minty': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/minty/bootstrap.min.css',
    'morph': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/morph/bootstrap.min.css',
    'pulse': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/pulse/bootstrap.min.css',
    'quartz': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/quartz/bootstrap.min.css',
    'sandstone': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/sandstone/bootstrap.min.css',
    'simplex': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/simplex/bootstrap.min.css',
    'sketchy': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/sketchy/bootstrap.min.css',
    'slate': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/slate/bootstrap.min.css',
    'solar': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/solar/bootstrap.min.css',
    'spacelab': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/spacelab/bootstrap.min.css',
    'superhero': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/superhero/bootstrap.min.css',
    'united': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/united/bootstrap.min.css',
    'vapor': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/vapor/bootstrap.min.css',
    'yeti': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/yeti/bootstrap.min.css',
    'zephyr': 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/zephyr/bootstrap.min.css'
};

// Set theme function - make it globally available
window.changeTheme = function(theme) {
    const themeLink = document.getElementById('bootstrap-theme');
    if (themeLink && BOOTSWATCH_THEMES[theme]) {
        themeLink.href = BOOTSWATCH_THEMES[theme];
        document.documentElement.setAttribute('data-theme', theme);
        if (window.localStorage) {
            localStorage.setItem('selectedTheme', theme);
            // Also maintain backward compatibility
            localStorage.setItem('frozoTheme', theme);
        }
    }
};

// Legacy function name for backward compatibility
function setTheme(theme) {
    window.changeTheme(theme);
}

// Set timezone function
function setTimezone(timezone) {
    if (window.localStorage) {
        localStorage.setItem('selectedTimezone', timezone);
        // Also maintain backward compatibility
        localStorage.setItem('frozoTimezone', timezone);
    }
}

document.addEventListener("DOMContentLoaded", function () {
    // Load saved theme or default to spacelab
    const savedTheme = (window.localStorage && (localStorage.getItem('selectedTheme') || localStorage.getItem('frozoTheme'))) || 'spacelab';
    window.changeTheme(savedTheme);
    
    // Handle legacy theme selector (if it exists in footer)
    const themeSelector = document.getElementById('theme-selector');
    if (themeSelector) {
        themeSelector.value = savedTheme;
        
        themeSelector.addEventListener('change', function () {
            window.changeTheme(this.value);
        });
    }

    // Enhanced timezone handling for legacy selector
    const tzSelector = document.getElementById('timezone-selector');
    if (tzSelector) {
        // Load saved timezone first, prioritize new localStorage key
        const savedTimezone = window.localStorage && (localStorage.getItem('selectedTimezone') || localStorage.getItem('frozoTimezone'));
        if (savedTimezone) {
            tzSelector.value = savedTimezone;
        }
        
        // Set up timezone change handler
        tzSelector.addEventListener('change', function () {
            setTimezone(this.value);
        });
    }
});