import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['form', 'loader', 'currencyName'];

    submit(event) {
        const currencyInput = this.element.querySelector('#currency');
        const currencyValue = currencyInput.value.trim();

        if (!currencyValue) {
            return;
        }

        // Update loader with currency name
        this.currencyNameTarget.textContent = currencyValue;

        // Hide form and show loader
        this.formTarget.style.display = 'none';
        this.loaderTarget.classList.remove('hidden');

        // Let the form submit normally
        // Don't prevent default - the form needs to POST to the server
    }
}
