<?php
declare(strict_types = 1);

namespace Mcp\PhpstanServer\Infrastructure\Tools;

use Mcp\PhpstanServer\Domain\ToolInterface;
use Mcp\PhpstanServer\Domain\ToolResult;
use Mcp\PhpstanServer\Infrastructure\Config;
use Mcp\PhpstanServer\Infrastructure\ProcessRunner;

final class PhpstanProTool implements ToolInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly ProcessRunner $runner,
    ) {}

    public function getName(): string
    {
        return 'phpstan_pro';
    }

    public function getDescription(): string
    {
        return 'Run PHPStan with JSON output and return raw JSON (useful for advanced clients).';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'paths' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Array of absolute or project-relative paths to analyze',
                ],
                'level' => [
                    'type' => ['string', 'integer'],
                    'description' => 'Optional level to override configuration (e.g., "max" or 8)'
                ],
            ],
            'required' => ['paths'],
        ];
    }

    /**
     * @param array{paths?:array<int,string>,level?:string|int} $arguments
     */
    public function call(array $arguments): ToolResult
    {
        $paths = $arguments['paths'] ?? [];

        if (!\is_array($paths) || 0 === count($paths)) {
            return new ToolResult('Missing required argument: paths', true);
        }

        $paths = array_map(fn(string $p) => $this->normalizePath($p), $paths);

        $cmd = escapeshellcmd($this->config->phpstanPath());
        $cmd .= ' analyse --no-progress --error-format=json';

        $configFile = $this->config->phpstanConfig();
        if ($configFile) {
            $cmd .= ' -c ' . escapeshellarg($configFile);
        }

        $level = $arguments['level'] ?? $this->config->phpstanLevel();
        if (null !== $level && $level !== '') {
            $cmd .= ' -l ' . escapeshellarg((string) $level);
        }

        foreach ($paths as $p) {
            $cmd .= ' ' . escapeshellarg($p);
        }

        $result = $this->runner->run($cmd);

        $json = trim($result['stdout']);
        if ($json === '') {
            $json = json_encode(['stderr' => $result['stderr']], JSON_UNESCAPED_SLASHES);
        }

        $isError = $this->hasErrors($json);

        return new ToolResult($json, $isError);
    }

    private function normalizePath(string $path): string
    {
        if ($path === '') {
            return $path;
        }

        if ($path[0] === '.' || $path[0] === '/') {
            return realpath($path) ?: $path;
        }

        $full = getcwd() . DIRECTORY_SEPARATOR . $path;

        return realpath($full) ?: $path;
    }

    private function hasErrors(string $json): bool
    {
        $data = json_decode($json, true);

        if (!is_array($data)) {
            return false;
        }

        $errors = (bool) ($data['errors'] ?? false);
        $totals = $data['totals'] ?? [];
        $fileErrors = (int) ($totals['file_errors'] ?? 0);

        return $errors || $fileErrors > 0;
    }
}
