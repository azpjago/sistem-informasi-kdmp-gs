<?php
// Query SQL diubah untuk mengambil semua kolom yang relevan dan nama anggota
$sql = "
    SELECT
        p.id, p.id_transaksi, p.tanggal_bayar, p.jenis_simpanan,
        p.jenis_transaksi, p.jumlah, p.metode, p.bukti, p.status, 
        a.no_anggota, a.nama AS nama_anggota
    FROM pembayaran p
    JOIN anggota a ON p.anggota_id = a.id
    ORDER BY p.tanggal_bayar DESC
";
$riwayat = $conn->query($sql);
?>
<style>
        #logTransaksiTable tbody td {
        font-size: 0.8rem; /* Anda bisa ubah nilainya, misal 0.8rem atau 13px */
        vertical-align: middle;
    }
    #logTransaksiTable thead th {
        font-size: 0.9rem; /* Sedikit lebih besar untuk header */
        vertical-align: middle;
    }
</style>
<h3 class="mb-4">💳 Log Semua Transaksi</h3>

<div class="table-responsive">
    <table id="logTransaksiTable" class="table table-striped table-bordered align-middle">
        <thead class="table-dark text-center">
            <tr>
                <th>Tanggal</th>
                <th>ID Transaksi</th>
                <th>No Anggota</th>
                <th>Nama Anggota</th>
                <th>Jenis Simpanan</th>
                <th>Jenis Transaksi</th>
                <th>Jumlah</th>
                <th>Metode</th>
                <th>Bukti</th>
                <th>Status/Keterangan</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($riwayat->num_rows > 0): ?>
                <?php while ($r = $riwayat->fetch_assoc()): ?>
                    <tr>
                        <td class="text-center"><?= date('d/m/Y H:i', strtotime($r['tanggal_bayar'])) ?></td>
                        <td><?= htmlspecialchars($r['id_transaksi']) ?></td>
                        <td><?= htmlspecialchars($r['no_anggota']) ?></td>
                        <td><?= htmlspecialchars($r['nama_anggota']) ?></td>
                        <td class="text-center"><?= htmlspecialchars($r['jenis_simpanan']) ?></td>
                        <td class="text-center">
                            <?php
                            // Membuat tampilan jenis transaksi lebih rapi
                            $jenis_transaksi = htmlspecialchars($r['jenis_transaksi']);
                            if ($jenis_transaksi === 'setor') {
                                echo '<span class="badge bg-success">Setor</span>';
                            } elseif ($jenis_transaksi === 'tarik') {
                                echo '<span class="badge bg-warning text-dark">Tarik</span>';
                            } else {
                                echo '-'; // Untuk Simpanan Pokok & Wajib yang tidak memiliki jenis ini
                            }
                            ?>
                        </td>
                        <td class="text-end fw-bold">
                            <?= "Rp " . number_format($r['jumlah'], 0, ',', '.') ?>
                        </td>
                        <td class="text-center"><?= ucfirst($r['metode']) ?></td>
                    <td class="text-center">
                        <?php if (!empty($r['bukti'])): ?>
                            <a href="<?= htmlspecialchars($r['bukti']) ?>" target="_blank" class="btn btn-info btn-sm">Lihat</a>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary btn-sm upload-bukti-btn" data-bs-toggle="modal"
                                data-bs-target="#modalUploadBukti" data-pembayaran-id="<?= $r['id'] ?>">
                                Upload
                            </button>
                        <?php endif; ?>
                    </td>
                        <td><?= htmlspecialchars($r['status']) ?></td>
                        <td class="text-center">
                            <a href="cetak_bukti.php?id=<?= $r['id'] ?>" target="_blank" class="btn btn-secondary btn-sm"
                                title="Cetak Bukti Transaksi">
                                🖨️
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="11" class="text-center">Belum ada data transaksi.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>