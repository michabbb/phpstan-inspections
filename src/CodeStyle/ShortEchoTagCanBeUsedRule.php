<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Stmt\Echo_;
use PhpParser\Node\Stmt\InlineHTML;
use PhpParser\Node\Expr\Print_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects when short echo tag ('<?= ... ?>') can be used instead of full echo/print statements.
 *
 * Following the exact Java original logic from ShortEchoTagCanBeUsedInspector.java:
 * Only flags echo/print statements that are surrounded by PHP opening and closing tags,
 * indicating a template context where short echo syntax would be appropriate.
 *
 * @implements Rule<Node>
 */
final class ShortEchoTagCanBeUsedRule implements Rule
{
    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {        
        if ($node instanceof Echo_) {
            return $this->analyzeEcho($node, $scope);
        }

        if ($node instanceof Print_) {
            return $this->analyzePrint($node, $scope);
        }

        return [];
    }

    private function analyzeEcho(Echo_ $echo, Scope $scope): array
    {
        // Following Java logic: only suggest for single expression echo statements
        if (count($echo->exprs) !== 1) {
            return [];
        }

        // Following Java logic: check for surrounding PHP tags
        if (!$this->isSurroundedByPhpTags($echo, $scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message("'<?= ... ?>' could be used instead.")
                ->identifier('echo.shortEchoTagCanBeUsed')
                ->line($echo->getStartLine())
                ->build(),
        ];
    }

    private function analyzePrint(Print_ $print, Scope $scope): array
    {
        // Following Java logic: check for surrounding PHP tags
        if (!$this->isSurroundedByPhpTags($print, $scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message("'<?= ... ?>' could be used instead.")
                ->identifier('echo.shortEchoTagCanBeUsed')
                ->line($print->getStartLine())
                ->build(),
        ];
    }

    /**
     * Following the exact Java logic: check if the echo/print statement
     * is surrounded by PHP opening and closing tags by analyzing source code.
     */
    private function isSurroundedByPhpTags(Node $node, Scope $scope): bool
    {
        $start = $node->getStartFilePos();
        $end = $node->getEndFilePos();
        if ($start === null || $end === null) {
            return false;
        }

        $fileName = $node->getAttribute('fileName')
            ?? $node->getAttribute('file')
            ?? $scope->getFile();

        $source = @file_get_contents($fileName);
        if ($source === false) {
            return false;
        }


        // Check for opening tag before the node (skip whitespace)
        $i = $start - 1;
        while ($i >= 0 && ctype_space($source[$i])) {
            $i--;
        }

        $openingFound = false;
        if ($i >= 4 && substr($source, $i - 4, 5) === '<?php') {
            $openingFound = true;
        } elseif ($i >= 1 && substr($source, $i - 1, 2) === '<?') {
            // Avoid matching <?= or <?xml
            $next = $source[$i + 1] ?? '';
            if ($next !== '=' && stripos(substr($source, $i - 1, 5), '<?xml') !== 0) {
                $openingFound = true;
            }
        }
        
        if (!$openingFound) {
            return false;
        }

        // Check for closing tag after the node (skip whitespace and semicolon)
        $j = $end + 1;
        $len = strlen($source);
        while ($j < $len && (ctype_space($source[$j]) || $source[$j] === ';')) {
            $j++;
        }
        $closingFound = substr($source, $j, 2) === '?>';

        return $closingFound;
    }
}