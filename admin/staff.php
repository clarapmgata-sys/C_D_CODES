<?php
session_start();
require_once '../db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Only administrators can access this page.
if (!isset($_SESSION['role']) ||
    strtolower($_SESSION['role']) !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$message = "";
$message_type = "success";

// ---------------------------------------------
// ADD STAFF
// ---------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['add_staff'])) {

    $full_name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $job_title = trim($_POST['job_title'] ?? '');
    $hire_date = trim($_POST['hire_date'] ?? '');
    $password = $_POST['password'] ?? '';

    if (
        $full_name === '' ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        $phone === '' ||
        $job_title === '' ||
        $hire_date === '' ||
        strlen($password) < 8
    ) {
        $message = "Please complete all fields. Use a valid email and a password of at least 8 characters.";
        $message_type = "danger";
    } else {
        try {
            // Check whether the email is already registered.
            $check = $conn->prepare(
                "SELECT user_id FROM users WHERE email = ? LIMIT 1"
            );
            $check->bind_param("s", $email);
            $check->execute();
            $existing = $check->get_result()->fetch_assoc();
            $check->close();

            if ($existing) {
                $message = "This email is already registered. Remove the previous staff account first, or use another email.";
                $message_type = "danger";
            } else {
                $conn->begin_transaction();

                // Create the staff login account.
                $password_hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $role = "staff";
                $status = "active";

                $stmt = $conn->prepare(
                    "INSERT INTO users
                    (full_name, email, phone, password_hash, role, account_status)
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "ssssss",
                    $full_name,
                    $email,
                    $phone,
                    $password_hash,
                    $role,
                    $status
                );

                $stmt->execute();
                $user_id = $conn->insert_id;
                $stmt->close();

                // Create the staff profile.
                $stmt = $conn->prepare(
                    "INSERT INTO staff (user_id, job_title, hire_date)
                     VALUES (?, ?, ?)"
                );

                $stmt->bind_param(
                    "iss",
                    $user_id,
                    $job_title,
                    $hire_date
                );

                $stmt->execute();
                $stmt->close();

                $conn->commit();

                $message = "Staff member added successfully.";
                $message_type = "success";
            }

        } catch (Throwable $e) {
            if ($conn->errno || $conn->connect_errno === 0) {
                // Roll back only if a transaction was started.
                try {
                    $conn->rollback();
                } catch (Throwable $ignored) {
                    // Nothing else to do.
                }
            }

            error_log("Add staff error: " . $e->getMessage());

            if (stripos($e->getMessage(), 'Duplicate entry') !== false) {
                $message = "This email is already registered. Please check the existing accounts.";
            } else {
                $message = "Could not add staff. Check that your database columns match the code.";
            }

            $message_type = "danger";
        }
    }
}

// ---------------------------------------------
// REMOVE STAFF AND FREE THE EMAIL
// ---------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_staff'])) {

    $staff_id = filter_input(
        INPUT_POST,
        'staff_id',
        FILTER_VALIDATE_INT
    );

    if (!$staff_id) {
        $message = "Invalid staff ID.";
        $message_type = "danger";
    } else {
        try {
            $conn->begin_transaction();

            // Find the staff member and their linked user account.
            $stmt = $conn->prepare(
                "SELECT s.staff_id, s.user_id, u.email
                 FROM staff s
                 INNER JOIN users u ON u.user_id = s.user_id
                 WHERE s.staff_id = ?
                   AND u.role = 'staff'
                 FOR UPDATE"
            );

            $stmt->bind_param("i", $staff_id);
            $stmt->execute();
            $staff = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$staff) {
                throw new Exception("Staff account was not found.");
            }

            $user_id = (int)$staff['user_id'];

            // Give the old account a unique placeholder email.
            // This releases the original email for future registration.
            $placeholder_email =
                "removed_staff_" .
                $user_id . "_" .
                bin2hex(random_bytes(6)) .
                "@invalid.local";

            $inactive = "inactive";

            $stmt = $conn->prepare(
                "UPDATE users
                 SET email = ?, account_status = ?
                 WHERE user_id = ? AND role = 'staff'"
            );

            $stmt->bind_param(
                "ssi",
                $placeholder_email,
                $inactive,
                $user_id
            );

            $stmt->execute();

            if ($stmt->affected_rows < 1) {
                $stmt->close();
                throw new Exception("Could not deactivate the account.");
            }

            $stmt->close();

            // Preserve the staff row for existing orders and appointments.
            $conn->commit();

            $message = "Staff account deactivated successfully. The original email address is now available to register again.";
            $message_type = "success";

        } catch (Throwable $e) {
            $conn->rollback();

            error_log("Remove staff error: " . $e->getMessage());

            $message = "Could not remove this staff account. Please check the database error log.";
            $message_type = "danger";
        }
    }
}

// ---------------------------------------------
// GET STAFF LIST
// ---------------------------------------------
$staff_result = $conn->query(
    "SELECT
        s.staff_id,
        s.user_id,
        s.job_title,
        s.hire_date,
        u.full_name,
        u.email,
        u.phone,
        u.account_status
     FROM staff s
     INNER JOIN users u ON u.user_id = s.user_id
     WHERE u.role = 'staff'
     ORDER BY s.staff_id DESC"
);

