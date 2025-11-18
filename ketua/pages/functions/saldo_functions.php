<?php
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

        // ✅ 1. Hitung Simpanan Pokok
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(jumlah), 0) as total 
            FROM pembayaran 
            WHERE id_anggota = ? 
            AND (status_bayar = 'Lunas' OR status = 'Lunas' OR status_bayar = 'approved' OR status = 'approved')
            AND jenis_transaksi = 'setor'
            AND jenis_simpanan = 'Simpanan Pokok'
        ");
        $stmt->bind_param("i", $id_anggota);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $saldo_data['simpanan_pokok'] = max(0, (float) $data['total']);

        // ✅ 2. Hitung Simpanan Wajib - FIX: Gunakan kolom status yang benar
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(jumlah), 0) as total 
            FROM pembayaran 
            WHERE id_anggota = ? 
            AND (status_bayar = 'Lunas' OR status = 'Lunas' OR status_bayar = 'approved' OR status = 'approved')
            AND jenis_transaksi = 'setor'
            AND jenis_simpanan = 'Simpanan Wajib'
        ");
        $stmt->bind_param("i", $id_anggota);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $saldo_data['simpanan_wajib'] = max(0, (float) $data['total']);

        // ✅ 3. Hitung Simpanan Sukarela - FIX: Filter status untuk SETOR dan TARIK
        $stmt = $conn->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' AND 
                (status_bayar = 'Lunas' OR status = 'Lunas' OR status_bayar = 'approved' OR status = 'approved') 
                THEN jumlah ELSE 0 END), 0) as total_setor,
                
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' AND 
                (status_bayar = 'Lunas' OR status = 'Lunas' OR status_bayar = 'approved' OR status = 'approved') 
                THEN jumlah ELSE 0 END), 0) as total_tarik
            FROM pembayaran 
            WHERE id_anggota = ? 
            AND jenis_simpanan = 'Simpanan Sukarela'
        ");
        $stmt->bind_param("i", $id_anggota);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        
        $total_setor = max(0, (float) $data['total_setor']);
        $total_tarik = max(0, (float) $data['total_tarik']);
        $saldo_data['simpanan_sukarela'] = $total_setor - $total_tarik;

        // ✅ Pastikan simpanan sukarela tidak negatif
        if ($saldo_data['simpanan_sukarela'] < 0) {
            $saldo_data['simpanan_sukarela'] = 0;
        }

        // 4. Total Simpanan
        $saldo_data['total_simpanan'] = $saldo_data['simpanan_pokok'] +
            $saldo_data['simpanan_wajib'] +
            $saldo_data['simpanan_sukarela'];

        // ✅ 5. Hitung Total Pinjaman yang Disetujui - FIX: Include semua status approved
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(jumlah_pinjaman), 0) as total 
            FROM pinjaman 
            WHERE id_anggota = ? 
            AND (status = 'approved' OR status = 'disetujui' OR status = 'lunas')
        ");
        $stmt->bind_param("i", $id_anggota);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $saldo_data['total_pinjaman'] = max(0, (float) $data['total']);

        // ✅ 6. Hitung Total Cicilan yang Sudah Dibayar - FIX: Include semua status pembayaran
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(c.jumlah_bayar), 0) as total_bayar
            FROM cicilan c
            INNER JOIN pinjaman p ON c.id_pinjaman = p.id_pinjaman
            WHERE p.id_anggota = ? 
            AND (c.status = 'lunas' OR c.status = 'paid' OR c.status_bayar = 'Lunas')
            AND c.jumlah_bayar > 0
        ");
        $stmt->bind_param("i", $id_anggota);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $saldo_data['total_cicilan_dibayar'] = max(0, (float) $data['total_bayar']);

        // ✅ 7. Sisa Pinjaman dengan validasi
        $saldo_data['sisa_pinjaman'] = $saldo_data['total_pinjaman'] - $saldo_data['total_cicilan_dibayar'];
        
        // Pastikan sisa pinjaman tidak negatif dan tidak melebihi total pinjaman
        if ($saldo_data['sisa_pinjaman'] < 0) {
            $saldo_data['sisa_pinjaman'] = 0;
        }
        if ($saldo_data['sisa_pinjaman'] > $saldo_data['total_pinjaman']) {
            $saldo_data['sisa_pinjaman'] = $saldo_data['total_pinjaman'];
        }

        // ✅ 8. Saldo Bersih dengan validasi
        $saldo_data['saldo_bersih'] = $saldo_data['total_simpanan'] - $saldo_data['sisa_pinjaman'];
        
        // Log untuk debugging
        error_log("Saldo Calculation for $id_anggota: " . json_encode($saldo_data));

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
 * Function untuk mendapatkan detail simpanan per jenis - DIPERBAIKI
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

        // ✅ FIX: Gunakan kondisi status yang komprehensif
        $status_condition = "(status_bayar = 'Lunas' OR status = 'Lunas' OR status_bayar = 'approved' OR status = 'approved')";

        // Simpanan Pokok
        $stmt = $conn->prepare("
            SELECT tanggal, jumlah, metode, keterangan, jenis_transaksi, status, status_bayar
            FROM pembayaran 
            WHERE id_anggota = ? 
            AND $status_condition
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
            SELECT tanggal, jumlah, metode, keterangan, jenis_transaksi, status, status_bayar
            FROM pembayaran 
            WHERE id_anggota = ? 
            AND $status_condition
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
            SELECT tanggal, jumlah, metode, keterangan, jenis_transaksi, status, status_bayar
            FROM pembayaran 
            WHERE id_anggota = ? 
            AND $status_condition
            AND jenis_transaksi = 'setor'
            AND jenis_simpanan = 'Simpanan Sukarela'
            ORDER BY tanggal DESC
        ");
        $stmt->bind_param("i", $id_anggota);
        $stmt->execute();
        $result = $stmt->get_result();
        $detail_simpanan['sukarela_setor'] = $result->fetch_all(MYSQLI_ASSOC);

        // Simpanan Sukarela Tarik - FIX: Filter status untuk tarik
        $stmt = $conn->prepare("
            SELECT tanggal, jumlah, metode, keterangan, jenis_transaksi, status, status_bayar
            FROM pembayaran 
            WHERE id_anggota = ? 
            AND $status_condition
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
 * Function untuk mendapatkan detail pinjaman - DIPERBAIKI
 */
function getDetailPinjaman($id_anggota)
{
    global $conn;

    try {
        $detail_pinjaman = [
            'pinjaman_aktif' => [],
            'riwayat_cicilan' => []
        ];

        // ✅ Pinjaman Aktif - FIX: Include berbagai status
        $stmt = $conn->prepare("
            SELECT p.id_pinjaman, p.jumlah_pinjaman, p.tanggal_pinjaman, 
                   p.jangka_waktu, p.bunga, p.status,
                   (SELECT COALESCE(SUM(jumlah_bayar), 0) 
                    FROM cicilan c 
                    WHERE c.id_pinjaman = p.id_pinjaman 
                    AND (c.status = 'lunas' OR c.status = 'paid' OR c.status_bayar = 'Lunas')) as total_dibayar,
                   (p.jumlah_pinjaman - (SELECT COALESCE(SUM(jumlah_bayar), 0) 
                                       FROM cicilan c 
                                       WHERE c.id_pinjaman = p.id_pinjaman 
                                       AND (c.status = 'lunas' OR c.status = 'paid' OR c.status_bayar = 'Lunas'))) as sisa_pinjaman
            FROM pinjaman p
            WHERE p.id_anggota = ? 
            AND (p.status = 'approved' OR p.status = 'disetujui' OR p.status = 'lunas')
            ORDER BY p.tanggal_pinjaman DESC
        ");
        $stmt->bind_param("i", $id_anggota);
        $stmt->execute();
        $result = $stmt->get_result();
        $detail_pinjaman['pinjaman_aktif'] = $result->fetch_all(MYSQLI_ASSOC);

        // ✅ Riwayat Cicilan - FIX: Include berbagai status
        $stmt = $conn->prepare("
            SELECT c.tanggal_bayar, c.jumlah_bayar, c.metode, c.keterangan,
                   p.jumlah_pinjaman, p.id_pinjaman, c.status, c.status_bayar
            FROM cicilan c
            INNER JOIN pinjaman p ON c.id_pinjaman = p.id_pinjaman
            WHERE p.id_anggota = ? 
            AND (c.status = 'lunas' OR c.status = 'paid' OR c.status_bayar = 'Lunas')
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

/**
 * ✅ FUNCTION BARU: Debug struktur database untuk troubleshooting
 */
function debugDatabaseStructure($id_anggota)
{
    global $conn;
    
    $debug_info = [];
    
    // Cek struktur tabel pembayaran
    $result = $conn->query("DESCRIBE pembayaran");
    $debug_info['pembayaran_columns'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Cek sample data pembayaran
    $stmt = $conn->prepare("SELECT * FROM pembayaran WHERE id_anggota = ? LIMIT 5");
    $stmt->bind_param("i", $id_anggota);
    $stmt->execute();
    $result = $stmt->get_result();
    $debug_info['sample_pembayaran'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Cek struktur tabel pinjaman
    $result = $conn->query("DESCRIBE pinjaman");
    $debug_info['pinjaman_columns'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Cek sample data pinjaman
    $stmt = $conn->prepare("SELECT * FROM pinjaman WHERE id_anggota = ? LIMIT 5");
    $stmt->bind_param("i", $id_anggota);
    $stmt->execute();
    $result = $stmt->get_result();
    $debug_info['sample_pinjaman'] = $result->fetch_all(MYSQLI_ASSOC);
    
    return $debug_info;
}
?>
