<?php
session_start();
require 'koneksi/koneksi.php';
require_once 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;
date_default_timezone_set("Asia/Jakarta");
if (isset($_GET['id'])) {
    $id_so = intval($_GET['id']);

    // Query data SO
    $query = "SELECT so.*, p.username as nama_petugas, ap.username as nama_approver
             FROM stock_opname_header so
             LEFT JOIN pengurus p ON so.id_petugas = p.id
             LEFT JOIN pengurus ap ON so.approved_by = ap.id
             WHERE so.id_so = '$id_so'";
    $result = $conn->query($query);
    $so = $result->fetch_assoc();

    // Query detail SO
    $query_detail = "SELECT sod.*, ir.nama_produk, ir.no_batch, ir.satuan_kecil
                    FROM stock_opname_detail sod
                    JOIN inventory_ready ir ON sod.id_inventory = ir.id_inventory
                    WHERE sod.id_so = '$id_so'";
    $result_detail = $conn->query($query_detail);

    // HTML content untuk PDF
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Laporan Stock Opname - ' . $so['no_so'] . '</title>
        <style>
            body { 
                font-family: "DejaVu Sans", sans-serif; 
                margin: 20px; 
                font-size: 12px;
            }
            .header { 
                text-align: center; 
                margin-bottom: 30px; 
                border-bottom: 2px solid #000; 
                padding-bottom: 10px; 
            }
            .table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-bottom: 20px; 
                font-size: 10px;
            }
            .table th, .table td { 
                border: 1px solid #000; 
                padding: 6px; 
                text-align: left; 
            }
            .table th { 
                background-color: #f2f2f2; 
                font-weight: bold;
            }
            .footer { 
                margin-top: 50px; 
            }
            .signature { 
                width: 100%; 
                margin-top: 50px; 
            }
            .signature td { 
                width: 50%; 
                vertical-align: top; 
                text-align: center;
            }
            .selisih-positif { 
                color: #28a745; 
                font-weight: bold;
            }
            .selisih-negatif { 
                color: #dc3545; 
                font-weight: bold;
            }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .mb-3 { margin-bottom: 15px; }
            .mt-3 { margin-top: 15px; }
            .page-break { page-break-after: always; }
        </style>
    </head>
    <body>
        <div class="header">
            <h2 style="margin-bottom: 5px;">LAPORAN STOCK OPNAME</h2>
            <h3 style="margin-bottom: 10px;">KOPERASI DESA MERAH PUTIH GANJAR SABAR</h3>
            <p style="margin-bottom: 5px;">No. SO: <strong>' . $so['no_so'] . '</strong></p>
            <p style="margin-bottom: 5px;">Tanggal: ' . date('d/m/Y', strtotime($so['tanggal_so'])) . '</p>
        </div>

        <div class="mb-3">
            <table style="width: 100%; margin-bottom: 15px;">
                <tr>
                    <td style="width: 30%;"><strong>Tanggal Stock Opname</strong></td>
                    <td>' . date('d/m/Y', strtotime($so['tanggal_so'])) . '</td>
                </tr>
                <tr>
                    <td><strong>Periode</strong></td>
                    <td>' . ucfirst($so['periode_so']) . '</td>
                </tr>
                <tr>
                    <td><strong>Petugas</strong></td>
                    <td>' . $so['nama_petugas'] . '</td>
                </tr>
                <tr>
                    <td><strong>Status</strong></td>
                    <td>' . strtoupper(str_replace('_', ' ', $so['status'])) . '</td>
                </tr>';

    if ($so['approved_by']) {
        $html .= '
                    <tr>
                        <td><strong>Disetujui oleh</strong></td>
                        <td>' . $so['nama_approver'] . '</td>
                    </tr>
                    <tr>
                        <td><strong>Tanggal Approval</strong></td>
                        <td>' . date('d/m/Y H:i', strtotime($so['approved_at'])) . '</td>
                    </tr>';
    }

    $html .= '
            </table>
        </div>

        <h4 style="margin-bottom: 10px;">Detail Stock Opname</h4>
        <table class="table">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="25%">Nama Produk</th>
                    <th width="15%">No. Batch</th>
                    <th width="10%">Stok Sistem</th>
                    <th width="10%">Stok Fisik</th>
                    <th width="10%">Selisih</th>
                    <th width="15%">Status Kondisi</th>
                    <th width="20%">Analisis Penyebab</th>
                </tr>
            </thead>
            <tbody>';

    $no = 1;
    while ($detail = $result_detail->fetch_assoc()) {
        $selisih_class = '';
        if ($detail['selisih'] > 0) {
            $selisih_class = 'selisih-positif';
        } elseif ($detail['selisih'] < 0) {
            $selisih_class = 'selisih-negatif';
        }

        $html .= '
                <tr>
                    <td class="text-center">' . $no++ . '</td>
                    <td>' . htmlspecialchars($detail['nama_produk']) . '</td>
                    <td>' . $detail['no_batch'] . '</td>
                    <td class="text-right">' . number_format($detail['stok_sistem'], 2) . ' ' . $detail['satuan_kecil'] . '</td>
                    <td class="text-right">' . number_format($detail['stok_fisik'], 2) . ' ' . $detail['satuan_kecil'] . '</td>
                    <td class="text-right ' . $selisih_class . '">' . number_format($detail['selisih'], 2) . ' ' . $detail['satuan_kecil'] . '</td>
                    <td>' . $detail['status_kondisi'] . '</td>
                    <td>' . ($detail['analisis_penyebab'] ?: '-') . '</td>
                </tr>';
    }

    $html .= '
            </tbody>
        </table>';

    // Summary section
    $html .= '
        <div class="mt-3">
            <table style="width: 50%; margin-left: auto; border: 1px solid #000; border-collapse: collapse;">
                <tr>
                    <td style="border: 1px solid #000; padding: 8px; background-color: #f2f2f2;"><strong>SUMMARY</strong></td>
                    <td style="border: 1px solid #000; padding: 8px; background-color: #f2f2f2;"><strong>JUMLAH</strong></td>
                </tr>
                <tr>
                    <td style="border: 1px solid #000; padding: 8px;">Total Item Diperiksa</td>
                    <td style="border: 1px solid #000; padding: 8px; text-align: center;">' . ($no - 1) . '</td>
                </tr>
            </table>
        </div>';

    if ($so['catatan']) {
        $html .= '
            <div class="mt-3">
                <p><strong>Catatan:</strong></p>
                <p style="border: 1px solid #ccc; padding: 10px; background-color: #f9f9f9;">' . nl2br(htmlspecialchars($so['catatan'])) . '</p>
            </div>';
    }

    $html .= '
        <div class="footer">
            <table class="signature">
                <tr>
                    <td>
                        <p>Petugas Stock Opname,</p>
                        <br><br><br>
                        <p><strong>' . $so['nama_petugas'] . '</strong></p>
                    </td>
                    <td>
                        <p>Mengetahui,</p>
                        <br><br><br>';

    if ($so['approved_by']) {
        $html .= '<p><strong>(__________________________)</strong></p>';
    } else {
        $html .= '<p><strong>(__________________________)</strong></p>';
    }

    $html .= '
                    </td>
                </tr>
            </table>
        </div>
        
        <div style="position: fixed; bottom: 20px; right: 20px; font-size: 10px; color: #666;">
            Dicetak pada: ' . date('d/m/Y H:i:s') . '
        </div>
    </body>
    </html>';

    // Konfigurasi DOMPDF
    $dompdf = new Dompdf();
    $dompdf->getOptions()->setIsRemoteEnabled(true);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Render PDF
    $dompdf->render();

    // Output PDF
    if (isset($_GET['download']) && $_GET['download'] == '1') {
        // Force download
        $dompdf->stream("SO_" . $so['no_so'] . ".pdf", array("Attachment" => true));
    } else {
        // View in browser
        $dompdf->stream("SO_" . $so['no_so'] . ".pdf", array("Attachment" => false));
    }

    exit;
} else {
    echo "ID Stock Opname tidak valid";
}
?>
