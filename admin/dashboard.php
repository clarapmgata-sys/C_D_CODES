<?php
session_start();
require_once "../db.php";


$customer_result = $conn->query("SELECT COUNT(*) AS total FROM customers");
$total_customers = $customer_result->fetch_assoc()['total'];


$service_result = $conn->query("SELECT COUNT(*) AS total FROM services");
$total_services = $service_result->fetch_assoc()['total'];


$order_result = $conn->query("SELECT COUNT(*) AS total FROM orders");
$total_orders = $order_result->fetch_assoc()['total'];


$payment_result = $conn->query(
    "SELECT COUNT(*) AS total FROM payments WHERE payment_status = 'pending'"
);
$pending_payments = $payment_result->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Clare Designs Dashboard</title>

    <link rel="stylesheet" href="../assets/css/adminlte.min.css">
</head>

<body>

<div class="container-fluid mt-4">

    <h1>CLARE DESIGNS</h1>
    <h3>Admin Dashboard</h3>
    <p>Welcome to the Clare Designs Management System.</p>

    <div class="row">

        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h3><?php echo $total_customers; ?></h3>
                    <p>Total Customers</p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h3><?php echo $total_services; ?></h3>
                    <p>Total Services</p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-warning">
                <div class="card-body">
                    <h3><?php echo $total_orders; ?></h3>
                    <p>Total Orders</p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h3><?php echo $pending_payments; ?></h3>
                    <p>Pending Payments</p>
                </div>
            </div>
        </div>

    </div>

    <hr>

    <h4>Management Menu</h4>

    <a href="customers.php" class="btn btn-primary">Customers</a>
    <a href="services.php" class="btn btn-success">Services</a>
    <a href="staff.php" class="btn btn-info">Staff</a>
    <a href="appointments.php" class="btn btn-secondary">Appointments</a>
    <a href="orders.php" class="btn btn-warning">Orders</a>
    <a href="payments.php" class="btn btn-danger">Payments</a>
    <a href="users.php" class="btn btn-dark">Users</a>

    <br><br>

    <a href="../logout.php" class="btn btn-outline-dark">Logout</a>

</div>

<script src="../assets/js/adminlte.min.js"></script>

</body>
</html>