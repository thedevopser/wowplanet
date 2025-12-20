import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['form', 'loader', 'factionName'];

    submit(event) {
        const factionInput = this.element.querySelector('#faction');
        const factionValue = factionInput.value.trim();

        if (!factionValue) {
            return;
        }

        // Update loader with faction name
        this.factionNameTarget.textContent = factionValue;

        // Hide form and show loader
        this.formTarget.style.display = 'none';
        this.loaderTarget.classList.remove('hidden');

        // Let the form submit normally
        // Don't prevent default - the form needs to POST to the server
    }
}
