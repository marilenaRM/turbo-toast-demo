<?php

declare(strict_types=1);

namespace App\Task;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Stores tasks as JSON in var/tasks.json — no Doctrine, no session.
 *
 * Seeds two normal tasks and one locked task on first read (file missing),
 * the locked fixture being required by US-003 (task deletion).
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
        if (!is_file($this->filePath)) {
            $tasks = $this->fixtures();
            $this->write($tasks);

            return $tasks;
        }

        $contents = file_get_contents($this->filePath);
        if (false === $contents) {
            throw new \RuntimeException(sprintf('Could not read "%s".', $this->filePath));
        }

        $rows = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return array_map($this->hydrate(...), $rows);
    }

    public function add(Task $task): void
    {
        $tasks = $this->all();
        $tasks[] = $task;
        $this->write($tasks);
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
    private function write(array $tasks): void
    {
        $dir = \dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $rows = array_map(static fn (Task $task): array => [
            'id' => $task->id,
            'title' => $task->title,
            'createdAt' => $task->createdAt->format(DATE_ATOM),
            'locked' => $task->locked,
        ], $tasks);

        file_put_contents($this->filePath, json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}
