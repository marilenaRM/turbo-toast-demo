<?php

declare(strict_types=1);

namespace App\Task;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Stores tasks as JSON in var/tasks.json — no Doctrine, no session.
 *
 * Seeds two normal tasks and one locked task on first read (file missing or
 * empty), the locked fixture being required by US-003 (task deletion).
 *
 * Every access runs under an exclusive flock() so concurrent requests cannot
 * lose a write: read-modify-write is atomic (US-009).
 */
final class JsonFileTaskRepository
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/tasks.json')]
        private readonly string $filePath,
    ) {
    }

    /**
     * @return Task[]
     */
    public function all(): array
    {
        return $this->withLockedFile(null);
    }

    public function add(Task $task): void
    {
        $this->withLockedFile(static function (array $tasks) use ($task): array {
            $tasks[] = $task;

            return $tasks;
        });
    }

    public function find(string $id): ?Task
    {
        foreach ($this->all() as $task) {
            if ($task->id === $id) {
                return $task;
            }
        }

        return null;
    }

    public function remove(string $id): void
    {
        $this->withLockedFile(static fn (array $tasks): array => array_values(array_filter(
            $tasks,
            static fn (Task $task): bool => $task->id !== $id,
        )));
    }

    /**
     * Reads the store under an exclusive lock, optionally applies a mutation,
     * and writes back whenever the content changed (mutation or first seed).
     *
     * @param (callable(Task[]): Task[])|null $mutator
     *
     * @return Task[]
     */
    private function withLockedFile(?callable $mutator): array
    {
        $dir = \dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $handle = fopen($this->filePath, 'c+');
        if (false === $handle) {
            throw new \RuntimeException(sprintf('Could not open "%s".', $this->filePath));
        }

        try {
            flock($handle, LOCK_EX);

            $contents = stream_get_contents($handle);
            $dirty = false;

            if (false === $contents || '' === trim($contents)) {
                $tasks = $this->fixtures();
                $dirty = true;
            } else {
                $rows = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                $tasks = array_map($this->hydrate(...), $rows);
            }

            if (null !== $mutator) {
                $tasks = $mutator($tasks);
                $dirty = true;
            }

            if ($dirty) {
                rewind($handle);
                ftruncate($handle, 0);
                fwrite($handle, $this->encode($tasks));
            }

            return $tasks;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return Task[]
     */
    private function fixtures(): array
    {
        $now = new \DateTimeImmutable();

        return [
            new Task(bin2hex(random_bytes(6)), 'Write the demo README', $now, false),
            new Task(bin2hex(random_bytes(6)), 'Wire the toast bundle', $now, false),
            new Task(bin2hex(random_bytes(6)), 'Ship the release', $now, true),
        ];
    }

    private function hydrate(array $row): Task
    {
        return new Task(
            $row['id'],
            $row['title'],
            new \DateTimeImmutable($row['createdAt']),
            $row['locked'],
        );
    }

    /**
     * @param Task[] $tasks
     */
    private function encode(array $tasks): string
    {
        $rows = array_map(static fn (Task $task): array => [
            'id' => $task->id,
            'title' => $task->title,
            'createdAt' => $task->createdAt->format(DATE_ATOM),
            'locked' => $task->locked,
        ], $tasks);

        return json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
