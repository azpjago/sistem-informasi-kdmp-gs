<?php
// export_anggota_excel.php
session_start();

// Cek role user - hanya ketua yang bisa akses
if ($_SESSION['role'] !== 'ketua') {
    header('Location: index.php');
    exit;
}

require 'vendor/autoload.php'; // Jika menggunakan PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// KUERI PERBAIKAN: Hitung total simpanan dari pembayaran, bukan dari field saldo_total
$query = "
    SELECT 
        a.id,
        a.nama,
        a.jenis_kelamin,
        a.no_hp,
        a.status_keanggotaan,
        COALESCE((
            SELECT SUM(p.jumlah) 
            FROM pembayaran p 
            WHERE p.anggota_id = a.no_anggota 
            AND (p.status_bayar = 'Lunas' OR p.status = 'Lunas')
            AND p.jenis_transaksi = 'setor'
            AND p.jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib', 'Simpanan Sukarela')
        ), 0) as total_simpanan,
        COALESCE((
            SELECT SUM(p.jumlah) 
            FROM pembayaran p 
            WHERE p.no_anggota = a.id 
            AND (p.status_bayar = 'Lunas' OR p.status = 'Lunas')
            AND p.jenis_transaksi = 'tarik'
        ), 0) as total_tarikan,
        (COALESCE((
            SELECT SUM(p.jumlah) 
            FROM pembayaran p 
            WHERE p.anggota_id = a.id 
            AND (p.status_bayar = 'Lunas' OR p.status = 'Lunas')
            AND p.jenis_transaksi = 'setor'
            AND p.jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib', 'Simpanan Sukarela')
        ), 0) - COALESCE((
            SELECT SUM(p.jumlah) 
            FROM pembayaran p 
            WHERE p.anggota_id = a.id 
            AND (p.status_bayar = 'Lunas' OR p.status = 'Lunas')
            AND p.jenis_transaksi = 'tarik'
        ), 0)) as saldo_aktual
    FROM anggota a
    ORDER BY a.id
";

$result = $conn->query($query);

// Create new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set document properties
$spreadsheet->getProperties()
    ->setCreator('KDMPGS System')
    ->setLastModifiedBy('KDMPGS System')
    ->setTitle('Data Anggota Koperasi')
    ->setSubject('Data Anggota')
    ->setDescription('Export data anggota koperasi');

// Set header style
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF']
    ],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => ['rgb' => '2C3E50']
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
        ]
    ]
];

// Set data style
$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
        ]
    ]
];

// Set column headers
$headers = [
    'No Anggota',
    'Nama Lengkap',
    'Jenis Kelamin',
    'No HP',
    'Status Keanggotaan',
    'Total Simpanan',
    'Total Tarikan',
    'Saldo Aktual'
];

$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

// Add data
$row = 2;
$total_simpanan = 0;
$total_tarikan = 0;
$total_saldo = 0;

while ($anggota = $result->fetch_assoc()) {
    $sheet->setCellValue('A' . $row, $anggota['no_anggota']);
    $sheet->setCellValue('B' . $row, $anggota['nama']);
    $sheet->setCellValue('C' . $row, $anggota['jenis_kelamin']);
    $sheet->setCellValue('D' . $row, $anggota['no_hp']);
    $sheet->setCellValue('E' . $row, $anggota['status_keanggotaan']);
    $sheet->setCellValue('F' . $row, $anggota['total_simpanan']);
    $sheet->setCellValue('G' . $row, $anggota['total_tarikan']);
    $sheet->setCellValue('H' . $row, $anggota['saldo_aktual']);

    $total_simpanan += $anggota['total_simpanan'];
    $total_tarikan += $anggota['total_tarikan'];
    $total_saldo += $anggota['saldo_aktual'];

    $row++;
}

// Add summary row
$sheet->setCellValue('E' . $row, 'TOTAL:');
$sheet->setCellValue('F' . $row, $total_simpanan);
$sheet->setCellValue('G' . $row, $total_tarikan);
$sheet->setCellValue('H' . $row, $total_saldo);

$sheet->getStyle('E' . $row . ':H' . $row)->applyFromArray($headerStyle);

// Set column widths
$sheet->getColumnDimension('A')->setWidth(15);
$sheet->getColumnDimension('B')->setWidth(25);
$sheet->getColumnDimension('C')->setWidth(15);
$sheet->getColumnDimension('D')->setWidth(15);
$sheet->getColumnDimension('E')->setWidth(20);
$sheet->getColumnDimension('F')->setWidth(15);
$sheet->getColumnDimension('G')->setWidth(15);
$sheet->getColumnDimension('H')->setWidth(15);

// Format currency columns
$sheet->getStyle('F2:H' . $row)
    ->getNumberFormat()
    ->setFormatCode('#,##0');

// Apply data style to all cells
$sheet->getStyle('A1:H' . $row)->applyFromArray($dataStyle);

// Set auto filter
$sheet->setAutoFilter('A1:H' . ($row - 1));

// Set file name and headers
$filename = 'data_anggota_koperasi_' . date('Y-m-d_H-i-s') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

$conn->close();
exit;
?>