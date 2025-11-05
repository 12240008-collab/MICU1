<?php
session_start();
require_once '../config.php';
requirePartner();

$user = getCurrentUser();
$db = db();

// Get partner info
$stmt = $db->prepare("SELECT * FROM laundry_partners WHERE user_id = ?");
$stmt->execute([$user['id']]);
$partner = $stmt->fetch();

if (!$partner) {
    redirect('partner/login.php');
}

$partner_id = $partner['id'];

// Get report type and date range
$report_type = isset($_GET['type']) ? sanitize($_GET['type']) : 'daily';
$custom_start = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : '';
$custom_end = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : '';

// Set date range based on report type
switch ($report_type) {
    case 'daily':
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d');
        $report_title = 'Laporan Harian';
        $subtitle = date('d F Y');
        break;
    case 'weekly':
        $start_date = date('Y-m-d', strtotime('monday this week'));
        $end_date = date('Y-m-d', strtotime('sunday this week'));
        $report_title = 'Laporan Mingguan';
        $subtitle = formatDate($start_date, 'd M') . ' - ' . formatDate($end_date, 'd M Y');
        break;
    case 'monthly':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        $report_title = 'Laporan Bulanan';
        $subtitle = date('F Y');
        break;
    case 'custom':
        $start_date = $custom_start ?: date('Y-m-01');
        $end_date = $custom_end ?: date('Y-m-d');
        $report_title = 'Laporan Custom';
        $subtitle = formatDate($start_date, 'd M Y') . ' - ' . formatDate($end_date, 'd M Y');
        break;
    default:
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d');
        $report_title = 'Laporan Harian';
        $subtitle = date('d F Y');
}

// Get summary statistics - UPDATED STATUS
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
        SUM(CASE WHEN status IN ('pending', 'paid', 'pickup', 'processing') THEN 1 ELSE 0 END) as in_progress_orders,
        SUM(CASE WHEN status = 'done' THEN total_amount ELSE 0 END) as total_revenue,
        SUM(CASE WHEN status = 'done' THEN estimated_weight ELSE 0 END) as total_weight,
        AVG(CASE WHEN status = 'done' THEN total_amount ELSE NULL END) as avg_order_value
    FROM orders
    WHERE partner_id = ? 
    AND DATE(created_at) BETWEEN ? AND ?
");
$stmt->execute([$partner_id, $start_date, $end_date]);
$summary = $stmt->fetch();

