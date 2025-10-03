-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 03, 2025 at 03:00 PM
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
-- Database: `sharkscope_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `makosense_readings`
--

CREATE TABLE `makosense_readings` (
  `reading_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `location` point NOT NULL,
  `prey_code` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tchi_rasters`
--

CREATE TABLE `tchi_rasters` (
  `raster_id` int(11) NOT NULL,
  `capture_date` date NOT NULL,
  `tchi_path` varchar(255) NOT NULL,
  `sst_path` varchar(255) NOT NULL,
  `chla_path` varchar(255) NOT NULL,
  `tfg_path` varchar(255) NOT NULL,
  `eke_path` varchar(255) NOT NULL,
  `bathy_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `makosense_readings`
--
ALTER TABLE `makosense_readings`
  ADD PRIMARY KEY (`reading_id`),
  ADD SPATIAL KEY `location` (`location`);

--
-- Indexes for table `tchi_rasters`
--
ALTER TABLE `tchi_rasters`
  ADD PRIMARY KEY (`raster_id`),
  ADD UNIQUE KEY `capture_date` (`capture_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `makosense_readings`
--
ALTER TABLE `makosense_readings`
  MODIFY `reading_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tchi_rasters`
--
ALTER TABLE `tchi_rasters`
  MODIFY `raster_id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
