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

$message = '';
$message_type = 'success';

/* =========================================
   2. BOOK AN APPOINTMENT
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {

    $service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
    $appointment_date = trim($_POST['appointment_date'] ?? '');
    $appointment_time = trim($_POST['appointment_time'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');

    if (
        !$service_id ||
        $appointment_date === '' ||
        $appointment_time === '' ||
        $purpose === ''
    ) {
        $message = 'Please complete all appointment fields.';
        $message_type = 'danger';

    } elseif (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date) ||
        !checkdate(
            (int) substr($appointment_date, 5, 2),
            (int) substr($appointment_date, 8, 2),
            (int) substr($appointment_date, 0, 4)
        ) ||
        $appointment_date < date('Y-m-d')
    ) {
        $message = 'Please choose a valid appointment date today or later.';
        $message_type = 'danger';

    } elseif (
        !preg_match('/^\d{2}:\d{2}$/', $appointment_time) ||
        (int) substr($appointment_time, 0, 2) > 23 ||
        (int) substr($appointment_time, 3, 2) > 59
    ) {
        $message = 'Please choose a valid appointment time.';
        $message_type = 'danger';

    } elseif (strlen($purpose) > 1000) {
        $message = 'The appointment purpose must not exceed 1,000 characters.';
        $message_type = 'danger';

    } elseif (
        $appointment_date === date('Y-m-d') &&
        $appointment_time <= date('H:i')
    ) {
        $message = 'Please choose a future time for today.';
        $message_type = 'danger';

    } else {

        /*
         * Confirm that the selected service exists and is active.
         */

        $service_check = $conn->prepare(
            "SELECT service_id
             FROM services
             WHERE service_id = ? AND is_active = 1"
        );

        if (!$service_check) {
            die('Database error: ' . e($conn->error));
        }

        $service_check->bind_param('i', $service_id);
        $service_check->execute();

        $service_result = $service_check->get_result();
        $service_exists = $service_result->num_rows === 1;

        $service_check->close();

        if (!$service_exists) {

            $message = 'The selected service is unavailable.';
            $message_type = 'danger';

        } else {

            /*
             * New appointments begin as Pending.
             * Staff/admin can confirm the appointment later.
             */

            $status = 'Pending';

            $insert = $conn->prepare(
                "INSERT INTO appointments
                    (customer_id, service_id, appointment_date,
                     appointment_time, purpose, status)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            if (!$insert) {
                die('Database error: ' . e($conn->error));
            }

            $insert->bind_param(
                'iissss',
                $customer_id,
                $service_id,
                $appointment_date,
                $appointment_time,
                $purpose,
                $status
            );

            if ($insert->execute()) {
                $message = 'Your appointment request has been submitted successfully. It is awaiting confirmation.';
                $message_type = 'success';
            } else {
                $message = 'Unable to book the appointment. Please try again.';
                $message_type = 'danger';
            }

            $insert->close();
        }
    }
}

/* =========================================
   3. CANCEL A PENDING APPOINTMENT
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {

    $appointment_id = filter_input(
        INPUT_POST,
        'appointment_id',
        FILTER_VALIDATE_INT
    );

    if (!$appointment_id) {

        $message = 'Invalid appointment.';
        $message_type = 'danger';

    } else {

        /*
         * Customers can cancel only their own pending appointments.
         */

        $cancel = $conn->prepare(
            "UPDATE appointments
             SET status = 'Cancelled'
             WHERE appointment_id = ?
               AND customer_id = ?
               AND status = 'Pending'
               AND appointment_date >= CURDATE()"
        );

        if (!$cancel) {
            die('Database error: ' . e($conn->error));
        }

        $cancel->bind_param(
            'ii',
            $appointment_id,
            $customer_id
        );

        $cancel->execute();

        if ($cancel->affected_rows > 0) {
            $message = 'Your appointment has been cancelled.';
            $message_type = 'success';
        } else {
            $message = 'The appointment could not be cancelled. It may not belong to you, may already be confirmed, or may be in the past.';
            $message_type = 'danger';
        }

        $cancel->close();
    }
}

/* =========================================
   4. GET AVAILABLE SERVICES
========================================= */

$services = $conn->query(
    "SELECT service_id, service_name
     FROM services
     WHERE is_active = 1
     ORDER BY service_name ASC"
);

if (!$services) {
    die('Unable to retrieve services: ' . e($conn->error));
}

/* =========================================
   5. GET THIS CUSTOMER'S APPOINTMENTS
========================================= */

$appointments_sql = "
    SELECT
        a.appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.purpose,
        a.status,
        s.service_name
    FROM appointments a
    INNER JOIN services s
        ON a.service_id = s.service_id
    WHERE a.customer_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
";

$appointments_stmt = $conn->prepare($appointments_sql);

if (!$appointments_stmt) {
    die('Database error: ' . e($conn->error));
}

$appointments_stmt->bind_param('i', $customer_id);
$appointments_stmt->execute();

$appointments_result = $appointments_stmt->get_result();

$appointments = [];

while ($appointment = $appointments_result->fetch_assoc()) {
    $appointments[] = $appointment;
}

$appointments_stmt->close();

/* =========================================
   6. COUNT APPOINTMENTS
========================================= */

$total_appointments = count($appointments);
$pending_count = 0;
$confirmed_count = 0;

