# PHPStan Rules to Java Inspector Mapping

This file maps each PHPStan rule in this directory to its corresponding Java inspector from the PhpStorm EA Extended plugin.

## File Mappings

| PHPStan Rule (PHP) | Java Inspector | Purpose |
|-------------------|----------------|---------|
| `AccessModifierPresentedRule.php` | `AccessModifierPresentedInspector.java` | Detects when access modifiers should be presented |
| `AliasFunctionsUsageRule.php` | `AliasFunctionsUsageInspector.java` | Detects usage of alias functions that should be replaced with their canonical equivalents |
| `AlterInForeachInspectorRule.php` | `AlterInForeachInspector.java` | Detects alter operations in foreach loops |
| `AlterInForeachRule.php` | `AlterInForeachInspector.java` | Detects alter operations in foreach loops |
| `AmbiguousMethodsCallsInArrayMappingRule.php` | `AmbiguousMethodsCallsInArrayMappingInspector.java` | Detects ambiguous method calls in array mapping operations |
| `ArgumentUnpackingCanBeUsedRule.php` | `ArgumentUnpackingCanBeUsedInspector.java` | Suggests using argument unpacking where applicable |
| `ArrayIsListCanBeUsedRule.php` | `ArrayIsListCanBeUsedInspector.java` | Suggests using array_is_list() where applicable |
| `ArrayPushMissUseRule.php` | `ArrayPushMissUseInspector.java` | Detects misuse of array_push() function |
| `ArraySearchLogicalUsageRule.php` | *No corresponding inspector found* | Detects logical issues with array_search() usage |
| `ArraySearchUsedAsInArrayRule.php` | `ArraySearchUsedAsInArrayInspector.java` | Detects array_search() used as in_array() |
| `ArrayUniqueCanBeUsedRule.php` | `ArrayUniqueCanBeUsedInspector.java` | Suggests using array_unique() where applicable |
| `AutoloadingIssuesRule.php` | `AutoloadingIssuesInspector.java` | Detects autoloading issues |
| `BadExceptionsProcessingRule.php` | `BadExceptionsProcessingInspector.java` | Detects problematic exception processing |
| `BadExceptionsProcessingCatchRule.php` | `BadExceptionsProcessingCatchInspector.java` | Detects problematic exception processing in catch blocks |
| `BadExceptionsProcessingTryRule.php` | `BadExceptionsProcessingTryInspector.java` | Detects problematic exception processing in try blocks |
| `BacktickOperatorUsageRule.php` | `BacktickOperatorUsageInspector.java` | Detects backtick operator usage |
| `CascadeStringReplacementRule.php` | `CascadeStringReplacementInspector.java` | Detects cascading string replacement operations that can be optimized |
| `CascadingDirnameCallsRule.php` | `CascadingDirnameCallsInspector.java` | Detects cascading dirname() calls that can be simplified |
| `CallableMethodValidityRule.php` | `CallableMethodValidityInspector.java` | Validates callable method implementations |
| `CaseInsensitiveStringFunctionsMissUseRule.php` | `CaseInsensitiveStringFunctionsMissUseInspector.java` | Detects misuse of case-insensitive string functions |
| `ClassConstantCanBeUsedRule.php` | `ClassConstantCanBeUsedInspector.java` | Suggests using class constants where applicable |
| `ClassConstantUsageCorrectnessRule.php` | `ClassConstantUsageCorrectnessInspector.java` | Validates class constant usage correctness |
| `ClassMethodNameMatchesFieldNameRule.php` | `ClassMethodNameMatchesFieldNameInspector.java` | Detects when class method names match field names |
| `ClassMockingCorrectnessRule.php` | `ClassMockingCorrectnessInspector.java` | Validates class mocking correctness |
| `ClassOverridesFieldOfSuperClassRule.php` | `ClassOverridesFieldOfSuperClassInspector.java` | Detects when a class overrides a field from its superclass |
| `ClassReImplementsParentInterfaceRule.php` | `ClassReImplementsParentInterfaceInspector.java` | Detects when a class reimplements a parent interface |
| `CompactArgumentsRule.php` | `CompactArgumentsInspector.java` | Validates compact() function arguments |
| `CompactCanBeUsedRule.php` | `CompactCanBeUsedInspector.java` | Suggests using compact() where applicable |
| `ComparisonOperandsOrderRule.php` | `ComparisonOperandsOrderInspector.java` | Detects incorrect operand order in comparisons |
| `ConstantCanBeUsedRule.php` | `ConstantCanBeUsedInspector.java` | Suggests using constants where applicable |
| `CryptographicallySecureRandomnessRule.php` | `CryptographicallySecureRandomnessInspector.java` | Ensures cryptographically secure randomness is used |
| `CryptographicallySecureAlgorithmsRule.php` | `CryptographicallySecureAlgorithmsInspector.java` | Ensures cryptographically secure algorithms are used |
| `CurlSslServerSpoofingRule.php` | `CurlSslServerSpoofingInspector.java` | Detects potential SSL server spoofing vulnerabilities in cURL |
| `DateIntervalSpecificationRule.php` | `DateIntervalSpecificationInspector.java` | Validates DateInterval specifications |
| `DateTimeConstantsUsageRule.php` | `DateTimeConstantsUsageInspector.java` | Suggests using DateTime constants |
| `DateTimeSetFunctionUsageRule.php` | *No corresponding inspector found* | Detects problematic DateTime set function usage |
| `DateTimeSetTimeUsageRule.php` | `DateTimeSetTimeUsageInspector.java` | Validates DateTime setTime() usage |
| `DateUsageRule.php` | `DateUsageInspector.java` | Detects problematic date/time usage patterns |
| `DebugRule.php` | *No corresponding inspector found* | Debug rule for testing custom PHPStan rules functionality |
| `DegradedSwitchRule.php` | `DegradedSwitchInspector.java` | Detects degraded switch statements |
| `DeprecatedConstructorStyleRule.php` | `DeprecatedConstructorStyleInspector.java` | Detects deprecated constructor style (PHP 4 style) |
| `DeprecatedIniOptionsRule.php` | `DeprecatedIniOptionsInspector.java` | Detects usage of deprecated PHP ini options |
| `DirectoryConstantCanBeUsedRule.php` | `DirectoryConstantCanBeUsedInspector.java` | Suggests using directory constants like __DIR__ |
| `DisallowWritingIntoStaticPropertiesRule.php` | `DisallowWritingIntoStaticPropertiesInspector.java` | Prevents writing into static properties inappropriately |
| `DisconnectedForeachInstructionRule.php` | `DisconnectedForeachInstructionInspector.java` | Detects disconnected foreach instructions |
| `DuplicatedCallInArrayMappingRule.php` | *No corresponding inspector found* | Detects duplicate calls in array mapping operations |
| `DuplicateArrayKeysRule.php` | `DuplicateArrayKeysInspector.java` | Detects duplicate array keys in array literals |
| `DynamicCallsToScopeIntrospectionRule.php` | `DynamicCallsToScopeIntrospectionInspector.java` | Detects dynamic calls to scope introspection functions |
| `DynamicInvocationViaScopeResolutionRule.php` | `DynamicInvocationViaScopeResolutionInspector.java` | Detects dynamic invocation via scope resolution |
| `EfferentObjectCouplingRule.php` | `EfferentObjectCouplingInspector.java` | Detects high efferent object coupling |
| `EmptyClassRule.php` | `EmptyClassInspector.java` | Detects empty class definitions |
| `EncryptionInitializationVectorRandomnessRule.php` | `EncryptionInitializationVectorRandomnessInspector.java` | Ensures encryption initialization vectors use proper randomness |
| `ElvisOperatorCanBeUsedRule.php` | `ElvisOperatorCanBeUsedInspector.java` | Suggests using the elvis operator (?:) where applicable |
| `FixedTimeStartWithRule.php` | `FixedTimeStartWithInspector.java` | Detects fixed time string comparisons that can be optimized |
| `FopenBinaryUnsafeUsageRule.php` | `FopenBinaryUnsafeUsageInspector.java` | Detects unsafe binary file operations with fopen() |
| `ForeachInvariantsRule.php` | `ForeachInvariantsInspector.java` | Detects foreach invariant violations |
| `ForgottenDebugOutputRule.php` | `ForgottenDebugOutputInspector.java` | Detects forgotten debug output statements |
| `GetClassUsageRule.php` | `GetClassUsageInspector.java` | Detects get_class() usage that can be improved |
| `GetDebugTypeCanBeUsedRule.php` | `GetDebugTypeCanBeUsedInspector.java` | Suggests using get_debug_type() where applicable |
| `GetTypeMissUseRule.php` | `GetTypeMissUseInspector.java` | Detects gettype() usage that can be replaced with is_*() functions |
| `HostnameSubstitutionRule.php` | `HostnameSubstitutionInspector.java` | Detects hostname substitution vulnerabilities |
| `IfReturnReturnSimplificationRule.php` | `IfReturnReturnSimplificationInspector.java` | Detects if-return-return patterns that can be simplified |
| `ImplodeArgumentsOrderRule.php` | `ImplodeArgumentsOrderInspector.java` | Detects incorrect argument order in implode() |
| `IncrementDecrementOperationEquivalentRule.php` | `IncrementDecrementOperationEquivalentInspector.java` | Detects increment/decrement operations that can be simplified |
| `InArrayMissUseRule.php` | `InArrayMissUseInspector.java` | Detects misuse of in_array() function |
| `IncorrectRandomRangeRule.php` | `IncorrectRandomRangeInspector.java` | Detects incorrect random number range usage |
| `InfinityLoopRule.php` | `InfinityLoopInspector.java` | Detects infinite loops |
| `InstanceofCanBeUsedRule.php` | `InstanceofCanBeUsedInspector.java` | Suggests using instanceof where applicable |
| `InvertedIfElseConstructsRule.php` | `InvertedIfElseConstructsInspector.java` | Detects inverted if-else constructs |
| `IsCountableCanBeUsedRule.php` | `IsCountableCanBeUsedInspector.java` | Suggests using is_countable() where applicable |
| `IsEmptyFunctionUsageRule.php` | `IsEmptyFunctionUsageInspector.java` | Detects problematic empty() function usage |
| `IsIterableCanBeUsedRule.php` | `IsIterableCanBeUsedInspector.java` | Suggests using is_iterable() where applicable |
| `IsNullFunctionUsageRule.php` | `IsNullFunctionUsageInspector.java` | Detects problematic is_null() function usage |
| `IssetArgumentExistenceRule.php` | `IssetArgumentExistenceInspector.java` | Detects isset() argument existence issues |
| `IssetConstructsCanBeMergedRule.php` | `IssetConstructsCanBeMergedInspector.java` | Detects isset() constructs that can be merged |
| `JsonThrowOnErrorRule.php` | `JsonEncodingApiUsageInspector.java` | Ensures JSON functions use JSON_THROW_ON_ERROR flag |
| `LongInheritanceChainRule.php` | `LongInheritanceChainInspector.java` | Detects long inheritance chains |
| `LoopWhichDoesNotLoopRule.php` | `LoopWhichDoesNotLoopInspector.java` | Detects loops that do not actually loop |
| `MagicMethodsValidityRule.php` | `MagicMethodsValidityInspector.java` | Validates magic method implementations |
| `MissingArrayInitializationRule.php` | `MissingArrayInitializationInspector.java` | Detects missing array initialization |
| `MissingIssetImplementationRule.php` | `MissingIssetImplementationInspector.java` | Detects missing isset() implementations |
| `MissingOrEmptyGroupStatementRule.php` | `MissingOrEmptyGroupStatementInspector.java` | Detects missing or empty group statements |
| `MisorderedModifiersRule.php` | `MisorderedModifiersInspector.java` | Detects misordered access modifiers |
| `MissUsingParentKeywordRule.php` | `MissUsingParentKeywordInspector.java` | Detects missing parent keyword usage |
| `MkdirRaceConditionRule.php` | `MkdirRaceConditionInspector.java` | Detects mkdir() race condition vulnerabilities |
| `MktimeUsageRule.php` | `MktimeUsageInspector.java` | Detects problematic mktime() usage patterns |
| `MockingMethodsCorrectnessRule.php` | `MockingMethodsCorrectnessInspector.java` | Validates mocking methods correctness |
| `MultiAssignmentUsageRule.php` | `MultiAssignmentUsageInspector.java` | Detects multi-assignment usage patterns |
| `MultipleReturnStatementsRule.php` | `MultipleReturnStatementsInspector.java` | Detects multiple return statements in functions |
| `NestedAssignmentsUsageRule.php` | `NestedAssignmentsUsageInspector.java` | Detects nested assignment usage |
| `NestedNotOperatorsRule.php` | `NestedNotOperatorsInspector.java` | Detects nested NOT operators |
| `NestedPositiveIfStatementsRule.php` | `NestedPositiveIfStatementsInspector.java` | Detects nested positive if statements |
| `NoNestedTernaryRule.php` | `NestedTernaryOperatorInspector.java` | Prevents nested ternary operators |
| `NotOptimalRegularExpressionsRule.php` | `NotOptimalRegularExpressionsInspector.java` | Detects non-optimal regular expressions |
| `NonSecureParseStrUsageRule.php` | `NonSecureParseStrUsageInspector.java` | Detects non-secure parse_str() usage |
| `NullPointerExceptionRule.php` | `NullPointerExceptionInspector.java` | Detects potential null pointer exceptions |
| `OffsetOperationsRule.php` | `OffsetOperationsInspector.java` | Detects problematic offset operations |
| `OneTimeUseVariablesRule.php` | `OneTimeUseVariablesInspector.java` | Detects variables used only once |
| `OnlyWritesOnParameterRule.php` | `OnlyWritesOnParameterInspector.java` | Detects parameters that are only written to |
| `OpAssignShortSyntaxRule.php` | `OpAssignShortSyntaxInspector.java` | Suggests using short assignment syntax |
| `ObGetCleanCanBeUsedRule.php` | `ObGetCleanCanBeUsedInspector.java` | Suggests using ob_get_clean() where applicable |
| `PackedHashtableOptimizationRule.php` | `PackedHashtableOptimizationInspector.java` | Suggests packed hashtable optimizations |
| `ParameterDefaultValueIsNotNullRule.php` | `ParameterDefaultValueIsNotNullInspector.java` | Detects parameters with non-null default values |
| `PassingByReferenceCorrectnessRule.php` | `PassingByReferenceCorrectnessInspector.java` | Validates passing by reference correctness |
| `PdoApiUsageRule.php` | `PdoApiUsageInspector.java` | Detects problematic PDO API usage |
| `PhpUnitDeprecationsRule.php` | `PhpUnitDeprecationsInspector.java` | Detects PHPUnit deprecations |
| `PhpUnitTestsRule.php` | `PhpUnitTestsInspector.java` | Validates PHPUnit test patterns |
| `PotentialMalwareRule.php` | `PotentialMalwareInspector.java` | Detects potential malware patterns |
| `PowerOperatorCanBeUsedRule.php` | `PowerOperatorCanBeUsedInspector.java` | Suggests using power operator where applicable |
| `PregQuoteUsageRule.php` | `PregQuoteUsageInspector.java` | Validates preg_quote() usage |
| `PreloadingUsageCorrectnessRule.php` | `PreloadingUsageCorrectnessInspector.java` | Validates PHP preloading usage (Note: Only triggers for files named `preload.php`) |
| `PrintfScanfArgumentsRule.php` | `PrintfScanfArgumentsInspector.java` | Validates printf/scanf arguments |
| `ProperNullCoalescingOperatorUsageRule.php` | `ProperNullCoalescingOperatorUsageInspector.java` | Ensures proper null coalescing operator usage |
| `PropertyCanBeStaticRule.php` | `PropertyCanBeStaticInspector.java` | Detects properties that can be static |
| `PropertyInitializationFlawsRule.php` | `PropertyInitializationFlawsInspector.java` | Detects property initialization issues |
| `RandomApiMigrationRule.php` | `RandomApiMigrationInspector.java` | Suggests migration from old random functions to new random API |
| `RealpathInStreamContextRule.php` | `RealpathInStreamContextInspector.java` | Detects problematic realpath() usage in stream contexts |
| `RedundantElseClauseRule.php` | `RedundantElseClauseInspector.java` | Detects redundant else clauses |
| `RedundantNullCoalescingRule.php` | `NullCoalescingOperatorCanBeUsedInspector.java` | Detects redundant null coalescing operators |
| `ReturnTypeCanBeDeclaredRule.php` | `ReturnTypeCanBeDeclaredInspector.java` | Suggests declaring return types where possible |
| `SecurityAdvisoriesRule.php` | `SecurityAdvisoriesInspector.java` | Detects security advisories and vulnerabilities |
| `SelfClassReferencingRule.php` | `SelfClassReferencingInspector.java` | Detects improper self class referencing |
| `SenselessMethodDuplicationRule.php` | `SenselessMethodDuplicationInspector.java` | Detects senseless method duplication |
| `SenselessProxyMethodRule.php` | `SenselessProxyMethodInspector.java` | Detects senseless proxy methods |
| `SenselessTernaryOperatorRule.php` | `SenselessTernaryOperatorInspector.java` | Detects senseless ternary operators |
| `ShortEchoTagCanBeUsedRule.php` | `ShortEchoTagCanBeUsedInspector.java` | Suggests using short echo tags where applicable |
| `ShortListSyntaxCanBeUsedRule.php` | `ShortListSyntaxCanBeUsedInspector.java` | Suggests using short list syntax where applicable |
| `ShortOpenTagUsageRule.php` | `ShortOpenTagUsageInspector.java` | Detects short open tag usage |
| `SimpleXmlLoadFileUsageRule.php` | `SimpleXmlLoadFileUsageInspector.java` | Validates simplexml_load_file() usage |
| `SlowArrayOperationsInLoopRule.php` | `SlowArrayOperationsInLoopInspector.java` | Detects slow array operations in loops |
| `StaticClosureCanBeUsedRule.php` | `StaticClosureCanBeUsedInspector.java` | Suggests using static closures where applicable |
| `StaticInvocationViaThisRule.php` | `StaticInvocationViaThisInspector.java` | Detects static invocation via $this |
| `StaticLambdaBindingRule.php` | `StaticLambdaBindingInspector.java` | Detects static lambda binding issues |
| `StrContainsCanBeUsedRule.php` | `StrContainsCanBeUsedInspector.java` | Suggests using str_contains() where applicable |
| `StrEndsWithCanBeUsedRule.php` | `StrEndsWithCanBeUsedInspector.java` | Suggests using str_ends_with() where applicable |
| `StrStartsWithCanBeUsedRule.php` | `StrStartsWithCanBeUsedInspector.java` | Suggests using str_starts_with() where applicable |
| `StrStrUsedAsStrPosRule.php` | `StrStrUsedAsStrPosInspector.java` | Detects strstr() used as strpos() |
| `StrTrUsageAsStrReplaceRule.php` | `StrTrUsageAsStrReplaceInspector.java` | Detects strtr() usage that can be replaced with str_replace() |
| `StringCaseManipulationRule.php` | `StringCaseManipulationInspector.java` | Detects problematic string case manipulation |
| `StringNormalizationRule.php` | `StringNormalizationInspector.java` | Detects string normalization issues |
| `StringsFirstCharactersCompareRule.php` | `StringsFirstCharactersCompareInspector.java` | Optimizes string first character comparisons |
| `StrtotimeUsageRule.php` | `StrtotimeUsageInspector.java` | Detects problematic strtotime() usage patterns |
| `SubStrShortHandUsageRule.php` | `SubStrShortHandUsageInspector.java` | Suggests substr() shorthand usage |
| `SubStrUsedAsArrayAccessRule.php` | `SubStrUsedAsArrayAccessInspector.java` | Detects substr() used as array access |
| `SubStrUsedAsStrPosRule.php` | `SubStrUsedAsStrPosInspector.java` | Detects substr() used as strpos() |
| `SuspiciousAssignmentsRule.php` | `SuspiciousAssignmentsInspector.java` | Detects suspicious assignments |
| `SuspiciousBinaryOperationRule.php` | `SuspiciousBinaryOperationInspector.java` | Detects suspicious binary operations |
| `SuspiciousFunctionCallsRule.php` | `SuspiciousFunctionCallsInspector.java` | Detects suspicious function calls |
| `SuspiciousLoopRule.php` | `SuspiciousLoopInspector.java` | Detects suspicious loop constructs |
| `SuspiciousReturnRule.php` | `SuspiciousReturnInspector.java` | Detects suspicious return statements |
| `SuspiciousSemicolonRule.php` | `SuspiciousSemicolonInspector.java` | Detects suspicious semicolon usage |
| `SwitchContinuationInLoopRule.php` | `SwitchContinuationInLoopInspector.java` | Detects continue statements inside switch statements within loops |
| `TernaryOperatorSimplifyRule.php` | `TernaryOperatorSimplifyInspector.java` | Detects ternary operators that can be simplified |
| `TestRule.php` | *No corresponding inspector found* | Test rule for development purposes |
| `TraitsPropertiesConflictsRule.php` | `TraitsPropertiesConflictsInspector.java` | Detects trait property conflicts |
| `ThrowRawExceptionRule.php` | `ThrowRawExceptionInspector.java` | Prevents throwing raw exceptions |
| `TypeUnsafeArraySearchRule.php` | `TypeUnsafeArraySearchInspector.java` | Detects type-unsafe array search operations |
| `TypeUnsafeComparisonRule.php` | `TypeUnsafeComparisonInspector.java` | Detects type-unsafe comparisons |
| `TypesCastingCanBeUsedRule.php` | `TypesCastingCanBeUsedInspector.java` | Suggests type casting where applicable |
| `UnSafeIsSetOverArrayRule.php` | `UnSafeIsSetOverArrayInspector.java` | Detects unsafe isset() usage over arrays |
| `UniqidMoreEntropyRule.php` | `NonSecureUniqidUsageInspector.java` | Ensures uniqid() uses more entropy for security |
| `UnknownInspectionRule.php` | `UnknownInspectionInspector.java` | Detects unknown inspection patterns |
| `UnnecessaryAssertionRule.php` | `UnnecessaryAssertionInspector.java` | Detects unnecessary assertions |
| `UnnecessaryCastingRule.php` | `UnnecessaryCastingInspector.java` | Detects unnecessary type casting |
| `UnNecessaryDoubleQuotesRule.php` | `UnNecessaryDoubleQuotesInspector.java` | Detects unnecessary double quotes |
| `UnnecessaryFinalModifierRule.php` | `UnnecessaryFinalModifierInspector.java` | Detects unnecessary final modifiers |
| `UnnecessaryIssetArgumentsRule.php` | `UnnecessaryIssetArgumentsInspector.java` | Detects unnecessary isset() arguments |
| `UnnecessarySemicolonRule.php` | `UnnecessarySemicolonInspector.java` | Detects unnecessary semicolons |
| `UnnecessaryUseAliasRule.php` | `UnnecessaryUseAliasInspector.java` | Detects unnecessary use aliases |
| `UnqualifiedReferenceRule.php` | `UnqualifiedReferenceInspector.java` | Detects unqualified references |
| `UntrustedInclusionRule.php` | `UntrustedInclusionInspector.java` | Detects untrusted file inclusions |
| `UnserializeExploitsRule.php` | `UnserializeExploitsInspector.java` | Prevents unserialize() security exploits |
| `UnsetConstructsCanBeMergedRule.php` | `UnsetConstructsCanBeMergedInspector.java` | Suggests merging unset() constructs |
| `UnsupportedEmptyListAssignmentsRule.php` | `UnsupportedEmptyListAssignmentsInspector.java` | Detects unsupported empty list assignments |
| `UnsupportedStringOffsetOperationsRule.php` | `UnsupportedStringOffsetOperationsInspector.java` | Detects unsupported string offset operations |
| `UnusedClosureParameterRule.php` | *No corresponding inspector found* | Detects unused closure parameters |
| `UnusedConstructorDependenciesRule.php` | `UnusedConstructorDependenciesInspector.java` | Detects unused constructor dependencies |
| `UnusedGotoLabelRule.php` | `UnusedGotoLabelInspector.java` | Detects unused goto labels |
| `UselessReturnRule.php` | `UselessReturnInspector.java` | Detects useless return statements |
| `UselessUnsetRule.php` | `UselessUnsetInspector.java` | Detects useless unset() calls |
| `UsingInclusionOnceReturnValueRule.php` | `UsingInclusionOnceReturnValueInspector.java` | Detects using include_once/require_once return values |
| `UsingInclusionReturnValueRule.php` | `UsingInclusionReturnValueInspector.java` | Detects using inclusion return values |
| `VariableFunctionsUsageRule.php` | `VariableFunctionsUsageInspector.java` | Detects variable functions usage |

