-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 04, 2026 at 10:42 AM
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
-- Database: `ram_pay`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `account_no` varchar(20) NOT NULL,
  `mobile` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `Wallet Status` enum('Active','Inactive') NOT NULL,
  `KYC Status` enum('Verified','Un Verified') NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `pin` varchar(255) DEFAULT NULL,
  `balance` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `account_no`, `mobile`, `email`, `Wallet Status`, `KYC Status`, `password`, `pin`, `balance`, `created_at`) VALUES
(1, 'Dheeraj Varshney', '2181010001', '9411272563', 'dheerajvarshney74@gmail.com', 'Active', 'Verified', '$2y$10$3lHUMd2/gPb8rBm.nYCKh.Q8yCoBJoSmRB/wKqv5Vn4/3rjIYEwze', '$2y$10$O.ojN15ibObCeHriHF8KsuWYwnafbwAgUqFMkRcrpVVezARRdLdva', 'EiMjETznFRI9rIpCygZlIy99medaBAxtnvuhPOiv7Y4=', '2026-08-04 08:40:39'),
(2, 'Ram Varshney', '2181010002', '9412510127', 'ramvarshney27@gmail.com', 'Active', 'Verified', '$2y$10$KadB.6BqS3xhs6Al9EeOVuRij5syZkfmYj.8WT98gF4AfqPmXGQ6C', '$2y$10$LOijJHsIntM5ILCDu28qOufoyIW1/UKkdpm0oVC/1GaSyhYj.rOey', '4BS/q0vUQNZEIIBNcaVxfqvk0OLCEOgJXEbp0dH5kHk=', '2026-08-04 08:41:04');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_no` (`account_no`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
