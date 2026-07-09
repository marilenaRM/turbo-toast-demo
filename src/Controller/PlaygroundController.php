<?php

declare(strict_types=1);

namespace App\Controller;

use MarilenaRM\TurboToastBundle\Controller\TurboToastTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Hardening scenarios from the bundle's threat model, each reproducible from
 * the UI: oversized deferred toast, 5xx discard, non-Turbo toast() guard.
 * The tampered-cookie scenario (4) is client-side only, see playground_controller.js.
 */
final class PlaygroundController extends AbstractController
{
    use TurboToastTrait;

    #[Route('/playground', name: 'app_playground', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('playground/index.html.twig');
    }

    #[Route('/playground/oversized', name: 'app_playground_oversized', methods: ['POST'])]
    public function oversized(): Response
    {
        // A single toast over the ~3.8 KB url-encoded budget: the bundle
        // sets no cookie at all and logs a "Dropped" warning.
        $this->deferToast('OVERSIZED '.str_repeat('x', 4000));

        return $this->redirectToRoute('app_playground');
    }

    #[Route('/playground/crash', name: 'app_playground_crash', methods: ['POST'])]
    public function crash(): Response
    {
        $this->deferToast('Saved');

        // The 500 response makes the subscriber drain and discard the
        // queued toast: a toast must never lie about a failed request.
        throw new \RuntimeException('Simulated crash after queueing a toast (US-005 scenario 2).');
    }

    #[Route('/playground/non-turbo-toast', name: 'app_playground_non_turbo', methods: ['GET'])]
    public function nonTurboToast(): Response
    {
        // Reached via a plain link (data-turbo="false"): the Accept header
        // lacks text/vnd.turbo-stream.html, so toast() throws LogicException.
        return $this->toast('You will never see me');
    }
}
