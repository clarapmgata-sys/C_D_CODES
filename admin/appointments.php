<?php
session_start();
require_once __DIR__ . "/../db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

if (($_SESSION["role"] ?? "") !== "admin") {
    header("Location: dashboard.php");
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$message_type = "success";

if (isset($_GET["message"])) {
    $message = $_GET["message"];
    $message_type = $_GET["type"] ?? "success";
}

// ADD, UPDATE AND DELETE
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        die("Invalid request. Refresh the page and try again.");
    }

    $action = $_POST["action"] ?? "";

    try {
        if ($action === "add") {
            $full_name = trim($_POST["full_name"] ?? "");
            $business_name = trim($_POST["business_name"] ?? "");
            $email = trim($_POST["email"] ?? "");
            $phone = trim($_POST["phone"] ?? "");

            if ($full_name === "") {
                throw new Exception("Customer name is required.");
            }

            if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Enter a valid email address.");
            }

            $stmt = $conn->prepare(
                "INSERT INTO customers
                (full_name, business_name, email, phone)
                VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("ssss", $full_name, $business_name, $email, $phone);
            $stmt->execute();
            $stmt->close();

            header("Location: customers.php?message=" . urlencode("Customer added successfully.") . "&type=success");
            exit;

        } elseif ($action === "update") {
            $customer_id = (int)($_POST["customer_id"] ?? 0);
            $full_name = trim($_POST["full_name"] ?? "");
            $business_name = trim($_POST["business_name"] ?? "");
            $email = trim($_POST["email"] ?? "");
            $phone = trim($_POST["phone"] ?? "");

            if ($customer_id <= 0 || $full_name === "") {
                throw new Exception("Enter a valid customer name.");
            }

            if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Enter a valid email address.");
            }

            $stmt = $conn->prepare(
                "UPDATE customers
                 SET full_name=?, business_name=?, email=?, phone=?
                 WHERE customer_id=?"
            );
            $stmt->bind_param("ssssi", $full_name, $business_name, $email, $phone, $customer_id);
            $stmt->execute();
            $stmt->close();

            header("Location: customers.php?message=" . urlencode("Customer updated successfully.") . "&type=success");
            exit;

        } elseif ($action === "delete") {
            $customer_id = (int)($_POST["customer_id"] ?? 0);

            if ($customer_id <= 0) {
                throw new Exception("Invalid customer ID.");
            }

            $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id=?");
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                $stmt->close();
                throw new Exception("Customer not found.");
            }

            $stmt->close();

            header("Location: customers.php?message=" . urlencode("Customer deleted successfully.") . "&type=success");
            exit;
        }

    } catch (mysqli_sql_exception $e) {
        if ((string)$e->getCode() === "1451") {
            $message = "This customer has existing appointments or orders and cannot be deleted.";
        } else {
            $message = "A database error occurred. Check the information and try again.";
        }
        $message_type = "danger";

    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = "danger";
    }
}

// SEARCH
$search = trim($_GET["search"] ?? "");

if ($search !== "") {
    $term = "%" . $search . "%";

    $stmt = $conn->prepare(
        "SELECT * FROM customers
         WHERE full_name LIKE ?
         OR business_name LIKE ?
         OR email LIKE ?
         OR phone LIKE ?
         ORDER BY customer_id DESC"
    );
    $stmt->bind_param("ssss", $term, $term, $term, $term);
    $stmt->execute();
    $result = $stmt->get_result();

} else {
    $result = $conn->query("SELECT * FROM customers ORDER BY customer_id DESC");
}

$count_result = $conn->query("SELECT COUNT(*) AS total FROM customers");
$total_customers = $count_result->fetch_assoc()["total"];

function escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// EDIT
$edit_customer = null;

