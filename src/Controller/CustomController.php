<?php

declare(strict_types=1);

namespace App\Controller;

use MarilenaRM\TurboToastBundle\Controller\TurboToastTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * US-006: a manually-written toast container demonstrating the two
 * cookie-rendering customization hooks (template target + cancelable
 * `:append` event), both proven XSS-inert against a script-bearing message.
 */
final class CustomController extends AbstractController
{
    use TurboToastTrait;

    private const array ALLOWED_TYPES = ['success', 'info', 'warning', 'error'];

    #[Route('/custom', name: 'app_custom', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('custom/index.html.twig');
    }

    #[Route('/custom/notify', name: 'app_custom_notify', methods: ['POST'])]
    public function notify(Request $request): Response
    {
        $message = $request->request->getString('message', "Custom toast <script>alert('xss')</script>");
        $type = $request->request->getString('type', 'success');
        if (!\in_array($type, self::ALLOWED_TYPES, true)) {
            $type = 'success';
        }

        $this->deferToast($message, $type);

        return $this->redirectToRoute('app_custom');
    }
}
