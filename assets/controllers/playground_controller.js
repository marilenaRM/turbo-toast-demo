import { Controller } from '@hotwired/stimulus';
import * as Turbo from '@hotwired/turbo';

/*
 * US-005 scenario 4: forges a `turbo_toast` cookie with an HTML payload,
 * then triggers a Turbo visit. The bundle's toast-container controller
 * reads the cookie and renders the message via `textContent`, so the
 * injected markup lands as inert text instead of executing.
 */
export default class extends Controller {
    forgeCookie() {
        const payload = [{
            message: '<img src=x onerror=alert(1)> forged!',
            type: 'success',
            delay: 8000,
        }];

        document.cookie = 'turbo_toast=' + encodeURIComponent(JSON.stringify(payload)) + '; path=/';

        Turbo.visit(window.location.href);
    }
}
