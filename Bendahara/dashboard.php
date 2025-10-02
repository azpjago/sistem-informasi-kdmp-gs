<!DOCTYPE html>
<html>
<?php
date_default_timezone_set('Asia/Jakarta');
?>
<head>
    <title>Dashboard Bendahara</title>
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

</head>

<body>
    <div class="d-flex main-container">
        <!-- SIDEBAR NAVIGATION -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h4>KDMPGS</h4>
            </div>

            <ul class="nav flex-column">
                <li class="nav-item"><a href="?page=dashboard_utama" class="nav-link"><i class="fas fa-home"></i>
                        <span>Home</span></a></li>
                <li class="nav-item"><a href="?page=monitoring" class="nav-link"><i class="fas fa-bar-chart"></i>
                        <span>Monitoring</span></a></li>
                <li class="nav-item"><a href="?page=transaksi" class="nav-link"><i class="fas fa-credit-card"></i>
                        <span>Log Transaksi</span></a></li>
                <li class="nav-item"><a href="?page=rekap" class="nav-link"><i class="fas fa-reorder"></i>
                        <span>Riwayat Transaksi</span></a></li>
                <li class="nav-item"><a href="?page=sukarela" class="nav-link"><i class="fas fa-dollar"></i>
                        <span>Sukarela</span></a></li>
                <li class="nav-item"><a href="?page=buku_simpanan" class="nav-link"><i class="fas fa-book"></i>
                        <span>Buku Simpanan</span></a></li>
                <li class="nav-item"><a href="?page=nota" class="nav-link"><i class="fas fa-sticky-note"></i>
                        <span>Kartu Nota</span></a></li>
                <li class="nav-item logout-item"><a href="../logout.php" class="nav-link"><i
                            class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>

            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-name"><?php echo $_SESSION['username'] ?? 'User'; ?></div>
                <div class="user-role"><?php echo $_SESSION['role'] ?? 'Pengurus'; ?></div>
            </div>
        </div>

        <!-- CONTENT AREA - akan di-load dari pages -->
        <div class="content-wrapper">
            <?php
            $page = $_GET['page'] ?? "dashboard_utama";
            $allowed_pages = [
                'dashboard_utama',
                'monitoring',
                'transaksi',
                'rekap',
                'riwayat_anggota',
                'buku_simpanan',
                'sukarela',
                'nota'
            ];

            if (in_array($page, $allowed_pages) && file_exists("pages/" . $page . ".php")) {
                include "pages/" . $page . ".php";
            } else {
                echo "Halaman tidak ditemukan.";
            }
            ?>
        </div>
    </div>
