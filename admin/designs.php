<?php
session_start();
require_once '../db.php';

// Check administrator login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. Only administrators can manage designs.");
}

$message = '';
$messageType = 'success';

// Create the upload directory if it does not exist
$uploadDirectory = __DIR__ . '/../uploads/designs/';

if (!is_dir($uploadDirectory)) {
    if (!mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        die("Unable to create the designs upload directory.");
    }
}

// Upload a design file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_design'])) {

    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $design_type = $_POST['design_type'] ?? '';

    if (!$order_id || !in_array($design_type, ['preview', 'final'], true)) {
        $message = "Please select a valid order and design type.";
        $messageType = 'danger';

    } elseif (
        !isset($_FILES['design_file']) ||
        $_FILES['design_file']['error'] !== UPLOAD_ERR_OK
    ) {
        $message = "Please select a file to upload.";
        $messageType = 'danger';

    } else {
        $file = $_FILES['design_file'];

        // Maximum file size: 10 MB
        $maxSize = 10 * 1024 * 1024;

        $originalName = $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // Allowed design file types
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];

        if ($file['size'] > $maxSize) {
            $message = "The file must not exceed 10 MB.";
            $messageType = 'danger';

        } elseif (!in_array($extension, $allowedExtensions, true)) {
            $message = "Only JPG, JPEG, PNG, WEBP and PDF files are allowed.";
            $messageType = 'danger';

        } else {
            // Check the actual file type
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);

            $allowedMimeTypes = [
                'jpg'  => ['image/jpeg'],
                'jpeg' => ['image/jpeg'],
                'png'  => ['image/png'],
                'webp' => ['image/webp'],
                'pdf'  => ['application/pdf']
            ];

            if (
                !isset($allowedMimeTypes[$extension]) ||
                !in_array($mimeType, $allowedMimeTypes[$extension], true)
            ) {
                $message = "The uploaded file type could not be verified.";
                $messageType = 'danger';

            } else {
                // Confirm that the order exists
                $stmt = $conn->prepare(
                    "SELECT order_id FROM orders WHERE order_id = ?"
                );
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $orderResult = $stmt->get_result();
                $orderExists = $orderResult->num_rows > 0;
                $stmt->close();

                if (!$orderExists) {
                    $message = "The selected order does not exist.";
                    $messageType = 'danger';

                } else {
                    // Use a generated filename rather than the original name
                    $newFilename = bin2hex(random_bytes(16)) . '.' . $extension;
                    $destination = $uploadDirectory . $newFilename;

                    if (move_uploaded_file($file['tmp_name'], $destination)) {

                        // Store a relative path in the database
                        $relativePath = 'uploads/designs/' . $newFilename;

                        if ($design_type === 'preview') {
                            $stmt = $conn->prepare(
                                "UPDATE orders SET preview_file = ? WHERE order_id = ?"
                            );
                        } else {
                            $stmt = $conn->prepare(
                                "UPDATE orders SET final_design_file = ? WHERE order_id = ?"
                            );
                        }

                        $stmt->bind_param("si", $relativePath, $order_id);

                        if ($stmt->execute()) {
                            $stmt->close();

                            header("Location: designs.php?success=uploaded");
                            exit;
                        } else {
                            $stmt->close();

                            // Remove the uploaded file if the database update fails
                            if (is_file($destination)) {
                                unlink($destination);
                            }

                            $message = "The file was uploaded, but the database could not be updated.";
                            $messageType = 'danger';
                        }

                    } else {
                        $message = "Unable to save the uploaded file.";
                        $messageType = 'danger';
                    }
                }
            }
        }
    }
}

// Show upload success message
if (isset($_GET['success']) && $_GET['success'] === 'uploaded') {
    $message = "Design file uploaded successfully.";
    $messageType = 'success';
}

// Get all orders and their design files
$sql = "
    SELECT
        o.order_id,
        o.order_date,
        o.deadline,
        o.order_status,
        o.preview_file,
        o.final_design_file,
        c.full_name AS customer_name,
        s.service_name
    FROM orders o
    INNER JOIN customers c ON o.customer_id = c.customer_id
    INNER JOIN services s ON o.service_id = s.service_id
    ORDER BY o.order_id DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("Unable to load designs. Please check your database table structure.");
}

$orders = [];
$totalOrders = 0;
$previewCount = 0;
$finalCount = 0;

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
    $totalOrders++;

    if (!empty($row['preview_file'])) {
        $previewCount++;
    }

    if (!empty($row['final_design_file'])) {
        $finalCount++;
    }
}

