-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 04, 2025 at 04:19 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kdmpgs - v2`
--

-- --------------------------------------------------------

--
-- Table structure for table `anggota`
--

CREATE TABLE `anggota` (
  `id` int(11) NOT NULL,
  `no_anggota` varchar(50) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `jenis_kelamin` enum('Laki-Laki','Perempuan') NOT NULL,
  `tempat_lahir` varchar(100) DEFAULT NULL,
  `tanggal_lahir` date NOT NULL,
  `nik` varchar(20) DEFAULT NULL,
  `npwp` varchar(20) DEFAULT NULL,
  `agama` varchar(50) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `rw` varchar(10) DEFAULT NULL,
  `rt` varchar(10) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `status_anggota` enum('aktif','non-aktif','dibekukan') DEFAULT 'aktif',
  `pekerjaan` varchar(20) NOT NULL,
  `simpanan_wajib` decimal(15,2) NOT NULL DEFAULT 0.00,
  `saldo_simpanan` decimal(15,2) DEFAULT 0.00,
  `poin` int(11) DEFAULT 0,
  `tanggal_daftar` datetime DEFAULT current_timestamp(),
  `foto_diri` varchar(255) DEFAULT NULL,
  `foto_ktp` varchar(255) DEFAULT NULL,
  `foto_kk` varchar(255) DEFAULT NULL,
  `tanggal_join` date DEFAULT NULL,
  `tanggal_jatuh_tempo` date DEFAULT NULL,
  `status` varchar(50) NOT NULL,
  `saldo_sukarela` decimal(10,0) NOT NULL,
  `saldo_total` decimal(10,0) NOT NULL,
  `status_keanggotaan` varchar(20) NOT NULL DEFAULT 'Aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `anggota`:
--

--
-- Triggers `anggota`
--
DELIMITER $$
CREATE TRIGGER `set_jatuh_tempo_awal` BEFORE INSERT ON `anggota` FOR EACH ROW BEGIN
    -- Hanya atur jika script tidak mengisinya (jika NULL)
    IF NEW.tanggal_jatuh_tempo IS NULL THEN
        SET NEW.tanggal_jatuh_tempo = DATE_ADD(NEW.tanggal_join, INTERVAL 1 MONTH);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id` int(11) NOT NULL,
  `anggota_id` int(11) NOT NULL,
  `id_transaksi` varchar(30) DEFAULT NULL,
  `jenis_simpanan` varchar(20) NOT NULL,
  `jenis_transaksi` varchar(20) NOT NULL,
  `jumlah` decimal(15,2) NOT NULL DEFAULT 0.00,
  `nama_anggota` varchar(50) DEFAULT NULL,
  `tanggal_bayar` datetime DEFAULT NULL,
  `bulan_periode` varchar(20) DEFAULT NULL,
  `metode` enum('cash','transfer') DEFAULT NULL,
  `bukti` varchar(255) DEFAULT NULL,
  `status` varchar(100) DEFAULT NULL,
  `keterangan` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `pembayaran`:
--   `anggota_id`
--       `anggota` -> `id`
--   `anggota_id`
--       `anggota` -> `id`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `pengurus` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('bendahara','ketua','sekretaris','usaha','anggota') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `users`:
--

--
-- Dumping data for table `users`
--

INSERT INTO `pengurus` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'KDMP_Pengurus_ba1', '$2y$10$1Bq29ZJ3kI0LR.pIaVq2d.bcWrz0vNWq5RoGa/TxZ3GDGqJhWTo.O', 'bendahara', '2025-08-30 05:47:21'),
(2, 'KDMP_Pengurus_ketua1', '$2y$10$APYlPUzrfqNDtxK2mFWSye6gaOV5AS67lOej9WNzhitdgyrvv1e6K', 'ketua', '2025-08-30 05:51:46'),
(3, 'KDMP_Pengurus_usaha1', '$2y$10$PXlVsbnep9CG4NGwOar/0Oo0MzGg2sBVTvDaigMU6ppgoTimNsJmq', '', '2025-08-30 05:56:04'),
(4, 'KDMP_Pengurus_sekre1', '$2y$10$33zUf3FPKkPBBUa5tMr4wuRSi/rNW0Mxm.6350kE8yB1k6Mvqi58u', '', '2025-08-30 05:56:06');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `anggota`
--
ALTER TABLE `anggota`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_anggota` (`no_anggota`);

--
-- Indexes for table `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pembayaran_anggota` (`anggota_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `anggota`
--
ALTER TABLE `anggota`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD CONSTRAINT `fk_pembayaran_anggota` FOREIGN KEY (`anggota_id`) REFERENCES `anggota` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pembayaran_ibfk_1` FOREIGN KEY (`anggota_id`) REFERENCES `anggota` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
