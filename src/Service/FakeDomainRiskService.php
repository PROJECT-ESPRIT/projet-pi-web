<?php

namespace App\Service;

use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Calls the Python ML script to get fake-domain risk for an email.
 * Output is plain text: "94 high" (percent and label). No JSON.
 */
final class FakeDomainRiskService
{
    private string $projectDir;
    private string $scriptPath;

    /** @var array<string, array{percent: int, label: string}> cache by domain per request */
    private array $cache = [];

    public function __construct(KernelInterface $kernel)
    {
        $this->projectDir = $kernel->getProjectDir();
        $pythonFile = $this->projectDir . \DIRECTORY_SEPARATOR . 'python' . \DIRECTORY_SEPARATOR . 'fake_domain_risk.py';
        $scriptsFile = $this->projectDir . \DIRECTORY_SEPARATOR . 'scripts' . \DIRECTORY_SEPARATOR . 'fake_domain_risk.py';
        $this->scriptPath = is_file($pythonFile) && is_readable($pythonFile)
            ? $pythonFile
            : $scriptsFile;
    }

    /**
     * Get fake-domain risk for the given email (or domain).
     *
     * @return array{percent: int, label: string}|null null if script missing or failed
     */
    public function getRisk(string $email): ?array
    {
        $domain = $this->extractDomain($email);
        if ($domain === '') {
            return null;
        }
        if (isset($this->cache[$domain])) {
            return $this->cache[$domain];
        }

        $result = $this->runScript($domain);
        $this->cache[$domain] = $result;

        return $result;
    }

    private function extractDomain(string $email): string
    {
        $email = trim($email);
        if ($email === '') {
            return '';
        }
        if (str_contains($email, '@')) {
            $parts = explode('@', $email, 2);
            $email = $parts[1] ?? '';
        }
        $email = explode('/', $email)[0];
        $email = explode('?', $email)[0];

        return strtolower($email);
    }

    /**
     * @return array{percent: int, label: string}|null
     */
    private function runScript(string $domain): ?array
    {
        if (!is_file($this->scriptPath) || !is_readable($this->scriptPath)) {
            return null;
        }

        $exe = str_starts_with(\PHP_OS, 'WIN') ? 'python' : 'python3';
        $cmd = [$exe, $this->scriptPath, '--domain', $domain];
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];
        $proc = @proc_open(
            $cmd,
            $descriptorSpec,
            $pipes,
            $this->projectDir,
            null,
            ['bypass_shell' => true]
        );

        if (!is_resource($proc)) {
            return null;
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        if ($stdout === false || $stdout === '') {
            return null;
        }

        $line = trim(explode("\n", $stdout)[0]);
        $parts = preg_split('/\s+/', $line, 2);
        if (count($parts) < 2) {
            return null;
        }

        $percent = (int) $parts[0];
        $label = in_array($parts[1], ['low', 'medium', 'high'], true) ? $parts[1] : 'low';
        $percent = max(0, min(100, $percent));

        return ['percent' => $percent, 'label' => $label];
    }
}
