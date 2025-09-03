package com.kalessil.phpStorm.phpInspectionsEA.inspectors.magicMethods;

import com.intellij.codeInspection.ProblemHighlightType;
import com.intellij.codeInspection.ProblemsHolder;
import com.intellij.psi.PsiElement;
import com.intellij.psi.PsiElementVisitor;
import com.jetbrains.php.lang.psi.elements.Method;
import com.jetbrains.php.lang.psi.elements.PhpClass;
import com.jetbrains.php.lang.psi.resolve.types.PhpType;
import com.kalessil.phpStorm.phpInspectionsEA.inspectors.magicMethods.strategy.*;
import com.kalessil.phpStorm.phpInspectionsEA.openApi.BasePhpElementVisitor;
import com.kalessil.phpStorm.phpInspectionsEA.openApi.BasePhpInspection;
import com.kalessil.phpStorm.phpInspectionsEA.openApi.PhpLanguageLevel;
import com.kalessil.phpStorm.phpInspectionsEA.utils.MessagesPresentationUtil;
import com.kalessil.phpStorm.phpInspectionsEA.utils.NamedElementUtil;
import com.kalessil.phpStorm.phpInspectionsEA.utils.Types;
import org.jetbrains.annotations.NotNull;

import java.util.HashSet;
import java.util.Set;

