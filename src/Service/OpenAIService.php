<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class OpenAIService
{
    private HttpClientInterface $httpClient;
    private string $apiKey;
    private string $tempDir;

    public function __construct(HttpClientInterface $httpClient, ParameterBagInterface $parameterBag)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $parameterBag->get('openai_api_key');
        $this->tempDir = $parameterBag->get('kernel.project_dir') . '/tmp';
    }

    public function transcribeAudio(UploadedFile $audioFile): array
    {
        try {
            // Validate file type
            $allowedTypes = ['audio/wav', 'audio/mp3', 'audio/mpeg', 'audio/webm', 'audio/ogg'];
            if (!in_array($audioFile->getMimeType(), $allowedTypes)) {
                return [
                    'success' => false,
                    'error' => 'Invalid audio file type. Allowed types: wav, mp3, webm, ogg'
                ];
            }

            // Create temp directory if not exists
            if (!is_dir($this->tempDir)) {
                mkdir($this->tempDir, 0777, true);
            }

            // Save file temporarily
            $tempFilePath = $this->tempDir . '/' . uniqid('audio_', true) . '.' . $audioFile->getClientOriginalExtension();
            $audioFile->move($this->tempDir, basename($tempFilePath));

            try {
                // Send to OpenAI Whisper API
                $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/audio/transcriptions', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiKey,
                    ],
                    'data' => [
                        'file' => fopen($tempFilePath, 'r'),
                        'model' => 'whisper-1',
                        'language' => 'fr',
                        'response_format' => 'json',
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                $content = $response->toArray(false);

                // Clean up temp file
                if (file_exists($tempFilePath)) {
                    unlink($tempFilePath);
                }

                if ($statusCode === 200 && isset($content['text'])) {
                    return [
                        'success' => true,
                        'text' => trim($content['text'])
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => 'OpenAI API error: ' . ($content['error']['message'] ?? 'Unknown error')
                    ];
                }

            } catch (\Exception $e) {
                // Clean up temp file on error
                if (file_exists($tempFilePath)) {
                    unlink($tempFilePath);
                }

                return [
                    'success' => false,
                    'error' => 'Transcription failed: ' . $e->getMessage()
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'File processing failed: ' . $e->getMessage()
            ];
        }
    }
}