// Get orders by status
$stmt = $db->prepare("
    SELECT status, COUNT(*) as count
    FROM orders
    WHERE partner_id = ? 
    AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY status
");
$stmt->execute([$partner_id, $start_date, $end_date]);
$orders_by_status = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get revenue by service
$stmt = $db->prepare("
    SELECT s.service_name, 
           COUNT(o.id) as order_count,
           SUM(CASE WHEN o.status = 'done' THEN o.total_amount ELSE 0 END) as revenue
    FROM services s
    LEFT JOIN orders o ON s.id = o.service_id 
        AND o.partner_id = ? 
        AND DATE(o.created_at) BETWEEN ? AND ?
    WHERE s.partner_id = ?
    GROUP BY s.id, s.service_name
    ORDER BY revenue DESC
");
$stmt->execute([$partner_id, $start_date, $end_date, $partner_id]);
$service_stats = $stmt->fetchAll();

// Get daily trend (for charts)
$stmt = $db->prepare("
    SELECT DATE(created_at) as date,
           COUNT(*) as orders,
           SUM(CASE WHEN status = 'done' THEN total_amount ELSE 0 END) as revenue
    FROM orders
    WHERE partner_id = ? 
    AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at)
");
$stmt->execute([$partner_id, $start_date, $end_date]);
$daily_trend = $stmt->fetchAll();

// Get top customers
$stmt = $db->prepare("
    SELECT u.full_name, u.phone,
           COUNT(o.id) as order_count,
           SUM(CASE WHEN o.status = 'done' THEN o.total_amount ELSE 0 END) as total_spent
    FROM users u
    JOIN orders o ON u.id = o.customer_id
    WHERE o.partner_id = ? 
    AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY u.id, u.full_name, u.phone
    ORDER BY total_spent DESC
    LIMIT 10
");
$stmt->execute([$partner_id, $start_date, $end_date]);
$top_customers = $stmt->fetchAll();

// Get payment methods distribution
$stmt = $db->prepare("
    SELECT p.payment_method,
           COUNT(*) as count,
           SUM(p.amount) as total
    FROM payments p
    JOIN orders o ON p.order_id = o.id
    WHERE o.partner_id = ? 
    AND p.status = 'paid'
    AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY p.payment_method
");
$stmt->execute([$partner_id, $start_date, $end_date]);
$payment_methods = $stmt->fetchAll();

// Calculate growth (compare with previous period)
$period_days = (strtotime($end_date) - strtotime($start_date)) / 86400 + 1;
$prev_start = date('Y-m-d', strtotime($start_date . " -$period_days days"));
$prev_end = date('Y-m-d', strtotime($start_date . " -1 day"));

$stmt = $db->prepare("
    SELECT 
        COUNT(*) as prev_orders,
        SUM(CASE WHEN status = 'done' THEN total_amount ELSE 0 END) as prev_revenue
    FROM orders
    WHERE partner_id = ? 
    AND DATE(created_at) BETWEEN ? AND ?
");
$stmt->execute([$partner_id, $prev_start, $prev_end]);
$previous = $stmt->fetch();

$order_growth = $previous['prev_orders'] > 0 
    ? (($summary['total_orders'] - $previous['prev_orders']) / $previous['prev_orders']) * 100 
    : 0;
$revenue_growth = $previous['prev_revenue'] > 0 
    ? (($summary['total_revenue'] - $previous['prev_revenue']) / $previous['prev_revenue']) * 100 
    : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - <?php echo htmlspecialchars($partner['laundry_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .card { border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: all 0.3s ease; } /* PERBAIKAN: Tambah shadow dan transisi */
        .card:hover { box-shadow: 0 6px 12px rgba(0,0,0,0.15); }
        .btn { border-radius: 12px; transition: all 0.2s ease; }
        .btn:hover { transform: translateY(-1px); }
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .card { box-shadow: none; border: 1px solid #e5e7eb; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen"> <!-- PERBAIKAN: Tambah min-h-screen untuk layout full -->
    <!-- Header -->
    <header class="bg-white shadow-sm sticky top-0 z-50 no-print">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <a href="dashboard.php" class="mr-4">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">Laporan & Analitik</h1>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($partner['laundry_name']); ?></p>
                    </div>
                </div>
                <!-- Tombol print atau lainnya bisa ditambah di sini jika perlu -->
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Pilih Periode Laporan (seperti di screenshot) -->
        <div class="bg-white card shadow-md p-6 mb-6 no-print"> <!-- PERBAIKAN: Tambah card class -->
            <h3 class="font-bold text-gray-800 mb-4">Pilih Periode Laporan</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="?type=daily" class="bg-purple-100 hover:bg-purple-200 text-purple-700 font-semibold py-4 px-4 rounded-lg text-center transition duration-200">
                    <svg class="w-6 h-6 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Harian<br>Hari ini
                </a>
                <a href="?type=weekly" class="bg-purple-100 hover:bg-purple-200 text-purple-700 font-semibold py-4 px-4 rounded-lg text-center transition duration-200">
                    <svg class="w-6 h-6 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Mingguan<br>Minggu ini
                </a>
                <a href="?type=monthly" class="bg-purple-100 hover:bg-purple-200 text-purple-700 font-semibold py-4 px-4 rounded-lg text-center transition duration-200">
                    <svg class="w-6 h-6 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Bulanan<br>Bulan ini
                </a>
                <button onclick="showCustomDateModal()" class="bg-purple-100 hover:bg-purple-200 text-purple-700 font-semibold py-4 px-4 rounded-lg text-center transition duration-200">
                    <svg class="w-6 h-6 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2v10m6-6H4"></path>
                    </svg>
                    Custom<br>Pilih tanggal
                </button>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Tren Pendapatan -->
            <div class="bg-white card shadow-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-gray-800">Tren Pendapatan</h3>
                    <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                </div>
                <div style="position: relative; height: 250px;">
                    <canvas id="revenueChart"></canvas>
                </div>
                <?php if (empty($daily_trend)): ?>
                    <p class="text-center text-gray-500 mt-2">Tidak ada data untuk periode ini</p> <!-- PERBAIKAN: Tambah pesan di bawah chart -->
                <?php endif; ?>
            </div>

            <!-- Status Pesanan -->
            <div class="bg-white card shadow-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-gray-800">Status Pesanan</h3>
                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                </div>
                <div style="position: relative; height: 250px;">
                    <canvas id="statusChart"></canvas>
                </div>
                <?php if (empty($orders_by_status)): ?>
                    <p class="text-center text-gray-500 mt-2">Tidak ada data untuk periode ini</p> <!-- PERBAIKAN: Tambah pesan di bawah chart -->
                <?php endif; ?>
            </div>
        </div>

        <!-- Bagian lain seperti Performa Layanan, Top Customers, dll. tetap sama, tapi bisa ditambahkan card class jika perlu -->
        <!-- ... (kode bagian lain seperti Service Performance, Top Customers, Payment Methods tetap seperti asli, tapi tambah class="bg-white card shadow-md p-6" pada div utamanya untuk konsistensi) ... -->

    </div>

    <!-- Custom Date Modal -->
    <div id="customDateModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4 no-print">
        <div class="bg-white rounded-lg max-w-md w-full p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-bold text-gray-800">Pilih Periode Custom</h3>
                <button onclick="closeCustomDateModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <form method="GET" action="">
                <input type="hidden" name="type" value="custom">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Tanggal Mulai *</label>
                    <input type="date" name="start_date" required max="<?php echo date('Y-m-d'); ?>"
                        value="<?php echo $custom_start ?: date('Y-m-01'); ?>"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Tanggal Akhir *</label>
                    <input type="date" name="end_date" required max="<?php echo date('Y-m-d'); ?>"
                        value="<?php echo $custom_end ?: date('Y-m-d'); ?>"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
                <div class="flex space-x-3">
                    <button type="button" onclick="closeCustomDateModal()" 
                        class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 btn transition duration-200">
                        Batal
                    </button>
                    <button type="submit" 
                        class="flex-1 bg-purple-500 hover:bg-purple-600 text-white font-semibold py-3 btn transition duration-200">
                        Tampilkan Laporan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // PERBAIKAN: Inisialisasi modal
        window.addEventListener('DOMContentLoaded', function() {
            document.getElementById('customDateModal').classList.add('hidden');
        });

        function showCustomDateModal() {
            document.getElementById('customDateModal').classList.remove('hidden');
        }

        function closeCustomDateModal() {
            document.getElementById('customDateModal').classList.add('hidden');
        }

        // PERBAIKAN: Close modal when clicking outside
        document.getElementById('customDateModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCustomDateModal();
            }
        });

        // PERBAIKAN: Revenue Trend Chart - Selalu render, bahkan jika data kosong
        const revenueCtx = document.getElementById('revenueChart');
        if (revenueCtx) {
            const revenueLabels = [<?php echo !empty($daily_trend) ? implode(',', array_map(function($d) { return "'" . formatDate($d['date'], 'd M') . "'"; }, $daily_trend)) : "''"; ?>];
            const revenueData = [<?php echo !empty($daily_trend) ? implode(',', array_column($daily_trend, 'revenue')) : '0'; ?>];
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: revenueLabels,
                    datasets: [{
                        label: 'Pendapatan (Rp)',
                        data: revenueData,
                        borderColor: 'rgb(139, 92, 246)',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) { return 'Rp ' + value.toLocaleString('id-ID'); }
                            }
                        }
                    }
                }
            });
        }

        // PERBAIKAN: Status Chart - UPDATED LABELS, selalu render bahkan jika data kosong
        const statusLabelsMap = {
            'pending': 'Menunggu',
            'paid': 'Dibayar',
            'pickup': 'Dijemput',
            'processing': 'Diproses',
            'done': 'Selesai',
            'cancelled': 'Dibatalkan'
        };

        const statusColors = {
            'pending': '#EAB308',
            'paid': '#3B82F6',
            'pickup': '#6366F1',
            'processing': '#8B5CF6',
            'done': '#10B981',
            'cancelled': '#EF4444'
        };

        const statusData = <?php echo json_encode($orders_by_status ?: []); ?>;
        const statusCtx = document.getElementById('statusChart');
        
        if (statusCtx) {
            const statusChartLabels = Object.keys(statusData).length > 0 ? Object.keys(statusData).map(key => statusLabelsMap[key] || key) : ['Tidak ada data'];
            const statusChartData = Object.keys(statusData).length > 0 ? Object.values(statusData) : [0];
            const statusChartColors = Object.keys(statusData).length > 0 ? Object.keys(statusData).map(key => statusColors[key] || '#6B7280') : ['#D1D5DB'];

            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: statusChartLabels,
                    datasets: [{
                        data: statusChartData,
                        backgroundColor: statusChartColors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }

        // Kode chart lain seperti Payment Methods tetap sama, tapi bisa diadaptasi jika perlu
    </script>
</body>
</html>