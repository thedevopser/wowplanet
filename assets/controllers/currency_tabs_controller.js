import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['fileTab', 'pasteTab', 'fileInput', 'pasteInput'];

    connect() {
        this.showFileTab();
    }

    showFileTab(event) {
        if (event) {
            event.preventDefault();
        }

        this.fileTabTarget.classList.add('tab-active');
        this.fileTabTarget.classList.remove('tab-inactive');
        this.pasteTabTarget.classList.add('tab-inactive');
        this.pasteTabTarget.classList.remove('tab-active');

        this.fileInputTarget.classList.remove('hidden');
        this.pasteInputTarget.classList.add('hidden');

        const textarea = this.pasteInputTarget.querySelector('textarea');
        if (textarea) {
            textarea.value = '';
        }
    }

    showPasteTab(event) {
        if (event) {
            event.preventDefault();
        }

        this.pasteTabTarget.classList.add('tab-active');
        this.pasteTabTarget.classList.remove('tab-inactive');
        this.fileTabTarget.classList.add('tab-inactive');
        this.fileTabTarget.classList.remove('tab-active');

        this.pasteInputTarget.classList.remove('hidden');
        this.fileInputTarget.classList.add('hidden');

        const fileInput = this.fileInputTarget.querySelector('input[type="file"]');
        if (fileInput) {
            fileInput.value = '';
        }
    }
}
