<?php

namespace App\Controller;

use App\Entity\Charity;
use App\Entity\User;
use App\Repository\FavoriteCharityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/favorite/charity')]
class FavoriteCharityController extends AbstractController
{
    #[Route('/{id}/toggle', name: 'app_favorite_charity_toggle', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function toggle(Charity $charity, FavoriteCharityRepository $repo, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($repo->isFavorite($user, $charity)) {
            $repo->removeFavorite($user, $charity);
            $favored = false;
        } else {
            $repo->addFavorite($user, $charity);
            $favored = true;
        }

        if ($request->isXmlHttpRequest() || $request->getPreferredFormat() === 'json') {
            return new JsonResponse([
                'favored' => $favored,
                'count' => $repo->countForCharity($charity),
            ]);
        }

        $this->addFlash('success', $favored ? 'Cause ajoutée aux favoris.' : 'Cause retirée des favoris.');
        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_charity_index'));
    }

    #[Route('/mine', name: 'app_favorite_charity_mine', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function mine(FavoriteCharityRepository $repo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('favorite_charity/mine.html.twig', [
            'charities' => $repo->findCharitiesByUser($user),
        ]);
    }
}
