<?php
session_start();
if (!isset($_SESSION['is_logged_in']) || $_SESSION['role'] != 'gudang') {
    exit('Akses ditolak');
}

$conn = new mysqli('localhost', 'root', '', 'kdmpgs');
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

if (isset($_GET['id_so'])) {
    $id_so = intval($_GET['id_so']);
    
    $query_header = "SELECT so.*, p.username as nama_petugas, ap.username as nama_approver
                    FROM stock_opname_header so
                    LEFT JOIN pengurus p ON so.id_petugas = p.id
                    LEFT JOIN pengurus ap ON so.approved_by = ap.id
                    WHERE so.id_so = '$id_so'";
    $result_header = $conn->query($query_header);
    $header = $result_header->fetch_assoc();
    
    $query_detail = "SELECT sod.*, ir.nama_produk, ir.no_batch, ir.satuan_kecil
                    FROM stock_opname_detail sod
                    JOIN inventory_ready ir ON sod.id_inventory = ir.id_inventory
                    WHERE sod.id_so = '$id_so'";
    $result_detail = $conn->query($query_detail);
    ?>
    
    <div class="row">
        <div class="col-md-6">
            <p><strong>No. SO:</strong> <?= $header['no_so'] ?></p>
            <p><strong>Tanggal:</strong> <?= date('d/m/Y', strtotime($header['tanggal_so'])) ?></p>
            <p><strong>Periode:</strong> <?= ucfirst($header['periode_so']) ?></p>
        </div>
        <div class="col-md-6">
            <p><strong>Petugas:</strong> <?= $header['nama_petugas'] ?></p>
            <p><strong>Status:</strong> 
                <span class="badge badge-<?= $header['status'] ?>">
                    <?= strtoupper(str_replace('_', ' ', $header['status'])) ?>
                </span>
            </p>
            <?php if ($header['approved_by']): ?>
                <p><strong>Disetujui oleh:</strong> <?= $header['nama_approver'] ?></p>
                <p><strong>Tanggal Approval:</strong> <?= date('d/m/Y H:i', strtotime($header['approved_at'])) ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="table-responsive mt-3">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Batch</th>
                    <th>Stok Sistem</th>
                    <th>Stok Fisik</th>
                    <th>Selisih</th>
                    <th>Kondisi</th>
                    <th>Analisis</th>
                    <th>Foto</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($detail = $result_detail->fetch_assoc()): ?>
                    <tr>
                        <td><?= $detail['nama_produk'] ?></td>
                        <td><?= $detail['no_batch'] ?></td>
                        <td><?= $detail['stok_sistem'] ?> <?= $detail['satuan_kecil'] ?></td>
                        <td><?= $detail['stok_fisik'] ?> <?= $detail['satuan_kecil'] ?></td>
                        <td>
                            <?php 
                            if ($detail['selisih'] > 0) {
                                echo "<span class='badge bg-success'>+".$detail['selisih']."</span>";
                            } elseif ($detail['selisih'] < 0) {
                                echo "<span class='badge bg-danger'>".$detail['selisih']."</span>";
                            } else {
                                echo "<span class='badge bg-secondary'>0</span>";
                            }
                            ?>
                        </td>
                        <td><?= $detail['status_kondisi'] ?></td>
                        <td><?= $detail['analisis_penyebab'] ?: '-' ?></td>
                        <td>
                            <?php if ($detail['foto_bukti']): ?>
                                <img src="<?= $detail['foto_bukti'] ?>" class="foto-bukti" 
                                     onclick="window.open('<?= $detail['foto_bukti'] ?>', '_blank')">
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($header['catatan']): ?>
        <div class="mt-3">
            <strong>Catatan:</strong>
            <p><?= nl2br($header['catatan']) ?></p>
        </div>
    <?php endif;
}
?>
