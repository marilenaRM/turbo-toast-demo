# turbo-toast-demo — Backlog

Demo application showcasing **every** feature of
[`marilenarm/turbo-toast-bundle`](https://github.com/marilenaRM/turbo-toast-bundle)
(v0.2.0): session-free flash/toast notifications for Symfony that keep pages
HTTP-cacheable.

**Stack**: PHP 8.3+, Symfony 7.x (`symfony/skeleton` + webapp pack), AssetMapper,
`symfony/ux-turbo`, `symfony/stimulus-bundle`, web profiler.
**Bundle source**: installed from the local path `../TurboToastBundle` via a
composer `path` repository (symlink, `@dev`).

**Demo domain (KISS)**: a small "task list" — enough CRUD to trigger every toast
scenario, no persistence beyond an in-memory/session-free store (JSON file or
static fixture repository; NEVER the PHP session — the whole point of the bundle
is to avoid it).

**Golden rule**: no controller may touch the session. The demo must stay fully
cacheable-friendly to illustrate the bundle's pitch.

---

## US-001 — Bootstrap the app with the bundle installed `[x]`

> Done: Symfony 7.4 skeleton + minimal packages (no doctrine), bundle from the
> `../TurboToastBundle` path repo (symlinked `@dev`), container in base layout,
> sessions disabled outright (`framework.session: false`) to enforce the pitch.

**Goal**: a running Symfony 7 app with the bundle wired.

**Scope**
- `composer create-project symfony/skeleton` + `composer require webapp` (or the
  minimal equivalent: twig, asset-mapper, stimulus-bundle, ux-turbo,
  web-profiler-bundle, monolog-bundle).
- composer `path` repository on `../TurboToastBundle`, then
  `composer require marilenarm/turbo-toast-bundle:@dev`.
- Base layout `templates/base.html.twig`: `{{ turbo_toast_container() }}` inside
  `<body>`, bundle stylesheet imported (AssetMapper path).
- Bundle config file `config/packages/marilena_rm_turbo_toast.yaml` present with
  the defaults spelled out (documented values).

**Acceptance criteria**
- `symfony server:start` (or `php -S` with the public/ router) serves a homepage.
- The rendered HTML contains the toast container div with
  `data-controller="marilenarm--turbo-toast--toast-container"`, `aria-live` and
  `data-turbo-permanent`.
- `bin/console debug:container marilena_rm_turbo_toast.renderer` shows the service
  and its `ToastRendererInterface` alias.

---

## US-002 — Turbo Stream toast on task creation (composed stream) `[x]`

> Done: `/` lists tasks from `JsonFileTaskRepository` (var/tasks.json, seeded
> with one locked fixture for US-003); POST `/tasks` returns the composed
> `task/create.stream.html.twig` (row append + toast partial include), with the
> same Accept guard as the bundle renderer; POST `/tasks/quick` uses the bare
> `$this->toast()` shortcut.

**Goal**: illustrate `toast()` and stream composition (the nominal AJAX flow).

**Scope**
- `/` lists tasks; a Turbo form adds a task (no full page reload).
- The controller returns a custom `*.stream.html.twig` composing: append of the
  task row into the list **and** the toast partial include (success type).
- A second action uses the bare `return $this->toast('Task added');` shortcut to
  show the one-liner (e.g. a "quick add" button).

**Acceptance criteria**
- Submitting the form appends the row and pops the toast, URL unchanged, no
  session cookie created (verify in devtools: no `PHPSESSID`).
- The toast auto-dismisses after the default delay and dismisses on click.

---

## US-003 — Toast variants: types, delays, multiples

**Goal**: illustrate `toasts()`, the type variants and per-toast delay.

**Scope**
- Task deletion returns `toasts(new Toast('Task deleted', 'warning'), new Toast('Undo is not implemented', 'info', 8000))`.
- A failing action (e.g. deleting a locked task) returns an `error` toast.

**Acceptance criteria**
- Both toasts stack visually in the container; the info one stays ~8s.
- Each type renders its own `toast--{type}` color and the error uses
  `role="alert"` (inspect DOM).

---

## US-004 — Cookie transport on a classic redirect (`deferToast()`)

**Goal**: illustrate the second transport — full-page redirect flows.

**Scope**
- A "profile" form posted with `data-turbo="false"` (deliberately non-Turbo):
  the controller calls `deferToast('Profile saved')` then `redirectToRoute()`.
- A "login-like" flow (fake, no security bundle): POST → `deferToast('Welcome back!')`
  → 303 redirect → landing page shows the toast on load.

**Acceptance criteria**
- After the redirect, the toast appears on the landing page; the `turbo_toast`
  cookie is visible on the redirect response (devtools) and **gone** after the
  toast renders.
- The redirect response carries `Cache-Control: private` (devtools) while the
  landing page keeps its normal cacheability.
- Doing browser back/forward does NOT replay the toast.

---

## US-005 — Playground: edge cases and observability

**Goal**: illustrate the hardening behaviors and the logging channel.

**Scope**: a `/playground` page with one button per scenario:
1. **Oversized toast** — `deferToast(str_repeat(...))` beyond 3.8 KB → nothing
   shown, a `turbo_toast` channel warning appears in the log/profiler.
2. **5xx discard** — `deferToast('Saved')` then throw → error page; after
   navigating back, no toast appears (and the warning is logged).
3. **Non-Turbo `toast()`** — a plain link (no turbo-stream Accept header) hitting
   an action that calls `toast()` → the `LogicException` with its actionable
   message is visible in the error page (dev mode), demonstrating the guard.
4. **Tampered cookie** — a JS button writing a forged `turbo_toast` cookie with
   an HTML payload, then `Turbo.visit()` → the message renders as inert text
   (XSS defused, live demo of `textContent`).

**Acceptance criteria**
- Each scenario is reproducible from the UI with a short explanation text.
- Scenarios 1 and 2 produce `turbo_toast` channel records visible in the
  profiler Logs panel.

---

## US-006 — Customization page: template hook + event hook

**Goal**: illustrate both cookie-rendering customization hooks.

**Scope**: a `/custom` page with its OWN container (manually written div —
document why: the Twig helper renders a childless div) demonstrating:
1. **Template hook** — a `<template>` target with custom markup (icon +
   `[data-toast-message]` slot) and custom CSS.
2. **Event hook** — a checkbox toggling a JS listener that cancels `:append` and
   renders through a homemade `console`-style notifier instead.

**Acceptance criteria**
- With the template active, deferred toasts render the custom markup (icon
  visible, `toast--{type}` class added on the root).
- With the event listener active, default rendering is skipped entirely and the
  custom notifier shows the message.
- Both paths render a `<script>`-looking message as plain text (XSS-safe).

---

## US-007 — Profiler tour

**Goal**: make the Turbo Toast profiler panel discoverable.

**Scope**
- Ensure every page of the demo shows the panel when toasts were emitted
  (toolbar icon + counts).
- On the playground, after the 5xx scenario, the toolbar shows the discarded
  count in red.
- A short "how to read this panel" section in the demo README (streams vs
  deferred, queued/transported/discarded).

**Acceptance criteria**
- Toolbar shows the toast count after US-002/003/004 flows.
- The panel lists messages per transport and the discarded counter increments
  on the 5xx scenario.

---

## US-008 — Demo README: feature ↔ URL map

**Goal**: the demo is self-explanatory for a newcomer.

**Scope**: README.md with: setup instructions (composer install, server start),
a table mapping every bundle feature to the demo URL that illustrates it, and
the golden rule (no session anywhere — how to verify with devtools).

**Acceptance criteria**
- Following the README from a clean checkout gets the demo running.
- Every bundle feature (both transports, variants, hooks, hardening behaviors,
  profiler, logging) appears in the mapping table with its URL.

---

## US-009 — `[review-debt]` Lock tasks.json writes against concurrent POSTs

`JsonFileTaskRepository::add()` does read-modify-write without `flock()`;
two near-simultaneous POSTs can lose a write. Trivial `flock()` around the
read+write would fix it. Demo-grade tolerance accepted in US-002 review.

---

## Out of scope
- Real authentication (security-bundle) — the "login" is a fake form.
- Database (doctrine) — a JSON-file or in-memory task store is enough.
- Production deployment concerns.
