(function () {
    var cookieName = 'luna_theme';
    var choices = document.querySelectorAll('[data-theme-choice]');

    function readTheme() {
        var match = document.cookie.match(new RegExp('(?:^|; )' + cookieName + '=([^;]*)'));
        return match && match[1] === 'light' ? 'light' : 'dark';
    }

    function writeTheme(theme) {
        var maxAge = 60 * 60 * 24 * 365;
        document.cookie = cookieName + '=' + theme + '; Max-Age=' + maxAge + '; Path=/; SameSite=Lax';
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-bs-theme', theme);

        choices.forEach(function (button) {
            var active = button.getAttribute('data-theme-choice') === theme;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    choices.forEach(function (button) {
        button.addEventListener('click', function () {
            var theme = button.getAttribute('data-theme-choice') === 'light' ? 'light' : 'dark';
            writeTheme(theme);
            applyTheme(theme);
        });
    });

    applyTheme(readTheme());
})();
