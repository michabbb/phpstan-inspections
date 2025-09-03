# PHPStan Inspections

> **:warning: Disclaimer: Use With Caution :warning:**
> 
> All PHPStan rules in this package were written with the assistance of AI. While they have been tested, it is highly likely that some rules may produce false positives or be otherwise incorrect. Please use this package with caution and thoroughly verify its findings.

This repository contains a set of PHPStan rules inspired by the popular PhpStorm plugin [PhpInspections (EA Extended)](https://github.com/kalessil/phpinspectionsea). Its goal is to bring the powerful static analysis capabilities of PhpInspections to the command line and your CI/CD pipeline through PHPStan.

This allows you to find potential bugs, performance issues, and code style violations automatically, ensuring a higher code quality across your projects.

## Installation

To use these rules, require the package via Composer:

```bash
composer require --dev macropage/phpstan-inspections
```

PHPStan will automatically discover the extension via `phpstan/extension-installer`.

## Usage

Once installed, the rules are active by default. If you need to configure it manually, you can include the `rules.neon` file in your project's `phpstan.neon` configuration:

```neon
includes:
    - vendor/macropage/phpstan-inspections/rules/rules.neon
```

### Laravel Support

This package also includes rules specifically adapted for Laravel projects. For example, the `StaticInvocationViaThisLaravelRule` is optimized to work correctly with Laravel's Facades and Eloquent Models.

To use the Laravel-specific rules, include the `rules.laravel.neon` file in your `phpstan.neon` instead:

```neon
includes:
    - vendor/macropage/phpstan-inspections/rules/rules.laravel.neon
```

You can then run PHPStan as you normally would:

```bash
vendor/bin/phpstan analyse src tests
```

## Origin of the Rule Logic

Each rule in this package is a direct port from a Java template found in the original PhpInspections (EA Extended) project. The corresponding Java source file for each rule is located in the `dev-assets/java` directory, providing a clear reference to the original logic.

**Example:**

- **Rule File:** `src/CodeStyle/AmbiguousMethodsCallsInArrayMappingRule.php`
- **Ported From:** `dev-assets/java/codeStyle/AmbiguousMethodsCallsInArrayMappingInspector.java`
- **Test Trigger File:** `dev-assets/triggers/AmbiguousMethodsCallsInArrayMappingRule_trigger.php`

## Rule Set

This package includes a wide variety of rules that check for:

*   Architecture-related issues
*   Potential bugs and logical errors
*   Performance bottlenecks
*   Code style and best practice violations
*   Security vulnerabilities

A complete list of all included rules can be found by browsing the `src/` directory.

## Acknowledgements

This project would not be possible without the incredible work done by Vladimir Reznichenko (@kalessil) on the original [PhpInspections (EA Extended)](https://github.com/kalessil/phpinspectionsea) plugin for PhpStorm. All the logic and inspiration for these rules come from that project.

## Contributing

Contributions are welcome! Please feel free to submit a pull request or create an issue for any bugs or feature requests.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.