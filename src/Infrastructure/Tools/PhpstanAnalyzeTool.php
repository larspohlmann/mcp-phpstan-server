<?php
declare(strict_types = 1);

namespace Mcp\PhpstanServer\Infrastructure\Tools;

use Mcp\PhpstanServer\Domain\ToolInterface;
use Mcp\PhpstanServer\Domain\ToolResult;
use Mcp\PhpstanServer\Infrastructure\Config;
use Mcp\PhpstanServer\Infrastructure\ProcessRunner;

final class PhpstanAnalyzeTool implements ToolInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly ProcessRunner $runner,
    ) {}

    public function getName(): string
    {
        return 'phpstan_analyze';
    }

    public function getDescription(): string
    {
        return 'Run PHPStan analysis for one or more paths and return a readable report.';
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

        // PHPStan exits non-zero when errors are found; still parse JSON
        $text = $this->formatPhpstanJson($result['stdout'], $result['stderr']);
        $isError = $this->hasErrors($result['stdout']);

        return new ToolResult($text, $isError);
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

        // PHPStan JSON has fields: errors, totals, files
        $errors = (bool) ($data['errors'] ?? false);
        $totals = $data['totals'] ?? [];
        $fileErrors = (int) ($totals['file_errors'] ?? 0);

        return $errors || $fileErrors > 0;
    }

    private function formatPhpstanJson(string $json, string $stderr): string
    {
        $data = json_decode($json, true);

        if (!is_array($data)) {
            $combined = trim($json . "\n" . $stderr);

            return '' !== $combined ? $combined : 'No output from phpstan.';
        }

        $lines = [];

        $errorsFlag = (bool) ($data['errors'] ?? false);
        $totals = $data['totals'] ?? ['file_errors' => 0, 'errors' => 0];
        $lines[] = sprintf('PHPStan: file_errors=%d, global_errors=%d, hasErrors=%s',
            (int) ($totals['file_errors'] ?? 0),
            (int) ($totals['errors'] ?? 0),
            $errorsFlag ? 'yes' : 'no'
        );

        $files = $data['files'] ?? [];

        foreach ($files as $file => $info) {
            $messages = $info['messages'] ?? [];

            if (!$messages) {
                continue;
            }

            $lines[] = '';
            $lines[] = $file;

            foreach ($messages as $m) {
                $line = (int) ($m['line'] ?? 0);
                $msg = (string) ($m['message'] ?? '');
                $ignorable = $m['ignorable'] ?? null;
                $identifier = (string) ($m['identifier'] ?? '');
                $lines[] = sprintf('  L%-4d %s%s%s',
                    $line,
                    $msg,
                    $identifier !== '' ? ' [' . $identifier . ']' : '',
                    $ignorable ? ' (ignorable)' : ''
                );
            }
        }

        // include non-file specific errors if present
        if (!empty($data['errors'])) {
            $lines[] = '';
            $lines[] = 'Global errors:';
            foreach ((array) $data['errors'] as $e) {
                if (is_string($e)) {
                    $lines[] = '  ' . $e;
                }
            }
        }

        return implode("\n", $lines);
    }
}
