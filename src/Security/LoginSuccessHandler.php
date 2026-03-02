<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $roles = $token->getRoleNames();

        if (in_array('ROLE_ADMIN', $roles, true)) {
<<<<<<< HEAD
            return new RedirectResponse($this->urlGenerator->generate('admin_stats'));
=======
            return new RedirectResponse($this->urlGenerator->generate('admin_dashboard'));
>>>>>>> c4d1c44b0746a7387dc28bd3111400a167bda2d9
        }

        if (in_array('ROLE_ARTISTE', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('artist_profile'));
        }

        if (in_array('ROLE_PARTICIPANT', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('participant_profile'));
        }

        return new RedirectResponse($this->urlGenerator->generate('home'));
    }
}
