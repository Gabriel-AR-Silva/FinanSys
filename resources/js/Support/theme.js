const STORAGE_KEY = 'finansys.theme';

export function getPreferredTheme() {
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);
        if (stored === 'dark' || stored === 'light') return stored;
    } catch (_) {
        // Storage may be blocked; fall back to the OS preference.
    }

    return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function applyTheme(theme) {
    const resolved = theme === 'dark' ? 'dark' : 'light';
    document.documentElement.classList.toggle('dark', resolved === 'dark');
    document.documentElement.dataset.theme = resolved;
    document.documentElement.style.colorScheme = resolved;
    return resolved;
}

export function setTheme(theme) {
    const resolved = applyTheme(theme);

    try {
        window.localStorage.setItem(STORAGE_KEY, resolved);
    } catch (_) {
        // Storage may be blocked. The active page still keeps the selected theme.
    }

    window.dispatchEvent(new CustomEvent('finansys:theme-changed', { detail: { theme: resolved } }));

    return resolved;
}
