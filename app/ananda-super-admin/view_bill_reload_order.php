<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';

$bill_reload_order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($bill_reload_order_id <= 0) {
    header('Location: bill_reload_orders.php');
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
        bro.bill_reload_order_id,
        bro.bill_reload_ref_no,
        bro.customer_id,
        bro.service_id,
        bro.account_number,
        bro.amount,
        bro.service_fee,
        bro.total_amount,
        bro.status,
        bro.payment_status,
        bro.processed_at,
        bro.note_store,
        bro.created_at,
        bro.updated_at,

        c.first_name,
        c.last_name,
        c.phone,

        s.service_name,
        s.service_type

    FROM bill_reload_orders bro

    INNER JOIN customers c
        ON bro.customer_id = c.customer_id

    INNER JOIN services s
        ON bro.service_id = s.service_id

    WHERE bro.bill_reload_order_id = ?",
    'i',
    [$bill_reload_order_id],
    'bill_reload_orders.php'
);

$allowedStatusOptions = [
    'Pending',
    'Processing',
    'Completed',
    'Failed',
    'Refunded'
];

$allowedPaymentStatusOptions = [
    'Pending',
    'Paid',
    'Failed',
    'Refunded'
];

$statusMessages = [
    'Pending' => 'Awaiting processing',
    'Processing' => 'Order is being processed',
    'Completed' => 'Order has been completed',
    'Failed' => 'Order processing failed',
    'Refunded' => 'Order has been refunded'
];

$page_name = !empty($order['bill_reload_ref_no'])
    ? $order['bill_reload_ref_no']
    : 'Bill/Reload Order #' . $bill_reload_order_id;
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
                            <li class="breadcrumb-item"><a href="bill_reload_orders.php" class="text-light">Bill/Reload Orders</a></li>
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page"><?= e($page_name) ?></li>
                        </ol>
                    </nav>

                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold">
                                <i class="fas fa-bolt mr-2"></i><?= e($page_name) ?>
                            </h6>
                            <div>
                                <a href="bill_reload_orders.php" class="btn btn-light btn-sm">
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
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-6 mb-4">
                                    <div class="card shadow-sm h-100">
                                        <div class="card-header bg-dark text-white py-2">
                                            <h6 class="m-0 font-weight-bold"><i class="fas fa-bolt mr-2"></i>Order Details</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-sm-6 mb-3">
                                                    <small class="text-muted">Reference No</small>
                                                    <div class="font-weight-bold"><?= e($order['bill_reload_ref_no']) ?></div>
                                                </div>
                                                <div class="col-sm-6 mb-3">
                                                    <small class="text-muted">Service</small>
                                                    <div>
                                                        <span class="badge badge-<?= $order['service_type'] === 'Bill' ? 'danger' : 'primary' ?>">
                                                            <?= e($order['service_type']) ?>
                                                        </span>
                                                        <span class="ml-1"><?= e($order['service_name']) ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-sm-6 mb-3">
                                                    <small class="text-muted">Account Number</small>
                                                    <div class="font-weight-bold"><?= e($order['account_number']) ?></div>
                                                </div>
                                                <div class="col-sm-6 mb-3">
                                                    <small class="text-muted">Amount</small>
                                                    <div class="font-weight-bold">Rs. <?= number_format((float)$order['amount'], 2) ?></div>
                                                </div>
                                                <div class="col-sm-6 mb-3">
                                                    <small class="text-muted">Service Fee</small>
                                                    <div class="font-weight-bold">Rs. <?= number_format((float)$order['service_fee'], 2) ?></div>
                                                </div>
                                                <div class="col-sm-6 mb-3">
                                                    <small class="text-muted">Total Amount</small>
                                                    <div class="h5 font-weight-bold text-primary mb-0">Rs. <?= number_format((float)$order['total_amount'], 2) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-lg-6 mb-4">
                                    <div class="card shadow-sm h-100">
                                        <div class="card-header bg-dark text-white py-2">
                                            <h6 class="m-0 font-weight-bold"><i class="fas fa-clock mr-2"></i>Timestamps</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-sm-6 mb-3">
                                                    <small class="text-muted">Created At</small>
                                                    <div class="font-weight-bold"><?= e($order['created_at']) ?></div>
                                                </div>
                                                <div class="col-sm-6 mb-3">
                                                    <small class="text-muted">Updated At</small>
                                                    <div class="font-weight-bold"><?= e($order['updated_at']) ?></div>
                                                </div>
                                                <div class="col-sm-6 mb-3">
                                                    <small class="text-muted">Processed At</small>
                                                    <div class="font-weight-bold"><?= e($order['processed_at'] ?: 'N/A') ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-6 mb-4">
                                    <div class="card shadow-sm h-100">
                                        <div class="card-header bg-dark text-white py-2">
                                            <h6 class="m-0 font-weight-bold"><i class="fas fa-check-circle mr-2"></i>Status</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-sm-6 mb-3">
                                                    <small class="text-muted">Order Status</small>
                                                    <select id="orderStatusSelect" class="form-control form-control-sm">
                                                        <?php foreach ($allowedStatusOptions as $statusOption): ?>
                                                            <option value="<?= e($statusOption) ?>" <?= $order['status'] === $statusOption ? 'selected' : '' ?>>
                                                                <?= e($statusOption) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <small class="text-muted d-block mt-1"><?= e($statusMessages[$order['status']] ?? 'Unknown status') ?></small>
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
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card shadow-sm">
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
                </div>
            </div>
            <?php require_once 'layout/footer.php'; ?>
        </div>
    </div>

    <?php require_once 'layout/scripts.php'; ?>
    <script>
        const orderId = <?= (int)$bill_reload_order_id ?>;
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
                const data = { bill_reload_order_id: orderId };
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
                            url: 'api/bill_reload_orders/update_bill_reload_order_status.php',
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
                    url: 'api/bill_reload_orders/update_bill_reload_order_notes.php',
                    method: 'POST',
                    dataType: 'json',
                    data: { bill_reload_order_id: orderId, admin_notes: notes },
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
