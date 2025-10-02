<!-- proses_login.php -->
<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);


$no_anggota = $_POST['no_anggota'];
$nik = $_POST['nik'];
$tanggal_lahir = $_POST['tanggal_lahir'];


$no_anggota = mysqli_real_escape_string($conn, $no_anggota);
$nik = mysqli_real_escape_string($conn, $nik);
$tanggal_lahir = mysqli_real_escape_string($conn, $tanggal_lahir);

$query = mysqli_query($conn, "SELECT * FROM anggota WHERE no_anggota='$no_anggota' AND nik='$nik' AND tanggal_lahir='$tanggal_lahir'");
$data = mysqli_fetch_assoc($query);


if ($data) {
    $_SESSION['id'] = $data['id'];
    $_SESSION['nama'] = $data['nama'];
    $_SESSION['no_anggota'] = $data['no_anggota'];
    header("Location: beranda.php");
    exit();
} else {
    echo "<script>alert('Nomor anggota, NIK atau tanggal lahir salah!'); window.location.href='index.php';</script>";
    exit();
}
?>