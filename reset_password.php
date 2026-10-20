<?php
session_start();

require_once "db.php";

error_reporting(E_ALL);
ini_set("display_errors", 1);

$message = "";
$messageType = "danger";
$reset = null;

// Get the token from the URL or submitted form.
$token = $_GET["token"] ?? $_POST["token"] ?? "";

if (!is_string($token)) {
    $token = "";
}

// Function to check whether the reset token is valid.
function findValidReset($conn, $token)
{
    // A SHA-256 reset token should contain 64 hexadecimal characters.
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $tokenHash = hash("sha256", $token);

    $sql = "
        SELECT reset_id, user_id, account_type
        FROM password_resets
        WHERE token_hash = ?
          AND expires_at > NOW()
          AND used_at IS NULL
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Reset query error: " . $conn->error);
        return null;
    }

    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();

    $result = $stmt->get_result();
    $reset = $result->fetch_assoc();

    $stmt->close();

    if (!$reset) {
        return null;
    }

    // Verify admin, staff, or designer accounts.
    if ($reset["account_type"] === "users") {

        $stmt = $conn->prepare("
            SELECT user_id
            FROM users
            WHERE user_id = ?
              AND (
                    (role = 'admin'
                     AND account_status = 'approved')
                    OR
                    (role IN ('staff', 'designer')
                     AND account_status = 'active')
                  )
            LIMIT 1
        ");

        if (!$stmt) {
            error_log("User verification error: " . $conn->error);
            return null;
        }

        $stmt->bind_param("i", $reset["user_id"]);
        $stmt->execute();

        $result = $stmt->get_result();
        $accountExists = $result->num_rows > 0;

        $stmt->close();

        return $accountExists ? $reset : null;
    }

    // Verify customer accounts.
    if ($reset["account_type"] === "customers") {

        $stmt = $conn->prepare("
            SELECT customer_id
            FROM customers
            WHERE customer_id = ?
              AND account_status = 'active'
            LIMIT 1
        ");

        if (!$stmt) {
            error_log("Customer verification error: " . $conn->error);
            return null;
        }

        $stmt->bind_param("i", $reset["user_id"]);
        $stmt->execute();

        $result = $stmt->get_result();
        $accountExists = $result->num_rows > 0;

        $stmt->close();

        return $accountExists ? $reset : null;
    }

    return null;
}


// Process the password reset form.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $password = $_POST["new_password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    // Check the token again when the form is submitted.
    $reset = findValidReset($conn, $token);

    if (!$reset) {

        $message = "This reset link is invalid, expired, or already used.";
        $messageType = "danger";

    } elseif (strlen($password) < 12) {

        $message = "Your password must contain at least 12 characters.";
        $messageType = "warning";

    } elseif ($password !== $confirmPassword) {

        $message = "The passwords do not match.";
        $messageType = "warning";

    } else {

        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        try {

            $conn->begin_transaction();

            // Update the correct account.
            if ($reset["account_type"] === "users") {

                $stmt = $conn->prepare("
                    UPDATE users
                    SET password_hash = ?
                    WHERE user_id = ?
                ");

            } else {

                $stmt = $conn->prepare("
                    UPDATE customers
                    SET password_hash = ?
                    WHERE customer_id = ?
                ");
            }

            if (!$stmt) {
                throw new Exception("Could not prepare password update.");
            }

            $stmt->bind_param(
                "si",
                $passwordHash,
                $reset["user_id"]
            );

            if (!$stmt->execute()) {
                throw new Exception("Could not update the password.");
            }

            $stmt->close();

            // Mark this reset token as used.
            $stmt = $conn->prepare("
                UPDATE password_resets
                SET used_at = NOW()
                WHERE reset_id = ?
                  AND used_at IS NULL
            ");

            if (!$stmt) {
                throw new Exception("Could not prepare token update.");
            }

            $stmt->bind_param("i", $reset["reset_id"]);

            if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                throw new Exception("Could not mark the reset token as used.");
            }

            $stmt->close();

            // Remove other reset requests for the same account.
            $stmt = $conn->prepare("
                DELETE FROM password_resets
                WHERE user_id = ?
                  AND account_type = ?
                  AND reset_id != ?
            ");

            if (!$stmt) {
                throw new Exception("Could not prepare token cleanup.");
            }

            $stmt->bind_param(
                "isi",
                $reset["user_id"],
                $reset["account_type"],
                $reset["reset_id"]
            );

            if (!$stmt->execute()) {
                throw new Exception("Could not clean up old reset requests.");
            }

            $stmt->close();

            $conn->commit();

            // Redirect after a successful password reset.
            header("Location: login.php?reset=success");
            exit;

        } catch (Throwable $e) {

            $conn->rollback();

            error_log("Password reset error: " . $e->getMessage());

            $message = "Something went wrong while resetting your password. Please try again.";
            $messageType = "danger";
        }
    }

} else {

    // Validate the token when the page first opens.
    $reset = findValidReset($conn, $token);

    if (!$reset) {
        $message = "This reset link is invalid, expired, or already used. Please request a new reset link.";
        $messageType = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reset Password | Clare Designs</title>

    <style>
       ```css
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    font-family: Arial, sans-serif;
    background: #eaf2ff;
}

.reset-container {
    width: 100%;
    max-width: 430px;
    padding: 30px;
    background: #ffffff;
    border-radius: 14px;
    box-shadow: 0 8px 25px rgba(13, 71, 161, 0.15);
    border-top: 5px solid #0d6efd;
}

h2 {
    text-align: center;
    color: #0d6efd;
    margin-top: 0;
}

p {
    text-align: center;
    color: #555555;
    line-height: 1.5;
}

label {
    display: block;
    margin-top: 18px;
    margin-bottom: 7px;
    font-weight: bold;
    color: #333333;
}

input {
    width: 100%;
    padding: 12px;
    border: 1px solid #b8c9e6;
    border-radius: 7px;
    font-size: 15px;
    outline: none;
}

input:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.12);
}

button,
.link-button {
    display: block;
    width: 100%;
    margin-top: 22px;
    padding: 13px;
    background: #0d6efd;
    color: #ffffff;
    border: none;
    border-radius: 7px;
    text-align: center;
    text-decoration: none;
    font-size: 16px;
    cursor: pointer;
    transition: background 0.3s ease;
}

button:hover,
.link-button:hover {
    background: #084298;
}

.message {
    padding: 12px;
    border-radius: 7px;
    margin-bottom: 18px;
    line-height: 1.5;
}

.danger {
    background: #f8d7da;
    color: #842029;
}

.warning {
    background: #fff3cd;
    color: #664d03;
}

.back-link {
    display: block;
    text-align: center;
    margin-top: 20px;
    color: #0d6efd;
    text-decoration: none;
}

.back-link:hover {
    color: #084298;
    text-decoration: underline;
}
```

    </style>
</head>

<body>

<div class="reset-container">

    <h2>Reset Your Password</h2>

    <p>Clare Designs Management System</p>

    <?php if ($message !== ""): ?>
        <div class="message <?= htmlspecialchars($messageType) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>


    <?php if ($reset): ?>

        <p>Enter your new password below.</p>

        <form method="POST" action="reset_password.php">

            <input
                type="hidden"
                name="token"
                value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"
            >

            <label for="new_password">New Password</label>

            <input
                type="password"
                id="new_password"
                name="new_password"
                minlength="12"
                autocomplete="new-password"
                required
            >

            <label for="confirm_password">Confirm New Password</label>

            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                minlength="12"
                autocomplete="new-password"
                required
            >

            <button type="submit">Update Password</button>

        </form>

    <?php else: ?>

        <a class="link-button" href="forgot_password.php">
            Request New Reset Link
        </a>

    <?php endif; ?>

    <a class="back-link" href="login.php">Back to Login</a>

</div>

</body>
</html>