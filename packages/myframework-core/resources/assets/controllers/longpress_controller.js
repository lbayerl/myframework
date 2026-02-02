/**
 * Long Press Controller
 * Detects long press gestures on touch and mouse devices
 * 
 * Usage:
 * <button data-controller="myframework--longpress"
 *         data-action="longpress->handleLongPress"
 *         data-myframework--longpress-duration-value="500">
 * 
 * Events dispatched:
 * - longpress (after duration threshold)
 * - longpressstart (on press start)
 * - longpresscancel (if cancelled before duration)
 */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        duration: { type: Number, default: 500 } // Duration in ms
    }

    connect() {
        this.timer = null;
        this.isPressed = false;

        // Support both touch and mouse
        this.element.addEventListener('touchstart', this.start.bind(this), { passive: false });
        this.element.addEventListener('mousedown', this.start.bind(this));
        
        this.element.addEventListener('touchend', this.cancel.bind(this));
        this.element.addEventListener('touchmove', this.cancel.bind(this));
        this.element.addEventListener('mouseup', this.cancel.bind(this));
        this.element.addEventListener('mouseleave', this.cancel.bind(this));
    }

    disconnect() {
        this.cancel();
    }

    start(e) {
        if (this.isPressed) return;
        
        this.isPressed = true;
        this.dispatch('start', { prefix: 'longpress' });

        this.timer = setTimeout(() => {
            if (this.isPressed) {
                this.dispatch('longpress');
                this.isPressed = false;
            }
        }, this.durationValue);
    }

    cancel() {
        if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
        }
        
        if (this.isPressed) {
            this.dispatch('cancel', { prefix: 'longpress' });
            this.isPressed = false;
        }
    }
}
