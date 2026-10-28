(function () {
    try {
        var mode = localStorage.getItem('inlay.mode');
        if (mode !== 'light' && mode !== 'dark') {
            mode = window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-mode', mode);
    } catch (e) {}
})();
