<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/commandes')]
class CommandeController extends AbstractController
{
    #[Route('/mes-commandes', name: 'app_commande_my', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myOrders(CommandeRepository $commandeRepository): Response
    {
        return $this->render('commande/my_orders.html.twig', [
            'commandes' => $commandeRepository->findBy(['user' => $this->getUser()], ['dateCommande' => 'DESC']),
        ]);
    }

    #[Route('/admin', name: 'app_commande_index', methods: ['GET'])]
    public function index(Request $request, CommandeRepository $commandeRepository): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_ARTISTE')) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        [$search, $sort, $direction] = $this->validateFilters(
            $request,
            ['id', 'dateCommande', 'statut', 'total', 'client'],
            'dateCommande',
            'desc'
        );

        return $this->render('commande/index.html.twig', [
            'commandes' => $commandeRepository->findForAdminBySearchAndSort($search, $sort, $direction),
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    #[Route('/admin/{id}/approve', name: 'app_commande_approve', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function approve(Commande $commande, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('commande_approve'.$commande->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_commande_index');
        }

        if ($commande->getStatut() !== Commande::STATUT_EN_ATTENTE) {
            $this->addFlash('warning', 'Cette commande n est plus en attente.');
            return $this->redirectToRoute('app_commande_index');
        }

        $commande->setStatut(Commande::STATUT_ACCEPTEE);
        $entityManager->flush();
        $this->addFlash('success', sprintf('Commande #%d acceptee.', (int) $commande->getId()));

        return $this->redirectToRoute('app_commande_index');
    }

    #[Route('/admin/{id}/reject', name: 'app_commande_reject', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function reject(Commande $commande, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('commande_reject'.$commande->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_commande_index');
        }

        if ($commande->getStatut() !== Commande::STATUT_EN_ATTENTE) {
            $this->addFlash('warning', 'Cette commande n est plus en attente.');
            return $this->redirectToRoute('app_commande_index');
        }

        $commande->setStatut(Commande::STATUT_REFUSEE);
        $entityManager->flush();
        $this->addFlash('success', sprintf('Commande #%d refusee.', (int) $commande->getId()));

        return $this->redirectToRoute('app_commande_index');
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function validateFilters(
        Request $request,
        array $allowedSorts,
        string $defaultSort,
        string $defaultDirection
    ): array {
        $search = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', $defaultSort);
        $direction = strtolower((string) $request->query->get('direction', $defaultDirection));

        if ($search !== '' && (mb_strlen($search) > 100 || !preg_match('/^[\p{L}\p{N}\s._\-\'@]+$/u', $search))) {
            $this->addFlash('error', 'Recherche invalide. Utilisez uniquement lettres, chiffres, espaces et . _ - @ \'.');
            $search = '';
        }

        if (!in_array($sort, $allowedSorts, true)) {
            $this->addFlash('error', 'Champ de tri invalide.');
            $sort = $defaultSort;
        }

        if (!in_array($direction, ['asc', 'desc'], true)) {
            $this->addFlash('error', 'Direction de tri invalide.');
            $direction = $defaultDirection;
        }

        return [$search, $sort, $direction];
    }
}
