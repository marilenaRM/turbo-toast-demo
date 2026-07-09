<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Generic homepage controller (US-001 bootstrap).
 *
 * `/` will become the task list in US-002, hence the generic "Home" naming
 * kept here rather than a task-specific controller — no rename needed later.
 */
final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }
}
