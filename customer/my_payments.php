<?php
session_start();
require_once '../db.php';

// Check customer login
if (
    !isset($_SESSION['customer_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'customer'
) {
    header("Location: ../login.php");
    exit;
}

$customer_id = (int) $_SESSION['customer_id'];
$customer_name = $_SESSION['full_name'] ?? 'Customer';

function escape($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/*
 * Retrieve payment records belonging to this customer's orders.
 * The payment table's amount and date columns may differ between databases,
 * so this page uses the payment fields already known to exist.
 */

$sql = "
    SELECT
        p.*,
        o.order_id,
        o.agreed_price,
        s.service_name
    FROM payments p
    INNER JOIN orders o ON p.order_id = o.order_id
    LEFT JOIN services s ON o.service_id = s.service_id
    WHERE o.customer_id = ?
    ORDER BY p.payment_id DESC
";

$stmt = $conn->prepare($sql);
$payments = [];
$error_message = '';

if ($stmt) {
    $stmt->bind_param("i", $customer_id);

    if ($stmt->execute()) {
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
    } else {
        $error_message = "Unable to retrieve payment records.";
    }

    $stmt->close();
} else {
    $error_message = "Unable to load payment records. Please check your database table structure.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Payments | Clare Designs</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #333;
        }

        .top-nav {
            background: #8b6b4a;
            padding: 16px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .brand {
            color: white;
            font-size: 21px;
            font-weight: bold;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 9px 12px;
            border-radius: 5px;
        }

        .nav-links a:hover,
        .nav-links a.active {
            background: #654321;
            color:white;
        }

        .container {
            width: 94%;
            max-width: 1200px;
            margin: 30px auto;
        }

        .card {
            background: white;
            padding: 22px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 750px;
            border-collapse: collapse;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #1769aa;
            color: white;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 15px;
            background: #e8eef5;
            font-size: 12px;
        }

        .empty {
            text-align: center;
            padding: 35px;
            color: #666;
        }

        .error {
            color: #842029;
            background: #f8d7da;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        @media (max-width: 700px) {
            .top-nav {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<nav class="top-nav">
    <a href="dashboard.php" class="brand">CLARE DESIGNS</a>

    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="my_orders.php">My Orders</a>
        <a href="my_appointments.php">Appointments</a>
        <a href="my_payments.php" class="active">Payments</a>
        <a href="profile.php">My Profile</a>
        <a href="../logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <h2>My Payments</h2>

    <p>
        Welcome, <?= escape($customer_name) ?>.
        View payments associated with your design orders below.
    </p>

    <div class="card">

        <?php if ($error_message !== ''): ?>

            <div class="error">
                <?= escape($error_message) ?>
            </div>

        <?php elseif (count($payments) > 0): ?>

            <table>
                <thead>
                    <tr>
                        <th>Payment ID</th>
                        <th>Order ID</th>
                        <th>Service</th>
                        <th>Order Price (TZS)</th>
                        <th>Payment Status</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($payments as $payment): ?>

                    <tr>
                        <td>
                            #<?= (int)$payment['payment_id'] ?>
                        </td>

                        <td>
                            #<?= (int)$payment['order_id'] ?>
                        </td>

                        <td>
                            <?= escape($payment['service_name'] ?? 'Design Service') ?>
                        </td>

                        <td>
                            <?= number_format((float)($payment['agreed_price'] ?? 0), 2) ?>
                        </td>

                        <td>
                            <span class="status">
                                <?= escape($payment['payment_status'] ?? 'Pending') ?>
                            </span>
                        </td>
                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>

        <?php else: ?>

            <div class="empty">
                <h3>No payment records found.</h3>
                <p>Payments associated with your orders will appear here.</p>
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>