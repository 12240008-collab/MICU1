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

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_service':
                $service_name = sanitize($_POST['service_name']);
                $service_name_en = sanitize($_POST['service_name_en']);
                $description = sanitize($_POST['description']);
                $price_per_kg = (float)$_POST['price_per_kg'];
                $estimated_hours = (int)$_POST['estimated_hours'];
                
                if (empty($service_name) || empty($price_per_kg)) {
                    $error = 'Nama layanan dan harga harus diisi';
                } elseif ($price_per_kg < 1000) {
                    $error = 'Harga minimal Rp 1.000';
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO services (partner_id, service_name, service_name_en, description, price_per_kg, estimated_hours, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, TRUE)
                    ");
                    if ($stmt->execute([$partner_id, $service_name, $service_name_en, $description, $price_per_kg, $estimated_hours])) {
                        $success = 'Layanan berhasil ditambahkan';
                    } else {
                        $error = 'Gagal menambahkan layanan';
                    }
                }
                break;
                
            case 'edit_service':
                $service_id = (int)$_POST['service_id'];
                $service_name = sanitize($_POST['service_name']);
                $service_name_en = sanitize($_POST['service_name_en']);
                $description = sanitize($_POST['description']);
                $price_per_kg = (float)$_POST['price_per_kg'];
                $estimated_hours = (int)$_POST['estimated_hours'];
                
                if (empty($service_name) || empty($price_per_kg)) {
                    $error = 'Nama layanan dan harga harus diisi';
                } elseif ($price_per_kg < 1000) {
                    $error = 'Harga minimal Rp 1.000';
                } else {
                    $stmt = $db->prepare("
                        UPDATE services 
                        SET service_name = ?, service_name_en = ?, description = ?, price_per_kg = ?, estimated_hours = ?
                        WHERE id = ? AND partner_id = ?
                    ");
                    if ($stmt->execute([$service_name, $service_name_en, $description, $price_per_kg, $estimated_hours, $service_id, $partner_id])) {
                        $success = 'Layanan berhasil diperbarui';
                    } else {
                        $error = 'Gagal memperbarui layanan';
                    }
                }
                break;
                
            case 'toggle_status':
                $service_id = (int)$_POST['service_id'];
                $is_active = (int)$_POST['is_active'];
                
                $stmt = $db->prepare("UPDATE services SET is_active = ? WHERE id = ? AND partner_id = ?");
                if ($stmt->execute([$is_active, $service_id, $partner_id])) {
                    $success = 'Status layanan berhasil diubah';
                } else {
                    $error = 'Gagal mengubah status';
                }
                break;
                
            case 'delete_service':
                $service_id = (int)$_POST['service_id'];
                
                // Check if service is being used in orders
                $stmt = $db->prepare("SELECT COUNT(*) as total FROM orders WHERE service_id = ?");
                $stmt->execute([$service_id]);
                $order_count = $stmt->fetch()['total'];
                
                if ($order_count > 0) {
                    $error = 'Layanan tidak dapat dihapus karena sudah digunakan dalam ' . $order_count . ' pesanan';
                } else {
                    $stmt = $db->prepare("DELETE FROM services WHERE id = ? AND partner_id = ?");
                    if ($stmt->execute([$service_id, $partner_id])) {
                        $success = 'Layanan berhasil dihapus';
                    } else {
                        $error = 'Gagal menghapus layanan';
                    }
                }
                break;
            case 'update_delivery_fee':
                $fee = (float)($_POST['delivery_fee'] ?? 0);
                if ($fee < 0) { $error = 'Biaya antar jemput tidak valid'; }
                else {
                    $stmt = $db->prepare("UPDATE laundry_partners SET delivery_fee = ? WHERE id = ?");
                    if ($stmt->execute([$fee, $partner_id])) { $success = 'Biaya antar jemput diperbarui'; $partner['delivery_fee']=$fee; }
                    else { $error = 'Gagal memperbarui biaya antar jemput'; }
                }
                break;
            case 'add_promo':
                $code = strtoupper(trim($_POST['code'] ?? ''));
                $discount_type = $_POST['discount_type'] === 'percent' ? 'percent' : 'amount';
                $discount_value = (float)($_POST['discount_value'] ?? 0);
                $min_order = (float)($_POST['min_order'] ?? 0);
                $max_uses = strlen($_POST['max_uses'] ?? '') ? (int)$_POST['max_uses'] : null;
                $expires_at = trim($_POST['expires_at'] ?? '') ?: null;
                if (!$code || $discount_value <= 0) { $error = 'Kode dan nilai diskon harus diisi'; break; }
                $stmt = $db->prepare("INSERT INTO partner_promotions (partner_id, code, discount_type, discount_value, min_order, max_uses, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$partner_id, $code, $discount_type, $discount_value, $min_order, $max_uses, $expires_at])) { $success='Kode promo ditambahkan'; }
                else { $error='Gagal menambahkan kode promo (duplikat?)'; }
                break;
            case 'delete_promo':
                $promo_id = (int)($_POST['promo_id'] ?? 0);
                $stmt = $db->prepare("DELETE FROM partner_promotions WHERE id = ? AND partner_id = ?");
                if ($stmt->execute([$promo_id, $partner_id])) { $success='Kode promo dihapus'; } else { $error='Gagal menghapus kode promo'; }
                break;
        }
    }
}

