<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id <= 0) {
    header('Location: orders.php');
    exit;
}

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function fetchOneOrRedirect($db, $sql, $types, $params, $redirectTo)
{
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        header("Location: {$redirectTo}");
        exit;
    }

    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        header("Location: {$redirectTo}");
        exit;
    }

    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) {
        $stmt->close();
        header("Location: {$redirectTo}");
        exit;
    }

    $row = $result->fetch_assoc();
    $stmt->close();
    return $row;
}

$order = fetchOneOrRedirect(
    $db,
    "SELECT
        o.order_id, o.order_ref_no, o.customer_id, o.status, o.ordered_datetime, o.picked_up_datetime,
        o.payment_status, o.order_total, o.price_type, o.note_customer, o.note_store, o.rating,
        o.created_at, o.updated_at,
        c.first_name, c.last_name, c.phone
     FROM orders o
     INNER JOIN customers c ON o.customer_id = c.customer_id
     WHERE o.order_id = ?",
    'i',
    [$order_id],
    'orders.php'
);

$itemStmt = $db->prepare(
    "SELECT
        oi.order_item_id, oi.product_id, oi.unit_id, oi.qty, oi.price_unit, oi.price_total, oi.price_type, oi.item_note,
        p.product_name, p.product_image,
        u.unit_code
     FROM order_items oi
     INNER JOIN products p ON oi.product_id = p.product_id
     INNER JOIN product_units u ON oi.unit_id = u.unit_id
     WHERE oi.order_id = ? AND oi.is_active = 1
     ORDER BY oi.order_item_id ASC"
);

$order_items = [];
if ($itemStmt) {
    $itemStmt->bind_param('i', $order_id);
    if ($itemStmt->execute()) {
        $itemResult = $itemStmt->get_result();
        while ($row = $itemResult->fetch_assoc()) {
            $order_items[] = $row;
        }
    }
    $itemStmt->close();
}

$allowedStatusOptions = ['Pending', 'Confirmed', 'Preparing', 'Ready', 'Picked Up', 'Cancelled', 'Refunded'];
$allowedPaymentStatusOptions = ['Pending', 'Paid', 'Failed', 'Refunded'];
$statusMessages = [
    'Pending' => 'Awaiting confirmation',
    'Confirmed' => 'Confirmed and queued for preparation',
    'Preparing' => 'Order is being prepared',
    'Ready' => 'Order is ready',
    'Picked Up' => 'Order has been picked up',
    'Cancelled' => 'Order has been cancelled',
    'Refunded' => 'Order has been refunded'
];

$page_name = !empty($order['order_ref_no']) ? $order['order_ref_no'] : 'Order #' . $order_id;
?>
<!DOCTYPE html>
<html lang="en">
<?php require_once 'layout/head.php'; ?>

