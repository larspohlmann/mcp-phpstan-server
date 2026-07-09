<?php
declare(strict_types = 1);

namespace Mcp\PhpstanServer\Infrastructure;

final class Config
{
    public function __construct(
        private readonly ?string $phpstanPath,
        private readonly ?string $phpstanConfig,
        private readonly ?string $phpstanLevel,
        private readonly ?string $phpstanMemoryLimit,
    ) {}

    public static function fromEnvironment(string $configDir, PhpstanConfigLocator $locator): self
    {
        $fileCfg = [];
        $jsonPath = rtrim($configDir, '/\\') . '/config.json';

        if (is_file($jsonPath)) {
            $decoded = json_decode((string) file_get_contents($jsonPath), true);

            if (is_array($decoded)) {
                /** @var array{phpstanPath?:?string, phpstanConfig?:?string, phpstanLevel?:?string, phpstanMemoryLimit?:?string} $fileCfg */
                $fileCfg = $decoded;
            }
        }

        $phpstan = getenv('MCP_PHPSTAN_PATH') ?: ($fileCfg['phpstanPath'] ?? null);
        $config = getenv('MCP_PHPSTAN_CONFIG') ?: ($fileCfg['phpstanConfig'] ?? null) ?: $locator->locate();
        $level = getenv('MCP_PHPSTAN_LEVEL') ?: ($fileCfg['phpstanLevel'] ?? null);
        $memoryLimit = getenv('MCP_PHPSTAN_MEMORY_LIMIT') ?: ($fileCfg['phpstanMemoryLimit'] ?? null);

        return new self($phpstan ?: null, $config ?: null, $level ?: null, $memoryLimit ?: null);
    }

    public function phpstanPath(): string
    {
        // prefer vendor/bin/phpstan if present
        $vendorBin = getcwd() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phpstan';

        if (null === $this->phpstanPath && is_file($vendorBin) && is_executable($vendorBin)) {
            return $vendorBin;
        }

        return $this->phpstanPath ?? 'phpstan';
    }

    public function phpstanConfig(): ?string
    {
        return $this->phpstanConfig;
    }

    public function phpstanLevel(): ?string
    {
        return $this->phpstanLevel;
    }

    public function phpstanMemoryLimit(): ?string
    {
        return $this->phpstanMemoryLimit ?? '1G';
    }
}
