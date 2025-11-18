<?php
/**
 * FUNGSI UTAMA UNTUK PERHITUNGAN LAPORAN KEUANGAN YANG AKURAT
 */

class LaporanKeuangan {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * PERHITUNGAN SALDO YANG AKURAT
     */
    public function getSaldoAkurat() {
        $data = [
            'saldo_utama' => 0,
            'rekening' => [],
            'total_simpanan' => 0,
            'total_pinjaman_aktif' => 0,
            'total_cicilan_dibayar' => 0,
            'last_update' => date('d/m/Y H:i:s')
        ];
        
        // 1. HITUNG SALDO REKENING DENGAN BENAR
        $rekening_list = ['Kas Tunai', 'Bank MANDIRI', 'Bank BRI', 'Bank BNI'];
        
        foreach ($rekening_list as $nama_rekening) {
            $saldo = $this->hitungSaldoRekening($nama_rekening);
            $data['saldo_utama'] += $saldo;
            
            $data['rekening'][] = [
                'nama_rekening' => $nama_rekening,
                'saldo_sekarang' => $saldo,
                'nomor_rekening' => $this->getNomorRekening($nama_rekening),
                'jenis' => ($nama_rekening == 'Kas Tunai') ? 'kas' : 'bank'
            ];
        }
        
        // 2. HITUNG TOTAL SIMPANAN YANG BENAR
        $data['total_simpanan'] = $this->hitungTotalSimpanan();
        
        // 3. HITUNG PINJAMAN AKTIF
        $data['total_pinjaman_aktif'] = $this->hitungPinjamanAktif();
        
        // 4. HITUNG TOTAL CICILAN DIBAYAR
        $data['total_cicilan_dibayar'] = $this->hitungTotalCicilanDibayar();
        
        return $data;
    }
    
    /**
     * HITUNG SALDO PER REKENING DENGAN LOGIKA YANG BENAR
     */
    private function hitungSaldoRekening($nama_rekening) {
        $saldo = 0;
        $is_cash = ($nama_rekening == 'Kas Tunai');
        
        // A. PENDAPATAN SETORAN SIMPANAN
        $saldo += $this->getSetoranSimpanan($nama_rekening, $is_cash);
        
        // B. PENDAPATAN PENJUALAN SEMBAKO
        $saldo += $this->getPendapatanPenjualan($nama_rekening, $is_cash);
        
        // C. PENDAPATAN CICILAN
        $saldo += $this->getPendapatanCicilan($nama_rekening, $is_cash);
        
        // D. PENDAPATAN HIBAH
        $saldo += $this->getPendapatanHibah($nama_rekening, $is_cash);
        
        // E. PENGELUARAN (TARIKAN & BIAYA)
        $saldo -= $this->getTotalPengeluaran($nama_rekening, $is_cash);
        
        return max(0, $saldo); // Pastikan tidak negatif
    }
    
