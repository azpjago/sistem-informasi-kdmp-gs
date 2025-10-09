<?php
// --- Menghitung Data Untuk Kartu Statistik ---
$result_anggota = $conn->query("SELECT COUNT(id) as total_anggota FROM anggota");
$total_anggota = $result_anggota->fetch_assoc()['total_anggota'];

// Perbaikan query untuk menghitung total simpanan
$sql_totals = "
SELECT
    (SELECT IFNULL(SUM(jumlah), 0) FROM pembayaran WHERE jenis_simpanan = 'Simpanan Wajib') AS total_wajib,
    (SELECT IFNULL(SUM(jumlah), 0) FROM pembayaran WHERE jenis_simpanan = 'Simpanan Pokok') AS total_pokok,
    (SELECT IFNULL(SUM(saldo_sukarela), 0) FROM anggota) AS total_sukarela
";
$result_totals = $conn->query($sql_totals);
$totals = $result_totals->fetch_assoc();
$total_wajib = $totals['total_wajib'];
$total_pokok = $totals['total_pokok'];
$total_sukarela = $totals['total_sukarela'];

// =====================================================================
// == PERUBAHAN: Mengambil data untuk grafik batang pakai kolom bulan_periode ==
// =====================================================================
$current_year = date('Y');

// Siapkan array 12 bulan untuk setiap jenis simpanan & penghitungnya
$pemasukan_wajib = array_fill(0, 12, 0);
$pemasukan_pokok = array_fill(0, 12, 0);
$pemasukan_sukarela = array_fill(0, 12, 0);
$penghitung_wajib = array_fill(0, 12, 0);
$penghitung_pokok = array_fill(0, 12, 0);
$penghitung_sukarela = array_fill(0, 12, 0);

// Cek struktur data di tabel pembayaran (debug non-invasif, aman di HTML)
$check_data = $conn->query("SELECT DISTINCT jenis_simpanan FROM pembayaran LIMIT 5");
echo "<!-- Debug jenis_simpanan: ";
while ($row = $check_data->fetch_assoc()) {
    echo $row['jenis_simpanan'] . " ";
}
echo " -->";

// 1) SIMPANAN WAJIB per bulan dari tabel pembayaran (pakai bulan_periode)
$sql_wajib = "
    SELECT 
        MONTH(bulan_periode) as bulan, 
        SUM(jumlah) as total, 
        COUNT(id) as penghitung
    FROM pembayaran 
    WHERE YEAR(bulan_periode) = ? 
      AND (jenis_simpanan = 'Simpanan Wajib' OR jenis_simpanan = 'wajib')
    GROUP BY bulan
";
$stmt_wajib = $conn->prepare($sql_wajib);
$stmt_wajib->bind_param("i", $current_year);
$stmt_wajib->execute();
$result_wajib = $stmt_wajib->get_result();
while ($row = $result_wajib->fetch_assoc()) {
    $pemasukan_wajib[$row['bulan'] - 1] = (int) $row['total'];
    $penghitung_wajib[$row['bulan'] - 1] = (int) $row['penghitung'];
}
$stmt_wajib->close();

// 2) SIMPANAN POKOK per bulan
$sql_pokok = "
    SELECT 
        MONTH(bulan_periode) as bulan, 
        SUM(jumlah) as total, 
        COUNT(id) as penghitung
    FROM pembayaran 
    WHERE YEAR(bulan_periode) = ? 
      AND (jenis_simpanan = 'Simpanan Pokok' OR jenis_simpanan = 'pokok')
    GROUP BY bulan
";
$stmt_pokok = $conn->prepare($sql_pokok);
$stmt_pokok->bind_param("i", $current_year);
$stmt_pokok->execute();
$result_pokok = $stmt_pokok->get_result();
while ($row = $result_pokok->fetch_assoc()) {
    $pemasukan_pokok[$row['bulan'] - 1] = (int) $row['total'];
    $penghitung_pokok[$row['bulan'] - 1] = (int) $row['penghitung'];
}
$stmt_pokok->close();

// 3) SIMPANAN SUKARELA per bulan
$sql_sukarela = "
    SELECT 
        MONTH(bulan_periode) as bulan, 
        SUM(jumlah) as total, 
        COUNT(id) as penghitung
    FROM pembayaran 
    WHERE YEAR(bulan_periode) = ? 
      AND (
        (jenis_simpanan = 'Simpanan Sukarela' OR jenis_simpanan = 'sukarela')
        OR 
        (jenis_transaksi = 'setor' AND jenis_simpanan LIKE '%sukarela%')
      )
    GROUP BY bulan
";
$stmt_sukarela = $conn->prepare($sql_sukarela);
$stmt_sukarela->bind_param("i", $current_year);
$stmt_sukarela->execute();
$result_sukarela = $stmt_sukarela->get_result();
while ($row = $result_sukarela->fetch_assoc()) {
    $pemasukan_sukarela[$row['bulan'] - 1] = (int) $row['total'];
    $penghitung_sukarela[$row['bulan'] - 1] = (int) $row['penghitung'];
}
$stmt_sukarela->close();

