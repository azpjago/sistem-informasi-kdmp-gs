<?php
$id_supplier = $conn->real_escape_string($_GET['lihat_produk']);
$query_supplier = "SELECT * FROM supplier WHERE id_supplier = '$id_supplier'";
$result_supplier = $conn->query($query_supplier);
$supplier = $result_supplier->fetch_assoc();

if (!$supplier) {
    header("Location: dashboard.php?page=supplier");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produk Supplier</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .nav-tabs .nav-link {
            color: #6c757d;
            font-weight: 500;
            padding: 0.75rem 1.25rem;
        }

        .nav-tabs .nav-link.active {
            color: #0d6efd;
            font-weight: 600;
            border-bottom: 3px solid #0d6efd;
        }

        .nav-tabs .nav-link:hover:not(.active) {
            color: #495057;
            background-color: #f8f9fa;
        }
        #riwayat-tab {
        color: #0059ffff  !important; /* teks jadi hitam */
        }

        #riwayat-tab i {
            color: #0059ffff  !important; /* icon jadi hitam */
        }
        #produk-tab {
        color: #0059ffff  !important; /* teks jadi hitam */
        }

        #produk-tab i {
            color: #0059ffff !important; /* icon jadi hitam */
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .chart-container {
            height: 100px;
            width: 100%;
        }

        .price-change-up {
            color: #28a745;
            font-weight: 600;
        }

        .price-change-down {
            color: #dc3545;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0">Produk Supplier: <?= $supplier['nama_supplier'] ?></h2>
                <p class="text-muted"><?= $supplier['jenis_supplier'] ?> - <?= $supplier['alamat'] ?></p>
            </div>
            <div>
                <a href="dashboard.php?page=supplier" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left me-1"></i>Kembali
                </a>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahProdukSupplierModal">
                    <i class="fas fa-plus me-2"></i>Tambah Produk
                </button>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <!-- Tab Navigation -->
                <ul class="nav nav-tabs mb-4" id="supplierTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="produk-tab" data-bs-toggle="tab" data-bs-target="#produk"
                            type="button" role="tab" aria-controls="produk" aria-selected="true">
                            <i class="fas fa-box me-1"></i>Produk Supplier
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="riwayat-tab" data-bs-toggle="tab" data-bs-target="#riwayat"
                            type="button" role="tab" aria-controls="riwayat" aria-selected="false">
                            <i class="fas fa-history me-1"></i>Riwayat Harga
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="supplierTabsContent">
                    <!-- Tab Produk -->
                    <div class="tab-pane fade show active" id="produk" role="tabpanel" aria-labelledby="produk-tab">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Nama Produk</th>
                                        <th>Kategori</th>
                                        <th>Harga Beli</th>
                                        <th>Satuan</th>
                                        <th>Tanggal Ditambahkan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $query_produk = "SELECT * FROM supplier_produk 
                                               WHERE id_supplier = '$id_supplier' 
                                               ORDER BY nama_produk";
                                    $result_produk = $conn->query($query_produk);

                                    if ($result_produk->num_rows > 0) {
                                        while ($row = $result_produk->fetch_assoc()) {
                                            echo "
                                        <tr>
                                            <td>{$row['nama_produk']}</td>
                                            <td>" . ($row['kategori'] ?: '-') . "</td>
                                            <td>Rp " . number_format($row['harga_beli'], 0, ',', '.') . "</td>
                                            <td>{$row['satuan_besar']}</td>
                                            <td>" . date('d/m/Y H:i', strtotime($row['updated_at'])) . "</td>
                                            <td>
                                                <a href='dashboard.php?page=supplier&hapus_produk_supplier={$row['id_supplier_produk']}&id_supplier={$id_supplier}' 
                                                   class='btn btn-sm btn-danger' 
                                                   onclick='return confirm(\"Hapus produk ini dari supplier?\")'>
                                                    <i class='fas fa-trash'></i>
                                                </a>
                                            </td>
                                        </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='6' class='text-center py-4'>Belum ada produk untuk supplier ini</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
<!-- Tab Riwayat Harga -->
<div class="tab-pane fade" id="riwayat" role="tabpanel" aria-labelledby="riwayat-tab">
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th width="20%">Nama Produk</th>
                    <th width="10%">Supplier</th>
                    <th width="10%">Harga Beli</th>
                    <th width="20%">Riwayat (3 Perubahan Terakhir)</th>
                    <th width="10%">Tanggal Berlaku</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Query untuk mendapatkan produk supplier beserta riwayat harganya
                $query_products = "SELECT sp.*, s.nama_supplier 
                                   FROM supplier_produk sp 
                                   JOIN supplier s ON sp.id_supplier = s.id_supplier 
                                   WHERE sp.id_supplier = '$id_supplier' 
                                   ORDER BY sp.nama_produk";
                $result_products = $conn->query($query_products);

                $chartData = [];

                if ($result_products && $result_products->num_rows > 0) {
                    while ($product = $result_products->fetch_assoc()) {
                        $id_sp = $product['id_supplier_produk'];

                        // Ambil 3 riwayat harga terakhir untuk produk ini
                        $history_query = "SELECT harga_beli, tanggal_berlaku 
                                          FROM supplier_harga_history 
                                          WHERE id_supplier_produk = '$id_sp'
                                          ORDER BY tanggal_berlaku DESC
                                          LIMIT 3";
                        $history_result = $conn->query($history_query);

                        $hargaArr = [];
                        $tanggalArr = [];
                        $history_list = [];

                        if ($history_result && $history_result->num_rows > 0) {
                            while ($h = $history_result->fetch_assoc()) {
                                $hargaArr[] = $h['harga_beli'];
                                $tanggalArr[] = date('d/m H:i', strtotime($h['tanggal_berlaku']));
                                $history_list[] = [
                                    'harga' => $h['harga_beli'],
                                    'tanggal' => date('d/m/Y H:i', strtotime($h['tanggal_berlaku']))
                                ];
                            }
                        } else {
                            // Jika tidak ada riwayat, gunakan harga saat ini
                            $hargaArr[] = $product['harga_beli'];
                            $tanggalArr[] = date('d/m H:i');
                            $history_list[] = [
                                'harga' => $product['harga_beli'],
                                'tanggal' => date('d/m/Y H:i')
                            ];
                        }

                        // Pastikan kita selalu memiliki 3 data untuk grafik
                        while (count($hargaArr) < 3) {
                            array_unshift($hargaArr, null);
                            array_unshift($tanggalArr, '');
                        }

                        // Simpan data untuk chart
                        $chartData[$id_sp] = [
                            'labels' => array_reverse($tanggalArr),
                            'data' => array_reverse($hargaArr)
                        ];

                        echo "
                        <tr>
                            <td>{$product['nama_produk']}</td>
                            <td>{$product['nama_supplier']}</td>
                            <td>Rp " . number_format($product['harga_beli'], 0, ',', '.') . "</td>
                            <td>
                                <div class='chart-container'>
                                    <canvas id='chart_{$id_sp}' height='100'></canvas>
                                </div>
                            </td>
                            <td>" . (!empty($history_list) ? $history_list[0]['tanggal'] : '-') . "</td>
                        </tr>";
                    }
                } else {
                    echo "<tr><td colspan='5' class='text-center py-4'>Belum ada produk untuk supplier ini</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
        <!-- Modal Tambah Produk ke Supplier -->
        <div class="modal fade" id="tambahProdukSupplierModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Produk ke Supplier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="id_supplier" value="<?= $id_supplier ?>">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Nama Produk <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nama_produk" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Harga Beli <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="harga_beli" min="0" step="0.01"
                                        required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Satuan <span class="text-danger">*</span></label>
                                    <select class="form-select" name="satuan" required>
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
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Kategori (opsional)</label>
                                <input type="text" class="form-control" name="kategori"
                                    placeholder="Contoh: Sembako, LPG, dll">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary" name="tambah_produk_supplier">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const chartData = <?php echo json_encode($chartData); ?>;

            Object.keys(chartData).forEach(id_sp => {
                const ctx = document.getElementById("chart_" + id_sp).getContext("2d");
                new Chart(ctx, {
                    type: "bar",
                    data: {
                        labels: chartData[id_sp].labels,
                        datasets: [{
                            label: "Harga",
                            data: chartData[id_sp].data,
                            backgroundColor: [
                                "rgba(54, 162, 235, 0.7)",
                                "rgba(255, 159, 64, 0.7)",
                                "rgba(75, 192, 192, 0.7)"
                            ],
                            borderColor: [
                                "rgba(54, 162, 235, 1)",
                                "rgba(255, 159, 64, 1)",
                                "rgba(75, 192, 192, 1)"
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return "Rp " + context.raw.toLocaleString("id-ID");
                                    }
                                }
                            }
                        },
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                display: false
                            },
                            y: {
                                beginAtZero: false,
                                display: false
                            }
                        }
                    }
                });
            });
        });
    </script>
</body>

</html>
