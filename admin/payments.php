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

// Update payment status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {

    $payment_id = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);
    $payment_status = $_POST['payment_status'] ?? '';

    $allowedStatuses = ['Pending', 'Confirmed', 'Failed'];

    if (!$payment_id || !in_array($payment_status, $allowedStatuses, true)) {
        $message = "Invalid payment or payment status.";
        $messageType = 'danger';
    } else {
        $stmt = $conn->prepare(
            "UPDATE payments SET payment_status = ? WHERE payment_id = ?"
        );

        $stmt->bind_param("si", $payment_status, $payment_id);

        if ($stmt->execute()) {
            $message = "Payment status updated successfully.";
            $messageType = 'success';
        } else {
            $message = "Unable to update payment status.";
            $messageType = 'danger';
        }

        $stmt->close();
    }
}

// Fetch payments
$sql = "
    SELECT
        p.payment_id,
        p.order_id,
        p.amount,
        p.payment_date,
        p.payment_method,
        p.transaction_reference,
        p.payment_status,
        o.customer_id,
        c.full_name AS customer_name,
        c.business_name
    FROM payments p
    INNER JOIN orders o ON p.order_id = o.order_id
    INNER JOIN customers c ON o.customer_id = c.customer_id
    ORDER BY p.payment_id DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("Unable to load payments. Please check the database table structure.");
}

$payments = [];
$totalPayments = 0;
$confirmedPayments = 0;
$pendingPayments = 0;
$failedPayments = 0;
$totalConfirmedAmount = 0;

while ($row = $result->fetch_assoc()) {
    $payments[] = $row;
    $totalPayments++;

    if ($row['payment_status'] === 'Confirmed') {
        $confirmedPayments++;
        $totalConfirmedAmount += (float)$row['amount'];
    } elseif ($row['payment_status'] === 'Pending') {
        $pendingPayments++;
    } elseif ($row['payment_status'] === 'Failed') {
        $failedPayments++;
    }
}

function escapePayment($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function paymentStatusClass($status) {
    switch ($status) {
        case 'Confirmed':
            return 'status-confirmed';
        case 'Pending':
            return 'status-pending';
        case 'Failed':
            return 'status-failed';
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

    <title>Payments Management | Clare Designs</title>

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

        .confirmed-card {
            border-left-color: #28a745;
        }

        .pending-card {
            border-left-color: #ffc107;
        }

        .failed-card {
            border-left-color: #dc3545;
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

        .form-control {
            border-radius: 5px;
        }

        .btn-primary {
            background: #007bff;
            border-color: #007bff;
        }

        .btn-primary:hover {
            background: #0056b3;
            border-color: #0056b3;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }

        .status-form {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 130px;
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
            <a href="orders.php" class="nav-link">Orders</a>
        </li>
        <li class="nav-item">
            <a href="payments.php" class="nav-link active">Payments</a>
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
            <h1>Payments Management</h1>
            <p>Review customer payments and confirm transactions.</p>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <!-- FEEDBACK MESSAGE -->
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?= escapePayment($messageType) ?> alert-dismissible">
                    <?= escapePayment($message) ?>
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
                        <h3><?= $totalPayments ?></h3>
                        <p>Total Payments</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="summary-card confirmed-card">
                        <h3><?= $confirmedPayments ?></h3>
                        <p>Confirmed Payments</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="summary-card pending-card">
                        <h3><?= $pendingPayments ?></h3>
                        <p>Pending Payments</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="summary-card failed-card">
                        <h3><?= $failedPayments ?></h3>
                        <p>Failed Payments</p>
                    </div>
                </div>
            </div>

            <!-- CONFIRMED PAYMENT TOTAL -->
            <div class="card">
                <div class="card-body">
                    <h5>Confirmed Payment Amount</h5>
                    <h2 class="text-primary">
                        TZS <?= number_format($totalConfirmedAmount, 2) ?>
                    </h2>
                </div>
            </div>

            <!-- PAYMENTS TABLE -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Customer Payments</h3>
                </div>

                <div class="card-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Customer</th>
                                <th>Business</th>
                                <th>Order ID</th>
                                <th>Amount (TZS)</th>
                                <th>Payment Date</th>
                                <th>Method</th>
                                <th>Transaction Reference</th>
                                <th>Status</th>
                                <th>Update Status</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if (count($payments) > 0): ?>

                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?= (int)$payment['payment_id'] ?></td>

                                    <td><?= escapePayment($payment['customer_name']) ?></td>

                                    <td>
                                        <?= escapePayment($payment['business_name'] ?: '—') ?>
                                    </td>

                                    <td><?= (int)$payment['order_id'] ?></td>

                                    <td>
                                        <?= number_format((float)$payment['amount'], 2) ?>
                                    </td>

                                    <td><?= escapePayment($payment['payment_date']) ?></td>

                                    <td><?= escapePayment($payment['payment_method']) ?></td>

                                    <td>
                                        <?= escapePayment($payment['transaction_reference'] ?: '—') ?>
                                    </td>

                                    <td>
                                        <span class="status-badge <?= escapePayment(paymentStatusClass($payment['payment_status'])) ?>">
                                            <?= escapePayment($payment['payment_status']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <form method="POST"
                                              action="payments.php"
                                              class="status-form">

                                            <input type="hidden"
                                                   name="payment_id"
                                                   value="<?= (int)$payment['payment_id'] ?>">

                                            <select name="payment_status"
                                                    class="form-control form-control-sm"
                                                    required>
                                                <?php foreach (['Pending', 'Confirmed', 'Failed'] as $status): ?>
                                                    <option value="<?= escapePayment($status) ?>"
                                                        <?= $payment['payment_status'] === $status ? 'selected' : '' ?>>
                                                        <?= escapePayment($status) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <button type="submit"
                                                    name="update_payment"
                                                    class="btn btn-primary btn-sm">
                                                Update
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center">
                                    No payment records found.
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