<body id="page-top">
    <div id="wrapper">
        <?php require_once 'layout/sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php require_once 'layout/topbar.php'; ?>

                <div class="container-fluid">
                    <nav aria-label="breadcrumb" class="mb-4">
                        <ol class="breadcrumb bg-primary">
                            <li class="breadcrumb-item"><a href="dashboard.php" class="text-light">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="orders.php" class="text-light">Product Orders</a></li>
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page"><?= e($page_name) ?></li>
                        </ol>
                    </nav>

                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold">
                                <i class="fas fa-shopping-cart mr-2"></i><?= e($page_name) ?>
                            </h6>
                            <div>
                                <a href="orders.php" class="btn btn-light btn-sm">
                                    <i class="fas fa-arrow-left mr-1"></i>Back To Orders
                                </a>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-6 mb-4">
                                    <div class="card shadow-sm h-100">
                                        <div class="card-header bg-dark text-white py-2">
                                            <h6 class="m-0 font-weight-bold"><i class="fas fa-user mr-2"></i>Customer Information</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-sm-6 mb-3">
                                                    <small class="text-muted">Name</small>
                                                    <div class="font-weight-bold"><?= e(trim($order['first_name'] . ' ' . $order['last_name'])) ?></div>
                                                </div>
                                                <div class="col-sm-6 mb-3">
                                                    <small class="text-muted">Phone</small>
                                                    <div class="font-weight-bold"><?= e($order['phone']) ?></div>
                                                </div>
                                                <div class="col-sm-6 mb-3">
                                                    <small class="text-muted">Price Type</small>
                                                    <div>
                                                        <span class="badge badge-<?= $order['price_type'] === 'Wholesale' ? 'info' : 'primary' ?>">
                                                            <?= e($order['price_type']) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-sm-6 mb-3">
                                                    <small class="text-muted">Rating</small>
                                                    <div>
                                                        <?php if (!empty($order['rating'])): ?>
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="<?= $i <= (int)$order['rating'] ? 'fas' : 'far' ?> fa-star text-warning"></i>
                                                            <?php endfor; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Not rated</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-6 mb-4">
                                    <div class="card shadow-sm h-100">
                                        <div class="card-header bg-dark text-white py-2">
                                            <h6 class="m-0 font-weight-bold"><i class="fas fa-file-invoice-dollar mr-2"></i>Product Order Details</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-sm-6 mb-3">
                                                    <small class="text-muted">Product Order Reference</small>
                                                    <div class="font-weight-bold"><?= e($order['order_ref_no']) ?></div>
                                                </div>
                                                <div class="col-sm-6 mb-3">
                                                    <small class="text-muted">Ordered At</small>
                                                    <div class="font-weight-bold"><?= e($order['ordered_datetime']) ?></div>
                                                </div>
                                                <div class="col-sm-6 mb-3">
                                                    <small class="text-muted">Picked Up At</small>
                                                    <div class="font-weight-bold"><?= e($order['picked_up_datetime'] ?: 'N/A') ?></div>
                                                </div>
                                                <div class="col-sm-6 mb-3">
                                                    <small class="text-muted">Payment Status</small>
                                                    <select id="paymentStatusSelect" class="form-control form-control-sm">
                                                        <?php foreach ($allowedPaymentStatusOptions as $paymentStatusOption): ?>
                                                            <option value="<?= e($paymentStatusOption) ?>" <?= $order['payment_status'] === $paymentStatusOption ? 'selected' : '' ?>>
                                                                <?= e($paymentStatusOption) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-sm-6 mb-3">
                                                    <small class="text-muted">Product Order Total</small>
                                                    <div class="h5 font-weight-bold text-primary mb-0">Rs. <?= number_format((float)$order['order_total'], 2) ?></div>
                                                </div>
                                                <div class="col-sm-6 mb-3">
                                                    <small class="text-muted">Status</small>
                                                    <select id="orderStatusSelect" class="form-control form-control-sm">
                                                        <?php foreach ($allowedStatusOptions as $statusOption): ?>
                                                            <option value="<?= e($statusOption) ?>" <?= $order['status'] === $statusOption ? 'selected' : '' ?>>
                                                                <?= e($statusOption) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <small class="text-muted d-block mt-1"><?= e($statusMessages[$order['status']] ?? 'Unknown status') ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <div class="card shadow-sm h-100">
                                        <div class="card-header bg-dark text-white py-2">
                                            <h6 class="m-0 font-weight-bold"><i class="fas fa-sticky-note mr-2"></i>Customer Notes</h6>
                                        </div>
                                        <div class="card-body">
                                            <p class="mb-0"><?= e($order['note_customer'] ?: 'No customer notes') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="card shadow-sm h-100">
                                        <div class="card-header bg-dark text-white py-2">
                                            <h6 class="m-0 font-weight-bold"><i class="fas fa-edit mr-2"></i>Admin Notes</h6>
                                        </div>
                                        <div class="card-body">
                                            <textarea id="adminNotesInput" class="form-control mb-2" rows="4" maxlength="500" placeholder="Add admin notes..."><?= e($order['note_store']) ?></textarea>
                                            <small class="text-muted"><span id="charCount">0</span>/500 characters</small>
                                            <button id="saveAdminNotesBtn" class="btn btn-primary btn-sm mt-2">
                                                <i class="fas fa-save mr-1"></i>Save Notes
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card shadow-sm">
                                <div class="card-header bg-dark text-white py-2">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-list mr-2"></i>Product Order Items (<?= count($order_items) ?>)
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($order_items)): ?>
                                        <div class="table-responsive">
                                            <table id="orderItemsTable" class="table table-bordered table-striped" style="width:100%">
                                                <thead class="bg-dark text-white">
                                                    <tr>
                                                        <th>Product</th>
                                                        <th>Unit</th>
                                                        <th>Price Type</th>
                                                        <th>Unit Price</th>
                                                        <th>Qty</th>
                                                        <th>Total</th>
                                                        <th>Notes</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($order_items as $item): ?>
                                                        <tr>
                                                            <td>
                                                                <?php if (!empty($item['product_image'])): ?>
                                                                    <img src="../assets/product/<?= e($item['product_image']) ?>"
                                                                        alt="<?= e($item['product_name']) ?>"
                                                                        class="rounded mr-2"
                                                                        style="width: 35px; height: 35px; object-fit: cover; vertical-align: middle;">
                                                                <?php endif; ?>
                                                                <?= e($item['product_name']) ?>
                                                            </td>
                                                            <td><?= e($item['unit_code']) ?></td>
                                                            <td><?= e($item['price_type']) ?></td>
                                                            <td>Rs. <?= number_format((float)$item['price_unit'], 2) ?></td>
                                                            <td><?= (int)$item['qty'] ?></td>
                                                            <td><strong>Rs. <?= number_format((float)$item['price_total'], 2) ?></strong></td>
                                                            <td><?= e($item['item_note'] ?: 'N/A') ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted text-center mb-0"><i class="fas fa-box-open mr-2"></i>No items found for this order.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php require_once 'layout/footer.php'; ?>
        </div>
    </div>

    <?php require_once 'layout/scripts.php'; ?>
    <script>
        const orderId = <?= (int)$order_id ?>;
        const currentStatus = '<?= addslashes($order['status']) ?>';
        const currentPaymentStatus = '<?= addslashes($order['payment_status']) ?>';

        function toast(type, title, text) {
            Swal.fire({
                icon: type,
                title: title,
                text: text,
                timer: type === 'success' ? 1500 : undefined,
                showConfirmButton: type !== 'success'
            });
        }

        $(document).ready(function() {
            const $notes = $('#adminNotesInput');
            const $count = $('#charCount');

            function updateCharCount() {
                const len = $notes.val().length;
                $count.text(len).toggleClass('text-danger', len > 500);
            }

            $notes.on('input', updateCharCount);
            updateCharCount();

            $('#orderItemsTable').DataTable({
                responsive: true,
                paging: false,
                searching: false,
                ordering: true,
                lengthChange: false
            });

            const confirmStatusUpdate = (statusType, newStatus, currentStatus, $select) => {
                const title = statusType === 'status'
                    ? 'Confirm Status Change'
                    : 'Confirm Payment Status Change';
                const text = statusType === 'status'
                    ? `Change order status to "${newStatus}"?`
                    : `Change payment status to "${newStatus}"?`;
                const successMsg = statusType === 'status'
                    ? 'Order status updated successfully'
                    : 'Payment status updated successfully';
                const data = { order_id: orderId };
                data[statusType] = newStatus;

                Swal.fire({
                    title,
                    text,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, update',
                    confirmButtonColor: '#0d6efd',
                    cancelButtonColor: '#6c757d',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showLoaderOnConfirm: true,
                    preConfirm: () => {
                        return $.ajax({
                            url: 'api/orders/update_order_status.php',
                            method: 'POST',
                            dataType: 'json',
                            data
                        }).then(res => {
                            if (!res.success) {
                                throw new Error(res.message || 'Update failed');
                            }
                            return res;
                        }).catch(err => {
                            Swal.showValidationMessage(err.message);
                        });
                    }
                }).then(result => {
                    if (result.isConfirmed) {
                        toast('success', 'Success', successMsg);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        $select.val(currentStatus);
                    }
                });
            };

            $('#orderStatusSelect').on('change', function() {
                confirmStatusUpdate('status', this.value, currentStatus, $('#orderStatusSelect'));
            });

            $('#paymentStatusSelect').on('change', function() {
                confirmStatusUpdate('payment_status', this.value, currentPaymentStatus, $('#paymentStatusSelect'));
            });

            $('#saveAdminNotesBtn').on('click', function() {
                const notes = $notes.val();

                if (notes.length > 500) {
                    toast('error', 'Error', 'Admin notes cannot exceed 500 characters');
                    return;
                }

                $.ajax({
                    url: 'api/orders/update_order_notes.php',
                    method: 'POST',
                    dataType: 'json',
                    data: { order_id: orderId, admin_notes: notes },
                    success(res) {
                        if (res.success) {
                            toast('success', 'Saved', 'Admin notes updated');
                        } else {
                            toast('error', 'Error', res.message || 'Failed');
                        }
                    },
                    error() {
                        toast('error', 'Error', 'Request failed');
                    }
                });
            });
        });
    </script>
</body>

</html>
