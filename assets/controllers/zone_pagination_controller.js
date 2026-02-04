import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['item', 'info', 'prevButton', 'nextButton'];
    static values = { perPage: { type: Number, default: 10 } };

    connect() {
        this.currentPage = 1;
        this.totalItems = this.itemTargets.length;
        this.totalPages = Math.ceil(this.totalItems / this.perPageValue);
        this.render();
    }

    previousPage() {
        if (this.currentPage > 1) {
            this.currentPage--;
            this.render();
        }
    }

    nextPage() {
        if (this.currentPage < this.totalPages) {
            this.currentPage++;
            this.render();
        }
    }

    render() {
        const start = (this.currentPage - 1) * this.perPageValue;
        const end = start + this.perPageValue;

        this.itemTargets.forEach((item, index) => {
            item.style.display = index >= start && index < end ? '' : 'none';
        });

        if (this.hasInfoTarget) {
            this.infoTarget.textContent =
                this.totalPages > 1
                    ? `${this.currentPage} / ${this.totalPages}`
                    : '';
        }

        if (this.hasPrevButtonTarget) {
            this.prevButtonTarget.disabled = this.currentPage <= 1;
        }

        if (this.hasNextButtonTarget) {
            this.nextButtonTarget.disabled =
                this.currentPage >= this.totalPages;
        }
    }
}
