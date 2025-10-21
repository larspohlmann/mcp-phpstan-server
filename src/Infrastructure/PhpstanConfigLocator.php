<?php
declare(strict_types = 1);

namespace Mcp\PhpstanServer\Infrastructure;

final class PhpstanConfigLocator
{
    /** Try to find a phpstan config upwards from CWD. */
    public function locate(): ?string
    {
        $candidates = ['phpstan.neon', 'phpstan.neon.dist'];
        $dir = getcwd() ?: __DIR__;

        for ($i = 0; $i < 5; $i++) {
            foreach ($candidates as $file) {
                $path = $dir . DIRECTORY_SEPARATOR . $file;

                if (is_file($path)) {
                    return $path;
                }
            }

            $parent = dirname($dir);

            if ($parent === $dir) {
                break;
            }

            $dir = $parent;
        }

        return null;
    }
}