</div>
</div>
    </div> </div> <div class="modal fade" id="modalUploadBukti" tabindex="-1" aria-labelledby="modalUploadBuktiLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalUploadBuktiLabel">Upload Bukti Transaksi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="formUploadBukti" action="upload_bukti_tambahan.php" method="POST" enctype="multipart/form-data">
        <div class="modal-body">
          <p>Anda akan mengupload bukti untuk transaksi ini. Pastikan file yang diupload benar.</p>
          <input type="hidden" name="pembayaran_id" id="pembayaranIdModal">
          <div class="mb-3">
            <label for="fileBukti" class="form-label">Pilih File Bukti (Gambar)</label>
            <input class="form-control" type="file" name="bukti" id="fileBukti" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Upload</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        $(document).ready(function() {
    // ==========================================================
    // == INISIALISASI DATATABLES                              ==
    // ==========================================================
    if ($('#tabelAnggota').length && !$.fn.DataTable.isDataTable('#tabelAnggota')) {
        $('#tabelAnggota').DataTable({
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            language: {
                search: "Cari No Anggota / Nama : ",
                lengthMenu: "Tampilkan _MENU_ data per halaman",
                info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                zeroRecords: "Tidak ada data yang cocok",
                infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
                infoFiltered: "(disaring dari _MAX_ total data)",
                paginate: { first: "Awal", last: "Akhir", next: "Berikutnya", previous: "Sebelumnya" }
            }
        });
    }
    // TAMBAHKAN INI: Untuk tabel di halaman Log Transaksi
if ($('#logTransaksiTable').length && !$.fn.DataTable.isDataTable('#logTransaksiTable')) {
    $('#logTransaksiTable').DataTable({
        "language": {
            "search": "Cari Transaksi:",
            "lengthMenu": "Tampilkan _MENU_ data per halaman",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            "zeroRecords": "Tidak ada data yang cocok",
            "infoEmpty": "Menampilkan 0 sampai 0 dari 0 data",
            "infoFiltered": "(disaring dari _MAX_ total data)",
            "paginate": { 
                "first": "Awal", 
                "last": "Akhir", 
                "next": "Berikutnya", 
                "previous": "Sebelumnya" 
            }
        },
        "order": [[ 0, "desc" ]] // Otomatis urutkan berdasarkan kolom pertama (Tanggal) secara menurun
    });
}
        // ==========================================================
    // == SCRIPT UNTUK MODAL SIMPANAN WAJIB                    ==
    // ==========================================================
// dashboard.php - Tambahkan kode ini di bagian JavaScript

// Function untuk init modal update bayar
function initUpdateBayarModal() {
    // Pastikan modal ada di DOM
    if ($('#updateBayarModal').length === 0) {
        console.log('Modal updateBayarModal tidak ditemukan di DOM');
        return;
    }

    // Handle click tombol update bayar
$(document).on('click', '.update-bayar-btn', function() {
    const anggotaId = $(this).data('id');         // Ini ANGGOYA.ID (INT)
    const noAnggota = $(this).data('no-anggota'); // Ini NO_ANGGOTA (VARCHAR)
    const anggotaNama = $(this).data('nama');
    const jumlahWajib = $(this).data('jumlah');
    
    console.log('ID:', anggotaId, 'Type:', typeof anggotaId);
    console.log('No Anggota:', noAnggota, 'Type:', typeof noAnggota);
    
    $('#anggotaIdModal').val(anggotaId);  // Simpan ID (INT) untuk dikirim ke PHP
    $('#noAnggotaModal').text(noAnggota); // Tampilkan No Anggota (VARCHAR) untuk display
    $('#namaAnggotaModal').text(anggotaNama);
    $('#jumlahWajibModal').text('Rp ' + parseInt(jumlahWajib).toLocaleString('id-ID'));
});

    // Handle submit form
    $('#formUpdateBayar').on('submit', function(event) {
        event.preventDefault();
        var formData = new FormData(this);
        $(this).find('button[type="submit"]').prop('disabled', true).text('Memproses...');
        $.ajax({
    url: 'update_pembayaran.php',
    type: 'POST',
    data: formData,
    processData: false,
    contentType: false,
    dataType: 'json', // ⬅️ ini penting, biar otomatis parse JSON
    success: function(result) {
        if (result.status === 'success') {
            alert(result.message);
            $('#updateBayarModal').modal('hide');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
        $('#formUpdateBayar').find('button[type="submit"]').prop('disabled', false).text('Konfirmasi Pembayaran');
    },
    error: function(jqXHR, textStatus, errorThrown) {
        var errorResponse = jqXHR.responseJSON;
        alert('Error: ' + (errorResponse ? errorResponse.message : 'Terjadi kesalahan.'));
        $('#formUpdateBayar').find('button[type="submit"]').prop('disabled', false).text('Konfirmasi Pembayaran');
    }
});
    });

    console.log('Modal updateBayarModal berhasil diinisialisasi');
}

    // Tunggu sebentar untuk memastikan modal sudah load
    setTimeout(initUpdateBayarModal, 500);
});

// Juga panggil ketika modal shown (untuk handle dynamic content)
$(document).on('shown.bs.modal', '#updateBayarModal', function() {
    initUpdateBayarModal();
});


    // ==========================================================
    // == SCRIPT UNTUK MODAL SIMPANAN SUKARELA                 ==
    // ==========================================================
    // Mengatur modal saat tombol setor/tarik diklik
    $(document).on('click', '.btn-transaksi', function() {
        var anggotaId = $(this).data('anggota-id');
        var jenis = $(this).data('jenis');
        
        $('#anggotaIdSukarela').val(anggotaId);
        $('#tipeTransaksiSukarela').val(jenis);

        if (jenis === 'setor') {
            $('#modalSukarelaLabel').text('Form Setor Sukarela');
            $('#btnSubmitSukarela').text('Konfirmasi Setoran').removeClass('btn-warning').addClass('btn-primary');
        } else {
            $('#modalSukarelaLabel').text('Form Tarik Sukarela');
            $('#btnSubmitSukarela').text('Konfirmasi Penarikan').removeClass('btn-primary').addClass('btn-warning');
        }
    });

    // Submit form sukarela via AJAX
    // Handle form submission
    $('#modalSukarela form').on('submit', function(event) {
        event.preventDefault();
        var formData = new FormData(this);
        var btn = $(this).find('button[type="submit"]');
        
        // Validasi manual
        const tipeTransaksi = $('#tipeTransaksiSukarela').val();
        const jumlah = parseFloat($('#jumlah').val());
        const saldoSekarang = parseFloat($('#saldoSekarangSukarela').val());
        
        if (isNaN(jumlah) || jumlah < 1000) {
            alert('Jumlah minimal Rp 1.000');
            return false;
        }
        
        if (tipeTransaksi === 'tarik' && jumlah > saldoSekarang) {
            alert('Maaf, saldo tidak mencukupi. Saldo tersedia: Rp ' + saldoSekarang.toLocaleString('id-ID'));
            return false;
        }
        
        btn.prop('disabled', true).text('Memproses...');

        $.ajax({
            url: 'proses_sukarela.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
            // Cek properti 'success' dari objek JSON yang dikirim PHP
            if (response.success) {
                // Jika sukses, tampilkan pesan dari PHP
                alert(response.message);
                location.reload();
            } else {
                // Jika gagal, tampilkan pesan error dari PHP
                alert('Error: ' + response.message);
                // Aktifkan kembali tombolnya jika gagal
                btn.prop('disabled', false).text('Konfirmasi');
            }
        },
            error: function() {
                alert('Terjadi kesalahan server. Silakan coba lagi.');
                btn.prop('disabled', false).text('Konfirmasi');
            }
        });
    });
  