if (isset($_GET["edit"])) {
    $edit_id = (int)$_GET["edit"];

    if ($edit_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id=?");
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $edit_customer = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$edit_customer) {
            $message = "Customer not found.";
            $message_type = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Customers | Clare Designs</title>

    <link rel="stylesheet" href="../assets/css/adminlte.min.css">

    <style>
        body {
    background: #f4f6f9;
    font-family: Arial, sans-serif;
    color: #343a40;
    margin: 0;
}

/* BLUE HORIZONTAL NAVIGATION */
.main-header {
    width: 100%;
    margin-left: 0 !important;
    padding: 10px 18px;
    background: #007bff !important;
    border-bottom: 3px solid #0056b3;
    display: flex !important;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    position: relative;
}

.navbar-brand {
    color: #fff !important;
    font-size: 20px;
    font-weight: bold;
    margin-right: 20px;
}

.main-header .navbar-nav {
    display: flex !important;
    flex-direction: row !important;
    align-items: center;
    flex-wrap: wrap;
    padding: 0;
    margin: 0;
    list-style: none;
}

.main-header .nav-item {
    display: block !important;
}

.main-header .nav-link {
    display: block !important;
    color: #fff !important;
    font-weight: 600;
    padding: 10px 12px !important;
    margin: 3px;
    border-radius: 5px;
    transition: 0.2s;
}

.main-header .nav-link:hover,
.main-header .nav-link.active {
    background: #0056b3 !important;
    color: white !important;
}

/* REMOVE SIDEBAR */
.main-sidebar {
    display: none !important;
}

.content-wrapper,
.main-footer {
    margin-left: 0 !important;
}

.content-wrapper {
    background: #f4f6f9;
    min-height: calc(100vh - 130px);
}

.content-header {
    padding: 25px 20px 10px;
}

.content-header h1 {
    color: #007bff;
    font-weight: bold;
}

/* CARDS */
.card {
    border: none;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
    margin-bottom: 24px;
    overflow: hidden;
}

.card-header {
    background: #e7f1ff;
    border-bottom: 3px solid #007bff;
    padding: 15px 18px;
}

.card-title {
    font-weight: bold;
    color: #0056b3;
    margin: 0;
}

.card-body {
    padding: 20px;
}

/* SUMMARY CARDS */
.summary-card {
    background: white;
    border-left: 5px solid #007bff;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 22px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
}

.summary-card h3 {
    font-size: 30px;
    color: #007bff;
    font-weight: bold;
    margin: 0 0 5px;
}

.summary-card p {
    margin: 0;
    color: #6c757d;
}

.summary-card.pending-card {
    border-left-color: #ffc107;
}

.summary-card.confirmed-card {
    border-left-color: #28a745;
}

.summary-card.completed-card {
    border-left-color: #17a2b8;
}

/* FORMS */
.form-control {
    border-radius: 5px;
}

.form-control:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 .2rem rgba(0, 123, 255, .15);
}

label {
    font-weight: 600;
}

/* BUTTONS */
.btn-primary {
    background: #007bff;
    border-color: #007bff;
}

.btn-primary:hover {
    background: #0056b3;
    border-color: #0056b3;
}

/* APPOINTMENTS TABLE */
.table thead th {
    background: #007bff;
    color: white;
    white-space: nowrap;
}

.table-striped tbody tr:nth-of-type(odd) {
    background: #f0f6ff;
}

.table tbody tr:hover {
    background: #e2edff;
}

.table td,
.table th {
    vertical-align: middle;
}

/* ACTION BUTTONS */
.action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.action-buttons form {
    margin: 0;
}

/* STATUS BADGES */
.status-badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-confirmed {
    background: #d4edda;
    color: #155724;
}

.status-completed {
    background: #d1ecf1;
    color: #0c5460;
}

.status-cancelled {
    background: #f8d7da;
    color: #721c24;
}

/* FOOTER */
.main-footer {
    background: white;
    border-top: 2px solid #007bff;
    padding: 16px;
    color: #6c757d;
    text-align: center;
}

/* ALERTS */
.alert {
    border-radius: 6px;
}

/* RESPONSIVE NAVIGATION */
@media (max-width: 900px) {
    .main-header {
        justify-content: center;
    }

    .navbar-brand {
        width: 100%;
        text-align: center;
        margin-bottom: 8px;
    }

    .main-header .navbar-nav {
        justify-content: center;
    }

    .main-header .nav-link {
        padding: 8px !important;
        font-size: 13px;
    }

    .content-header {
        padding: 20px 12px 10px;
    }

    .card-body {
        padding: 15px;
    }
}
    </style>
</head>

<body>
<div class="wrapper">

    <!-- HORIZONTAL BLUE NAVIGATION -->
    <nav class="main-header navbar navbar-expand navbar-primary">
    <a href="dashboard.php" class="navbar-brand">CLARE DESIGNS</a>


<ul class="navbar-nav">
    <li class="nav-item">
        <a href="dashboard.php" class="nav-link">Dashboard</a>
    </li>

    <li class="nav-item">
        <a href="services.php" class="nav-link">Services</a>
    </li>

    <li class="nav-item">
        <a href="customers.php" class="nav-link">Customers</a>
    </li>

    <li class="nav-item">
        <a href="staff.php" class="nav-link">Staff</a>
    </li>

    <li class="nav-item">
        <a href="appointments.php" class="nav-link">Appointments</a>
    </li>

    <li class="nav-item">
        <a href="orders.php" class="nav-link">Orders</a>
    </li>

    <li class="nav-item">
        <a href="payments.php" class="nav-link">Payments</a>
    </li>

    <li class="nav-item">
        <a href="designs.php" class="nav-link">Designs</a>
    </li>

    <li class="nav-item">
        <a href="../logout.php" class="nav-link">Logout</a>
    </li>
</ul>
</nav>

    <div class="content-wrapper">

        <section class="content-header">
            <div class="container-fluid">
                <h1>Customers Management</h1>
                <p>Register, search and manage Clare Designs customers.</p>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">

                <?php if ($message !== ""): ?>
                    <div class="alert alert-<?= escape($message_type) ?> alert-dismissible">
                        <?= escape($message) ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-4">
                        <div class="summary-card">
                            <h3><?= (int)$total_customers ?></h3>
                            <p>Registered Customers</p>
                        </div>
                    </div>
                </div>

                <div class="row">

                    <!-- CUSTOMER FORM -->
                    <div class="col-lg-4">
                        <div class="card">

                            <div class="card-header">
                                <h3 class="card-title">
                                    <?= $edit_customer ? "Edit Customer" : "Add New Customer" ?>
                                </h3>
                            </div>

                            <form method="POST" action="customers.php">

                                <input type="hidden" name="csrf_token"
                                       value="<?= escape($_SESSION["csrf_token"]) ?>">

                                <input type="hidden" name="action"
                                       value="<?= $edit_customer ? "update" : "add" ?>">

                                <?php if ($edit_customer): ?>
                                    <input type="hidden" name="customer_id"
                                           value="<?= (int)$edit_customer["customer_id"] ?>">
                                <?php endif; ?>

                                <div class="card-body">

                                    <div class="form-group">
                                        <label>Full Name *</label>
                                        <input type="text" name="full_name"
                                               class="form-control"
                                               placeholder="Enter full name"
                                               value="<?= escape($edit_customer["full_name"] ?? "") ?>"
                                               required>
                                    </div>

                                    <div class="form-group">
                                        <label>Business Name</label>
                                        <input type="text" name="business_name"
                                               class="form-control"
                                               placeholder="Enter business name"
                                               value="<?= escape($edit_customer["business_name"] ?? "") ?>">
                                    </div>

                                    <div class="form-group">
                                        <label>Email Address</label>
                                        <input type="email" name="email"
                                               class="form-control"
                                               placeholder="Enter email"
                                               value="<?= escape($edit_customer["email"] ?? "") ?>">
                                    </div>

                                    <div class="form-group">
                                        <label>Phone Number</label>
                                        <input type="text" name="phone"
                                               class="form-control"
                                               placeholder="Enter phone number"
                                               value="<?= escape($edit_customer["phone"] ?? "") ?>">
                                    </div>

                                </div>

                                <div class="card-body pt-0">
                                    <button type="submit" class="btn btn-primary">
                                        <?= $edit_customer ? "Update Customer" : "Save Customer" ?>
                                    </button>

                                    <?php if ($edit_customer): ?>
                                        <a href="customers.php" class="btn btn-secondary">Cancel</a>
                                    <?php endif; ?>
                                </div>

                            </form>
                        </div>
                    </div>

                    <!-- CUSTOMER TABLE -->
                    <div class="col-lg-8">
                        <div class="card">

                            <div class="card-header">
                                <h3 class="card-title">Registered Customers</h3>
                            </div>

                            <div class="card-body">

                                <form method="GET" action="customers.php" class="mb-3">
                                    <div class="input-group">
                                        <input type="text" name="search"
                                               class="form-control"
                                               placeholder="Search customers..."
                                               value="<?= escape($search) ?>">

                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-primary">
                                                Search
                                            </button>
                                            <a href="customers.php" class="btn btn-secondary">
                                                Reset
                                            </a>
                                        </div>
                                    </div>
                                </form>

                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">

                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Customer</th>
                                                <th>Business</th>
                                                <th>Email</th>
                                                <th>Phone</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                        <?php if ($result && $result->num_rows > 0): ?>

                                            <?php while ($customer = $result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?= (int)$customer["customer_id"] ?></td>
                                                    <td><?= escape($customer["full_name"]) ?></td>
                                                    <td><?= escape($customer["business_name"] ?? "") ?></td>
                                                    <td><?= escape($customer["email"] ?? "") ?></td>
                                                    <td><?= escape($customer["phone"] ?? "") ?></td>

                                                    <td>
                                                        <div class="action-buttons">

                                                            <a href="customers.php?edit=<?= (int)$customer["customer_id"] ?>"
                                                               class="btn btn-sm btn-warning">
                                                                Edit
                                                            </a>

                                                            <form method="POST" action="customers.php"
                                                                  onsubmit="return confirm('Delete this customer?');">

                                                                <input type="hidden" name="csrf_token"
                                                                       value="<?= escape($_SESSION["csrf_token"]) ?>">

                                                                <input type="hidden" name="action"
                                                                       value="delete">

                                                                <input type="hidden" name="customer_id"
                                                                       value="<?= (int)$customer["customer_id"] ?>">

                                                                <button type="submit"
                                                                        class="btn btn-sm btn-danger">
                                                                    Delete
                                                                </button>
                                                            </form>

                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>

                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center">
                                                    No customers found.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                        </tbody>

                                    </table>
                                </div>

                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>
    </div>

    <footer class="main-footer">
        <strong>Clare Designs Management System</strong>
        <span class="float-right">Turning an idea to reality.</span>
    </footer>

</div>
</body>
</html>
