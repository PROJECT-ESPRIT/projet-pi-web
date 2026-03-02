<?php

namespace App\Controller\Api;

use App\Service\OnboardingChatbotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/onboarding')]
class GuestOnboardingChatbotController extends AbstractController
{
    #[Route('/guest-chat', name: 'api_onboarding_guest_chat', methods: ['POST'])]
    public function chat(Request $request, OnboardingChatbotService $chatbotService): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse([
                'error' => 'Payload JSON invalide.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $message = trim((string) ($payload['message'] ?? ''));
        if ($message === '') {
            return new JsonResponse([
                'error' => 'Le champ "message" est obligatoire.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $history = $payload['history'] ?? [];
        if (!is_array($history)) {
            return new JsonResponse([
                'error' => 'Le champ "history" doit etre un tableau.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $result = $chatbotService->generateGuestReply($message, $history);

        return new JsonResponse($result);
    }
}
