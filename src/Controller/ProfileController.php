<?php

declare(strict_types=1);

namespace App\Controller;

use MarilenaRM\TurboToastBundle\Controller\TurboToastTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Classic full-page redirect flows (profile save, fake login) demonstrating
 * `deferToast()`: the toast travels in the `turbo_toast` cookie instead of
 * a Turbo Stream response, since these flows never touch Turbo Streams.
 */
final class ProfileController extends AbstractController
{
    use TurboToastTrait;

    #[Route('/profile', name: 'app_profile', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('profile/index.html.twig');
    }

    #[Route('/profile', name: 'app_profile_save', methods: ['POST'])]
    public function save(): Response
    {
        // No persistence: this demo illustrates the cookie transport, not a
        // real profile feature. Deliberately not a Turbo request (the form
        // carries data-turbo="false"), hence deferToast() over toast().
        $this->deferToast('Profile saved');

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/login', name: 'app_login', methods: ['GET'])]
    public function login(): Response
    {
        return $this->render('profile/login.html.twig');
    }

    #[Route('/login', name: 'app_login_check', methods: ['POST'])]
    public function loginCheck(): Response
    {
        // Fake login: no security-bundle, no session, no credential check.
        $this->deferToast('Welcome back!');

        return $this->redirectToRoute('app_home', [], Response::HTTP_SEE_OTHER);
    }
}
