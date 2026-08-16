-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 16, 2026 at 01:20 PM
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
  `type` enum('Credit','Debit','Currency Generated','Currency Received') DEFAULT NULL,
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
(2, 'MBD17858329163976', '9411272563', 'Debit', 'aahLpaPc9tUGtaYkfr2llvVcNkLknScMWZ4mpAZcThs=', 'IIZNCdnaRIv6UMnBC7Rahp8FOquRHRzgdkg0hQGHQ+U=', 'EiMjETznFRI9rIpCygZlIy99medaBAxtnvuhPOiv7Y4=', '2026-08-04 08:41:56', 'Withdrawal to MBD bank account', 'Success'),
(3, 'MBD17858428778584', '9411272563', 'Credit', 'wBaEwOX2sFLbhuSkgAV3vjkI7D7Ham0ANGkfO/zEqhs=', 'Shs6WUYMmY30UV21NgWk/mZ0fh7TMLcwIoAqRQP6VgY=', 'fBmS2lMGS5I1GOWTHMYG8gqY9bmnI5KEkmEfYm4Utx8=', '2026-08-04 11:27:57', 'Wallet recharge from bank account', 'Success'),
(4, 'MBD17858430352785', '9411272563', 'Debit', 'f88NcNCtzGIbuRejqCZv3bIRUyY00xXKmmglkmb5mtw=', 'tBptW4mQ/AluWf5zULW80q22wnUFN7cuLPTkk8JNtkk=', 'kpbPCuM7NXFtyQmkt3V0MUmoCa7appjFkKlrfhp2WJw=', '2026-08-04 11:30:35', 'Withdrawal to MBD bank account', 'Success'),
(5, 'MBD17858564635840', '9412510127', 'Credit', '8vlNmFtw0EEbZWJrRyFXO7ujoDeu4MmE/jGuNo9ZSDg=', '5H8nJTpPm/65PjQXZkiWjWkXLiQJ5WH3sRW1Qf2WWUA=', 'ZJRWLuYlG2D8bDf8+yHysg/napluymNFTCZPtJj6NZA=', '2026-08-04 15:14:23', 'Wallet recharge from bank account', 'Success'),
(6, 'MBD202608060753200A40CC917A', '9411272563', 'Currency Generated', 'GqYFYPiF27rmzVs0dinSbqyYLfWeESqU0vilOthqahE=', 'AzYTYAptMibIr+3t61mI7UUFX0707q6oM6VjRfXfdF8=', '60qcmeS7i1lzGhk5hX3YWUQ6Vd9ALxOZ7yz6cPTqsgE=', '2026-08-06 05:53:20', 'MBD Pay currency generated', 'Success'),
(7, 'MBD20260806075945A9E97EC00B', '9411272563', 'Currency Generated', 'fr8XjmVVIZJk3UiWwAfluGl34NSkbCEQvGKPutMomy4=', 'xYY+MDRiAlbTSqkABkPHxXhUTfEWcJpgnqtHPaYvxgc=', '4HSQpHYVMxO4/nlgeM/ti3lnpQnhi1UM3l0rNh0AY1E=', '2026-08-06 05:59:45', 'MBD Pay currency generated', 'Success'),
(8, 'MBD20260806080433EEE16484A0', '9411272563', 'Currency Generated', 'l1sH5BIfgHOgUI2qc+V227OLBbAdiTkbiQNSDf7LS3M=', 'HvnReIpZicqcpB59zS/VQkzqqcwajER6pSXd0D6S0b4=', 'vYya6WRlWFLZyGjUs9N7ab+cuMh6CIBFiFnl8/ZIeOQ=', '2026-08-06 06:04:33', 'MBD Pay currency generated', 'Success'),
(9, 'MBD202608061009282C4D4CFC84', '9411272563', 'Currency Generated', 't4irUzRic1P5HoM8uSiis/psImB8WMqLp91AGzpIIe4=', 'qTGpz6JOH3zjJIka15NSTylCvvk109fqlppNpCWtsvo=', '2NluCA29u3p4xnug4g8oulxI8VdBce23MsbtSBKmxQc=', '2026-08-06 08:09:28', 'MBD Pay currency generated', 'Success'),
(10, 'MBD20260806102755189A1D0F68', '9411272563', 'Currency Generated', 'KYwfvq47TIbJ7XwX+6JQPqqCuKKTEAAnp4VgDLrTUZ8=', 'IT2HNq66DaKIXXJGVG2ChAxjuUjW4QpQMvq2SczoQDI=', 'y3ZmSB6Txl7Lpys394Q8WJLEOKLkMmzlRSMmvkv1Dmo=', '2026-08-06 08:27:55', 'MBD Pay currency generated', 'Success'),
(11, 'MBD2026080610543936F3EE0AF6', '9411272563', 'Currency Generated', 'DfSymGBnmY/BMH3hy+pOrPuQdgc2aBqRdSVNqzBSdwE=', 'aIO4bdOIY5yJaWh078PM+AlmsNW8YmKy7P6KOwtnzdc=', 'r2TREpZvDC9qtnOrZrli3ZVBt0/31lEoSc/hkft7Hco=', '2026-08-06 08:54:39', 'MBD Pay currency generated', 'Success'),
(12, 'MBD2026080611030940F78248E3', '9411272563', 'Currency Generated', 'XrP+8+FQBSvcmEOvcM0n0ZF4+56ylcMW3ofgRFn7v0Y=', 'i5zq4Z1p4mEtvkJAsbCuwLSYy1/37h0EnFvYZSDmOQU=', 'jbqHMlJt/D77Q+NTwOgBJxOUoKfyJTK2rxznNK++MSk=', '2026-08-06 09:03:09', 'MBD Pay currency generated', 'Success'),
(13, 'MBD202608061103323022720324', '9411272563', 'Currency Generated', 'exsPugfK99iNhlT3OR1CMfN67bF8V2ratY9rhj88zWs=', 'e3u7JMd0hgEYfNGuNPe23knviQu00K8tCNeXDTOV2pQ=', '/4k2yzib5gmjqM1ug6P5DCIDiK4UcrnG065UO5/GEPs=', '2026-08-06 09:03:32', 'MBD Pay currency generated', 'Success'),
(14, 'MBD20260806110531D8682B38F7', '9411272563', 'Currency Generated', '9tihIJIOo8vG0nD84yCi7DIxgRNUJTyGuea1hXthHl8=', 'fnBnfC4XNZMqSYtRS5lXz6WToa1BzeUSi/WTLdeXqgA=', 'WB2basaoPtXxhSLD0ugPbhyYIMHfcef/BNRLZkYG5T0=', '2026-08-06 09:05:31', 'MBD Pay currency generated', 'Success'),
(15, 'MBD202608061105359D505E1462', '9411272563', 'Currency Generated', '9F25pc5NdJ++1UhkTw/loOUkrktDyxg1CaUt4c2Ms0w=', 'qeNarteS14p2vz6uEmam/PabOpqUERU3KJEP/jRk2BE=', 'qKFQFpZaxIz9c0iHZY787p0q4XTgcrBnBRn7YfAlEoM=', '2026-08-06 09:05:35', 'MBD Pay currency generated', 'Success'),
(16, 'MBD202608061105552B3E4E9A47', '9411272563', 'Currency Generated', 'MXl2ceyDCn4QlZtkDGIMXRQJuv0uppYANuOFMSA89YE=', 'iV2UEKrjo8kWK3+Ht7AAK+hO8mXJ1KEH9/L1bcdQ8HY=', 'bk0VWmBpfv+P3EAutRP9PRkd3NVMWJ6mHULmqC3ZuH8=', '2026-08-06 09:05:55', 'MBD Pay currency generated', 'Success'),
(17, 'MBD20260806111956229FE98ADD', '9411272563', 'Currency Generated', 'cNi6qsiqL/u5Hql5DNfvnjiifwJn1GD2iFUVbMS/iGI=', '0XUWPhdQgkR0hBrH23Z4RaBTToDS3N9T4ej3ek5VcKs=', 'SFhxHjTMpvtsEXqJjB/vH5CfIbjqUo0/jCkXvu28eoQ=', '2026-08-06 09:19:56', 'MBD Pay currency generated', 'Success'),
(18, 'MBD20260806112143CE930E46D1', '9411272563', 'Currency Generated', 'wihzOlD/8j43kDYEX48lv+uB6W6DNga1NkX1bmKFsVg=', 'J879rSS/lTuayiUnN+LK3VgHhlHcU3ye9aJrghUXjuE=', '8oS6Daja+CyfFywQ/eghgiDfZNsmb4qITy5txbpwqKU=', '2026-08-06 09:21:43', 'MBD Pay currency generated', 'Success'),
(19, 'MBD20260806112434639C194C9B', '9411272563', 'Currency Generated', 'eKRCc8nTH4oa9lmedEg4ZCJjR4K16mAVLAYwSkVzgwE=', 'rkkV1nCdXbrQm/hokeJBUU54LadStmq4uzvGC1qSce8=', 'YiP5A9dn+8XjDOXNiMrpTQdpAs6+L29QM02EZlP65ng=', '2026-08-06 09:24:34', 'MBD Pay currency generated', 'Success'),
(20, 'MBD202608070914184632CE7BDF', '9411272563', 'Currency Generated', 'WB+7RZYLsDDnmAX9nnuD0QjPd+3aHTOsoxntkiPay08=', 'XKFM1LSbdJGZRqDWLwiSKON+zfVv5+qu06S8unoaDxc=', '12uvx2stpS0/7rdsFgv3AU2cy+2KUJO0c+qcBDW5O1k=', '2026-08-07 07:14:18', 'MBD Pay currency generated', 'Success'),
(21, 'MBD17860892761270', '9411272563', 'Credit', 'sZcDJCNFjcVf23seR+/wfjCGxLqZLRkOqypIQnrYWk8=', 'u1sH0Gf+CNjdlOFRqezkRoOJHWki2nxLHRzLWqBt+1s=', 'hYyfui2lwg7om+YhZNZM5cGXVwjmBuIBHrgE1H4CIQA=', '2026-08-07 07:54:36', 'Wallet recharge from bank account', 'Success'),
(22, 'MBD17860892856052', '9411272563', 'Credit', 'TiCr5STg9S0n21piBotjZE0FBK96rMFfdEqxudheC0M=', 'DmTS1QXenH3NsDuZq+pYJGg1mcXpoQEQLtatEB5gdKQ=', 'bj5RJeljLC7w+JohNon3iJ4T/Sw+9bklcAVwFAVVJVI=', '2026-08-07 07:54:45', 'Wallet recharge from bank account', 'Success'),
(23, 'MBD17860896375081', '9411272563', 'Credit', '6Sektb42+D0IS1Gi/s+hyl/1MyjujmNT5BLy1k71P9k=', 'GTM5UwDuULk7Pv9MQV2DZSxacU5xdAzic2/egCVgnn8=', 'Idwi1ad87GQozeE1skTPKN905rzMTjo6iojNbgV/pAo=', '2026-08-07 08:00:37', 'Wallet recharge from bank account', 'Success'),
(24, 'MBD17860896587875', '9411272563', 'Debit', 'TNky7onu+d8m8mbB3091bq8LVurRs3ytxsBZ5FFw3oo=', 'rIOrUCVdixgC7ZdFrzhkbaxLQDSXBJloze9aI8HeWfI=', 'j8bb/0ArtPhbTmCacg9c20gW2CBYhVKtnsCHM0Hpkh8=', '2026-08-07 08:00:58', 'Withdrawal to MBD bank account', 'Success'),
(25, 'MBD17860896725789', '9411272563', 'Debit', '172dgLeHEDHTzkgla6cCLbj44n5yGFfSR5+CtjjAYls=', 'AAt669xmejz5QKetexjpjWUzBdMNhb/tSygzXos1E18=', '+D1Sca/dxkv/e01lWRE72I+NwTgLVgQu46n9fDFzLq4=', '2026-08-07 08:01:12', 'Withdrawal to MBD bank account', 'Success'),
(26, 'MBD17860896773413', '9411272563', 'Debit', 'F7Jd+aqwywwc7WmKX99VYH3Hmlm5/+UMbNk3RYff7UY=', 'hemhXx5RGHSe3VT4h6todqdKRxBm859sfRgzqn5dBQQ=', 'yJxTv9heebqHES6p29jqDt3Bp8V/lgs6UEFpziicKIg=', '2026-08-07 08:01:17', 'Withdrawal to MBD bank account', 'Success'),
(27, 'MBD17860898609144', '9411272563', 'Debit', 'XYk38nUzt5ZHf6l3pW4Z8gU7XVXRr1LbFJC3sPvGVdI=', 'yInRFUm2QQKWivukWi2D1DEPvtpMGZ3sHezOLpqkdiI=', 'cKEUPsNWbVf0fpGev7Z6v+31X+2nw2DrsUQP4J0WNBM=', '2026-08-07 08:04:20', 'Withdrawal to MBD bank account', 'Success'),
(28, 'MBD17860898672984', '9411272563', 'Credit', 'ZRMMRFrmib8ch4qXQS6hSh/pMSMNjGW0ccHB99cytb0=', 'Bd5SrEmfmlvDjPQYdN/CjWmUz73yElDxMeTCUgGZqM0=', 'ZAGwQqAoXtUI1EDikWKeWVSi9ZY3fMpgSyQQBXzMnNE=', '2026-08-07 08:04:27', 'Wallet recharge from bank account', 'Success'),
(29, 'MBD17860898775925', '9411272563', 'Debit', '1pDeijt1bc/92LYJj8uFphrKcY2gSzTmriB6Qh6MZ4c=', 'cfHytuNu+gn4R5pEJTaclVPZnBrRvR7MZggcMU4fwJo=', 'j7Y4hqmBMZli6rOtNFdXUXGdLr9ZvGria+Gm+KBkw8c=', '2026-08-07 08:04:37', 'Withdrawal to MBD bank account', 'Success'),
(30, 'MBD202608071007463AA2AC2567', '9411272563', 'Currency Generated', '0MAnBrrTmubCbf99nLYDegWxVihq3hjBZWy7aL8YYwY=', 'ryIkIVaYhePpsQrMYyluqasuu58iZ7wwomUqf8VX614=', '5DFttCqlI/nOuk6A7SGT7KD/epPKO2AkKJITgl54BlA=', '2026-08-07 08:07:46', 'MBD Pay currency generated', 'Success'),
(31, 'MBD2026080710092341E315197B', '9411272563', 'Currency Generated', '4uwpSQ/vTbyNCnXI7NSI4sFeQyFB34wjt6Y7+B2PrUg=', 'm108MG1bseTIw1J8N7+P5EPfTEWZy6pTvmVjN8D7HFw=', '2E5+bpTUT1xN/JIc0MdLOUbLsCbmLKImiTyAFUuRRJI=', '2026-08-07 08:09:23', 'MBD Pay currency generated', 'Success'),
(32, 'MBD202608071010098CF982D41A', '9411272563', 'Currency Generated', 'g7KsrnZ3wVs/FyQuzwT+y7QejVdK9uiofKy3uNd3EF8=', 'lvrXozfPgyZxpG6UnPkcDBao9bShHjbcLpSymUV/SjY=', 'lo5iFR+1NTUxKdmkUwpgSbF6a5z/i8U7XSW8fBNuBao=', '2026-08-07 08:10:09', 'MBD Pay currency generated', 'Success'),
(33, 'MBD20260807101313F2A91A174D', '9411272563', 'Currency Generated', 'w3hYuNjUZjUHyQ7+9fAbf8Up/7b6x4Yu6CRVdB4PjhM=', 'G5rCvFQxgy2UBhV2KJiku7ZO/zuA3b0olOCi6jzI+qY=', 'q6UwR6ZcHhqDNm6Oa+OFmJrRDr0UO/O+VACtkt2p5IE=', '2026-08-07 08:13:14', 'MBD Pay currency generated', 'Success'),
(34, 'MBD202608071015406CDB2F8474', '9411272563', 'Currency Generated', '8xhcZTHRPzQwAhxH9Rh+gxF7iptKAmwvRP3zdpyHg6E=', 'yME53T0Prw2mnIAi7MN4KIQLznwT1G1IQK6OZOH5xi4=', 'vuTylm83DbLHaLSLLehJQA3aTezQQosCKN7h1w9pCCE=', '2026-08-07 08:15:40', 'MBD Pay currency generated', 'Success'),
(35, 'MBD202608071016323656718171', '9411272563', 'Currency Generated', 'mFWwLCP709Joi813ELzLss7H5irjeEoyeI2bZCmAM7Y=', 'Rtm09Z3KU19iojT3N6/RCWJnc8BazM02UxjlkgM1Weg=', '4K9pcW4Qur9Rfk52Q4ORer3ZjAnbQFeJ3FhQ5sHs+h0=', '2026-08-07 08:16:32', 'MBD Pay currency generated', 'Success'),
(36, 'MBD202608071017269113218413', '9411272563', 'Currency Generated', '4SjT00F90IHoyofTcwnJqbXy+pAw0yrQtFeEj3XpSe4=', 'xGxym8V4M8EeTsT15gKDozip58QWrmIdMiknD/KrK58=', 'NlCTyq8or2pIX/VfAZb0UpKIoyXMHJfntR1TZARtfRI=', '2026-08-07 08:17:26', 'MBD Pay currency generated', 'Success'),
(37, 'MBD20260807101750355F40B5EC', '9411272563', 'Currency Generated', '3Fail0xQKbbyrn4ph5QZt0NJkF58/rqOFnZQ/GN/Qts=', 'BG29P1ZlqQaZ7c4PWHiHVpX9TRdQDf+lxYRbvTmo8v0=', 'gbsWVkb5jQZplRgfGqAUoUyTG6Cf8vajIIgHlKn8q5E=', '2026-08-07 08:17:50', 'MBD Pay currency generated', 'Success'),
(38, 'MBD202608071020267DFD5285DA', '9411272563', 'Currency Generated', '7zKC2mmM4KXpk+WwftOKrQO8I0Oi+erBYYU+sfTYba0=', '8Lx7NPTeB79Nduu1CHS+Iv2rAdia2YMOyyhUmzifsJA=', 'LCCMDL2ipw1Zxij4oZLwksL039cJmmNqD8vdOQFNtC8=', '2026-08-07 08:20:26', 'MBD Pay currency generated', 'Success'),
(39, 'MBD202608071027377B0A1B2E83', '9411272563', 'Currency Generated', 'fpDSFcmTw8T6uj3Uqs3qlHDSDgUUU1mU7yjKO+dWIo8=', 'a0yl4m2FhcTrroClD9qBSsO4zhg9dbsex7ZC9fIe5DA=', 'fWILNdZgnlL3x0j/3jZrclHBQmR13nFF5iHs/Tvo5bQ=', '2026-08-07 08:27:37', 'MBD Pay currency generated', 'Success'),
(40, 'MBD2026080710274788B2EE6531', '9411272563', 'Currency Generated', 'JIapKA+wciVX2JS0bnIdSpt13nhnrPYfb4g14P1xZJM=', '7u+Db57gWFMdenPvNs/JQqKzi74WiCWGSlUAZZzPSVs=', 'VOV5MAxkn2D+DKHzNv5/5Tx+64iYoQUIg3UGhNDmB/s=', '2026-08-07 08:27:47', 'MBD Pay currency generated', 'Success'),
(41, 'MBD2026080710332163399B814C', '9411272563', 'Currency Generated', '0MvOmHNI2vLYIKD1e1UJFyuYpav9pMrCHYTSxlm+XrI=', 'pvWKIpKFDzR4LyGBV+fX37lvtANuUGNetc4cgcYtI+c=', 'CFelyzJgF2orjfKSX/TX4YlZoQTnvmMu/DyuAQagIrg=', '2026-08-07 08:33:21', 'MBD Pay currency generated', 'Success'),
(42, 'MBD20260807104044C5EF9319BA', '9411272563', 'Currency Generated', 'VkXq+zoIuJOWjqt0MHf9htR9V5JidI3FCmqHfeCGQhE=', 'n8UVanHh9kdFbcBcQmR6HlDUMiCeU72ehn3Ek8rOTiI=', 'wl/TjHcYR14Lp5fneJrnSrAGBW43vwdVbr+Vl03uEcI=', '2026-08-07 08:40:44', 'MBD Pay currency generated', 'Success'),
(43, 'MBD2026080710405643FFC0024F', '9411272563', 'Currency Generated', 'KUV/1A93Y+afMtuiNp7ZVPei3ZlRM1Jj0l3ofRchJP8=', 'fvwOss+l81hG7g7CiB/JgWG0OHXy0nbFWzcUVB0hCdA=', '0+9BY+TF2aWjZpTc0J3FSazs03vOYQcZBa5YJu3iN3o=', '2026-08-07 08:40:56', 'MBD Pay currency generated', 'Success'),
(44, 'MBD20260807104524922F6E32D0', '9411272563', 'Currency Generated', '19F8Kjq/S4eCbcvDwu5lQjveUSiISCTYSXUrbgqQmiA=', 'prC3NjlRt4z9p5EgChaQzXb5UdRMdUgqRC2rNlfyEZg=', 'D8CsshQayh5HSxbqlpDI798DFMwVRjiragy7UCu0ySE=', '2026-08-07 08:45:24', 'MBD Pay currency generated', 'Success'),
(45, 'MBD20260807113528C7F81892BC', '9411272563', 'Currency Generated', 'V7WZBt+OoC50vsVzzWkPfkUMOJexHM5a7DQH+4yYmDU=', 'Ryc3qqbITHQgFFfVcfHDbe8RDUavSIvD6uE71oQELb8=', 'f8z3ktUEQL2aTAMB7bx/G89K2Z9z9644ajP8ZM4xmSA=', '2026-08-07 09:35:28', 'MBD Pay currency generated', 'Success'),
(46, 'MBD20260807113633092F96365A', '9411272563', 'Currency Generated', 'AN2NDCJuVq7P/oqnziqr+M/K8TdYrmwJObngNzbkTKA=', 'lbPuOQF+4uT7Q1OVzq8l2aPAEwl29B3zv5YMhVsXUdo=', 'oQDV/ojU3zNugpzY8bjwUmBBWOS5qhtZ8BNI5amsY1E=', '2026-08-07 09:36:33', 'MBD Pay currency generated', 'Success'),
(47, 'MBD20260807113818BC9D6C9F66', '9411272563', 'Currency Generated', 'WAC8zWeaWzkEXihuYc9q6dWKLdw/Ao7pwzfArheuk+Y=', '07Inj5HxhmR1UzJ8nO0Pmv0N1zBpHcqbKQm33UhDDJg=', '90N3urm9XbzvtYC3/ST/ECPPLCR4U/ggofnjj1CoN/s=', '2026-08-07 09:38:18', 'MBD Pay currency generated', 'Success'),
(48, 'MBD20260807114323E864A00FE5', '9411272563', 'Currency Generated', 'iF0C/mjVxMetwxVaFHH8ihoDmMNwflaypn0hCIgBmp8=', '7A36nXuOA9+HptkmTcrFVVjhcGD4I4UsEI5JA44Vzaw=', 'zVX0ccuXQZSDaeye/H0nKiPFmJCV842d/vKIzNSe43I=', '2026-08-07 09:43:23', 'MBD Pay currency generated', 'Success'),
(49, 'MBD20260807115101FF52A73E73', '9411272563', 'Currency Generated', 'h9mjTANeBjjaU5icoD7iJ2OL8dSURPyzjEwVSbNQOvA=', 'JxUV5SgLp8O9FbqBSyWRAnx9b9UEkGukgSoSLUeJ5x8=', '1Vz1npCE69mRkoKt8UKHQFLt6RUKHSvRwvSXcetvigI=', '2026-08-07 09:51:01', 'MBD Pay currency generated', 'Success'),
(50, 'MBD2026080711513599BF84F6D9', '9411272563', 'Currency Generated', 'rdiz7G0vuiFPfCaiLQ2oboP6mv2jfPMBIgD9d9y8fRg=', '4rVL62tiyMuqxzZ2jr35vQA27Jx3SjUQYKbOVqcEVEs=', 'LMBmkod0cjOD66H/P6bPEGlZB5LlR4YLXvBkXElI6uo=', '2026-08-07 09:51:35', 'MBD Pay currency generated', 'Success'),
(51, 'MBD202608071154411D8AA15A65', '9411272563', 'Currency Generated', 'c6J4IKDrXEWkPO8WLMIoa3tZcrQBrGodWDuE3GcBXPE=', 'y6kc39lIW6QSZ07wtB/VkH48syoKUBB1UVKwVe5FDaE=', 'X8yeBmH2PEHUiN+bPG3ALP3CbN38AS77MRwa/Q2SyNg=', '2026-08-07 09:54:41', 'MBD Pay currency generated', 'Success'),
(52, 'MBD20260807115536F7F7FB8CFF', '9411272563', 'Currency Generated', '6YS6v0DDw+n0o7XrKVKdA+is9WnfGLFJAmfPrqVI9WI=', 'PgaM8Nz6O8+ZdeQ/JkbLY9hVVIVFyhDYBFijbcUJHgE=', 'U9K6TDzS87C3O/HyXo/iylh6xFKq3pWWYpbN4pNnoM4=', '2026-08-07 09:55:36', 'MBD Pay currency generated', 'Success'),
(53, 'MBD20260807115711ADCA5276B1', '9411272563', 'Currency Generated', '8Mc/rHhBDdptjk2IsCJ5Jj8N7vukEEQp+DYIfYcxDIk=', 'EWonxlre2X//Mw5QbNDlZJ/XJbHS+GMPdhwtgxBwTZY=', 'DhOGy+gIL7sYDKzJCb1o394Z9e6Ab2ZzQ7gyFYVgeeA=', '2026-08-07 09:57:11', 'MBD Pay currency generated', 'Success'),
(54, 'MBD2026080712013398694D7A48', '9411272563', 'Currency Generated', 'pCb+W6sA9q4ihgtJAOE6wVC0BTE6uqBna/05EqSxq7c=', 'QCKofu/s+oDSEfDdULYsvwN3xqVI1nprbVY5tmgYe14=', 'UMCBB0PuZ7lgDNwyyaFkTuaZ1OPs/jILqmxfHgF5pXA=', '2026-08-07 10:01:33', 'MBD Pay currency generated', 'Success'),
(55, 'MBD202608071534196ACF9DC335', '9411272563', 'Currency Generated', 'NlYWCFQjODRN7TOw+TvGVlLDSfc3jmlByHqHoa/jPHg=', 'QtcGshNQcxf5Gbh6smxoKqp3WU3EQuD/PLWGoOe2RhU=', '4/uxsi6KkRRykQWZuHoqVx4uu7rDDJlo+3J4yumLahM=', '2026-08-07 10:04:19', 'MBD Pay currency generated', 'Success'),
(56, 'MBD17860971946111', '9411272563', 'Credit', 'YeiZkk8XzMVPPiRsV82fNMBQKNXpAsvaYmjcrA24Oc8=', '0YA9kEPDpmPg1/y5GHEtYExhIxp8ndfc9s1h/HSq6m8=', 'bcOfof+5o1/PaqRybTT4GthrarPcUZqIBJ54peyp6Ls=', '2026-08-07 10:06:34', 'Wallet recharge from bank account', 'Success'),
(57, 'MBD17860973997087', '9411272563', 'Debit', 'OaibR/XGqwDrH1qyBG0VQwKs3jaRAtQ08bhaDZ5RJTc=', 'E7lpsLVljIDt4T0S9FlEbY1CGw45WUGYzxStuMjQfg0=', '7QS6lS5jbNgix/cN6HEh9hsI+TGqH5Tiu2pESBAukmY=', '2026-08-07 10:09:59', 'Withdrawal to MBD bank account', 'Success'),
(58, 'MBD202608071542126F9892EA7E', '9411272563', 'Currency Generated', 'qhgqkGEYwNzn5ZwLK10RYSmKEouxJ87fT7lf17rsW2Y=', 'Vngrp9ACXLLqauuXVV2m9zXLHljQt+UfMkyhilGQDT8=', 'Nv77/+dYtHjCtYj/02F9sNeyjEUiGRTz5fzCUBq2IEI=', '2026-08-07 10:12:12', 'MBD Pay currency generated', 'Success'),
(59, 'MBD20260807155247C69473BF01', '9411272563', 'Currency Generated', 'HaDfmtZcjWMgvxbV469E+N2EGLX0uJzHd+PcoDssLdE=', 'pnt4aYuCjYlrAY4ujVer4q2j/g0uZXxCbuzZI5TITbU=', 'n7B7F4TIPnQb/s7gTxjkpjwpG7nAzI8oq01+fGSzPLY=', '2026-08-07 10:22:47', 'MBD Pay currency generated', 'Success'),
(60, 'MBD20260808153059AFC1AAFE3A', '9411272563', 'Currency Generated', 'EuVqo9qlR1ZYiCab/YKtyjPOc34roIeAI2xCPxbBKjA=', 'PZL3E8ecmSPAUHCGS2942Q9w3fU3dyyp3T4gp4p9L9w=', '9j2xAqcqdvURT4CFZ3C9ym2mcE+QXDXbPEpajn1SuuM=', '2026-08-08 10:00:59', 'MBD Pay currency generated', 'Success'),
(61, 'MBD20260808153325D8642ED461', '9411272563', 'Currency Generated', 'S8QydKHnVMOI9Ipfe+VjoMkvFyKkiSRjwlAwnJKOozI=', 'Bej2ODv0D15DPBjEzILnsJvSus0np9YGddvlakr1328=', '6aAxcWbP2A9vxLqAlQ6OUAB8Rius30LhF2XgB2ehU3w=', '2026-08-08 10:03:25', 'MBD Pay currency generated', 'Success');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
