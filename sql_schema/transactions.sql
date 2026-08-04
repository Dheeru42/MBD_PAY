-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 04, 2026 at 10:43 AM
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
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `transaction_id` varchar(50) NOT NULL,
  `mobile` varchar(10) DEFAULT NULL,
  `type` enum('Credit','Debit') DEFAULT NULL,
  `amount` text NOT NULL,
  `balance_before` text NOT NULL,
  `balance_after` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `description` varchar(255) NOT NULL,
  `status` enum('Success','Failed') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `transaction_id`, `mobile`, `type`, `amount`, `balance_before`, `balance_after`, `created_at`, `description`, `status`) VALUES
(1, 'MBD17858328984225', '9411272563', 'Credit', 'TdJrddflpvU7XfudxZXBZLfCINkzCUhfRnShRmoDyd0=', '3EB3zcmBQSY5GO0O/niwM5B6odEuYa6KITtKxHiCmnE=', '6BAWuyHLaPfPOsGUez1MfrV5nrLHyFsGdq02d1HXW/A=', '2026-08-04 08:41:38', 'Wallet recharge from bank account', 'Success'),
(2, 'MBD17858329163976', '9411272563', 'Debit', 'aahLpaPc9tUGtaYkfr2llvVcNkLknScMWZ4mpAZcThs=', 'IIZNCdnaRIv6UMnBC7Rahp8FOquRHRzgdkg0hQGHQ+U=', 'EiMjETznFRI9rIpCygZlIy99medaBAxtnvuhPOiv7Y4=', '2026-08-04 08:41:56', 'Withdrawal to MBD bank account', 'Success');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
