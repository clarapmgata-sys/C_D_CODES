<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'db.php';

$error = '';
$selectedRole = '';

// Redirect already logged-in users
if (isset($_SESSION['role'])) {

    if (
        $_SESSION['role'] === 'admin' &&
        isset($_SESSION['user_id'])
    ) {
        header("Location: admin/dashboard.php");
        exit;
    }

    if (
        in_array($_SESSION['role'], ['staff', 'designer'], true) &&
        isset($_SESSION['user_id'], $_SESSION['staff_id'])
    ) {
        header("Location: staff/dashboard.php");
        exit;
    }

    if (
        $_SESSION['role'] === 'customer' &&
        isset($_SESSION['customer_id'])
    ) {
        header("Location: customer/dashboard.php");
        exit;
    }
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $selectedRole = $_POST['role'] ?? '';

    $allowedRoles = ['admin', 'staff', 'customer'];

    if (
        $email === '' ||
        $password === '' ||
        !in_array($selectedRole, $allowedRoles, true)
    ) {
        $error = "Please enter your email, password, and account type.";

    } else {

        // =====================================
        // ADMIN AND STAFF LOGIN
        // =====================================
        if ($selectedRole === 'admin' || $selectedRole === 'staff') {

            $stmt = $conn->prepare("
                SELECT user_id, full_name, email, password_hash,
                       role, account_status
                FROM users
                WHERE email = ?
                LIMIT 1
            ");

            if (!$stmt) {

                $error = "Unable to process login. Please try again.";

            } else {

                $stmt->bind_param("s", $email);
                $stmt->execute();

                $result = $stmt->get_result();
                $user = $result->fetch_assoc();

                $stmt->close();

                if (
                    $user &&
                    !empty($user['password_hash']) &&
                    password_verify($password, $user['password_hash'])
                ) {

                    $databaseRole = strtolower($user['role']);
                    $accountStatus = strtolower($user['account_status']);

                    // Check role and account status
                    $validAdmin =
                        $selectedRole === 'admin' &&
                        $databaseRole === 'admin' &&
                        $accountStatus === 'approved';

                    $validStaff =
                        $selectedRole === 'staff' &&
                        in_array(
                            $databaseRole,
                            ['staff', 'designer'],
                            true
                        ) &&
                        $accountStatus === 'active';

                    if ($validAdmin || $validStaff) {

                        $staffId = null;

                        // Find staff profile for staff/designer
                        if ($validStaff) {

                            $staffStmt = $conn->prepare("
                                SELECT staff_id
                                FROM staff
                                WHERE user_id = ?
                                LIMIT 1
                            ");

                            if (!$staffStmt) {

                                $error = "Unable to check your staff profile.";

                            } else {

                                $staffStmt->bind_param(
                                    "i",
                                    $user['user_id']
                                );

                                $staffStmt->execute();

                                $staffResult = $staffStmt->get_result();
                                $staffRecord = $staffResult->fetch_assoc();

                                $staffStmt->close();

                                if (!$staffRecord) {
                                    $error = "Your staff profile has not been created. Contact the administrator.";
                                } else {
                                    $staffId = (int)$staffRecord['staff_id'];
                                }
                            }
                        }

                        if ($error === '') {

                            session_regenerate_id(true);

                            $_SESSION['user_id'] = (int)$user['user_id'];
                            $_SESSION['full_name'] = $user['full_name'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['role'] = $databaseRole;

                            if ($validStaff) {

                                $_SESSION['staff_id'] = $staffId;

                                header("Location: staff/dashboard.php");
                                exit;
                            }

                            header("Location: admin/dashboard.php");
                            exit;
                        }

                    } else {

                        $error = "Invalid email, password, account type, or inactive account.";
                    }

                } else {

                    $error = "Invalid email, password, account type, or inactive account.";
                }
            }

        } else {

            // =====================================
            // CUSTOMER LOGIN
            // =====================================

            $stmt = $conn->prepare("
                SELECT customer_id, full_name, email,
                       password_hash, account_status
                FROM customers
                WHERE email = ?
                LIMIT 1
            ");

            if (!$stmt) {

                $error = "Unable to process customer login. Check your database structure.";

            } else {

                $stmt->bind_param("s", $email);
                $stmt->execute();

                $result = $stmt->get_result();
                $customer = $result->fetch_assoc();

                $stmt->close();

                if (
                    $customer &&
                    !empty($customer['password_hash']) &&
                    password_verify(
                        $password,
                        $customer['password_hash']
                    ) &&
                    strtolower($customer['account_status']) === 'active'
                ) {

                    session_regenerate_id(true);

                    // Store customer session
                    $_SESSION['customer_id'] =
                        (int)$customer['customer_id'];

                    $_SESSION['full_name'] =
                        $customer['full_name'];

                    $_SESSION['email'] =
                        $customer['email'];

                    $_SESSION['role'] = 'customer';

                    // Redirect to customer dashboard
                    header("Location: customer/dashboard.php");
                    exit;

                } else {

                    $error = "Invalid customer email or password, or your account is inactive. If you registered before adding a password, please register with a new email or contact the administrator.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Clare Designs</title>

    <link rel="stylesheet" href="assets/css/adminlte.min.css">

    <style>
        body {
            background: #f4f6f9;
            font-family: Arial, sans-serif;
            color: #343a40;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 460px;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 18px rgba(0, 0, 0, .12);
        }

        .login-header {
            background: #007bff;
            border-bottom: 4px solid #0056b3;
            color: #fff;
            padding: 25px 20px;
            text-align: center;
        }

        .login-header h1 {
            font-size: 25px;
            font-weight: bold;
            margin: 0 0 8px;
        }

        .login-header p {
            margin: 0;
        }

        .login-body {
            padding: 25px;
        }

        .form-control {
            min-height: 44px;
            border-radius: 5px;
        }

        .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 .2rem rgba(0, 123, 255, .15);
        }

        .role-label {
            font-weight: bold;
            margin-bottom: 8px;
        }

        .btn-login {
            background: #007bff;
            border: 1px solid #007bff;
            color: white;
            width: 100%;
            padding: 11px;
            font-weight: bold;
            border-radius: 5px;
        }

        .btn-login:hover {
            background: #0056b3;
            border-color: #0056b3;
            color: white;
        }

        .login-footer {
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
            padding: 15px;
            text-align: center;
            font-size: 14px;
        }

        .login-footer a {
            color: #007bff;
            font-weight: bold;
        }

        .forgot-password {
            text-align: right;
            margin-bottom: 15px;
        }
    </style>

</head>

<body>

<div class="login-container">

    <div class="login-header">

        <h1>CLARE DESIGNS</h1>

        <p>Welcome back! Please log in to continue.</p>

    </div>

    <div class="login-body">

        <?php if ($error !== ''): ?>

            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="login.php">

            <div class="form-group">

                <label class="role-label" for="role">
                    Login As
                </label>

                <select
                    name="role"
                    id="role"
                    class="form-control"
                    required>

                    <option value="">
                        Select your account type
                    </option>

                    <option
                        value="admin"
                        <?= $selectedRole === 'admin' ? 'selected' : '' ?>>
                        Administrator
                    </option>

                    <option
                        value="staff"
                        <?= $selectedRole === 'staff' ? 'selected' : '' ?>>
                        Staff / Designer
                    </option>

                    <option
                        value="customer"
                        <?= $selectedRole === 'customer' ? 'selected' : '' ?>>
                        Customer / Client
                    </option>

                </select>

            </div>

            <div class="form-group">

                <label for="email">Email Address</label>

                <input
                    type="email"
                    name="email"
                    id="email"
                    class="form-control"
                    placeholder="Enter your email address"
                    value="<?= htmlspecialchars(
                        $_POST['email'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    autocomplete="username"
                    maxlength="150"
                    required>

            </div>

            <div class="form-group">

                <label for="password">Password</label>

                <input
                    type="password"
                    name="password"
                    id="password"
                    class="form-control"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required>

            </div>

            <div class="forgot-password">

                <a href="forgot_password.php">
                    Forgot Password?
                </a>

            </div>

            <button type="submit" class="btn btn-login">
                Login
            </button>

        </form>

    </div>

    <div class="login-footer">

        New customer?

        <a href="register.php">Create an account</a>

    </div>

</div>

</body>

</html>
