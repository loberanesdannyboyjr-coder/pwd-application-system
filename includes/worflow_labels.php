<?php
// includes/workflow_labels.php

function workflow_label(string $status): string {
    return match ($status) {

        // Draft / internal
        'draft'          => 'DRAFT',

        // Review stages
        'pdao_review'    => 'FOR PDAO REVIEW',
        'cho_review'     => 'FOR CHO REVIEW',

        // Approved stages
        'cho_approved'   => 'CHO APPROVED',
        'pdao_approved'  => 'PDAO APPROVED',

        // Final negative
        'rejected'       => 'DISAPPROVED',

        default          => strtoupper(str_replace('_', ' ', $status)),
    };
}

function workflow_badge(string $status): string {
    return match ($status) {

        'draft'          => '#9ca3af', // gray
        'pdao_review'    => '#8b5cf6', // purple
        'cho_review'     => '#f59e0b', // orange
        'cho_approved'   => '#22c55e', // green
        'pdao_approved'  => '#16a34a', // dark green
        'rejected'       => '#dc2626', // red

        default          => '#6b7280',
    };
}
