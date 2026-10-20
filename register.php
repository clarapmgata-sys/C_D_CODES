<?php
session_start();
require_once 'db.php';

$message = "";
$message_type = "danger";

$full_name = "";
$business_name = "";
$email = "";
$phone = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get form data
    $full_name = trim($_POST["full_name"] ?? "");
    $business_name = trim($_POST["business_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");

    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    // Validate required fields
    if (
        $full_name === "" ||
        $email === "" ||
        $phone === "" ||
        $password === "" ||
        $confirm_password === ""
    ) {
        $message = "Please fill in all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";

    } elseif (strlen($password) < 12) {
        $message = "Your password must contain at least 12 characters.";

    } elseif ($password !== $confirm_password) {
        $message = "The passwords do not match.";

    } else {

        // Check whether email is already registered
        $check = $conn->prepare(
            "SELECT customer_id FROM customers WHERE email = ? LIMIT 1"
        );

        if (!$check) {
            $message = "Unable to check customer details.";

        } else {

            $check->bind_param("s", $email);
            $check->execute();

            $result = $check->get_result();

            if ($result->num_rows > 0) {

                $message = "This email is already registered. Please log in instead.";

            } else {

                // Hash the password securely
                $password_hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                if ($password_hash === false) {

                    $message = "Unable to secure your password. Please try again.";

                } else {

                    // Save customer and password in database
                    $insert = $conn->prepare(
                        "INSERT INTO customers
                        (full_name, business_name, email, password_hash, account_status, phone)
                        VALUES (?, ?, ?, ?, 'active', ?)"
                    );

                    if (!$insert) {

                        $message = "Unable to prepare customer registration.";

                    } else {

                        $insert->bind_param(
                            "sssss",
                            $full_name,
                            $business_name,
                            $email,
                            $password_hash,
                            $phone
                        );

                        if ($insert->execute()) {

                            $message_type = "success";
                            $message = "Registration successful! You can now log in using your email and password.";

                            // Clear form fields
                            $full_name = "";
                            $business_name = "";
                            $email = "";
                            $phone = "";

                        } else {

                            $message = "Registration failed. Please try again.";
                        }

                        $insert->close();
                    }
                }
            }

            $check->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Customer Registration | Clare Designs</title>

    <link rel="stylesheet" href="assets/css/adminlte.min.css">

    <style>
        body {
            background: #f4f6f9;
            font-family: Arial, sans-serif;
            color: #343a40;
        }

        .top-nav {
            background: #007bff;
            border-bottom: 3px solid #0056b3;
            padding: 15px 25px;
        }

        .top-nav a {
            color: white;
            text-decoration: none;
            font-weight: bold;
            margin-right: 20px;
        }

        .top-nav a:hover {
            text-decoration: underline;
        }

        .registration-container {
            max-width: 600px;
            margin: 45px auto;
            padding: 0 15px;
        }

        .registration-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .registration-header {
            background: #e7f1ff;
            border-bottom: 3px solid #007bff;
            padding: 25px;
            text-align: center;
        }

        .registration-header h2 {
            margin-bottom: 8px;
            color: #0056b3;
        }

        .registration-body {
            padding: 25px;
        }

        .form-control {
            border-radius: 5px;
        }

        .btn-register {
            background: #007bff;
            color: white;
            width: 100%;
            padding: 11px;
            font-weight: bold;
        }

        .btn-register:hover {
            background: #0056b3;
            color: white;
        }

        .required {
            color: red;
        }

        .footer-text {
            text-align: center;
            margin-top: 20px;
        }

        @media (max-width: 600px) {
            .registration-body {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<nav class="top-nav">
    <a href="index.php">Home</a>
    <a href="login.php">Login</a>
</nav>

<div class="registration-container">

    <div class="registration-card">

        <div class="registration-header">
            <h2>Clare Designs</h2>
            <p>Create your customer account</p>
        </div>

        <div class="registration-body">

            <?php if ($message !== ""): ?>
                <div class="alert alert-<?= htmlspecialchars($message_type) ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php">

                <div class="form-group">
                    <label for="full_name">
                        Full Name <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        class="form-control"
                        value="<?= htmlspecialchars($full_name) ?>"
                        required
                        maxlength="100">
                </div>

                <div class="form-group">
                    <label for="business_name">Business Name (Optional)</label>

                    <input
                        type="text"
                        id="business_name"
                        name="business_name"
                        class="form-control"
                        value="<?= htmlspecialchars($business_name) ?>"
                        maxlength="150">
                </div>

                <div class="form-group">
                    <label for="email">
                        Email Address <span class="required">*</span>
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        value="<?= htmlspecialchars($email) ?>"
                        required
                        maxlength="150">
                </div>

                <div class="form-group">
                    <label for="phone">
                        Phone Number <span class="required">*</span>
                    </label>

                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        class="form-control"
                        value="<?= htmlspecialchars($phone) ?>"
                        required
                        maxlength="20">
                </div>

                <div class="form-group">
                    <label for="password">
                        Create Password <span class="required">*</span>
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Create a password"
                        minlength="12"
                        maxlength="72"
                        autocomplete="new-password"
                        required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">
                        Confirm Password <span class="required">*</span>
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        class="form-control"
                        placeholder="Confirm your password"
                        minlength="12"
                        maxlength="72"
                        autocomplete="new-password"
                        required>
                </div>

                <button type="submit" class="btn btn-register">
                    Create Account
                </button>

            </form>

            <div class="footer-text">
                Already registered?
                <a href="login.php">Login here</a>
            </div>

        </div>
    </div>

</div>

</body>
</html>
