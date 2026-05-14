<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';

$page_name = "Offers";
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
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Offers</li>
                        </ol>
                    </nav>

                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-tags mr-2"></i>
                                        Offers
                                    </h6>
                                </div>
                                <div class="col-md-4 text-right">
                                    <a href="add_offer.php" class="btn btn-light btn-sm">
                                        <i class="fas fa-plus mr-1"></i> Add Offer
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row mb-3" id="offer-counts">
                                <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-2">
                                    <div class="card card-stats bg-white">
                                        <div class="card-body p-2">
                                            <div class="text-muted small text-uppercase">Total</div>
                                            <div class="h4 mb-0 font-weight-bold" id="count-total">-</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-2">
                                    <div class="card card-stats bg-white border-left-success">
                                        <div class="card-body p-2">
                                            <div class="text-muted small text-uppercase">Active</div>
                                            <div class="h4 mb-0 font-weight-bold text-success" id="count-active">-</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-2">
                                    <div class="card card-stats bg-white border-left-secondary">
                                        <div class="card-body p-2">
                                            <div class="text-muted small text-uppercase">Inactive</div>
                                            <div class="h4 mb-0 font-weight-bold text-secondary" id="count-inactive">-</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-2">
                                    <div class="card card-stats bg-white border-left-info">
                                        <div class="card-body p-2">
                                            <div class="text-muted small text-uppercase">Running Now</div>
                                            <div class="h4 mb-0 font-weight-bold text-info" id="count-running_now">-</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-2">
                                    <div class="card card-stats bg-white border-left-warning">
                                        <div class="card-body p-2">
                                            <div class="text-muted small text-uppercase">Upcoming</div>
                                            <div class="h4 mb-0 font-weight-bold text-warning" id="count-upcoming">-</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-2">
                                    <div class="card card-stats bg-white border-left-danger">
                                        <div class="card-body p-2">
                                            <div class="text-muted small text-uppercase">Expired</div>
                                            <div class="h4 mb-0 font-weight-bold text-danger" id="count-expired">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 mb-3">
                                <form id="filterForm" onsubmit="return false;">
                                    <div class="form-row">
                                        <div class="form-group col-md-3">
                                            <label for="date_start">Date From</label>
                                            <input type="date" name="date_start" id="date_start" class="form-control form-control-sm">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="date_end">Date To</label>
                                            <input type="date" name="date_end" id="date_end" class="form-control form-control-sm">
                                        </div>
                                        <div class="form-group col-md-2">
                                            <label for="is_active">Active Status</label>
                                            <select name="is_active" id="is_active" class="form-control form-control-sm">
                                                <option value="">All</option>
                                                <option value="1">Active</option>
                                                <option value="0">Inactive</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-2">
                                            <label for="offer_status">Offer Period</label>
                                            <select name="offer_status" id="offer_status" class="form-control form-control-sm">
                                                <option value="">All</option>
                                                <option value="active">Running Now</option>
                                                <option value="upcoming">Upcoming</option>
                                                <option value="expired">Expired</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-2 d-flex align-items-end">
                                            <button type="button" class="btn btn-primary btn-sm mr-2" onclick="applyFilters()">Filter</button>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="clearFilters()">Clear</button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <div class="table">
                                <table id="offersTable" class="table table-bordered" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Image</th>
                                            <th>Offer Name</th>
                                            <th>Code</th>
                                            <th>Discount</th>
                                            <th>Valid Period</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                </table>
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
        $(document).ready(function() {
            const renderImage = data => {
                if (data && data !== 'N/A') {
                    return `<img src="../assets/offer/${data}" alt="Offer" style="width: 60px; height: 30px; object-fit: cover;">`;
                }
                return '<span class="text-muted">No Image</span>';
            };

            const renderOfferName = (data, type, row) => {
                if (!data) return '-';
                const description = row.description && row.description !== 'N/A'
                    ? row.description.substring(0, 50) + (row.description.length > 50 ? '...' : '')
                    : '';
                return `<strong>${data}</strong>${description ? `<br><small class="text-muted">${description}</small>` : ''}`;
            };

            const renderOfferCode = data => {
                return data ? `<code>${data}</code>` : '<span class="text-muted">-</span>';
            };

            const renderDiscount = data => {
                return data ? `<span class="badge badge-primary">${data}% OFF</span>` : '<span class="text-muted">-</span>';
            };

            const renderValidPeriod = (data, type, row) => {
                const startDate = new Date(row.start_date);
                const endDate = new Date(row.end_date);
                const formattedStart = startDate.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
                const formattedEnd = endDate.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
                const statusBadge = `<span class="badge badge-${row.offer_period_class}">${row.offer_period_status}</span>`;
                return `${formattedStart} - ${formattedEnd}<br>${statusBadge}`;
            };

            const renderStatus = data => {
                if (data == null || data === '') return '-';
                return data == 1 || data == '1'
                    ? '<span class="badge badge-success">Active</span>'
                    : '<span class="badge badge-danger">Inactive</span>';
            };

            const renderActions = (data, type, row) => {
                if (!row || !row.offer_id) return '';
                const checked = (row.is_active == 1 || row.is_active == '1') ? 'checked' : '';
                const id = row.offer_id;
                return `
                    <a href="edit_offer.php?id=${encodeURIComponent(id)}" class="btn btn-primary btn-sm mr-2" title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    <div class="custom-control custom-switch d-inline-block">
                        <input type="checkbox" class="custom-control-input status-switch"
                               id="switch_${id}" data-id="${id}" ${checked}>
                        <label class="custom-control-label" for="switch_${id}">Active</label>
                    </div>`;
            };

            const updateStatus = (id, isActive) => {
                $.post('api/shared/change_status.php', { type: 'offer', id, is_active: isActive })
                    .done(res => {
                        Swal.fire({ icon: 'success', title: 'Success', text: res, timer: 1500, showConfirmButton: false });
                        table.ajax.reload(null, false);
                        loadOfferCounts();
                    })
                    .fail(() => {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to update offer status.' });
                        table.ajax.reload(null, false);
                    });
            };

            const loadOfferCounts = () => {
                $.get('api/shared/fetch_counts.php', { type: 'offer' }, res => {
                    if (res && res.success && res.counts) {
                        Object.entries(res.counts).forEach(([k, v]) => {
                            $('#count-' + k).text(v ?? 0);
                        });
                    }
                });
            };

            let filterDateStart = '';
            let filterDateEnd = '';
            let filterActive = '';
            let filterOfferStatus = '';

            const fetchOffers = () => table.ajax.reload();
            const clearFilters = () => {
                $('#filterForm')[0].reset();
                filterDateStart = '';
                filterDateEnd = '';
                filterActive = '';
                filterOfferStatus = '';
                fetchOffers();
            };
            const applyFilters = () => {
                filterDateStart = $('#date_start').val();
                filterDateEnd = $('#date_end').val();
                filterActive = $('#is_active').val();
                filterOfferStatus = $('#offer_status').val();
                table.ajax.reload();
            };

            loadOfferCounts();

            const status = new URLSearchParams(window.location.search).get('status');
            if (status === 'active') {
                filterActive = '1';
                $('#is_active').val('1');
            } else if (status === 'inactive') {
                filterActive = '0';
                $('#is_active').val('0');
            }

            const table = $('#offersTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: 'api/offers/fetch_offers.php',
                    type: 'POST',
                    data: function(d) {
                        return {
                            draw: d.draw,
                            start: d.start,
                            length: d.length,
                            date_start: filterDateStart,
                            date_end: filterDateEnd,
                            is_active: filterActive,
                            offer_status: filterOfferStatus
                        };
                    },
                    dataSrc: function(json) {
                        if (!json || !json.data) return [];
                        return json.data;
                    }
                },
                columns: [
                    { data: 'offer_id', defaultContent: '' },
                    { data: 'offer_image', orderable: false, render: renderImage, defaultContent: '' },
                    { data: 'offer_name', render: renderOfferName, defaultContent: '' },
                    { data: 'offer_code', render: renderOfferCode, defaultContent: '' },
                    { data: 'off_percentage', render: renderDiscount, defaultContent: '' },
                    { data: null, render: renderValidPeriod, defaultContent: '' },
                    { data: 'is_active', render: renderStatus, defaultContent: '' },
                    { data: null, orderable: false, render: renderActions, defaultContent: '' }
                ],
                responsive: true,
                pageLength: 25,
                order: [[0, 'desc']]
            });

            $('#offersTable').on('change', '.status-switch', function() {
                updateStatus($(this).data('id'), $(this).is(':checked') ? 1 : 0);
            });

            window.fetchOffers = fetchOffers;
            window.clearFilters = clearFilters;
            window.applyFilters = applyFilters;
        });
    </script>
</body>

</html>
