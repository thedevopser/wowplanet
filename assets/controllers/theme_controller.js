import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['icon'];

    connect() {
        this.initializeTheme();
    }

    initializeTheme() {
        const savedTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        if (savedTheme === 'dark' || (!savedTheme && systemPrefersDark)) {
            this.enableDarkMode();
            return;
        }

        this.enableLightMode();
    }

    toggle() {
        if (document.documentElement.classList.contains('dark')) {
            this.enableLightMode();
            return;
        }

        this.enableDarkMode();
    }

    enableDarkMode() {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
        this.updateIcon();
    }

    enableLightMode() {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
        this.updateIcon();
    }

    updateIcon() {
        if (!this.hasIconTarget) {
            return;
        }

        const isDark = document.documentElement.classList.contains('dark');
        this.iconTarget.textContent = isDark ? '☀️' : '🌙';
    }
}
