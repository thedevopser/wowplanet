import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['item', 'page', 'prevButton', 'nextButton', 'pageInfo'];
    static values = {
        perPage: { type: Number, default: 12 },
        currentPage: { type: Number, default: 1 }
    };

    connect() {
        this.totalItems = this.itemTargets.length;
        this.totalPages = Math.ceil(this.totalItems / this.perPageValue);
        this.showPage(1);
    }

    showPage(pageNumber) {
        this.currentPageValue = pageNumber;
        const start = (pageNumber - 1) * this.perPageValue;
        const end = start + this.perPageValue;

        // Hide all items
        this.itemTargets.forEach((item, index) => {
            if (index >= start && index < end) {
                item.classList.remove('hidden');
                item.style.animation = 'fadeIn 0.3s ease-in-out';
            } else {
                item.classList.add('hidden');
            }
        });

        // Update pagination buttons
        this.updatePaginationUI();

        // Scroll to top of results
        this.element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    updatePaginationUI() {
        // Update page buttons
        this.pageTargets.forEach((button) => {
            const pageNum = parseInt(button.dataset.page);
            if (pageNum === this.currentPageValue) {
                button.classList.add('bg-blue-600', 'text-white', 'shadow-lg', 'shadow-blue-500/30');
                button.classList.remove('bg-white/5', 'text-white/60');
            } else {
                button.classList.remove('bg-blue-600', 'text-white', 'shadow-lg', 'shadow-blue-500/30');
                button.classList.add('bg-white/5', 'text-white/60');
            }
        });

        // Update prev/next buttons
        if (this.hasPrevButtonTarget) {
            this.prevButtonTarget.disabled = this.currentPageValue === 1;
            if (this.currentPageValue === 1) {
                this.prevButtonTarget.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                this.prevButtonTarget.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }

        if (this.hasNextButtonTarget) {
            this.nextButtonTarget.disabled = this.currentPageValue === this.totalPages;
            if (this.currentPageValue === this.totalPages) {
                this.nextButtonTarget.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                this.nextButtonTarget.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }

        // Update page info
        if (this.hasPageInfoTarget) {
            const start = (this.currentPageValue - 1) * this.perPageValue + 1;
            const end = Math.min(this.currentPageValue * this.perPageValue, this.totalItems);
            this.pageInfoTarget.textContent = `${start}-${end} sur ${this.totalItems}`;
        }
    }

    goToPage(event) {
        const pageNumber = parseInt(event.currentTarget.dataset.page);
        this.showPage(pageNumber);
    }

    previousPage() {
        if (this.currentPageValue > 1) {
            this.showPage(this.currentPageValue - 1);
        }
    }

    nextPage() {
        if (this.currentPageValue < this.totalPages) {
            this.showPage(this.currentPageValue + 1);
        }
    }
}
