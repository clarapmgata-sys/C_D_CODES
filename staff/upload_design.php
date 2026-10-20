<?php
session_start();
require_once '../db.php';

// Check whether the user is logged in as staff
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    strtolower($_SESSION['role']) !== 'staff' ||
    !isset($_SESSION['staff_id'])
) {
    header("Location: ../login.php");
    exit();
}

$staff_id = (int) $_SESSION['staff_id'];
$message = "";
$message_type = "info";

// Upload settings
$upload_dir = '../uploads/designs/';
$allowed_extensions = [
    'jpg', 'jpeg', 'png', 'webp',
    'pdf', 'svg', 'zip'
];
$max_file_size = 10 * 1024 * 1024; // 10 MB

// Create the upload folder if it does not exist
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        die("Unable to create the designs upload folder.");
    }
}

/**
 * Upload a file and return its relative path.
 */
function uploadDesignFile(
    $field_name,
    $upload_dir,
    $allowed_extensions,
    $max_file_size
) {
    if (
        !isset($_FILES[$field_name]) ||
        $_FILES[$field_name]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return [
            'success' => true,
            'path' => null,
            'error' => ''
        ];
    }

    $file = $_FILES[$field_name];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [
            'success' => false,
            'path' => null,
            'error' => 'The file could not be uploaded.'
        ];
    }

    if ($file['size'] > $max_file_size) {
        return [
            'success' => false,
            'path' => null,
            'error' => 'Each file must be 10 MB or smaller.'
        ];
    }

    $extension = strtolower(
        pathinfo($file['name'], PATHINFO_EXTENSION)
    );

    if (!in_array($extension, $allowed_extensions, true)) {
        return [
            'success' => false,
            'path' => null,
            'error' => 'File type not allowed.'
        ];
    }

    // Generate a unique filename instead of trusting the original name
    $new_filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = $upload_dir . $new_filename;

    if (!is_uploaded_file($file['tmp_name'])) {
        return [
            'success' => false,
            'path' => null,
            'error' => 'Invalid uploaded file.'
        ];
    }

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return [
            'success' => false,
            'path' => null,
            'error' => 'Unable to save the uploaded file.'
        ];
    }

    return [
        'success' => true,
        'path' => 'uploads/designs/' . $new_filename,
        'error' => ''
    ];
}

// Load orders assigned to the logged-in staff member
$orders_sql = "
    SELECT
        o.order_id,
        o.order_date,
        o.deadline,
        o.requirements,
        o.order_status,
        o.preview_file,
        o.final_design_file,
        c.full_name AS customer_name,
        s.service_name
    FROM orders o
    INNER JOIN customers c
        ON o.customer_id = c.customer_id
    INNER JOIN services s
        ON o.service_id = s.service_id
    WHERE o.staff_id = ?
    ORDER BY o.order_id DESC
";

$orders_stmt = $conn->prepare($orders_sql);

if (!$orders_stmt) {
    die("Unable to load orders. Please check your database structure.");
}

$orders_stmt->bind_param("i", $staff_id);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();

