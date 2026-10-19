<?php

require_once "db.php";

$message = "";
$messageType = "info";

$token = $_GET["token"] ?? "";
$validToken = false;


// STEP 1: Validate the token from the email

if (
    is_string($token) &&
    preg_match('/^[a-f0-9]{64}$/', $token)
) {

    $tokenHash = hash("sha256", $token);

    $stmt = $conn->prepare(
        "SELECT
            password_resets.reset_id,
            password_resets.user_id

         FROM password_resets

         INNER JOIN users
         ON password_resets.user_id = users.user_id

         WHERE password_resets.token_hash = ?

         AND password_resets.expires_at > NOW()

         AND password_resets.used_at IS NULL

         AND users.role = 'admin'

         AND users.account_status = 'approved'

         LIMIT 1"
    );

    $stmt->bind_param("s", $tokenHash);

    $stmt->execute();

    $result = $stmt->get_result();

    $reset = $result->fetch_assoc();

    $stmt->close();

    if ($reset) {
        $validToken = true;
    }
}


// STEP 2: Process the new password form

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["token"] ?? "";

    $password = $_POST["password"] ?? "";

    $confirmPassword =
        $_POST["confirm_password"] ?? "";

    if (
        !is_string($token) ||
        !preg_match('/^[a-f0-9]{64}$/', $token)
    ) {

        $message =
            "Invalid reset link. Request a new one.";

        $messageType = "danger";

    } elseif (
        !is_string($password) ||
        strlen($password) < 12
    ) {

        $message =
            "Your password must contain at least 12 characters.";

        $messageType = "danger";

    } elseif (
        !is_string($confirmPassword) ||
        $password !== $confirmPassword
    ) {

        $message =
            "The passwords do not match.";

        $messageType = "danger";

    } else {

        $tokenHash = hash("sha256", $token);

        // Verify the token again before updating the password
        $stmt = $conn->prepare(
            "SELECT
                password_resets.reset_id,
                password_resets.user_id

             FROM password_resets

             INNER JOIN users
             ON password_resets.user_id = users.user_id

             WHERE password_resets.token_hash = ?

             AND password_resets.expires_at > NOW()

             AND password_resets.used_at IS NULL

             AND users.role = 'admin'

             AND users.account_status = 'approved'

             LIMIT 1"
        );

        $stmt->bind_param("s", $tokenHash);

        $stmt->execute();

        $result = $stmt->get_result();

        $reset = $result->fetch_assoc();

        $stmt->close();

        if (!$reset) {

            $message =
                "This reset link is invalid, expired, "
                . "or has already been used.";

            $messageType = "danger";

            $validToken = false;

        } else {

            // Securely hash the new password
            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            try {

                $conn->begin_transaction();

                // Update the administrator's password
                $stmt = $conn->prepare(
                    "UPDATE users
                     SET password_hash = ?
                     WHERE user_id = ?
                     AND role = 'admin'
                     AND account_status = 'approved'"
                );

                $stmt->bind_param(
                    "si",
                    $passwordHash,
                    $reset["user_id"]
                );

                if (!$stmt->execute()) {

                    throw new Exception(
                        "Password update failed."
                    );
                }

                $stmt->close();

                // Mark the token as used
                $stmt = $conn->prepare(
                    "UPDATE password_resets
                     SET used_at = NOW()
                     WHERE reset_id = ?
                     AND used_at IS NULL"
                );

                $stmt->bind_param(
                    "i",
                    $reset["reset_id"]
                );

                if (
                    !$stmt->execute() ||
                    $stmt->affected_rows !== 1
                ) {

                    throw new Exception(
                        "The reset token could not be completed."
                    );
                }

                $stmt->close();

                // Remove remaining reset requests for this admin
                $stmt = $conn->prepare(
                    "DELETE FROM password_resets
                     WHERE user_id = ?"
                );

                $stmt->bind_param(
                    "i",
                    $reset["user_id"]
                );

                $stmt->execute();

                $stmt->close();

                $conn->commit();

                // Redirect to login after success
                header("Location: login.php?reset=success");

                exit;

            } catch (Throwable $e) {

                $conn->rollback();

                error_log(
                    "Password reset failed: "
                    . $e->getMessage()
                );

                $message =
                    "The password could not be changed. "
                    . "Please request a new reset link if needed.";

                $messageType = "danger";
            }
        }
    }


    // Recheck the token to decide whether to display the form
    if (
        is_string($token) &&
        preg_match('/^[a-f0-9]{64}$/', $token)
    ) {

        $tokenHash = hash("sha256", $token);

        $stmt = $conn->prepare(
            "SELECT password_resets.reset_id

             FROM password_resets

             INNER JOIN users
             ON password_resets.user_id = users.user_id

             WHERE password_resets.token_hash = ?

             AND password_resets.expires_at > NOW()

             AND password_resets.used_at IS NULL

             AND users.role = 'admin'

             AND users.account_status = 'approved'

             LIMIT 1"
        );

        $stmt->bind_param("s", $tokenHash);

        $stmt->execute();

        $result = $stmt->get_result();

        $validToken = ($result->num_rows > 0);

        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Reset Password - Clare Designs</title>

    <link rel="stylesheet"
          href="assets/css/adminlte.min.css">

</head>

<body class="hold-transition login-page">

<div class="login-box">

    <div class="card card-outline card-primary">

        <div class="card-header text-center">

            <h3>Clare Designs</h3>

            <p>Reset Administrator Password</p>

        </div>

        <div class="card-body">

            <?php if ($message !== ""): ?>

                <div class="alert alert-<?php
                    echo htmlspecialchars($messageType);
                ?>">

                    <?php
                    echo htmlspecialchars($message);
                    ?>

                </div>

            <?php endif; ?>


            <?php if ($validToken): ?>

                <p>
                    Enter and confirm your new password below.
                </p>

                <form method="POST"
                      action="reset_password.php">

                    <input
                        type="hidden"
                        name="token"
                        value="<?php
                            echo htmlspecialchars(
                                $token,
                                ENT_QUOTES,
                                "UTF-8"
                            );
                        ?>"
                    >

                    <div class="form-group">

                        <label for="password">
                            New Password
                        </label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            minlength="12"
                            autocomplete="new-password"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="confirm_password">
                            Confirm New Password
                        </label>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            class="form-control"
                            minlength="12"
                            autocomplete="new-password"
                            required
                        >

                    </div>

                    <button
                        type="submit"
                        class="btn btn-primary btn-block">

                        Reset Password

                    </button>

                </form>

            <?php else: ?>

                <p>
                    Your reset link is invalid or has expired.
                    Request a new link to continue.
                </p>

                <a
                    href="forgot_password.php"
                    class="btn btn-primary btn-block">

                    Request New Reset Link

                </a>

            <?php endif; ?>

            <div class="text-center mt-3">

                <a href="login.php">
                    Back to Login
                </a>

            </div>

        </div>

    </div>

</div>

</body>

</html>