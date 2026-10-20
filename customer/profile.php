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

function escape($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

// Process profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');
    $business_name = trim($_POST['business_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($full_name === '' || $email === '' || $phone === '') {
        $error = "Please fill in your full name, email, and phone number.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";

    } elseif (strlen($full_name) > 100 || strlen($email) > 150 ||
              strlen($phone) > 20 || strlen($business_name) > 150) {
        $error = "One or more fields exceed the allowed length.";

    } elseif ($password !== '' && strlen($password) < 12) {
        $error = "A new password must contain at least 12 characters.";

    } elseif ($password !== $confirm_password) {
        $error = "The new password and confirmation do not match.";
    }

    // Check whether another customer is already using this email
    if ($error === '') {
        $check = $conn->prepare(
            "SELECT customer_id FROM customers
             WHERE email = ? AND customer_id != ?
             LIMIT 1"
        );

        if ($check) {
            $check->bind_param("si", $email, $customer_id);
            $check->execute();

            $existing = $check->get_result();

            if ($existing->num_rows > 0) {
                $error = "That email address is already being used by another customer.";
            }

            $check->close();
        } else {
            $error = "Unable to check the email address.";
        }
    }

    // Update the profile
    if ($error === '') {

        if ($password !== '') {

            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $sql = "
                UPDATE customers
                SET full_name = ?,
                    business_name = ?,
                    email = ?,
                    phone = ?,
                    password_hash = ?
                WHERE customer_id = ?
            ";

            $stmt = $conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param(
                    "sssssi",
                    $full_name,
                    $business_name,
                    $email,
                    $phone,
                    $password_hash,
                    $customer_id
                );
            }

        } else {

            $sql = "
                UPDATE customers
                SET full_name = ?,
                    business_name = ?,
                    email = ?,
                    phone = ?
                WHERE customer_id = ?
            ";

            $stmt = $conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param(
                    "ssssi",
                    $full_name,
                    $business_name,
                    $email,
                    $phone,
                    $customer_id
                );
            }
        }

        if (isset($stmt) && $stmt) {

            if ($stmt->execute()) {
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;

                $message = "Your profile has been updated successfully.";
            } else {
                $error = "Unable to update your profile. Please try again.";
            }

            $stmt->close();

        } elseif ($error === '') {
            $error = "Unable to prepare the profile update.";
        }
    }
}

// Retrieve the latest profile details
$stmt = $conn->prepare(
    "SELECT full_name, business_name, email, phone, account_status
     FROM customers
     WHERE customer_id = ?
     LIMIT 1"
);

$customer = null;

if ($stmt) {
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();

    $stmt->close();
}

if (!$customer) {
    exit("Customer profile could not be found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile | Clare Designs</title>

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
            background: #1769aa;
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
            background: #0d4778;
        }

        .container {
            width: 94%;
            max-width: 800px;
            margin: 30px auto;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        label {
            display: block;
            margin-top: 16px;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 15px;
        }

        input:focus {
            outline: none;
            border-color: #1769aa;
        }

        .btn {
            margin-top: 22px;
            padding: 12px 18px;
            background: #1769aa;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 15px;
        }

        .btn:hover {
            background: #0d4778;
        }

        .message {
            background: #d1e7dd;
            color: #0f5132;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .error {
            background: #f8d7da;
            color: #842029;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .help {
            color: #666;
            font-size: 13px;
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
        <a href="my_payments.php">Payments</a>
        <a href="profile.php" class="active">My Profile</a>
        <a href="../logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <h2>My Profile</h2>

    <p>View and update your customer account information.</p>

    <div class="card">

        <?php if ($message !== ''): ?>
            <div class="message"><?= escape($message) ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="error"><?= escape($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="profile.php">

            <label for="full_name">Full Name</label>
            <input
                type="text"
                id="full_name"
                name="full_name"
                maxlength="100"
                value="<?= escape($customer['full_name']) ?>"
                required
            >

            <label for="business_name">Business Name</label>
            <input
                type="text"
                id="business_name"
                name="business_name"
                maxlength="150"
                value="<?= escape($customer['business_name']) ?>"
            >

            <label for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                maxlength="150"
                value="<?= escape($customer['email']) ?>"
                required
            >

            <label for="phone">Phone Number</label>
            <input
                type="text"
                id="phone"
                name="phone"
                maxlength="20"
                value="<?= escape($customer['phone']) ?>"
                required
            >

            <label for="password">New Password (Optional)</label>
            <input
                type="password"
                id="password"
                name="password"
                minlength="12"
                autocomplete="new-password"
            >

            <p class="help">
                Leave the password fields empty if you do not want to change your password.
                If changing it, use at least 12 characters.
            </p>

            <label for="confirm_password">Confirm New Password</label>
            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                minlength="12"
                autocomplete="new-password"
            >

            <button type="submit" class="btn">
                Save Changes
            </button>

        </form>

        <p>
            <strong>Account Status:</strong>
            <?= escape($customer['account_status'] ?? 'Active') ?>
        </p>

    </div>

</div>

</body>
</html>