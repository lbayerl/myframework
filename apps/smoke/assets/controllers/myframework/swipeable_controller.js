/**
 * Swipeable Controller
 * Detects swipe gestures (left, right, up, down) on touch devices
 * 
 * Usage:
 * <div data-controller="myframework--swipeable"
 *      data-action="swipeleft->myfunction swiperight->otherfunction"
 *      data-myframework--swipeable-threshold-value="50">
 * 
 * Events dispatched:
 * - swipeleft, swiperight, swipeup, swipedown
 */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        threshold: { type: Number, default: 50 }, // Minimum distance in pixels
        restraint: { type: Number, default: 100 }, // Maximum perpendicular distance
        allowedTime: { type: Number, default: 500 } // Maximum time in ms
    }

    connect() {
        this.startX = 0;
        this.startY = 0;
        this.startTime = 0;

        this.element.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: true });
        this.element.addEventListener('touchend', this.handleTouchEnd.bind(this), { passive: false });
    }

    handleTouchStart(e) {
        const touch = e.changedTouches[0];
        this.startX = touch.pageX;
        this.startY = touch.pageY;
        this.startTime = Date.now();
    }

    handleTouchEnd(e) {
        const touch = e.changedTouches[0];
        const distX = touch.pageX - this.startX;
        const distY = touch.pageY - this.startY;
        const elapsedTime = Date.now() - this.startTime;

        // Check if swipe is within allowed time
        if (elapsedTime > this.allowedTimeValue) return;

        // Horizontal swipe
        if (Math.abs(distX) >= this.thresholdValue && Math.abs(distY) <= this.restraintValue) {
            const direction = distX < 0 ? 'left' : 'right';
            this.dispatch(direction, { prefix: 'swipe', detail: { distX, distY, elapsedTime } });
        }
        // Vertical swipe
        else if (Math.abs(distY) >= this.thresholdValue && Math.abs(distX) <= this.restraintValue) {
            const direction = distY < 0 ? 'up' : 'down';
            this.dispatch(direction, { prefix: 'swipe', detail: { distX, distY, elapsedTime } });
        }
    }
}
