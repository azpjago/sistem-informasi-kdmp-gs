<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Set Saldo Awal</h2>
        <a href="?page=saldo_dashboard" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Input Saldo Awal Rekening</h5>
                </div>
                <div class="card-body">
                    <form id="formSetSaldo">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Kas Tunai</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" name="saldo_kas" 
                                           value="0" min="0" step="1000">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Uang fisik yang ada di tangan bendahara</small>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Bank BCA</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" name="saldo_bca" 
                                           value="0" min="0" step="1000">
                                </div>
                                <small class="text-muted">No. Rek: 1234567890</small>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Saldo di rekening BCA</small>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Bank BRI</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" name="saldo_bri" 
                                           value="5000000" min="0" step="1000">
                                </div>
                                <small class="text-muted">No. Rek: 0987654321</small>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Saldo di rekening BRI</small>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Bank BNI</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" name="saldo_bni" 
                                           value="0" min="0" step="1000">
                                </div>
                                <small class="text-muted">No. Rek: 5555555555</small>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Saldo di rekening BNI</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Simpan Saldo Awal
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                    <i class="fas fa-undo"></i> Reset
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Ringkasan</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Total Saldo Utama:</strong>
                        <h4 id="totalSaldo" class="text-primary">Rp 0</h4>
                    </div>
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle"></i> 
                            Input saldo sesuai dengan kondisi riil:
                            <ul class="mb-0 mt-2">
                                <li>Saldo di kas fisik</li>
                                <li>Saldo di masing-masing bank</li>
                            </ul>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Hitung total saldo real-time
function hitungTotal() {
    const kas = parseFloat($('[name="saldo_kas"]').val()) || 0;
    const bca = parseFloat($('[name="saldo_bca"]').val()) || 0;
    const bri = parseFloat($('[name="saldo_bri"]').val()) || 0;
    const bni = parseFloat($('[name="saldo_bni"]').val()) || 0;
    
    const total = kas + bca + bri + bni;
    $('#totalSaldo').text('Rp ' + formatNumber(total));
}

// Format number
function formatNumber(num) {
    return new Intl.NumberFormat('id-ID').format(num);
}

// Reset form
function resetForm() {
    if(confirm('Reset semua input ke 0?')) {
        $('#formSetSaldo')[0].reset();
        hitungTotal();
    }
}

// Submit form
$('#formSetSaldo').on('submit', function(e) {
    e.preventDefault();
    
    const formData = {
        kas: $('[name="saldo_kas"]').val(),
        bca: $('[name="saldo_bca"]').val(), 
        bri: $('[name="saldo_bri"]').val(),
        bni: $('[name="saldo_bni"]').val()
    };

    if(confirm('Simpan saldo awal? Pastikan data sudah sesuai dengan kondisi riil.')) {
        $.ajax({
            url: 'pages/ajax/set_saldo_awal.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if(response.status === 'success') {
                    alert('Saldo awal berhasil disimpan!');
                    window.location.href = '?page=saldo_dashboard';
                } else {
                    alert('Error: ' + response.message);
                }
            }
        });
    }
});

// Hitung total saat input berubah
$('#formSetSaldo input').on('input', hitungTotal);

// Hitung total pertama kali
$(document).ready(hitungTotal);
</script>