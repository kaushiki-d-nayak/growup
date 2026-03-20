<?php
function ensureDreamsBudgetSchema(PDO $db): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $budgetCol = $db->query("SHOW COLUMNS FROM dreams LIKE 'budget_range'")->fetch();
    $hasBudgetRange = (bool)$budgetCol;
    if (!$hasBudgetRange) {
        $db->exec("
            ALTER TABLE dreams
            ADD COLUMN budget_range VARCHAR(60) NULL AFTER category
        ");
        $hasBudgetRange = true;
    } else {
        $type = strtolower((string)($budgetCol['Type'] ?? ''));
        if ($type !== '' && !str_starts_with($type, 'varchar')) {
            $db->exec("
                ALTER TABLE dreams
                MODIFY COLUMN budget_range VARCHAR(60) NULL
            ");
        }
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS dreams_budget_backup (
            dream_id INT UNSIGNED NOT NULL PRIMARY KEY,
            budget_range VARCHAR(60) NULL,
            backed_up_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $db->exec("
        INSERT INTO dreams_budget_backup (dream_id, budget_range)
        SELECT id, budget_range
        FROM dreams
        WHERE budget_range IS NOT NULL AND budget_range <> ''
        ON DUPLICATE KEY UPDATE budget_range = VALUES(budget_range)
    ");

    $hasLegacyBudget = (bool)$db->query("SHOW COLUMNS FROM dreams LIKE 'budget'")->fetch();
    if ($hasBudgetRange && $hasLegacyBudget) {
        $db->exec("
            UPDATE dreams
            SET budget_range = budget
            WHERE (budget_range IS NULL OR budget_range = '')
              AND budget IS NOT NULL AND budget <> ''
        ");
    }

    $db->exec("
        UPDATE dreams d
        JOIN dreams_budget_backup b ON b.dream_id = d.id
        SET d.budget_range = b.budget_range
        WHERE (d.budget_range IS NULL OR d.budget_range = '')
          AND b.budget_range IS NOT NULL AND b.budget_range <> ''
    ");

    $ensured = true;
}
