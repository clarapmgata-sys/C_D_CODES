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

$sql = "
    SELECT
        o.order_id,
        o.order_date,
        o.deadline,
        o.order_status,
        o.agreed_price,
        o.preview_file,
        o.final_design_file,
        s.service_name
    FROM orders o
    INNER JOIN services s ON o.service_id = s.service_id
    WHERE o.customer_id = ?
    ORDER BY o.order_id DESC
";

$stmt = $conn->prepare($sql);
$orders = [];

if ($stmt) {
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Orders | Clare Designs</title>

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
            background: #8B6B4A;
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

        .heading {
            margin-bottom: 20px;
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
            border-collapse: collapse;
            min-width: 850px;
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

        tr:hover {
            background: #f7faff;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 15px;
            background: #e8eef5;
            font-size: 12px;
        }

        .btn {
            display: inline-block;
            padding: 8px 11px;
            border-radius: 5px;
            text-decoration: none;
            color: white;
            background: #1769aa;
            font-size: 13px;
            border: none;
            cursor: pointer;
        }

        .btn-download {
            background: #198754;
        }

        .empty {
            text-align: center;
            padding: 35px;
            color: #666;
        }

        @media (max-width: 700px) {
            .top-nav {
                align-items: flex-start;
                flex-direction: column;
            }

            .container {
                width: 96%;
            }
        }
    </style>
</head>

<body>

<nav class="top-nav">
    <a href="dashboard.php" class="brand">CLARE DESIGNS</a>

    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="my_orders.php" class="active">My Orders</a>
        <a href="my_appointments.php">Appointments</a>
        <a href="my_payments.php">Payments</a>
        <a href="profile.php">My Profile</a>
        <a href="../logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <div class="heading">
        <h2>My Orders</h2>
        <p>Welcome, <?= escape($customer_name) ?>. View your design orders below.</p>
    </div>

    <div class="card">

        <?php if (count($orders) > 0): ?>

            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Service</th>
                        <th>Order Date</th>
                        <th>Deadline</th>
                        <th>Price (TZS)</th>
                        <th>Status</th>
                        <th>Design</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($orders as $order): ?>

                    <tr>
                        <td>#<?= (int)$order['order_id'] ?></td>

                        <td>
                            <?= escape($order['service_name']) ?>
                        </td>

                        <td>
                            <?= escape($order['order_date']) ?>
                        </td>

                        <td>
                            <?= escape($order['deadline']) ?>
                        </td>

                        <td>
                            <?= number_format((float)($order['agreed_price'] ?? 0), 2) ?>
                        </td>

                        <td>
                            <span class="status">
                                <?= escape($order['order_status']) ?>
                            </span>
                        </td>

                        <td>
                            <?php
                            $file = basename((string)($order['final_design_file'] ?? ''));
                            $filePath = __DIR__ . '/../uploads/designs/' . $file;
                            ?>

                            <?php if ($file !== '' && is_file($filePath)): ?>

                                <a
                                    class="btn btn-download"
                                    href="../uploads/designs/<?= rawurlencode($file) ?>"
                                    download
                                >
                                    Download
                                </a>

                            <?php else: ?>

                                <span>Not available</span>

                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>

        <?php else: ?>

            <div class="empty">
                <h3>You have no orders yet.</h3>
                <p>Your design orders will appear here after you place them.</p>
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>