<?php
session_start();
require_once '../db.php';

/* =====================================
   1. CHECK ADMIN LOGIN
===================================== */
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit;
}

if (strtolower($_SESSION['role']) !== 'admin') {
    die("Access denied. Only administrators can access this page.");
}

/* =====================================
   2. CSRF TOKEN
===================================== */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =====================================
   3. HELPER FUNCTION
===================================== */
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* =====================================
   4. PROCESS ADD, EDIT, DELETE
===================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die("Invalid request. Refresh the page and try again.");
    }

    $action = $_POST['action'] ?? '';
    $message = '';
    $message_type = 'success';

    /* ADD SERVICE */
    if ($action === 'add') {

        $service_name = trim($_POST['service_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price_input = $_POST['base_price'] ?? '';

        if (
            $service_name === '' ||
            $price_input === '' ||
            !is_numeric($price_input) ||
            (float)$price_input < 0
        ) {
            $message = "Enter a service name and a valid price.";
            $message_type = 'danger';

        } else {

            $price = (float)$price_input;

            $stmt = $conn->prepare(
                "INSERT INTO services
                (service_name, description, base_price, is_active)
                VALUES (?, ?, ?, 1)"
            );

            $stmt->bind_param(
                "ssd",
                $service_name,
                $description,
                $price
            );

            if ($stmt->execute()) {
                $message = "Service added successfully!";
            } else {
                $message = "Failed to add service.";
                $message_type = 'danger';
            }

            $stmt->close();
        }
    }

    /* UPDATE SERVICE */
    elseif ($action === 'update') {

        $service_id = (int)($_POST['service_id'] ?? 0);
        $service_name = trim($_POST['service_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price_input = $_POST['base_price'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (
            $service_id <= 0 ||
            $service_name === '' ||
            $price_input === '' ||
            !is_numeric($price_input) ||
            (float)$price_input < 0
        ) {
            $message = "Please enter valid service details.";
            $message_type = 'danger';

        } else {

            $price = (float)$price_input;

            $stmt = $conn->prepare(
                "UPDATE services
                 SET service_name = ?,
                     description = ?,
                     base_price = ?,
                     is_active = ?
                 WHERE service_id = ?"
            );

            $stmt->bind_param(
                "ssdii",
                $service_name,
                $description,
                $price,
                $is_active,
                $service_id
            );

            if ($stmt->execute()) {
                $message = "Service updated successfully!";
            } else {
                $message = "Failed to update service.";
                $message_type = 'danger';
            }

            $stmt->close();
        }
    }

    /* DELETE SERVICE */
    elseif ($action === 'delete') {

        $service_id = (int)($_POST['service_id'] ?? 0);

        if ($service_id <= 0) {

            $message = "Invalid service selected.";
            $message_type = 'danger';

        } else {

            $check = $conn->prepare(
                "SELECT
                    (SELECT COUNT(*)
                     FROM appointments
                     WHERE service_id = ?)
                    +
                    (SELECT COUNT(*)
                     FROM orders
                     WHERE service_id = ?)"
            );

            $check->bind_param(
                "ii",
                $service_id,
                $service_id
            );

            $check->execute();
            $check->bind_result($usage_count);
            $check->fetch();
            $check->close();

            if ($usage_count > 0) {

                $message = "This service is linked to an appointment or order. Deactivate it instead.";
                $message_type = 'warning';

            } else {

                $stmt = $conn->prepare(
                    "DELETE FROM services WHERE service_id = ?"
                );

                $stmt->bind_param("i", $service_id);

                if ($stmt->execute()) {
                    $message = "Service deleted successfully!";
                } else {
                    $message = "Failed to delete service.";
                    $message_type = 'danger';
                }

                $stmt->close();
            }
        }
    }

    /* DEACTIVATE SERVICE */
    elseif ($action === 'deactivate') {

        $service_id = (int)($_POST['service_id'] ?? 0);

        $stmt = $conn->prepare(
            "UPDATE services SET is_active = 0 WHERE service_id = ?"
        );

        $stmt->bind_param("i", $service_id);

        if ($stmt->execute()) {
            $message = "Service deactivated successfully!";
        } else {
            $message = "Failed to deactivate service.";
            $message_type = 'danger';
        }

        $stmt->close();
    }

    /* ACTIVATE SERVICE */
    elseif ($action === 'activate') {

        $service_id = (int)($_POST['service_id'] ?? 0);

        $stmt = $conn->prepare(
            "UPDATE services SET is_active = 1 WHERE service_id = ?"
        );

        $stmt->bind_param("i", $service_id);

        if ($stmt->execute()) {
            $message = "Service activated successfully!";
        } else {
            $message = "Failed to activate service.";
            $message_type = 'danger';
        }

        $stmt->close();
    }

    $_SESSION['services_message'] = $message;
    $_SESSION['services_message_type'] = $message_type;

    header("Location: services.php");
    exit;
}

/* =====================================
   5. DISPLAY FEEDBACK MESSAGE
===================================== */
$message = $_SESSION['services_message'] ?? '';
$message_type = $_SESSION['services_message_type'] ?? 'success';

unset($_SESSION['services_message']);
unset($_SESSION['services_message_type']);

/* =====================================
   6. SEARCH SERVICES
===================================== */
$search = trim($_GET['search'] ?? '');

if ($search !== '') {

    $search_term = "%{$search}%";

    $stmt = $conn->prepare(
        "SELECT service_id, service_name, description,
                base_price, is_active
         FROM services
         WHERE service_name LIKE ?
            OR description LIKE ?
         ORDER BY service_id DESC"
    );

    $stmt->bind_param("ss", $search_term, $search_term);
    $stmt->execute();

    $services_result = $stmt->get_result();

} else {

    $services_result = $conn->query(
        "SELECT service_id, service_name, description,
                base_price, is_active
         FROM services
         ORDER BY service_id DESC"
    );
}

/* =====================================
   7. SUMMARY COUNTS
===================================== */
$total_result = $conn->query(
    "SELECT COUNT(*) AS total FROM services"
);

$total_services = $total_result->fetch_assoc()['total'];

$active_result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM services WHERE is_active = 1"
);

$active_services = $active_result->fetch_assoc()['total'];

$inactive_result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM services WHERE is_active = 0"
);

