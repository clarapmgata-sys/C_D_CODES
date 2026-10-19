<?php
session_start();
require_once '../db.php';

// Check staff login
if (!isset($_SESSION['staff_id'])) {
    header("Location: ../login.php");
    exit;
}

$staff_id = (int) $_SESSION['staff_id'];

// Get staff details
$stmt = $conn->prepare("
    SELECT u.full_name
    FROM staff s
    INNER JOIN users u ON s.user_id = u.user_id
    WHERE s.staff_id = ?
");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$staffResult = $stmt->get_result();
$staff = $staffResult->fetch_assoc();
$stmt->close();

if (!$staff) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

// Count assigned orders
$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_orders,
        SUM(CASE WHEN order_status = 'Pending' THEN 1 ELSE 0 END) AS pending_orders,
        SUM(CASE WHEN order_status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress_orders,
        SUM(CASE WHEN order_status = 'Completed' THEN 1 ELSE 0 END) AS completed_orders
    FROM orders
    WHERE staff_id = ?
");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Recent assigned orders
$stmt = $conn->prepare("
    SELECT
        o.order_id,
        o.order_date,
        o.deadline,
        o.order_status,
        c.full_name AS customer_name,
        s.service_name
    FROM orders o
    INNER JOIN customers c ON o.customer_id = c.customer_id
    INNER JOIN services s ON o.service_id = s.service_id
    WHERE o.staff_id = ?
    ORDER BY o.order_id DESC
    LIMIT 5
");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$orders = $stmt->get_result();
$stmt->close();

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function statusClass($status) {
    switch ($status) {
        case 'Pending':
            return 'badge-warning';
        case 'In Progress':
            return 'badge-info';
        case 'Completed':
            return 'badge-success';
        case 'Cancelled':
            return 'badge-danger';
        default:
            return 'badge-secondary';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard | Clare Designs</title>

    <link rel="stylesheet" href="../assets/css/adminlte.min.css">

    <style>
        body {
            background: #f4f6f9;
            font-family: Arial, sans-serif;
            color: #343a40;
            margin: 0;
        }

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

        .btn-primary {
            background: #007bff;
            border-color: #007bff;
        }

        .btn-primary:hover {
            background: #0056b3;
            border-color: #0056b3;
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

<nav class="main-header navbar navbar-expand navbar-primary">
    <a href="staff_dashboard.php" class="navbar-brand">CLARE DESIGNS</a>

    <ul class="navbar-nav">
        <li class="nav-item">
            <a href="staff_dashboard.php" class="nav-link active">Dashboard</a>
        </li>
        <li class="nav-item">
            <a href="my_orders.php" class="nav-link">My Orders</a>
        </li>
        <li class="nav-item">
            <a href="upload_design.php" class="nav-link">Upload Design</a>
        </li>
        <li class="nav-item">
            <a href="../logout.php" class="nav-link">Logout</a>
        </li>
    </ul>
</nav>

<div class="content-wrapper">

    <div class="content-header">
        <div class="container-fluid">
            <h1>Staff Dashboard</h1>
            <p>
                Welcome, <strong><?= e($staff['full_name']) ?></strong>!
                Here is an overview of your assigned design orders.
            </p>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <div class="row">

                <div class="col-md-3 col-sm-6">
                    <div class="summary-card">
                        <h3><?= (int)($summary['total_orders'] ?? 0) ?></h3>
                        <p>My Orders</p>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="summary-card pending-card">
                        <h3><?= (int)($summary['pending_orders'] ?? 0) ?></h3>
                        <p>Pending Orders</p>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="summary-card progress-card">
                        <h3><?= (int)($summary['in_progress_orders'] ?? 0) ?></h3>
                        <p>In Progress</p>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="summary-card completed-card">
                        <h3><?= (int)($summary['completed_orders'] ?? 0) ?></h3>
                        <p>Completed Orders</p>
                    </div>
                </div>

            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">My Recent Orders</h3>
                </div>

                <div class="card-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Service</th>
                                <th>Order Date</th>
                                <th>Deadline</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if ($orders->num_rows > 0): ?>

                            <?php while ($order = $orders->fetch_assoc()): ?>
                                <tr>
                                    <td><?= (int)$order['order_id'] ?></td>
                                    <td><?= e($order['customer_name']) ?></td>
                                    <td><?= e($order['service_name']) ?></td>
                                    <td><?= e($order['order_date']) ?></td>
                                    <td><?= e($order['deadline']) ?></td>
                                    <td>
                                        <span class="badge <?= statusClass($order['order_status']) ?>">
                                            <?= e($order['order_status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>

                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">
                                    You have no assigned orders yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>

                    <a href="my_orders.php" class="btn btn-primary">
                        View All My Orders
                    </a>
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
