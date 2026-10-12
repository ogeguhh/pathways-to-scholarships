
<?php
// Temporary authentication helpers for Iteration 2

// Role checking will be implemented in Iteration 7
function require_role(array $roles) {
    // No role restrictions during local development
}

// Return the placeholder development user's ID
function current_user_id() {
    return 1;
}
