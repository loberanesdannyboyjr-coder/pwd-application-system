<?php
function workflow_label(string $status): string {
    return match ($status) {
        'draft'            => 'DRAFT',
        'submitted'        => 'SUBMITTED',
        'pdao_review'      => 'PDAO REVIEW',
        'returned'         => 'RETURNED (INCOMPLETE)',
        'endorsed_to_cho'  => 'ENDORSED TO CHO',
        'cho_review'       => 'CHO REVIEW',
        'approved'         => 'APPROVED',
        'rejected'         => 'REJECTED',
        'released'         => 'ID RELEASED',
        default             => strtoupper(str_replace('_', ' ', $status)),
    };
}

function workflow_badge(string $status): string {
    return match ($status) {
        'draft'            => '#9ca3af', // gray
        'submitted'        => '#3b82f6', // blue
        'pdao_review'      => '#8b5cf6', // purple
        'returned'         => '#ef4444', // red
        'endorsed_to_cho'  => '#6366f1', // indigo
        'cho_review'       => '#f59e0b', // orange
        'approved'         => '#22c55e', // green
        'rejected'         => '#dc2626', // dark red
        'released'         => '#16a34a', // darker green
        default             => '#6b7280',
    };
}
