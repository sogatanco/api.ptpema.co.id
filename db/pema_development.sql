-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 22, 2023 at 10:04 AM
-- Server version: 10.4.24-MariaDB
-- PHP Version: 8.1.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pema_development`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_bases`
--

CREATE TABLE `activity_bases` (
  `base_id` int(10) UNSIGNED NOT NULL,
  `base_description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_year` year(4) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `employe_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_bases`
--

INSERT INTO `activity_bases` (`base_id`, `base_description`, `base_year`, `active`, `employe_id`, `created_at`, `updated_at`) VALUES
(2, 'Perintah Gubernur/ Pemegang Saham atau Perintah Pemerintah Aceh melalui Sekda atau Perintah DPRA atau Saran Komisaris (Dilengkapi dengan notulensi pertemuan, teks elektronik, surat resmi)', 2023, 1, NULL, '2023-09-14 09:34:11', '2023-09-14 09:34:11'),
(3, 'Bisnis Plan PT. Pembangunan Aceh Tahun 2022-2027 berupa [pilih BP]', 2023, 1, NULL, '2023-09-14 09:34:27', '2023-09-14 09:34:27'),
(4, 'Surat-Menyurat', 2023, 1, NULL, '2023-09-14 09:35:18', '2023-09-14 09:35:18'),
(5, 'Memo Dirut/ Direksi', 2023, 1, NULL, '2023-09-14 09:36:04', '2023-09-14 09:36:04'),
(6, 'Memo Manager/ Sekper/ SPI', 2023, 1, NULL, '2023-09-14 09:36:25', '2023-09-14 09:36:25');

-- --------------------------------------------------------

--
-- Table structure for table `activity_levels`
--

CREATE TABLE `activity_levels` (
  `level_id` smallint(5) UNSIGNED NOT NULL,
  `level_desc` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `employe_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_levels`
--

INSERT INTO `activity_levels` (`level_id`, `level_desc`, `employe_id`, `created_at`, `updated_at`) VALUES
(1, 'Mendesak', '123456223K', '2023-09-15 03:22:23', '2023-09-15 03:22:23'),
(2, 'Mengikuti Jadwal Kerja', '123456223K', '2023-09-15 03:22:23', '2023-09-15 03:22:23'),
(3, 'Biasa', '123456223K', '2023-09-15 03:23:11', '2023-09-15 03:23:11');

-- --------------------------------------------------------

--
-- Table structure for table `board_organizations`
--

CREATE TABLE `board_organizations` (
  `board_id` smallint(5) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `board_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `board_organizations`
--

INSERT INTO `board_organizations` (`board_id`, `company_id`, `board_name`, `created_at`, `updated_at`) VALUES
(1, 1, 'Pemegang Saham', '2023-09-14 02:48:07', '2023-09-14 02:48:07'),
(2, 1, 'Komisaris', '2023-09-14 02:48:07', '2023-09-14 02:48:07'),
(3, 1, 'Direksi', '2023-09-14 02:49:19', '2023-09-14 02:49:19'),
(4, 1, 'Manajer Eksekutif', '2023-09-14 02:49:43', '2023-09-14 02:49:43'),
(5, 1, 'Manajer', '2023-09-14 02:50:08', '2023-09-14 02:50:08'),
(6, 1, 'Supervisor', '2023-09-14 02:50:24', '2023-09-14 02:50:24'),
(7, 1, 'Staf', '2023-09-14 02:50:40', '2023-09-14 02:50:40'),
(8, 1, 'Sekretariat Perusahaan', '2023-09-14 02:50:57', '2023-09-14 02:50:57'),
(9, 1, 'Satuan Pengawas Internal', '2023-09-14 02:51:21', '2023-09-14 02:51:21'),
(10, 1, 'Manajemen Resiko', '2023-09-14 02:51:40', '2023-09-14 02:51:40');

-- --------------------------------------------------------

--
-- Table structure for table `business_plans`
--

CREATE TABLE `business_plans` (
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `employe_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_number` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_desc` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `investment` double DEFAULT NULL,
  `df` double DEFAULT NULL,
  `irr` double DEFAULT NULL,
  `npv` double DEFAULT NULL,
  `pi` double DEFAULT NULL,
  `pbp` double DEFAULT NULL,
  `conclusion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `business_plans`
--

INSERT INTO `business_plans` (`business_id`, `employe_id`, `business_number`, `business_type`, `business_desc`, `investment`, `df`, `irr`, `npv`, `pi`, `pbp`, `conclusion`, `created_at`, `updated_at`) VALUES
(1, NULL, '101', 'Rencana Jangka Panjang', 'Efisiensi Energi WK-B', 211750000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:13:32', '2023-09-15 01:13:32'),
(2, NULL, '102', 'Rencana Jangka Pendek', 'Trading Kondensat', 111550000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:14:55', '2023-09-15 01:14:55'),
(3, NULL, '103', 'Rencana Jangka Pendek', 'Peternakan Ayam Petelur', 133050000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:14:55', '2023-09-15 01:14:55'),
(4, NULL, '104', 'Rencana Jangka Panjang', 'PI 10% WK-A', 168550000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:17:21', '2023-09-15 01:17:21'),
(5, NULL, '105', 'Rencana Jangka Panjang', 'PI 10% WK-Lhokseumawe', 168550000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:17:21', '2023-09-15 01:17:21'),
(6, NULL, '106', 'Rencana Jangka Panjang', 'PI 10% WK-NSO', 168550000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:19:17', '2023-09-15 01:19:17'),
(7, NULL, '107', 'Rencana Jangka Panjang', 'Penyetoran Modal 30% Saham PT PAG', 334100000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:19:17', '2023-09-15 01:19:17'),
(8, NULL, '108', 'Rencana Jangka Panjang', 'Revitalisasi Pabrik Kertas Kraft Aceh (KKA)', 338100000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:21:04', '2023-09-15 01:21:04'),
(9, NULL, '109', 'Rencana Jangka Panjang', 'Pengelolaan Hutan Berimbang PEMA-CHL', 196550000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:21:52', '2023-09-15 01:21:52'),
(10, NULL, '110', 'Rencana Jangka Panjang', 'Carbon Capture & Storage', 356800000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:22:37', '2023-09-15 01:22:37'),
(11, NULL, '111', 'Rencana Jangka Panjang', 'Efisiensi Energi KEK Arun', 174200000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:22:37', '2023-09-15 01:22:37'),
(12, NULL, '112', 'Rencana Jangka Menengah', 'Kerja sama PEMA-Medco', 146050000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:24:27', '2023-09-15 01:24:27'),
(13, NULL, '113', 'Rencana Jangka Menengah', 'Kerja sama Budidaya Udang Vannamei', 107850000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:25:29', '2023-09-15 01:25:29'),
(14, NULL, '114', 'Rencana Jangka Menengah', 'Penjualan Pupuk Non Subsidi', 142350000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:26:20', '2023-09-15 01:26:20'),
(15, NULL, '115', 'Rencana Jangka Menengah', 'KSP PPS Lampulo', 125350000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:27:22', '2023-09-15 01:27:22'),
(16, NULL, '116', 'Rencana Jangka Panjang', 'JV WKP Seulawah Agam', 160850000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:27:22', '2023-09-15 01:27:22'),
(17, NULL, '117', 'Rencana Jangka Panjang', 'Pembangkit Listrik Tenaga Bayu', 170850000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:28:46', '2023-09-15 01:28:46'),
(18, NULL, '118', 'Rencana Jangka Panjang', 'Pembangunan Pabrik Minyak Goreng', 202850000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:29:34', '2023-09-15 01:29:34'),
(19, NULL, '119', 'Rencana Jangka Panjang', 'Shorebase', 125850000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:30:37', '2023-09-15 01:30:37'),
(20, NULL, '120', 'Rencana Jangka Panjang', 'Kawasan Ekonomi Khusus (KEK) Arun', 0, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:30:37', '2023-09-15 01:30:37'),
(21, NULL, '121', 'Rencana Jangka Menengah', 'Penjualan Alat Kesehatan', 537033255, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:31:53', '2023-09-15 01:31:53'),
(22, NULL, '122', 'Rencana Jangka Panjang', 'Revitalisasi Tangki Kondensat eks-Arun', 3262500000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:32:36', '2023-09-15 01:32:36'),
(23, NULL, '123', 'Rencana Jangka Panjang', 'Pupuk Magnesium (Astra)', 500000000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:33:13', '2023-09-15 01:33:13'),
(24, NULL, '124', 'Rencana Jangka Menengah', 'Modern Intergated Farming', 5100000000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:33:58', '2023-09-15 01:33:58'),
(25, NULL, '125', 'Rencana Jangka Menengah', 'Trading Ikan', 1000000000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:34:38', '2023-09-15 01:34:38'),
(26, NULL, '126', 'Rencana Jangka Panjang', 'KIA Ladong Tahap', 943000000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:35:17', '2023-09-15 01:35:17'),
(27, NULL, '127', 'Rencana Jangka Panjang', 'Pengembangan Usaha Lainnya', 1000000000, 0, 0, 0, 0, 0, NULL, '2023-09-15 01:36:08', '2023-09-15 01:36:08');

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_email` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_logo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_banner` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`id`, `company_name`, `company_email`, `company_logo`, `company_banner`, `company_active`, `created_at`, `updated_at`) VALUES
(1, 'PT Pembangunan Aceh', 'contact@ptpema.co.id', '', '', 1, '2023-09-14 08:06:37', '2023-09-14 08:06:37');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employe_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `position_id` smallint(5) UNSIGNED NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `religion` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `birthday` date NOT NULL,
  `birthday_place` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `marital_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `img` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employe_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employe_id`, `user_id`, `position_id`, `first_name`, `last_name`, `gender`, `religion`, `birthday`, `birthday_place`, `marital_status`, `img`, `employe_active`, `created_at`, `updated_at`) VALUES
('123456223K', 1, 24, 'Maeke', 'Diya', 'Malec', 'Islam', '1990-12-01', 'Jogjakarta', 'single', 'agus-salim.jpg', 1, '2023-09-15 01:42:13', '2023-09-15 01:42:13');

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2019_12_14_000001_create_personal_access_tokens_table', 1),
(2, '2023_09_11_142704_create_companies_table', 1),
(3, '2023_09_11_162417_create_board_organizations_table', 1),
(4, '2023_09_11_162741_create_organizations_table', 1),
(5, '2023_09_11_163143_create_positions_table', 1),
(6, '2023_09_12_052910_create_users_table', 1),
(7, '2023_09_12_204228_create_employees_table', 1),
(8, '2023_09_12_214128_create_activity_levels_table', 1),
(9, '2023_09_13_165214_create_activity_bases_table', 1),
(10, '2023_09_13_165608_create_business_plans_table', 1),
(11, '2023_09_14_214421_create_projects_table', 1),
(12, '2023_09_14_215233_create_project_histories_table', 1);

-- --------------------------------------------------------

--
-- Table structure for table `organizations`
--

CREATE TABLE `organizations` (
  `organization_id` smallint(5) UNSIGNED NOT NULL,
  `board_id` smallint(5) UNSIGNED NOT NULL,
  `organization_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `organizations`
--

INSERT INTO `organizations` (`organization_id`, `board_id`, `organization_name`, `created_at`, `updated_at`) VALUES
(1, 1, 'Pemegang Saham', '2023-09-14 02:53:40', '2023-09-14 02:53:40'),
(2, 2, 'Komisaris Utama', '2023-09-14 02:53:40', '2023-09-14 02:53:40'),
(3, 2, 'Komisaris', '2023-09-14 02:54:43', '2023-09-14 02:54:43'),
(4, 3, 'Direktur Utama', '2023-09-14 02:55:22', '2023-09-14 02:55:22'),
(5, 3, 'Direktur Pengembangan Bisnis', '2023-09-14 02:56:02', '2023-09-14 02:56:02'),
(6, 3, 'Direktur Komersial', '2023-09-14 02:56:37', '2023-09-14 02:56:37'),
(7, 3, 'Direktur Umum dan Keuangan', '2023-09-14 02:56:58', '2023-09-14 02:56:58'),
(8, 4, 'Divisi Eksekutif Pengembangan Bisnis', '2023-09-14 02:57:25', '2023-09-14 02:57:25'),
(9, 4, 'Divisi Eksekutif Komersial', '2023-09-14 02:58:38', '2023-09-14 02:58:38'),
(10, 4, 'Divisi Eksekutif dan Keuangan', '2023-09-14 02:59:03', '2023-09-14 02:59:03'),
(11, 5, 'Divisi Keuangan', '2023-09-14 02:59:33', '2023-09-14 02:59:33'),
(12, 5, 'Divisi Agro dan Jasa Lainnya', '2023-09-14 03:02:04', '2023-09-14 03:02:04'),
(13, 5, 'Divisi Industri dan Perdagangan', '2023-09-14 03:02:38', '2023-09-14 03:02:38'),
(14, 5, 'Divisi Migas, Minerba dan EBTKE', '2023-09-14 03:03:08', '2023-09-14 03:03:08'),
(15, 5, 'Divisi Penelitian dan Pengembangan', '2023-09-14 03:03:41', '2023-09-14 03:03:41'),
(16, 5, 'Divisi Teknikal dan Operasi', '2023-09-14 03:06:19', '2023-09-14 03:06:19'),
(17, 5, 'Divisi Pemasaran', '2023-09-14 03:07:08', '2023-09-14 03:07:08'),
(18, 5, 'Divisi Pembinaan Anak Usaha/Afiliasi', '2023-09-14 03:07:37', '2023-09-14 03:07:37'),
(19, 5, 'Divisi Manajemen Investasi', '2023-09-14 03:08:25', '2023-09-14 03:08:25'),
(20, 5, 'Divisi Umum', '2023-09-14 03:08:46', '2023-09-14 03:08:46'),
(21, 5, 'Divisi Teknologi Informasi', '2023-09-14 03:09:09', '2023-09-14 03:09:09'),
(22, 5, 'Divisi Sumber Daya Manusia', '2023-09-14 03:09:33', '2023-09-14 03:09:33'),
(23, 8, 'Sekretariat Perusahaan', '2023-09-14 03:09:54', '2023-09-14 03:09:54'),
(24, 9, 'Kepala Satuan Pengawas Internal', '2023-09-14 03:10:51', '2023-09-14 03:10:51'),
(25, 10, 'Kepala Manajemen Resiko', '2023-09-14 03:11:22', '2023-09-14 03:11:22');

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `position_id` smallint(5) UNSIGNED NOT NULL,
  `organization_id` smallint(5) UNSIGNED NOT NULL,
  `position_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `positions`
--

INSERT INTO `positions` (`position_id`, `organization_id`, `position_name`, `created_at`, `updated_at`) VALUES
(1, 1, 'Pemegang Saham', '2023-09-14 03:15:06', '2023-09-14 03:15:06'),
(2, 3, 'Komisaris', '2023-09-14 03:15:06', '2023-09-14 03:15:06'),
(3, 4, 'Direktur Utama', '2023-09-14 03:17:31', '2023-09-14 03:17:31'),
(4, 5, 'Direktur Pengembangan Bisnis', '2023-09-14 03:18:03', '2023-09-14 03:18:03'),
(5, 6, 'Direktur Komersial', '2023-09-14 03:18:49', '2023-09-14 03:18:49'),
(6, 7, 'Direktur Umum dan Keuangan', '2023-09-14 03:20:18', '2023-09-14 03:20:18'),
(7, 8, 'Manajer Eksekutif Pengembangan Bisnis 1', '2023-09-14 03:20:51', '2023-09-14 03:20:51'),
(8, 8, 'Manajer Eksekutif Pengembangan Bisnis 2', '2023-09-14 03:23:21', '2023-09-14 03:23:21'),
(9, 9, 'Manajer Eksekutif Komersial 1', '2023-09-14 03:23:53', '2023-09-14 03:23:53'),
(10, 9, 'Manajer Eksekutif Komersial 2', '2023-09-14 03:24:31', '2023-09-14 03:24:31'),
(11, 10, 'Manajer Eksekutif Umum dan Keuangan 1', '2023-09-14 03:25:01', '2023-09-14 03:25:01'),
(12, 10, 'Manajer Eksekutif Umum dan Keuangan 2', '2023-09-14 03:26:11', '2023-09-14 03:26:11'),
(13, 11, 'Manajer Keuangan', '2023-09-14 03:26:40', '2023-09-14 03:26:40'),
(14, 11, 'Supervisor Keuangan', '2023-09-14 03:28:15', '2023-09-14 03:28:15'),
(15, 11, 'Staf Keuangan', '2023-09-14 03:28:36', '2023-09-14 03:28:36'),
(16, 12, 'Manajer Agro dan Jasa Lainnya', '2023-09-14 03:29:34', '2023-09-14 03:29:34'),
(17, 12, 'Supervisor Agro dan Jasa Lainnya', '2023-09-14 03:30:11', '2023-09-14 03:30:11'),
(18, 12, 'Staf Agro dan Jasa Lainnya', '2023-09-14 03:30:49', '2023-09-14 03:30:49'),
(19, 13, 'Manajer Industri dan Perdagangan', '2023-09-14 03:31:23', '2023-09-14 03:31:23'),
(20, 13, 'Supervisor Industri dan Perdagangan', '2023-09-14 03:32:23', '2023-09-14 03:32:23'),
(21, 13, 'Staf Industri dan Perdagangan', '2023-09-14 03:32:56', '2023-09-14 03:32:56'),
(22, 14, 'Manajer Migas, Minerba dan EBTKE', '2023-09-14 03:33:28', '2023-09-14 03:33:28'),
(23, 14, 'Supervisor Migas, Minerba dan EBTKE', '2023-09-14 03:34:13', '2023-09-14 03:34:13'),
(24, 14, 'Staf Migas, Minerba dan EBTKE', '2023-09-14 03:34:50', '2023-09-14 03:34:50'),
(25, 15, 'Manajer Penelitian dan Pengembangan', '2023-09-14 03:35:16', '2023-09-14 03:35:16'),
(26, 15, 'Supervisor Penelitian dan Pengembangan', '2023-09-14 03:35:59', '2023-09-14 03:35:59'),
(27, 15, 'Staf Penelitian dan Pengembangan', '2023-09-14 03:36:34', '2023-09-14 03:36:34'),
(28, 16, 'Manajer Teknikal dan Operasi', '2023-09-14 03:37:17', '2023-09-14 03:37:17'),
(29, 16, 'Supervisor Teknikal dan Operasi', '2023-09-14 03:37:44', '2023-09-14 03:37:44'),
(30, 16, 'Staf Teknikal dan Operasi', '2023-09-14 03:38:12', '2023-09-14 03:38:12'),
(31, 17, 'Manajer Pemasaran', '2023-09-14 03:38:39', '2023-09-14 03:38:39'),
(32, 17, 'Supervisor Pemasaran', '2023-09-14 03:40:06', '2023-09-14 03:40:06'),
(33, 17, 'Staf Pemasaran', '2023-09-14 03:40:45', '2023-09-14 03:40:45'),
(34, 17, 'Supervisor SCM', '2023-09-14 03:41:10', '2023-09-14 03:41:10'),
(35, 17, 'Staf SCM', '2023-09-14 03:41:54', '2023-09-14 03:41:54'),
(36, 18, 'Manajer Pembinaan Anak Usaha/Afiliasi', '2023-09-14 03:42:22', '2023-09-14 03:42:22'),
(37, 18, 'Supervisor Pembinaan Anak Usaha/Afiliasi', '2023-09-14 03:43:31', '2023-09-14 03:43:31'),
(75, 18, 'Staf Pembinaan Anak Usaha/Afiliasi', '2023-09-14 04:09:33', '2023-09-14 04:09:33'),
(76, 19, 'Manajer Manajemen Investasi', '2023-09-14 04:09:33', '2023-09-14 04:09:33'),
(77, 19, 'Supervisor Manajemen Investasi', '2023-09-14 04:11:27', '2023-09-14 04:11:27'),
(78, 19, 'Staf Manajemen Investasi', '2023-09-14 04:11:58', '2023-09-14 04:11:58'),
(79, 20, 'Manajer Umum', '2023-09-14 04:12:44', '2023-09-14 04:12:44'),
(80, 20, 'Supervisor Umum', '2023-09-14 04:12:44', '2023-09-14 04:12:44'),
(81, 20, 'Staf Umum', '2023-09-14 04:13:22', '2023-09-14 04:13:22'),
(82, 21, 'Manajer Teknologi Informasi', '2023-09-14 04:13:54', '2023-09-14 04:13:54'),
(83, 21, 'Supervisor Teknologi Informasi', '2023-09-14 04:13:54', '2023-09-14 04:13:54'),
(84, 21, 'Staf Teknologi Informasi', '2023-09-14 04:14:44', '2023-09-14 04:14:44'),
(85, 22, 'Manajer Sumber Daya Manusia', '2023-09-14 04:15:35', '2023-09-14 04:15:35'),
(86, 22, 'Supervisor Sumber Daya Manusia', NULL, NULL),
(87, 22, 'Staf Sumber Daya Manusia', '2023-09-14 04:16:31', '2023-09-14 04:16:31'),
(88, 23, 'Sekretaris Perusahaan', '2023-09-14 04:16:47', '2023-09-14 04:16:47'),
(89, 23, 'Staf Hukum dan Legalitas Perusahaan', '2023-09-14 04:17:29', '2023-09-14 04:17:29'),
(90, 23, 'Staf Hubungan Masyarakat', '2023-09-14 04:17:58', '2023-09-14 04:17:58'),
(91, 23, 'Staf Sekretaris Direktur Utama', '2023-09-14 04:18:42', '2023-09-14 04:18:42'),
(92, 23, 'Staf Komisaris Utama', '2023-09-14 04:19:17', '2023-09-14 04:19:17'),
(93, 23, 'Supervisor Hukum dan Legalitas Perusahaan', '2023-09-14 04:19:58', '2023-09-14 04:19:58'),
(94, 20, 'PIC Manajer Umum', '2023-09-14 04:19:58', '2023-09-14 04:19:58'),
(95, 20, 'Petugas Kebersihan', '2023-09-14 04:21:23', '2023-09-14 04:21:23'),
(96, 20, 'Supir', '2023-09-14 04:21:53', '2023-09-14 04:21:53'),
(97, 11, 'PIC Manajer Keuangan', '2023-09-14 04:22:09', '2023-09-14 04:22:09'),
(98, 22, 'PIC Sumber Daya Manusia', '2023-09-14 04:24:20', '2023-09-14 04:24:20');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `project_id` bigint(20) UNSIGNED NOT NULL,
  `project_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `division` smallint(5) UNSIGNED NOT NULL,
  `project_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `goals` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `estimated_income` double NOT NULL,
  `estimated_cost` double NOT NULL,
  `base_id` int(10) UNSIGNED NOT NULL,
  `level_id` smallint(5) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`project_id`, `project_number`, `division`, `project_name`, `start_date`, `end_date`, `goals`, `estimated_income`, `estimated_cost`, `base_id`, `level_id`, `business_id`, `created_by`, `created_at`, `updated_at`) VALUES
(3, '12BEBEB', 14, 'The First Project', '2023-09-23', '2023-09-26', 'Explain of the fist project', 2000000000, 900000000, 3, 2, 14, '123456223K', '2023-09-15 07:47:08', '2023-09-15 07:47:08'),
(5, 'SDGSDFG2342', 14, 'The second Project', '2023-09-21', '2023-09-28', 'aasfAEFasfASF', 23536, 234234, 3, 1, 11, '123456223K', '2023-09-16 09:12:58', '2023-09-16 09:12:58'),
(6, 'gsdgdfghf', 14, 'sdfsdsdg', '2023-09-29', '2023-09-28', 'sdshfghkker', 2354, 346456454, 2, 2, NULL, '123456223K', '2023-09-16 09:44:56', '2023-09-16 09:44:56');

-- --------------------------------------------------------

--
-- Table structure for table `project_histories`
--

CREATE TABLE `project_histories` (
  `history_id` bigint(20) UNSIGNED NOT NULL,
  `project_id` bigint(20) UNSIGNED NOT NULL,
  `employe_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `history_desc` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `project_histories`
--

INSERT INTO `project_histories` (`history_id`, `project_id`, `employe_id`, `history_desc`, `active`, `created_at`, `updated_at`) VALUES
(1, 3, '123456223K', 'Project created', 1, '2023-09-15 07:47:08', '2023-09-15 07:47:08'),
(3, 5, '123456223K', 'Project created', 1, '2023-09-16 09:12:58', '2023-09-16 09:12:58'),
(4, 6, '123456223K', 'Project created', 1, '2023-09-16 09:44:56', '2023-09-16 09:44:56');

-- --------------------------------------------------------

--
-- Table structure for table `project_tasks`
--

CREATE TABLE `project_tasks` (
  `task_id` int(11) UNSIGNED NOT NULL,
  `project_id` bigint(20) UNSIGNED NOT NULL,
  `task_parent` int(11) DEFAULT NULL,
  `task_title` varchar(255) NOT NULL,
  `task_desc` text DEFAULT NULL,
  `task_progress` float DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `project_tasks`
--

INSERT INTO `project_tasks` (`task_id`, `project_id`, `task_parent`, `task_title`, `task_desc`, `task_progress`, `created_at`, `updated_at`) VALUES
(3, 3, NULL, 'Task Pertama', 'Task Description', 10, '2023-09-19 03:25:55', '2023-09-19 03:25:55'),
(4, 3, NULL, 'Task Pertama', 'Task Description', 10, '2023-09-19 03:27:18', '2023-09-19 03:27:18'),
(5, 3, 3, 'Task Pertama', 'Task Description', 10, '2023-09-19 03:27:36', '2023-09-19 03:27:36'),
(6, 3, NULL, 'Task Pertama', 'Task Description', 10, '2023-09-19 03:42:32', '2023-09-19 03:42:32'),
(7, 3, 3, 'Task Pertama', 'Task Description', 10, '2023-09-19 03:42:50', '2023-09-19 03:42:50'),
(8, 3, NULL, 'Task Pertama', 'Task Description', 10, '2023-09-19 03:46:37', '2023-09-19 03:46:37'),
(9, 3, NULL, 'Task Pertama', 'Task Description', 10, '2023-09-19 03:47:37', '2023-09-19 03:47:37'),
(10, 3, 6, 'Task Pertama', 'Task Description', 10, '2023-09-19 04:09:19', '2023-09-19 04:09:19'),
(11, 3, NULL, 'Task Pertama', 'Task Description', 10, '2023-09-19 04:09:32', '2023-09-19 04:09:32'),
(12, 3, NULL, 'Task Pertama', 'Task Description', 10, '2023-09-19 04:09:48', '2023-09-19 04:09:48'),
(13, 3, NULL, 'Task Pertama', 'Task Description', 10, '2023-09-19 04:10:44', '2023-09-19 04:10:44'),
(14, 3, NULL, 'Task Pertama', 'Task Description', 10, '2023-09-19 04:58:55', '2023-09-19 04:58:55'),
(15, 3, NULL, 'Task Pertama', 'Task Description', 10, '2023-09-19 04:59:14', '2023-09-19 04:59:14'),
(16, 3, NULL, 'Task Pertama', 'Task Description', 10, '2023-09-19 04:59:32', '2023-09-19 04:59:32'),
(17, 3, NULL, 'Task Pertama', 'Task Description', 10, '2023-09-19 05:00:52', '2023-09-19 05:00:52'),
(18, 5, NULL, 'Task Kedua', 'Task Description Kdeua', 10, '2023-09-19 05:02:15', '2023-09-19 05:02:15'),
(19, 5, NULL, 'asas', NULL, NULL, '2023-09-19 05:16:46', '2023-09-19 05:16:46'),
(21, 6, NULL, 'aasas', NULL, NULL, '2023-09-19 05:24:42', '2023-09-19 05:24:42'),
(22, 6, NULL, 'abajabjba', NULL, NULL, '2023-09-20 07:51:28', '2023-09-20 07:51:28'),
(23, 6, NULL, 'Markicob', NULL, NULL, '2023-09-20 08:20:03', '2023-09-20 08:20:03'),
(24, 6, NULL, 'Mensjns sjns jnSKJdns ks', NULL, NULL, '2023-09-20 09:20:03', '2023-09-20 09:20:03'),
(25, 6, NULL, 'abajabjba', NULL, NULL, '2023-09-21 03:35:08', '2023-09-21 03:35:08'),
(26, 6, NULL, 'abajabjba', NULL, NULL, '2023-09-21 03:36:04', '2023-09-21 03:36:04'),
(27, 6, NULL, 'sdlknslkn', NULL, NULL, '2023-09-21 03:38:55', '2023-09-21 03:38:55'),
(28, 6, NULL, 'sdlknslkn', NULL, NULL, '2023-09-21 03:40:50', '2023-09-21 03:40:50'),
(29, 6, NULL, 'sdlknslkn', NULL, NULL, '2023-09-21 03:41:27', '2023-09-21 03:41:27'),
(30, 6, NULL, 'sdlknslkn', NULL, NULL, '2023-09-21 03:42:21', '2023-09-21 03:42:21'),
(31, 6, NULL, 'sdlknslkn', NULL, NULL, '2023-09-21 03:43:25', '2023-09-21 03:43:25'),
(32, 6, NULL, 'sdlknslkn', NULL, NULL, '2023-09-21 03:44:35', '2023-09-21 03:44:35'),
(33, 3, NULL, 'sdlksdlknsdnslkn', NULL, NULL, '2023-09-21 03:56:31', '2023-09-21 03:56:31'),
(34, 3, NULL, 'sdlksdlknsdnslkn', NULL, NULL, '2023-09-21 03:59:50', '2023-09-21 03:59:50'),
(35, 3, NULL, 'sdlksdlknsdnslkn', NULL, NULL, '2023-09-21 04:00:51', '2023-09-21 04:00:51'),
(36, 3, NULL, 'sdlksdlknsdnslkn', NULL, NULL, '2023-09-21 04:01:14', '2023-09-21 04:01:14'),
(37, 3, NULL, 'sdlksdlknsdnslkn', NULL, NULL, '2023-09-21 04:01:33', '2023-09-21 04:01:33'),
(38, 3, NULL, 'sdlksdlknsdnslkn', NULL, NULL, '2023-09-21 04:01:42', '2023-09-21 04:01:42'),
(39, 3, NULL, 'sdlksdlknsdnslkn', NULL, NULL, '2023-09-21 04:02:00', '2023-09-21 04:02:00'),
(40, 3, NULL, 'sdlksdlknsdnslkn', NULL, NULL, '2023-09-21 04:02:07', '2023-09-21 04:02:07'),
(41, 3, NULL, 'sdlksdlknsdnslkn', NULL, NULL, '2023-09-21 04:02:33', '2023-09-21 04:02:33'),
(42, 3, NULL, 'sdlksdlknsdnslkn', NULL, NULL, '2023-09-21 04:02:46', '2023-09-21 04:02:46'),
(43, 3, NULL, 'sdlksdlknsdnslkn', NULL, NULL, '2023-09-21 04:03:12', '2023-09-21 04:03:12'),
(44, 3, NULL, 'sdlksdlknsdnslkn', NULL, NULL, '2023-09-21 04:03:36', '2023-09-21 04:03:36'),
(45, 3, NULL, 'sdlksdlknsdnslkn', NULL, NULL, '2023-09-21 04:03:44', '2023-09-21 04:03:44'),
(46, 3, NULL, 'sdlksdlknsdnslkn', NULL, NULL, '2023-09-21 04:07:00', '2023-09-21 04:07:00'),
(47, 3, NULL, 'sdlksdlknsdnslkn', NULL, NULL, '2023-09-21 04:07:11', '2023-09-21 04:07:11'),
(48, 3, NULL, 'sdlksdlknsdnslkn', NULL, NULL, '2023-09-21 04:08:58', '2023-09-21 04:08:58'),
(49, 3, NULL, 'sdlksdlknsdnslkn', NULL, NULL, '2023-09-21 04:09:33', '2023-09-21 04:09:33');

-- --------------------------------------------------------

--
-- Table structure for table `project_task_approval`
--

CREATE TABLE `project_task_approval` (
  `approval_id` bigint(20) UNSIGNED NOT NULL,
  `task_id` int(10) UNSIGNED NOT NULL,
  `employe_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` smallint(6) NOT NULL DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `project_task_approval`
--

INSERT INTO `project_task_approval` (`approval_id`, `task_id`, `employe_id`, `status`, `start_date`, `end_date`, `created_at`, `updated_at`) VALUES
(1, 32, '123456223K', 0, '2023-09-22', NULL, '2023-09-21 03:50:12', '2023-09-21 03:50:12'),
(2, 33, '123456223K', 0, NULL, NULL, '2023-09-21 03:56:32', '2023-09-21 03:56:32'),
(3, 37, '123456223K', 0, NULL, NULL, '2023-09-21 04:01:33', '2023-09-21 04:01:33'),
(4, 39, '123456223K', 0, NULL, NULL, '2023-09-21 04:02:00', '2023-09-21 04:02:00'),
(5, 40, '123456223K', 0, NULL, NULL, '2023-09-21 04:02:07', '2023-09-21 04:02:07'),
(6, 41, '123456223K', 0, NULL, NULL, '2023-09-21 04:02:33', '2023-09-21 04:02:33'),
(7, 42, '123456223K', 0, NULL, NULL, '2023-09-21 04:02:46', '2023-09-21 04:02:46'),
(8, 43, '123456223K', 0, NULL, NULL, '2023-09-21 04:03:12', '2023-09-21 04:03:12'),
(9, 44, '123456223K', 0, NULL, NULL, '2023-09-21 04:03:36', '2023-09-21 04:03:36'),
(10, 45, '123456223K', 0, NULL, NULL, '2023-09-21 04:03:44', '2023-09-21 04:03:44'),
(11, 46, '123456223K', 0, NULL, NULL, '2023-09-21 04:07:00', '2023-09-21 04:07:00'),
(12, 47, '123456223K', 0, NULL, NULL, '2023-09-21 04:07:11', '2023-09-21 04:07:11'),
(13, 48, '123456223K', 0, NULL, NULL, '2023-09-21 04:08:58', '2023-09-21 04:08:58'),
(14, 49, '123456223K', 0, NULL, NULL, '2023-09-21 04:09:33', '2023-09-21 04:09:33'),
(15, 49, '123456223K', 1, NULL, NULL, '2023-09-21 04:09:33', '2023-09-21 04:09:33');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `roles` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `roles`, `created_at`, `updated_at`) VALUES
(1, 'user1@gmail.com', '$2y$10$oH0cGDScauOZPOIxPR1lBuX3PLIa7czrAUuIC5cE3onDrfynKzvUu', '[\"Employee\"]', '2023-09-15 01:41:59', '2023-09-15 01:41:59');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_bases`
--
ALTER TABLE `activity_bases`
  ADD PRIMARY KEY (`base_id`),
  ADD KEY `activity_bases_employe_id_foreign` (`employe_id`);

--
-- Indexes for table `activity_levels`
--
ALTER TABLE `activity_levels`
  ADD PRIMARY KEY (`level_id`),
  ADD KEY `activity_levels_employe_id_foreign` (`employe_id`);

--
-- Indexes for table `board_organizations`
--
ALTER TABLE `board_organizations`
  ADD PRIMARY KEY (`board_id`),
  ADD KEY `board_organizations_company_id_foreign` (`company_id`);

--
-- Indexes for table `business_plans`
--
ALTER TABLE `business_plans`
  ADD PRIMARY KEY (`business_id`),
  ADD KEY `employe_id` (`employe_id`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employe_id`),
  ADD KEY `employees_user_id_foreign` (`user_id`),
  ADD KEY `employees_position_id_foreign` (`position_id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `organizations`
--
ALTER TABLE `organizations`
  ADD PRIMARY KEY (`organization_id`),
  ADD KEY `organizations_board_id_foreign` (`board_id`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`position_id`),
  ADD KEY `positions_organization_id_foreign` (`organization_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`project_id`),
  ADD UNIQUE KEY `projects_project_number_unique` (`project_number`),
  ADD KEY `projects_created_by_foreign` (`created_by`),
  ADD KEY `projects_division_foreign` (`division`),
  ADD KEY `projects_base_id_foreign` (`base_id`),
  ADD KEY `projects_level_id_foreign` (`level_id`),
  ADD KEY `projects_business_id_foreign` (`business_id`);

--
-- Indexes for table `project_histories`
--
ALTER TABLE `project_histories`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `project_histories_project_id_foreign` (`project_id`),
  ADD KEY `project_histories_employe_id_foreign` (`employe_id`);

--
-- Indexes for table `project_tasks`
--
ALTER TABLE `project_tasks`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `task_project_id_foreign` (`project_id`);

--
-- Indexes for table `project_task_approval`
--
ALTER TABLE `project_task_approval`
  ADD PRIMARY KEY (`approval_id`),
  ADD KEY `task_task_id_foreign` (`task_id`),
  ADD KEY `task_employe_id_foreign` (`employe_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_bases`
--
ALTER TABLE `activity_bases`
  MODIFY `base_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `activity_levels`
--
ALTER TABLE `activity_levels`
  MODIFY `level_id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `board_organizations`
--
ALTER TABLE `board_organizations`
  MODIFY `board_id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `business_plans`
--
ALTER TABLE `business_plans`
  MODIFY `business_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `organizations`
--
ALTER TABLE `organizations`
  MODIFY `organization_id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `position_id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `project_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `project_histories`
--
ALTER TABLE `project_histories`
  MODIFY `history_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `project_tasks`
--
ALTER TABLE `project_tasks`
  MODIFY `task_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `project_task_approval`
--
ALTER TABLE `project_task_approval`
  MODIFY `approval_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_bases`
--
ALTER TABLE `activity_bases`
  ADD CONSTRAINT `activity_bases_employe_id_foreign` FOREIGN KEY (`employe_id`) REFERENCES `employees` (`employe_id`) ON DELETE CASCADE;

--
-- Constraints for table `activity_levels`
--
ALTER TABLE `activity_levels`
  ADD CONSTRAINT `activity_levels_employe_id_foreign` FOREIGN KEY (`employe_id`) REFERENCES `employees` (`employe_id`) ON DELETE CASCADE;

--
-- Constraints for table `board_organizations`
--
ALTER TABLE `board_organizations`
  ADD CONSTRAINT `board_organizations_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_position_id_foreign` FOREIGN KEY (`position_id`) REFERENCES `positions` (`position_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employees_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `organizations`
--
ALTER TABLE `organizations`
  ADD CONSTRAINT `organizations_board_id_foreign` FOREIGN KEY (`board_id`) REFERENCES `board_organizations` (`board_id`) ON DELETE CASCADE;

--
-- Constraints for table `positions`
--
ALTER TABLE `positions`
  ADD CONSTRAINT `positions_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`organization_id`) ON DELETE CASCADE;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_base_id_foreign` FOREIGN KEY (`base_id`) REFERENCES `activity_bases` (`base_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `projects_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `business_plans` (`business_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `projects_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `employees` (`employe_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `projects_division_foreign` FOREIGN KEY (`division`) REFERENCES `organizations` (`organization_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `projects_level_id_foreign` FOREIGN KEY (`level_id`) REFERENCES `activity_levels` (`level_id`) ON DELETE CASCADE;

--
-- Constraints for table `project_histories`
--
ALTER TABLE `project_histories`
  ADD CONSTRAINT `project_histories_employe_id_foreign` FOREIGN KEY (`employe_id`) REFERENCES `employees` (`employe_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_histories_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE;

--
-- Constraints for table `project_tasks`
--
ALTER TABLE `project_tasks`
  ADD CONSTRAINT `task_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE;

--
-- Constraints for table `project_task_approval`
--
ALTER TABLE `project_task_approval`
  ADD CONSTRAINT `task_employe_id_foreign` FOREIGN KEY (`employe_id`) REFERENCES `employees` (`employe_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `project_tasks` (`task_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
