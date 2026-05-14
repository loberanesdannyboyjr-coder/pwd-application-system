<?php

function logAction($conn, $action, $application_id = null) {

    if (!isset($_SESSION)) {
        session_start();
    }

    $user_id = $_SESSION['user_id'] ?? null;
    $role    = $_SESSION['role'] ?? 'UNKNOWN';

    pg_query_params(
        $conn,
        "INSERT INTO audit_logs (user_id, role, action, application_id)
         VALUES ($1, $2, $3, $4)",
        [
            $user_id,
            $role,
            $action,
            $application_id
        ]
    );
}