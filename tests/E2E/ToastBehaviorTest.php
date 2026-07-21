<?php

declare(strict_types=1);

namespace App\Tests\E2E;

/**
 * Client-side behaviours of turbo-toast-bundle, driven through a real browser.
 * Each test targets something a curl-level check provably missed during the
 * demo build (see the README profiler notes and the delivery history).
 *
 * Harness note: playwright-symfony routes browser requests back to the kernel
 * in-process via Playwright's `route.fulfill()`, which does NOT propagate a
 * response `Set-Cookie` into the browser jar. So the *server sets the cookie on
 * a redirect* half of deferToast() is covered by curl (see the README), while
 * these tests exercise the *browser consumes the cookie on load* half by
 * seeding the cookie through the client API (exactly what the bundle reads).
 */
final class ToastBehaviorTest extends AbstractToastTestCase
{
    /**
     * The regression that curl could never catch: if the bundle's Stimulus
     * controllers are mis-identified they silently never connect, so the toast
     * appears (server-rendered stream) but never auto-dismisses. Asserting the
     * toast is gone after its delay proves the controller actually connected.
     * Also asserts the golden rule: the stream response sets no session cookie.
     */
    public function testStreamToastAppearsThenAutoDismisses(): void
    {
        $page = $this->visit('/');
        $page->click('form[action="/tasks/quick"] button[type="submit"]');

        $page->waitForSelector('#toasts .toast.toast--success', ['timeout' => 9000]);
        $this->assertSelectorTextContains('#toasts .toast', 'Task added');

        $response = $this->getLastResponse();
        self::assertNotNull($response);
        self::assertStringContainsString('text/vnd.turbo-stream.html', (string) $response->headers->get('Content-Type'));
        $cookieNames = array_map(static fn ($c) => $c->getName(), $response->headers->getCookies());
        self::assertNotContains('PHPSESSID', $cookieNames, 'A session cookie leaked — the demo must stay session-free.');

        // Default delay is 5000 ms; the Stimulus toast controller removes the node.
        $page->waitForSelector('#toasts .toast', ['state' => 'detached', 'timeout' => 9000]);
        $this->assertSelectorNotExists('#toasts .toast');
    }

    /**
     * The browser half of deferToast(): a queued cookie is consumed on the
     * landing page and cleared before rendering, so a Turbo cache restore
     * cannot replay it. (The server-sets-the-cookie-on-redirect half is
     * curl-verified — see the harness note above.)
     */
    public function testDeferredCookieIsConsumedAndClearedOnLanding(): void
    {
        $this->setCookie('turbo_toast', json_encode(
            [['message' => 'Profile saved', 'type' => 'success', 'delay' => 5000]],
            JSON_THROW_ON_ERROR,
        ));

        $page = $this->visit('/');
        $page->waitForSelector('#toasts .toast.toast--success', ['timeout' => 9000]);
        $this->assertSelectorTextContains('#toasts .toast', 'Profile saved');

        self::assertNull($this->getCookie('turbo_toast'), 'The cookie must be cleared on consumption so it never replays.');
    }

    /**
     * XSS hardening: the cookie is deliberately unsigned and client-writable.
     * A forged HTML payload must render as inert text (textContent), never as
     * live markup — no <img>/<script> node is created inside the toast.
     */
    public function testForgedCookiePayloadRendersAsInertText(): void
    {
        $this->setCookie('turbo_toast', json_encode(
            [['message' => '<img src=x onerror=alert(1)> pwned', 'type' => 'success', 'delay' => 8000]],
            JSON_THROW_ON_ERROR,
        ));

        $page = $this->visit('/');
        $page->waitForSelector('#toasts .toast', ['timeout' => 9000]);

        $this->assertSelectorNotExists('#toasts .toast img');
        $this->assertSelectorTextContains('#toasts .toast', '<img src=x onerror=alert(1)> pwned');
    }

    /**
     * Customization hook 2: with the checkbox ticked, a listener cancels the
     * container's :append event and routes the message into a console-style
     * notifier instead, so no default toast is rendered. A Turbo visit fires
     * turbo:load, which is when the container consumes the seeded cookie.
     */
    public function testAppendEventCancellationRoutesToConsoleNotifier(): void
    {
        $page = $this->visit('/custom');
        $page->click('[data-custom-hooks-target="toggle"]');

        $this->setCookie('turbo_toast', json_encode(
            [['message' => 'Routed to the console notifier', 'type' => 'info', 'delay' => 5000]],
            JSON_THROW_ON_ERROR,
        ));
        $page->evaluate('window.Turbo.visit(window.location.href, { action: "replace" })');

        $page->waitForSelector('.console-line', ['timeout' => 9000]);
        $this->assertSelectorExists('.console-line');
        $this->assertSelectorNotExists('#custom-toasts .custom-toast');
    }
}
