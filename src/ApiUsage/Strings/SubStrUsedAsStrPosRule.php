<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Strings;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<BinaryOp>
 */
class SubStrUsedAsStrPosRule implements Rule
{
    private const SUBSTRING_FUNCTIONS = ['substr', 'mb_substr'];
    private const LENGTH_FUNCTIONS = ['strlen', 'mb_strlen'];
    private const CASE_FUNCTIONS = ['strtolower', 'strtoupper', 'mb_strtolower', 'mb_strtoupper'];

    public function getNodeType(): string
    {
        return BinaryOp::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof BinaryOp\Identical && !$node instanceof BinaryOp\Equal) {
            return [];
        }

        $leftAnalysis = $this->analyzeSide($node->left);
        $rightAnalysis = $this->analyzeSide($node->right);

        if ($leftAnalysis['type'] === 'substr' && $rightAnalysis['type'] === 'needle') {
            $substrInfo = $leftAnalysis;
            $needleInfo = $rightAnalysis;
        } elseif ($rightAnalysis['type'] === 'substr' && $leftAnalysis['type'] === 'needle') {
            $substrInfo = $rightAnalysis;
            $needleInfo = $leftAnalysis;
        } else {
            return [];
        }

        if ($substrInfo['case'] !== $needleInfo['case']) {
            return [];
        }

        if (!$this->areNodesEquivalent($substrInfo['needle'], $needleInfo['needle'])) {
            return [];
        }

        $isMb = str_starts_with($substrInfo['func'], 'mb_');
        $replacementFunc = $isMb ? 'mb_' : '';
        $replacementFunc .= $substrInfo['case'] ? 'stripos' : 'strpos';

        $haystackStr = $this->nodeToString($substrInfo['haystack']);
        $needleStr = $this->nodeToString($needleInfo['needle']);

        $message = sprintf(
            "Usage of '%s' can be replaced with '%s(%s, %s) === 0' (improves performance and readability).",
            $substrInfo['func'],
            $replacementFunc,
            $haystackStr,
            $needleStr
        );

        return [
            RuleErrorBuilder::message($message)
                ->identifier('string.substrUsedAsStrpos')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function analyzeSide(Node $node): array
    {
        $isCase = false;
        $originalNode = $node;
        $unwrappedNode = $node;

        if ($unwrappedNode instanceof FuncCall && $unwrappedNode->name instanceof Name && in_array($unwrappedNode->name->toString(), self::CASE_FUNCTIONS, true)) {
            $isCase = true;
            $unwrappedNode = $unwrappedNode->getArgs()[0]->value;
        }

        if ($unwrappedNode instanceof FuncCall && $unwrappedNode->name instanceof Name && in_array($unwrappedNode->name->toString(), self::SUBSTRING_FUNCTIONS, true)) {
            $args = $unwrappedNode->getArgs();
            if (count($args) === 3 && $this->isZero($args[1]->value)) {
                $lengthCall = $args[2]->value;
                if ($lengthCall instanceof FuncCall && $lengthCall->name instanceof Name && in_array($lengthCall->name->toString(), self::LENGTH_FUNCTIONS, true)) {
                    return [
                        'type' => 'substr',
                        'func' => $unwrappedNode->name->toString(),
                        'haystack' => $args[0]->value,
                        'needle' => $lengthCall->getArgs()[0]->value,
                        'case' => $isCase,
                    ];
                }
            }
        }

        return ['type' => 'needle', 'needle' => $unwrappedNode, 'case' => $isCase];
    }

    private function isZero(Node $node): bool
    {
        return $node instanceof LNumber && $node->value === 0;
    }

    private function areNodesEquivalent(?Node $a, ?Node $b): bool
    {
        if (!$a || !$b) {
            return false;
        }
        return $this->nodeToString($a) === $this->nodeToString($b);
    }

    private function nodeToString(Node $node): string
    {
        if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
            return '$' . $node->name;
        }
        if ($node instanceof FuncCall && $node->name instanceof Name) {
            $args = array_map(fn($arg) => $this->nodeToString($arg->value), $node->getArgs());
            return $node->name->toString() . '(' . implode(', ', $args) . ')';
        }
        return '...';
    }
}
