<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/paths.php';
require_once __DIR__ . '/../../config/db.php';

/* ------------------ ACCESS CONTROL ------------------ */
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header('Location: ' . ADMIN_BASE . '/signin.php');
    exit;
}

/* ------------------ ADD USER ------------------ */
if (isset($_POST['add_user'])) {

    $full_name  = trim($_POST['full_name']);
    $designation = trim($_POST['designation']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role     = trim($_POST['role']);
    $access   = $_POST['access_level'];

    if ($username && $password && $role && $access) {

        $check = pg_query_params($conn,
            "SELECT id FROM user_admin WHERE username = $1",
            [$username]
        );

        if (pg_num_rows($check) > 0) {
            die("Username already exists.");
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);

             pg_query_params($conn,
            "INSERT INTO user_admin 
            (full_name, username, password, role, access_level, designation, status)
            VALUES ($1, $2, $3, $4, $5, $6, 'active')",
            [$full_name, $username, $hashed, $role, $access, $designation]
        );

        header("Location: manage_admins.php");
        exit;
    }
}

/* ------------------ UPDATE ACCESS LEVEL ------------------ */
if (isset($_POST['update_access'])) {

    $id = (int) $_POST['id'];
    $access = $_POST['access_level'];

    if ($role === 'super_admin') {
    $access = 'full';
}

    if ($role === 'super_admin') {
    $access = 'full';
}

    if ($id == $_SESSION['user_id']) {
        die("You cannot change your own access.");
    }

    // 🔒 NEW: block editing super_admin
    $resCheck = pg_query_params($conn,
        "SELECT role FROM user_admin WHERE id = $1",
        [$id]
    );

    $userCheck = pg_fetch_assoc($resCheck);

    if ($userCheck && $userCheck['role'] === 'super_admin') {
        die("Cannot modify super admin.");
    }

    if (!in_array($access, ['full', 'edit', 'view'])) {
        die("Invalid access level.");
    }

    pg_query_params($conn,
        "UPDATE user_admin SET access_level = $1 WHERE id = $2",
        [$access, $id]
    );

    header("Location: manage_admins.php");
    exit;
}

/* ------------------ UPDATE USER ------------------ */
if (isset($_POST['update_user'])) {

    $id           = (int) $_POST['id'];
    $full_name    = trim($_POST['full_name']);
    $username     = trim($_POST['username']);
    $designation  = trim($_POST['designation']);
    $role         = trim($_POST['role']);
    $access       = trim($_POST['access_level']);
    $status = trim($_POST['status']);

if ($id == $_SESSION['user_id']) {
    die("You cannot edit your own account.");
}

$checkUsername = pg_query_params($conn,
    "SELECT id FROM user_admin 
     WHERE username = $1 AND id != $2",
    [$username, $id]
);

if (pg_num_rows($checkUsername) > 0) {
    die("Username already exists.");
}

    // protect super admin
    $resCheck = pg_query_params($conn,
        "SELECT role FROM user_admin WHERE id = $1",
        [$id]
    );

    $userCheck = pg_fetch_assoc($resCheck);

    if ($userCheck && $userCheck['role'] === 'super_admin') {
        die("Cannot modify super admin.");
    }

    pg_query_params($conn,
        "UPDATE user_admin
         SET full_name = $1,
             username = $2,
             designation = $3,
             role = $4,
             access_level = $5,
        status = $6
        WHERE id = $7",
        [$full_name, $username, $designation, $role, $access, $status, $id]
    );

    header("Location: manage_admins.php");
    exit;
}

/* ------------------ TOGGLE USER STATUS ------------------ */
if (isset($_POST['toggle_status'])) {

    $id = (int) $_POST['id'];


if ($id == $_SESSION['user_id']) {
    die("You cannot deactivate your own account.");
}

    // protect super admin
    $resCheck = pg_query_params($conn,
        "SELECT role, status FROM user_admin WHERE id = $1",
        [$id]
    );

    $userCheck = pg_fetch_assoc($resCheck);

    if ($userCheck && $userCheck['role'] === 'super_admin') {
        die("Cannot modify super admin.");
    }

    $newStatus = $userCheck['status'] === 'active'
        ? 'inactive'
        : 'active';

    pg_query_params($conn,
        "UPDATE user_admin SET status = $1 WHERE id = $2",
        [$newStatus, $id]
    );

    header("Location: manage_admins.php");
    exit;
}

/* ------------------ FETCH USERS ------------------ */
$result = pg_query($conn,
    "SELECT id,
       full_name,
       username,
       role,
       designation,
       access_level,
       status
     FROM user_admin
     ORDER BY id ASC"
);

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<title>User Management</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
body {
    background: #f1f5f9;
    font-family: 'Segoe UI', sans-serif;
}
.main-content {
    margin-left: 80px;
    padding: 30px;
}
body.sidebar-expanded .main-content {
    margin-left: 260px;
}
.card {
    border-radius: 16px;
    border: none;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
}

.designation {
    color: #64748b;
    font-size: 13px;
}

.table {
    border-spacing: 0 8px;
}

.table tbody tr {
    transition: 0.2s ease;
}

.table tbody tr:hover {
    background: #f8fafc;
}

.role-badge {
    padding: 6px 14px;
    border-radius: 50px;
    font-size: 11px;
}
.admin { background: rgba(13,110,253,0.1); color: #0d6efd; }
.doctor { background: rgba(25,135,84,0.1); color: #198754; }
.super_admin { background: rgba(220,53,69,0.1); color: #dc3545; }
</style>

</head>

<body>

<?php include __DIR__ . '/../../includes/pdao_sidebar.php'; ?>

<div class="main-content">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>User Management</h2>

    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fas fa-user-plus me-1"></i> Add User
    </button>
</div>

<!-- USERS TABLE -->
<div class="card">
<div class="card-body">

<div class="table-responsive">
<table class="table align-middle">

<thead>
<tr>
    <th>ID</th>
    <th>Full Name</th>
    <th>Username</th>
    <th>Role</th>
    <th>Designation</th>
    <th>Status</th>
    <th>Access</th>
    <th>Actions</th>
</tr>
</thead>

<tbody>

<?php while ($row = pg_fetch_assoc($result)): ?>

<tr>

<td><?= $row['id'] ?></td>
<td><?= htmlspecialchars($row['full_name'] ?? '-') ?></td>
<td><?= htmlspecialchars($row['username'] ?? '-') ?></td>

<td>
    <span class="role-badge <?= $row['role'] ?>">
        <?= strtoupper($row['role']) ?>
    </span>
</td>

<td class="designation">
    <?= htmlspecialchars($row['designation'] ?? '-') ?>
</td>

<td>

<?php if ($row['status'] === 'active'): ?>

    <span class="badge bg-success">
        Active
    </span>

<?php else: ?>

    <span class="badge bg-danger">
        Inactive
    </span>

<?php endif; ?>

</td>

<!-- ACCESS -->
<td>

<?php if (
    $row['role'] === 'super_admin' ||
    $row['id'] == $_SESSION['user_id']
): ?>

    <span class="text-muted">Protected</span>

<?php else: ?>

<form method="POST">

    <input type="hidden" name="id" value="<?= $row['id'] ?>">
    <input type="hidden" name="update_access" value="1">

    <select name="access_level"
            class="form-select form-select-sm"
            onchange="this.form.submit()">

        <option value="full"
            <?= $row['access_level']=='full'?'selected':'' ?>>
            Full
        </option>

        <option value="edit"
            <?= $row['access_level']=='edit'?'selected':'' ?>>
            Edit
        </option>

        <option value="view"
            <?= $row['access_level']=='view'?'selected':'' ?>>
            View
        </option>

    </select>

</form>

<?php endif; ?>

</td>

<!-- ACTIONS -->
<td class="align-middle">

<?php if (
    $row['role'] === 'super_admin' ||
    $row['id'] == $_SESSION['user_id']
): ?>

    <span class="text-muted">Protected</span>

<?php else: ?>

<button
    class="btn btn-primary btn-sm"
    data-bs-toggle="modal"
    data-bs-target="#editModal<?= $row['id'] ?>">

    <i class="fas fa-pen"></i>

</button>

<?php endif; ?>

</td>


<!-- EDIT MODAL -->
<div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">

<div class="modal-header">
    <h5 class="modal-title">Edit User</h5>
    <button class="btn-close" data-bs-dismiss="modal"></button>
</div>

<form method="POST">

<div class="modal-body">

<input type="hidden" name="id" value="<?= $row['id'] ?>">

<div class="mb-3">
    <label>Full Name</label>
    <input type="text"
           name="full_name"
           class="form-control"
           value="<?= htmlspecialchars($row['full_name'] ?? '') ?>"
           required>
</div>

<div class="mb-3">
    <label>Username</label>
    <input type="text"
           name="username"
           class="form-control"
           value="<?= htmlspecialchars($row['username'] ?? '') ?>"
           required>
</div>

<div class="mb-3">
    <label>Designation</label>
    <input type="text"
           name="designation"
           class="form-control"
           value="<?= htmlspecialchars($row['designation'] ?? '') ?>">
</div>

<div class="mb-3">
    <label>Role</label>
    <select name="role" class="form-select">

        <option value="admin"
            <?= $row['role']=='admin'?'selected':'' ?>>
            Admin
        </option>

        <option value="doctor"
            <?= $row['role']=='doctor'?'selected':'' ?>>
            Doctor
        </option>

    </select>
</div>

<div class="mb-3">
    <label>Access Level</label>
    <select name="access_level" class="form-select">

        <option value="full"
            <?= $row['access_level']=='full'?'selected':'' ?>>
            Full
        </option>

        <option value="edit"
            <?= $row['access_level']=='edit'?'selected':'' ?>>
            Edit
        </option>

        <option value="view"
            <?= $row['access_level']=='view'?'selected':'' ?>>
            View
        </option>

    </select>
</div>

<div class="mb-3">
    <label>Status</label>

    <select name="status" class="form-select">

        <option value="active"
            <?= $row['status']=='active'?'selected':'' ?>>
            Active
        </option>

        <option value="inactive"
            <?= $row['status']=='inactive'?'selected':'' ?>>
            Inactive
        </option>

    </select>
</div>

</div>

<div class="modal-footer">
    <button class="btn btn-light" data-bs-dismiss="modal">
        Cancel
    </button>

    <button type="submit"
            name="update_user"
            class="btn btn-primary">
        Save Changes
    </button>
</div>

</form>

</div>
</div>
</div>

</tr>

<?php endwhile; ?>

</tbody>
</div>
</table>

</div>
</div>

</div>

<!-- MODAL -->
<div class="modal fade" id="addUserModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">

<div class="modal-header">
    <h5 class="modal-title">Add User</h5>
    <button class="btn-close" data-bs-dismiss="modal"></button>
</div>

<form method="POST">

<div class="modal-body">

<div class="mb-3">
    <label>Full Name</label>
    <input type="text" name="full_name" class="form-control" required>
</div>

<div class="mb-3">
    <label>Username</label>
    <input type="text" name="username" class="form-control" required>
</div>

<div class="mb-3">
    <label>Password</label>
    <input type="password" name="password" class="form-control" required>
</div>

<div class="mb-3">
    <label>Role</label>
    <select name="role" class="form-select" required>
        <option value="admin">Admin</option>
        <option value="doctor">Doctor</option>
        <option value="super_admin">Super Admin</option>
    </select>
</div>


<div class="mb-3">
    <label>Designation</label>
    <input type="text" name="designation" required class="form-control" placeholder="e.g. PWD Officer">
</div>

<div class="mb-3">
    <label>Access Level</label>
    <select name="access_level" class="form-select" required>
        <option value="full">Full</option>
        <option value="edit">Edit</option>
        <option value="view" selected>View</option>
    </select>
</div>

</div>

<div class="modal-footer">
    <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
    <button type="submit" name="add_user" class="btn btn-success">
        Create
    </button>
</div>

</form>

</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>