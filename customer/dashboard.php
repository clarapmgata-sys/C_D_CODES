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

function escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* =========================================
   2. INITIALIZE SUMMARY COUNTS
========================================= */

$total_orders = 0;
$pending_orders = 0;
$completed_orders = 0;
$total_appointments = 0;
$upcoming_appointments = 0;
$pending_payments = 0;
$confirmed_payments = 0;

/* =========================================
   3. COUNT CUSTOMER ORDERS
========================================= */

$order_sql = "
    SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(order_status = 'Pending'), 0) AS pending_orders,
        COALESCE(SUM(order_status = 'Completed'), 0) AS completed_orders
    FROM orders
    WHERE customer_id = ?
";

$order_stmt = $conn->prepare($order_sql);

if ($order_stmt) {
    $order_stmt->bind_param("i", $customer_id);
    $order_stmt->execute();

    $order_result = $order_stmt->get_result();
    $order_summary = $order_result->fetch_assoc();

    $total_orders = (int) ($order_summary['total_orders'] ?? 0);
    $pending_orders = (int) ($order_summary['pending_orders'] ?? 0);
    $completed_orders = (int) ($order_summary['completed_orders'] ?? 0);

    $order_stmt->close();
}

/* =========================================
   4. COUNT CUSTOMER APPOINTMENTS
========================================= */

$appointment_sql = "
    SELECT
        COUNT(*) AS total_appointments,
        COALESCE(
            SUM(
                appointment_date >= CURDATE()
                AND status IN ('Pending', 'Confirmed', 'Scheduled')
            ),
            0
        ) AS upcoming_appointments
    FROM appointments
    WHERE customer_id = ?
";

$appointment_stmt = $conn->prepare($appointment_sql);

if ($appointment_stmt) {
    $appointment_stmt->bind_param("i", $customer_id);
    $appointment_stmt->execute();

    $appointment_result = $appointment_stmt->get_result();
    $appointment_summary = $appointment_result->fetch_assoc();

    $total_appointments = (int) ($appointment_summary['total_appointments'] ?? 0);
    $upcoming_appointments = (int) ($appointment_summary['upcoming_appointments'] ?? 0);

    $appointment_stmt->close();
}

/* =========================================
   5. COUNT PAYMENT RECORDS
========================================= */

$payment_sql = "
    SELECT
        COALESCE(
            SUM(payment_status IN ('Pending', 'Unconfirmed')),
            0
        ) AS pending_payments,
        COALESCE(
            SUM(payment_status IN ('Confirmed', 'Paid', 'Completed')),
            0
        ) AS confirmed_payments
    FROM payments p
    INNER JOIN orders o ON p.order_id = o.order_id
    WHERE o.customer_id = ?
";

$payment_stmt = $conn->prepare($payment_sql);

if ($payment_stmt) {
    $payment_stmt->bind_param("i", $customer_id);
    $payment_stmt->execute();

    $payment_result = $payment_stmt->get_result();
    $payment_summary = $payment_result->fetch_assoc();

    $pending_payments = (int) ($payment_summary['pending_payments'] ?? 0);
    $confirmed_payments = (int) ($payment_summary['confirmed_payments'] ?? 0);

    $payment_stmt->close();
}

/* =========================================
   6. GET RECENT ORDERS
========================================= */

$recent_orders = [];

$recent_orders_sql = "
    SELECT
        o.order_id,
        o.order_date,
        o.deadline,
        o.order_status,
        o.preview_file,
        o.final_design_file,
        o.agreed_price,
        s.service_name,
        (
            SELECT p.payment_status
            FROM payments p
            WHERE p.order_id = o.order_id
            ORDER BY p.payment_id DESC
            LIMIT 1
        ) AS latest_payment_status
    FROM orders o
    INNER JOIN services s ON o.service_id = s.service_id
    WHERE o.customer_id = ?
    ORDER BY o.order_id DESC
    LIMIT 5
";

$recent_stmt = $conn->prepare($recent_orders_sql);

if ($recent_stmt) {
    $recent_stmt->bind_param("i", $customer_id);
    $recent_stmt->execute();

    $recent_result = $recent_stmt->get_result();

    while ($row = $recent_result->fetch_assoc()) {
        $recent_orders[] = $row;
    }

    $recent_stmt->close();
}

/* =========================================
   7. GET UPCOMING APPOINTMENTS
========================================= */

$appointments = [];

$upcoming_sql = "
    SELECT
        a.appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.purpose,
        s.service_name
    FROM appointments a
    INNER JOIN services s ON a.service_id = s.service_id
    WHERE a.customer_id = ?
      AND a.appointment_date >= CURDATE()
      AND a.status IN ('Pending', 'Confirmed', 'Scheduled')
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 5
";

