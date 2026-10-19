<?php
session_start();
require_once '../db.php';

/* =========================================
   1. CHECK STAFF LOGIN
========================================= */

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../login.php");
    exit;
}

if (!isset($_SESSION['staff_id']) || empty($_SESSION['staff_id'])) {
    die("Staff account not found. Please log in again or contact the administrator.");
}

$staff_id = (int) $_SESSION['staff_id'];
$message = "";
$message_type = "success";

/* =========================================
   2. UPDATE ORDER STATUS
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {

    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $new_status = $_POST['order_status'] ?? '';

    $allowed_statuses = ['Pending', 'In Progress', 'Completed'];

    if (!$order_id || !in_array($new_status, $allowed_statuses, true)) {
        $message = "Invalid order or status.";
        $message_type = "danger";
    } else {
        /*
         * The staff member can update only an order
         * assigned to their own staff ID.
         */
        $check = $conn->prepare(
            "SELECT order_id
             FROM orders
             WHERE order_id = ? AND staff_id = ?"
        );

        if (!$check) {
            die("Database error: " . htmlspecialchars($conn->error));
        }

        $check->bind_param("ii", $order_id, $staff_id);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows === 1) {

            if ($new_status === 'Completed') {
                $update = $conn->prepare(
                    "UPDATE orders
                     SET order_status = ?, delivery_date = CURDATE()
                     WHERE order_id = ? AND staff_id = ?"
                );
            } else {
                $update = $conn->prepare(
                    "UPDATE orders
                     SET order_status = ?, delivery_date = NULL
                     WHERE order_id = ? AND staff_id = ?"
                );
            }

            if (!$update) {
                die("Database error: " . htmlspecialchars($conn->error));
            }

            $update->bind_param("sii", $new_status, $order_id, $staff_id);

            if ($update->execute()) {
                $message = "Order status updated successfully.";
                $message_type = "success";
            } else {
                $message = "Unable to update the order status.";
                $message_type = "danger";
            }

            $update->close();

        } else {
            $message = "You cannot update an order that is not assigned to you.";
            $message_type = "danger";
        }

        $check->close();
    }
}

/* =========================================
   3. GET STAFF NAME
========================================= */

$staff_name = $_SESSION['full_name'] ?? 'Staff Member';

$staff_query = $conn->prepare(
    "SELECT u.full_name
     FROM staff s
     INNER JOIN users u ON s.user_id = u.user_id
     WHERE s.staff_id = ?"
);

if ($staff_query) {
    $staff_query->bind_param("i", $staff_id);
    $staff_query->execute();
    $staff_result = $staff_query->get_result();

    if ($staff_result->num_rows === 1) {
        $staff_data = $staff_result->fetch_assoc();
        $staff_name = $staff_data['full_name'];
    }

    $staff_query->close();
}

/* =========================================
   4. GET ORDERS ASSIGNED TO THIS STAFF
========================================= */

$sql = "SELECT
            o.order_id,
            o.order_date,
            o.deadline,
            o.requirements,
            o.agreed_price,
            o.order_status,
            o.preview_file,
            o.final_design_file,
            o.delivery_date,
            c.full_name AS customer_name,
            c.business_name,
            c.email AS customer_email,
            c.phone AS customer_phone,
            s.service_name
        FROM orders o
        INNER JOIN customers c ON o.customer_id = c.customer_id
        INNER JOIN services s ON o.service_id = s.service_id
        WHERE o.staff_id = ?
        ORDER BY
            CASE
                WHEN o.order_status = 'In Progress' THEN 1
                WHEN o.order_status = 'Pending' THEN 2
                WHEN o.order_status = 'Completed' THEN 3
                ELSE 4
            END,
            o.deadline ASC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die(
        "Unable to retrieve orders. Please check your database tables and columns. " .
        htmlspecialchars($conn->error)
    );
}

$stmt->bind_param("i", $staff_id);
$stmt->execute();
$orders = $stmt->get_result();

