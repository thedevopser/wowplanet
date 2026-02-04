import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['trigger', 'content', 'icon'];

    toggle() {
        const isHidden = this.contentTarget.classList.contains('hidden');

        if (isHidden) {
            this.contentTarget.classList.remove('hidden');
            if (this.hasIconTarget) {
                this.iconTarget.style.transform = 'rotate(90deg)';
            }
        } else {
            this.contentTarget.classList.add('hidden');
            if (this.hasIconTarget) {
                this.iconTarget.style.transform = 'rotate(0deg)';
            }
        }
    }
}
