<?php

namespace App\Controller;

use App\Entity\ForumReponse;
use App\Form\ForumReponseType;
use App\Repository\ForumReponseRepository;
use App\Service\ForumMailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/forum/reponse')]
final class ForumReponseController extends AbstractController
{
    #[Route(name: 'app_forum_reponse_index', methods: ['GET'])]
    public function index(Request $request, ForumReponseRepository $forumReponseRepository): Response
    {
        // Récupérer les paramètres de recherche et tri
        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sort', 'dateReponse');
        $order = $request->query->get('order', 'DESC');

        $allowedSortFields = [
            'dateReponse' => 'dateReponse',
        ];

        $sortBy = $allowedSortFields[$sortBy] ?? 'dateReponse';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        
        // Créer la requête avec recherche et tri
        $queryBuilder = $forumReponseRepository->createQueryBuilder('fr')
            ->leftJoin('fr.forum', 'f')
            ->leftJoin('fr.auteur', 'a');
        
        // Recherche
        if ($search) {
            $queryBuilder->where('fr.contenu LIKE :search OR f.sujet LIKE :search OR a.nom LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        
        // Tri
        $queryBuilder->orderBy('fr.' . $sortBy, $order);
        
        $forumReponses = $queryBuilder->getQuery()->getResult();
        
        return $this->render('forum_reponse/index.html.twig', [
            'forum_reponses' => $forumReponses,
            'search' => $search,
            'sortBy' => $sortBy,
            'order' => $order,
        ]);
    }

    #[Route('/new', name: 'app_forum_reponse_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ForumMailService $forumMailService): Response
    {
        $forumReponse = new ForumReponse();
        $form = $this->createForm(ForumReponseType::class, $forumReponse);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Sauvegarde du commentaire en base
            $entityManager->persist($forumReponse);
            $entityManager->flush();
            
            // Envoi automatique de l'email de notification après flush()
            try {
                $emailSent = $forumMailService->sendDynamicCommentNotification($forumReponse);
                
                if ($emailSent) {
                    $this->addFlash('success', 'Commentaire ajouté avec succès et notification envoyée à l\'auteur du post !');
                } else {
                    $this->addFlash('warning', 'Commentaire ajouté avec succès mais l\'envoi de la notification a échoué.');
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Commentaire ajouté avec succès mais une erreur est survenue lors de l\'envoi de la notification: ' . $e->getMessage());
            }

            return $this->redirectToRoute('app_forum_reponse_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('forum_reponse/new.html.twig', [
            'forum_reponse' => $forumReponse,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_forum_reponse_show', methods: ['GET'])]
    public function show(ForumReponse $forumReponse): Response
    {
        return $this->render('forum_reponse/show.html.twig', [
            'forum_reponse' => $forumReponse,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_forum_reponse_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ForumReponse $forumReponse, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ForumReponseType::class, $forumReponse);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // Ajouter un message flash
            $this->addFlash('success', 'Réponse modifiée avec succès !');

            // Rediriger vers la page index du forum avec les paramètres actuels
            $search = $request->query->getString('search', '');
            $sortBy = $request->query->getString('sort', 'dateCreation');
            $order = $request->query->getString('order', 'DESC');
            
            $params = [];
            if ($search) $params['search'] = $search;
            if ($sortBy) $params['sort'] = $sortBy;
            if ($order) $params['order'] = $order;

            return $this->redirectToRoute('app_forum_index', $params);
        }

        return $this->render('forum_reponse/edit.html.twig', [
            'forum_reponse' => $forumReponse,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_forum_reponse_delete', methods: ['POST'])]
    public function delete(Request $request, ForumReponse $forumReponse, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$forumReponse->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($forumReponse);
            $entityManager->flush();
            
            // Ajouter un message flash
            $this->addFlash('success', 'Réponse supprimée avec succès !');
            
            // Rediriger vers la page index du forum avec les paramètres actuels
            $search = $request->query->getString('search', '');
            $sortBy = $request->query->getString('sort', 'dateCreation');
            $order = $request->query->getString('order', 'DESC');
            
            $params = [];
            if ($search) $params['search'] = $search;
            if ($sortBy) $params['sort'] = $sortBy;
            if ($order) $params['order'] = $order;

            return $this->redirectToRoute('app_forum_index', $params);
        }

        return $this->redirectToRoute('app_forum_index');
    }
}
