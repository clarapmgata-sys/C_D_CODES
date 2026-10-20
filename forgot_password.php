<?php
session_start();

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/mail_config.php";

$message = "";
$messageType = "info";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");

    if ($email === "") {

        $message = "Please enter your email address.";
        $messageType = "danger";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $messageType = "danger";

    } else {

        $user = null;
        $accountType = "";

        // Check administrator or staff accounts.
        $stmt = $conn->prepare("
            SELECT user_id, full_name, email, role, account_status
            FROM users
            WHERE email = ?
              AND (
                  (role = 'admin' AND account_status = 'approved')
                  OR
                  (role IN ('staff', 'designer')
                   AND account_status = 'active')
              )
            LIMIT 1
        ");

        if (!$stmt) {
            die("Unable to prepare the user account query.");
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        $stmt->close();

        if ($user) {

            $accountType = "user";

        } else {

            // If no administrator or staff account is found,
            // check the customers table.
            $stmt = $conn->prepare("
                SELECT customer_id, full_name, email
                FROM customers
                WHERE email = ?
                  AND account_status = 'active'
                LIMIT 1
            ");

            if (!$stmt) {
                die("Unable to prepare the customer account query.");
            }

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();
            $customer = $result->fetch_assoc();

            $stmt->close();

            if ($customer) {
                $user = $customer;
                $accountType = "customer";
                $user["user_id"] = $customer["customer_id"];
            }
        }

        if (!$user) {

            $message = "No active account was found with that email address.";
            $messageType = "warning";

        } else {

            // Generate a secure random token.
            $token = bin2hex(random_bytes(32));

            // Store only the token hash.
            $tokenHash = hash("sha256", $token);

            // Save the token in the password_resets table.
            $stmt = $conn->prepare("
                INSERT INTO password_resets
                    (user_id, token_hash, expires_at)
                VALUES
                    (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
            ");

            if (!$stmt) {
                die("Unable to prepare the password-reset query. Check your password_resets table.");
            }

            $resetUserId = (int)$user["user_id"];

            $stmt->bind_param(
                "is",
                $resetUserId,
                $tokenHash
            );

            if (!$stmt->execute()) {

                $message = "Unable to create your reset link. Please try again.";
                $messageType = "danger";

                $stmt->close();

            } else {

                $stmt->close();

                // Build the reset URL using the current project address.
                $baseUrl = "http://192.168.1.3/C_D_CODES";

                $resetLink = $baseUrl
                    . "/reset_password.php?token="
                    . urlencode($token);

                // Send the reset email.
                $emailSent = sendResetEmail(
                    $user["email"],
                    $resetLink
                );

                if ($emailSent) {

                    $message = "A password-reset link has been sent. Check your inbox and spam folder.";
                    $messageType = "success";

                } else {

                    $message = "The reset link was created, but the email could not be sent. Check mail_config.php.";
                    $messageType = "danger";
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

    <title>Forgot Password | Clare Designs</title>

    <link rel="stylesheet" href="assets/css/adminlte.min.css">
</head>

<body class="hold-transition login-page">

<div class="login-box">
    <div class="card card-outline card-primary">

        <div class="card-header text-center">
            <h2><b>Clare Designs</b></h2>
            <p>Forgot Password</p>
        </div>

        <div class="card-body">

            <p class="login-box-msg">
                Enter your registered email address to receive a password-reset link.
            </p>

            <?php if ($message !== ""): ?>
                <div class="alert alert-<?=
                    htmlspecialchars($messageType, ENT_QUOTES, "UTF-8")
                ?>">
                    <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
                </div>
            <?php endif; ?>

            <form action="forgot_password.php" method="POST">

                <div class="input-group mb-3">
                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        placeholder="Email address"
                        required
                        autocomplete="email"
                        value="<?= htmlspecialchars(
                            $_POST["email"] ?? "",
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                    >

                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span>@</span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    Send Password Reset Link
                </button>

            </form>

            <p class="mt-3 mb-0 text-center">
                <a href="login.php">Back to Login</a>
            </p>

        </div>
    </div>
</div>

</body>
</html>