## Statistics

- **Total PHPStan Rules**: 183
- **Mapped to Java Inspectors**: 178
- **No corresponding Java Inspector**: 5 (ArraySearchLogicalUsageRule.php, DateTimeSetFunctionUsageRule.php, DuplicatedCallInArrayMappingRule.php, TestRule.php, UnusedClosureParameterRule.php)

## Source Paths

- **PHPStan Rules**: `phpstanrules/`
- **Java Inspectors**: `eainspections/phpinspectionsea/src/main/java/com/kalessil/phpStorm/phpInspectionsEA/inspectors/`
- **HTML Descriptions**: `eainspections/phpinspectionsea/src/main/resources/inspectionDescriptions/`

## Notes

- Rules without corresponding Java inspectors were likely created independently or based on custom requirements
- The mapping helps understand the purpose and origin of each PHPStan rule
- Java inspector names follow the pattern `*Inspector.java` while PHPStan rules follow `*Rule.php`

## Special Testing Notes

### PreloadingUsageCorrectnessRule
This rule has a special requirement: it **only triggers for files named `preload.php`**. For testing:

```bash
# ✅ Will trigger the rule (file must be named preload.php)
docker exec -w /app/worktree/phpstan rameder-amazon-lister-php-1 php vendor/bin/phpstan analyse --configuration test_config.neon phpstanrules/trigger/preload.php

# ❌ Will NOT trigger the rule (different filename)
docker exec -w /app/worktree/phpstan rameder-amazon-lister-php-1 php vendor/bin/phpstan analyse --configuration test_config.neon phpstanrules/trigger/PreloadingUsageCorrectnessRule_trigger.php
```

The rule detects `include`, `include_once`, `require`, and `require_once` statements in preload files and suggests using `opcache_compile_file()` instead for proper preloading behavior.