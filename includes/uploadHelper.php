<?php

function uploadAndReplace(
    $conn,
    int $application_id,
    string $fileKey,      // $_FILES key
    string $dbColumn,     // column in documentrequirements
    array $allowedExts,
    string $uploadSubdir = '/uploads/'
) {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $root = realpath(__DIR__ . '/../');
    $fsDir = $root . $uploadSubdir;
    if (!is_dir($fsDir)) mkdir($fsDir, 0777, true);

    // Get old path
    $oldRes = pg_query_params(
        $conn,
        "SELECT {$dbColumn}
           FROM documentrequirements
          WHERE application_id = $1",
        [$application_id]
    );
    $oldPath = ($oldRes && pg_num_rows($oldRes))
        ? pg_fetch_result($oldRes, 0, $dbColumn)
        : null;

    // Validate extension
    $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        return null;
    }

    $file = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $fs   = $fsDir . $file;
    $web  = $uploadSubdir . $file;

    // Upload first
    if (!move_uploaded_file($_FILES[$fileKey]['tmp_name'], $fs)) {
        return null;
    }

    // Save new path
    pg_query_params(
        $conn,
        "INSERT INTO documentrequirements (application_id, {$dbColumn})
         VALUES ($1, $2)
         ON CONFLICT (application_id)
         DO UPDATE SET {$dbColumn} = EXCLUDED.{$dbColumn}",
        [$application_id, $web]
    );

    // Delete old file
    if ($oldPath && $oldPath !== $web) {
        $oldFs = realpath($root . $oldPath);
        $base  = realpath($root . '/uploads/');
        if ($oldFs && str_starts_with($oldFs, $base)) {
            unlink($oldFs);
        }
    }

    return $web;
}