// Process the upload form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $order_id = filter_input(
        INPUT_POST,
        'order_id',
        FILTER_VALIDATE_INT
    );

    if (!$order_id || $order_id < 1) {
        $message = "Please select a valid order.";
        $message_type = "danger";

    } else {

        // Verify that this order belongs to the logged-in staff member
        $check_stmt = $conn->prepare("
            SELECT preview_file, final_design_file
            FROM orders
            WHERE order_id = ? AND staff_id = ?
        ");

        if (!$check_stmt) {
            $message = "Unable to verify the selected order.";
            $message_type = "danger";

        } else {
            $check_stmt->bind_param("ii", $order_id, $staff_id);
            $check_stmt->execute();
            $existing_order = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();

            if (!$existing_order) {
                $message = "This order is not assigned to your account.";
                $message_type = "danger";

            } else {

                $old_preview = $existing_order['preview_file'];
                $old_final = $existing_order['final_design_file'];

                // Upload the preview file
                $preview = uploadDesignFile(
                    'preview_file',
                    $upload_dir,
                    $allowed_extensions,
                    $max_file_size
                );

                // Upload the final file
                $final = uploadDesignFile(
                    'final_design_file',
                    $upload_dir,
                    $allowed_extensions,
                    $max_file_size
                );

                if (!$preview['success'] || !$final['success']) {

                    $message = !$preview['success']
                        ? $preview['error']
                        : $final['error'];

                    $message_type = "danger";

                    // Remove any newly uploaded file if the other upload failed
                    if ($preview['path'] !== null) {
                        $preview_path = '../' . $preview['path'];

                        if (is_file($preview_path)) {
                            unlink($preview_path);
                        }
                    }

                    if ($final['path'] !== null) {
                        $final_path = '../' . $final['path'];

                        if (is_file($final_path)) {
                            unlink($final_path);
                        }
                    }

                } elseif (
                    $preview['path'] === null &&
                    $final['path'] === null
                ) {
                    $message = "Please select a preview file or a final design file.";
                    $message_type = "warning";

                } else {

                    // Keep existing files when no replacement is uploaded
                    $new_preview = $preview['path'] ?? $old_preview;
                    $new_final = $final['path'] ?? $old_final;

                    $update_stmt = $conn->prepare("
                        UPDATE orders
                        SET preview_file = ?,
                            final_design_file = ?
                        WHERE order_id = ? AND staff_id = ?
                    ");

                    if (!$update_stmt) {
                        $message = "Unable to prepare the database update.";
                        $message_type = "danger";

                    } else {

                        $update_stmt->bind_param(
                            "ssii",
                            $new_preview,
                            $new_final,
                            $order_id,
                            $staff_id
                        );

                        if ($update_stmt->execute()) {

                            // Delete replaced files only after the database update succeeds
                            if (
                                $preview['path'] !== null &&
                                !empty($old_preview) &&
                                $old_preview !== $new_preview
                            ) {
                                $old_path = '../' . $old_preview;

                                if (is_file($old_path)) {
                                    unlink($old_path);
                                }
                            }

                            if (
                                $final['path'] !== null &&
                                !empty($old_final) &&
                                $old_final !== $new_final
                            ) {
                                $old_path = '../' . $old_final;

                                if (is_file($old_path)) {
                                    unlink($old_path);
                                }
                            }

                            $message = "Design files uploaded successfully!";
                            $message_type = "success";

                        } else {

                            // Remove new files if the database update fails
                            if ($preview['path'] !== null) {
                                $new_path = '../' . $preview['path'];

                                if (is_file($new_path)) {
                                    unlink($new_path);
                                }
                            }

                            if ($final['path'] !== null) {
                                $new_path = '../' . $final['path'];

                                if (is_file($new_path)) {
                                    unlink($new_path);
                                }
                            }

                            $message = "Failed to save the design paths in the database.";
                            $message_type = "danger";
                        }

                        $update_stmt->close();
                    }
                }
            }
        }
    }

    // Refresh the list of orders after an upload
    $orders_stmt->execute();
    $orders_result = $orders_stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Upload Designs | Clare Designs</title>

    <link rel="stylesheet"
          href="../assets/css/adminlte.min.css">

    <style>
        body {
            background: #f4f6f9;
            font-family: Arial, sans-serif;
            color: #343a40;
            margin: 0;
        }

        .top-nav {
            background: #007bff;
            border-bottom: 3px solid #0056b3;
            padding: 14px 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
        }

        .top-nav a {
            color: white;
            text-decoration: none;
            font-weight: bold;
        }

        .top-nav a:hover,
        .top-nav a.active {
            text-decoration: underline;
        }

        .page-container {
            max-width: 1150px;
            margin: 30px auto;
            padding: 0 15px;
        }

        .page-title {
            margin-bottom: 20px;
            color: #343a40;
        }

        .card {
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }

        .card-header {
            background: #e7f1ff;
            border-bottom: 2px solid #007bff;
            padding: 15px;
            font-weight: bold;
        }

        .card-body {
            padding: 20px;
        }

        label {
            font-weight: bold;
        }

        .table th {
            background: #007bff;
            color: white;
        }

        .table td,
        .table th {
            vertical-align: middle;
        }

        .file-note {
            color: #6c757d;
            font-size: 13px;
        }

        .btn-primary {
            background: #007bff;
        }

        @media (max-width: 600px) {
            .top-nav {
                gap: 12px;
            }

            .card-body {
                padding: 12px;
            }
        }
    </style>
</head>

<body>

<nav class="top-nav">
    <a href="dashboard.php">Dashboard</a>
    <a href="my_orders.php">My Orders</a>
    <a href="upload_design.php" class="active">Upload Designs</a>
    <a href="../logout.php">Logout</a>
</nav>

<div class="page-container">

    <h2 class="page-title">Upload Design Files</h2>

    <?php if ($message !== ""): ?>
        <div class="alert alert-<?= htmlspecialchars($message_type) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            Upload a Preview or Final Design
        </div>

        <div class="card-body">

            <form method="POST"
                  action="upload_design.php"
                  enctype="multipart/form-data">

                <div class="form-group">
                    <label for="order_id">Select Assigned Order</label>

                    <select name="order_id"
                            id="order_id"
                            class="form-control"
                            required>

                        <option value="">-- Select an order --</option>

                        <?php while ($order = $orders_result->fetch_assoc()): ?>
                            <option value="<?= (int) $order['order_id'] ?>"
                                <?= (
                                    isset($_POST['order_id']) &&
                                    (int) $_POST['order_id'] === (int) $order['order_id']
                                ) ? 'selected' : '' ?>>

                                Order #<?= (int) $order['order_id'] ?>
                                -
                                <?= htmlspecialchars($order['customer_name']) ?>
                                -
                                <?= htmlspecialchars($order['service_name']) ?>

                            </option>
                        <?php endwhile; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label for="preview_file">Preview Design</label>

                    <input type="file"
                           name="preview_file"
                           id="preview_file"
                           class="form-control-file"
                           accept=".jpg,.jpeg,.png,.webp,.pdf,.svg,.zip">

                    <small class="file-note">
                        Upload a preview for the customer to review.
                        Maximum size: 10 MB.
                    </small>
                </div>

                <div class="form-group">
                    <label for="final_design_file">Final Design</label>

                    <input type="file"
                           name="final_design_file"
                           id="final_design_file"
                           class="form-control-file"
                           accept=".jpg,.jpeg,.png,.webp,.pdf,.svg,.zip">

                    <small class="file-note">
                        Upload the completed design file.
                        Maximum size: 10 MB.
                    </small>
                </div>

                <button type="submit" class="btn btn-primary">
                    Upload Design
                </button>

                <a href="my_orders.php" class="btn btn-secondary">
                    Back to My Orders
                </a>

            </form>

        </div>
    </div>

    <div class="card">
        <div class="card-header">
            My Assigned Orders and Uploaded Files
        </div>

        <div class="card-body table-responsive">

            <table class="table table-bordered table-striped">

                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Deadline</th>
                        <th>Preview</th>
                        <th>Final Design</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($orders_result->num_rows > 0): ?>

                    <?php while ($order = $orders_result->fetch_assoc()): ?>

                        <tr>
                            <td>
                                #<?= (int) $order['order_id'] ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($order['customer_name']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($order['service_name']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($order['deadline'] ?? 'Not set') ?>
                            </td>

                            <td>
                                <?php if (!empty($order['preview_file'])): ?>
                                    <span class="badge badge-success">
                                        Uploaded
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-warning">
                                        Not uploaded
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!empty($order['final_design_file'])): ?>
                                    <span class="badge badge-success">
                                        Uploaded
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-warning">
                                        Not uploaded
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($order['order_status'] ?? 'Pending') ?>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7" class="text-center">
                            No orders have been assigned to you yet.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>
    </div>

</div>

</body>
</html>

<?php
$orders_stmt->close();
?>