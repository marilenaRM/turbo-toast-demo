<?php

declare(strict_types=1);

namespace App\Task;

/**
 * A single task in the demo list.
 *
 * `locked` gates deletion (US-003): kept here now so that US-003 only needs
 * controller/template changes, not a data model change.
 */
final readonly class Task
{
    public function __construct(
        public string $id,
        public string $title,
        public \DateTimeImmutable $createdAt,
        public bool $locked = false,
    ) {
    }
}
