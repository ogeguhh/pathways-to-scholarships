<?php
declare(strict_types=1);

// Pathways to Scholarships
// Iteration 7: Seed Administrative Accounts

// Prevent this script from being executed through a web browser.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Access denied.');
}

require_once dirname(__DIR__) . '/includes/db.php';

$pdo = getDBConnection();

// Read passwords without displaying them in the terminal.
function readPassword(string $prompt): string
{
    echo $prompt;

    system('stty -echo');

    try {
        $password = rtrim((string) fgets(STDIN), "\r\n");
    } finally {
        system('stty echo');
        echo PHP_EOL;
    }

    return $password;
}

// Apply the same password requirements used during registration.
function validPassword(string $password): bool
{
    return strlen($password) >= 8
        && strlen($password) <= 72
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[^a-zA-Z0-9]/', $password);
}

// Define the administrative accounts without storing passwords.
$accounts = [
    [
        'full_name' => 'System Super-admin',
        'email' => 'superadmin@example.test',
        'role' => 'Super-admin'
    ],
    [
        'full_name' => 'System Administrator',
        'email' => 'admin@example.test',
        'role' => 'Administrator'
    ]
];

echo "========================================\n";
echo "PATHWAYS TO SCHOLARSHIPS - ADMIN SETUP\n";
echo "========================================\n\n";

try {

    // Collect and validate passwords before modifying the database.
    foreach ($accounts as &$account) {

        echo "Account: {$account['email']}\n";

        $password = readPassword('Enter password: ');
        $confirmation = readPassword('Confirm password: ');

        if (!validPassword($password)) {
            throw new RuntimeException(
                'Password does not meet the strength requirements.'
            );
        }

        if ($password !== $confirmation) {
            throw new RuntimeException('Passwords do not match.');
        }

        $account['password_hash'] = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        unset($password, $confirmation);
    }

    unset($account);

    // A transaction ensures that all changes succeed together.
    $pdo->beginTransaction();

    foreach ($accounts as $account) {

        $stmt = $pdo->prepare(
            'SELECT role_id FROM roles WHERE role_name = ?'
        );

        $stmt->execute([$account['role']]);

        $roleId = $stmt->fetchColumn();

        if ($roleId === false) {
            throw new RuntimeException('Required role not found.');
        }

        // Prevent duplicate accounts or accidental overwrites.
        $stmt = $pdo->prepare(
            'SELECT user_id FROM users WHERE email = ?'
        );

        $stmt->execute([$account['email']]);

        if ($stmt->fetchColumn() !== false) {
            throw new RuntimeException(
                'Account already exists: ' . $account['email']
            );
        }

        $stmt = $pdo->prepare(
            "INSERT INTO users
                (
                    role_id,
                    full_name,
                    email,
                    password_hash,
                    account_status,
                    must_change_password
                )
             VALUES (?, ?, ?, ?, 'active', 0)"
        );

        $stmt->execute([
            $roleId,
            $account['full_name'],
            $account['email'],
            $account['password_hash']
        ]);

        echo "Created: {$account['email']}\n";
    }

    // Deactivate the temporary development account.
    // Preserve the account instead of deleting historical information.
    $stmt = $pdo->prepare(
        "UPDATE users
         SET account_status = 'inactive'
         WHERE email = ?
           AND password_hash IS NULL"
    );

    $stmt->execute(['dev-admin@example.test']);

    $pdo->commit();

    echo "\nAdministrative setup completed successfully.\n";
    echo "Development Administrator accounts deactivated: ";
    echo $stmt->rowCount() . "\n";

} catch (Throwable $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "\nSetup failed: ";
    echo $exception->getMessage() . "\n";
    exit(1);
}
