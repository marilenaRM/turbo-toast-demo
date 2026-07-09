# turbo-toast-demo

Demo application for `marilenarm/turbo-toast-bundle` — session-free flash/toast
notifications for Symfony that keep pages HTTP-cacheable.

## Context

- The bundle source lives at `../TurboToastBundle` (installed via composer
  `path` repository, symlinked, `@dev`). Its README and `docs/hardening.md`
  are the reference for every behavior this demo illustrates.
- The work plan is `BACKLOG.md` — implement the US in order (US-001 → US-008).
  Each US has acceptance criteria; verify them before moving on.

## Hard rules

- **No controller may touch the PHP session** (`$request->getSession()`,
  `addFlash()`, security-bundle…). The demo exists to prove the session-free
  claim; one session access breaks the pitch. No doctrine either — task
  storage is a JSON file in `var/` behind a small repository class.
- PHP 8.3+, Symfony 7.x, PSR-12, strict types, readonly properties.
- Code, comments, commits in English.
- KISS: this is a demo, not a product. No abstraction beyond what the backlog
  asks. Tests are NOT required for demo code (the bundle has its own suite);
  the acceptance criteria are verified by running the app.

## Verifying

- Run with `symfony server:start` or `php -S localhost:8000 -t public`.
- "No session" check: devtools → no `PHPSESSID` cookie on any flow.
- The bundle's Stimulus controllers are auto-registered by Flex/AssetMapper;
  identifiers are namespaced (`marilenarm--turbo-toast--toast`,
  `marilenarm--turbo-toast--toast-container`).
