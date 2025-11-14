<?php
// File: ajax/get_supplier_produk.php
session_start();
if (!isset($_SESSION['is_logged_in']) || $_SESSION['role'] != 'gudang') {
    header('HTTP/1.1 403 Forbidden');
    exit('Akses ditolak');
}

$conn = new mysqli('localhost', 'root', '', 'kdmpgs');
if ($conn->connect_error) {
    header('HTTP/1.1 500 Internal Server Error');
    exit("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['id_supplier'])) {
    $id_supplier = $conn->real_escape_string($_GET['id_supplier']);

    $query_produk = "SELECT * FROM supplier_produk 
                    WHERE id_supplier = '$id_supplier' 
                    ORDER BY nama_produk";
    $result_produk = $conn->query($query_produk);

    $output = '';
    if ($result_produk && $result_produk->num_rows > 0) {
        while ($row = $result_produk->fetch_assoc()) {
            $output .= '
            <div class="row produk-supplier-item mb-3">
                <!-- INPUT HIDDEN UNTUK ID PRODUK -->
                <input type="hidden" name="id_supplier_produk[]" value="' . $row['id_supplier_produk'] . '">
                
                <div class="col-md-2">
                    <label for="nama_produk[]">Nama Produk</label>
                    <input type="text" class="form-control" name="nama_produk[]" 
                           value="' . htmlspecialchars($row['nama_produk']) . '" 
                           placeholder="Nama Produk" required>
                </div>
                <div class="col-md-2">
                    <label for="harga_beli[]">Harga beli</label>
                    <input type="number" class="form-control" name="harga_beli[]" 
                           value="' . $row['harga_beli'] . '" 
                           placeholder="Harga" min="0" step="0.01" required>
                </div>
                <div class="col-md-2">
                    <label for="satuan[]">Satuan</label>
                    <select class="form-select" name="satuan[]" required>';

            $satuan_options = ['pcs', 'unit', 'karung', 'kg', 'gram', 'liter', 'pack', 'dus', 'karton', 'ton', 'box'];
            foreach ($satuan_options as $option) {
                $selected = $row['satuan_besar'] == $option ? 'selected' : '';
                $output .= "<option value='$option' $selected>" . ucfirst($option) . "</option>";
            }

            $output .= '
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="kategori[]">Kategori</label>
                    <input type="text" class="form-control" name="kategori[]" 
                           value="' . htmlspecialchars($row['kategori'] ?? '') . '" 
                           placeholder="Kategori">
                </div>
                <div class="col-md-2">
                    <label for="min_order[]">Min. Order</label>
                    <input type="number" class="form-control" name="min_order[]" 
                           value="' . ($row['min_order'] ?? 1) . '" 
                           placeholder="Min Order" min="1" required>
                </div>
                <div class="col-md-2">
                    <label for="lead_time[]">Waktu Pengiriman</label>
                    <input type="number" class="form-control" name="lead_time[]" 
                           value="' . ($row['lead_time'] ?? 0) . '" 
                           placeholder="Hari" min="0">
                </div>
                <div class="col-md-2">
                    <label for="status[]">Status</label>
                    <select class="form-select" name="status[]">
                        <option value="active" ' . (($row['status'] ?? 'active') == 'active' ? 'selected' : '') . '>Aktif</option>
                        <option value="inactive" ' . (($row['status'] ?? 'active') == 'inactive' ? 'selected' : '') . '>Nonaktif</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="isi[]">Isi produk</label>
                    <input type="text" class="form-control" name="isi[]" value="' . ($row['isi'] ?? '') . '" placeholder="Isi produk">
                </div>
                <div class="col-md-2">
                    <label for="merk[]">Merk</label>
                    <input type="text" class="form-control" name="merk[]" value="' . ($row['merk'] ?? '') . '" placeholder="Merk">
                </div>
                <div class="col-md-2">
                    <label for="berat[]">Berat</label>
                    <input type="text" class="form-control" name="berat[]" value="' . ($row['berat'] ?? '') . '" placeholder="Berat">
                </div>
                <div class="col-md-2">
                    <label for="volume[]">Volume</label>
                    <input type="text" class="form-control" name="volume[]" value="' . ($row['volume'] ?? '') . '" placeholder="Volume">
                </div>
                <div class="col-md-2">
                    <label for="kemasan[]">Kemasan</label>
                    <input type="text" class="form-control" name="kemasan[]" value="' . ($row['kemasan'] ?? '') . '" placeholder="Kemasan" required>
                </div>
                <div class="col-md-12 mt-2">
                    <label for="notes[]">Catatan</label>
                    <textarea class="form-control" name="notes[]" placeholder="Catatan tambahan" rows="2">' . htmlspecialchars($row['notes'] ?? '') . '</textarea>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-danger btn-sm hapus-produk-supplier">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>';
        }
    } else {
        $output = '
        <div class="row produk-supplier-item mb-3">
            <!-- UNTUK PRODUK BARU -->
            <input type="hidden" name="id_supplier_produk[]" value="new">
            
            <div class="col-md-2">
                <label for="nama_produk[]">Nama Produk</label>
                <input type="text" class="form-control" name="nama_produk[]" placeholder="Nama Produk" required>
            </div>
            <div class="col-md-2">
                <label for="harga_beli[]">Harga beli</label>
                <input type="number" class="form-control" name="harga_beli[]" placeholder="Harga" min="0" step="0.01" required>
            </div>
            <div class="col-md-2">
                <label for="satuan[]">Satuan</label>
                <select class="form-select" name="satuan[]" required>
                    <option value="pcs">Pcs</option>
                    <option value="unit">Unit</option>
                    <option value="kg">Kg</option>
                    <option value="gram">Gram</option>
                    <option value="liter">Liter</option>
                    <option value="pack">Pack</option>
                    <option value="dus">Dus</option>
                    <option value="karton">Karton</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="kategori[]">Kategori</label>
                <input type="text" class="form-control" name="kategori[]" placeholder="Kategori">
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
                </select>
            </div>
            <div class="col-md-2">
                <label for="isi[]">Isi produk</label>
                <input type="text" class="form-control" name="isi[]" placeholder="Isi produk">
            </div>
            <div class="col-md-2">
                <label for="merk[]">Merk</label>
                <input type="text" class="form-control" name="merk[]" placeholder="Merk">
            </div>
            <div class="col-md-2">
                <label for="berat[]">Berat</label>
                <input type="text" class="form-control" name="berat[]" placeholder="Berat">
            </div>
            <div class="col-md-2">
                <label for="volume[]">Volume</label>
                <input type="text" class="form-control" name="volume[]" placeholder="Volume">
            </div>
            <div class="col-md-2">
                <label for="kemasan[]">Kemasan</label>
                <input type="text" class="form-control" name="kemasan[]" placeholder="Kemasan" required>
            </div>
            <div class="col-md-12 mt-2">
                <label for="notes[]">Catatan</label>
                <textarea class="form-control" name="notes[]" placeholder="Catatan tambahan" rows="2"></textarea>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-danger btn-sm hapus-produk-supplier" disabled>
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>';
    }

    echo $output;
} else {
    echo '<div class="alert alert-danger">ID Supplier tidak valid</div>';
}

$conn->close();
?>
