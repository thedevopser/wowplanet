// Inlined verbatim in <head> by App\Http\Theme\ThemeBootScript, before any stylesheet: no import, no export.
(function () {
    try {
        var match = document.cookie.match(/(?:^|; )wowplanet-theme=(system|dark|light)(?:;|$)/);
        var stored = localStorage.getItem('wowplanet-theme');
        var choice = match ? match[1] : stored;
        var dark = choice === 'dark'
            || (choice !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);

        document.documentElement.classList.toggle('dark', dark);
    } catch (error) {
        // The server-rendered class stays: a missing preference must never break the page.
    }
})();
