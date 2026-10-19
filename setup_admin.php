<?php
require_once "db.php";

$message = "";

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM users WHERE role = 'admin'"
);
$admin_exists = $result->fetch_assoc()['total'] > 0;

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$admin_exists) {

    $full_name = trim($_POST["full_name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $password = $_POST["password"];

    if (
        $full_name === "" ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($password) < 8
    ) {
        $message = "Enter a name, a valid email, and a password of at least 8 characters.";

    } else {

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            "INSERT INTO users
            (full_name, email, phone, password_hash, role, account_status)
            VALUES (?, ?, ?, ?, 'admin', 'approved')"
        );

        $stmt->bind_param(
            "ssss",
            $full_name,
            $email,
            $phone,
            $password_hash
        );

        if ($stmt->execute()) {
            $message = "Administrator account created successfully. Delete setup_admin.php now.";
            $admin_exists = true;
        } else {
            $message = "Could not create the account. The email may already be registered.";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Administrator | Clare Designs</title>

    <link rel="stylesheet" href="assets/css/adminlte.min.css">
</head>

<body class="bg-light">

<div class="container mt-5">

    <div class="card mx-auto" style="max-width: 500px;">

        <div class="card-header">
            <h3>Create Administrator Account</h3>
        </div>

        <div class="card-body">

            <?php if ($message !== ""): ?>
                <div class="alert alert-info">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (!$admin_exists): ?>

                <form method="POST">

                    <div class="form-group mb-3">
                        <label>Full Name</label>
                        <input
                            type="text"
                            name="full_name"
                            class="form-control"
                            required
                        >
                    </div>

                    <div class="form-group mb-3">
                        <label>Email Address</label>
                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            required
                        >
                    </div>

                    <div class="form-group mb-3">
                        <label>Phone Number</label>
                        <input
                            type="text"
                            name="phone"
                            class="form-control"
                        >
                    </div>

                    <div class="form-group mb-3">
                        <label>Password</label>
                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            minlength="8"
                            required
                        >
                        <small>Use at least 8 characters.</small>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Create Administrator
                    </button>

                </form>

            <?php else: ?>

                <p>An administrator account already exists, or the account has just been created.</p>
                <p>Delete <strong>setup_admin.php</strong> before continuing.</p>

            <?php endif; ?>

        </div>
    </div>

</div>

</body>
</html>