$inactive_services = $inactive_result->fetch_assoc()['total'];

/* =====================================
   8. GET SERVICE FOR EDITING
===================================== */
$edit_service = null;

if (isset($_GET['edit'])) {

    $edit_id = (int)$_GET['edit'];

    $stmt = $conn->prepare(
        "SELECT service_id, service_name, description,
                base_price, is_active
         FROM services
         WHERE service_id = ?"
    );

    $stmt->bind_param("i", $edit_id);
    $stmt->execute();

    $edit_result = $stmt->get_result();
    $edit_service = $edit_result->fetch_assoc();

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Services | Clare Designs</title>

    <!-- ADMINLTE CSS -->
    <link rel="stylesheet"
          href="../assets/css/adminlte.min.css">

    <style>
       body {
    background: #f4f6f9;
    font-family: Arial, sans-serif;
    color: #343a40;
    margin: 0;
}

/* BLUE HORIZONTAL NAVIGATION */
.main-header {
    width: 100%;
    margin-left: 0 !important;
    padding: 10px 18px;
    background: #007bff !important;
    border-bottom: 3px solid #0056b3;
    display: flex !important;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    position: relative;
}

.navbar-brand {
    color: #fff !important;
    font-size: 20px;
    font-weight: bold;
    margin-right: 20px;
}

.main-header .navbar-nav {
    display: flex !important;
    flex-direction: row !important;
    align-items: center;
    flex-wrap: wrap;
    padding: 0;
    margin: 0;
    list-style: none;
}

.main-header .nav-item {
    display: block !important;
}

.main-header .nav-link {
    display: block !important;
    color: #fff !important;
    font-weight: 600;
    padding: 10px 12px !important;
    margin: 3px;
    border-radius: 5px;
    transition: 0.2s;
}

.main-header .nav-link:hover,
.main-header .nav-link.active {
    background: #0056b3 !important;
    color: white !important;
}

/* REMOVE SIDEBAR */
.main-sidebar {
    display: none !important;
}

.content-wrapper,
.main-footer {
    margin-left: 0 !important;
}

.content-wrapper {
    background: #f4f6f9;
    min-height: calc(100vh - 130px);
}

.content-header {
    padding: 25px 20px 10px;
}

.content-header h1 {
    color: #007bff;
    font-weight: bold;
}

/* CARDS */
.card {
    border: none;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
    margin-bottom: 24px;
    overflow: hidden;
}

.card-header {
    background: #e7f1ff;
    border-bottom: 3px solid #007bff;
    padding: 15px 18px;
}

.card-title {
    font-weight: bold;
    color: #0056b3;
    margin: 0;
}

.card-body {
    padding: 20px;
}

/* SUMMARY CARDS */
.summary-card {
    background: white;
    border-left: 5px solid #007bff;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 22px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
}

.summary-card h3 {
    font-size: 30px;
    color: #007bff;
    font-weight: bold;
    margin: 0 0 5px;
}

.summary-card p {
    margin: 0;
    color: #6c757d;
}

.summary-card.active-card {
    border-left-color: #28a745;
}

.summary-card.inactive-card {
    border-left-color: #dc3545;
}

/* FORMS */
.form-control {
    border-radius: 5px;
}

.form-control:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 .2rem rgba(0, 123, 255, .15);
}

