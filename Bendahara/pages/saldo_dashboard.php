<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Dashboard Saldo</h2>
        <button class="btn btn-outline-primary" onclick="refreshSaldo()">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>

    <!-- SALDO UTAMA -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">SALDO UTAMA</h5>
                    <h1 class="card-text display-4" id="saldoUtama">Rp 0</h1>
                    <small>Update: <span id="lastUpdate">-</span></small>
                </div>
            </div>
        </div>
    </div>

    <!-- BREAKDOWN PER SUMBER -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Simpanan Anggota</h6>
                    <h4 class="text-success" id="saldoSimpanan">Rp 0</h4>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Penjualan Sembako</h6>
                    <h4 class="text-info" id="saldoPenjualan">Rp 0</h4>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Hibah</h6>
                    <h4 class="text-warning" id="saldoHibah">Rp 0</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- BREAKDOWN PER REKENING -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Saldo Per Rekening</h5>
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
// Di saldo_dashboard.php - tambahkan info hubungan
function loadSaldoDashboard() {
    $.ajax({
        url: 'pages/ajax/get_saldo_data.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if(response.status === 'success') {
                // Update Saldo Utama
                $('#saldoUtama').text('Rp ' + formatNumber(response.saldo_utama));
                $('#lastUpdate').text(response.last_update);
                
                // Update Per Sumber
                $('#saldoSimpanan').text('Rp ' + formatNumber(response.simpanan_anggota));
                $('#saldoPenjualan').text('Rp ' + formatNumber(response.penjualan_sembako));
                $('#saldoHibah').text('Rp ' + formatNumber(response.hibah));
                
                // Tampilkan hubungan antara komponen
                $('#infoBreakdown').html(`
                    <div class="alert alert-info mt-3">
                        <small>
                            <strong>Keterangan Hubungan:</strong><br>
                            • Saldo Utama (Rp ${formatNumber(response.saldo_utama)}) = Total semua rekening<br>
                            • Simpanan Anggota (Rp ${formatNumber(response.simpanan_anggota)}) = Total pembayaran lunas<br>
                            • Penjualan Sembako (Rp ${formatNumber(response.penjualan_sembako)}) = Total penjualan terkirim<br>
                            • Hibah (Rp ${formatNumber(response.hibah)}) = Total penerimaan hibah<br>
                            • Selisih: Rp ${formatNumber(response.selisih)} (dana dari sumber lain)
                        </small>
                    </div>
                `);
                
                // Update Per Rekening
                let rekeningHtml = '';
                response.rekening.forEach(function(rek) {
                    rekeningHtml += `
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">${rek.nama_rekening}</h6>
                                <h5 class="card-text ${rek.jenis === 'kas' ? 'text-success' : 'text-primary'}">
                                    Rp ${formatNumber(rek.saldo_sekarang)}
                                </h5>
                                <small class="text-muted">${rek.nomor_rekening || 'Tunai'}</small>
                            </div>
                        </div>
                    </div>`;
                });
                $('#rekeningSaldo').html(rekeningHtml);
            }
        }
    });
}

function formatNumber(num) {
    return new Intl.NumberFormat('id-ID').format(num);
}

function refreshSaldo() {
    loadSaldoDashboard();
}

// Load pertama kali
$(document).ready(function() {
    loadSaldoDashboard();
});
</script>