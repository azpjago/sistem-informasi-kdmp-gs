<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fungsi untuk cek dan set broadcast lock
function setBroadcastLock() {
    $_SESSION['broadcast_lock'] = time();
    return true;
}


function hasBroadcastLock() {
    if (!isset($_SESSION['broadcast_lock'])) {
        return false;
    }
    
    $lock_time = $_SESSION['broadcast_lock'];
    $current_time = time();
    
    // Lock berlaku selama 5 menit
    if (($current_time - $lock_time) < 300) {
        return true;
    }
    
    // Hapus lock jika sudah expired
    unset($_SESSION['broadcast_lock']);
    return false;
}

function clearBroadcastLock() {
    unset($_SESSION['broadcast_lock']);
}

// TAMBAHKAN FUNGSI PRODUK FILTER
function getProdukWithFilter($conn, $filters = []) {
    $min_stok = $filters['min_stok'] ?? 1;
    $kategori = $filters['kategori'] ?? '';
    $limit = $filters['limit'] ?? 50; // Batasi default 50 produk
    
    $query = "
        SELECT 
            p.id_produk,
            p.nama_produk,
            p.keterangan,
            p.gambar,
            p.harga,
            p.kategori,
            ir.jumlah_tersedia as stok,
            ir.satuan_kecil
        FROM produk p
        INNER JOIN inventory_ready ir ON p.id_inventory = ir.id_inventory
        WHERE ir.jumlah_tersedia >= ?
        " . ($kategori ? " AND p.kategori = ?" : "") . "
        ORDER BY p.nama_produk ASC
        LIMIT ?
    ";
    
    $stmt = $conn->prepare($query);
    
    if ($kategori) {
        $stmt->bind_param("isi", $min_stok, $kategori, $limit);
    } else {
        $stmt->bind_param("ii", $min_stok, $limit);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    return $result;
}

// TAMBAHKAN FUNGSI VALIDASI PANJANG PESAN
function validateMessageLength($message) {
    $max_length = 4096; // Batas maksimal pesan WhatsApp
    
    if (strlen($message) > $max_length) {
        return [
            'valid' => false,
            'length' => strlen($message),
            'max_length' => $max_length,
            'exceeded_by' => strlen($message) - $max_length
        ];
    }
    
    return [
        'valid' => true,
        'length' => strlen($message),
        'max_length' => $max_length
    ];
}
// Fungsi untuk mendapatkan semua produk yang tersedia
function getProdukForBroadcast($conn, $filters = []) {
    $min_stok = $filters['min_stok'] ?? 1;
    $kategori = $filters['kategori'] ?? '';
    $limit = $filters['max_produk'] ?? 20;
    
    $query = "
        SELECT 
            p.id_produk,
            p.nama_produk,
            p.keterangan,
            p.gambar,
            p.harga,
            p.kategori,
            ir.jumlah_tersedia as stok,
            ir.satuan_kecil
        FROM produk p
        INNER JOIN inventory_ready ir ON p.id_inventory = ir.id_inventory
        WHERE ir.jumlah_tersedia >= ?
        " . ($kategori ? " AND p.kategori = ?" : "") . "
        ORDER BY ir.jumlah_tersedia ASC, p.nama_produk ASC
        LIMIT ?
    ";
    
    $stmt = $conn->prepare($query);
    
    if ($kategori) {
        $stmt->bind_param("isi", $min_stok, $kategori, $limit);
    } else {
        $stmt->bind_param("ii", $min_stok, $limit);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    return $result;
}


// Fungsi untuk mendapatkan grup WA 
function getGrupWA($conn) {
    // === KONFIGURASI GRUP WA ANDA ===
    $grup_wa = [
        [
            'nama_grup' => 'Anggota KDMP Ganjar Sabar',
            'group_id' => '120363420762315139@g.us' // ID grup
        ]
    ];
    
    return $grup_wa;
}

// Fungsi untuk format pesan broadcast
function formatPesanBroadcast($produkTersedia) {
    $date = date('d/m/Y');
    $pesan = "🛍️ *INFORMASI PRODUK KOPERASI TERBARU*\n";
    $pesan .= "📅 Tanggal: $date\n\n";
    
    if ($produkTersedia->num_rows > 0) {
        $pesan .= "🎯 *PRODUK TERSEDIA SAAT INI:*\n\n";
        
        while ($produk = $produkTersedia->fetch_assoc()) {
            $status_emoji = $produk['stok'] <= 5 ? '⚠️' : '✅';
            $pesan .= "{$status_emoji} *{$produk['nama_produk']}*\n";
            $pesan .= "   📦 Stok: {$produk['stok']} {$produk['satuan_kecil']}\n";
            $pesan .= "   💰 Harga: Rp " . number_format($produk['harga'], 0, ',', '.') . "\n";
            
            if (!empty($produk['keterangan'])) {
                $pesan .= "   📝 {$produk['keterangan']}\n";
            }
            $pesan .= "\n";
        }
        
        $pesan .= "🛒 *CARA PEMESANAN:*\n";
        $pesan .= "1. Langsung ke kantor koperasi\n";
        $pesan .= "2. Hubungi admin\n"; 
        $pesan .= "3. Via WhatsApp dengan format:\n";
        $pesan .= "   *NAMA - PRODUK - JUMLAH*\n\n";
        
        $pesan .= "📞 *KONTAK ADMIN:*\n";
        $pesan .= "Usaha (Hendra Suparman): 085220703417\n";
        $pesan .= "Bendahara (Yeyes Resti): 08978190899\n";
        $pesan .= "Ketua (Purnama): 082117587151\n\n";
        
        $pesan .= "Terima kasih atas perhatiannya 🙏\n";
        $pesan .= "_*Koperasi Desa Merah Putih Bersinar Bersama*_";
    } else {
        $pesan .= "❌ *Saat ini tidak ada produk yang tersedia.*\n";
        $pesan .= "Silakan hubungi admin untuk informasi lebih lanjut.";
    }
    
    return $pesan;
}

// ADD getallproduktersedia function
function getAllProdukTersedia($conn) {
    $query = "
        SELECT 
            p.id_produk,
            p.nama_produk,
            p.keterangan,
            p.gambar,
            p.harga,
            ir.jumlah_tersedia as stok,
            ir.satuan_kecil
        FROM produk p
        INNER JOIN inventory_ready ir ON p.id_inventory = ir.id_inventory
        WHERE ir.jumlah_tersedia > 0
        ORDER BY p.nama_produk ASC
    ";
    $result = $conn->query($query);
    return $result;
}
// Proses form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'broadcast_stok') {
    $custom_message = trim($_POST['custom_message'] ?? '');
    $min_stok = intval($_POST['min_stok'] ?? 1);
    $kategori = trim($_POST['kategori'] ?? '');
    $max_produk = intval($_POST['max_produk'] ?? 20);
    
    // Validasi custom message length
    if (strlen($custom_message) > 500) {
        $response = [
            'status' => 'error',
            'message' => 'Pesan tambahan terlalu panjang. Maksimal 500 karakter.'
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Get data dengan filter
    $filters = [
        'min_stok' => $min_stok,
        'kategori' => $kategori,
        'max_produk' => $max_produk
    ];
    
    $produkTersedia = getProdukForBroadcast($conn, $filters);
    
    $grup_wa = getGrupWA($conn);
    
    if ($produkTersedia->num_rows === 0) {
        $response = [
            'status' => 'info',
            'message' => 'Tidak ada produk yang sesuai dengan filter yang dipilih.'
        ];
    } else {
        // Format pesan
        $pesan = formatPesanBroadcast($produkTersedia);
        
        // Tambahkan custom message jika ada
        if (!empty($custom_message)) {
            $pesan .= "\n\n📢 *PENGUMUMAN:*\n" . $custom_message;
        }
        
        // Validasi panjang pesan total
        $validation = validateMessageLength($pesan);
        if (!$validation['valid']) {
            $response = [
                'status' => 'error',
                'message' => "Pesan terlalu panjang! ({$validation['length']}/{$validation['max_length']} karakter). Kurangi {$validation['exceeded_by']} karakter."
            ];
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        
        $response = [
            'status' => 'success',
            'message' => 'Pesan broadcast berhasil dibuat!',
            'preview' => $pesan,
            'total_grup' => count($grup_wa),
            'total_produk' => $produkTersedia->num_rows,
            'grup_list' => $grup_wa,
            'message_length' => $validation['length'],
            'max_length' => $validation['max_length']
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
   
    // GANTI BAGIAN INI dalam proses send_broadcast
	if ($action === 'send_broadcast') {
    // Cek apakah ada broadcast yang sedang berjalan
		if (hasBroadcastLock()) {
			$response = [
				'status' => 'error',
				'message' => 'Broadcast sedang berjalan. Tunggu 5 menit sebelum mengirim lagi.'
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Set lock
    setBroadcastLock();
    
    $pesan = $_POST['message'] ?? '';
    $grup_wa = getGrupWA($conn);
    
    $results = [];
    $success_count = 0;
    $failed_count = 0;
    
    foreach ($grup_wa as $grup) {
        $group_id = $grup['group_id'];
        $nama_grup = $grup['nama_grup'];
        
        // Kirim ke grup WA menggunakan Fonnte API
        $send_result = sendViaFonnteGroup($group_id, $pesan, $nama_grup);
        
        if ($send_result['success']) {
            $success_count++;
            $results[] = "✅ {$nama_grup}: Berhasil dikirim";
        } else {
            $failed_count++;
            $results[] = "❌ {$nama_grup}: Gagal - " . $send_result['error'];
        }
        
        // Delay antar pengiriman (3 detik untuk lebih aman)
        sleep(3);
    }
        
    
    // Clear lock setelah selesai
    clearBroadcastLock();
    
    $response = [
        'status' => 'success',
        'message' => "Broadcast selesai! Berhasil: {$success_count} grup, Gagal: {$failed_count} grup",
        'results' => $results
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
}

// ================================
// FUNGSI UTAMA: FONNTE API KE GRUP
// ================================

function sendViaFonnteGroup($group_id, $message, $nama_grup) {
    // === KONFIGURASI FONNTE API ===
    $token = "5zo4wWs6ceWmqQjBWjDF"; // Ganti dengan token dari fonnte.com
    
    $url = "https://api.fonnte.com/send";
    
    $data = [
        'target' => $group_id,
        'message' => $message,
        'countryCode' => '62',
        // 'delay' => '2', // Optional: delay antara pesan
        // 'typing' => '5', // Optional: simulate typing
    ];
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30, // Timeout 30 detik
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => [
            "Authorization: $token"
        ],
    ]);
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    
    // Debug logging
    error_log("Fonnte API Response: " . $response);
    error_log("HTTP Code: " . $http_code);
    
    if ($error) {
        return ['success' => false, 'error' => 'CURL Error: ' . $error];
    }
    
    $result = json_decode($response, true);
    
    if ($result && isset($result['status']) && $result['status'] === true) {
        return [
            'success' => true, 
            'message_id' => $result['message_id'] ?? 'unknown',
            'response' => $result
        ];
    } else {
        $error_msg = $result['message'] ?? 'Unknown API error';
        return ['success' => false, 'error' => $error_msg];
    }
}

// Fungsi fallback jika Fonnte gagal
function sendViaDemoFallback($group_id, $message, $nama_grup) {
    sleep(2);
    
    // Simpan ke file sebagai backup
    $filename = "broadcast_backup_" . date('Y-m-d_H-i-s') . ".txt";
    $file_content = "GRUP: $nama_grup\nID: $group_id\n\n$message";
    
    file_put_contents($filename, $file_content);
    
    return [
        'success' => true, 
        'message' => "Pesan disimpan ke file: $filename",
        'file_path' => $filename
    ];
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-4">📢 Broadcast Grup WA (Fonnte API)</h3>
    </div>

    <!-- Info Konfigurasi -->
    <div class="alert alert-warning">
        <h6><i class="fas fa-cog"></i> Konfigurasi Fonnte API</h6>
        <p class="mb-1"><strong>Status:</strong> 
            <?php
            // Test koneksi Fonnte
            $test_token = "5zo4wWs6ceWmqQjBWjDF";
            if ($test_token === "YOUR_FONNTE_TOKEN") {
                echo '<span class="badge bg-danger">Token belum diatur</span>';
            } else {
                echo '<span class="badge bg-success">Token siap</span>';
            }
            ?>
        </p>
        <p class="mb-0"><small>Pastikan Anda sudah memiliki token dari <a href="https://fonnte.com" target="_blank">fonnte.com</a> dan mengganti <code>YOUR_FONNTE_TOKEN</code> di kode.</small></p>
    </div>

    <!-- Card Statistik -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-success bg-opacity-10 border-success">
                <div class="card-body">
                    <h6 class="card-title text-success">📦 Produk Tersedia</h6>
                    <h4 class="card-text fw-bold">
                        <?php 
                        $produkTersedia = getAllProdukTersedia($conn);
                        echo $produkTersedia->num_rows;
                        ?>
                    </h4>
                    <small class="text-muted">Stok > 0</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info bg-opacity-10 border-info">
                <div class="card-body">
                    <h6 class="card-title text-info">👥 Grup WA</h6>
                    <h4 class="card-text fw-bold">
                        <?php 
                        $grup_wa = getGrupWA($conn);
                        echo count($grup_wa);
                        ?>
                    </h4>
                    <small class="text-muted">Grup aktif</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-primary bg-opacity-10 border-primary">
                <div class="card-body">
                    <h6 class="card-title text-primary">⚡ API</h6>
                    <h4 class="card-text fw-bold">Fonnte</h4>
                    <small class="text-muted">Metode pengiriman</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning bg-opacity-10 border-warning">
                <div class="card-body">
                    <h6 class="card-title text-warning">⏱️ Estimasi</h6>
                    <h4 class="card-text fw-bold">
                        <?php 
                        $grup_wa = getGrupWA($conn);
                        echo (count($grup_wa) * 2) . 's';
                        ?>
                    </h4>
                    <small class="text-muted">Waktu pengiriman</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Broadcast -->
    <div class="card">
        <div class="card-header">
            <strong>Broadcast ke Grup WA via Fonnte API</strong>
        </div>
        <div class="card-body">
            <!-- Daftar Grup -->
            <div class="mb-3">
                <label class="form-label"><strong>Grup Tujuan:</strong></label>
                <div class="border rounded p-3 bg-light">
                    <?php
                    $grup_wa = getGrupWA($conn);
                    foreach ($grup_wa as $grup):
                    ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" checked disabled>
                        <label class="form-check-label">
                            <strong><?= htmlspecialchars($grup['nama_grup']) ?></strong>
                            <small class="text-muted">(<?= $grup['group_id'] ?>)</small>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <form id="formBroadcast">
    <input type="hidden" name="action" value="broadcast_stok">
    
    <!-- FILTER PRODUK -->
    <div class="card mb-3">
        <div class="card-header bg-light">
            <strong>🔧 Filter Produk yang Akan Dikirim</strong>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <label for="min_stok" class="form-label">Stok Minimum</label>
                    <input type="number" class="form-control" id="min_stok" name="min_stok" 
                           value="1" min="1" max="100">
                    <small class="text-muted">Hanya produk dengan stok ≥ nilai ini</small>
                </div>
                <div class="col-md-4">
                    <label for="kategori" class="form-label">Kategori</label>
                    <select class="form-select" id="kategori" name="kategori">
                        <option value="">Semua Kategori</option>
                        <option value="Sembako">Sembako</option>
                        <option value="LPG">LPG</option>
                        <option value="Pupuk">Pupuk</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="max_produk" class="form-label">Max Produk</label>
                    <select class="form-select" id="max_produk" name="max_produk">
                        <option value="10">10 Produk</option>
                        <option value="20" selected>20 Produk</option>
                        <option value="30">30 Produk</option>
                        <option value="50">50 Produk</option>
                    </select>
                    <small class="text-muted">Batasi jumlah produk</small>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <label for="custom_message" class="form-label">Pesan Tambahan/Promosi (Opsional)</label>
        <textarea class="form-control" id="custom_message" name="custom_message" 
                  rows="3" placeholder="Contoh: 'Diskon spesial akhir bulan!' atau 'Buruan pesan, stok terbatas!'"></textarea>
        <small class="text-muted">Maksimal 500 karakter</small>
        <div class="form-text">
            <span id="charCount">0</span>/500 karakter
        </div>
    </div>

    <button type="submit" class="btn btn-primary" id="btnPreview">
        <i class="fas fa-eye"></i> Preview Broadcast
    </button>
</form>

            <!-- Preview Pesan -->
            <div id="previewSection" class="mt-4" style="display: none;">
                <hr>
                <h5>Preview Pesan Broadcast</h5>
                <div class="alert alert-info">
                    <div id="previewContent" style="white-space: pre-wrap;"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted" id="previewInfo"></small>
                    <button type="button" class="btn btn-success" id="btnSendBroadcast">
                        <i class="fab fa-whatsapp"></i> Kirim via Fonnte API
                    </button>
                </div>
            </div>

            <!-- Results -->
            <div id="resultsSection" class="mt-4" style="display: none;">
                <hr>
                <h5>Hasil Pengiriman ke Grup</h5>
                <div id="resultsContent" class="border rounded p-3" style="max-height: 300px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
	// Character counter untuk custom message
    $('#custom_message').on('input', function() {
        const length = $(this).val().length;
        $('#charCount').text(length);
        
        if (length > 500) {
            $('#charCount').addClass('text-danger');
        } else {
            $('#charCount').removeClass('text-danger');
        }
    });
	
    // Preview broadcast
    $('#formBroadcast').on('submit', function(e) {
        e.preventDefault();
        
        const btn = $('#btnPreview');
        const originalText = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin"></i> Membuat preview...').prop('disabled', true);
        
        const formData = new FormData(this);
        
        fetch('pages/broadcast_wa.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                $('#previewContent').text(data.preview);
                $('#previewInfo').text(`Akan dikirim ke ${data.total_grup} grup WA via Fonnte API (${data.total_produk} produk tersedia)`);
                $('#previewSection').show();
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan server');
        })
        .finally(() => {
            btn.html(originalText).prop('disabled', false);
        });
    });
    
    // Kirim broadcast ke grup via Fonnte API
    $('#btnSendBroadcast').on('click', function() {
        if (!confirm('Kirim broadcast ke semua grup WA menggunakan Fonnte API?')) {
            return;
        }
        
        const btn = $(this);
        const originalText = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin"></i> Mengirim via API...').prop('disabled', true);
        
        const message = $('#previewContent').text();
        
        const formData = new FormData();
        formData.append('action', 'send_broadcast');
        formData.append('message', message);
        
        fetch('pages/broadcast_wa.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            $('#resultsContent').html('');
            if (data.results) {
                data.results.forEach(result => {
                    $('#resultsContent').append('<div class="mb-1">' + result + '</div>');
                });
            }
            $('#resultsSection').show();
            alert(data.message);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat mengirim broadcast');
        })
        .finally(() => {
            btn.html(originalText).prop('disabled', false);
        });
    });
});

});

</script>
