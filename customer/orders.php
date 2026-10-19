<?php
session_start();
require_once '../db.php';

/* =========================================
   1. CHECK CUSTOMER LOGIN
========================================= */

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'customer' ||
    empty($_SESSION['customer_id'])
) {
    header("Location: ../login.php");
    exit;
}

$customer_id = (int) $_SESSION['customer_id'];
$customer_name = $_SESSION['full_name'] ?? 'Customer';

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* =========================================
   2. GET CUSTOMER ORDERS
========================================= */

$sql = "
    SELECT
        o.order_id,
        o.order_date,
        o.deadline,
        o.requirements,
        o.agreed_price,
        o.order_status,
        o.preview_file,
        o.final_design_file,
        o.delivery_date,
        s.service_name,

        (
            SELECT p.payment_status
            FROM payments p
            WHERE p.order_id = o.order_id
            ORDER BY p.payment_id DESC
            LIMIT 1
        ) AS payment_status,

        (
            SELECT p.amount
            FROM payments p
            WHERE p.order_id = o.order_id
            ORDER BY p.payment_id DESC
            LIMIT 1
        ) AS payment_amount

    FROM orders o
    INNER JOIN services s
        ON o.service_id = s.service_id

    WHERE o.customer_id = ?

    ORDER BY o.order_id DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . e($conn->error));
}

$stmt->bind_param("i", $customer_id);
$stmt->execute();

$result = $stmt->get_result();

$orders = [];

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$stmt->close();

/* =========================================
   3. COUNT ORDERS
========================================= */

$total_orders = count($orders);
$pending_orders = 0;
$completed_orders = 0;

foreach ($orders as $order) {
    if (($order['order_status'] ?? '') === 'Pending') {
        $pending_orders++;
    }

    if (($order['order_status'] ?? '') === 'Completed') {
        $completed_orders++;
    }
}

/* =========================================
   4. CHECK PAYMENT STATUS
========================================= */