function escapeDesign($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Only link to files stored in the expected design upload folder
function designFileUrl($path) {
    $filename = basename(str_replace('\\', '/', (string)$path));

    if ($filename === '' || $filename === '.' || $filename === '..') {
        return '';
    }

    return '../uploads/designs/' . rawurlencode($filename);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Designs Management | Clare Designs</title>

    <link rel="stylesheet" href="../assets/css/adminlte.min.css">

    <style>
        body {
            background: #f4f6f9;
            font-family: Arial, sans-serif;
            color: #343a40;
            margin: 0;
        }

        /* SAME BLUE HORIZONTAL NAVIGATION AS SERVICES */
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

        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
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

        .summary-card {
            background: white;
            border-left: 5px solid #007bff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 22px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }

        .summary-card h3 {
            font-size: 30px;
            color: #007bff;
            font-weight: bold;
            margin: 0 0 5px;
        }

        .preview-card {
            border-left-color: #17a2b8;
        }

        .final-card {
            border-left-color: #28a745;
        }

        .form-control {
            border-radius: 5px;
        }

        .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 .2rem rgba(0,123,255,.15);
        }

        .btn-primary {
            background: #007bff;
            border-color: #007bff;
        }

        .btn-primary:hover {
            background: #0056b3;
            border-color: #0056b3;
        }

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

        .file-link {
            display: inline-block;
            margin: 3px;
        }

        .main-footer {
            background: white;
            border-top: 2px solid #007bff;
            padding: 16px;
            color: #6c757d;
        }

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
        }
    </style>
</head>

<body class="hold-transition layout-top-nav">

<!-- TOP NAVIGATION -->
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
            <a href="designs.php" class="nav-link active">Designs</a>
        </li>
        <li class="nav-item">
            <a href="../logout.php" class="nav-link">Logout</a>
        </li>
    </ul>
</nav>

<div class="content-wrapper">

    <!-- PAGE TITLE -->
    <div class="content-header">
        <div class="container-fluid">
            <h1>Designs Management</h1>
            <p>Upload previews and final design files for customer orders.</p>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <!-- FEEDBACK MESSAGE -->
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?= escapeDesign($messageType) ?> alert-dismissible">
                    <?= escapeDesign($message) ?>
                    <button type="button" class="close"
                            data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- SUMMARY CARDS -->
            <div class="row">
                <div class="col-md-4">
                    <div class="summary-card">
                        <h3><?= $totalOrders ?></h3>
                        <p>Total Orders</p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="summary-card preview-card">
                        <h3><?= $previewCount ?></h3>
                        <p>Orders With Previews</p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="summary-card final-card">
                        <h3><?= $finalCount ?></h3>
                        <p>Orders With Final Designs</p>
                    </div>
                </div>
            </div>

            <!-- UPLOAD DESIGN FORM -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Upload Design File</h3>
                </div>

                <div class="card-body">
                    <form method="POST"
                          action="designs.php"
                          enctype="multipart/form-data">

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Select Order *</label>

                                    <select name="order_id"
                                            class="form-control"
                                            required>
                                        <option value="">Choose an order</option>

                                        <?php foreach ($orders as $order): ?>
                                            <option value="<?= (int)$order['order_id'] ?>">
                                                Order #<?= (int)$order['order_id'] ?>
                                                — <?= escapeDesign($order['customer_name']) ?>
                                                (<?= escapeDesign($order['service_name']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Design Type *</label>

                                    <select name="design_type"
                                            class="form-control"
                                            required>
                                        <option value="">Choose type</option>
                                        <option value="preview">Preview Design</option>
                                        <option value="final">Final Design</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Design File *</label>

                                    <input type="file"
                                           name="design_file"
                                           class="form-control"
                                           accept=".jpg,.jpeg,.png,.webp,.pdf"
                                           required>

                                    <small class="text-muted">
                                        JPG, JPEG, PNG, WEBP or PDF. Maximum 10 MB.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <button type="submit"
                                name="upload_design"
                                class="btn btn-primary">
                            Upload Design
                        </button>
                    </form>
                </div>
            </div>

            <!-- DESIGN RECORDS -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Design Files By Order</h3>
                </div>

                <div class="card-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Service</th>
                                <th>Order Date</th>
                                <th>Deadline</th>
                                <th>Order Status</th>
                                <th>Preview Design</th>
                                <th>Final Design</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if (count($orders) > 0): ?>

                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?= (int)$order['order_id'] ?></td>

                                    <td><?= escapeDesign($order['customer_name']) ?></td>

                                    <td><?= escapeDesign($order['service_name']) ?></td>

                                    <td><?= escapeDesign($order['order_date']) ?></td>

                                    <td><?= escapeDesign($order['deadline']) ?></td>

                                    <td><?= escapeDesign($order['order_status']) ?></td>

                                    <td>
                                        <?php if (!empty($order['preview_file'])): ?>
                                            <a class="btn btn-info btn-sm file-link"
                                               href="<?= escapeDesign(designFileUrl($order['preview_file'])) ?>"
                                               target="_blank"
                                               rel="noopener">
                                                View Preview
                                            </a>

                                            <a class="btn btn-primary btn-sm file-link"
                                               href="<?= escapeDesign(designFileUrl($order['preview_file'])) ?>"
                                               download>
                                                Download
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Not uploaded</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($order['final_design_file'])): ?>
                                            <a class="btn btn-success btn-sm file-link"
                                               href="<?= escapeDesign(designFileUrl($order['final_design_file'])) ?>"
                                               target="_blank"
                                               rel="noopener">
                                                View Final
                                            </a>

                                            <a class="btn btn-primary btn-sm file-link"
                                               href="<?= escapeDesign(designFileUrl($order['final_design_file'])) ?>"
                                               download>
                                                Download
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Not uploaded</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">
                                    No orders found. Orders will appear here when customers place them.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>
</div>

<footer class="main-footer text-center">
    <strong>Copyright &copy; <?= date('Y') ?> Clare Designs.</strong>
    All rights reserved.
</footer>

</body>
</html>