/* BUTTONS */
.btn-primary {
    background: #007bff;
    border-color: #007bff;
}

.btn-primary:hover {
    background: #0056b3;
    border-color: #0056b3;
}

/* SERVICES TABLE */
.table thead th {
    background: #007bff;
    color: white;
    white-space: nowrap;
}

.table-striped tbody tr:nth-of-type(odd) {
    background: #f0f6ff;
}

.table tbody tr:hover {
    background: #e2edff;
}

.table td,
.table th {
    vertical-align: middle;
}

/* SERVICE DESCRIPTION */
.service-description {
    min-width: 180px;
    max-width: 300px;
    overflow-wrap: anywhere;
}

/* ACTION BUTTONS */
.action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.action-buttons form {
    margin: 0;
}

/* STATUS LABELS */
.status-badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-inactive {
    background: #f8d7da;
    color: #721c24;
}

/* FOOTER */
.main-footer {
    background: white;
    border-top: 2px solid #007bff;
    padding: 16px;
    color: #6c757d;
    text-align: center;
}

/* RESPONSIVE NAVIGATION */
@media (max-width: 900px) {
    .main-header {
        justify-content: center;
    }

    .navbar-brand {
        width: 100%;
        text-align: center;
        margin-bottom: 8px;
    }

    .main-header .navbar-nav {
        justify-content: center;
    }

    .main-header .nav-link {
        padding: 8px !important;
        font-size: 13px;
    }
}
    </style>
</head>

<body class="hold-transition">

<!-- =====================================
     HEADER AND NAVIGATION
===================================== -->
<nav class="main-header navbar navbar-expand navbar-primary">
    <a href="dashboard.php" class="navbar-brand">CLARE DESIGNS</a>


<ul class="navbar-nav">
    <li class="nav-item">
        <a href="dashboard.php" class="nav-link">Dashboard</a>
    </li>

    <li class="nav-item">
        <a href="services.php" class="nav-link">Services</a>
    </li>

    <li class="nav-item">
        <a href="customers.php" class="nav-link">Customers</a>
    </li>

    <li class="nav-item">
        <a href="staff.php" class="nav-link">Staff</a>
    </li>

    <li class="nav-item">
        <a href="appointments.php" class="nav-link">Appointments</a>
    </li>

    <li class="nav-item">
        <a href="orders.php" class="nav-link">Orders</a>
    </li>

    <li class="nav-item">
        <a href="payments.php" class="nav-link">Payments</a>
    </li>

    <li class="nav-item">
        <a href="designs.php" class="nav-link">Designs</a>
    </li>

    <li class="nav-item">
        <a href="../logout.php" class="nav-link">Logout</a>
    </li>
</ul>


</nav>


<!-- =====================================
     MAIN CONTENT
