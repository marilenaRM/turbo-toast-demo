import { Controller } from '@hotwired/stimulus';

/*
 * US-006 hook 2: cancels the bundle's `:append` event to render toasts into
 * a homemade console-style notifier — the README's documented pattern, a
 * document-level listener registered once at module load.
 *
 * Deliberately NOT bound to connect()/disconnect(): the notifier section is
 * data-turbo-permanent, and Turbo's permanent-element swap briefly
 * disconnects it exactly when the container consumes the deferred cookie on
 * turbo:load — a lifecycle-bound listener misses the event every time.
 *
 * Security: the cookie is client-modifiable. The message is written with
 * textContent only, never innerHTML.
 */
document.addEventListener('marilenarm--turbo-toast--toast-container:append', (event) => {
    const toggle = document.querySelector('[data-custom-hooks-target="toggle"]');
    if (!toggle || !toggle.checked) {
        return;
    }

    event.preventDefault();

    const { message, type = 'success' } = event.detail.toast;
    const line = document.createElement('p');
    line.className = 'console-line';
    line.textContent = `[${type}] ${String(message)}`;
    document.querySelector('[data-custom-hooks-target="log"]')?.append(line);
});

/*
 * The controller only anchors the data-custom-hooks-target attributes the
 * module-level listener queries; it has no behavior of its own.
 */
export default class extends Controller {
    static targets = ['toggle', 'log'];
}
