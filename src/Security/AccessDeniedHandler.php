<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?RedirectResponse
    {
        if ($request->hasSession()) {
            $path = $request->getPathInfo();

            if (str_starts_with($path, '/admin')) {
                /** @phpstan-ignore-next-line FlashBag is provided by Symfony session implementation */
                $request->getSession()->getFlashBag()->add('info', 'Cette page est reservee a l administrateur.');
            } else {
                /** @phpstan-ignore-next-line FlashBag is provided by Symfony session implementation */
                $request->getSession()->getFlashBag()->add('info', 'Acces non autorise pour cette action.');
            }
        }

        return new RedirectResponse($this->urlGenerator->generate('home'));
    }
}
