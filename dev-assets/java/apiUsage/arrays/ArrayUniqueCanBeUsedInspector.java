package com.kalessil.phpStorm.phpInspectionsEA.inspectors.apiUsage.arrays;

import com.intellij.codeInspection.ProblemsHolder;
import com.intellij.psi.PsiElement;
import com.intellij.psi.PsiElementVisitor;
import com.jetbrains.php.lang.psi.elements.FunctionReference;
import com.kalessil.phpStorm.phpInspectionsEA.fixers.UseSuggestedReplacementFixer;
import com.kalessil.phpStorm.phpInspectionsEA.openApi.BasePhpElementVisitor;
import com.kalessil.phpStorm.phpInspectionsEA.openApi.BasePhpInspection;
import com.kalessil.phpStorm.phpInspectionsEA.openApi.PhpLanguageLevel;
import com.kalessil.phpStorm.phpInspectionsEA.utils.MessagesPresentationUtil;
import com.kalessil.phpStorm.phpInspectionsEA.utils.OpenapiTypesUtil;
import org.jetbrains.annotations.NotNull;

/*
 * This file is part of the Php Inspections (EA Extended) package.
 *
 * (c) Vladimir Reznichenko <kalessil@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

public class ArrayUniqueCanBeUsedInspector extends BasePhpInspection {
    private static final String messagePattern = "'%e%' would be more readable here (array_unique(...) was optimized in PHP 7.2-beta3+).";

    @NotNull
    @Override
    public String getShortName() {
        return "ArrayUniqueCanBeUsedInspection";
    }

    @NotNull
    @Override
    public String getDisplayName() {
        return "'array_unique(...)' can be used";
    }

    @Override
    @NotNull
    public PsiElementVisitor buildVisitor(@NotNull final ProblemsHolder holder, boolean isOnTheFly) {
        return new BasePhpElementVisitor() {
            @Override
            public void visitPhpFunctionCall(@NotNull FunctionReference reference) {
                if (PhpLanguageLevel.get(holder.getProject()).atLeast(PhpLanguageLevel.PHP720)) {
                    final String functionName = reference.getName();
                    if (functionName != null && functionName.equals("array_count_values")) {
                        final PsiElement[] arguments = reference.getParameters();
                        if (arguments.length == 1) {
                            final PsiElement context = reference.getParent().getParent();
                            if (OpenapiTypesUtil.isFunctionReference(context)) {
                                final String parentFunctionName = ((FunctionReference) context).getName();
                                if (parentFunctionName != null) {
                                    if (parentFunctionName.equals("array_keys")) {
                                        final String replacement = "array_values(array_unique(%a%))".replace("%a%", arguments[0].getText());
                                        holder.registerProblem(
                                                context,
                                                MessagesPresentationUtil.prefixWithEa(messagePattern.replace("%e%", replacement)),
                                                new ReplaceFix(replacement)
                                        );
                                    } else if (parentFunctionName.equals("count")) {
                                        final String replacement = "count(array_unique(%a%))".replace("%a%", arguments[0].getText());
                                        holder.registerProblem(
                                                context,
                                                MessagesPresentationUtil.prefixWithEa(messagePattern.replace("%e%", replacement)),
                                                new ReplaceFix(replacement)
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            }
        };
    }

    private static final class ReplaceFix extends UseSuggestedReplacementFixer {
        private static final String title = "Use array_unique(...) instead";

        @NotNull
        @Override
        public String getName() {
            return MessagesPresentationUtil.prefixWithEa(title);
        }

        ReplaceFix(@NotNull String expression) {
            super(expression);
        }
    }
}
