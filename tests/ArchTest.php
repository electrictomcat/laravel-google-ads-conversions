<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests;

use PhpToken;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class ArchTest extends TestCase
{
    public function test_it_will_not_use_debugging_functions(): void
    {
        $forbidden = ['dd', 'dump', 'ray'];
        $violations = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(__DIR__.'/../src')
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $tokens = array_values(array_filter(
                PhpToken::tokenize((string) file_get_contents($file->getPathname())),
                fn (PhpToken $token): bool => ! $token->isIgnorable()
            ));

            $count = count($tokens);
            for ($i = 0; $i < $count; $i++) {
                $token = $tokens[$i];

                if (! in_array($token->id, [T_STRING, T_NAME_FULLY_QUALIFIED], true)) {
                    continue;
                }

                $name = strtolower(ltrim($token->text, '\\'));
                if (! in_array($name, $forbidden, true)) {
                    continue;
                }

                $prev = $tokens[$i - 1] ?? null;
                $next = $tokens[$i + 1] ?? null;

                if (
                    $token->id === T_STRING
                    && $prev !== null
                    && in_array($prev->id, [
                        T_OBJECT_OPERATOR,
                        T_NULLSAFE_OBJECT_OPERATOR,
                        T_DOUBLE_COLON,
                        T_FUNCTION,
                        T_CONST,
                        T_NEW,
                    ], true)
                ) {
                    if ($prev->id === T_FUNCTION && ($tokens[$i - 2]->id ?? null) === T_USE) {
                        $violations[] = "{$file->getPathname()}:{$token->line} imports {$token->text}";
                    }

                    continue;
                }

                if ($next !== null && $next->text === '(') {
                    $violations[] = "{$file->getPathname()}:{$token->line} calls {$token->text}()";
                }
            }
        }

        $this->assertSame([], $violations, "Debugging functions must not be used:\n".implode("\n", $violations));
    }
}
