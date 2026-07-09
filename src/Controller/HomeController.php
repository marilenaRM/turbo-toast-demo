<?php

declare(strict_types=1);

namespace App\Controller;

use App\Task\JsonFileTaskRepository;
use App\Task\Task;
use MarilenaRM\TurboToastBundle\Controller\TurboToastTrait;
use MarilenaRM\TurboToastBundle\Toast\Toast;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Task list (`/`) plus Turbo endpoints to add tasks without a page reload.
 */
final class HomeController extends AbstractController
{
    use TurboToastTrait;

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(JsonFileTaskRepository $repository): Response
    {
        return $this->render('home/index.html.twig', [
            'tasks' => $repository->all(),
        ]);
    }

    #[Route('/tasks', name: 'app_task_create', methods: ['POST'])]
    public function create(Request $request, JsonFileTaskRepository $repository): Response
    {
        // Same guard as the bundle's ToastRenderer: a turbo-stream body served
        // to a client that did not ask for it would render as raw markup.
        if (!\in_array('text/vnd.turbo-stream.html', $request->getAcceptableContentTypes(), true)) {
            throw new \LogicException('This endpoint only answers Turbo Stream requests; submit the form through Turbo.');
        }

        $title = trim((string) $request->request->get('title', ''));

        if ('' === $title) {
            return $this->toast('Title cannot be empty', 'error');
        }

        $task = new Task(bin2hex(random_bytes(6)), $title, new \DateTimeImmutable());
        $repository->add($task);

        return $this->render('task/create.stream.html.twig', [
            'task' => $task,
        ], new Response(null, Response::HTTP_OK, ['Content-Type' => 'text/vnd.turbo-stream.html']));
    }

    #[Route('/tasks/quick', name: 'app_task_quick', methods: ['POST'])]
    public function quickCreate(Request $request, JsonFileTaskRepository $repository): Response
    {
        $title = trim((string) $request->request->get('title', ''));
        $task = new Task(bin2hex(random_bytes(6)), '' === $title ? 'Quick task' : $title, new \DateTimeImmutable());
        $repository->add($task);

        return $this->toast('Task added');
    }

    #[Route('/tasks/{id}/delete', name: 'app_task_delete', methods: ['POST'])]
    public function delete(string $id, JsonFileTaskRepository $repository): Response
    {
        $task = $repository->find($id);

        if (null === $task) {
            return $this->toast('Task not found', 'error');
        }

        if ($task->locked) {
            return $this->toast(sprintf('"%s" is locked and cannot be deleted', $task->title), 'error');
        }

        $repository->remove($id);

        $response = $this->toasts(
            new Toast('Task deleted', 'warning'),
            new Toast('Undo is not implemented', 'info', 8000),
        );

        // Compose the row-removal stream onto the toasts() response so the
        // list stays in sync while toasts() remains the star of the show.
        $response->setContent(
            $this->renderView('task/delete.stream.html.twig', ['task' => $task]) . $response->getContent()
        );

        return $response;
    }
}