foreach ($appointments as $appointment) {

    if ($appointment['status'] === 'Pending') {
        $pending_count++;
    }

    if ($appointment['status'] === 'Confirmed') {
        $confirmed_count++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Appointments | Clare Designs</title>

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
            max-width: 1300px;
            margin: 25px auto;
        }

        .page-heading {
            margin-bottom: 22px;
        }

        .page-heading h2 {
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

        .card-body {
            padding: 22px;
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
            font-size: 28px;
            color: #007bff;
            font-weight: bold;
            margin: 0 0 5px;
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

        .confirmed {
            background: #d4edda;
            color: #155724;
        }

        .cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .other {
            background: #e2e3e5;
            color: #383d41;
        }

        .form-group label {
            font-weight: bold;
        }

        .empty-state {
            text-align: center;
            padding: 30px 10px;
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

        <a href="orders.php">My Orders</a>

        <a href="appointments.php" class="active">Appointments</a>

        <a href="my_payments.php">Payments</a>

        <a href="profile.php">My Profile</a>

        <a href="../logout.php">Logout</a>

    </div>

</nav>


<div class="container-page">

    <!-- PAGE HEADING -->

    <div class="page-heading">

        <h2>My Appointments</h2>

        <p>
            Welcome, <strong><?= e($customer_name) ?></strong>.
            Book and manage your Clare Designs consultations.
        </p>

    </div>


    <!-- NOTIFICATION -->

    <?php if ($message !== ''): ?>

        <div class="alert alert-<?= e($message_type) ?>">
            <?= e($message) ?>
        </div>

    <?php endif; ?>


    <!-- SUMMARY CARDS -->

    <div class="row">

        <div class="col-md-4">

            <div class="summary-card">

                <h3><?= $total_appointments ?></h3>

                <p>Total Appointments</p>

            </div>

        </div>

        <div class="col-md-4">

            <div class="summary-card">

                <h3><?= $pending_count ?></h3>

                <p>Pending Requests</p>

            </div>

        </div>

        <div class="col-md-4">

            <div class="summary-card">

                <h3><?= $confirmed_count ?></h3>

                <p>Confirmed Appointments</p>

            </div>

        </div>

    </div>


    <!-- =========================================
         BOOK APPOINTMENT FORM
    ========================================== -->

    <div class="card">

        <div class="card-header">
            Book a New Appointment
        </div>

        <div class="card-body">

            <?php if ($services->num_rows > 0): ?>

                <form method="POST" action="appointments.php">

                    <div class="form-group">

                        <label for="service_id">
                            Select Service
                        </label>

                        <select
                            name="service_id"
                            id="service_id"
                            class="form-control"
                            required
                        >

                            <option value="">
                                -- Choose a Service --
                            </option>

                            <?php while ($service = $services->fetch_assoc()): ?>

                                <option value="<?= (int) $service['service_id'] ?>">
                                    <?= e($service['service_name']) ?>
                                </option>

                            <?php endwhile; ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label for="appointment_date">
                            Appointment Date
                        </label>

                        <input
                            type="date"
                            name="appointment_date"
                            id="appointment_date"
                            class="form-control"
                            min="<?= date('Y-m-d') ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="appointment_time">
                            Appointment Time
                        </label>

                        <input
                            type="time"
                            name="appointment_time"
                            id="appointment_time"
                            class="form-control"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="purpose">
                            Purpose of Appointment
                        </label>

                        <textarea
                            name="purpose"
                            id="purpose"
                            class="form-control"
                            rows="4"
                            maxlength="1000"
                            placeholder="Explain what you would like to discuss with Clare Designs..."
                            required
                        ></textarea>

                    </div>


                    <button
                        type="submit"
                        name="book_appointment"
                        class="btn btn-primary"
                    >
                        Submit Appointment Request
                    </button>

                </form>

            <?php else: ?>

                <div class="alert alert-warning">
                    No active services are currently available.
                    Please contact Clare Designs.
                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- =========================================
         APPOINTMENT HISTORY
    ========================================== -->

    <div class="card">

        <div class="card-header">
            My Appointment History
        </div>

        <div class="card-body">

            <?php if ($total_appointments > 0): ?>

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
                                <th>Action</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($appointments as $appointment): ?>

                            <?php
                            $status = $appointment['status'] ?? 'Pending';

                            if ($status === 'Pending') {
                                $status_class = 'pending';
                            } elseif ($status === 'Confirmed') {
                                $status_class = 'confirmed';
                            } elseif ($status === 'Cancelled') {
                                $status_class = 'cancelled';
                            } else {
                                $status_class = 'other';
                            }
                            ?>

                            <tr>

                                <td>
                                    #<?= (int) $appointment['appointment_id'] ?>
                                </td>

                                <td>
                                    <?= e($appointment['service_name']) ?>
                                </td>

                                <td>
                                    <?= e($appointment['appointment_date']) ?>
                                </td>

                                <td>
                                    <?= e($appointment['appointment_time']) ?>
                                </td>

                                <td>
                                    <?= nl2br(e($appointment['purpose'] ?? '')) ?>
                                </td>

                                <td>

                                    <span class="status-badge <?= $status_class ?>">
                                        <?= e($status) ?>
                                    </span>

                                </td>

                                <td>

                                    <?php if (
                                        $status === 'Pending' &&
                                        $appointment['appointment_date'] >= date('Y-m-d')
                                    ): ?>

                                        <form
                                            method="POST"
                                            action="appointments.php"
                                            onsubmit="return confirm('Are you sure you want to cancel this appointment?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="appointment_id"
                                                value="<?= (int) $appointment['appointment_id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="cancel_appointment"
                                                class="btn btn-sm btn-danger"
                                            >
                                                Cancel
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <span class="text-muted">No action</span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">

                    <h5>No Appointments Yet</h5>

                    <p>
                        Your appointment requests will appear here after booking.
                    </p>

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
