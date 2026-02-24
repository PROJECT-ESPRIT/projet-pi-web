<?php

namespace App\Controller\Forum;

use App\Entity\Forum;
use App\Form\Forum\ForumType;
use App\Repository\ForumRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/forum')]
final class ForumController extends AbstractController
{
    #[Route(name: 'app_forum_index', methods: ['GET'])]
    public function index(Request $request, ForumRepository $forumRepository): Response
    {
        $search = $request->query->getString('search', '');
        $sortBy = $request->query->getString('sort', 'dateCreation');
        $order = $request->query->getString('order', 'DESC');

        $forums = $forumRepository->findBySearchAndSort($search, $sortBy, $order);
        
        return $this->render('forum/index.html.twig', [
            'forums' => $forums,
            'search' => $search,
            'sortBy' => $sortBy,
            'order' => $order,
        ]);
    }

    #[Route('/new', name: 'app_forum_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $forum = new Forum();
        $form = $this->createForm(ForumType::class, $forum);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($forum);
            $entityManager->flush();

            return $this->redirectToRoute('app_forum_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('forum/new.html.twig', [
            'forum' => $forum,
            'form' => $form,
        ]);
    }

    #[Route('/{id<\\d+>}', name: 'app_forum_show', methods: ['GET'])]
    public function show(Forum $forum): Response
    {
        return $this->render('forum/show.html.twig', [
            'forum' => $forum,
        ]);
    }

    #[Route('/{id<\\d+>}/edit', name: 'app_forum_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Forum $forum, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ForumType::class, $forum);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_forum_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('forum/edit.html.twig', [
            'forum' => $forum,
            'form' => $form,
        ]);
    }

    #[Route('/{id<\\d+>}', name: 'app_forum_delete', methods: ['POST'])]
    public function delete(Request $request, Forum $forum, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$forum->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($forum);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_forum_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id<\d+>}/pdf', name: 'post_pdf', methods: ['GET'])]
    public function generatePdf(Forum $forum): Response
    {
        try {
            // Configure Dompdf
            $options = new Options();
            $options->set('defaultFont', 'Arial');
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isPhpEnabled', true);
            
            $dompdf = new Dompdf($options);
            
            // Generate HTML content
            $html = $this->renderView('forum/pdf.html.twig', [
                'forum' => $forum,
                'generationDate' => new \DateTimeImmutable(),
            ]);
            
            // Load HTML to Dompdf
            $dompdf->loadHtml($html);
            
            // Set paper size and orientation
            $dompdf->setPaper('A4', 'portrait');
            
            // Render the PDF
            $dompdf->render();
            
            // Generate filename
            $filename = 'forum_post_' . $forum->getId() . '_' . date('Y-m-d') . '.pdf';
            
            // Create response with proper headers
            $response = new Response($dompdf->output());
            
            // Set headers for PDF download
            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $response->headers->set('Content-Length', strlen($dompdf->output()));
            $response->headers->set('Cache-Control', 'private, max-age=0, must-revalidate');
            $response->headers->set('Pragma', 'public');
            
            return $response;
            
        } catch (\Exception $e) {
            // Log error and return error page
            error_log('PDF Generation Error: ' . $e->getMessage());
            
            return $this->render('forum/pdf_error.html.twig', [
                'forum' => $forum,
                'error' => $e->getMessage(),
            ], new Response(null, 500));
        }
    }
}