// ==========================================================
// == BAGIAN BARU: SCRIPT UNTUK MODAL UPLOAD BUKTI TAMBAHAN ==
// ==========================================================
$(document).on('click', '.upload-bukti-btn', function() {
    var pembayaranId = $(this).data('pembayaran-id');
    $('#pembayaranIdModal').val(pembayaranId);
});

$('#formUploadBukti').on('submit', function(event) {
    event.preventDefault();
    var formData = new FormData(this);
    var btn = $(this).find('button[type="submit"]');
    btn.prop('disabled', true).text('Mengupload...');

    $.ajax({
        url: 'upload_bukti_tambahan.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            alert(response.message);
            $('#modalUploadBukti').modal('hide');
            location.reload();
        },
        error: function(jqXHR) {
            var errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : 'Gagal mengupload.';
            alert('Error: ' + errorMsg);
            btn.prop('disabled', false).text('Upload');
        }
    });
});

// =======================================================================
// JAVASCRIPT UNTUK NAVIGASI MODAL PENDAFTARAN
// =======================================================================
    let faseSekarang = 1;
    const modalPendaftaran = $('#modalPendaftaran');

    // --- FUNGSI-FUNGSI PEMBANTU (HELPER FUNCTIONS) ---

    function updateTombolModal() {
        if (faseSekarang === 1) {
            $('#tombolKembali').hide();
            $('#tombolLanjut').show();
            $('#tombolSimpan').hide();
        } else if (faseSekarang === 2) {
            $('#tombolKembali').show();
            $('#tombolLanjut').show();
            $('#tombolSimpan').hide();
        } else if (faseSekarang === 3) {
            $('#tombolKembali').show();
            $('#tombolLanjut').hide();
            $('#tombolSimpan').show();
        }
    }

    function updateBuktiUploadFields() {
        if ($('#bukti_sama').is(':checked')) {
            $('#bukti-tunggal-container').show();
            $('#bukti-terpisah-container').hide();
        } else {
            $('#bukti-tunggal-container').hide();
            $('#bukti-terpisah-container').show();
            $('#check-pokok').is(':checked') ? $('#field-bukti-pokok').show() : $('#field-bukti-pokok').hide();
            $('#check-wajib').is(':checked') ? $('#field-bukti-wajib').show() : $('#field-bukti-wajib').hide();
            $('#check-sukarela').is(':checked') ? $('#field-bukti-sukarela').show() : $('#field-bukti-sukarela').hide();
        }
    }
    
    function generateSummary() {
        // Mengisi rangkuman data diri (Fase 1)
        $('#summary_jenis_transaksi').text($('#jenis_transaksi').val());
        $('#summary_nama').text($('#nama').val());
        $('#summary_jenis_kelamin').text($('#jenis_kelamin').val());
        $('#summary_ttl').text($('#tempat_lahir').val() + ', ' + $('#tanggal_lahir').val());
        $('#summary_nik').text($('#nik').val());
        $('#summary_alamat').text($('#alamat').val());
        $('#summary_no_hp').text($('#no_hp').val());
        $('#summary_tanggal_join').text($('#tanggal_join').val());

        // Mengisi rangkuman pembayaran (Fase 2)
        $('#summary_metode').text($('#metode_pembayaran option:selected').text());
        $('#summary_pokok_section, #summary_wajib_section, #summary_sukarela_section').hide();
        let buktiText = [];

        const formatCurrency = (val) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(val);

        if ($('#check-pokok').is(':checked')) {
            $('#summary_amount_pokok').text(formatCurrency($('#amount_pokok').val()));
            $('#summary_pokok_section').show();
            const file = $('#bukti_pokok')[0].files[0];
            if (file) buktiText.push('Pokok: ' + file.name);
        }
        if ($('#check-wajib').is(':checked')) {
            $('#summary_amount_wajib').text(formatCurrency($('#amount_wajib').val()));
            $('#summary_wajib_section').show();
            const file = $('#bukti_wajib')[0].files[0];
            if (file) buktiText.push('Wajib: ' + file.name);
        }
        if ($('#check-sukarela').is(':checked')) {
            $('#summary_amount_sukarela').text(formatCurrency($('#amount_sukarela').val()));
            $('#summary_sukarela_section').show();
            const file = $('#bukti_sukarela')[0].files[0];
            if (file) buktiText.push('Sukarela: ' + file.name);
        }
        
        const fileTunggal = $('#bukti_tunggal')[0].files[0];
        if ($('#bukti_sama').is(':checked') && fileTunggal) {
             $('#summary_bukti').text(fileTunggal.name);
        } else if (buktiText.length > 0) {
            $('#summary_bukti').html(buktiText.join('<br>'));
        } else {
            $('#summary_bukti').text('Tidak ada file diupload');
        }
    }
    // --- EVENT HANDLERS UNTUK MODAL PENDAFTARAN ---

    // Navigasi Maju (Lanjutkan)
    $('#tombolLanjut').on('click', function() {
        if (faseSekarang === 1) {
            let valid = true;
            $('#fase-1 [required]').each(function() {
                if (!$(this).val()) {
                    valid = false;
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });
            if (!valid) {
                alert('Harap lengkapi semua data yang wajib diisi.');
                return;
            }
            // Mengambil nilai simpanan wajib saat pindah dari fase 1 ke 2
            $('#amount_wajib').val($('#simpanan_wajib').val());
        }
        
        if (faseSekarang === 2) {
            if ($('#check-sukarela').is(':checked') && ($('#amount_sukarela').val() < 5000 || $('#amount_sukarela').val() === '')) {
                alert('Jumlah simpanan sukarela minimal Rp 5.000.');
                return;
            }
            generateSummary(); 
        }

        if (faseSekarang < 3) {
            $('#fase-' + faseSekarang).hide();
            faseSekarang++;
            $('#fase-' + faseSekarang).show();
            updateTombolModal();
        }
    });

    // Navigasi Mundur (Kembali)
    $('#tombolKembali').on('click', function() {
        if (faseSekarang > 1) {
            $('#fase-' + faseSekarang).hide();
            faseSekarang--;
            $('#fase-' + faseSekarang).show();
            updateTombolModal();
        }
    });

    // Kontrol dinamis di Fase 2
    $('.simpanan-checkbox').on('change', function(){
        const jenis = $(this).val();
        $(this).is(':checked') ? $('#field-' + jenis).show() : $('#field-' + jenis).hide();
        updateBuktiUploadFields();
    });
    $('#bukti_sama').on('change', updateBuktiUploadFields);

    // Reset modal saat ditutup
    modalPendaftaran.on('hidden.bs.modal', function () {
        faseSekarang = 1;
        $('#fase-2, #fase-3').hide();
        $('#fase-1').show();
        $('#formPendaftaran')[0].reset();
        $('#formPendaftaran .is-invalid').removeClass('is-invalid');
        updateTombolModal();
    });
    
    // Hapus border merah saat input diisi
    modalPendaftaran.on('input', '.is-invalid', function() {
        $(this).removeClass('is-invalid');
    });

    // AJAX Submit Form Pendaftaran - VERSI DIPERBAIKI
$('#formPendaftaran').on('submit', function(e) {
    e.preventDefault();
    
    console.log('Form submit triggered');
    
    // Buat FormData baru
    const formData = new FormData();
    const form = this;
    
    // Tambahkan semua input text, select, textarea secara eksplisit
    const textInputs = ['jenis_transaksi', 'nama', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'nik', 'npwp', 'alamat', 'agama', 'rw', 'rt', 'no_hp', 'pekerjaan', 'simpanan_wajib', 'tanggal_join', 'metode_pembayaran'];
    
    textInputs.forEach(function(name) {
        const element = form.elements[name];
        if (element && element.value) {
            formData.append(name, element.value);
            console.log('Added text field:', name, '=', element.value);
        }
    });
    
    // Tambahkan checkbox yang dicentang
    const checkboxes = ['check-pokok', 'check-wajib', 'check-sukarela'];
    checkboxes.forEach(function(name) {
        const element = form.elements[name];
        if (element && element.checked) {
            formData.append(name, 'on');
            console.log('Added checkbox:', name, '= on');
        }
    });
    
    // Tambahkan amount fields
    const amountFields = ['amount_pokok', 'amount_wajib', 'amount_sukarela'];
    amountFields.forEach(function(name) {
        const element = form.elements[name];
        if (element && element.value) {
            formData.append(name, element.value);
            console.log('Added amount field:', name, '=', element.value);
        }
    });
    
    // Tambahkan file uploads - INI YANG PALING PENTING
    const fileInputs = ['foto_diri', 'foto_ktp', 'foto_kk', 'bukti_tunggal', 'bukti_pokok', 'bukti_wajib', 'bukti_sukarela'];
    
    fileInputs.forEach(function(name) {
        const element = form.elements[name];
        if (element && element.files && element.files.length > 0) {
            formData.append(name, element.files[0]);
            console.log('Added file:', name, '=', element.files[0].name);
        }
    });
    
    // Validasi file wajib sebelum submit
    const fotoDiri = form.elements['foto_diri'];
    const fotoKtp = form.elements['foto_ktp'];
    
    if (!fotoDiri || !fotoDiri.files || fotoDiri.files.length === 0) {
        alert('Foto diri wajib diupload!');
        return;
    }
    
    if (!fotoKtp || !fotoKtp.files || fotoKtp.files.length === 0) {
        alert('Foto KTP wajib diupload!');
        return;
    }
    
    // Validasi bukti pembayaran untuk metode transfer
    if ($('#metode_pembayaran').val() === 'transfer') {
        let needsBukti = false;
        if ($('#check-pokok').is(':checked') || $('#check-wajib').is(':checked') || $('#check-sukarela').is(':checked')) {
            needsBukti = true;
        }
        
        if (needsBukti) {
            const buktiSama = form.elements['bukti_sama'] && form.elements['bukti_sama'].checked;
            const buktiTunggal = form.elements['bukti_tunggal'];
            
            if (buktiSama) {
                if (!buktiTunggal || !buktiTunggal.files || buktiTunggal.files.length === 0) {
                    alert('Bukti pembayaran wajib diupload untuk metode transfer!');
                    return;
                }
            } else {
                let buktiValid = true;
                if ($('#check-pokok').is(':checked')) {
                    const buktiPokok = form.elements['bukti_pokok'];
                    if (!buktiPokok || !buktiPokok.files || buktiPokok.files.length === 0) {
                        alert('Bukti pembayaran simpanan pokok wajib diupload!');
                        return;
                    }
                }
                if ($('#check-wajib').is(':checked')) {
                    const buktiWajib = form.elements['bukti_wajib'];
                    if (!buktiWajib || !buktiWajib.files || buktiWajib.files.length === 0) {
                        alert('Bukti pembayaran simpanan wajib wajib diupload!');
                        return;
                    }
                }
                if ($('#check-sukarela').is(':checked')) {
                    const buktiSukarela = form.elements['bukti_sukarela'];
                    if (!buktiSukarela || !buktiSukarela.files || buktiSukarela.files.length === 0) {
                        alert('Bukti pembayaran simpanan sukarela wajib diupload!');
                        return;
                    }
                }
            }
        }
    }
    
    // Tambahkan bukti_sama flag jika dicentang
    if (form.elements['bukti_sama'] && form.elements['bukti_sama'].checked) {
        formData.append('bukti_sama', 'on');
    }

    const tombolSimpan = $('#tombolSimpan');

    // Debug: tampilkan semua data yang akan dikirim
    console.log('=== FormData Contents ===');
    for (let pair of formData.entries()) {
        console.log(pair[0] + ':', pair[1] instanceof File ? pair[1].name + ' (' + pair[1].size + ' bytes)' : pair[1]);
    }

    $.ajax({
        url: 'proses_pendaftaran.php',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        dataType: 'json',
        beforeSend: function() {
            tombolSimpan.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Menyimpan...');
        },
        success: function(response) {
            console.log('Success response:', response);
            if (response.status === 'success') {
                alert(response.message);
                modalPendaftaran.modal('hide');
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.log('AJAX Error Details:');
            console.log('Status:', textStatus);
            console.log('Error:', errorThrown);
            console.log('Response Text:', jqXHR.responseText);
            console.log('Status Code:', jqXHR.status);
            
            let errorMessage = 'Terjadi kesalahan saat mengirim data.';
            
            try {
                if (jqXHR.responseText) {
                    const errorResponse = JSON.parse(jqXHR.responseText);
                    if (errorResponse.message) {
                        errorMessage = 'Error: ' + errorResponse.message;
                    }
                }
            } catch (parseError) {
                console.log('Could not parse error response as JSON');
                errorMessage = 'Server Error: ' + jqXHR.responseText.substring(0, 200);
            }
            
            alert(errorMessage);
        },
        complete: function() {
            tombolSimpan.prop('disabled', false).html('Simpan Pendaftaran');
        }
    });
});
        // Menandai menu aktif berdasarkan parameter URL
        $(document).ready(function () {
            const urlParams = new URLSearchParams(window.location.search);
            const currentPage = urlParams.get('page') || 'dashboard_utama';

            $('.nav-item').removeClass('active');
            $(`.nav-item a[href="?page=${currentPage}"]`).parent().addClass('active');
        });

    </script>
</body>

</html>