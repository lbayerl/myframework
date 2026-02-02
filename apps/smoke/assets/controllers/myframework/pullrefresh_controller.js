/**
 * Pull to Refresh Controller
 * Implements pull-to-refresh gesture for mobile PWAs
 * 
 * Usage:
 * <div data-controller="myframework--pullrefresh"
 *      data-action="refresh->handleRefresh"
 *      data-myframework--pullrefresh-threshold-value="80">
 * 
 * Events dispatched:
 * - refresh (when pull threshold is reached and released)
 */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        threshold: { type: Number, default: 80 }, // Pull distance threshold in pixels
        maxPull: { type: Number, default: 150 }, // Maximum pull distance
        resistance: { type: Number, default: 2.5 } // Pull resistance factor
    }

    static targets = ['indicator']

    connect() {
        this.startY = 0;
        this.currentY = 0;
        this.isPulling = false;
        this.isRefreshing = false;

        // Only enable if at top of page
        this.element.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: true });
        this.element.addEventListener('touchmove', this.handleTouchMove.bind(this), { passive: false });
        this.element.addEventListener('touchend', this.handleTouchEnd.bind(this), { passive: true });

        this.createIndicator();
    }

    disconnect() {
        this.removeIndicator();
    }

    createIndicator() {
        if (!this.hasIndicatorTarget) {
            const indicator = document.createElement('div');
            indicator.className = 'pull-refresh-indicator';
            indicator.innerHTML = '<div class="spinner-border spinner-border-sm text-primary" role="status"></div>';
            indicator.style.cssText = `
                position: absolute;
                top: 0;
                left: 50%;
                transform: translateX(-50%) translateY(-100%);
                padding: 1rem;
                transition: transform 0.2s ease-out;
                z-index: 1000;
                opacity: 0;
            `;
            this.element.style.position = 'relative';
            this.element.insertBefore(indicator, this.element.firstChild);
            this.indicator = indicator;
        } else {
            this.indicator = this.indicatorTarget;
        }
    }

    removeIndicator() {
        if (this.indicator && !this.hasIndicatorTarget) {
            this.indicator.remove();
        }
    }

    handleTouchStart(e) {
        // Only trigger if scrolled to top
        if (window.scrollY > 0 || this.isRefreshing) return;
        
        this.startY = e.touches[0].pageY;
        this.isPulling = true;
    }

    handleTouchMove(e) {
        if (!this.isPulling || this.isRefreshing) return;

        this.currentY = e.touches[0].pageY;
        const pullDistance = this.currentY - this.startY;

        // Only pull down, and only if at top
        if (pullDistance > 0 && window.scrollY === 0) {
            e.preventDefault();
            
            // Apply resistance
            const resistedPull = Math.min(
                pullDistance / this.resistanceValue,
                this.maxPullValue
            );

            // Update indicator position and opacity
            this.indicator.style.transform = `translateX(-50%) translateY(${resistedPull - 100}%)`;
            this.indicator.style.opacity = Math.min(resistedPull / this.thresholdValue, 1);

            // Visual feedback when threshold reached
            if (resistedPull >= this.thresholdValue) {
                this.indicator.classList.add('ready');
            } else {
                this.indicator.classList.remove('ready');
            }
        }
    }

    async handleTouchEnd() {
        if (!this.isPulling || this.isRefreshing) return;

        const pullDistance = (this.currentY - this.startY) / this.resistanceValue;

        if (pullDistance >= this.thresholdValue) {
            this.isRefreshing = true;
            this.indicator.style.transform = 'translateX(-50%) translateY(0)';
            
            try {
                // Dispatch refresh event
                await this.dispatch('refresh', { 
                    cancelable: true,
                    detail: { pullDistance } 
                });
            } finally {
                // Reset after 1 second minimum (for visual feedback)
                setTimeout(() => {
                    this.reset();
                }, 1000);
            }
        } else {
            this.reset();
        }

        this.isPulling = false;
    }

    reset() {
        this.indicator.style.transform = 'translateX(-50%) translateY(-100%)';
        this.indicator.style.opacity = '0';
        this.indicator.classList.remove('ready');
        this.isRefreshing = false;
        this.startY = 0;
        this.currentY = 0;
    }

    // Public method for programmatic refresh completion
    complete() {
        if (this.isRefreshing) {
            this.reset();
        }
    }
}
