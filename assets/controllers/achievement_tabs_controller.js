import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tab', 'panel'];

    connect() {
        if (this.tabTargets.length > 0) {
            const firstTab = this.tabTargets[0];
            this.showTab(firstTab.dataset.tab);
        }
    }

    switchTab(event) {
        event.preventDefault();
        const tabId = event.currentTarget.dataset.tab;
        this.showTab(tabId);
    }

    showTab(tabId) {
        this.tabTargets.forEach((tab) => {
            if (tab.dataset.tab === tabId) {
                tab.classList.add('achievement-tab-active');
                tab.classList.remove('achievement-tab-inactive');
            } else {
                tab.classList.add('achievement-tab-inactive');
                tab.classList.remove('achievement-tab-active');
            }
        });

        this.panelTargets.forEach((panel) => {
            if (panel.dataset.tab === tabId) {
                panel.style.display = '';
            } else {
                panel.style.display = 'none';
            }
        });
    }
}
