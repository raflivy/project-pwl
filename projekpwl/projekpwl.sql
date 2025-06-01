-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 01, 2025 at 10:43 PM
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
-- Database: `projekpwl`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'rafli', '$2y$10$4CFm4qg0iYV8P8BVE1aEjeqvPypVryLXIe79VO80Jv9uKfFAWVIee', '2025-05-25 14:17:09'),
(2, 'admin', '$2y$10$cHmyju4PRmerrW87phKjLuisMlAcnbC2665GDluwVZWvxVnf4.CRG', '2025-05-25 14:39:09');

-- --------------------------------------------------------

--
-- Table structure for table `item_pesanan`
--

CREATE TABLE `item_pesanan` (
  `id` int(11) NOT NULL,
  `id_pesanan` int(11) NOT NULL,
  `id_produk` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `harga` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item_pesanan`
--

INSERT INTO `item_pesanan` (`id`, `id_pesanan`, `id_produk`, `qty`, `harga`) VALUES
(1, 1, 12, 1, 20000.00),
(2, 2, 11, 1, 20000.00),
(3, 3, 8, 1, 20000.00);

-- --------------------------------------------------------

--
-- Table structure for table `pesanan`
--

CREATE TABLE `pesanan` (
  `id` int(11) NOT NULL,
  `nama_pelanggan` varchar(100) NOT NULL,
  `no_telp` varchar(20) NOT NULL,
  `alamat_pelanggan` text NOT NULL,
  `total_belanja` decimal(10,2) NOT NULL,
  `waktu_pesanan` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('menunggu','selesai','dibatalkan') DEFAULT 'menunggu'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pesanan`
--

INSERT INTO `pesanan` (`id`, `nama_pelanggan`, `no_telp`, `alamat_pelanggan`, `total_belanja`, `waktu_pesanan`, `status`) VALUES
(1, 'a', '123', 'aaa', 20000.00, '2025-05-25 21:02:46', 'menunggu'),
(2, 'aaa', 'a', 'aaa', 20000.00, '2025-05-25 21:02:59', 'menunggu'),
(3, 'z', 'z', 'aa', 20000.00, '2025-05-25 21:03:13', 'menunggu');

-- --------------------------------------------------------

--
-- Table structure for table `produk`
--

CREATE TABLE `produk` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `harga` int(11) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `produk`
--

INSERT INTO `produk` (`id`, `nama`, `deskripsi`, `harga`, `stock`, `image`, `created_at`, `updated_at`) VALUES
(1, 'Nasi Gudeg', 'Nangka muda manis legit dimasak santan dan gula aren, disajikan dengan lauk lengkap seperti ayam, telur, dan sambal krecek. Cita rasa otentik Yogyakarta.', 15000, 50, 'uploads/products/product_682f8b157c8df.webp', '2025-05-22 15:47:14', '2025-05-25 11:48:48'),
(2, 'Soto Banjar', 'Soto ayam hangat khas Kalimantan Selatan dengan kuah bening kaya rempah. Disajikan dengan ayam suwir, perkedel, dan nasi, perpaduan gurih otentik yang menghangatkan.', 15000, 20, 'uploads/products/product_682f95efb6cd9.jpg', '2025-05-22 15:47:14', '2025-05-25 14:17:55'),
(3, 'Rendang', 'Rendang daging dimasak lama dengan santan dan rempah pekat hingga bumbu meresap. Hasilnya: kaya rasa, pedas-gurih, mahakarya kuliner Minang.', 25000, 20, 'uploads/products/product_682f960562564.jpg', '2025-05-22 15:47:14', '2025-05-25 11:49:49'),
(4, 'Gado-gado', 'Campuran sayuran rebus segar disajikan dengan irisan kentang, tahu, tempe, telur rebus, dan kerupuk, semua disiram saus kacang kental dan gurih. Rasanya segar, kaya tekstur, dan penuh cita rasa.', 10000, 40, 'uploads/products/product_682f9611eabd5.jpg', '2025-05-22 15:47:14', '2025-05-25 11:50:36'),
(5, 'Nasi Liwet', 'Nasi gurih yang dimasak dengan santan, serai, daun salam, dan teri Medan, menciptakan aroma dan rasa yang kaya. Disajikan dengan berbagai lauk seperti ayam suwir, telur pindang, tahu tempe, dan sambal, menjadikannya hidangan yang lezat dan komplit.', 18000, 25, 'uploads/products/product_682f9628abbf9.jpg', '2025-05-22 15:47:14', '2025-05-25 11:51:02'),
(8, 'Nasi Kuning', '[BEST SELLER] Nasi Kuning Khas Kalimantan. Kelezatan nasi kuning pulen kaya rempah dari Borneo, disajikan dengan beragam lauk. Nyaman banar!!', 20000, 49, 'uploads/products/product_682f83f7f142b.webp', '2025-05-22 20:07:19', '2025-05-25 21:03:13'),
(11, 'Mie Bancir', 'look like spageti, but from kalimantan. Rasanya gurih, kaya rempah, dengan topping ayam kampung dan telur bebek. Unik dan must try!!', 20000, 19, 'uploads/products/product_682f9836539b2.jpg', '2025-05-22 21:33:42', '2025-05-25 21:02:59'),
(12, 'Bingka', 'Kue lembut dari kentang, santan, dan telur, dipanggang dengan arang hingga harum dengan tekstur legit dan rasa manis legit. Aromanya khas, menggoda selera.', 20000, 14, 'uploads/products/product_683303133ef86.webp', '2025-05-25 11:46:27', '2025-05-25 21:02:46');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `item_pesanan`
--
ALTER TABLE `item_pesanan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_pesanan` (`id_pesanan`),
  ADD KEY `id_produk` (`id_produk`);

--
-- Indexes for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `item_pesanan`
--
ALTER TABLE `item_pesanan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `pesanan`
--
ALTER TABLE `pesanan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `produk`
--
ALTER TABLE `produk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `item_pesanan`
--
ALTER TABLE `item_pesanan`
  ADD CONSTRAINT `item_pesanan_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `item_pesanan_ibfk_2` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