    /**
     * SETORAN SIMPANAN (POKOK, WAJIB, SUKARELA)
     */
    private function getSetoranSimpanan($nama_rekening, $is_cash) {
        $total = 0;
        
        // Simpanan Pokok & Wajib
        $query = $is_cash ? 
            "SELECT COALESCE(SUM(jumlah), 0) as total 
             FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'setor'
             AND metode = 'cash'
             AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib')" :
             
            "SELECT COALESCE(SUM(jumlah), 0) as total 
             FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'setor'
             AND metode = 'transfer' 
             AND bank_tujuan = ?
             AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib')";
             
        if ($is_cash) {
            $result = $this->conn->query($query);
        } else {
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        
        $data = $result->fetch_assoc();
        $total += (float) $data['total'];
        
        // Simpanan Sukarela (SETOR saja, TARIK dihitung di pengeluaran)
        $query_sukarela = $is_cash ?
            "SELECT COALESCE(SUM(jumlah), 0) as total 
             FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'setor'
             AND metode = 'cash'
             AND jenis_simpanan = 'Simpanan Sukarela'" :
             
            "SELECT COALESCE(SUM(jumlah), 0) as total 
             FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'setor'
             AND metode = 'transfer' 
             AND bank_tujuan = ?
             AND jenis_simpanan = 'Simpanan Sukarela'";
             
        if ($is_cash) {
            $result = $this->conn->query($query_sukarela);
        } else {
            $stmt = $this->conn->prepare($query_sukarela);
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        
        $data = $result->fetch_assoc();
        $total += (float) $data['total'];
        
        return $total;
    }
    
    /**
     * PENDAPATAN PENJUALAN SEMBAKO
     */
    private function getPendapatanPenjualan($nama_rekening, $is_cash) {
        $query = $is_cash ?
            "SELECT COALESCE(SUM(pd.subtotal), 0) as total 
             FROM pemesanan_detail pd 
             INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
             WHERE p.status = 'Terkirim' 
             AND p.metode = 'cash'" :
             
            "SELECT COALESCE(SUM(pd.subtotal), 0) as total 
             FROM pemesanan_detail pd 
             INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
             WHERE p.status = 'Terkirim' 
             AND p.metode = 'transfer'
             AND p.bank_tujuan = ?";
             
        if ($is_cash) {
            $result = $this->conn->query($query);
        } else {
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        
        $data = $result->fetch_assoc();
        return (float) $data['total'];
    }
    
    /**
     * PENDAPATAN CICILAN
     */
    private function getPendapatanCicilan($nama_rekening, $is_cash) {
        $query = $is_cash ?
            "SELECT COALESCE(SUM(jumlah_bayar), 0) as total 
             FROM cicilan 
             WHERE status = 'lunas'
             AND metode = 'cash'
             AND jumlah_bayar > 0" :
             
            "SELECT COALESCE(SUM(jumlah_bayar), 0) as total 
             FROM cicilan 
             WHERE status = 'lunas'
             AND metode = 'transfer'
             AND bank_tujuan = ?
             AND jumlah_bayar > 0";
             
        if ($is_cash) {
            $result = $this->conn->query($query);
        } else {
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        
        $data = $result->fetch_assoc();
        return (float) $data['total'];
    }
    
    /**
     * PENDAPATAN HIBAH
     */
    private function getPendapatanHibah($nama_rekening, $is_cash) {
        $query = $is_cash ?
            "SELECT COALESCE(SUM(jumlah), 0) as total 
             FROM pembayaran 
             WHERE (jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%')
             AND metode = 'cash'" :
             
            "SELECT COALESCE(SUM(jumlah), 0) as total 
             FROM pembayaran 
             WHERE (jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%')
             AND metode = 'transfer' 
             AND bank_tujuan = ?";
             
        if ($is_cash) {
            $result = $this->conn->query($query);
        } else {
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        
        $data = $result->fetch_assoc();
        return (float) $data['total'];
    }
    
    /**
     * TOTAL PENGELUARAN (TARIKAN + BIAYA)
     */
    private function getTotalPengeluaran($nama_rekening, $is_cash) {
        $total = 0;
        
        // 1. TARIKAN SIMPANAN SUKARELA
        $query_tarik = $is_cash ?
            "SELECT COALESCE(SUM(jumlah), 0) as total 
             FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'tarik'
             AND metode = 'cash'" :
             
            "SELECT COALESCE(SUM(jumlah), 0) as total 
             FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'tarik'
             AND metode = 'transfer' 
             AND bank_tujuan = ?";
             
        if ($is_cash) {
            $result = $this->conn->query($query_tarik);
        } else {
            $stmt = $this->conn->prepare($query_tarik);
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        
        $data = $result->fetch_assoc();
        $total += (float) $data['total'];
        
        // 2. PENGELUARAN APPROVED
        $query_pengeluaran = "
            SELECT COALESCE(SUM(jumlah), 0) as total 
            FROM pengeluaran 
            WHERE status = 'approved' AND sumber_dana = ?
        ";
        
        $stmt = $this->conn->prepare($query_pengeluaran);
        $stmt->bind_param("s", $nama_rekening);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $total += (float) $data['total'];
        
        return $total;
    }
    
    /**
     * TOTAL SIMPANAN ANGGOTA (dari tabel anggota)
     */
    private function hitungTotalSimpanan() {
        $query = "SELECT COALESCE(SUM(saldo_total), 0) as total_simpanan 
                  FROM anggota 
                  WHERE status_keanggotaan = 'Aktif'";
        
        $result = $this->conn->query($query);
        $data = $result->fetch_assoc();
        return (float) $data['total_simpanan'];
    }
    
    /**
     * TOTAL PINJAMAN AKTIF
     */
    private function hitungPinjamanAktif() {
        $query = "SELECT COALESCE(SUM(jumlah_pinjaman), 0) as total 
                  FROM pinjaman 
                  WHERE status = 'approved'";
        
        $result = $this->conn->query($query);
        $data = $result->fetch_assoc();
        return (float) $data['total'];
    }
    
    /**
     * TOTAL CICILAN DIBAYAR
     */
    private function hitungTotalCicilanDibayar() {
        $query = "SELECT COALESCE(SUM(jumlah_bayar), 0) as total 
                  FROM cicilan 
                  WHERE status = 'lunas' AND jumlah_bayar > 0";
        
        $result = $this->conn->query($query);
        $data = $result->fetch_assoc();
        return (float) $data['total'];
    }
    
    /**
     * PERHITUNGAN NET INCOME YANG BENAR
     */
    public function getNetIncome($bulan_ini) {
        // 1. TOTAL PENDAPATAN
        $pendapatan_penjualan = $this->getPendapatanBulan($bulan_ini, 'penjualan');
        $pendapatan_cicilan = $this->getPendapatanBulan($bulan_ini, 'cicilan');
        $pendapatan_simpanan = $this->getPendapatanBulan($bulan_ini, 'simpanan');
        
        $total_pendapatan = $pendapatan_penjualan + $pendapatan_cicilan + $pendapatan_simpanan;
        
        // 2. TOTAL PENGELUARAN
        $total_pengeluaran = $this->getPengeluaranBulan($bulan_ini);
        
        // 3. NET INCOME = TOTAL PENDAPATAN - TOTAL PENGELUARAN
        $net_income = $total_pendapatan - $total_pengeluaran;
        
        // 4. NET CASH FLOW = (Penjualan + Cicilan) - Pengeluaran
        $net_cash_flow = ($pendapatan_penjualan + $pendapatan_cicilan) - $total_pengeluaran;
        
        return [
            'net_income' => $net_income,
            'net_cash_flow' => $net_cash_flow,
            'pendapatan_penjualan' => $pendapatan_penjualan,
            'pendapatan_cicilan' => $pendapatan_cicilan,
            'pendapatan_simpanan' => $pendapatan_simpanan,
            'total_pengeluaran' => $total_pengeluaran
        ];
    }
    
    private function getPendapatanBulan($bulan, $jenis) {
        switch ($jenis) {
            case 'penjualan':
                $query = "SELECT COALESCE(SUM(pd.subtotal), 0) as total 
                          FROM pemesanan_detail pd 
                          INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
                          WHERE p.status = 'Terkirim' 
                          AND DATE_FORMAT(p.tanggal_pesan, '%Y-%m') = '$bulan'";
                break;
                
            case 'cicilan':
                $query = "SELECT COALESCE(SUM(jumlah_bayar), 0) as total 
                          FROM cicilan 
                          WHERE status = 'lunas' 
                          AND DATE_FORMAT(tanggal_bayar, '%Y-%m') = '$bulan'
                          AND jumlah_bayar > 0";
                break;
                
            case 'simpanan':
                $query = "SELECT COALESCE(SUM(jumlah), 0) as total 
                          FROM pembayaran 
                          WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                          AND jenis_transaksi = 'setor'
                          AND DATE_FORMAT(tanggal_bayar, '%Y-%m') = '$bulan'";
                break;
                
            default:
                return 0;
        }
        
        $result = $this->conn->query($query);
        $data = $result->fetch_assoc();
        return (float) $data['total'];
    }
    
    private function getPengeluaranBulan($bulan) {
        $query = "SELECT COALESCE(SUM(jumlah), 0) as total 
                  FROM pengeluaran 
                  WHERE status = 'approved' 
                  AND DATE_FORMAT(tanggal, '%Y-%m') = '$bulan'";
        
        $result = $this->conn->query($query);
        $data = $result->fetch_assoc();
        return (float) $data['total'];
    }
    
    private function getNomorRekening($nama_rekening) {
        $nomor_rekening = [
            'Kas Tunai' => '-',
            'Bank MANDIRI' => '1234567890',
            'Bank BRI' => '0987654321',
            'Bank BNI' => '55555555555'
        ];
        return $nomor_rekening[$nama_rekening] ?? '-';
    }
}
?>
