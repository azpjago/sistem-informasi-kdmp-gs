<?php
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Fungsi untuk mendapatkan semua produk yang tersedia
function getAllProdukTersedia($conn)
{
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

// Fungsi untuk mendapatkan produk dengan stok menipis (untuk statistik)
function getProdukStokMenipis($conn, $threshold = 10)
{
    $query = "
        SELECT 
            p.id_produk,
            p.nama_produk,
            ir.jumlah_tersedia as stok
        FROM produk p
        INNER JOIN inventory_ready ir ON p.id_inventory = ir.id_inventory
        WHERE ir.jumlah_tersedia <= ? AND ir.jumlah_tersedia > 0
        ORDER BY ir.jumlah_tersedia ASC
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $threshold);
    $stmt->execute();
    return $stmt->get_result();
}

// Fungsi untuk mendapatkan produk habis (untuk statistik)
function getProdukStokHabis($conn)
{
    $query = "
        SELECT 
            p.id_produk,
            p.nama_produk
        FROM produk p
        INNER JOIN inventory_ready ir ON p.id_inventory = ir.id_inventory
        WHERE ir.jumlah_tersedia = 0
        ORDER BY p.nama_produk ASC
    ";
    $result = $conn->query($query);
    return $result;
}

// Fungsi untuk mendapatkan semua anggota dengan nomor WA
function getAnggotaWithWA($conn)
{
    $query = "
        SELECT nama, no_hp 
        FROM anggota 
        WHERE no_hp IS NOT NULL AND no_hp != ''
        ORDER BY nama ASC
    ";
    $result = $conn->query($query);
    return $result;
}

// Fungsi untuk format pesan broadcast
function formatPesanBroadcast($produkTersedia)
{
    $date = date('d/m/Y');
    $pesan = "🛍️ *DAFTAR PRODUK TERSEDIA KOPERASI*\n";
    $pesan .= "Tanggal: $date\n\n";

    if ($produkTersedia->num_rows > 0) {
        while ($produk = $produkTersedia->fetch_assoc()) {
            $pesan .= "✅ *{$produk['nama_produk']}*\n";
            $pesan .= "   Stok: {$produk['stok']} {$produk['satuan']}\n";
            $pesan .= "   Harga: Rp " . number_format($produk['harga'], 0, ',', '.') . "\n";

            if (!empty($produk['deskripsi'])) {
                $pesan .= "   Deskripsi: {$produk['deskripsi']}\n";
            }

            $pesan .= "\n";
        }

        $pesan .= "📞 *Info & Pemesanan:*\n";
        $pesan .= "Hubungi admin koperasi untuk pemesanan.\n\n";
        $pesan .= "Terima kasih 🙏";
    } else {
        $pesan .= "❌ *Tidak ada produk yang tersedia saat ini.*\n";
        $pesan .= "Silakan hubungi admin untuk informasi lebih lanjut.";
    }

    return $pesan;
}

