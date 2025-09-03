<?php declare(strict_types=1);

namespace PdoApiUsageTrigger;

function testPdoApiUsage(): void
{
    /** @var \PDO $pdo */
    $pdo = new 
PDO(); // This will not actually run, just for type hinting

    // Positive case: Unnecessary PDO::prepare() followed by execute() without parameters
    /** @var \PDOStatement $stmt */
    $stmt = $pdo->prepare('SELECT * FROM users');
    $stmt->execute(); // Should trigger the rule

    // Negative case: PDO::prepare() followed by execute() with parameters
    /** @var \PDOStatement $stmtWithParams */
    $stmtWithParams = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmtWithParams->execute([1]); // Should NOT trigger the rule

    // Negative case: PDO::query() usage (not covered by current rule, should not trigger)
    $pdo->query('SELECT * FROM products'); // Should NOT trigger the rule

    // Another positive case: prepare and execute on separate lines, no params
    /** @var \PDOStatement $stmt2 */
    $stmt2 = $pdo->prepare('INSERT INTO logs (message) VALUES (?)');
    $stmt2->execute(); // Should trigger the rule

    // Another negative case: prepare and execute on separate lines, with params
    /** @var \PDOStatement $stmt3 */
    $stmt3 = $pdo->prepare('UPDATE settings SET value = ? WHERE key = ?');
    $stmt3->execute(['value', 'key']); // Should NOT trigger the rule
}