import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tab', 'panel'];

    connect() {
        this.showTab('quests');
    }

    switchTab(event) {
        event.preventDefault();
        const tabName = event.currentTarget.dataset.tab;
        this.showTab(tabName);
    }

    showTab(tabName) {
        this.tabTargets.forEach((tab) => {
            if (tab.dataset.tab === tabName) {
                tab.classList.add('tab-active');
                tab.classList.remove('tab-inactive');
            } else {
                tab.classList.add('tab-inactive');
                tab.classList.remove('tab-active');
            }
        });

        this.panelTargets.forEach((panel) => {
            if (panel.dataset.tab === tabName) {
                panel.classList.remove('hidden');
            } else {
                panel.classList.add('hidden');
            }
        });
    }
}
