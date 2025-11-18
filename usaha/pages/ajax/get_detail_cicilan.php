<?php
session_start();
if ($_SESSION['role'] !== 'usaha')
    exit;

$conn = new mysqli('localhost', 'root', '', 'kdmpgs');
$id_pinjaman = intval($_GET['id'] ?? 0);
$id_cicilan = intval($_GET['id'] ?? 0);

$query = "
    SELECT 
        c.*,
        p.id_pinjaman,
        p.id_anggota,
        p.jumlah_pinjaman,
        a.nama,
        a.no_anggota,
        DATE_FORMAT(c.jatuh_tempo, '%d/%m/%Y') as jatuh_tempo_formatted
    FROM cicilan c
    JOIN pinjaman p ON c.id_pinjaman = p.id_pinjaman
    JOIN anggota a ON p.id_anggota = a.id
    WHERE c.id_cicilan = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_cicilan);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $cicilan = $result->fetch_assoc();
    header('Content-Type: application/json');
    echo json_encode($cicilan);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Data cicilan tidak ditemukan']);
}
?>
