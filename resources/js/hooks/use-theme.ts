import { useCallback, useEffect, useState } from 'react';

type Theme = 'light' | 'dark';

const STORAGE_KEY = 'worktrack-theme';

function resolveInitialTheme(): Theme {
    if (typeof document === 'undefined') {
        return 'light';
    }

    return document.documentElement.classList.contains('dark')
        ? 'dark'
        : 'light';
}

/**
 * Reads/writes the `dark` class on <html>, matching the blocking
 * inline script in app.blade.php that sets it before first paint.
 */
export function useTheme() {
    const [theme, setTheme] = useState<Theme>(resolveInitialTheme);

    useEffect(() => {
        document.documentElement.classList.toggle('dark', theme === 'dark');
    }, [theme]);

    const toggleTheme = useCallback(() => {
        setTheme((current) => {
            const next = current === 'dark' ? 'light' : 'dark';
            localStorage.setItem(STORAGE_KEY, next);

            return next;
        });
    }, []);

    return { theme, toggleTheme };
}
