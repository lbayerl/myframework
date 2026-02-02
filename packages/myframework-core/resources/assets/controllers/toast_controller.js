/**
 * Toast Controller
 * Auto-shows Bootstrap toasts on connect and handles auto-dismiss
 * 
 * Usage:
 * <div class="toast" data-controller="myframework--toast" data-myframework--toast-delay-value="3000">
 */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        delay: { type: Number, default: 3000 },
        autohide: { type: Boolean, default: true }
    }

    connect() {
        // Bootstrap 5 Toast API
        const toast = new window.bootstrap.Toast(this.element, {
            autohide: this.autohideValue,
            delay: this.delayValue
        });
        
        toast.show();

        // Cleanup after hide
        this.element.addEventListener('hidden.bs.toast', () => {
            this.element.remove();
        }, { once: true });
    }

    dismiss() {
        window.bootstrap.Toast.getInstance(this.element)?.hide();
    }
}
