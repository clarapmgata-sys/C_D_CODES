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
        a.appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.purpose,
        s.service_name
    FROM appointments a
    INNER JOIN services s ON a.service_id = s.service_id
    WHERE a.customer_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
";

$stmt = $conn->prepare($sql);
$appointments = [];

if ($stmt) {
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Appointments | Clare Designs</title>

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
        <a href="my_appointments.php" class="active">Appointments</a>
        <a href="my_payments.php">Payments</a>
        <a href="profile.php">My Profile</a>
        <a href="../logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <h2>My Appointments</h2>

    <p>
        Welcome, <?= escape($customer_name) ?>.
        View your appointment details below.
    </p>

    <div class="card">

        <?php if (count($appointments) > 0): ?>

            <table>
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
                            #<?= (int)$appointment['appointment_id'] ?>
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
                            <span class="status">
                                <?= escape($appointment['status']) ?>
                            </span>
                        </td>
                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>

        <?php else: ?>

            <div class="empty">
                <h3>No appointments found.</h3>
                <p>Your appointments will appear here when they have been recorded.</p>
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>