function isPaymentConfirmed($status)
{
    return in_array(
        strtolower(trim((string) $status)),
        ['confirmed', 'paid', 'completed'],
        true
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Orders | Clare Designs</title>

    <link rel="stylesheet" href="../assets/css/adminlte.min.css">

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

        .brand:hover {
            color: white;
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
            max-width: 1450px;
            margin: 25px auto;
        }

        .page-heading {
            margin-bottom: 22px;
        }

        .page-heading h2 {
            font-weight: bold;
        }

        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 6px;
            border-top: 4px solid #007bff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }

        .summary-card h3 {
            color: #007bff;
            font-weight: bold;
            font-size: 28px;
            margin: 0 0 5px;
        }

        .card {
            border: none;
            border-radius: 6px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
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
        }

        .pending {
            background: #fff3cd;
            color: #856404;
        }

        .progress {
            background: #cce5ff;
            color: #004085;
        }

        .completed {
            background: #d4edda;
            color: #155724;
        }

        .other {
            background: #e2e3e5;
            color: #383d41;
        }

        .payment-notice {
            padding: 18px;
            background: #fff3cd;
            border-left: 5px solid #ffc107;
            border-radius: 5px;
            margin-bottom: 22px;
        }

        .payment-notice h5 {
            font-weight: bold;
        }

        .preview-box {
            min-width: 150px;
        }

        .empty-state {
            text-align: center;
            padding: 35px 15px;
        }

        .small-note {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
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

<!-- =========================================
     NAVIGATION
========================================= -->

<nav class="top-nav">

    <a href="customer_dashboard.php" class="brand">
        CLARE DESIGNS
    </a>

    <div class="nav-links">
        <a href="customer_dashboard.php">Dashboard</a>
        <a href="orders.php" class="active">My Orders</a>
        <a href="my_appointments.php">Appointments</a>
        <a href="my_payments.php">Payments</a>
        <a href="profile.php">My Profile</a>
        <a href="../logout.php">Logout</a>
    </div>

</nav>


<div class="container-page">

    <!-- PAGE HEADING -->

    <div class="page-heading">

        <h2>My Design Orders</h2>

        <p>
            Welcome, <strong><?= e($customer_name) ?></strong>.
            Track your design orders and download completed work.
        </p>

    </div>


    <!-- PAYMENT PROCESS NOTICE -->

    <div class="payment-notice">

        <h5>How to Receive Your Final Design</h5>

        <ol>
            <li>Place your design order.</li>
            <li>Wait for the designer to upload a watermarked preview.</li>
            <li>Review the preview and make the required payment.</li>
            <li>Wait for the administrator to confirm your payment.</li>
            <li>Download your original, unwatermarked design once payment is confirmed.</li>
        </ol>

        <p class="mb-0">
            <strong>Important:</strong>
            The original design is not available for download before payment confirmation.
        </p>

    </div>


    <!-- SUMMARY CARDS -->

    <div class="row">

        <div class="col-md-4">

            <div class="summary-card">

                <h3><?= $total_orders ?></h3>

                <p>Total Orders</p>

            </div>

        </div>

        <div class="col-md-4">

            <div class="summary-card">

                <h3><?= $pending_orders ?></h3>

                <p>Pending Orders</p>

            </div>

        </div>

        <div class="col-md-4">

            <div class="summary-card">

                <h3><?= $completed_orders ?></h3>

                <p>Completed Orders</p>

            </div>

        </div>

    </div>


    <!-- ORDER TABLE -->

    <div class="card">

        <div class="card-header">
            My Orders
        </div>

        <div class="card-body">

            <?php if ($total_orders > 0): ?>

                <div class="table-responsive">

                    <table class="table table-bordered table-striped">

                        <thead>

                            <tr>
                                <th>Order ID</th>
                                <th>Service</th>
                                <th>Order Date</th>
                                <th>Deadline</th>
                                <th>Requirements</th>
                                <th>Price</th>
                                <th>Order Status</th>
                                <th>Payment Status</th>
                                <th>Design Preview</th>
                                <th>Final Design</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($orders as $order): ?>

                            <?php
                            $order_status = $order['order_status'] ?? 'Pending';

                            if ($order_status === 'Pending') {
                                $order_class = 'pending';
                            } elseif ($order_status === 'In Progress') {
                                $order_class = 'progress';
                            } elseif ($order_status === 'Completed') {
                                $order_class = 'completed';
                            } else {
                                $order_class = 'other';
                            }

                            $payment_status = $order['payment_status'] ?? 'Not Paid';

                            $payment_confirmed = isPaymentConfirmed($payment_status);
                            ?>

                            <tr>

                                <td>
                                    #<?= (int) $order['order_id'] ?>
                                </td>

                                <td>
                                    <?= e($order['service_name']) ?>
                                </td>

                                <td>
                                    <?= e($order['order_date']) ?>
                                </td>

                                <td>
                                    <?= e($order['deadline'] ?? 'Not specified') ?>
                                </td>

                                <td>
                                    <?= nl2br(e($order['requirements'] ?? 'No requirements provided')) ?>
                                </td>

                                <td>
                                    <?= number_format((float) ($order['agreed_price'] ?? 0), 2) ?>
                                </td>

                                <td>

                                    <span class="status-badge <?= $order_class ?>">
                                        <?= e($order_status) ?>
                                    </span>

                                </td>

                                <td>

                                    <?php if ($payment_confirmed): ?>

                                        <span class="status-badge completed">
                                            <?= e($payment_status) ?>
                                        </span>

                                    <?php elseif (in_array(strtolower(trim($payment_status)), ['pending', 'unconfirmed'], true)): ?>

                                        <span class="status-badge pending">
                                            Payment Pending
                                        </span>

                                    <?php else: ?>

                                        <span class="status-badge other">
                                            <?= e($payment_status) ?>
                                        </span>

                                    <?php endif; ?>

                                    <br>

                                    <a href="my_payments.php" class="btn btn-sm btn-primary mt-2">
                                        View Payments
                                    </a>

                                </td>

                                <!-- WATERMARKED PREVIEW -->

                                <td class="preview-box">

                                    <?php if (!empty($order['preview_file'])): ?>

                                        <a
                                            href="view_preview.php?order_id=<?= (int) $order['order_id'] ?>"
                                            class="btn btn-sm btn-info"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            View Preview
                                        </a>

                                        <div class="small-note">
                                            Watermarked preview
                                        </div>

                                    <?php else: ?>

                                        <span class="text-muted">
                                            Not ready yet
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- FINAL DESIGN -->

                                <td>

                                    <?php if ($payment_confirmed && !empty($order['final_design_file'])): ?>

                                        <a
                                            href="download_design.php?order_id=<?= (int) $order['order_id'] ?>"
                                            class="btn btn-sm btn-success"
                                        >
                                            Download Original
                                        </a>

                                    <?php elseif (!$payment_confirmed && !empty($order['final_design_file'])): ?>

                                        <span class="status-badge pending">
                                            Payment Required
                                        </span>

                                    <?php else: ?>

                                        <span class="text-muted">
                                            Not ready yet
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">

                    <h4>You Have No Orders Yet</h4>

                    <p>
                        Your design orders will appear here after you place an order.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <p class="text-muted text-center mt-4">
        &copy; <?= date('Y') ?> Clare Designs. All rights reserved.
    </p>

</div>

</body>
</html>

<?php
$conn->close();
?>
