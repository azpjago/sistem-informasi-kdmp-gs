<?php
require_once 'functions/history_log.php';
// TAMBAH SUPPLIER
if (isset($_POST['tambah'])) {
    $nama_supplier = $conn->real_escape_string($_POST['nama_supplier']);
    $alamat = $conn->real_escape_string($_POST['alamat'] ?? '');
    $no_telp = $conn->real_escape_string($_POST['no_telp'] ?? '');
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $jenis_supplier = $conn->real_escape_string($_POST['jenis_supplier'] ?? '');
    $status_supplier = $conn->real_escape_string($_POST['status_supplier'] ?? '');

    // Mulai transaction
    $conn->begin_transaction();

    try {
        $query = "INSERT INTO supplier (nama_supplier, alamat, no_telp, email, jenis_supplier, status_supplier) 
                  VALUES ('$nama_supplier', '$alamat', '$no_telp', '$email', '$jenis_supplier', '$status_supplier')";

        if ($conn->query($query)) {
            $id_supplier = $conn->insert_id;

            $description = "Membuat supplier baru: " . $nama_supplier . " (" . $jenis_supplier . ")";
            log_supplier_activity($id_supplier, 'create', $description, 'gudang');

            // Proses produk supplier
            if (isset($_POST['nama_produk']) && is_array($_POST['nama_produk'])) {
                $nama_produk = $_POST['nama_produk'];
                $harga_beli = $_POST['harga_beli'] ?? [];
                $satuan = $_POST['satuan'] ?? [];
                $kategori = $_POST['kategori'] ?? [];
                $min_order = $_POST['min_order'] ?? [];
                $lead_time = $_POST['lead_time'] ?? [];
                $status = $_POST['status'] ?? [];
                $notes = $_POST['notes'] ?? [];
                $isi = $_POST['isi'] ?? [];
                $merk = $_POST['merk'] ?? [];
                $berat = $_POST['berat'] ?? [];
                $volume = $_POST['volume'] ?? [];
                $kemasan = $_POST['kemasan'] ?? [];

                foreach ($nama_produk as $index => $nama) {
                    if (!empty($nama)) {
                        $nama_clean = $conn->real_escape_string($nama);
                        $harga_clean = $conn->real_escape_string($harga_beli[$index] ?? 0);
                        $satuan_clean = $conn->real_escape_string($satuan[$index] ?? '');
                        $kategori_clean = $conn->real_escape_string($kategori[$index] ?? '');
                        $min_order_clean = $conn->real_escape_string($min_order[$index] ?? 1);
                        $lead_time_clean = $conn->real_escape_string($lead_time[$index] ?? 0);
                        $status_clean = $conn->real_escape_string($status[$index] ?? 'active');
                        $notes_clean = $conn->real_escape_string($notes[$index] ?? '');
                        $isi_clean = $conn->real_escape_string($isi[$index] ?? '');
                        $merk_clean = $conn->real_escape_string($merk[$index] ?? '');
                        $berat_clean = $conn->real_escape_string($berat[$index] ?? '');
                        $volume_clean = $conn->real_escape_string($volume[$index] ?? '');
                        $kemasan_clean = $conn->real_escape_string($kemasan[$index] ?? '');

                        $query_produk = "INSERT INTO supplier_produk 
                                        (id_supplier, nama_produk, harga_beli, satuan_besar, kategori, 
                                        min_order, lead_time, isi, merk, berat, volume, kemasan, status, notes) 
                                        VALUES ('$id_supplier', '$nama_clean', '$harga_clean', 
                                                '$satuan_clean', '$kategori_clean', '$min_order_clean', 
                                                '$lead_time_clean', '$isi_clean','$merk_clean', 
                                                '$berat_clean', '$volume_clean', '$kemasan_clean', '$status_clean', '$notes_clean')";

                        if ($conn->query($query_produk)) {
                            // Ambil ID produk terakhir
                            $id_supplier_produk = $conn->insert_id;
                            $description_produk = "Menambahkan produk: " . $nama_clean . " dengan harga Rp " . number_format($harga_clean, 0, ',', '.') . " per " . $satuan_clean;
                            log_supplier_product_activity($id_supplier_produk, 'create', $description_produk, 'gudang');

                            // Insert juga ke tabel history
                            $query_history = "INSERT INTO supplier_harga_history (id_supplier_produk, harga_beli, tanggal_berlaku) 
                                            VALUES ('$id_supplier_produk', '$harga_clean', NOW())";

                            if (!$conn->query($query_history)) {
                                error_log("Error menyimpan history: " . $conn->error);
                                throw new Exception("Gagal menyimpan history harga: " . $conn->error);
                            }
                        } else {
                            throw new Exception("Gagal menyimpan produk: " . $conn->error);
                        }
                    }
                }
            }

            $conn->commit();
            $_SESSION['success'] = "Supplier berhasil ditambahkan beserta produknya!";
        } else {
            throw new Exception("Error membuat supplier: " . $conn->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        error_log("Error dalam transaksi: " . $e->getMessage());
    }

    header("Location: dashboard.php?page=supplier");
    exit;
}

// EDIT SUPPLIER - PERBAIKAN KHUSUS UNTUK HISTORY HARGA
if (isset($_POST['edit'])) {
    $id_supplier = $conn->real_escape_string($_POST['id_supplier']);
    $nama_supplier = $conn->real_escape_string($_POST['nama_supplier']);
    $alamat = $conn->real_escape_string($_POST['alamat'] ?? '');
    $no_telp = $conn->real_escape_string($_POST['no_telp'] ?? '');
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $jenis_supplier = $conn->real_escape_string($_POST['jenis_supplier'] ?? '');
    $status_supplier = $conn->real_escape_string($_POST['status_supplier'] ?? '');

    // Ambil data lama untuk log
    $query_old = "SELECT * FROM supplier WHERE id_supplier = '$id_supplier'";
    $result_old = $conn->query($query_old);
    $old_data = $result_old->fetch_assoc();

    // Mulai transaction
    $conn->begin_transaction();

    try {
        $query = "UPDATE supplier SET 
                  nama_supplier = '$nama_supplier', 
                  alamat = '$alamat', 
                  no_telp = '$no_telp', 
                  email = '$email', 
                  jenis_supplier = '$jenis_supplier',
                  status_supplier = '$status_supplier',
                  updated_at = NOW() 
                  WHERE id_supplier = '$id_supplier'";

        if ($conn->query($query)) {
            // Log perubahan data supplier
            $changes = [];
            if ($old_data['nama_supplier'] != $nama_supplier)
                $changes[] = "nama dari '" . $old_data['nama_supplier'] . "' ke '" . $nama_supplier . "'";
            if ($old_data['alamat'] != $alamat)
                $changes[] = "alamat";
            if ($old_data['no_telp'] != $no_telp)
                $changes[] = "no. telepon";
            if ($old_data['email'] != $email)
                $changes[] = "email";
            if ($old_data['jenis_supplier'] != $jenis_supplier)
                $changes[] = "jenis dari '" . $old_data['jenis_supplier'] . "' ke '" . $jenis_supplier . "'";
            if ($old_data['status_supplier'] != $status_supplier)
                $changes[] = "status dari '" . $old_data['status_supplier'] . "' ke '" . $status_supplier . "'";

            if (!empty($changes)) {
                $description = "Mengubah data supplier " . $nama_supplier . ": " . implode(", ", $changes);
                log_supplier_activity($id_supplier, 'update', $description, 'gudang');
            }

            // Update produk yang dijual oleh supplier
            if (isset($_POST['id_supplier_produk']) && is_array($_POST['id_supplier_produk'])) {
                $id_produk_arr = $_POST['id_supplier_produk'];
                $nama_produk = $_POST['nama_produk'];
                $harga_beli = $_POST['harga_beli'] ?? [];
                $satuan = $_POST['satuan'] ?? [];
                $kategori = $_POST['kategori'] ?? [];
                $min_order = $_POST['min_order'] ?? [];
                $lead_time = $_POST['lead_time'] ?? [];
                $status = $_POST['status'] ?? [];
                $isi = $_POST['isi'] ?? [];
                $merk = $_POST['merk'] ?? [];
                $berat = $_POST['berat'] ?? [];
                $volume = $_POST['volume'] ?? [];
                $kemasan = $_POST['kemasan'] ?? [];
                $notes = $_POST['notes'] ?? [];

                foreach ($id_produk_arr as $index => $id_produk) {
                    if (!empty($nama_produk[$index])) {
                        $id_produk_clean = $conn->real_escape_string($id_produk);
                        $nama_clean = $conn->real_escape_string($nama_produk[$index]);
                        $harga_clean = $conn->real_escape_string($harga_beli[$index] ?? 0);
                        $satuan_clean = $conn->real_escape_string($satuan[$index] ?? 'pcs');
                        $kategori_clean = $conn->real_escape_string($kategori[$index] ?? '');
                        $min_order_clean = $conn->real_escape_string($min_order[$index] ?? 1);
                        $lead_time_clean = $conn->real_escape_string($lead_time[$index] ?? 0);
                        $isi_clean = $conn->real_escape_string($isi[$index] ?? '');
                        $merk_clean = $conn->real_escape_string($merk[$index] ?? '');
                        $berat_clean = $conn->real_escape_string($berat[$index] ?? '');
                        $volume_clean = $conn->real_escape_string($volume[$index] ?? '');
                        $kemasan_clean = $conn->real_escape_string($kemasan[$index] ?? '');
                        $notes_clean = $conn->real_escape_string($notes[$index] ?? '');
                        $status_clean = $conn->real_escape_string($status[$index] ?? 'active');

                        // Cek apakah produk sudah ada (bukan produk baru)
                        if (!empty($id_produk_clean) && $id_produk_clean != 'new') {
                            // Ambil data lama produk
                            $query_old_produk = "SELECT * FROM supplier_produk WHERE id_supplier_produk = '$id_produk_clean'";
                            $result_old_produk = $conn->query($query_old_produk);
                            $old_produk = $result_old_produk->fetch_assoc();

                            $old_price = $old_produk['harga_beli'];

                            // Update produk yang sudah ada
                            $query_produk = "UPDATE supplier_produk SET 
                                    nama_produk = '$nama_clean', 
                                    harga_beli = '$harga_clean', 
                                    satuan_besar = '$satuan_clean', 
                                    kategori = '$kategori_clean', 
                                    min_order = '$min_order_clean',
                                    lead_time = '$lead_time_clean', 
                                    isi = '$isi_clean', 
                                    merk = '$merk_clean',
                                    berat = '$berat_clean', 
                                    volume = '$volume_clean', 
                                    kemasan = '$kemasan_clean', 
                                    status = '$status_clean', 
                                    notes = '$notes_clean',
                                    updated_at = NOW()
                                    WHERE id_supplier_produk = '$id_produk_clean'";

                            if ($conn->query($query_produk)) {
                                // Log perubahan produk
                                $produk_changes = [];
                                if ($old_produk['nama_produk'] != $nama_clean)
                                    $produk_changes[] = "nama produk";
                                if ($old_produk['harga_beli'] != $harga_clean)
                                    $produk_changes[] = "harga dari Rp " . number_format($old_produk['harga_beli'], 0, ',', '.') . " ke Rp " . number_format($harga_clean, 0, ',', '.');
                                if ($old_produk['satuan_besar'] != $satuan_clean)
                                    $produk_changes[] = "satuan";
                                if ($old_produk['status'] != $status_clean)
                                    $produk_changes[] = "status dari '" . $old_produk['status'] . "' ke '" . $status_clean . "'";

                                if (!empty($produk_changes)) {
                                    $description_produk = "Mengubah data produk " . $nama_clean . ": " . implode(", ", $produk_changes);
                                    log_supplier_product_activity($id_produk_clean, 'update', $description_produk, 'gudang');
                                }

                                // Jika harga berubah, simpan ke history
                                if ($harga_clean != $old_price) {
                                    log_supplier_price_change($id_produk_clean, $old_price, $harga_clean, 'gudang');

                                    $query_history = "INSERT INTO supplier_harga_history (id_supplier_produk, harga_beli, tanggal_berlaku)
                                                    VALUES ('$id_produk_clean', '$harga_clean', NOW())";

                                    if (!$conn->query($query_history)) {
                                        throw new Exception("Gagal menyimpan history harga: " . $conn->error);
                                    }
                                }
                            } else {
                                throw new Exception("Gagal update produk: " . $conn->error);
                            }
                        } else {
                            // Insert produk baru
                            $query_produk = "INSERT INTO supplier_produk 
                                    (id_supplier, nama_produk, harga_beli, satuan_besar, kategori, 
                                    min_order, lead_time, isi, merk,
                                    berat, volume, kemasan, status, notes) 
                                    VALUES ('$id_supplier', '$nama_clean', '$harga_clean', 
                                            '$satuan_clean', '$kategori_clean', '$min_order_clean',
                                            '$lead_time_clean', '$isi_clean', '$merk_clean',
                                            '$berat_clean', '$volume_clean', 
                                            '$kemasan_clean', '$status_clean', '$notes_clean')";

                            if ($conn->query($query_produk)) {
                                $id_supplier_produk = $conn->insert_id;

                                // Log produk baru
                                $description_produk = "Menambahkan produk baru: " . $nama_clean . " dengan harga Rp " . number_format($harga_clean, 0, ',', '.') . " per " . $satuan_clean;
                                log_supplier_product_activity($id_supplier_produk, 'create', $description_produk, 'gudang');

                                // Simpan ke tabel history harga
                                $query_history = "INSERT INTO supplier_harga_history (id_supplier_produk, harga_beli, tanggal_berlaku)
                                                VALUES ('$id_supplier_produk', '$harga_clean', NOW())";

                                if (!$conn->query($query_history)) {
                                    throw new Exception("Gagal menyimpan history harga: " . $conn->error);
                                }
                            } else {
                                throw new Exception("Gagal menambahkan produk baru: " . $conn->error);
                            }
                        }
                    }
                }
            }

            $conn->commit();
            $_SESSION['success'] = "Supplier berhasil diupdate!";
        } else {
            throw new Exception("Error mengupdate supplier: " . $conn->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        error_log("Error dalam transaksi edit: " . $e->getMessage());
    }

    header("Location: dashboard.php?page=supplier");
    exit;
}

// HAPUS SUPPLIER
if (isset($_GET['hapus'])) {
    $id_supplier = $conn->real_escape_string($_GET['hapus']);

    // Ambil data supplier untuk log
    $query_supplier = "SELECT * FROM supplier WHERE id_supplier = '$id_supplier'";
    $result_supplier = $conn->query($query_supplier);
    $supplier_data = $result_supplier->fetch_assoc();

    // Cek apakah supplier punya relasi dengan barang_masuk_qc
    $check_query = "SELECT COUNT(*) as total FROM barang_masuk_qc WHERE id_supplier = '$id_supplier'";
    $check_result = $conn->query($check_query);
    $check_data = $check_result->fetch_assoc();

    if ($check_data['total'] > 0) {
        $_SESSION['error'] = "Tidak dapat menghapus supplier karena sudah memiliki data barang masuk!";
    } else {
        // Log penghapusan supplier
        $description = "Menghapus supplier: " . $supplier_data['nama_supplier'] . " (" . $supplier_data['jenis_supplier'] . ")";
        log_supplier_activity($id_supplier, 'delete', $description, 'gudang');

        // Hapus relasi produk supplier terlebih dahulu
        $conn->query("DELETE FROM supplier_produk WHERE id_supplier = '$id_supplier'");

        // Hapus supplier
        $query = "DELETE FROM supplier WHERE id_supplier = '$id_supplier'";
        if ($conn->query($query)) {
            $_SESSION['success'] = "Supplier berhasil dihapus!";
        } else {
            $_SESSION['error'] = "Error: " . $conn->error;
        }
    }

    header("Location: dashboard.php?page=supplier");
    exit;
}

?>
<!-- HTML CONTENT TIDAK BERUBAH -->

<head>
    <style>
        .produk-section {
            transition: all 0.3s ease;
        }

        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .produk-supplier-item {
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #f8f9fa;
        }

        .produk-supplier-item:hover {
            background-color: #e9ecef;
        }

        .hapus-produk-supplier {
            margin-top: 32px;
        }
    </style>
</head>
<!-- Konten Halaman Supplier -->
<div class="container-fluid py-4">
    <!-- Notifikasi -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php
    // Tampilkan daftar produk supplier jika parameter lihat_produk ada
    if (isset($_GET['lihat_produk'])) {
        $id_supplier = $conn->real_escape_string($_GET['lihat_produk']);
        $query_supplier = "SELECT * FROM supplier WHERE id_supplier = '$id_supplier'";
        $result_supplier = $conn->query($query_supplier);
        $supplier = $result_supplier->fetch_assoc();

        if ($supplier) {
            include 'supplier_produk.php';
            exit;
        }
    }
    ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">🏭 Manajemen Supplier</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahSupplierModal">
            <i class="fas fa-plus me-2"></i>Tambah Supplier
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tabelSupplier" class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th width="5%">No</th>
                            <th width="15%">Nama Supplier</th>
                            <th width="15%">Alamat</th>
                            <th width="10%">No. Telepon</th>
                            <th width="10%">Email</th>
                            <th width="10%">Jenis</th>
                            <th width="10%">Jumlah Produk</th>
                            <th width="10%">Tanggal Dibuat</th>
                            <th width="10%">Status Supplier</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT s.*, COUNT(sp.id_supplier_produk) as jumlah_produk 
              FROM supplier s 
              LEFT JOIN supplier_produk sp ON s.id_supplier = sp.id_supplier 
              GROUP BY s.id_supplier 
              ORDER BY s.created_at DESC";
                        $result = $conn->query($query);

                        if ($result && $result->num_rows > 0) {
                            $no = 1;
                            while ($row = $result->fetch_assoc()) {
                                echo "
            <tr>
                <td>{$no}</td>
                <td>{$row['nama_supplier']}</td>
                <td>" . (!empty($row['alamat']) ? substr($row['alamat'], 0, 50) . "..." : '-') . "</td>
                <td>" . ($row['no_telp'] ?? '-') . "</td>
                <td>" . ($row['email'] ?? '-') . "</td>
                <td><span class='badge bg-secondary'>" . ($row['jenis_supplier'] ?? '-') . "</span></td>
                <td class='text-center'><span class='badge bg-info'>{$row['jumlah_produk']}</span></td>
                <td>" . date('d/m/Y', strtotime($row['created_at'])) . "</td>
                <td><span class='badge bg-success'>" . ($row['status_supplier'] ?? '-') . "</span></td>
                <td>
					<div class='btn-group btn-group-sm' role='group'>
                    <a href='dashboard.php?page=supplier&lihat_produk={$row['id_supplier']}' class='btn btn-sm btn-success btn-action' title='Lihat Produk'>
                        <i class='fas fa-box'></i>
                    </a>
                    <button class='btn btn-sm btn-info btn-action view-supplier' 
                            data-id='{$row['id_supplier']}'
                            data-nama='{$row['nama_supplier']}'
                            data-alamat='" . ($row['alamat'] ?? '') . "'
                            data-telp='" . ($row['no_telp'] ?? '') . "'
                            data-email='" . ($row['email'] ?? '') . "'
                            data-jenis='" . ($row['jenis_supplier'] ?? '') . "'
                            data-status='" . ($row['status_supplier'] ?? '') . "'>
                        <i class='fas fa-eye'></i>
                    </button>
                    <button class='btn btn-sm btn-warning btn-action edit-supplier' 
                            data-id='{$row['id_supplier']}'
                            data-nama='{$row['nama_supplier']}'
                            data-alamat='" . ($row['alamat'] ?? '') . "'
                            data-telp='" . ($row['no_telp'] ?? '') . "'
                            data-email='" . ($row['email'] ?? '') . "'
                            data-jenis='" . ($row['jenis_supplier'] ?? '') . "'
                            data-status='" . ($row['status_supplier'] ?? '') . "'
                            >
                        <i class='fas fa-edit'></i>
                    </button>
                    <button class='btn btn-sm btn-danger btn-action delete-supplier' 
                            data-id='{$row['id_supplier']}'
                            data-nama='{$row['nama_supplier']}'>
                        <i class='fas fa-trash'></i>
                    </button>
                    </div>
                </td>
            </tr>";
                                $no++;
                            }
                        } else {
                            echo "<tr><td colspan='9' class='text-center py-4'>Tidak ada data supplier</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Supplier -->
<div class="modal fade" id="tambahSupplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Supplier Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="formTambahSupplier">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nama_supplier" class="form-label">Nama Supplier <span
                                    class="text-danger">*</span></label>
                            <input type="text" id="nama_supplier" class="form-control" name="nama_supplier" required
                                maxlength="100">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="jenis_supplier" class="form-label">Tipe Supplier</label>
                            <select id="jenis_supplier" name="jenis_supplier" class="form-select">
                                <option value="sembako">Sembako</option>
                                <option value="lpg">LPG</option>
                                <option value="Pertanian">Pertanian</option>
                                <option value="Elektronik">Elektronik</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-9">
                            <label for="alamat" class="form-label">Alamat</label>
                            <textarea class="form-control" id="alamat" name="alamat" rows="2"
                                maxlength="500"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status_supplier" class="form-label">Status Supplier</label>
                            <select id="status_supplier" name="status_supplier" class="form-select">
                                <option value="active">Aktif</option>
                                <option value="non-active">Non-Aktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">No. Telepon</label>
                            <input type="text" class="form-control" name="no_telp" maxlength="15">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" maxlength="100">
                        </div>
                    </div>

                    <h6 class="mt-4 mb-3">Produk yang Dijual</h6>

                    <div id="produk-supplier-container">
                        <div class="row produk-supplier-item mb-3">
                            <div class="col-md-2">
                                <label for="nama_produk[]" class="form-label">Nama Prouk</label>
                                <input type="text" class="form-control" name="nama_produk[]" placeholder="Nama Produk"
                                    required>
                            </div>
                            <div class="col-md-2">
                                <label for="harga_beli[]" class="form-label">Harga beli</label>
                                <input type="number" class="form-control" name="harga_beli[]" placeholder="Harga"
                                    min="0" step="0.01" required>
                            </div>
                            <div class="col-md-2">
                                <label for="satuan[]" class="form-label">Satuan</label>
                                <select class="form-select" name="satuan[]" required>
                                    <option value="pcs">Pcs</option>
                                    <option value="unit">Unit</option>
                                    <option value="karung">Karung</option>
                                    <option value="kg">Kg</option>
                                    <option value="gram">Gram</option>
                                    <option value="pack">Pack</option>
                                    <option value="liter">Liter</option>
                                    <option value="dus">Dus</option>
                                    <option value="karton">Karton</option>
                                    <option value="ton">Ton</option>
                                    <option value="box">Box</option>
                                    <option value="kerat">Kerat</option>
                                    <option value="kwintal">Kwintal</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="kategori[]" class="form-label">Kategori</label>
                                <select class="form-select" name="kategori[]">
                                    <option value="sembako">Sembako</option>
                                    <option value="lpg">LPG</option>
                                    <option value="pertanian">Pertanian</option>
                                    <option value="elektronik">Elektronik</option>
                                    <option value="lainnya">Lainnya</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="min_order[]" class="form-label">Min. Order</label>
                                <input type="number" class="form-control" name="min_order[]" value="1"
                                    placeholder="Min Order" min="1" required>
                            </div>
                            <div class="col-md-2">
                                <label for="lead_time[]" class="form-label">Waktu Pengiriman</label>
                                <input type="number" class="form-control" name="lead_time[]" value="0"
                                    placeholder="Hari" min="0">
                            </div>
                            <div class="col-md-2">
                                <label for="status[]" class="form-label">Status</label>
                                <select class="form-select" name="status[]">
                                    <option value="active">Aktif</option>
                                    <option value="inactive">Nonaktif</option>
                                    <option value="discontinued">Diskontinyu</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="isi[]" class="form-label">Isi produk</label>
                                <input class='form-control' name="isi[]" type="number" placeholder="Isi produk" required>
                            </div>
                            <div class="col-md-2">
                                <label for="merk[]" class="form-label">Merk</label>
                                <input type="text" class="form-control" name="merk[]" value="" placeholder="Merk"
                                    min="1">
                            </div>
                            <div class="col-md-2">
                                <label for="berat[]" class="form-label">Berat</label>
                                <input type="text" class="form-control" name="berat[]" value="" placeholder="Berat"
                                    min="1">
                            </div>
                            <div class="col-md-2">
                                <label for="volume[]" class="form-label">Volume</label>
                                <input type="text" class="form-control" name="volume[]" value="" placeholder="Volume"
                                    min="1">
                            </div>
                            <div class="col-md-2">
                                <label for="kemasan[]" class="form-label">Kemasan</label>
                                <input type="text" class="form-control" name="kemasan[]" value="" placeholder="Kemasan"
                                    min="1" required>
                            </div>
                            <div class="col-md-12 mt-2">
                                <label for="notes[]" class="form-label"></label>
                                <textarea class="form-control" name="notes[]" placeholder="Catatan tambahan"
                                    rows="2"></textarea>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-primary" id="tambah-produk-supplier">
                        <i class="fas fa-plus me-1"></i>Tambah Produk Lain
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" name="tambah">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Supplier -->
<div class="modal fade" id="editSupplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="id_supplier" id="edit_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Supplier <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_supplier" id="edit_nama" required
                                maxlength="100">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipe Supplier</label>
                            <select name="jenis_supplier" id="edit_jenis" class="form-select">
                                <option value="sembako">Sembako</option>
                                <option value="lpg">LPG</option>
                                <option value="Pertanian">Pertanian</option>
                                <option value="Elektronik">Elektronik</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-9">
                            <label for="alamat" class="form-label">Alamat</label>
                            <textarea class="form-control" id="edit_alamat" name="alamat" rows="2"
                                maxlength="500"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_status" class="form-label">Status Supplier</label>
                            <select id="edit_status" name="status_supplier" class="form-select">
                                <option value="active">Aktif</option>
                                <option value="non-active">Non-Aktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">No. Telepon</label>
                            <input type="text" class="form-control" name="no_telp" id="edit_telp" maxlength="15">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="edit_email" maxlength="100">
                        </div>
                    </div>

                    <h6 class="mt-4 mb-3">Produk yang Dijual</h6>

                    <div id="edit-produk-supplier-container">
                        <!-- Produk akan diisi oleh JavaScript -->
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-primary" id="edit-produk-supplier">
                        <i class="fas fa-plus me-1"></i>Tambah Produk
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" name="edit">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detail Supplier -->
<div class="modal fade" id="detailSupplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Nama Supplier</label>
                    <p id="detail_nama" class="form-control-plaintext"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Alamat</label>
                    <p id="detail_alamat" class="form-control-plaintext"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">No. Telepon</label>
                    <p id="detail_telp" class="form-control-plaintext"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Email</label>
                    <p id="detail_email" class="form-control-plaintext"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Jenis Supplier</label>
                    <p id="detail_jenis" class="form-control-plaintext"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Status Supplier</label>
                    <p id="detail_status" class="form-control-plaintext"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPT TIDAK BERUBAH -->
<script>
    $(document).ready(function () {
        // Inisialisasi DataTables
        if ($('#tabelProduk').length && !$.fn.DataTable.isDataTable('#tabelProduk')) {
            $('#tabelProduk').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                language: {
                    search: "Cari Produk : ",
                    lengthMenu: "Tampilkan _MENU_ data per halaman",
                    info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    zeroRecords: "Tidak ada data yang cocok",
                    infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
                    infoFiltered: "(disaring dari _MAX_ total data)",
                    paginate: { first: "Awal", last: "Akhir", next: "Berikutnya", previous: "Sebelumnya" }
                }
            });
        }
    });
    // Tambah baris produk supplier edit
    $('#edit-produk-supplier').click(function () {
        const newRow = `
    <div class="row produk-supplier-item mb-3">
    <input type="hidden" name="id_supplier_produk[]" value="new">
        <div class="col-md-2">
        <label for="nama_produk[]">Nama Produk</label>
            <input type="text" class="form-control" name="nama_produk[]" placeholder="Nama Produk" required>
        </div>
        <div class="col-md-2">
        <label for="harga_beli[]">Harga Beli</label>
            <input type="number" class="form-control" name="harga_beli[]" placeholder="Harga" min="0" step="0.01" required>
        </div>
        <div class="col-md-2">
        <label for="satuan[]">Satuan</label>
            <select class="form-select" name="satuan[]" required>
                <option value="pcs">Pcs</option>
                    <option value="unit">Unit</option>
                    <option value="karung">Karung</option>
                    <option value="kg">Kg</option>
                    <option value="gram">Gram</option>
                    <option value="pack">Pack</option>
                    <option value="liter">Liter</option>
                    <option value="dus">Dus</option>
                    <option value="karton">Karton</option>
                    <option value="ton">Ton</option>
                    <option value="box">Box</option>
                    <option value="kerat">Kerat</option>
                    <option value="kwintal">Kwintal</option>
            </select>
        </div>
        <div class="col-md-2">
        <label for="kategori[]">Kategori</label>
            <select class="form-select" name="kategori[]">
                <option value="sembako">Sembako</option>
                <option value="lpg">LPG</option>
                <option value="pertanian">Pertanian</option>
                <option value="elektronik">Elektronik</option>
                <option value="lainnya">Lainnya</option>
            </select>
        </div>
        <div class="col-md-2">
        <label for="min_order[]">Min. Order</label>
            <input type="number" class="form-control" name="min_order[]" value="1" placeholder="Min Order" min="1" required>
        </div>
        <div class="col-md-2">
        <label for="lead_time[]">Waktu Pengiriman</label>
            <input type="number" class="form-control" name="lead_time[]" value="0" placeholder="Hari" min="0">
        </div>
        <div class="col-md-2">
        <label for="status[]">Status</label>
            <select class="form-select" name="status[]">
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
                <option value="Discontinued">Diskontinyu</option>
            </select>
        </div>
        <div class="col-md-2">
                                <label for="isi[]" class="form-label">Isi produk</label>
                                <input class='form-control' name='isi[]' type="number" placeholder="Isi produk" required>
                            </div>
                            <div class="col-md-2">
                                <label for="merk[]">Merk</label>
                                <input type="text" class="form-control" name="merk[]" value="" placeholder="Merk" min="1">
                            </div>
                            <div class="col-md-2">
                                <label for="berat[]">Berat</label>
                                <input type="text" class="form-control" name="berat[]" value="" placeholder="Berat" min="1">
                            </div>
                            <div class="col-md-2">
                                <label for="volume[]">Volume</label>
                                <input type="text" class="form-control" name="volume[]" value="" placeholder="Volume" min="1">
                            </div>
                            <div class="col-md-2">
                                <label for="kemasan[]">Kemasan</label>
                                <input type="text" class="form-control" name="kemasan[]" value="" placeholder="Kemasan" min="1" required>
                            </div>
        <div class="col-md-12 mt-2">
        <label for="notes[]">Catatan</label>
            <textarea class="form-control" name="notes[]" placeholder="Catatan tambahan" rows="2"></textarea>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-danger btn-sm hapus-produk-supplier">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>`;

        $('#edit-produk-supplier-container').append(newRow);
        updateHapusButtonState();
    });
    // Tambah baris produk supplier
    $('#tambah-produk-supplier').click(function () {
        const newRow = `
    <div class="row produk-supplier-item mb-3">
        <div class="col-md-2">
        <label for="nama_produk[]">Nama Produk</label>
            <input type="text" class="form-control" name="nama_produk[]" placeholder="Nama Produk" required>
        </div>
        <div class="col-md-2">
        <label for="harga_beli[]">Harga Beli</label>
            <input type="number" class="form-control" name="harga_beli[]" placeholder="Harga" min="0" step="0.01" required>
        </div>
        <div class="col-md-2">
        <label for="satuan[]">Satuan</label>
            <select class="form-select" name="satuan[]" required>
                <option value="pcs">Pcs</option>
                    <option value="unit">Unit</option>
                    <option value="karung">Karung</option>
                    <option value="kg">Kg</option>
                    <option value="gram">Gram</option>
                    <option value="pack">Pack</option>
                    <option value="liter">Liter</option>
                    <option value="dus">Dus</option>
                    <option value="karton">Karton</option>
                    <option value="ton">Ton</option>
                    <option value="box">Box</option>
                    <option value="kerat">Kerat</option>
                    <option value="kwintal">Kwintal</option>
            </select>
        </div>
        <div class="col-md-2">
        <label for="kategori[]">Kategori</label>
            <select class="form-select" name="kategori[]">
                <option value="sembako">Sembako</option>
                <option value="lpg">LPG</option>
                <option value="pertanian">Pertanian</option>
                <option value="elektronik">Elektronik</option>
                <option value="lainnya">Lainnya</option>
            </select>
        </div>
        <div class="col-md-2">
        <label for="min_order[]">Min. Order</label>
            <input type="number" class="form-control" name="min_order[]" value="1" placeholder="Min Order" min="1" required>
        </div>
        <div class="col-md-2">
        <label for="lead_time[]">Waktu Pengiriman</label>
            <input type="number" class="form-control" name="lead_time[]" value="0" placeholder="Hari" min="0">
        </div>
        <div class="col-md-2">
        <label for="status[]">Status</label>
            <select class="form-select" name="status[]">
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
                <option value="discontinued">Diskontinyu</option>
            </select>
        </div>
        <div class="col-md-2">
                                <label for="isi[]" class="form-label">Isi produk</label>
                                <input class='form-control' name='isi[]' type="number" placeholder="Isi produk" required>
                            </div>
                            <div class="col-md-2">
                                <label for="merk[]">Merk</label>
                                <input type="text" class="form-control" name="merk[]" value="" placeholder="Merk" min="1">
                            </div>
                            <div class="col-md-2">
                                <label for="berat[]">Berat</label>
                                <input type="text" class="form-control" name="berat[]" value="" placeholder="Berat" min="1">
                            </div>
                            <div class="col-md-2">
                                <label for="volume[]">Volume</label>
                                <input type="text" class="form-control" name="volume[]" value="" placeholder="Volume" min="1">
                            </div>
                            <div class="col-md-2">
                                <label for="kemasan[]">Kemasan</label>
                                <input type="text" class="form-control" name="kemasan[]" value="" placeholder="Kemasan" min="1" required>
                            </div>
        <div class="col-md-12 mt-2">
        <label for="notes[]">Catatan</label>
            <textarea class="form-control" name="notes[]" placeholder="Catatan tambahan" rows="2"></textarea>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-danger btn-sm hapus-produk-supplier">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>`;

        $('#produk-supplier-container').append(newRow);
        updateHapusButtonState();
    });

    // Hapus baris produk supplier
    $(document).on('click', '.hapus-produk-supplier', function () {
        if ($('.produk-supplier-item').length > 1) {
            $(this).closest('.produk-supplier-item').remove();
        }
        updateHapusButtonState();
    });

    // Update state tombol hapus
    function updateHapusButtonState() {
        $('.hapus-produk-supplier').prop('disabled', $('.produk-supplier-item').length <= 1);
    }

    // Inisialisasi state tombol hapus
    updateHapusButtonState();

    // Handle edit button click - Load produk supplier
    $(document).on('click', '.edit-supplier', function () {
        const id = $(this).data('id');
        $('#edit_id').val(id);
        $('#edit_nama').val($(this).data('nama'));
        $('#edit_alamat').val($(this).data('alamat') || '');
        $('#edit_telp').val($(this).data('telp') || '');
        $('#edit_email').val($(this).data('email') || '');
        $('#edit_jenis').val($(this).data('jenis') || '');
        $('#edit_status').val($(this).data('status_supplier') || 'active');

        // Load produk supplier via AJAX
        $.ajax({
            url: 'pages/ajax/get_supplier_produk.php',
            type: 'GET',
            data: { id_supplier: id },
            success: function (response) {
                $('#edit-produk-supplier-container').html(response);
                updateHapusButtonState();
            },
            error: function (xhr, status, error) {
                console.error("AJAX Error:", status, error);
                $('#edit-produk-supplier-container').html('<div class="alert alert-danger">Gagal memuat data produk</div>');
            }
        });

        $('#editSupplierModal').modal('show');
    });

    // Handle view button click
    $(document).on('click', '.view-supplier', function () {
        $('#detail_nama').text($(this).data('nama'));
        $('#detail_alamat').text($(this).data('alamat') || '-');
        $('#detail_telp').text($(this).data('telp') || '-');
        $('#detail_email').text($(this).data('email') || '-');
        $('#detail_jenis').text($(this).data('jenis') || '-');
        $('#detail_status').text($(this).data('status') || '-');
        $('#detailSupplierModal').modal('show');
    });

    // Handle delete button click
    $(document).on('click', '.delete-supplier', function () {
        const id = $(this).data('id');
        const nama = $(this).data('nama');

        if (confirm(`Apakah Anda yakin ingin menghapus supplier: ${nama}?`)) {
            window.location.href = 'dashboard.php?page=supplier&hapus=' + id;
        }
    });

    // Inisialisasi state tombol hapus
    updateHapusButtonState();

    // Toggle antara pilih produk exist dan tambah produk baru
    //$('#tambahProdukBaruCheck').change(function() {
    //if ($(this).is(':checked')) {
    //$('#pilihProdukExist').hide();
    //$('#tambahProdukBaru').show();
    //} else {
    //$('#pilihProdukExist').show();
    //$('#tambahProdukBaru').hide();
    //}
    //});

    // Tambah baris produk exist
    $('#tambah-produk-exist').click(function () {
        const newRow = `
        <div class="row produk-supplier-item mb-3">
            <div class="col-md-6">
                <select class="form-select" name="produk_exist[]">
                    <option value="">Pilih Produk yang Sudah Ada</option>
                    <?php
                    $query_produk = "SELECT * FROM produk ORDER BY nama_produk";
                    $result_produk = $conn->query($query_produk);
                    while ($produk = $result_produk->fetch_assoc()) {
                        echo "<option value='{$produk['id_produk']}'>{$produk['nama_produk']}</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-4">
                <input type="number" class="form-control" name="harga_beli_exist[]" placeholder="Harga Beli" min="0" step="0.01">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-danger btn-sm hapus-produk-supplier">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>`;

        $('#produk-supplier-container').append(newRow);
        updateHapusButtonState('.hapus-produk-supplier', '.produk-supplier-item');
    });

    // Tambah baris produk baru
    $('#tambah-produk-baru').click(function () {
        const newRow = `
        <div class="row produk-baru-item mb-3">
            <div class="col-md-5">
                <input type="text" class="form-control" name="nama_produk_baru[]" placeholder="Nama Produk Baru">
            </div>
            <div class="col-md-3">
                <input type="number" class="form-control" name="harga_beli_baru[]" placeholder="Harga Beli" min="0" step="0.01">
            </div>
            <div class="col-md-2">
                <select class="form-select" name="satuan_baru[]">
                    <option value="pcs">Pcs</option>
                    <option value="unit">Unit</option>
                    <option value="karung">Karung</option>
                    <option value="kg">Kg</option>
                    <option value="gram">Gram</option>
                    <option value="pack">Pack</option>
                    <option value="liter">Liter</option>
                    <option value="dus">Dus</option>
                    <option value="karton">Karton</option>
                    <option value="ton">Ton</option>
                    <option value="box">Box</option>
                    <option value="kerat">Kerat</option>
                    <option value="kwintal">Kwintal</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-danger btn-sm hapus-produk-baru">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>`;

        $('#produk-baru-container').append(newRow);
        updateHapusButtonState('.hapus-produk-baru', '.produk-baru-item');
    });

    // Hapus baris produk
    $(document).on('click', '.hapus-produk-supplier, .hapus-produk-baru', function () {
        $(this).closest('.row').remove();
        updateHapusButtonState('.hapus-produk-supplier', '.produk-supplier-item');
        updateHapusButtonState('.hapus-produk-baru', '.produk-baru-item');
    });

    // Update state tombol hapus
    function updateHapusButtonState(buttonSelector, itemSelector) {
        $(buttonSelector).prop('disabled', $(itemSelector).length <= 1);
    }

    // Validasi form
    $('#formTambahSupplier').on('submit', function (e) {
        let valid = true;
        let errorMessage = "";

        // Validasi supplier
        const namaSupplier = $('input[name="nama_supplier"]');
        if (namaSupplier.val() === '') {
            valid = false;
            namaSupplier.addClass('is-invalid');
            errorMessage += "- Nama supplier harus diisi\n";
        }

        // Validasi produk
        if ($('#tambahProdukBaruCheck').is(':checked')) {
            // Validasi produk baru
            $('input[name="nama_produk_baru[]"]').each(function (index) {
                if ($(this).val() === '') {
                    valid = false;
                    $(this).addClass('is-invalid');
                    errorMessage += "- Nama produk baru harus diisi untuk baris " + (index + 1) + "\n";
                }
            });
        } else {
            // Validasi produk exist
            $('select[name="produk_exist[]"]').each(function (index) {
                if ($(this).val() === '') {
                    valid = false;
                    $(this).addClass('is-invalid');
                    errorMessage += "- Pilih produk yang sudah ada untuk baris " + (index + 1) + "\n";
                }
            });
        }

        if (!valid) {
            e.preventDefault();
            alert('Harap perbaiki kesalahan berikut:\n' + errorMessage);
            return false;
        }

        return true;
    });

    // Hapus kelas invalid saat input diubah
    $(document).on('change', 'input, select', function () {
        $(this).removeClass('is-invalid');
    });

    // Inisialisasi state tombol hapus
    updateHapusButtonState('.hapus-produk-supplier', '.produk-supplier-item');
    updateHapusButtonState('.hapus-produk-baru', '.produk-baru-item');
</script>
