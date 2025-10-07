<?php
/**
 * Function untuk mengambil data saldo anggota
 * @param int $id_anggota ID anggota
 * @return array Data saldo anggota
 */
function getSaldoData($id_anggota)
{
    global $conn;

    try {
        // Validasi input
        if (empty($id_anggota) || !is_numeric($id_anggota)) {
            return [
                'success' => false,
                'message' => 'ID anggota tidak valid'
            ];
        }

        $saldo_data = [
            'id_anggota' => $id_anggota,
            'simpanan_pokok' => 0,
            'simpanan_wajib' => 0,
            'simpanan_sukarela' => 0,
            'total_simpanan' => 0,
            'total_pinjaman' => 0,
            'total_cicilan_dibayar' => 0,
            'sisa_pinjaman' => 0,
            'saldo_bersih' => 0,
            'last_update' => date('Y-m-d H:i:s')
        ];

        // 1. Hitung Simpanan Pokok
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(jumlah), 0) as total 
            FROM pembayaran 
            WHERE id_anggota = ? 
            AND (status_bayar = 'Lunas' OR status = 'Lunas')
            AND jenis_transaksi = 'setor'
            AND jenis_simpanan = 'Simpanan Pokok'
        ");
        $stmt->bind_param("i", $id_anggota);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $saldo_data['simpanan_pokok'] = (float) $data['total'];

        // 2. Hitung Simpanan Wajib
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(jumlah), 0) as total 
            FROM pembayaran 
            WHERE id_anggota = ? 
            AND (status_bayar = 'Lunas' OR status = 'Lunas')
            AND jenis_transaksi = 'setor'
            AND jenis_simpanan = 'Simpanan Wajib'
        ");
        $stmt->bind_param("i", $id_anggota);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $saldo_data['simpanan_wajib'] = (float) $data['total'];

        // 3. Hitung Simpanan Sukarela (Setor - Tarik)
        $stmt = $conn->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) as total_setor,
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) as total_tarik
            FROM pembayaran 
            WHERE id_anggota = ? 
            AND (status_bayar = 'Lunas' OR status = 'Lunas')
            AND jenis_simpanan = 'Simpanan Sukarela'
        ");
        $stmt->bind_param("i", $id_anggota);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $saldo_data['simpanan_sukarela'] = (float) $data['total_setor'] - (float) $data['total_tarik'];

        // 4. Total Simpanan
        $saldo_data['total_simpanan'] = $saldo_data['simpanan_pokok'] +
            $saldo_data['simpanan_wajib'] +
            $saldo_data['simpanan_sukarela'];

        // 5. Hitung Total Pinjaman yang Disetujui
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(jumlah_pinjaman), 0) as total 
            FROM pinjaman 
            WHERE id_anggota = ? 
            AND status = 'approved'
        ");
        $stmt->bind_param("i", $id_anggota);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $saldo_data['total_pinjaman'] = (float) $data['total'];

        // 6. Hitung Total Cicilan yang Sudah Dibayar
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(c.jumlah_bayar), 0) as total_bayar
            FROM cicilan c
            INNER JOIN pinjaman p ON c.id_pinjaman = p.id_pinjaman
            WHERE p.id_anggota = ? 
            AND c.status = 'lunas'
            AND c.jumlah_bayar > 0
        ");
        $stmt->bind_param("i", $id_anggota);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $saldo_data['total_cicilan_dibayar'] = (float) $data['total_bayar'];

        // 7. Sisa Pinjaman
        $saldo_data['sisa_pinjaman'] = $saldo_data['total_pinjaman'] - $saldo_data['total_cicilan_dibayar'];

        // Pastikan sisa pinjaman tidak negatif
        if ($saldo_data['sisa_pinjaman'] < 0) {
            $saldo_data['sisa_pinjaman'] = 0;
        }

        // 8. Saldo Bersih (Simpanan - Sisa Pinjaman)
        $saldo_data['saldo_bersih'] = $saldo_data['total_simpanan'] - $saldo_data['sisa_pinjaman'];

        return [
            'success' => true,
            'data' => $saldo_data
        ];

    } catch (Exception $e) {
        error_log("Error in getSaldoData: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error retrieving saldo data: ' . $e->getMessage()
        ];
    }
}

/**
 * Function untuk mendapatkan detail simpanan per jenis
 */
