<?php
declare(strict_types=1);

// Return the assigned national theme for a contributing student.
// Unknown students return null rather than receiving a guessed theme.
function importAssignedNationalTheme(?string $student): ?string
{
    $assignments = [
        'Ogega_Bryan' => 'First-generation college students',
        'Wolde_Amen' => 'Professional associations',
        'Muse_Mohamed' => 'Humanities and social sciences',
    ];

    return $assignments[$student ?? ''] ?? null;
}