/*
 * This file is part of the Php Inspections (EA Extended) package.
 *
 * (c) Vladimir Reznichenko <kalessil@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

public class MagicMethodsValidityInspector extends BasePhpInspection {
    private static final String messageUseSplAutoloading = "Has been deprecated in favour of 'spl_autoload_register(...)' as of PHP 7.2.0.";
    private static final String messageNotMagic          = "Only magic methods should start with '__'.";

    private static final PhpType arrayType         = (new PhpType()).add(PhpType.ARRAY);
    private static final PhpType stringType        = (new PhpType()).add(PhpType.STRING);
    private static final PhpType arrayOrNullType   = (new PhpType()).add(PhpType.NULL).add(PhpType.ARRAY);
    private static final Set<String> knownNonMagic = new HashSet<>();
    static {
        /* Magento & co */
        knownNonMagic.add("__inject");
        knownNonMagic.add("__prepare");
        knownNonMagic.add("__toArray");
        knownNonMagic.add("__");
        /* SoapClient */
        knownNonMagic.add("__doRequest");
        knownNonMagic.add("__getCookies");
        knownNonMagic.add("__getFunctions");
        knownNonMagic.add("__getLastRequest");
        knownNonMagic.add("__getLastRequestHeaders");
        knownNonMagic.add("__getLastResponse");
        knownNonMagic.add("__getLastResponseHeaders");
        knownNonMagic.add("__getTypes");
        knownNonMagic.add("__setCookie");
        knownNonMagic.add("__setLocation");
        knownNonMagic.add("__setSoapHeaders");
        knownNonMagic.add("__soapCall");
    }

    @NotNull
    @Override
    public String getShortName() {
        return "MagicMethodsValidityInspection";
    }

    @NotNull
    @Override
    public String getDisplayName() {
        return "Magic methods validity";
    }

    @Override
    @NotNull
    public PsiElementVisitor buildVisitor(@NotNull final ProblemsHolder holder, final boolean isOnTheFly) {
        return new BasePhpElementVisitor() {
            @Override
            public void visitPhpMethod(@NotNull Method method) {
                final PhpClass clazz            = method.getContainingClass();
                final String methodName         = method.getName();
                final PsiElement nameNode       = NamedElementUtil.getNameIdentifier(method);

                if (clazz == null || nameNode == null || !methodName.startsWith("_") || method.isAbstract()) {
                    return;
                }

                switch (methodName) {
                    case "__construct":
                        CanNotBeStaticStrategy.apply(method, holder);
                        CanNotReturnTypeStrategy.apply(method, holder);
                        if (!this.isTestContext(clazz)) {
                            NormallyCallsParentMethodStrategy.apply(method, holder);
                        }
                        break;
                    case "__destruct":
                    case "__clone":
                        CanNotBeStaticStrategy.apply(method, holder);
                        CanNotReturnTypeStrategy.apply(method, holder);
                        CanNotTakeArgumentsStrategy.apply(method, holder);
                        NormallyCallsParentMethodStrategy.apply(method, holder);
                        break;
                    case "__get":
                    case "__isset":
                    case "__unset":
                        TakesExactAmountOfArgumentsStrategy.apply(1, method, holder);
                        CanNotBeStaticStrategy.apply(method, holder);
                        MustBePublicStrategy.apply(method, holder);
                        CanNotTakeArgumentsByReferenceStrategy.apply(method, holder);
                        HasAlsoMethodStrategy.apply(method, "__set", holder);
                        break;
                    case "__set":
                    case "__call":
                        TakesExactAmountOfArgumentsStrategy.apply(2, method, holder);
                        CanNotBeStaticStrategy.apply(method, holder);
                        MustBePublicStrategy.apply(method, holder);
                        CanNotTakeArgumentsByReferenceStrategy.apply(method, holder);
                        if (methodName.equals("__set")) {
                            HasAlsoMethodStrategy.apply(method, "__isset", holder);
                            HasAlsoMethodStrategy.apply(method, "__get", holder);
                        }
                        break;
                    case "__callStatic":
                        TakesExactAmountOfArgumentsStrategy.apply(2, method, holder);
                        MustBeStaticStrategy.apply(method, holder);
                        MustBePublicStrategy.apply(method, holder);
                        CanNotTakeArgumentsByReferenceStrategy.apply(method, holder);
                        break;
                    case "__toString":
                        CanNotBeStaticStrategy.apply(method, holder);
                        CanNotTakeArgumentsStrategy.apply(method, holder);
                        MustBePublicStrategy.apply(method, holder);
                        MustReturnSpecifiedTypeStrategy.apply(stringType, method, holder);
                        break;
                    case "__debugInfo":
                        CanNotBeStaticStrategy.apply(method, holder);
                        CanNotTakeArgumentsStrategy.apply(method, holder);
                        MustBePublicStrategy.apply(method, holder);
                        MustReturnSpecifiedTypeStrategy.apply(arrayOrNullType, method, holder);
                        MinimalPhpVersionStrategy.apply(method, holder, PhpLanguageLevel.PHP560);
                        break;
                    case "__set_state":
                        TakesExactAmountOfArgumentsStrategy.apply(1, method, holder);
                        MustBeStaticStrategy.apply(method, holder);
                        MustBePublicStrategy.apply(method, holder);
                        final PhpType returnTypes = (new PhpType()).add(clazz.getFQN()).add(Types.strStatic);
                        MustReturnSpecifiedTypeStrategy.apply(returnTypes, method, holder);
                        break;
                    case "__invoke":
                        CanNotBeStaticStrategy.apply(method, holder);
                        MustBePublicStrategy.apply(method, holder);
                        break;
                    case "__wakeup":
                        CanNotBeStaticStrategy.apply(method, holder);
                        CanNotTakeArgumentsStrategy.apply(method, holder);
                        CanNotReturnTypeStrategy.apply(method, holder);
                        break;
                    case "__unserialize":
                        CanNotBeStaticStrategy.apply(method, holder);
                        MustBePublicStrategy.apply(method, holder);
                        TakesExactAmountOfArgumentsStrategy.apply(1, method, holder);
                        CanNotReturnTypeStrategy.apply(method, holder);
                        break;
                    case "__sleep":
                    case "__serialize":
                        CanNotBeStaticStrategy.apply(method, holder);
                        MustBePublicStrategy.apply(method, holder);
                        CanNotTakeArgumentsStrategy.apply(method, holder);
                        MustReturnSpecifiedTypeStrategy.apply(arrayType, method, holder);
                        break;
                    case "__autoload":
                        TakesExactAmountOfArgumentsStrategy.apply(1, method, holder);
                        CanNotReturnTypeStrategy.apply(method, holder);
                        holder.registerProblem(
                                nameNode,
                                MessagesPresentationUtil.prefixWithEa(messageUseSplAutoloading),
                                ProblemHighlightType.LIKE_DEPRECATED
                        );
                        break;
                    default:
                        if (methodName.startsWith("__") && !knownNonMagic.contains(methodName)) {
                            holder.registerProblem(
                                    nameNode,
                                    MessagesPresentationUtil.prefixWithEa(messageNotMagic)
                            );
                        } else {
                            MissingUnderscoreStrategy.apply(method, holder);
                        }
                        break;
                }
            }
        };
    }
}
