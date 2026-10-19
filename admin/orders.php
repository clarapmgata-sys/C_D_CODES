<?php
session_start();
require_once '../db.php';

// Check administrator login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. Only administrators can access this page.");
}

$message = '';
$messageType = 'success';

// Update order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $order_status = $_POST['order_status'] ?? '';

    $allowedStatuses = [
        'Pending',
        'In Progress',
        'Completed',
        'Cancelled'
    ];

    if (!$order_id || !in_array($order_status, $allowedStatuses, true)) {
        $message = "Invalid order or status.";
        $messageType = 'danger';
    } else {
        $stmt = $conn->prepare(
            "UPDATE orders SET order_status = ? WHERE order_id = ?"
        );

        $stmt->bind_param("si", $order_status, $order_id);

        if ($stmt->execute()) {
            $message = "Order status updated successfully.";
            $messageType = 'success';
        } else {
            $message = "Unable to update order status.";
            $messageType = 'danger';
        }

        $stmt->close();
    }
}

// Get order records
$sql = "
    SELECT
        o.order_id,
        o.customer_id,
        o.service_id,
        o.staff_id,
        o.order_date,
        o.deadline,
        o.requirements,
        o.agreed_price,
        o.preview_file,
        o.final_design_file,
        o.order_status,
        o.delivery_date,
        c.full_name AS customer_name,
        c.business_name,
        s.service_name,
        st_user.full_name AS staff_name
    FROM orders o
    INNER JOIN customers c ON o.customer_id = c.customer_id
    INNER JOIN services s ON o.service_id = s.service_id
    LEFT JOIN staff st ON o.staff_id = st.staff_id
    LEFT JOIN users st_user ON st.user_id = st_user.user_id
    ORDER BY o.order_id DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("Unable to load orders. Please check the database table structure.");
}

$orders = [];
$totalOrders = 0;
$pendingOrders = 0;
$inProgressOrders = 0;
$completedOrders = 0;

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
    $totalOrders++;

    if ($row['order_status'] === 'Pending') {
        $pendingOrders++;
    } elseif ($row['order_status'] === 'In Progress') {
        $inProgressOrders++;
    } elseif ($row['order_status'] === 'Completed') {
        $completedOrders++;
    }
}

function escapeOrder($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function orderStatusClass($status) {
    switch ($status) {
        case 'Pending':
            return 'status-pending';
        case 'In Progress':
            return 'status-progress';
        case 'Completed':
            return 'status-completed';
        case 'Cancelled':
            return 'status-cancelled';
        default:
            return 'status-pending';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Orders Management | Clare Designs</title>

    <link rel="stylesheet" href="../assets/css/adminlte.min.css">

    <style>
        body {
            background: #f4f6f9;
            font-family: Arial, sans-serif;
            color: #343a40;
            margin: 0;
        }

        /* SAME BLUE HORIZONTAL NAVIGATION AS SERVICES */
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

        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
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

        .summary-card {
            background: white;
            border-left: 5px solid #007bff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 22px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }

        .summary-card h3 {
            font-size: 30px;
            color: #007bff;
            font-weight: bold;
            margin: 0 0 5px;
        }

        .pending-card {
            border-left-color: #ffc107;
        }

        .progress-card {
            border-left-color: #17a2b8;
        }

        .completed-card {
            border-left-color: #28a745;
        }

        .form-control {
            border-radius: 5px;
        }

        .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 .2rem rgba(0,123,255,.15);
        }

        .btn-primary {
            background: #007bff;
            border-color: #007bff;
        }

        .btn-primary:hover {
            background: #0056b3;
            border-color: #0056b3;
        }

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

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-progress {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .requirements-text {
            max-width: 220px;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .status-form {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 145px;
        }

        .main-footer {
            background: white;
            border-top: 2px solid #007bff;
            padding: 16px;
            color: #6c757d;
        }

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

<body class="hold-transition layout-top-nav">

<!-- TOP NAVIGATION -->
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
            <a href="orders.php" class="nav-link active">Orders</a>
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

<div class="content-wrapper">

    <!-- PAGE TITLE -->
    <div class="content-header">
        <div class="container-fluid">
            <h1>Orders Management</h1>
            <p>View customer orders and track their progress.</p>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <!-- FEEDBACK MESSAGE -->
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?= escapeOrder($messageType) ?> alert-dismissible">
                    <?= escapeOrder($message) ?>
                    <button type="button" class="close"
                            data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- SUMMARY CARDS -->
            <div class="row">
                <div class="col-md-3">
                    <div class="summary-card">
                        <h3><?= $totalOrders ?></h3>
                        <p>Total Orders</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="summary-card pending-card">
                        <h3><?= $pendingOrders ?></h3>
                        <p>Pending Orders</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="summary-card progress-card">
                        <h3><?= $inProgressOrders ?></h3>
                        <p>In Progress</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="summary-card completed-card">
                        <h3><?= $completedOrders ?></h3>
                        <p>Completed Orders</p>
                    </div>
                </div>
            </div>

            <!-- ORDERS TABLE -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Customer Orders</h3>
                </div>

                <div class="card-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Business</th>
                                <th>Service</th>
                                <th>Assigned Staff</th>
                                <th>Order Date</th>
                                <th>Deadline</th>
                                <th>Agreed Price</th>
                                <th>Requirements</th>
                                <th>Status</th>
                                <th>Update Status</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if (count($orders) > 0): ?>

                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?= (int)$order['order_id'] ?></td>

                                    <td><?= escapeOrder($order['customer_name']) ?></td>

                                    <td>
                                        <?= escapeOrder($order['business_name'] ?: '—') ?>
                                    </td>

                                    <td><?= escapeOrder($order['service_name']) ?></td>

                                    <td>
                                        <?= escapeOrder($order['staff_name'] ?: 'Not assigned') ?>
                                    </td>

                                    <td><?= escapeOrder($order['order_date']) ?></td>

                                    <td><?= escapeOrder($order['deadline']) ?></td>

                                    <td>
                                        TZS <?= number_format((float)$order['agreed_price'], 2) ?>
                                    </td>

                                    <td>
                                        <div class="requirements-text">
                                            <?= escapeOrder($order['requirements'] ?: 'No requirements entered') ?>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="status-badge <?= escapeOrder(orderStatusClass($order['order_status'])) ?>">
                                            <?= escapeOrder($order['order_status']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <form method="POST" action="orders.php" class="status-form">

                                            <input type="hidden"
                                                   name="order_id"
                                                   value="<?= (int)$order['order_id'] ?>">

                                            <select name="order_status"
                                                    class="form-control form-control-sm"
                                                    required>
                                                <?php
                                                $statuses = [
                                                    'Pending',
                                                    'In Progress',
                                                    'Completed',
                                                    'Cancelled'
                                                ];
                                                ?>

                                                <?php foreach ($statuses as $status): ?>
                                                    <option value="<?= escapeOrder($status) ?>"
                                                        <?= $order['order_status'] === $status ? 'selected' : '' ?>>
                                                        <?= escapeOrder($status) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <button type="submit"
                                                    name="update_status"
                                                    class="btn btn-primary btn-sm">
                                                Update
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center">
                                    No orders have been placed yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>
</div>

<footer class="main-footer text-center">
    <strong>Copyright &copy; <?= date('Y') ?> Clare Designs.</strong>
    All rights reserved.
</footer>

</body>
</html>