===================================== -->
<div class="content-wrapper">

    <div class="content-header">
        <div class="container-fluid">

            <h1>Services Management</h1>

            <p>
                Manage the design services offered by Clare Designs.
            </p>

        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <!-- FEEDBACK MESSAGE -->
            <?php if ($message !== ''): ?>

                <div class="alert alert-<?= e($message_type) ?> alert-dismissible">
                    <button type="button"
                            class="close"
                            data-dismiss="alert"
                            aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>

                    <?= e($message) ?>
                </div>

            <?php endif; ?>

            <!-- SUMMARY CARDS -->
            <div class="row">

                <div class="col-lg-4 col-md-6">
                    <div class="summary-card">
                        <h3><?= (int)$total_services ?></h3>
                        <p>Total Services</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="summary-card active-card">
                        <h3><?= (int)$active_services ?></h3>
                        <p>Active Services</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="summary-card inactive-card">
                        <h3><?= (int)$inactive_services ?></h3>
                        <p>Inactive Services</p>
                    </div>
                </div>

            </div>

            <!-- ADD OR EDIT SERVICE -->
            <div class="card">

                <div class="card-header">
                    <h3 class="card-title">
                        <?= $edit_service ? 'Edit Service' : 'Add New Service' ?>
                    </h3>
                </div>

                <div class="card-body">

                    <form method="POST" action="services.php">

                        <input type="hidden"
                               name="csrf_token"
                               value="<?= e($_SESSION['csrf_token']) ?>">

                        <?php if ($edit_service): ?>

                            <input type="hidden"
                                   name="action"
                                   value="update">

                            <input type="hidden"
                                   name="service_id"
                                   value="<?= (int)$edit_service['service_id'] ?>">

                        <?php else: ?>

                            <input type="hidden"
                                   name="action"
                                   value="add">

                        <?php endif; ?>

                        <div class="row">

                            <div class="col-md-6">
                                <div class="form-group">

                                    <label for="service_name">
                                        Service Name *
                                    </label>

                                    <input type="text"
                                           name="service_name"
                                           id="service_name"
                                           class="form-control"
                                           placeholder="Example: Logo Design"
                                           maxlength="150"
                                           required
                                           value="<?= e($edit_service['service_name'] ?? '') ?>">

                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">

                                    <label for="base_price">
                                        Base Price (TZS) *
                                    </label>

                                    <input type="number"
                                           name="base_price"
                                           id="base_price"
                                           class="form-control"
                                           placeholder="Example: 25000"
                                           min="0"
                                           step="0.01"
                                           required
                                           value="<?= e($edit_service['base_price'] ?? '') ?>">

                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-group">

                                    <label for="description">
                                        Service Description
                                    </label>

                                    <textarea name="description"
                                              id="description"
                                              class="form-control"
                                              rows="4"
                                              placeholder="Describe this service..."><?= e($edit_service['description'] ?? '') ?></textarea>

                                </div>
                            </div>

                            <?php if ($edit_service): ?>

                                <div class="col-md-12">
                                    <div class="form-group">

                                        <label>
                                            <input type="checkbox"
                                                   name="is_active"
                                                   value="1"
                                                <?= ((int)$edit_service['is_active'] === 1) ? 'checked' : '' ?>>

                                            Service is active
                                        </label>

                                    </div>
                                </div>

                            <?php endif; ?>

                            <div class="col-md-12">

                                <button type="submit" class="btn btn-primary">
                                    <?= $edit_service ? 'Update Service' : 'Save Service' ?>
                                </button>

                                <?php if ($edit_service): ?>

                                    <a href="services.php"
                                       class="btn btn-secondary">
                                        Cancel
                                    </a>

                                <?php endif; ?>

                            </div>

                        </div>

                    </form>

                </div>
            </div>

            <!-- SERVICES TABLE -->
            <div class="card">

                <div class="card-header">
                    <h3 class="card-title">All Services</h3>
                </div>

                <div class="card-body">

                    <!-- SEARCH -->
                    <form method="GET"
                          action="services.php"
                          class="mb-4">

                        <div class="row">

                            <div class="col-md-9">
                                <input type="text"
                                       name="search"
                                       class="form-control"
                                       placeholder="Search services..."
                                       value="<?= e($search) ?>">
                            </div>

                            <div class="col-md-3 mt-2 mt-md-0">

                                <button type="submit"
                                        class="btn btn-primary">
                                    Search
                                </button>

                                <a href="services.php"
                                   class="btn btn-secondary">
                                    Reset
                                </a>

                            </div>

                        </div>

                    </form>

                    <!-- TABLE -->
                    <div class="table-responsive">

                        <table class="table table-bordered table-striped table-hover">

                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Service Name</th>
                                    <th>Description</th>
                                    <th>Price (TZS)</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php if ($services_result && $services_result->num_rows > 0): ?>

                                    <?php while ($service = $services_result->fetch_assoc()): ?>

                                        <tr>

                                            <td>
                                                <?= (int)$service['service_id'] ?>
                                            </td>

                                            <td>
                                                <strong>
                                                    <?= e($service['service_name']) ?>
                                                </strong>
                                            </td>

                                            <td class="service-description">
                                                <?= e($service['description'] ?: 'No description') ?>
                                            </td>

                                            <td>
                                                <?= number_format((float)$service['base_price'], 2) ?>
                                            </td>

                                            <td>

                                                <?php if ((int)$service['is_active'] === 1): ?>

                                                    <span class="status-badge status-active">
                                                        Active
                                                    </span>

                                                <?php else: ?>

                                                    <span class="status-badge status-inactive">
                                                        Inactive
                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                            <td>

                                                <div class="action-buttons">

                                                    <!-- EDIT BUTTON -->
                                                    <a href="services.php?edit=<?= (int)$service['service_id'] ?>"
                                                       class="btn btn-info btn-sm">
                                                        Edit
                                                    </a>

                                                    <!-- ACTIVATE OR DEACTIVATE -->
                                                    <form method="POST"
                                                          action="services.php"
                                                          onsubmit="return confirm('Change this service status?');">

                                                        <input type="hidden"
                                                               name="csrf_token"
                                                               value="<?= e($_SESSION['csrf_token']) ?>">

                                                        <input type="hidden"
                                                               name="service_id"
                                                               value="<?= (int)$service['service_id'] ?>">

                                                        <?php if ((int)$service['is_active'] === 1): ?>

                                                            <input type="hidden"
                                                                   name="action"
                                                                   value="deactivate">

                                                            <button type="submit"
                                                                    class="btn btn-warning btn-sm">
                                                                Deactivate
                                                            </button>

                                                        <?php else: ?>

                                                            <input type="hidden"
                                                                   name="action"
                                                                   value="activate">

                                                            <button type="submit"
                                                                    class="btn btn-success btn-sm">
                                                                Activate
                                                            </button>

                                                        <?php endif; ?>

                                                    </form>

                                                    <!-- DELETE BUTTON -->
                                                    <form method="POST"
                                                          action="services.php"
                                                          onsubmit="return confirm('Are you sure you want to delete this service?');">

                                                        <input type="hidden"
                                                               name="csrf_token"
                                                               value="<?= e($_SESSION['csrf_token']) ?>">

                                                        <input type="hidden"
                                                               name="action"
                                                               value="delete">

                                                        <input type="hidden"
                                                               name="service_id"
                                                               value="<?= (int)$service['service_id'] ?>">

                                                        <button type="submit"
                                                                class="btn btn-danger btn-sm">
                                                            Delete
                                                        </button>

                                                    </form>

                                                </div>

                                            </td>

                                        </tr>

                                    <?php endwhile; ?>

                                <?php else: ?>

                                    <tr>
                                        <td colspan="6" class="text-center">
                                            No services found.
                                        </td>
                                    </tr>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>
            </div>

        </div>
    </section>

</div>

<!-- FOOTER -->
<footer class="main-footer">
    <strong>
        &copy; <?= date('Y') ?> Clare Designs.
    </strong>
    All rights reserved.
</footer>

<script>
    // Hide notification after five seconds.
    setTimeout(function () {
        const alertBox = document.querySelector('.alert');

        if (alertBox) {
            alertBox.style.transition = 'opacity 0.5s';
            alertBox.style.opacity = '0';

            setTimeout(function () {
                alertBox.remove();
            }, 500);
        }
    }, 5000);
</script>

</body>
</html>