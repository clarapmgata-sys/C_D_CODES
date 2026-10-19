<?php
session_start();

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/mail_config.php";

$message = "";
$messageType = "info";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");

    if (empty($email)) {

        $message = "Please enter your email address.";
        $messageType = "danger";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $messageType = "danger";

    } else {

        // Find the approved administrator
        $stmt = $conn->prepare(
            "SELECT user_id, full_name, email
             FROM users
             WHERE email = ?
             AND role = 'admin'
             AND account_status = 'approved'
             LIMIT 1"
        );

        if (!$stmt) {
            die("Database query preparation failed.");
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        $stmt->close();

        if (!$user) {

            $message = "No approved administrator account was found with that email address.";
            $messageType = "warning";

        } else {

            // Generate a new secure token
            $token = bin2hex(random_bytes(32));

            // Store only the token hash in the database
            $tokenHash = hash("sha256", $token);

            // Expire the link one hour after database time
            $stmt = $conn->prepare(
                "INSERT INTO password_resets
                (user_id, token_hash, expires_at)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))"
            );

            if (!$stmt) {
                die("Could not prepare the password-reset query.");
            }

            $stmt->bind_param(
                "is",
                $user["user_id"],
                $tokenHash
            );

            if (!$stmt->execute()) {

                $message = "Unable to create your reset link. Please try again.";
                $messageType = "danger";

            } else {

                // Create the reset URL
                $baseUrl = "http://192.168.1.3/C_D_CODES";

                $resetLink = $baseUrl
                    . "/reset_password.php?token="
                    . urlencode($token);

                // Send the email
                $emailSent = sendResetEmail(
                    $user["email"],
                    $resetLink
                );

                if ($emailSent) {

                    $message = "A password-reset link has been sent. Check your inbox and spam folder.";
                    $messageType = "success";

                } else {

                    $message = "The reset link was created, but the email could not be sent. Please check your mail configuration.";
                    $messageType = "danger";
                }
            }

            $stmt->close();
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

    <!-- Existing AdminLTE stylesheet -->
    <link rel="stylesheet" href="assets/css/adminlte.min.css">
</head>

<body class="hold-transition login-page">

    <div class="login-box">

        <div class="card card-outline">

            <div class="card-header text-center">
                <h2><b>Clare Designs</b></h2>
                <p>Forgot Password</p>
            </div>

            <div class="card-body">

                <p class="login-box-msg">
                    Enter your administrator email address to receive a password-reset link.
                </p>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php
                        echo htmlspecialchars($messageType);
                    ?>">
                        <?php echo htmlspecialchars($message); ?>
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
                            value="<?php
                                echo htmlspecialchars($_POST["email"] ?? "");
                            ?>"
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
