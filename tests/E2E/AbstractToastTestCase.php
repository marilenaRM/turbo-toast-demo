<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Kernel;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Base for the browser E2E suite: boots the app kernel in the test environment
 * and drives a real headless browser through Playwright PHP. These tests exist
 * to cover what curl cannot see — the client-side Stimulus/Turbo behaviour that
 * is the whole point of the bundle (auto-dismiss, cookie consumption on load,
 * XSS-inert rendering, cancelable events).
 *
 * Skipped automatically unless PLAYWRIGHT_E2E=1 (see the `e2e` compose service).
 */
abstract class AbstractToastTestCase extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel('test', (bool) ($options['debug'] ?? false));
    }
}