function getDetailSimpanan($id_anggota)
{
    global $conn;

    try {
        $detail_simpanan = [
            'pokok' => [],
            'wajib' => [],
            'sukarela_setor' => [],
            'sukarela_tarik' => []
        ];

        // Simpanan Pokok
        $stmt = $conn->prepare("
            SELECT tanggal, jumlah, metode, keterangan
            FROM pembayaran 
            WHERE id_anggota = ? 
            AND (status_bayar = 'Lunas' OR status = 'Lunas')
            AND jenis_transaksi = 'setor'
            AND jenis_simpanan = 'Simpanan Pokok'
            ORDER BY tanggal DESC
        ");
        $stmt->bind_param("i", $id_anggota);
        $stmt->execute();
        $result = $stmt->get_result();
        $detail_simpanan['pokok'] = $result->fetch_all(MYSQLI_ASSOC);

        // Simpanan Wajib
        $stmt = $conn->prepare("
            SELECT tanggal, jumlah, metode, keterangan
            FROM pembayaran 
            WHERE id_anggota = ? 
            AND (status_bayar = 'Lunas' OR status = 'Lunas')
            AND jenis_transaksi = 'setor'
            AND jenis_simpanan = 'Simpanan Wajib'
            ORDER BY tanggal DESC
        ");
        $stmt->bind_param("i", $id_anggota);
        $stmt->execute();
        $result = $stmt->get_result();
        $detail_simpanan['wajib'] = $result->fetch_all(MYSQLI_ASSOC);

        // Simpanan Sukarela Setor
        $stmt = $conn->prepare("
            SELECT tanggal, jumlah, metode, keterangan
            FROM pembayaran 
            WHERE id_anggota = ? 
            AND (status_bayar = 'Lunas' OR status = 'Lunas')
            AND jenis_transaksi = 'setor'
            AND jenis_simpanan = 'Simpanan Sukarela'
            ORDER BY tanggal DESC
        ");
        $stmt->bind_param("i", $id_anggota);
        $stmt->execute();
        $result = $stmt->get_result();
        $detail_simpanan['sukarela_setor'] = $result->fetch_all(MYSQLI_ASSOC);

        // Simpanan Sukarela Tarik
        $stmt = $conn->prepare("
            SELECT tanggal, jumlah, metode, keterangan
            FROM pembayaran 
            WHERE id_anggota = ? 
            AND (status_bayar = 'Lunas' OR status = 'Lunas')
            AND jenis_transaksi = 'tarik'
            AND jenis_simpanan = 'Simpanan Sukarela'
            ORDER BY tanggal DESC
        ");
        $stmt->bind_param("i", $id_anggota);
        $stmt->execute();
        $result = $stmt->get_result();
        $detail_simpanan['sukarela_tarik'] = $result->fetch_all(MYSQLI_ASSOC);

        return [
            'success' => true,
            'data' => $detail_simpanan
        ];

    } catch (Exception $e) {
        error_log("Error in getDetailSimpanan: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error retrieving detail simpanan: ' . $e->getMessage()
        ];
    }
}

/**
 * Function untuk mendapatkan detail pinjaman
 */
function getDetailPinjaman($id_anggota)
{
    global $conn;

    try {
        $detail_pinjaman = [
            'pinjaman_aktif' => [],
            'riwayat_cicilan' => []
        ];

        // Pinjaman Aktif
        $stmt = $conn->prepare("
            SELECT p.id_pinjaman, p.jumlah_pinjaman, p.tanggal_pinjaman, 
                   p.jangka_waktu, p.bunga, p.status,
                   (SELECT COALESCE(SUM(jumlah_bayar), 0) 
                    FROM cicilan c 
                    WHERE c.id_pinjaman = p.id_pinjaman 
                    AND c.status = 'lunas') as total_dibayar,
                   (p.jumlah_pinjaman - (SELECT COALESCE(SUM(jumlah_bayar), 0) 
                                       FROM cicilan c 
                                       WHERE c.id_pinjaman = p.id_pinjaman 
                                       AND c.status = 'lunas')) as sisa_pinjaman
            FROM pinjaman p
            WHERE p.id_anggota = ? 
            AND p.status = 'approved'
            ORDER BY p.tanggal_pinjaman DESC
        ");
        $stmt->bind_param("i", $id_anggota);
        $stmt->execute();
        $result = $stmt->get_result();
        $detail_pinjaman['pinjaman_aktif'] = $result->fetch_all(MYSQLI_ASSOC);

        // Riwayat Cicilan
        $stmt = $conn->prepare("
            SELECT c.tanggal_bayar, c.jumlah_bayar, c.metode, c.keterangan,
                   p.jumlah_pinjaman, p.id_pinjaman
            FROM cicilan c
            INNER JOIN pinjaman p ON c.id_pinjaman = p.id_pinjaman
            WHERE p.id_anggota = ? 
            AND c.status = 'lunas'
            ORDER BY c.tanggal_bayar DESC
        ");
        $stmt->bind_param("i", $id_anggota);
        $stmt->execute();
        $result = $stmt->get_result();
        $detail_pinjaman['riwayat_cicilan'] = $result->fetch_all(MYSQLI_ASSOC);

        return [
            'success' => true,
            'data' => $detail_pinjaman
        ];

    } catch (Exception $e) {
        error_log("Error in getDetailPinjaman: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error retrieving detail pinjaman: ' . $e->getMessage()
        ];
    }
}
?>