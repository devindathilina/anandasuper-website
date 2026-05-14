<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';
$currentDate = date('Y-m-d');
$page_name = "Admin Dashboard";
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

                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2 text-center">
                                            <div class="text-lg font-weight-bold text-dark text-uppercase text-wrap">Welcome to <?= htmlspecialchars($app_name); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="text-md font-weight-bold text-dark text-uppercase mb-2 text-center">Filter by Date Range</div>
                                    <form id="filterForm">
                                        <div class="form-row align-items-end justify-content-center">
                                            <div class="col-auto">
                                                <label for="date_start">From Date</label>
                                                <input type="date" class="form-control form-control-sm" id="date_start" value="<?= $currentDate ?>">
                                            </div>
                                            <div class="col-auto">
                                                <label for="date_end">To Date</label>
                                                <input type="date" class="form-control form-control-sm" id="date_end" value="<?= $currentDate ?>">
                                            </div>
                                            <div class="col-auto mt-2">
                                                <button type="button" class="btn btn-dark btn-sm mr-2" onclick="fetchDashboard()">
                                                    <i class="fas fa-filter mr-1"></i>Filter
                                                </button>
                                                <button type="button" class="btn btn-secondary btn-sm" onclick="clearFilters()">
                                                    <i class="fas fa-times mr-1"></i>Clear
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                    <div class="mt-2">
                                        <small class="text-muted text-center d-block font-weight-bold" id="filterText">Loading...</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-2">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-dark shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Total Orders</div>
                                            <div class="h6 mb-0 font-weight-bold text-gray-800" id="totalOrders">-</div>
                                        </div>
                                        <div class="col-auto"><i class="fas fa-shopping-cart fa-2x text-gray-300"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-dark shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Total Sales</div>
                                            <div class="h6 mb-0 font-weight-bold text-gray-800" id="totalSales">-</div>
                                        </div>
                                        <div class="col-auto"><i class="fas fa-money-bill-wave fa-2x text-gray-300"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-dark shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Avg Order Value</div>
                                            <div class="h6 mb-0 font-weight-bold text-gray-800" id="avgOrderValue">-</div>
                                        </div>
                                        <div class="col-auto"><i class="fas fa-calculator fa-2x text-gray-300"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-dark shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Total Customers</div>
                                            <div class="h6 mb-0 font-weight-bold text-gray-800" id="totalCustomers">-</div>
                                        </div>
                                        <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-xl-6 mb-4">
                            <div class="card shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="text-md text-center font-weight-bold text-dark text-uppercase mb-3">Monthly Sales (LKR)</div>
                                    <div class="col-12 chart-container" style="min-height: 280px;">
                                        <canvas id="revenueChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-6 mb-4">
                            <div class="card shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="text-md text-center font-weight-bold text-dark text-uppercase mb-3">Monthly Growth Rate (%)</div>
                                    <div class="col-12 chart-container" style="min-height: 280px;">
                                        <canvas id="growthChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="card shadow py-2">
                                <div class="card-body">
                                    <div class="text-md text-center font-weight-bold text-dark text-uppercase mb-3">Popular Products</div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered" id="popularProductsTable">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th class="text-center">#</th>
                                                    <th>Product</th>
                                                    <th class="text-center">Qty Sold</th>
                                                    <th class="text-center">Orders</th>
                                                    <th class="text-right">Revenue (LKR)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted">Loading...</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
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
        var CONFIG = {
            api: 'api/dashboard/fetch_dashboard.php',
            chartColors: {
                sales: {
                    bg: 'rgba(54, 162, 235, 0.7)',
                    border: '#36a2eb'
                },
                growth: {
                    positive: '#28a745',
                    negative: '#dc3545'
                }
            }
        };
        var revenueChart = null;
        var growthChart = null;

        function getFilterText(date_start, date_end) {
            var text = 'Orders: Picked Up + Paid | Bill/Reload: Completed + Paid';
            if (date_start && date_end) {
                text = 'From: ' + date_start + ' | To: ' + date_end + ' | ' + text;
            } else if (date_start) {
                text = 'From: ' + date_start + ' | ' + text;
            } else if (date_end) {
                text = 'To: ' + date_end + ' | ' + text;
            }
            return text;
        }

        function formatCurrency(value) {
            return 'LKR ' + parseFloat(value).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        function updateChart(monthlyData) {
            var ctx = document.getElementById('revenueChart').getContext('2d');
            if (revenueChart) {
                revenueChart.destroy();
            }
            var labels = [];
            var salesData = [];
            for (var i = 0; i < monthlyData.length; i++) {
                labels.push(monthlyData[i].month_label);
                salesData.push(monthlyData[i].total_sales);
            }
            revenueChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Sales (LKR)',
                        data: salesData,
                        backgroundColor: CONFIG.chartColors.sales.bg,
                        borderColor: CONFIG.chartColors.sales.border,
                        borderWidth: 1,
                        barPercentage: 0.6,
                        categoryPercentage: 0.7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: '#495057',
                            callbacks: {
                                label: function(c) {
                                    return 'Sales: LKR ' + c.parsed.y.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#6c757d',
                                font: { size: 11 },
                                maxRotation: 45,
                                minRotation: 0
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.05)'
                            },
                            ticks: {
                                color: '#6c757d',
                                font: { size: 11 },
                                callback: function(v) {
                                    return 'LKR ' + v.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }

        function updateGrowthChart(growthData) {
            var ctx = document.getElementById('growthChart').getContext('2d');
            if (growthChart) {
                growthChart.destroy();
            }

            var labels = [];
            var growthValues = [];
            var pointColors = [];
            for (var i = 0; i < growthData.length; i++) {
                labels.push(growthData[i].month_label);
                growthValues.push(growthData[i].growth);
                pointColors.push(growthData[i].growth >= 0 ? CONFIG.chartColors.growth.positive : CONFIG.chartColors.growth.negative);
            }

            growthChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Monthly Growth %',
                        data: growthValues,
                        borderColor: '#6c757d',
                        backgroundColor: 'rgba(108, 117, 125, 0.1)',
                        borderWidth: 2,
                        pointBackgroundColor: pointColors,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: '#495057',
                            callbacks: {
                                label: function(c) {
                                    var sign = c.parsed.y >= 0 ? '+' : '';
                                    return 'Growth: ' + sign + c.parsed.y.toFixed(1) + '%';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                color: '#6c757d',
                                font: { size: 11 },
                                maxRotation: 45,
                                minRotation: 0
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.05)' },
                            ticks: {
                                color: '#6c757d',
                                font: { size: 11 },
                                callback: function(v) {
                                    return v.toFixed(0) + '%';
                                }
                            }
                        }
                    }
                }
            });
        }

        function updatePopularProducts(products) {
            var tbody = $('#popularProductsTable tbody');
            if (products.length === 0) {
                tbody.html('<tr><td colspan="5" class="text-center text-muted">No data available</td></tr>');
                return;
            }
            var html = '';
            for (var i = 0; i < products.length; i++) {
                var p = products[i];
                var productImage = p.product_image ? '<img src="' + p.product_image + '" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">' : '<div class="bg-secondary text-white d-inline-flex align-items-center justify-content-center" style="width:40px;height:40px;border-radius:4px;"><i class="fas fa-box"></i></div>';
                html += '<tr>' +
                    '<td class="text-center">' + (i + 1) + '</td>' +
                    '<td>' +
                        '<div class="d-flex align-items-center">' +
                            productImage +
                            '<span class="ml-2">' + p.product_name + '</span>' +
                        '</div>' +
                    '</td>' +
                    '<td class="text-center">' + p.total_qty_sold + '</td>' +
                    '<td class="text-center">' + p.order_count + '</td>' +
                    '<td class="text-right">' + formatCurrency(p.total_revenue) + '</td>' +
                '</tr>';
            }
            tbody.html(html);
        }

        function updateSummary(summary) {
            $('#totalOrders').text(summary.total_orders);
            $('#totalSales').text(formatCurrency(summary.total_sales));
            $('#avgOrderValue').text(formatCurrency(summary.avg_order_value));
            $('#totalCustomers').text(summary.total_customers);
        }

        function resetDisplay() {
            $('#totalOrders, #totalSales, #avgOrderValue, #totalCustomers').text('-');
            $('#filterText').text('Select filters and click "Filter" to view data');
            if (revenueChart) {
                revenueChart.destroy();
                revenueChart = null;
            }
            if (growthChart) {
                growthChart.destroy();
                growthChart = null;
            }
            $('#popularProductsTable tbody').html('<tr><td colspan="5" class="text-center text-muted">Loading...</td></tr>');
        }

        function fetchDashboard() {
            var params = {
                date_start: $('#date_start').val(),
                date_end: $('#date_end').val()
            };
            $.get(CONFIG.api, params, function(response) {
                if (response.success) {
                    updateSummary(response.summary);
                    $('#filterText').text(getFilterText(params.date_start, params.date_end));
                    updateChart(response.monthly_data);
                    updateGrowthChart(response.growth_data);
                    updatePopularProducts(response.popular_products);
                }
            }).fail(function() {
                $('#totalOrders').text('Error');
            });
        }

        function clearFilters() {
            $('#filterForm')[0].reset();
            resetDisplay();
            fetchDashboard();
        }

        $(document).ready(function() {
            fetchDashboard();
        });
    </script>
</body>
</html>
