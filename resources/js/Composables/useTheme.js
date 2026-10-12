import { ref, computed } from 'vue';

// 儲存於 LocalStorage 的 key
const THEME_KEY = 'theme';

// 當前主題設定：'light' | 'dark' | 'system'
const currentTheme = ref('system');
const systemIsDark = ref(false);

let isInitialized = false;

function updateDocumentClass(isDark) {
    if (typeof document === 'undefined') return;
    if (isDark) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
}

export function useTheme() {
    if (!isInitialized && typeof window !== 'undefined') {
        isInitialized = true;

        // 讀取既有設定
        const stored = localStorage.getItem(THEME_KEY);
        if (stored === 'light' || stored === 'dark') {
            currentTheme.value = stored;
        } else {
            currentTheme.value = 'system';
        }

        // 系統偏好監聽
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        systemIsDark.value = mediaQuery.matches;

        mediaQuery.addEventListener('change', (e) => {
            systemIsDark.value = e.matches;
            if (currentTheme.value === 'system') {
                updateDocumentClass(e.matches);
            }
        });

        // 首次更新
        const activeDark = currentTheme.value === 'dark' || (currentTheme.value === 'system' && systemIsDark.value);
        updateDocumentClass(activeDark);
    }

    const isDark = computed(() => {
        if (currentTheme.value === 'system') {
            return systemIsDark.value;
        }
        return currentTheme.value === 'dark';
    });

    const setTheme = (theme) => {
        if (!['light', 'dark', 'system'].includes(theme)) return;

        currentTheme.value = theme;
        if (theme === 'system') {
            localStorage.removeItem(THEME_KEY);
            updateDocumentClass(systemIsDark.value);
        } else {
            localStorage.setItem(THEME_KEY, theme);
            updateDocumentClass(theme === 'dark');
        }
    };

    const toggleTheme = () => {
        if (isDark.value) {
            setTheme('light');
        } else {
            setTheme('dark');
        }
    };

    return {
        theme: currentTheme,
        isDark,
        setTheme,
        toggleTheme,
    };
}
