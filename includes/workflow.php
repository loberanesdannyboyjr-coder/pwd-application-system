<?php
// includes/workflow.php
// FINAL & LOCKED workflow transition map
// Used by api/admin_action.php

return [

    /*
    |--------------------------------------------------
    | PDAO ACTIONS
    |--------------------------------------------------
    */

    // PDAO forwards application to CHO for medical review
    'forward_to_cho' => [
        'to_status'       => 'cho_review',
        'allowed_roles'   => ['ADMIN', 'PDAO'],
        'require_remarks' => false,
        'redirect'        => '/src/admin_side/application_review.php',
    ],

    // PDAO requests additional information from applicant
    'request_more_info' => [
        'to_status'       => 'pdao_rejected',
        'allowed_roles'   => ['ADMIN', 'PDAO'],
        'require_remarks' => true,
        // stay on page (reload)
    ],

    // PDAO rejects application
    'reject' => [
        'to_status'       => 'rejected',
        'allowed_roles'   => ['ADMIN', 'PDAO'],
        'require_remarks' => true,
        'redirect'        => '/src/admin_side/application_review.php',
    ],

    /*
    |--------------------------------------------------
    | CHO ACTIONS
    |--------------------------------------------------
    */

    // CHO verifies applicant as PWD
    'cho_verify' => [
        'to_status'       => 'cho_accepted',
        'allowed_roles'   => ['CHO', 'ADMIN'],
        'require_remarks' => false,
        'redirect'        => '/src/doctor/accepted.php',
    ],

    // CHO rejects applicant as NOT PWD (medical rejection)
    'cho_reject' => [
        'to_status'       => 'cho_rejected',
        'allowed_roles'   => ['CHO', 'ADMIN'],
        'require_remarks' => true,
        'redirect'        => '/src/doctor/denied.php',
    ],

    /*
    |--------------------------------------------------
    | FINALIZATION
    |--------------------------------------------------
    */

    // PDAO issues final PWD ID number
    'finalize_issue_id' => [
        'to_status'       => 'approved_final',
        'allowed_roles'   => ['PDAO', 'ADMIN'],
        'require_remarks' => false,
        'redirect'        => '/src/admin_side/members.php',
    ],

];
