(() => {
	'use strict';

	const storageKey = 'theme';
	const validThemes = ['light', 'dark', 'auto'];

	const normalizeTheme = (theme) => (validThemes.includes(theme) ? theme : 'auto');
	const getStoredTheme = () => normalizeTheme(localStorage.getItem(storageKey));
	const getSystemTheme = () =>
		window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
	const resolveTheme = (theme) => (theme === 'auto' ? getSystemTheme() : theme);

	const applyTheme = (theme) => {
		document.documentElement.setAttribute('data-bs-theme', resolveTheme(theme));
	};

	const updateActiveButtons = (theme) => {
		document.querySelectorAll('[data-bs-theme-value]').forEach((button) => {
			const isActive = button.getAttribute('data-bs-theme-value') === theme;
			button.classList.toggle('active', isActive);
			button.setAttribute('aria-pressed', String(isActive));
		});
	};

	const initThemeSwitcher = () => {
		const preferredTheme = getStoredTheme();
		applyTheme(preferredTheme);
		updateActiveButtons(preferredTheme);

		document.querySelectorAll('[data-bs-theme-value]').forEach((button) => {
			button.addEventListener('click', () => {
				const nextTheme = normalizeTheme(button.getAttribute('data-bs-theme-value'));
				localStorage.setItem(storageKey, nextTheme);
				applyTheme(nextTheme);
				updateActiveButtons(nextTheme);
			});
		});
	};

	const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
	const onSystemThemeChange = () => {
		if (getStoredTheme() === 'auto') {
			applyTheme('auto');
		}
	};

	if (typeof mediaQuery.addEventListener === 'function') {
		mediaQuery.addEventListener('change', onSystemThemeChange);
	} else if (typeof mediaQuery.addListener === 'function') {
		mediaQuery.addListener(onSystemThemeChange);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initThemeSwitcher);
	} else {
		initThemeSwitcher();
	}
})();
