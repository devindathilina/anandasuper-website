<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';

$page_name = "Product Orders";

$countCards = [
    ['label' => 'Total', 'id' => 'total', 'border' => 'border-left-primary', 'text' => '', 'color' => null],
    ['label' => 'Pending', 'id' => 'pending', 'border' => 'border-left-warning', 'text' => 'text-warning', 'color' => null],
    ['label' => 'Confirmed', 'id' => 'confirmed', 'border' => 'border-left-info', 'text' => 'text-info', 'color' => null],
    ['label' => 'Preparing', 'id' => 'preparing', 'border' => '', 'text' => '', 'color' => '#fd7e14'],
    ['label' => 'Ready', 'id' => 'ready', 'border' => 'border-left-success', 'text' => 'text-success', 'color' => null],
    ['label' => 'Picked Up', 'id' => 'picked_up', 'border' => 'border-left-secondary', 'text' => 'text-secondary', 'color' => null],
    ['label' => 'Cancelled', 'id' => 'cancelled', 'border' => 'border-left-danger', 'text' => 'text-danger', 'color' => null],
    ['label' => 'Refunded', 'id' => 'refunded', 'border' => 'border-left-dark', 'text' => 'text-dark', 'color' => null]
];
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
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Product Orders</li>
                        </ol>
                    </nav>

                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white">
                            <h6 class="m-0 font-weight-bold">
                                <i class="fas fa-shopping-cart mr-2"></i>Product Orders
                            </h6>
                        </div>

                        <div class="card-body">
                            <div class="row mb-3" id="order-counts">
                                <?php foreach ($countCards as $card): ?>
                                    <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-2">
                                        <div class="card card-stats bg-white <?= $card['border'] ?>" <?= $card['color'] ? "style=\"border-left: 4px solid {$card['color']}\"" : '' ?>>
                                            <div class="card-body p-2">
                                                <div class="text-muted small text-uppercase"><?= $card['label'] ?></div>
                                                <div class="h4 mb-0 font-weight-bold <?= $card['text'] ?>" id="count-<?= $card['id'] ?>">-</div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <form id="filterForm" class="mb-3">
                                <div class="form-row">
                                    <div class="form-group col-md-3">
                                        <label for="date_start">Date From</label>
                                        <input type="date" id="date_start" class="form-control form-control-sm">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="date_end">Date To</label>
                                        <input type="date" id="date_end" class="form-control form-control-sm">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="status">Order Status</label>
                                        <select id="status" class="form-control form-control-sm">
                                            <option value="">All</option>
                                            <option value="Pending">Pending</option>
                                            <option value="Confirmed">Confirmed</option>
                                            <option value="Preparing">Preparing</option>
                                            <option value="Ready">Ready</option>
                                            <option value="Picked Up">Picked Up</option>
                                            <option value="Cancelled">Cancelled</option>
                                            <option value="Refunded">Refunded</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="payment_status">Payment Status</label>
                                        <select id="payment_status" class="form-control form-control-sm">
                                            <option value="">All</option>
                                            <option value="Pending">Pending</option>
                                            <option value="Paid">Paid</option>
                                            <option value="Failed">Failed</option>
                                            <option value="Refunded">Refunded</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-3">
                                        <label for="customer_id">Customer</label>
                                        <select id="customer_id" class="form-control form-control-sm select2-filter">
                                            <option value="">All Customers</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="price_type">Price Type</label>
                                        <select id="price_type" class="form-control form-control-sm">
                                            <option value="">All</option>
                                            <option value="Retail">Retail</option>
                                            <option value="Wholesale">Wholesale</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-3 d-flex align-items-end">
                                        <button type="button" class="btn btn-primary btn-sm mr-2" onclick="applyFilters()">Filter</button>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="clearFilters()">Clear</button>
                                    </div>
                                </div>
                            </form>

                            <table id="ordersTable" class="table table-bordered" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Order Ref</th>
                                        <th>Customer</th>
                                        <th>Price Type</th>
                                        <th>Status</th>
                                        <th>Order Total</th>
                                        <th>Payment</th>
                                        <th>Ordered Date</th>
                                        <th>Rating</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php require_once 'layout/footer.php'; ?>
        </div>
    </div>

    <?php require_once 'layout/scripts.php'; ?>

    <script>
        $(document).ready(function() {
            const na = data => data || '<span class="text-muted">-</span>';

            const renderLink = (data, type, row) => {
                return data ? `<a href="view_order.php?id=${encodeURIComponent(row.order_id)}" class="btn btn-link btn-sm" target="_blank">${data}</a>` : na();
            };

            const renderPriceType = data => {
                if (!data || data === 'N/A') return na();
                const badgeClass = data === 'Wholesale' ? 'info' : 'primary';
                return `<span class="badge badge-${badgeClass}">${data}</span>`;
            };

            const renderStatus = (data, type, row) => {
                return `<span class="badge" ${row.status_class}>${row.status}</span>`;
            };

            const renderPayment = (data, type, row) => {
                const badgeClass = row.payment_status === 'Paid' ? 'success' :
                                   row.payment_status === 'Pending' ? 'warning' :
                                   row.payment_status === 'Failed' ? 'danger' : 'secondary';
                return `<span class="badge badge-${badgeClass}">${row.payment_status}</span>`;
            };

            const renderTotal = data => {
                return `Rs. ${parseFloat(data).toFixed(2)}`;
            };

            const renderDate = data => {
                if (!data || data === 'N/A') return '<span class="text-muted">-</span>';
                const date = new Date(data);
                return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + '<br>' +
                       date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
            };

            const renderRating = data => {
                if (data === null || data === 'N/A') return '<span class="text-muted">-</span>';
                let stars = '';
                for (let i = 1; i <= 5; i++) {
                    stars += i <= data ? '<i class="fas fa-star text-warning"></i>' : '<i class="far fa-star text-muted"></i>';
                }
                return stars;
            };

            const loadOrderCounts = () => {
                $.get('api/shared/fetch_counts.php', { type: 'order' }, res => {
                    if (res && res.success && res.counts) {
                        Object.entries(res.counts).forEach(([k, v]) => {
                            $(`#count-${k}`).text(v ?? 0);
                        });
                    }
                });
            };

            let filterDateStart = '';
            let filterDateEnd = '';
            let filterStatus = '';
            let filterPaymentStatus = '';
            let filterCustomerId = '';
            let filterPriceType = '';

            const fetchOrders = () => table.ajax.reload();
            const clearFilters = () => {
                $('#filterForm')[0].reset();
                $('.select2-filter').val(null).trigger('change');
                filterDateStart = '';
                filterDateEnd = '';
                filterStatus = '';
                filterPaymentStatus = '';
                filterCustomerId = '';
                filterPriceType = '';
                fetchOrders();
            };
            const applyFilters = () => {
                filterDateStart = $('#date_start').val();
                filterDateEnd = $('#date_end').val();
                filterStatus = $('#status').val();
                filterPaymentStatus = $('#payment_status').val();
                filterCustomerId = $('#customer_id').val();
                filterPriceType = $('#price_type').val();
                table.ajax.reload();
            };

            loadOrderCounts();

            $('#customer_id').select2({
                placeholder: 'All Customers',
                allowClear: true,
                ajax: {
                    url: 'api/customers/fetch_customers_2.php',
                    dataType: 'json',
                    delay: 250,
                    data: params => ({ search: params.term }),
                    processResults: data => {
                        if (data.success) {
                            return { results: data.customers.map(c => ({ id: c.customer_id, text: `${c.customer_name} (${c.phone})` })) };
                        }
                        return { results: [] };
                    },
                    cache: true
                },
                minimumInputLength: 0
            });

            const table = $('#ordersTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: 'api/orders/fetch_orders.php',
                    type: 'POST',
                    data: function(d) {
                        return {
                            draw: d.draw,
                            start: d.start,
                            length: d.length,
                            date_start: filterDateStart,
                            date_end: filterDateEnd,
                            status: filterStatus,
                            payment_status: filterPaymentStatus,
                            customer_id: filterCustomerId,
                            price_type: filterPriceType
                        };
                    },
                    dataSrc: function(json) {
                        if (!json || !json.data) return [];
                        return json.data;
                    }
                },
                columns: [
                    { data: 'order_id', defaultContent: '' },
                    { data: 'order_ref_no', render: renderLink, defaultContent: '' },
                    { data: 'customer_name', render: na, defaultContent: '' },
                    { data: 'price_type', render: renderPriceType, defaultContent: '' },
                    { data: null, render: renderStatus, defaultContent: '' },
                    { data: 'order_total', render: renderTotal, defaultContent: '' },
                    { data: null, render: renderPayment, defaultContent: '' },
                    { data: 'ordered_datetime', render: renderDate, defaultContent: '' },
                    { data: 'rating', render: renderRating, defaultContent: '' }
                ],
                responsive: true,
                pageLength: 25,
                order: [[0, 'desc']]
            });

            window.fetchOrders = fetchOrders;
            window.clearFilters = clearFilters;
            window.applyFilters = applyFilters;
        });
    </script>
</body>
</html>
