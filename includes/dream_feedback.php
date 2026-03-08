<?php
// Helpers for post-achievement feedback collection.

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/mail.php';

function ensureDreamFeedbackSchema(PDO $db): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS dream_feedback_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            dream_id INT UNSIGNED NOT NULL,
            recipient_role ENUM('guardian','supporter') NOT NULL,
            recipient_name VARCHAR(150) NOT NULL,
            recipient_email VARCHAR(255) NOT NULL,
            token VARCHAR(64) NOT NULL,
            sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            opened_at DATETIME NULL DEFAULT NULL,
            submitted_at DATETIME NULL DEFAULT NULL,
            rating TINYINT UNSIGNED NULL DEFAULT NULL,
            review_text TEXT NULL,
            donation_interest TINYINT(1) NOT NULL DEFAULT 0,
            donation_amount DECIMAL(10,2) NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_token (token),
            UNIQUE KEY uniq_dream_recipient (dream_id, recipient_email),
            INDEX idx_dream (dream_id),
            INDEX idx_submitted (submitted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS admin_feedback_reads (
            admin_id INT UNSIGNED NOT NULL PRIMARY KEY,
            last_seen_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $ensured = true;
}

function markAdminFeedbackReviewed(PDO $db, int $adminId): void {
    if ($adminId <= 0) {
        return;
    }
    $stmt = $db->prepare("
        INSERT INTO admin_feedback_reads (admin_id, last_seen_at)
        VALUES (?, NOW())
        ON DUPLICATE KEY UPDATE last_seen_at = NOW()
    ");
    $stmt->execute([$adminId]);
}

function getAdminUnreadFeedbackCount(PDO $db, int $adminId): int {
    if ($adminId <= 0) {
        return 0;
    }
    $seenStmt = $db->prepare("SELECT last_seen_at FROM admin_feedback_reads WHERE admin_id = ? LIMIT 1");
    $seenStmt->execute([$adminId]);
    $lastSeenAt = $seenStmt->fetchColumn();

    if (!$lastSeenAt) {
        return (int)$db->query("SELECT COUNT(*) FROM dream_feedback_requests WHERE submitted_at IS NOT NULL")->fetchColumn();
    }

    $cntStmt = $db->prepare("
        SELECT COUNT(*)
        FROM dream_feedback_requests
        WHERE submitted_at IS NOT NULL
          AND submitted_at > ?
    ");
    $cntStmt->execute([$lastSeenAt]);
    return (int)$cntStmt->fetchColumn();
}

function getDreamFeedbackRequestByToken(PDO $db, string $token): ?array {
    $stmt = $db->prepare("
        SELECT fr.*, d.title AS dream_title, d.category AS dream_category, d.status AS dream_status
        FROM dream_feedback_requests fr
        JOIN dreams d ON fr.dream_id = d.id
        WHERE fr.token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function createDreamFeedbackRequest(PDO $db, int $dreamId, string $role, string $name, string $email): ?array {
    $email = trim($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    $role = $role === 'supporter' ? 'supporter' : 'guardian';
    $name = trim($name) !== '' ? trim($name) : ucfirst($role);

    $existingStmt = $db->prepare("
        SELECT id, token
        FROM dream_feedback_requests
        WHERE dream_id = ? AND recipient_email = ?
        LIMIT 1
    ");
    $existingStmt->execute([$dreamId, $email]);
    $existing = $existingStmt->fetch();
    if ($existing) {
        return [
            'id' => (int)$existing['id'],
            'token' => $existing['token'],
            'is_new' => false,
            'role' => $role,
            'name' => $name,
            'email' => $email
        ];
    }

    $token = bin2hex(random_bytes(32));
    $insertStmt = $db->prepare("
        INSERT INTO dream_feedback_requests (dream_id, recipient_role, recipient_name, recipient_email, token)
        VALUES (?, ?, ?, ?, ?)
    ");
    $insertStmt->execute([$dreamId, $role, $name, $email, $token]);

    return [
        'id' => (int)$db->lastInsertId(),
        'token' => $token,
        'is_new' => true,
        'role' => $role,
        'name' => $name,
        'email' => $email
    ];
}

function sendDreamFeedbackInvites(PDO $db, int $dreamId): array {
    ensureDreamFeedbackSchema($db);

    $dreamStmt = $db->prepare("
        SELECT d.id, d.title,
               gu.name AS guardian_name, gu.email AS guardian_email
        FROM dreams d
        JOIN students s ON d.student_id = s.id
        JOIN users gu ON s.guardian_id = gu.id
        WHERE d.id = ?
        LIMIT 1
    ");
    $dreamStmt->execute([$dreamId]);
    $dream = $dreamStmt->fetch();
    if (!$dream) {
        return ['sent' => 0, 'failed' => 0, 'skipped' => 0];
    }

    $targets = [];
    if (!empty($dream['guardian_email'])) {
        $targets[] = [
            'role' => 'guardian',
            'name' => $dream['guardian_name'],
            'email' => $dream['guardian_email']
        ];
    }

    $supporterStmt = $db->prepare("
        SELECT DISTINCT su.name, su.email
        FROM dream_support ds
        JOIN users su ON ds.supporter_id = su.id
        WHERE ds.dream_id = ? AND ds.status = 'Approved'
    ");
    $supporterStmt->execute([$dreamId]);
    foreach ($supporterStmt->fetchAll() as $s) {
        if (empty($s['email'])) {
            continue;
        }
        $targets[] = [
            'role' => 'supporter',
            'name' => $s['name'],
            'email' => $s['email']
        ];
    }

    $seen = [];
    $sent = 0;
    $failed = 0;
    $skipped = 0;

    foreach ($targets as $target) {
        $emailKey = strtolower(trim($target['email']));
        if (isset($seen[$emailKey])) {
            continue;
        }
        $seen[$emailKey] = true;

        $request = createDreamFeedbackRequest($db, $dreamId, $target['role'], $target['name'], $target['email']);
        if (!$request) {
            $skipped++;
            continue;
        }
        if (!$request['is_new']) {
            $skipped++;
            continue;
        }

        $link = appUrl('feedback_form.php?token=' . urlencode($request['token']));
        $subject = 'Please share feedback for completed dream';
        $body = '<p>Hi ' . e($request['name']) . ',</p>'
              . '<p>The dream "<strong>' . e($dream['title']) . '</strong>" has been marked as achieved.</p>'
              . '<p>We would value your feedback. Please share:</p>'
              . '<ul><li>a quick rating</li><li>a short review</li><li>optional donation support for the platform</li></ul>'
              . '<p><a href="' . e($link) . '">' . e($link) . '</a></p>'
              . '<p>Thank you for helping us improve ' . APP_NAME . '.</p>'
              . '<p>With gratitude,<br>' . APP_NAME . ' team</p>';

        if (sendEmail($request['email'], $subject, $body)) {
            $sent++;
        } else {
            $failed++;
        }
    }

    return ['sent' => $sent, 'failed' => $failed, 'skipped' => $skipped];
}
