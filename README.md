# turbo-toast-demo

Demo application for [`marilenarm/turbo-toast-bundle`](https://github.com/marilenaRM/turbo-toast-bundle)
(v0.2.0) — session-free flash/toast notifications for Symfony that keep pages
HTTP-cacheable. Every feature of the bundle is reachable from this app's UI.

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
