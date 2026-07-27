# turbo-toast-demo

Demo application for [`marilenarm/turbo-toast-bundle`](https://github.com/marilenaRM/turbo-toast-bundle)
(v0.2.0) — session-free flash/toast notifications for Symfony that keep pages
HTTP-cacheable. Every feature of the bundle is reachable from this app's UI.

A standalone presentation page — the pitch, a live toast demo, and the
hardening proof — is published via GitHub Pages:
<https://marilenarm.github.io/turbo-toast-demo/> (source: `docs/index.html`).

## Setup

Requirements: PHP >= 8.3, Composer, and the bundle source checked out as a
**sibling** of this project (the composer `path` repository points at
`../TurboToastBundle`).

```bash
git clone <this-repo> turbo-toast-demo
git clone https://github.com/marilenaRM/turbo-toast-bundle TurboToastBundle
cd turbo-toast-demo
composer install            # symlinks marilenarm/turbo-toast-bundle from ../TurboToastBundle
php bin/console asset-map:compile   # required with php -S (no AssetMapper dev middleware)
php -S localhost:8000 -t public
# or, with the Symfony CLI (serves assets without compiling): symfony server:start
```

Open <http://localhost:8000/>. Task storage is a JSON file (`var/tasks.json`),
seeded on first load — delete it to reset the demo. If you edit anything under
`assets/`, re-run `asset-map:compile`.

> **Gotcha:** the bundle ships no `symfony-ux` keyword, so Flex cannot sync its
> Stimulus controllers — they are wired by hand in `assets/controllers.json`
> with explicit `name` overrides. A `composer require`/`update` re-runs the UX
> synchronizer and silently drops those entries; toasts then render but never
> connect (no auto-dismiss, no cookie consumption, and curl can't see it).
> Restore them with `git checkout -- assets/controllers.json` — the Playwright
> suite below is what catches this regression.

### With Docker

Same prerequisite — the bundle checked out as a sibling `../TurboToastBundle`.
The container serves the app with [FrankenPHP](https://frankenphp.dev/), which
serves AssetMapper assets directly, so **no `asset-map:compile` step** is
needed.

```bash
docker compose up --build
```

Open <http://localhost:8000/>. The sibling bundle is bind-mounted at
`/TurboToastBundle` (where the `../TurboToastBundle` path repository resolves
from `/app`), Composer dependencies install on first boot into a named volume,
and `var/tasks.json` lives on the bind-mounted source. Still session-free: no
`PHPSESSID` on any response from the container either.

## The golden rule: no session, ever

The whole point of the bundle is that flash messages stop forcing a PHP
session (and its lock, and its `Cache-Control: private`). This demo enforces
it structurally: `framework.session: false` — no controller *can* touch a
session, and no Doctrine either (the task store is a JSON file behind a small
repository class).

Verify it yourself: open devtools → Application/Storage → Cookies. Whatever
flow you exercise, **no `PHPSESSID` cookie ever appears**. The only cookie you
will ever see is the short-lived `turbo_toast` cookie on a redirect response,
and it is gone as soon as the toast renders. (In dev you may also see
`sf_redirect`, an artifact of the web profiler, not of the app.)

## Feature ↔ URL map

| Bundle feature | Where to see it | What to do |
|---|---|---|
| `toast()` one-liner (Turbo Stream transport) | [/](http://localhost:8000/) | Click **Quick add** — the POST returns the bare `$this->toast('Task added')` stream. |
| Stream composition (row append **+** toast partial include) | [/](http://localhost:8000/) | Add a task with the form — one turbo-stream response appends the row *and* pops the toast, URL unchanged. |
| `toasts()` — multiple toasts, one response | [/](http://localhost:8000/) | Delete a task — `toasts(warning, info)` stack in the container. |
| Type variants `toast--{type}` + per-toast `delay` | [/](http://localhost:8000/) | Same deletion: warning (default 5 s) + info kept ~8 s (`delay: 8000`). |
| `role="alert"` on error toasts (a11y) | [/](http://localhost:8000/) | Delete the 🔒 locked task — the error toast interrupts screen readers; other types use `role="status"`. |
| `deferToast()` — cookie transport over a hard redirect | [/profile](http://localhost:8000/profile) | Submit the `data-turbo="false"` form: 302 + `Set-Cookie: turbo_toast`, toast pops on the next load. |
| `deferToast()` over a 303 + Turbo Drive visit | [/login](http://localhost:8000/login) | Submit the fake login: 303 → `/`, the toast pops on the landing page. |
| Redirect forced `Cache-Control: private` (cookie never enters a shared cache) | [/profile](http://localhost:8000/profile) | Devtools → Network: the redirect response carries `private`; landing pages keep their normal cacheability. |
| No replay on back/forward (cookie cleared *before* rendering) | [/login](http://localhost:8000/login) | Log in, then navigate back and forward — the toast does not reappear. |
| `turbo_toast_container()` Twig helper | every page | View source: `<div id="toasts" aria-live="polite" data-turbo-permanent data-controller="marilenarm--turbo-toast--toast-container" …>`. |
| Cookie size budget (~3.8 KB, graceful overflow) | [/playground](http://localhost:8000/playground) | Scenario 1: nothing shown, `turbo_toast` channel warning logged. |
| 5xx discard (a toast never lies) | [/playground](http://localhost:8000/playground) | Scenario 2: 500 page, no cookie set, warning logged; back → no toast. |
| Non-Turbo `toast()` guard (actionable `LogicException`) | [/playground](http://localhost:8000/playground) | Scenario 3: plain link → dev error page pointing you to `deferToast()`. |
| XSS-inert rendering (`textContent` only, type filtered) | [/playground](http://localhost:8000/playground) | Scenario 4: forge a cookie with an HTML payload — it renders as inert text, no alert. |
| Customization hook 1 — `<template>` target | [/custom](http://localhost:8000/custom) | Send a deferred toast: custom markup (icon + `[data-toast-message]`), `toast--{type}` on the root. |
| Customization hook 2 — cancelable `:append` event | [/custom](http://localhost:8000/custom) | Tick the checkbox first: default rendering is skipped, a console-style notifier takes over. |
| `turbo_toast` monolog channel | [/playground](http://localhost:8000/playground) | Scenarios 1 & 2, then profiler → **Logs**: `turbo_toast.WARNING` records ("Dropped …", "Discarded …"). |
| Profiler panel (per-transport traces + counters) | any flow above | See [Reading the profiler panel](#reading-the-profiler-panel). |
| Bundle stylesheet via AssetMapper | every page | `assets/styles/app.css` imports the bundle's `toast.css` (relative path — see the comment there). |

## Reading the profiler panel

In dev mode, a **Turbo Toast** panel appears in the Symfony profiler whenever a
request emitted toasts (toolbar icon with the total count). It traces the two
transports separately:

- **Rendered as Turbo Streams** — every toast that went through
  `toast()` / `toasts()` (i.e. the `ToastRenderer`). Try the *Quick add* button
  or a task deletion on `/`, then open the profiler of that POST request.
- **Deferred through the cookie** — toasts queued with `deferToast()`, with
  three counters: **queued** (pushed onto the stack), **transported** (actually
  serialized into the `turbo_toast` cookie) and **discarded**. Try the profile
  or login forms, then open the profiler of the *redirect* response.
- **Discarded turns the toolbar red**: run the playground's *5xx crash*
  scenario and open the profiler of the 500 response — queued 1, transported 0,
  discarded 1. Same panel after the *oversized toast* scenario: queued 1, but
  nothing transported (over the cookie budget, a warning is logged instead).

Two subtleties worth knowing:

- The composed stream on `POST /tasks` includes the toast **partial** directly
  in its template, bypassing the renderer — so it is deliberately *not* counted
  by the panel. The panel traces the PHP API, not raw template includes.
- The `turbo_toast` monolog channel records the cookie-budget and 5xx-discard
  warnings; they show up in the profiler **Logs** panel of the same requests.

## Browser end-to-end tests (Playwright PHP)

The bundle's whole value is client-side — toasts that auto-dismiss, a cookie
consumed on load, a forged payload rendered inert, a cancelable event. None of
that is visible to a curl-level check (during the build, a Stimulus identifier
mismatch let toasts render but never connect/auto-dismiss, and curl never saw
it). So the interesting behaviours are covered by a small
[Playwright PHP](https://github.com/playwright-php) suite in `tests/E2E/`,
driving a real headless browser against the kernel handled in-process.

```bash
# needs Node 20+ and the Playwright browsers:
vendor/bin/playwright-install --with-deps
PLAYWRIGHT_E2E=1 vendor/bin/phpunit --testsuite e2e

# …or fully containerised (Node + browsers baked into a dedicated image):
docker compose --profile e2e run --build --rm e2e
```

The suite asserts the Stimulus controller actually connects (the toast is gone
after its delay), the deferred cookie is consumed and cleared on landing, a
forged HTML payload renders as inert text, and the `:append` event is
cancelable. One deliberate gap: the *server sets the `turbo_toast` cookie on a
redirect* half of `deferToast()` can't be exercised in-process — Playwright's
`route.fulfill()` doesn't propagate a response `Set-Cookie` into the browser
jar — so that hop stays covered by the curl checks above, and the tests seed
the cookie through the client API to exercise the browser half.