// Get all services
$stmt = $db->prepare("
    SELECT s.*,
           COUNT(o.id) as order_count,
           SUM(CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END) as completed_count
    FROM services s
    LEFT JOIN orders o ON s.id = o.service_id
    WHERE s.partner_id = ?
    GROUP BY s.id
    ORDER BY s.is_active DESC, s.id DESC
");
$stmt->execute([$partner_id]);
$services = $stmt->fetchAll();

// Get statistics
$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(is_active = TRUE) as active FROM services WHERE partner_id = ?");
$stmt->execute([$partner_id]);
$stats = $stmt->fetch();

// Load promos
$promosStmt = $db->prepare("SELECT * FROM partner_promotions WHERE partner_id = ? ORDER BY created_at DESC");
$promosStmt->execute([$partner_id]);
$promos = $promosStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Layanan - <?php echo htmlspecialchars($partner['laundry_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .card { border-radius: 12px; }
        .btn { border-radius: 12px; cursor: pointer; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <a href="dashboard.php" class="mr-4">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">Kelola Layanan</h1>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($partner['laundry_name']); ?></p>
                    </div>
                </div>
                <button onclick="showAddModal()" class="bg-purple-500 hover:bg-purple-600 text-white font-semibold px-6 py-2 btn transition duration-200">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Tambah Layanan
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- Services List -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <?php foreach ($services as $service): ?>
                <div class="bg-white card shadow-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($service['service_name']); ?></h3>
                        <span class="px-3 py-1 bg-<?php echo $service['is_active'] ? 'green' : 'red'; ?>-100 text-<?php echo $service['is_active'] ? 'green' : 'red'; ?>-700 text-xs font-semibold rounded-full">
                            <?php echo $service['is_active'] ? t('active') : t('inactive'); ?>
                        </span>
                    </div>
                    <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($service['description']); ?></p>
                    <p class="text-sm text-gray-600 mb-2"><strong><?php echo t('name_en'); ?>:</strong> <?php echo htmlspecialchars($service['service_name_en'] ?? 'Not Set'); ?></p>
                    <p class="font-semibold text-purple-600 mb-4"><?php echo formatRupiah($service['price_per_kg']); ?>/kg</p>
                    <div class="flex space-x-2">
                        <button onclick="editService(<?php echo htmlspecialchars(json_encode($service), ENT_QUOTES, 'UTF-8'); ?>)" 
                            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 btn text-sm transition duration-200 cursor-pointer">
                            <?php echo t('edit'); ?>
                        </button>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                            <input type="hidden" name="is_active" value="<?php echo $service['is_active'] ? 0 : 1; ?>">
                            <button type="submit" 
                                class="bg-<?php echo $service['is_active'] ? 'yellow' : 'green'; ?>-500 hover:bg-<?php echo $service['is_active'] ? 'yellow' : 'green'; ?>-600 text-white px-4 py-2 btn text-sm transition duration-200 cursor-pointer">
                                <?php echo $service['is_active'] ? t('deactivate') : t('activate'); ?>
                            </button>
                        </form>
                        <button onclick="confirmDelete(<?php echo $service['id']; ?>, '<?php echo addslashes($service['service_name']); ?>', <?php echo $service['order_count']; ?>)" 
                            class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 btn text-sm transition duration-200 cursor-pointer">
                            <?php echo t('delete'); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Service Modal (Add/Edit) -->
        <div id="serviceModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg max-w-md w-full p-6">
                <h3 id="modalTitle" class="text-xl font-bold text-gray-800 mb-4">Tambah Layanan Baru</h3>
                <form id="serviceForm" method="POST">
                    <input type="hidden" name="action" id="formAction" value="add_service">
                    <input type="hidden" name="service_id" id="serviceId" value="">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2">Nama Layanan *</label>
                            <input type="text" name="service_name" id="serviceName" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                placeholder="Cuci Kering">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-semibold mb-2">Deskripsi</label>
                        <textarea name="description" id="serviceDescription" rows="3"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            placeholder="Jelaskan layanan Anda..."></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2">Harga per kg (Rp) *</label>
                            <input type="number" name="price_per_kg" id="pricePerKg" min="1000" step="500" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                placeholder="5000">
                            <p class="text-xs text-gray-500 mt-1">Minimal Rp 1.000</p>
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2">Estimasi Waktu (jam) *</label>
                            <input type="number" name="estimated_hours" id="estimatedHours" min="1" max="168" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                placeholder="24">
                            <p class="text-xs text-gray-500 mt-1">1 - 168 jam (7 hari)</p>
                        </div>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <p class="text-sm text-gray-700">
                            <strong>💡 Tips:</strong> Pastikan harga kompetitif dan estimasi waktu realistis untuk meningkatkan kepuasan customer.
                        </p>
                    </div>

                    <div class="flex space-x-3">
                        <button type="button" onclick="closeServiceModal()" 
                            class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 btn transition duration-200 cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" 
                            class="flex-1 bg-purple-500 hover:bg-purple-600 text-white font-semibold py-3 btn transition duration-200 cursor-pointer">
                            <span id="submitButtonText">Tambah Layanan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg max-w-md w-full p-6">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center">
                        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                </div>

                <h3 class="text-xl font-bold text-gray-800 text-center mb-2">Hapus Layanan?</h3>
                <p id="deleteMessage" class="text-gray-600 text-center mb-6"></p>

                <form id="deleteForm" method="POST" action="">
                    <input type="hidden" name="action" value="delete_service">
                    <input type="hidden" name="service_id" id="deleteServiceId" value="">
                    
                    <div class="flex space-x-3">
                        <button type="button" onclick="closeDeleteModal()" 
                            class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 btn transition duration-200 cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" 
                            class="flex-1 bg-red-500 hover:bg-red-600 text-white font-semibold py-3 btn transition duration-200 cursor-pointer">
                            Hapus Layanan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function showAddModal() {
                console.log('Opening add modal'); // Debug
                document.getElementById('serviceModal').classList.remove('hidden');
                document.getElementById('modalTitle').textContent = 'Tambah Layanan Baru';
                document.getElementById('submitButtonText').textContent = 'Tambah Layanan';
                document.getElementById('formAction').value = 'add_service';
                document.getElementById('serviceForm').reset();
                document.getElementById('serviceId').value = '';
            }

            function editService(service) {
                console.log('Editing service:', service); // Debug
                document.getElementById('serviceModal').classList.remove('hidden');
                document.getElementById('modalTitle').textContent = 'Edit Layanan';
                document.getElementById('submitButtonText').textContent = 'Simpan Perubahan';
                document.getElementById('formAction').value = 'edit_service';
                document.getElementById('serviceId').value = service.id;
                document.getElementById('serviceName').value = service.service_name;
                document.getElementById('serviceNameEn').value = service.service_name_en || '';
                document.getElementById('serviceDescription').value = service.description || '';
                document.getElementById('pricePerKg').value = service.price_per_kg;
                document.getElementById('estimatedHours').value = service.estimated_hours;
            }

            function closeServiceModal() {
                document.getElementById('serviceModal').classList.add('hidden');
            }

            function confirmDelete(serviceId, serviceName, orderCount) {
                console.log('Confirming delete for ID:', serviceId); // Debug
                document.getElementById('deleteModal').classList.remove('hidden');
                document.getElementById('deleteServiceId').value = serviceId;
                
                let message = `Apakah Anda yakin ingin menghapus layanan "${serviceName}"?`;
                if (orderCount > 0) {
                    message = `Layanan "${serviceName}" telah digunakan dalam ${orderCount} pesanan dan tidak dapat dihapus.`;
                    document.querySelector('#deleteForm button[type="submit"]').disabled = true;
                    document.querySelector('#deleteForm button[type="submit"]').classList.add('opacity-50', 'cursor-not-allowed');
                } else {
                    document.querySelector('#deleteForm button[type="submit"]').disabled = false;
                    document.querySelector('#deleteForm button[type="submit"]').classList.remove('opacity-50', 'cursor-not-allowed');
                }
                
                document.getElementById('deleteMessage').textContent = message;
            }

            function closeDeleteModal() {
                document.getElementById('deleteModal').classList.add('hidden');
            }

            // Close modals on outside click
            document.getElementById('serviceModal').addEventListener('click', function(e) {
                if (e.target === this) closeServiceModal();
            });

            document.getElementById('deleteModal').addEventListener('click', function(e) {
                if (e.target === this) closeDeleteModal();
            });
        </script>
</body>
</html>