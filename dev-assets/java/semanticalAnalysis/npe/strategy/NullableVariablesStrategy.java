package com.kalessil.phpStorm.phpInspectionsEA.inspectors.semanticalAnalysis.npe.strategy;

import com.intellij.codeInspection.ProblemsHolder;
import com.intellij.openapi.project.Project;
import com.intellij.openapi.util.Condition;
import com.intellij.psi.PsiElement;
import com.intellij.psi.tree.IElementType;
import com.intellij.psi.util.PsiTreeUtil;
import com.jetbrains.php.lang.documentation.phpdoc.psi.PhpDocComment;
import com.jetbrains.php.lang.documentation.phpdoc.psi.PhpDocType;
import com.jetbrains.php.lang.documentation.phpdoc.psi.PhpDocVariable;
import com.jetbrains.php.lang.documentation.phpdoc.psi.tags.PhpDocTag;
import com.jetbrains.php.lang.lexer.PhpTokenTypes;
import com.jetbrains.php.lang.psi.elements.*;
import com.jetbrains.php.lang.psi.resolve.types.PhpType;
import com.kalessil.phpStorm.phpInspectionsEA.utils.*;
import org.jetbrains.annotations.NotNull;
import org.jetbrains.annotations.Nullable;

import java.util.*;
import java.util.stream.Collectors;

