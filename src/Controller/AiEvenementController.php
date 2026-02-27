<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Exposes two JSON endpoints that delegate to the Python AI scripts:
 *   GET /ai/events/recommend   — personalized recommendations for the logged-in participant
 *   GET /ai/events/hotness     — hot-event ranking for admins
 */
#[Route('/ai/events')]
class AiEvenementController extends AbstractController
{
    /**
     * Returns personalized event recommendations for the currently logged-in participant.
     * Called via AJAX on the home page popup.
     */
    #[Route('/recommend', name: 'ai_events_recommend', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function recommend(): JsonResponse
    {
        $user = $this->getUser();
        if ($user === null) {
            return $this->json(['success' => false, 'error' => 'Not authenticated', 'recommendations' => []]);
        }

        $result = $this->runPythonScript('event_recommender.py', [
            '--user_id', (string) $user->getId(),
            '--limit',   '5',
        ]);

        return $this->json($result);
    }

    /**
     * Returns the hotness ranking of upcoming events.
     * Only accessible by admins.
     */
    #[Route('/hotness', name: 'ai_events_hotness', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function hotness(): JsonResponse
    {
        $result = $this->runPythonScript('event_hotness.py', ['--limit', '10']);

        return $this->json($result);
    }

    // -------------------------------------------------------------------------

    private function runPythonScript(string $scriptName, array $extraArgs = []): array
    {
        $scriptPath = $this->getParameter('kernel.project_dir') . '/python/' . $scriptName;

        if (!file_exists($scriptPath)) {
            return ['success' => false, 'error' => "Script not found: $scriptName"];
        }

        $python = $this->detectPython();
        $dbUrl  = $this->buildDbUrl();

        $cmd = array_merge(
            [$python, $scriptPath],
            $extraArgs,
            ['--db_url', $dbUrl],
        );

        $escapedCmd = implode(' ', array_map('escapeshellarg', $cmd));

        $output   = [];
        $exitCode = 0;
        exec($escapedCmd . ' 2>&1', $output, $exitCode);

        $raw = trim(implode("\n", $output));

        if ($raw === '') {
            return ['success' => false, 'error' => 'Script produced no output (exit ' . $exitCode . ')'];
        }

        $decoded = json_decode($raw, true);
        if ($decoded === null) {
            // Sanitize to valid UTF-8 so Symfony's JSON encoder never chokes
            $safe = mb_convert_encoding(substr($raw, 0, 300), 'UTF-8', 'UTF-8');
            if (!mb_check_encoding($safe, 'UTF-8')) {
                $safe = mb_convert_encoding($safe, 'UTF-8', 'Windows-1252');
            }
            return ['success' => false, 'error' => 'Invalid JSON from script: ' . $safe];
        }

        return $decoded;
    }

    private function detectPython(): string
    {
        // Prefer the known system Python on Windows — avoids the App Store stub
        $candidates = [
            'C:\\Python312\\python.exe',
            'C:\\Python311\\python.exe',
            'C:\\Python310\\python.exe',
            'C:\\Python39\\python.exe',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Fall back to PATH lookup — skip python3 on Windows (App Store stub)
        $lookups = PHP_OS_FAMILY === 'Windows' ? ['python', 'py'] : ['python3', 'python'];

        foreach ($lookups as $candidate) {
            $whereCmd = PHP_OS_FAMILY === 'Windows'
                ? 'where.exe ' . escapeshellarg($candidate) . ' 2>NUL'
                : 'which ' . escapeshellarg($candidate) . ' 2>/dev/null';

            $out  = [];
            $code = 0;
            exec($whereCmd, $out, $code);

            if ($code === 0 && !empty($out)) {
                $found = trim($out[0]);
                // Reject the Windows App Store stub
                if (str_contains($found, 'WindowsApps')) {
                    continue;
                }
                return $found;
            }
        }

        return 'python';
    }

    private function buildDbUrl(): string
    {
        $raw = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?? '';

        if ($raw === '') {
            return 'mysql+pymysql://root:@127.0.0.1:3306/projet_pi_web';
        }

        // Convert Doctrine DSN  mysql://user:pass@host:port/db?...
        // to SQLAlchemy DSN     mysql+pymysql://user:pass@host:port/db
        $url = preg_replace('#^mysql://#', 'mysql+pymysql://', $raw);
        // Strip Doctrine query params (serverVersion, charset…)
        $url = preg_replace('/\?.*$/', '', (string) $url);

        return $url;
    }
}