$total_orders = $orders->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Orders | Clare Designs</title>

    <link rel="stylesheet"
          href="../assets/css/adminlte.min.css">

    <style>
        body {
            background: #f4f6f9;
            font-family: Arial, sans-serif;
            color: #343a40;
            margin: 0;
        }

        .top-nav {
            background: #007bff;
            padding: 14px 20px;
            border-bottom: 3px solid #0056b3;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .brand {
            color: white;
            font-size: 20px;
            font-weight: bold;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 9px 12px;
            border-radius: 4px;
            font-size: 14px;
        }

        .nav-links a:hover,
        .nav-links a.active {
            background: #0056b3;
        }

        .container-page {
            width: 95%;
            max-width: 1500px;
            margin: 25px auto;
        }

        .page-heading {
            margin-bottom: 20px;
        }

        .page-heading h2 {
            font-weight: bold;
            margin-bottom: 6px;
        }

        .summary-card {
            background: white;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.08);
            border-left: 5px solid #007bff;
        }

        .summary-card h3 {
            color: #007bff;
            font-size: 28px;
            margin: 0;
        }

        .summary-card p {
            margin: 5px 0 0;
        }

        .card {
            border-radius: 6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.08);
            border: none;
        }

        .card-header {
            background: #e7f1ff;
            border-bottom: 2px solid #007bff;
            padding: 15px 20px;
            font-weight: bold;
        }

        .table thead th {
            background: #007bff;
            color: white;
            vertical-align: middle;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background: #f2f7ff;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-progress {
            background: #cce5ff;
            color: #004085;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-other {
            background: #e2e3e5;
            color: #383d41;
        }

        .requirements {
            min-width: 180px;
            max-width: 280px;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .status-form {
            min-width: 190px;
        }

        .status-form select {
            margin-bottom: 6px;
        }

        .action-links a {
            display: inline-block;
            margin: 2px 4px 2px 0;
        }

        .empty-state {
            text-align: center;
            padding: 35px 15px;
        }

        @media (max-width: 768px) {
            .container-page {
                width: 98%;
            }

            .top-nav {
                padding: 12px;
            }

            .nav-links a {
                padding: 8px;
            }
        }
    </style>
</head>

<body>

<!-- TOP NAVIGATION -->
<nav class="top-nav">
    <a href="staff_dashboard.php" class="brand">
        CLARE DESIGNS
    </a>

    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="my_orders.php" class="active">My Orders</a>
        <a href="upload_design.php">Upload Design</a>
        <a href="../logout.php">Logout</a>
    </div>
</nav>

<div class="container-page">

    <!-- PAGE HEADING -->
    <div class="page-heading">
        <h2>My Orders</h2>
        <p>
            Welcome, <strong><?= htmlspecialchars($staff_name) ?></strong>.
            View and manage the design orders assigned to you.
        </p>
    </div>

    <!-- NOTIFICATION -->
    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($message_type) ?> alert-dismissible">
            <?= htmlspecialchars($message) ?>

            <button type="button"
                    class="close"
                    data-dismiss="alert"
                    aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- TOTAL ORDERS -->
    <div class="summary-card">
        <h3><?= (int) $total_orders ?></h3>
        <p>Total orders assigned to you</p>
    </div>

    <!-- ORDERS TABLE -->
    <div class="card">

        <div class="card-header">
            Orders Assigned to Me
        </div>

        <div class="card-body">

            <?php if ($total_orders > 0): ?>

                <div class="table-responsive">

                    <table class="table table-bordered table-striped">

                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Service</th>
                                <th>Order Date</th>
                                <th>Deadline</th>
                                <th>Requirements</th>
                                <th>Agreed Price</th>
                                <th>Status</th>
                                <th>Design Files</th>
                                <th>Update Status</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php while ($order = $orders->fetch_assoc()): ?>

                            <?php
                            $status = $order['order_status'] ?? 'Pending';

                            if ($status === 'Pending') {
                                $status_class = 'status-pending';
                            } elseif ($status === 'In Progress') {
                                $status_class = 'status-progress';
                            } elseif ($status === 'Completed') {
                                $status_class = 'status-completed';
                            } else {
                                $status_class = 'status-other';
                            }
                            ?>

                            <tr>

                                <td>
                                    #<?= (int) $order['order_id'] ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($order['customer_name'] ?? '') ?>
                                    </strong>

                                    <?php if (!empty($order['business_name'])): ?>
                                        <br>
                                        <small>
                                            <?= htmlspecialchars($order['business_name']) ?>
                                        </small>
                                    <?php endif; ?>

                                    <?php if (!empty($order['customer_phone'])): ?>
                                        <br>
                                        <small>
                                            Phone:
                                            <?= htmlspecialchars($order['customer_phone']) ?>
                                        </small>
                                    <?php endif; ?>

                                    <?php if (!empty($order['customer_email'])): ?>
                                        <br>
                                        <small>
                                            <?= htmlspecialchars($order['customer_email']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($order['service_name'] ?? '') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($order['order_date'] ?? '') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($order['deadline'] ?? '') ?>
                                </td>

                                <td class="requirements">
                                    <?= nl2br(htmlspecialchars($order['requirements'] ?? 'No requirements provided')) ?>
                                </td>

                                <td>
                                    <?= number_format((float) ($order['agreed_price'] ?? 0), 2) ?>
                                </td>

                                <td>
                                    <span class="status-badge <?= $status_class ?>">
                                        <?= htmlspecialchars($status) ?>
                                    </span>

                                    <?php if (!empty($order['delivery_date'])): ?>
                                        <br>
                                        <small>
                                            Delivered:
                                            <?= htmlspecialchars($order['delivery_date']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>

                                <td class="action-links">

                                    <?php if (!empty($order['preview_file'])): ?>
                                        <a class="btn btn-sm btn-info"
                                           href="../uploads/designs/<?= rawurlencode(basename($order['preview_file'])) ?>"
                                           target="_blank"
                                           rel="noopener">
                                            Preview
                                        </a>
                                    <?php endif; ?>

                                    <?php if (!empty($order['final_design_file'])): ?>
                                        <a class="btn btn-sm btn-success"
                                           href="../uploads/designs/<?= rawurlencode(basename($order['final_design_file'])) ?>"
                                           target="_blank"
                                           rel="noopener">
                                            Final Design
                                        </a>
                                    <?php endif; ?>

                                    <?php if (empty($order['preview_file']) && empty($order['final_design_file'])): ?>
                                        <span class="text-muted">No files yet</span>
                                    <?php endif; ?>

                                    <br>

                                    <a class="btn btn-sm btn-primary"
                                       href="upload_design.php?order_id=<?= (int) $order['order_id'] ?>">
                                        Upload Design
                                    </a>

                                </td>

                                <td>
                                    <form method="POST"
                                          action="my_orders.php"
                                          class="status-form"
                                          onsubmit="return confirm('Are you sure you want to update this order status?');">

                                        <input type="hidden"
                                               name="order_id"
                                               value="<?= (int) $order['order_id'] ?>">

                                        <select name="order_status"
                                                class="form-control form-control-sm"
                                                required>

                                            <option value="Pending"
                                                <?= $status === 'Pending' ? 'selected' : '' ?>>
                                                Pending
                                            </option>

                                            <option value="In Progress"
                                                <?= $status === 'In Progress' ? 'selected' : '' ?>>
                                                In Progress
                                            </option>

                                            <option value="Completed"
                                                <?= $status === 'Completed' ? 'selected' : '' ?>>
                                                Completed
                                            </option>

                                        </select>

                                        <button type="submit"
                                                name="update_status"
                                                class="btn btn-sm btn-primary btn-block">
                                            Save Status
                                        </button>

                                    </form>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">
                    <h4>No Orders Assigned Yet</h4>

                    <p>
                        You do not have any orders assigned to your staff account.
                        Please contact the administrator to assign orders to you.
                    </p>

                    <a href="staff_dashboard.php" class="btn btn-primary">
                        Return to Dashboard
                    </a>
                </div>

            <?php endif; ?>

        </div>
    </div>

    <p class="text-muted text-center mt-4">
        &copy; <?= date('Y') ?> Clare Designs. All rights reserved.
    </p>

</div>

<script src="../assets/js/jquery.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/adminlte.min.js"></script>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>