$upcoming_stmt = $conn->prepare($upcoming_sql);

if ($upcoming_stmt) {
    $upcoming_stmt->bind_param("i", $customer_id);
    $upcoming_stmt->execute();

    $upcoming_result = $upcoming_stmt->get_result();

    while ($row = $upcoming_result->fetch_assoc()) {
        $appointments[] = $row;
    }

    $upcoming_stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Customer Dashboard | Clare Designs</title>

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
            max-width: 1400px;
            margin: 25px auto;
        }

        .welcome-section {
            background: white;
            padding: 25px;
            border-radius: 6px;
            border-left: 5px solid #007bff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }

        .welcome-section h2 {
            font-weight: bold;
            margin-bottom: 8px;
        }

        .welcome-section p {
            margin-bottom: 0;
            color: #6c757d;
        }

        .summary-card {
            background: white;
            padding: 22px;
            border-radius: 6px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
            margin-bottom: 22px;
            border-top: 4px solid #007bff;
            height: calc(100% - 22px);
        }

        .summary-card h3 {
            font-size: 30px;
            font-weight: bold;
            color: #007bff;
            margin: 0 0 8px;
        }

        .summary-card p {
            margin-bottom: 12px;
        }

        .summary-card a {
            font-size: 14px;
            font-weight: bold;
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

        .notice {
            background: #fff3cd;
            border-left: 5px solid #ffc107;
            padding: 18px;
            border-radius: 5px;
            margin-bottom: 25px;
        }

        .notice h5 {
            font-weight: bold;
        }

        .quick-action {
            display: block;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 18px;
            margin-bottom: 15px;
            color: #343a40;
            text-decoration: none;
            transition: 0.2s;
        }

        .quick-action:hover {
            background: #e7f1ff;
            border-color: #007bff;
            color: #0056b3;
            text-decoration: none;
        }

        .quick-action strong {
            display: block;
            margin-bottom: 5px;
        }

        .empty-state {
            text-align: center;
            padding: 25px 10px;
            color: #6c757d;
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

<!-- =====================================
     TOP NAVIGATION
====================================== -->

<nav class="top-nav">

    <a href="customer_dashboard.php" class="brand">
        CLARE DESIGNS
    </a>

    <div class="nav-links">
        <a href="customer_dashboard.php" class="active">Dashboard</a>
        <a href="my_orders.php">My Orders</a>
        <a href="my_appointments.php">Appointments</a>
        <a href="my_payments.php">Payments</a>
        <a href="profile.php">My Profile</a>
        <a href="../logout.php">Logout</a>
    </div>

</nav>


<div class="container-page">

    <!-- =====================================
         WELCOME SECTION
    ====================================== -->

    <div class="welcome-section">

        <h2>
            Welcome, <?= escape($customer_name) ?>!
        </h2>

        <p>
            Welcome to Clare Designs. Manage your design orders,
            appointments, and payments from your dashboard.
        </p>

    </div>


    <!-- =====================================
         PAYMENT AND WATERMARK NOTICE
    ====================================== -->

    <div class="notice">

        <h5>Important: Your Design and Payment</h5>

        <p>
            You can review your design using the watermarked preview
            provided by our designer.
        </p>

        <p>
            Once you are satisfied, make the required payment.
            Your original, unwatermarked design will become available
            for download after the administrator confirms your payment.
        </p>

        <a href="my_orders.php" class="btn btn-primary btn-sm">
            View My Orders
        </a>

    </div>


    <!-- =====================================
         SUMMARY CARDS
    ====================================== -->

    <div class="row">

        <div class="col-lg-3 col-md-6">

            <div class="summary-card">

                <h3><?= $total_orders ?></h3>

                <p>Total Orders</p>

                <a href="my_orders.php">
                    View Orders
                </a>

            </div>

        </div>


        <div class="col-lg-3 col-md-6">

            <div class="summary-card">

                <h3><?= $pending_orders ?></h3>

                <p>Pending Orders</p>

                <a href="my_orders.php">
                    Track Progress
                </a>

            </div>

        </div>


        <div class="col-lg-3 col-md-6">

            <div class="summary-card">

                <h3><?= $completed_orders ?></h3>

                <p>Completed Orders</p>

                <a href="my_orders.php">
                    View Completed Orders
                </a>

            </div>

        </div>


        <div class="col-lg-3 col-md-6">

            <div class="summary-card">

                <h3><?= $upcoming_appointments ?></h3>

                <p>Upcoming Appointments</p>

                <a href="my_appointments.php">
                    View Appointments
                </a>

            </div>

        </div>

    </div>


    <!-- =====================================
         PAYMENT SUMMARY
    ====================================== -->

    <div class="row">

        <div class="col-md-6">

            <div class="summary-card">

                <h3><?= $pending_payments ?></h3>

                <p>Payments Awaiting Confirmation</p>

                <a href="my_payments.php">
                    Check Payment Status
                </a>

            </div>

        </div>


        <div class="col-md-6">

            <div class="summary-card">

                <h3><?= $confirmed_payments ?></h3>

                <p>Confirmed Payments</p>

                <a href="my_payments.php">
                    View Payments
                </a>

            </div>

        </div>

    </div>


    <!-- =====================================
         QUICK ACTIONS
    ====================================== -->

    <div class="card">

        <div class="card-header">
            Quick Actions
        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-4">

                    <a href="my_orders.php" class="quick-action">

                        <strong>My Orders</strong>

                        View order details, watermarked previews,
                        deadlines, and final design availability.

                    </a>

                </div>


                <div class="col-md-4">

                    <a href="my_appointments.php" class="quick-action">

                        <strong>My Appointments</strong>

                        Book a consultation and check your
                        upcoming appointments.

                    </a>

                </div>


                <div class="col-md-4">

                    <a href="my_payments.php" class="quick-action">

                        <strong>My Payments</strong>

                        Review your payment records and confirmation
                        status.

                    </a>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================
         RECENT ORDERS
    ====================================== -->

    <div class="card">

        <div class="card-header">
            My Recent Orders
        </div>

        <div class="card-body">

            <?php if (count($recent_orders) > 0): ?>

                <div class="table-responsive">

                    <table class="table table-bordered table-striped">

                        <thead>

                            <tr>
                                <th>Order ID</th>
                                <th>Service</th>
                                <th>Order Date</th>
                                <th>Deadline</th>
                                <th>Price</th>
                                <th>Order Status</th>
                                <th>Payment Status</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($recent_orders as $order): ?>

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

                            $payment_status = $order['latest_payment_status']
                                ?? 'Not Paid';
                            ?>

                            <tr>

                                <td>
                                    #<?= (int) $order['order_id'] ?>
                                </td>

                                <td>
                                    <?= escape($order['service_name']) ?>
                                </td>

                                <td>
                                    <?= escape($order['order_date']) ?>
                                </td>

                                <td>
                                    <?= escape($order['deadline'] ?? 'Not specified') ?>
                                </td>

                                <td>
                                    <?= number_format(
                                        (float) ($order['agreed_price'] ?? 0),
                                        2
                                    ) ?>
                                </td>

                                <td>

                                    <span class="status-badge <?= $status_class ?>">
                                        <?= escape($status) ?>
                                    </span>

                                </td>

                                <td>
                                    <?= escape($payment_status) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <a href="my_orders.php" class="btn btn-primary">
                    View All Orders
                </a>

            <?php else: ?>

                <div class="empty-state">

                    <h5>You Have No Orders Yet</h5>

                    <p>
                        Your design orders will appear here once you place one.
                    </p>

                    <a href="my_orders.php" class="btn btn-primary">
                        Go to My Orders
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- =====================================
         UPCOMING APPOINTMENTS
    ====================================== -->

    <div class="card">

        <div class="card-header">
            My Upcoming Appointments
        </div>

        <div class="card-body">

            <?php if (count($appointments) > 0): ?>

                <div class="table-responsive">

                    <table class="table table-bordered table-striped">

                        <thead>

                            <tr>
                                <th>Appointment ID</th>
                                <th>Service</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Purpose</th>
                                <th>Status</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($appointments as $appointment): ?>

                            <tr>

                                <td>
                                    #<?= (int) $appointment['appointment_id'] ?>
                                </td>

                                <td>
                                    <?= escape($appointment['service_name']) ?>
                                </td>

                                <td>
                                    <?= escape($appointment['appointment_date']) ?>
                                </td>

                                <td>
                                    <?= escape($appointment['appointment_time']) ?>
                                </td>

                                <td>
                                    <?= escape($appointment['purpose'] ?? 'Consultation') ?>
                                </td>

                                <td>
                                    <span class="status-badge status-pending">
                                        <?= escape($appointment['status']) ?>
                                    </span>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <a href="my_appointments.php" class="btn btn-primary">
                    View All Appointments
                </a>

            <?php else: ?>

                <div class="empty-state">

                    <h5>No Upcoming Appointments</h5>

                    <p>
                        You do not have any upcoming appointments.
                    </p>

                    <a href="my_appointments.php" class="btn btn-primary">
                        Manage Appointments
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- FOOTER -->

    <p class="text-muted text-center mt-4">
        &copy; <?= date('Y') ?> Clare Designs. All rights reserved.
    </p>

</div>

</body>
</html>

<?php
$conn->close();
?>