// --- Data untuk Grafik Status Anggota ---
$sql_status = "
    SELECT SUM(CASE WHEN tanggal_jatuh_tempo >= CURDATE() THEN 1 ELSE 0 END) as total_aktif,
           SUM(CASE WHEN tanggal_jatuh_tempo < CURDATE() THEN 1 ELSE 0 END) as total_jatuh_tempo
    FROM anggota";
$result_status = $conn->query($sql_status);
$data_status = $result_status->fetch_assoc();

function format_rupiah($angka)
{
    return "Rp. " . number_format($angka, 0, ',', '.');
}
?>

<h3 class="mb-4">🏠 Dashboard Utama</h3>

<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card text-white bg-primary h-100">
            <div class="card-body">
                <h5 class="card-title">👥 Jumlah Anggota</h5>
                <h2><?= $total_anggota ?></h2>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card text-white bg-success h-100">
            <div class="card-body">
                <h5 class="card-title">💰 Simpanan Wajib Terkumpul</h5>
                <h4><?= format_rupiah($total_wajib) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card text-white bg-warning h-100">
            <div class="card-body">
                <h5 class="card-title">🏦 Simpanan Pokok</h5>
                <h4><?= format_rupiah($total_pokok) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card text-white bg-info h-100">
            <div class="card-body">
                <h5 class="card-title">💸 Simpanan Sukarela</h5>
                <h4><?= format_rupiah($total_sukarela) ?></h4>
            </div>
        </div>
    </div>
</div>

<div class="row mt-2">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">Grafik Pemasukan Bulanan (Tahun <?= $current_year ?>)</div>
            <div class="card-body">
                <canvas id="grafikPemasukan"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Grafik Status Anggota</div>
            <div class="card-body">
                <canvas id="grafikStatus"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Mengambil semua data dari PHP ke JavaScript
    var dataWajib = <?= json_encode(array_values($pemasukan_wajib)) ?>;
    var dataPokok = <?= json_encode(array_values($pemasukan_pokok)) ?>;
    var dataSukarela = <?= json_encode(array_values($pemasukan_sukarela)) ?>;
    var dataPenghitung = {
        wajib: <?= json_encode(array_values($penghitung_wajib)) ?>,
        pokok: <?= json_encode(array_values($penghitung_pokok)) ?>,
        sukarela: <?= json_encode(array_values($penghitung_sukarela)) ?>
    };
    var dataStatus = [<?= (int) ($data_status['total_aktif'] ?? 0) ?>, <?= (int) ($data_status['total_jatuh_tempo'] ?? 0) ?>];

    // Debug: Tampilkan data di console
    console.log('Data untuk grafik:');
    console.log('Wajib:', dataWajib);
    console.log('Pokok:', dataPokok);
    console.log('Sukarela:', dataSukarela);
    console.log('Penghitung:', dataPenghitung);

    // Pastikan canvas ada sebelum membuat chart
    if (document.getElementById('grafikPemasukan')) {
        // Grafik Pemasukan
        var ctx1 = document.getElementById('grafikPemasukan').getContext('2d');
        var grafikPemasukan = new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
                datasets: [
                    {
                        label: 'Simpanan Wajib',
                        data: dataWajib,
                        backgroundColor: 'rgba(255, 99, 132, 0.5)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Simpanan Pokok',
                        data: dataPokok,
                        backgroundColor: 'rgba(255, 205, 86, 0.5)',
                        borderColor: 'rgba(255, 205, 86, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Simpanan Sukarela',
                        data: dataSukarela,
                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: { 
                    y: { 
                        beginAtZero: true, 
                        ticks: { 
                            callback: function(value) { 
                                return 'Rp ' + new Intl.NumberFormat('id-ID').format(value); 
                            } 
                        } 
                    } 
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                let value = context.parsed.y;
                                let formattedValue = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
                                let penghitung = 0;
                                
                                if (label === 'Simpanan Wajib') {
                                    penghitung = dataPenghitung.wajib[context.dataIndex] || 0;
                                } else if (label === 'Simpanan Pokok') {
                                    penghitung = dataPenghitung.pokok[context.dataIndex] || 0;
                                } else if (label === 'Simpanan Sukarela') {
                                    penghitung = dataPenghitung.sukarela[context.dataIndex] || 0;
                                }
                                
                                return `${label}: ${formattedValue} (${penghitung} Transaksi)`;
                            }
                        }
                    }
                }
            }
        });
    }

    // Grafik Status
    if (document.getElementById('grafikStatus')) {
        var ctx2 = document.getElementById('grafikStatus').getContext('2d');
        var grafikStatus = new Chart(ctx2, {
            type: 'pie',
            data: {
                labels: ['Aktif', 'Jatuh Tempo'],
                datasets: [{
                    label: 'Status Anggota',
                    data: dataStatus,
                    backgroundColor: ['rgba(40, 167, 69, 0.7)', 'rgba(220, 53, 69, 0.7)'],
                    borderColor: ['rgba(40, 167, 69, 1)', 'rgba(220, 53, 69, 1)'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true
            }
        });
    }
});
</script>