// Proses form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'broadcast_stok') {
        $custom_message = trim($_POST['custom_message'] ?? '');

        // Get data
        $produkTersedia = getAllProdukTersedia($conn);
        $anggota = getAnggotaWithWA($conn);

        if ($produkTersedia->num_rows === 0) {
            $response = [
                'status' => 'info',
                'message' => 'Tidak ada produk yang tersedia untuk di-broadcast.'
            ];
        } else {
            // Format pesan
            $pesan = formatPesanBroadcast($produkTersedia);

            // Tambahkan custom message jika ada
            if (!empty($custom_message)) {
                $pesan .= "\n\n📝 *Pesan Tambahan:*\n" . $custom_message;
            }

            // Simpan pesan untuk ditampilkan
            $_SESSION['broadcast_message'] = $pesan;
            $_SESSION['total_anggota'] = $anggota->num_rows;

            $response = [
                'status' => 'success',
                'message' => 'Pesan broadcast berhasil dibuat!',
                'preview' => $pesan,
                'total_anggota' => $anggota->num_rows,
                'total_produk' => $produkTersedia->num_rows
            ];
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    if ($action === 'send_broadcast') {
        // Implementasi pengiriman WA sebenarnya
        $pesan = $_POST['message'] ?? '';
        $anggota = getAnggotaWithWA($conn);

        $results = [];
        $success_count = 0;
        $failed_count = 0;

        while ($member = $anggota->fetch_assoc()) {
            $phone = $member['no_hp'];
            $nama = $member['nama'];

            // Format nomor telepon (hapus +, spasi, dll)
            $phone_clean = preg_replace('/[^0-9]/', '', $phone);

            if (strlen($phone_clean) >= 10) {
                // Kirim WA (implementasi sesuai API yang digunakan)
                $send_result = sendWhatsAppMessage($phone_clean, $pesan, $nama);

                if ($send_result['success']) {
                    $success_count++;
                    $results[] = "✅ {$nama}: Berhasil";
                } else {
                    $failed_count++;
                    $results[] = "❌ {$nama}: Gagal - " . $send_result['error'];
                }

                // Delay antar pengiriman untuk menghindari spam
                usleep(500000); // 0.5 detik
            } else {
                $failed_count++;
                $results[] = "❌ {$nama}: Nomor tidak valid";
            }
        }

        $response = [
            'status' => 'success',
            'message' => "Broadcast selesai! Berhasil: {$success_count}, Gagal: {$failed_count}",
            'results' => $results
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Fungsi untuk mengirim WA (contoh implementasi)
function sendWhatsAppMessage($phone, $message, $nama)
{
    // Untuk demo/testing tanpa API
    return sendViaDemo($phone, $message, $nama);
}

// Untuk demo/testing tanpa API
function sendViaDemo($phone, $message, $nama)
{
    // Simulasi pengiriman
    sleep(1);

    // 80% success rate untuk demo
    if (rand(1, 100) <= 80) {
        return ['success' => true, 'message_id' => 'demo_' . uniqid()];
    } else {
        return ['success' => false, 'error' => 'Timeout'];
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-4">📢 Broadcast Info Produk</h3>
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
            <div class="card bg-warning bg-opacity-10 border-warning">
                <div class="card-body">
                    <h6 class="card-title text-warning">🔄 Stok Menipis</h6>
                    <h4 class="card-text fw-bold">
                        <?php
                        $stokMenipis = getProdukStokMenipis($conn, 10);
                        echo $stokMenipis->num_rows;
                        ?>
                    </h4>
                    <small class="text-muted">Stok ≤ 10</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger bg-opacity-10 border-danger">
                <div class="card-body">
                    <h6 class="card-title text-danger">❌ Stok Habis</h6>
                    <h4 class="card-text fw-bold">
                        <?php
                        $stokHabis = getProdukStokHabis($conn);
                        echo $stokHabis->num_rows;
                        ?>
                    </h4>
                    <small class="text-muted">Stok = 0</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info bg-opacity-10 border-info">
                <div class="card-body">
                    <h6 class="card-title text-info">👥 Anggota Terdaftar</h6>
                    <h4 class="card-text fw-bold">
                        <?php
                        $anggota = getAnggotaWithWA($conn);
                        echo $anggota->num_rows;
                        ?>
                    </h4>
                    <small class="text-muted">Dengan nomor WhatsApp</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Broadcast -->
    <div class="card">
        <div class="card-header">
            <strong>Buat Pesan Broadcast Produk Tersedia</strong>
        </div>
        <div class="card-body">
            <form id="formBroadcast">
                <input type="hidden" name="action" value="broadcast_stok">
                
                <div class="mb-3">
                    <label for="custom_message" class="form-label">Pesan Tambahan (Opsional)</label>
                    <textarea class="form-control" id="custom_message" name="custom_message" 
                              rows="3" placeholder="Contoh: 'Segera pesan sebelum kehabisan!' atau info promo..."></textarea>
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
                        <i class="fab fa-whatsapp"></i> Kirim Broadcast
                    </button>
                </div>
            </div>

            <!-- Results -->
            <div id="resultsSection" class="mt-4" style="display: none;">
                <hr>
                <h5>Hasil Pengiriman</h5>
                <div id="resultsContent" class="border rounded p-3" style="max-height: 300px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>

    <!-- Daftar Semua Produk Tersedia -->
    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Daftar Semua Produk Tersedia</strong>
            <span class="badge bg-success"><?php echo getAllProdukTersedia($conn)->num_rows; ?> Produk</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-success">
                        <tr>
                            <th>Nama Produk</th>
                            <th>Stok</th>
                            <th>Satuan</th>
                            <th>Harga</th>
                            <th>Deskripsi</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $produkTersedia = getAllProdukTersedia($conn);
                        if ($produkTersedia->num_rows > 0):
                            while ($produk = $produkTersedia->fetch_assoc()):
                                $status = $produk['stok'] <= 5 ? 'warning' : 'success';
                                $status_text = $produk['stok'] <= 5 ? 'Menipis' : 'Tersedia';
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($produk['nama_produk']) ?></td>
                                    <td class="text-center"><?= $produk['stok'] ?></td>
                                    <td class="text-center"><?= htmlspecialchars($produk['satuan']) ?></td>
                                    <td class="text-end">Rp <?= number_format($produk['harga'], 0, ',', '.') ?></td>
                                    <td><?= htmlspecialchars($produk['deskripsi'] ?? '-') ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $status ?>"><?= $status_text ?></span>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">Tidak ada produk yang tersedia</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
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
                $('#previewInfo').text(`Akan dikirim ke ${data.total_anggota} anggota (${data.total_produk} produk tersedia)`);
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
    
    // Kirim broadcast
    $('#btnSendBroadcast').on('click', function() {
        if (!confirm('Kirim broadcast info produk ke semua anggota? Pastikan pesan sudah benar.')) {
            return;
        }
        
        const btn = $(this);
        const originalText = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin"></i> Mengirim...').prop('disabled', true);
        
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
                    $('#resultsContent').append('<div>' + result + '</div>');
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
</script>