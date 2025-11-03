<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-4">💵Dashboard Saldo</h3>
        <button class="btn btn-outline-primary btn-sm" onclick="refreshSaldo()">
            <i class="fas fa-sync-alt me-1"></i> Refresh
        </button>
    </div>

    <!-- SALDO UTAMA -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-4">
                    <h6 class="card-title text-muted mb-2">SALDO UTAMA</h6>
                    <h1 class="card-text display-5 fw-bold text-primary mb-2" id="saldoUtama">Rp 0</h1>
                    <small class="text-muted">Update: <span id="lastUpdate">-</span></small>
                </div>
            </div>
        </div>
    </div>

    <!-- BREAKDOWN PER SUMBER -->
    <div class="row mb-4">
        <div class="col-md-2 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <h6 class="card-title text-muted small mb-2">Pokok & Wajib</h6>
                    <h5 class="text-success fw-bold mb-1" id="saldoPokokWajib">Rp 0</h5>
                    <small class="text-muted">Operasional</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <h6 class="card-title text-muted small mb-2">Simpanan Sukarela</h6>
                    <h5 class="text-secondary fw-bold mb-1" id="saldoSukarela">Rp 0</h5>
                    <small class="text-muted">Bisa ditarik</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <h6 class="card-title text-muted small mb-2">Penjualan</h6>
                    <h5 class="text-info fw-bold mb-1" id="saldoPenjualan">Rp 0</h5>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <h6 class="card-title text-muted small mb-2">Hibah</h6>
                    <h5 class="text-warning fw-bold mb-1" id="saldoHibah">Rp 0</h5>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <h6 class="card-title text-muted small mb-2">Angsuran</h6>
                    <h5 class="text-success fw-bold mb-1" id="saldoAngsuran">Rp 0</h5>
                    <small class="text-muted">Pinjaman</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <h6 class="card-title text-muted small mb-2">Dana Operasional</h6>
                    <h5 class="text-dark fw-bold mb-1" id="saldoOperasional">Rp 0</h5>
                </div>
            </div>
        </div>
    </div>

    <!-- PENGURANGAN SALDO -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <h6 class="card-title text-muted small mb-2">Tarik Simpanan</h6>
                    <h5 class="text-danger fw-bold mb-1" id="saldoTarik">Rp 0</h5>
                    <small class="text-muted">Pengurangan</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <h6 class="card-title text-muted small mb-2">Pengeluaran</h6>
                    <h5 class="text-danger fw-bold mb-1" id="saldoPengeluaran">Rp 0</h5>
                    <small class="text-muted">Approved</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <h6 class="card-title text-muted small mb-2">Pinjaman</h6>
                    <h5 class="text-danger fw-bold mb-1" id="saldoPinjaman">Rp 0</h5>
                    <small class="text-muted">Approved</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <h6 class="card-title text-muted small mb-2">Selisih</h6>
                    <h5 class="text-dark fw-bold mb-1" id="saldoSelisih">Rp 0</h5>
                    <small class="text-muted">Verifikasi</small>
                </div>
            </div>
        </div>
    </div>

    <!-- INFO BREAKDOWN -->
    <div class="row mb-4">
        <div class="col-12">
            <div id="infoBreakdown">
                <!-- Info hubungan akan dimuat di sini -->
            </div>
        </div>
    </div>

    <!-- BREAKDOWN PER REKENING -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-dark">Saldo Per Rekening</h5>
                </div>
                <div class="card-body">
                    <div class="row" id="rekeningSaldo">
                        <!-- Data akan di-load via AJAX -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function loadSaldoDashboard() {
        $.ajax({
            url: 'pages/ajax/get_saldo_data.php',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.status === 'success') {
                    // Update Saldo Utama
                    $('#saldoUtama').text('Rp ' + formatNumber(response.saldo_utama));
                    $('#lastUpdate').text(response.last_update);

                    // Update Per Sumber (PENAMBAHAN)
                    $('#saldoPokokWajib').text('Rp ' + formatNumber(response.simpanan_pokok_wajib));
                    $('#saldoSukarela').text('Rp ' + formatNumber(response.simpanan_sukarela));
                    $('#saldoPenjualan').text('Rp ' + formatNumber(response.penjualan_sembako));
                    $('#saldoHibah').text('Rp ' + formatNumber(response.hibah));
                    $('#saldoAngsuran').text('Rp ' + formatNumber(response.total_angsuran));
                    $('#saldoOperasional').text('Rp ' + formatNumber(response.dana_operasional));

                    // Update Pengurangan
                    $('#saldoTarik').text('Rp ' + formatNumber(response.tarik_simpanan));
                    $('#saldoPengeluaran').text('Rp ' + formatNumber(response.total_pengeluaran));
                    $('#saldoPinjaman').text('Rp ' + formatNumber(response.total_pinjaman_approved));
                    $('#saldoSelisih').text('Rp ' + formatNumber(response.selisih));

                    // Tampilkan hubungan antara komponen
                    const selisih = response.selisih || 0;
                    const selisihClass = Math.abs(selisih) < 0.01 ? 'text-success' : 'text-danger';
                    const selisihIcon = Math.abs(selisih) < 0.01 ? 'fa-check-circle' : 'fa-exclamation-circle';

                    $('#infoBreakdown').html(`
                    <div class="alert alert-light border mt-3">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-info-circle text-primary me-2"></i>
                            <strong class="text-dark">Keterangan Hubungan</strong>
                        </div>
                        <div class="row small text-muted">
                            <div class="col-md-4">
                                <div class="mb-1"><i class="fas fa-plus-circle text-success me-2"></i><strong>PENAMBAHAN:</strong></div>
                                <div class="mb-1"><i class="fas fa-wallet me-2"></i>Pokok & Wajib: <span class="fw-bold text-success">Rp ${formatNumber(response.simpanan_pokok_wajib)}</span></div>
                                <div class="mb-1"><i class="fas fa-piggy-bank me-2"></i>Simpanan Sukarela: <span class="fw-bold text-secondary">Rp ${formatNumber(response.simpanan_sukarela)}</span></div>
                                <div class="mb-1"><i class="fas fa-shopping-cart me-2"></i>Penjualan: <span class="fw-bold text-info">Rp ${formatNumber(response.penjualan_sembako)}</span></div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-1"><i class="fas fa-plus-circle text-success me-2"></i><strong>PENAMBAHAN:</strong></div>
                                <div class="mb-1"><i class="fas fa-gift me-2"></i>Hibah: <span class="fw-bold text-warning">Rp ${formatNumber(response.hibah)}</span></div>
                                <div class="mb-1"><i class="fas fa-money-bill-wave me-2"></i>Angsuran: <span class="fw-bold text-success">Rp ${formatNumber(response.total_angsuran)}</span></div>
                                <div class="mb-1"><i class="fas fa-calculator me-2"></i>Dana Operasional: <span class="fw-bold text-dark">Rp ${formatNumber(response.dana_operasional)}</span></div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-1"><i class="fas fa-minus-circle text-danger me-2"></i><strong>PENGURANGAN:</strong></div>
                                <div class="mb-1"><i class="fas fa-hand-holding-usd me-2"></i>Tarik Simpanan: <span class="fw-bold text-danger">Rp ${formatNumber(response.tarik_simpanan)}</span></div>
                                <div class="mb-1"><i class="fas fa-receipt me-2"></i>Pengeluaran: <span class="fw-bold text-danger">Rp ${formatNumber(response.total_pengeluaran)}</span></div>
                                <div class="mb-1"><i class="fas fa-hand-holding-heart me-2"></i>Pinjaman: <span class="fw-bold text-danger">Rp ${formatNumber(response.total_pinjaman_approved)}</span></div>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="row small">
                            <div class="col-md-6">
                                <div class="mb-1"><i class="fas fa-wallet me-2"></i>Saldo Utama: <span class="fw-bold text-primary">Rp ${formatNumber(response.saldo_utama)}</span></div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-1"><i class="fas ${selisihIcon} me-2 ${selisihClass}"></i>Selisih: <span class="fw-bold ${selisihClass}">Rp ${formatNumber(selisih)}</span></div>
                            </div>
                        </div>
                        ${Math.abs(selisih) > 0.01 ?
                            '<div class="alert alert-warning mt-2 mb-0 py-2 small"><i class="fas fa-exclamation-triangle me-2"></i>Terdapat selisih antara saldo utama dan perhitungan komponen. Periksa kembali transaksi.</div>' :
                            '<div class="alert alert-success mt-2 mb-0 py-2 small"><i class="fas fa-check-circle me-2"></i>Saldo sesuai dengan perhitungan komponen.</div>'
                        }
                    </div>
                `);

                    // Update Per Rekening
                    let rekeningHtml = '';
                    response.rekening.forEach(function (rek) {
                        const isKas = rek.jenis === 'kas';
                        const saldoClass = rek.saldo_sekarang >= 0 ? (isKas ? 'text-success' : 'text-primary') : 'text-danger';

                        rekeningHtml += `
                    <div class="col-md-3 mb-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center py-3">
                                <h6 class="card-title text-muted small mb-2">${rek.nama_rekening}</h6>
                                <h5 class="card-text fw-bold mb-1 ${saldoClass}">
                                    Rp ${formatNumber(rek.saldo_sekarang)}
                                </h5>
                                <small class="text-muted">${rek.nomor_rekening || (isKas ? 'Tunai' : 'Rekening')}</small>
                                <div class="mt-2">
                                    <span class="badge ${isKas ? 'bg-success' : 'bg-primary'}">${isKas ? 'KAS' : 'BANK'}</span>
                                </div>
                            </div>
                        </div>
                    </div>`;
                    });
                    $('#rekeningSaldo').html(rekeningHtml);
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function (xhr, status, error) {
                console.error('Error loading saldo data:', error);
                alert('Error loading saldo data. Please try again.');
            }
        });
    }

    function formatNumber(num) {
        if (!num) return '0';
        return new Intl.NumberFormat('id-ID').format(num);
    }

    function refreshSaldo() {
        loadSaldoDashboard();
        // Tampilkan loading indicator
        $('#saldoUtama').html('<span class="spinner-border spinner-border-sm"></span> Memuat Data...');
    }

    // Load pertama kali
    $(document).ready(function () {
        loadSaldoDashboard();

        // Auto refresh setiap 30 detik
        setInterval(loadSaldoDashboard, 30000);
    });
</script>