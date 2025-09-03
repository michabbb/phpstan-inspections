<?php
declare(strict_types=1);

namespace macropage\PHPStan\Inspections;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PhpParser\PrettyPrinter\Standard;

/**
 * Meldet doppelte (struktur-identische) Aufrufe in array-mapping Zuweisungen
 * innerhalb von Schleifen: $arr[$obj->id()] = $obj->id();
 */
final class DuplicatedCallInArrayMappingRule implements Rule
{
    public function getNodeType(): string
    {
        return Assign::class;
    }

    /** @param Assign $node */
    public function processNode(Node $node, Scope $scope): array
    {
        // Try PHPStan's built-in loop detection first
        if (method_exists($scope, 'isInLoop')) {
            if (!$scope->isInLoop()) {
                return [];
            }
        } else {
            // Fallback: don't use the isInsideLoop check for now
            // This means the rule will be more permissive but functional
        }

        if (!$node->var instanceof ArrayDimFetch) {
            return [];
        }

        $leftCalls  = $this->collectCalls($node->var);     // key-seite (inkl. var/dim)
        if ($leftCalls === []) {
            return [];
        }

        $rightCalls = $this->collectCalls($node->expr);    // value-seite

        foreach ($rightCalls as $rc) {
            foreach ($leftCalls as $lc) {
                if ($this->callsEquivalent($lc, $rc)) {
                    return [
                        RuleErrorBuilder::message('duplicated method/function call in array mapping; assign it to a local variable and reuse.')
                                        ->line($rc->getStartLine())
                                        ->build(),
                    ];
                }
            }
        }

        return [];
    }

    /** @return Node[] */
    private function collectCalls(?Node $node): array
    {
        if ($node === null) {
            return [];
        }

        $finder = new NodeFinder();

        // sowohl func-, method- als auch static-calls erfassen
        $calls = [
            ...$finder->findInstanceOf($node, FuncCall::class),
            ...$finder->findInstanceOf($node, MethodCall::class),
            ...$finder->findInstanceOf($node, StaticCall::class),
        ];

        // falls der knoten selbst ein call ist und oben nicht gefunden wurde
        if ($node instanceof FuncCall || $node instanceof MethodCall || $node instanceof StaticCall) {
            $calls[] = $node;
        }

        return $calls;
    }

    private function callsEquivalent(Node $a, Node $b): bool
    {
        if (get_class($a) !== get_class($b)) {
            return false;
        }

        // strukturvergleich via pretty print, attribute (linenr etc.) ignorieren
        $printer = new Standard();

        $normalize = static function (Node $n) use ($printer): string {
            $strip = static function (Node $node) use (&$strip): Node {
                $clone = clone $node;
                $clone->setAttributes([]);
                foreach ($clone->getSubNodeNames() as $name) {
                    $sub = $clone->$name;
                    if ($sub instanceof Node) {
                        $clone->$name = $strip($sub);
                    } elseif (is_array($sub)) {
                        foreach ($sub as $k => $v) {
                            if ($v instanceof Node) {
                                $sub[$k] = $strip($v);
                            }
                        }
                        $clone->$name = $sub;
                    }
                }
                return $clone;
            };
            
            try {
                return $printer->prettyPrintExpr($strip($n));
            } catch (\Throwable) {
                // Fallback: use simple string representation if pretty printing fails
                return get_class($n) . '_' . spl_object_id($n);
            }
        };

        return $normalize($a) === $normalize($b);
    }

    private function isInsideLoop(Node $node): bool
    {
        // Check if the node has loop parents by traversing up the AST
        $current = $node;
        while ($current && $parent = $current->getAttribute('parent')) {
            if ($parent instanceof Node\Stmt\For_ ||
                $parent instanceof Node\Stmt\Foreach_ ||
                $parent instanceof Node\Stmt\While_ ||
                $parent instanceof Node\Stmt\Do_) {
                return true;
            }
            $current = $parent;
        }
        return false;
    }
}