/*
 * This file is part of the Php Inspections (EA Extended) package.
 *
 * (c) Vladimir Reznichenko <kalessil@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

final public class NullableVariablesStrategy {
    private static final String message = "Null pointer exception may occur here.";

    private static final Set<String> objectTypes = new HashSet<>();
    static {
        objectTypes.add(Types.strSelf);
        objectTypes.add(Types.strStatic);
        objectTypes.add(Types.strObject);
    }

    final private static Condition<PsiElement> PARENT_FUNCTION = new Condition<PsiElement>() {
        public boolean value(PsiElement element) { return element instanceof Function; }
        public String toString()                 { return "Condition.PARENT_FUNCTION"; }
    };

    public static void applyToLocalVariables(@NotNull Function function, @NotNull ProblemsHolder holder) {
        final GroupStatement body = ExpressionSemanticUtil.getGroupStatement(function);
        if (body != null) {
            /* group variables assignments, except parameters */
            final Set<String> parameters = Arrays.stream(function.getParameters()).map(Parameter::getName).collect(Collectors.toSet());
            final Map<String, List<AssignmentExpression>> assignments = new HashMap<>();
            for (final Variable variable : PsiTreeUtil.findChildrenOfType(body, Variable.class)) {
                final String variableName = variable.getName();
                final PsiElement parent   = variable.getParent();
                if (parent instanceof AssignmentExpression && !parameters.contains(variableName)) {
                    final AssignmentExpression assignment = (AssignmentExpression) parent;
                    if (assignment.getVariable() == variable && OpenapiTypesUtil.isStatementImpl(assignment.getParent())) {
                        /* skip unsupported assignments */
                        final PsiElement value = assignment.getValue(); /* TODO: strict method reference type check */
                        if (value instanceof FieldReference || value instanceof UnaryExpression) {
                            continue;
                        }
                        /* pick up the assignment */
                        assignments.computeIfAbsent(variableName, v -> new ArrayList<>()).add(assignment);
                    }
                }
            }

            /* check if the variable has been written only once, inspect when null/void values are possible */
            final Project project           = holder.getProject();
            final Set<PsiElement> processed = new HashSet<>();
            for (final Map.Entry<String, List<AssignmentExpression>> pair : assignments.entrySet()) {
                final List<AssignmentExpression> variableAssignments = pair.getValue();
                if (!variableAssignments.isEmpty()) {
                    final AssignmentExpression assignment = variableAssignments.get(0);
                    if (isNullableResult(assignment, project)) {
                        /* find first nullable assignments, invoke analyzing statements after it */
                        apply(pair.getKey(), assignment, body, holder, processed);
                    }
                    variableAssignments.clear();
                }
            }
            processed.clear();
            assignments.clear();
        }
    }

    private static boolean isNullableResult(@NotNull AssignmentExpression assignment, @NotNull Project project) {
        boolean result                   = false;
        final PsiElement assignmentValue = assignment.getValue();
        /* primary strategy: resolve types and check nullability */
        if (assignmentValue instanceof PhpTypedElement) {
            final PhpType resolved = OpenapiResolveUtil.resolveType((PhpTypedElement) assignmentValue, project);
            if (resolved != null) {
                final Set<String> types = new HashSet<>();
                resolved.filterUnknown().getTypes().forEach(t -> types.add(Types.getType(t)));
                if (types.contains(Types.strNull) || types.contains(Types.strVoid)) {
                    types.remove(Types.strNull);
                    types.remove(Types.strVoid);
                    if (!types.isEmpty()) {
                        result = types.stream().noneMatch(t -> !t.startsWith("\\") && !objectTypes.contains(t));
                    }
                }
                types.clear();
            }
        }
        /* secondary strategy: support type specification with `@var <type> <variable>` */
        if (result) {
            final PhpPsiElement variable = assignment.getVariable();
            final PsiElement parent      = assignment.getParent();
            if (variable != null && OpenapiTypesUtil.isStatementImpl(parent) && OpenapiTypesUtil.isAssignment(assignment)) {
                final PsiElement previous = ((PhpPsiElement) parent).getPrevPsiSibling();
                if (previous instanceof PhpDocComment) {
                    final PhpDocTag[] hints = ((PhpDocComment) previous).getTagElementsByName("@var");
                    if (hints.length == 1) {
                        final PhpDocVariable specifiedVariable = PsiTreeUtil.findChildOfType(hints[0], PhpDocVariable.class);
                        if (specifiedVariable != null && specifiedVariable.getName().equals(variable.getName())) {
                            result = Arrays.stream(hints[0].getChildren())
                                .anyMatch(t -> t instanceof PhpDocType && Types.getType(t.getText()).equals(Types.strNull));
                        }
                    }
                }
            }
        }
        return result;
    }

    public static void applyToParameters(@NotNull Function function, @NotNull ProblemsHolder holder) {
        final GroupStatement body = ExpressionSemanticUtil.getGroupStatement(function);
        if (body != null) {
            final Set<PsiElement> processed = new HashSet<>();
            for (final Parameter parameter : function.getParameters()) {
                final Set<String> declaredTypes = new HashSet<>();
                OpenapiResolveUtil.resolveDeclaredType(parameter).getTypes().forEach(t -> declaredTypes.add(Types.getType(t)));
                if (declaredTypes.contains(Types.strNull) || PhpLanguageUtil.isNull(parameter.getDefaultValue())) {
                    declaredTypes.remove(Types.strNull);

                    boolean isObject = !declaredTypes.isEmpty();
                    for (final String type : declaredTypes) {
                        if (!type.startsWith("\\") && !objectTypes.contains(type)) {
                            isObject = false;
                            break;
                        }
                    }

                    if (isObject) {
                        apply(parameter.getName(), null, body, holder, processed);
                    }
                }
                declaredTypes.clear();
            }
            processed.clear();
        }
    }

    private static void apply(
        @NotNull String variableName,
        @Nullable AssignmentExpression variableDeclaration,
        @NotNull GroupStatement body,
        @NotNull ProblemsHolder holder,
        @NotNull Set<PsiElement> processed
    ) {
        /* find variable usages, control flow is not our friend here */
        final Function function        = (Function) body.getParent();
        final List<Variable> variables = new ArrayList<>();
        PsiTreeUtil.findChildrenOfType(body, Variable.class).stream()
                .filter(variable  ->
                    variableName.equals(variable.getName()) && PsiTreeUtil.findFirstParent(variable, PARENT_FUNCTION) == function
                )
                .forEach(variable -> {
                    final PsiElement parent = variable.getParent();
                    if (parent instanceof AssignmentExpression) {
                        final List<Variable> currentUsages    = new ArrayList<>();
                        final AssignmentExpression assignment = (AssignmentExpression) parent;
                        PsiTreeUtil.findChildrenOfType(assignment.getValue(), Variable.class).stream()
                                .filter(v -> variableName.equals(v.getName()))
                                .forEach(currentUsages::add);
                        PsiTreeUtil.findChildrenOfType(assignment, Variable.class).stream()
                                .filter(v -> variableName.equals(v.getName()) && !currentUsages.contains(v))
                                .forEach(currentUsages::add);
                        variables.addAll(currentUsages);
                        currentUsages.clear();
                    } else {
                        PsiTreeUtil.findChildrenOfType(parent, Variable.class).stream()
                                .filter(v -> variableName.equals(v.getName()))
                                .forEach(variables::add);
                    }
                });
        /* analyze collected variable usages */
        final Project project                 = holder.getProject();
        boolean skipPerformed                 = false;
        final boolean skipToDeclarationNeeded = variableDeclaration != null;
        for (final Variable variable : variables) {
            final PsiElement parent = variable.getParent();

            /* for local variables we need to skip usages until assignment performed */
            if (skipToDeclarationNeeded && !skipPerformed) {
                skipPerformed = variableDeclaration == parent;
                continue;
            }

            final PsiElement grandParent = parent.getParent();

            /* instanceof, implicit null comparisons */
            if (parent instanceof BinaryExpression) {
                final BinaryExpression expression = (BinaryExpression) parent;
                final IElementType operation      = expression.getOperationType();
                if (PhpTokenTypes.kwINSTANCEOF == operation) {
                    return;
                }
                if (OpenapiTypesUtil.tsCOMPARE_EQUALITY_OPS.contains(operation)) {
                    final PsiElement secondOperand = OpenapiElementsUtil.getSecondOperand(expression, variable);
                    if (PhpLanguageUtil.isNull(secondOperand)) {
                        return;
                    }
                    continue;
                }
            }

            /* non-implicit null comparisons; `else if` here would change semantics */
            if (parent instanceof PhpEmpty || parent instanceof PhpIsset || ExpressionSemanticUtil.isUsedAsLogicalOperand(variable)) {
                return;
            }
            /* re-defined in catch-statements */
            else if (parent instanceof Catch) {
                return;
            }
            /* test and business logic assertions */
            else if (parent instanceof ParameterList && (isAssertion(grandParent) || isNull(grandParent))) {
                return;
            }

            /* show stoppers: overriding the variable; except the variable declarations of course */
            if (parent instanceof AssignmentExpression) {
                if (parent == variableDeclaration) {
                    continue;
                }

                final AssignmentExpression assignment = (AssignmentExpression) parent;
                final PsiElement candidate            = assignment.getVariable();
                if (candidate instanceof Variable && ((Variable) candidate).getName().equals(variableName)) {
                    if (!isNullableResult(assignment, project)) {
                        return;
                    }
                }
            }
            /* cases when NPE can be introduced: array access */
            else if (parent instanceof ArrayAccessExpression) {
                final PsiElement container = ((ArrayAccessExpression) parent).getValue();
                if (variable == container && processed.add(variable)) {
                    holder.registerProblem(
                            variable,
                            MessagesPresentationUtil.prefixWithEa(message)
                    );
                }
            }
            /* cases when NPE can be introduced: member reference */
            else if (parent instanceof MemberReference) {
                final MemberReference reference = (MemberReference) parent;
                final PsiElement operator       = OpenapiPsiSearchUtil.findResolutionOperator(reference);
                if (OpenapiTypesUtil.is(operator, PhpTokenTypes.ARROW) && ! OpenapiTypesUtil.isNullSafeMemberReferenceOperator(operator)) {
                    final PsiElement subject        = reference.getClassReference();
                    if (subject instanceof Variable && ((Variable) subject).getName().equals(variableName)) {
                        /* false-positives: `$variable->property ?? ...`, isset($variable->property), isset($variable->property[...]) */
                        if (reference instanceof FieldReference) {
                            PsiElement lastReference    = reference;
                            PsiElement referenceContext = reference;
                            while (referenceContext instanceof FieldReference || referenceContext instanceof ArrayAccessExpression) {
                                lastReference    = referenceContext;
                                referenceContext = referenceContext.getParent();
                            }
                            if (referenceContext instanceof BinaryExpression) {
                                final BinaryExpression binary = (BinaryExpression) referenceContext;
                                final boolean isCoalescing    = binary.getOperationType() == PhpTokenTypes.opCOALESCE;
                                if (isCoalescing && lastReference == binary.getLeftOperand()) {
                                    continue;
                                }
                            } else if (referenceContext instanceof PhpIsset) {
                                continue;
                            }
                        }
                        /* false-positive: `$variable = $variable->method(...)` */
                        else if (reference instanceof MethodReference && grandParent == variableDeclaration) {
                            continue;
                        }

                        if (processed.add(subject)) {
                            holder.registerProblem(
                                    subject,
                                    MessagesPresentationUtil.prefixWithEa(message)
                            );
                        }
                    }
                }
            }
            /* cases when NPE can be introduced: __invoke calls */
            else if (OpenapiTypesUtil.isFunctionReference(parent) && variable == parent.getFirstChild()) {
                if (processed.add(variable)) {
                    holder.registerProblem(
                            variable,
                            MessagesPresentationUtil.prefixWithEa(message)
                    );
                }
            }
            /* cases when NPE can be introduced: clone operator */
            else if (parent instanceof UnaryExpression) {
                if (OpenapiTypesUtil.is(((UnaryExpression) parent).getOperation(), PhpTokenTypes.kwCLONE) && processed.add(variable)) {
                    holder.registerProblem(
                            variable,
                            MessagesPresentationUtil.prefixWithEa(message)
                    );
                }
            }
            /* cases when null dispatched into to non-null parameter */
            else if (parent instanceof ParameterList && grandParent instanceof FunctionReference) {
                final FunctionReference reference = (FunctionReference) grandParent;
                final PsiElement resolved         = OpenapiResolveUtil.resolveReference(reference);
                if (resolved != null)  {
                    /* get the parameter definition */
                    final int position           = Arrays.asList(reference.getParameters()).indexOf(variable);
                    final Parameter[] parameters = ((Function) resolved).getParameters();
                    if (position >= parameters.length) {
                        continue;
                    }

                    /* lookup types, if no null declarations - report class-only declarations */
                    final Parameter parameter       = parameters[position];
                    final Set<String> declaredTypes = new HashSet<>();
                    OpenapiResolveUtil.resolveDeclaredType(parameter).getTypes().forEach(t -> declaredTypes.add(Types.getType(t)));
                    if (!declaredTypes.contains(Types.strNull) && !PhpLanguageUtil.isNull(parameter.getDefaultValue())) {
                        declaredTypes.remove(Types.strNull);

                        boolean isObject = !declaredTypes.isEmpty();
                        for (final String type : declaredTypes) {
                            if (!type.startsWith("\\") && !objectTypes.contains(type)) {
                                isObject = false;
                                break;
                            }
                        }
                        if (isObject && processed.add(variable)) {
                            holder.registerProblem(
                                    variable,
                                    MessagesPresentationUtil.prefixWithEa(message)
                            );
                        }
                    }
                    declaredTypes.clear();
                }
            }
        }
    }

    private static boolean isAssertion(@NotNull PsiElement reference) {
        boolean result = false;
        if (reference instanceof MethodReference) {
            final String methodName = ((MethodReference) reference).getName();
            if (methodName != null) {
                if (methodName.equals("assertNotNull") || methodName.equals("assertInstanceOf") ||
                    methodName.equals("notNull") || methodName.equals("isInstanceOf") ||
                    methodName.equals("isInstanceOfAny")
                ) {
                    /* PHPUnit, beberlei/assert and webmozart/assert assertions */
                    result = true;
                } else if (methodName.equals("that")) {
                    /* another beberlei/assert assertion: `Assert::that($g)->notNull()` */
                    PsiElement parent = reference.getParent();
                    while (parent instanceof MethodReference) {
                        final String parentMethodName = ((MethodReference) parent).getName();
                        if (parentMethodName != null && parentMethodName.equals("notNull")) {
                            result = true;
                            break;
                        }
                        parent = parent.getParent();
                    }
                }
            }
        }
        return result;
    }

    private static boolean isNull(@NotNull PsiElement reference) {
        boolean result = false;
        if (OpenapiTypesUtil.isFunctionReference(reference)) {
            final String functionName = ((FunctionReference) reference).getName();
            result                    = functionName != null && functionName.equals("is_null");
        }
        return result;
    }
}
