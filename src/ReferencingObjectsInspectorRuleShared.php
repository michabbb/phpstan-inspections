<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\While_;
use PhpParser\Node\NullableType;
use PhpParser\Node\Identifier;
use PhpParser\Node\UnionType;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleErrorBuilder;

final class ReferencingObjectsInspectorRuleShared
{
    /**
     * @param Function_|ClassMethod $functionLike
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public static function processFunctionLike($functionLike, Scope $scope): array
    {
        $errors = [];

        foreach ($functionLike->params as $param) {
            if (!self::shouldAnalyzeParam($param)) {
                continue;
            }

            $typeNode = $param->type;
            if ($typeNode === null) {
                continue;
            }

            if (self::isSupportedScalarType($typeNode)) {
                continue;
            }

            if (self::hasDisqualifyingUsage($functionLike, $param)) {
                continue;
            }

            $paramName = is_string($param->var->name) ? $param->var->name : 'param';
            $errors[] = RuleErrorBuilder::message(
                'Objects are always passed by reference; please correct "& $' . $paramName . '".'
            )
                ->identifier(ReferencingObjectsInspectorRuleFunction::IDENTIFIER)
                ->line($param->getStartLine())
                ->build();
        }

        return $errors;
    }

    private static function shouldAnalyzeParam(Param $param): bool
    {
        if ($param->byRef !== true) {
            return false;
        }
        if ($param->default !== null) {
            return false;
        }
        return true;
    }

    /**
     * True if the entire declared type is among supported scalars: string|int|float|bool|array|mixed|iterable|null
     */
    private static function isSupportedScalarType(Node\NullableType|Node\UnionType|Node\Identifier|Node\Name|Node $type): bool
    {
        if ($type instanceof NullableType) {
            return self::isSupportedScalarType($type->type) || true; // nullability is allowed
        }
        if ($type instanceof UnionType) {
            foreach ($type->types as $inner) {
                if (!self::isSupportedScalarType($inner)) {
                    return false;
                }
            }
            return true;
        }

        if ($type instanceof Identifier) {
            $lower = strtolower($type->toString());
            return in_array($lower, ['string', 'int', 'float', 'bool', 'array', 'mixed', 'iterable', 'null'], true);
        }

        if ($type instanceof Name) {
            $lower = strtolower($type->toString());
            return in_array($lower, ['array', 'iterable'], true);
        }

        return false; // treat other cases as object-like
    }

    private static function hasDisqualifyingUsage(Function_|ClassMethod $functionLike, Param $param): bool
    {
        $paramName = is_string($param->var->name) ? $param->var->name : null;
        if ($paramName === null) {
            return false;
        }

        $finder = new NodeFinder();
        $vars = $finder->find($functionLike->stmts ?? [], static function (Node $n) use ($paramName): bool {
            return $n instanceof Variable && $n->name === $paramName;
        });

        foreach ($vars as $var) {
            $parent = $var->getAttribute('parent');
            if ($parent instanceof Assign && $parent->var === $var) {
                return true;
            }
            if ($parent instanceof AssignRef && $parent->var === $var) {
                return true;
            }

            if ($parent instanceof BooleanNot) {
                return true;
            }
            if ($parent instanceof BooleanAnd || $parent instanceof BooleanOr) {
                return true;
            }
            if (($parent instanceof If_ || $parent instanceof While_ || $parent instanceof Do_) && $parent->cond === $var) {
                return true;
            }
        }

        return false;
    }
}

