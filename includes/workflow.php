<?php
// includes/workflow.php
// FINAL workflow definition
// Used by admin / PDAO / CHO action handlers

return [

    /*
    |--------------------------------------------------
    | INITIAL SUBMISSION
    |--------------------------------------------------
    */

    // Applicant submits application → goes to PDAO first
    'submit' => [
        'to_status'       => 'pdao_review',
        'allowed_roles'   => ['APPLICANT'],
        'require_remarks' => false,
    ],

    /*
    |--------------------------------------------------
    | PDAO ACTIONS
    |--------------------------------------------------
    */

    // PDAO endorses to CHO
    'endorse_to_cho' => [
        'to_status'       => 'cho_review',
        'allowed_roles'   => ['PDAO', 'ADMIN'],
        'require_remarks' => false,
        'redirect'        => '/src/admin_side/application_review.php',
    ],

    // PDAO disapproves (remarks required)
    'pdao_disapprove' => [
        'to_status'       => 'rejected',
        'allowed_roles'   => ['PDAO', 'ADMIN'],
        'require_remarks' => true,
        'redirect'        => '/src/admin_side/application_review.php',
    ],

    /*
    |--------------------------------------------------
    | CHO ACTIONS
    |--------------------------------------------------
    */

    // CHO approves medically
    'cho_approve' => [
        'to_status'       => 'cho_approved',
        'allowed_roles'   => ['CHO', 'ADMIN'],
        'require_remarks' => false,
        'redirect'        => '/src/doctor/accepted.php',
    ],

    // CHO disapproves medically (remarks required)
    'cho_disapprove' => [
        'to_status'       => 'rejected',
        'allowed_roles'   => ['CHO', 'ADMIN'],
        'require_remarks' => true,
        'redirect'        => '/src/doctor/denied.php',
    ],

    /*
    |--------------------------------------------------
    | FINAL PDAO APPROVAL
    |--------------------------------------------------
    */

    // PDAO issues final PWD ID
    'final_approve' => [
        'to_status'       => 'pdao_approved',
        'allowed_roles'   => ['PDAO', 'ADMIN'],
        'require_remarks' => false,
        'redirect'        => '/src/admin_side/members.php',
    ],

];