if (!$staff_result) {
    die("Could not load staff records. Check the database table and column names.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Staff | Clare Designs</title>

    <link rel="stylesheet"
          href="../assets/css/adminlte.min.css">

    <style>
        body {
            background: #f4f6f9;
            font-family: Arial, sans-serif;
            color: #343a40;
        }

        .top-nav {
            background: #007bff;
            border-bottom: 3px solid #0056b3;
            padding: 14px 10px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 6px;
        }

        .top-nav a {
            color: white;
            text-decoration: none;
            padding: 10px 13px;
            border-radius: 5px;
            font-weight: bold;
            font-size: 14px;
        }

        .top-nav a:hover,
        .top-nav a.active {
            background: #0056b3;
        }

        .page-container {
            width: 95%;
            max-width: 1250px;
            margin: 25px auto;
        }

        .card {
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 2px 7px rgba(0,0,0,0.08);
        }

        .card-header {
            background: #e7f1ff;
            border-bottom: 2px solid #007bff;
            font-weight: bold;
            padding: 15px;
        }

        .card-body {
            padding: 20px;
        }

        .form-control {
            margin-bottom: 12px;
        }

        .table thead th {
            background: #007bff;
            color: white;
            vertical-align: middle;
        }

        .table td {
            vertical-align: middle;
        }

        .btn {
            margin: 2px;
        }

        .status-active {
            color: #155724;
            background: #d4edda;
            padding: 5px 8px;
            border-radius: 4px;
        }

        .status-inactive {
            color: #721c24;
            background: #f8d7da;
            padding: 5px 8px;
            border-radius: 4px;
        }

        .email-cell {
            overflow-wrap: anywhere;
        }

        @media (max-width: 700px) {
            .page-container {
                width: 98%;
            }

            .table {
                font-size: 12px;
            }

            .top-nav a {
                font-size: 12px;
                padding: 8px;
            }
        }
    </style>
</head>

<body>

<nav class="top-nav">
    <a href="dashboard.php">Dashboard</a>
    <a href="services.php">Services</a>
    <a href="customers.php">Customers</a>
    <a href="staff.php" class="active">Staff</a>
    <a href="appointments.php">Appointments</a>
    <a href="orders.php">Orders</a>
    <a href="payments.php">Payments</a>
    <a href="designs.php">Designs</a>
    <a href="../logout.php">Logout</a>
</nav>

<div class="page-container">

    <h2 class="mb-4">
        <i class="fas fa-users"></i>
        Manage Staff
    </h2>

    <?php if ($message !== ""): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- ADD STAFF FORM -->
    <div class="card">
        <div class="card-header">
            Add New Staff Member
        </div>

        <div class="card-body">
            <form method="POST">

                <div class="row">

                    <div class="col-md-6">
                        <label>Full Name</label>
                        <input type="text"
                               name="full_name"
                               class="form-control"
                               required>
                    </div>

                    <div class="col-md-6">
                        <label>Email Address</label>
                        <input type="email"
                               name="email"
                               class="form-control"
                               required>
                    </div>

                    <div class="col-md-6">
                        <label>Phone Number</label>
                        <input type="text"
                               name="phone"
                               class="form-control"
                               required>
                    </div>

                    <div class="col-md-6">
                        <label>Job Title</label>
                        <input type="text"
                               name="job_title"
                               class="form-control"
                               placeholder="e.g. Graphic Designer"
                               required>
                    </div>

                    <div class="col-md-6">
                        <label>Hire Date</label>
                        <input type="date"
                               name="hire_date"
                               class="form-control"
                               required>
                    </div>

                    <div class="col-md-6">
                        <label>Login Password</label>
                        <input type="password"
                               name="password"
                               class="form-control"
                               minlength="8"
                               required>
                    </div>

                </div>

                <button type="submit"
                        name="add_staff"
                        class="btn btn-primary">
                    Add Staff
                </button>

            </form>
        </div>
    </div>

    <!-- STAFF LIST -->
    <div class="card">
        <div class="card-header">
            Staff Members
        </div>

        <div class="card-body table-responsive">

            <table class="table table-bordered table-striped">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Job Title</th>
                        <th>Hire Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($staff_result->num_rows > 0): ?>

                    <?php while ($row = $staff_result->fetch_assoc()): ?>

                        <tr>
                            <td>
                                <?php echo (int)$row['staff_id']; ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['full_name'] ?? ''); ?>
                            </td>

                            <td class="email-cell">
                                <?php echo htmlspecialchars($row['email'] ?? ''); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['phone'] ?? ''); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['job_title'] ?? ''); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['hire_date'] ?? ''); ?>
                            </td>

                            <td>
                                <?php if (($row['account_status'] ?? '') === 'active'): ?>
                                    <span class="status-active">Active</span>
                                <?php else: ?>
                                    <span class="status-inactive">Inactive</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (($row['account_status'] ?? '') === 'active'): ?>

                                    <form method="POST"
                                          onsubmit="return confirm('Deactivate this staff account and free its email address?');">

                                        <input type="hidden"
                                               name="staff_id"
                                               value="<?php echo (int)$row['staff_id']; ?>">

                                        <button type="submit"
                                                name="delete_staff"
                                                class="btn btn-danger btn-sm">
                                            Delete
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <span class="text-muted">
                                        Account removed
                                    </span>

                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8" class="text-center">
                            No staff members found.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>
    </div>

</div>

</body>
</html>
