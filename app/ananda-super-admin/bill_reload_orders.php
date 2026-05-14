<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';

$page_name = "Bill/Reload Orders";

$countCards = [
    ['label' => 'Total', 'id' => 'total', 'border' => 'border-left-primary', 'text' => '', 'color' => null],
    ['label' => 'Pending', 'id' => 'pending', 'border' => 'border-left-warning', 'text' => 'text-warning', 'color' => null],
    ['label' => 'Processing', 'id' => 'processing', 'border' => '', 'text' => '', 'color' => '#0dcaf0'],
    ['label' => 'Completed', 'id' => 'completed', 'border' => 'border-left-success', 'text' => 'text-success', 'color' => null],
    ['label' => 'Failed', 'id' => 'failed', 'border' => 'border-left-danger', 'text' => 'text-danger', 'color' => null],
    ['label' => 'Refunded', 'id' => 'refunded', 'border' => 'border-left-secondary', 'text' => 'text-secondary', 'color' => null]
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
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Bill/Reload Orders</li>
                        </ol>
                    </nav>

                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white">
                            <h6 class="m-0 font-weight-bold">
                                <i class="fas fa-bolt mr-2"></i>Bill/Reload Orders
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
                                            <option value="Processing">Processing</option>
                                            <option value="Completed">Completed</option>
                                            <option value="Failed">Failed</option>
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
                                        <label for="service_id">Service</label>
                                        <select id="service_id" class="form-control form-control-sm select2-filter">
                                            <option value="">All Services</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-3 d-flex align-items-end">
                                        <button type="button" class="btn btn-primary btn-sm mr-2" onclick="fetchOrders()">Filter</button>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="clearFilters()">Clear</button>
                                    </div>
                                </div>
                            </form>

                            <table id="ordersTable" class="table table-bordered" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Ref No</th>
                                        <th>Customer</th>
                                        <th>Service</th>
                                        <th>Account Number</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Created Date</th>
                                        <th>Action</th>
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
    $(document).ready(function () {

        const na = (data) => {
            return data && data !== 'N/A'
                ? data
                : '<span class="text-muted">-</span>';
        };

        const renderLink = (data, type, row) => {
            if (!data || !row.bill_reload_order_id) {
                return na();
            }

            return `
                <a href="view_bill_reload_order.php?id=${encodeURIComponent(row.bill_reload_order_id)}"
                   class="btn btn-link btn-sm p-0"
                   target="_blank">
                    ${data}
                </a>
            `;
        };

        const renderServiceType = (data, type, row) => {

            if (!row.service_type || row.service_type === 'N/A') {
                return na();
            }

            const badgeClass =
                row.service_type === 'Bill'
                    ? 'danger'
                    : 'primary';

            return `
                <div>
                    <div>${na(row.service_name)}</div>
                    <span class="badge badge-${badgeClass}">
                        ${row.service_type}
                    </span>
                </div>
            `;
        };

        const renderStatus = (data, type, row) => {

            const status = row.status || 'Unknown';

            let badgeClass = 'secondary';

            switch (status.toLowerCase()) {
                case 'completed':
                case 'success':
                    badgeClass = 'success';
                    break;

                case 'pending':
                    badgeClass = 'warning';
                    break;

                case 'failed':
                case 'cancelled':
                    badgeClass = 'danger';
                    break;

                case 'processing':
                    badgeClass = 'info';
                    break;
            }

            return `
                <span class="badge badge-${badgeClass}">
                    ${status}
                </span>
            `;
        };

        const renderPayment = (data, type, row) => {

            const paymentStatus = row.payment_status || 'Unknown';

            let badgeClass = 'secondary';

            switch (paymentStatus.toLowerCase()) {
                case 'paid':
                    badgeClass = 'success';
                    break;

                case 'pending':
                    badgeClass = 'warning';
                    break;

                case 'failed':
                    badgeClass = 'danger';
                    break;
            }

            return `
                <span class="badge badge-${badgeClass}">
                    ${paymentStatus}
                </span>
            `;
        };

        const renderAmount = (data) => {

            const amount = parseFloat(data);

            if (isNaN(amount)) {
                return 'Rs. 0.00';
            }

            return `Rs. ${amount.toFixed(2)}`;
        };

        const renderDate = (data) => {

            if (!data || data === 'N/A') {
                return '<span class="text-muted">-</span>';
            }

            const date = new Date(data);

            if (isNaN(date.getTime())) {
                return '<span class="text-muted">-</span>';
            }

            return `
                ${date.toLocaleDateString('en-GB', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric'
                })}
                <br>
                ${date.toLocaleTimeString('en-GB', {
                    hour: '2-digit',
                    minute: '2-digit'
                })}
            `;
        };

        let filterDateStart = '';
        let filterDateEnd = '';
        let filterStatus = '';
        let filterPaymentStatus = '';
        let filterCustomerId = '';
        let filterServiceId = '';

        const loadOrderCounts = () => {

            $.ajax({
                url: 'api/shared/fetch_counts.php',
                type: 'GET',
                dataType: 'json',
                data: {
                    type: 'bill_reload_order'
                },
                success: function (res) {

                    if (res && res.success && res.counts) {

                        Object.entries(res.counts).forEach(([key, value]) => {
                            $('#count-' + key).text(value ?? 0);
                        });
                    }
                }
            });
        };

        const fetchOrders = () => {
            table.ajax.reload(null, false);
        };

        const clearFilters = () => {

            $('#filterForm')[0].reset();

            $('.select2-filter')
                .val(null)
                .trigger('change');

            filterDateStart = '';
            filterDateEnd = '';
            filterStatus = '';
            filterPaymentStatus = '';
            filterCustomerId = '';
            filterServiceId = '';

            fetchOrders();
        };

        const applyFilters = () => {

            filterDateStart = $('#date_start').val();
            filterDateEnd = $('#date_end').val();
            filterStatus = $('#status').val();
            filterPaymentStatus = $('#payment_status').val();
            filterCustomerId = $('#customer_id').val();
            filterServiceId = $('#service_id').val();

            fetchOrders();
        };

        loadOrderCounts();

        $('#customer_id').select2({
            placeholder: 'All Customers',
            allowClear: true,
            width: '100%',
            ajax: {
                url: 'api/customers/fetch_customers_2.php',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        search: params.term || ''
                    };
                },
                processResults: function (data) {

                    if (!data.success || !data.customers) {
                        return {
                            results: []
                        };
                    }

                    return {
                        results: data.customers.map(function (customer) {
                            return {
                                id: customer.customer_id,
                                text: `${customer.customer_name} (${customer.phone})`
                            };
                        })
                    };
                },
                cache: true
            },
            minimumInputLength: 0
        });

        $('#service_id').select2({
            placeholder: 'All Services',
            allowClear: true,
            width: '100%',
            ajax: {
                url: 'api/service/fetch_services_2.php',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        search: params.term || ''
                    };
                },
                processResults: function (data) {

                    if (!data.success || !data.services) {
                        return {
                            results: []
                        };
                    }

                    return {
                        results: data.services.map(function (service) {
                            return {
                                id: service.service_id,
                                text: `${service.service_name} (${service.service_type})`
                            };
                        })
                    };
                },
                cache: true
            },
            minimumInputLength: 0
        });

        const table = $('#ordersTable').DataTable({

            processing: true,
            serverSide: true,
            responsive: true,
            pageLength: 25,
            order: [[0, 'desc']],

            ajax: {
                url: 'api/bill_reload_orders/fetch_bill_reload_orders.php',
                type: 'POST',

                data: function (d) {

                    d.date_start = filterDateStart;
                    d.date_end = filterDateEnd;
                    d.status = filterStatus;
                    d.payment_status = filterPaymentStatus;
                    d.customer_id = filterCustomerId;
                    d.service_id = filterServiceId;

                    return d;
                },

                dataSrc: function (json) {

                    if (!json || !json.data) {
                        return [];
                    }

                    return json.data;
                },

                error: function (xhr, error, thrown) {
                    console.error('DataTable AJAX Error:', error);
                    console.error(xhr.responseText);
                }
            },

            columns: [

                {
                    data: 'bill_reload_order_id',
                    defaultContent: ''
                },

                {
                    data: 'bill_reload_ref_no',
                    render: renderLink,
                    defaultContent: ''
                },

                {
                    data: 'customer_name',
                    render: na,
                    defaultContent: ''
                },

                {
                    data: null,
                    render: renderServiceType,
                    defaultContent: ''
                },

                {
                    data: 'account_number',
                    render: na,
                    defaultContent: ''
                },

                {
                    data: 'total_amount',
                    render: renderAmount,
                    defaultContent: ''
                },

                {
                    data: null,
                    render: renderStatus,
                    defaultContent: ''
                },

                {
                    data: null,
                    render: renderPayment,
                    defaultContent: ''
                },

                {
                    data: 'created_at',
                    render: renderDate,
                    defaultContent: ''
                },

                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    defaultContent: '',
                    render: function (data, type, row) {

                        if (!row.bill_reload_order_id) {
                            return '';
                        }

                        return `
                            <a href="view_bill_reload_order.php?id=${encodeURIComponent(row.bill_reload_order_id)}"
                               class="btn btn-sm btn-info"
                               title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                        `;
                    }
                }
            ]
        });

        window.fetchOrders = fetchOrders;
        window.clearFilters = clearFilters;
        window.applyFilters = applyFilters;

        window.viewOrder = function (id) {

            if (!id) {
                return;
            }

            window.location.href =
                'view_bill_reload_order.php?id=' + encodeURIComponent(id);
        };
    });
</script>
</body>
</html>
