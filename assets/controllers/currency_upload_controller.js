import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['form', 'loader'];

    submit(event) {
        // Hide form and show loader
        this.formTarget.classList.add('hidden');
        this.loaderTarget.classList.remove('hidden');
    }
}
