-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 28, 2025 at 02:48 PM
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
-- Database: `borrowsmart`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `adminID` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `borrowingrecord`
--

CREATE TABLE `borrowingrecord` (
  `recordID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `instrumentID` int(11) NOT NULL,
  `borrowDate` date DEFAULT NULL,
  `returnDate` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `generatereports`
--

CREATE TABLE `generatereports` (
  `reportID` int(11) NOT NULL,
  `generatedBy` varchar(100) DEFAULT NULL,
  `reportType` varchar(50) DEFAULT NULL,
  `dateGenerated` date DEFAULT NULL,
  `reportContent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `instruments`
--

CREATE TABLE `instruments` (
  `instrumentID` int(11) NOT NULL,
  `instrumentName` varchar(255) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `availabilityStatus` varchar(50) DEFAULT NULL,
  `borrowedBy` int(11) DEFAULT NULL,
  `borrowDate` date DEFAULT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `instruments`
--

INSERT INTO `instruments` (`instrumentID`, `instrumentName`, `category`, `availabilityStatus`, `borrowedBy`, `borrowDate`, `name`) VALUES
(1, NULL, 'Brassband', 'Available', NULL, NULL, 'Trumpet'),
(2, NULL, 'Brassband', '1', NULL, NULL, 'Trombone'),
(3, NULL, 'Percussion', '1', NULL, NULL, 'Snare Drum'),
(4, NULL, 'Brassband', 'Borrowed', 4, '2025-01-22', 'Euphonium'),
(9, NULL, 'Brassband', '1', NULL, NULL, 'Trumpet'),
(10, NULL, 'Brassband', '1', NULL, NULL, 'Trombone'),
(11, NULL, 'Percussion', '1', NULL, NULL, 'Snare Drum'),
(12, NULL, 'Brassband', '1', NULL, NULL, 'Euphonium');

-- --------------------------------------------------------

--
-- Table structure for table `returningrecord`
--

CREATE TABLE `returningrecord` (
  `returnID` int(11) NOT NULL,
  `recordID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `instrumentID` int(11) NOT NULL,
  `returnDate` date DEFAULT NULL,
  `conditionStatus` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `return_history`
--

CREATE TABLE `return_history` (
  `historyID` int(11) NOT NULL,
  `instrumentID` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `borrowDate` date NOT NULL,
  `returnDate` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `return_history`
--

INSERT INTO `return_history` (`historyID`, `instrumentID`, `uid`, `borrowDate`, `returnDate`) VALUES
(1, 1, 1, '2025-01-22', '2025-01-22'),
(4, 1, 4, '2025-01-22', '2025-01-22');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `uid` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `role` enum('user','staff','admin') NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`uid`, `name`, `email`, `phone`, `role`, `password`) VALUES
(1, 'Fazreen Saleha', 'fzreenha@gmail.com', '0108874515', 'user', '$2y$10$Wqx1970.dFhwRuM1KpAoXuiQNPv3jIxg7rTjMVNXHFWDOPFYPJP76'),
(4, 'Fazreen ', 'ai220045@student.uthm.edu.my', '0101111111', 'user', '$2y$10$UigHnuGmDELbf/0WQjtRdeYvsBFsjIFexXBEoaRSt38aoH/lvbBR2');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`adminID`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `borrowingrecord`
--
ALTER TABLE `borrowingrecord`
  ADD PRIMARY KEY (`recordID`),
  ADD KEY `userID` (`userID`),
  ADD KEY `instrumentID` (`instrumentID`);

--
-- Indexes for table `generatereports`
--
ALTER TABLE `generatereports`
  ADD PRIMARY KEY (`reportID`);

--
-- Indexes for table `instruments`
--
ALTER TABLE `instruments`
  ADD PRIMARY KEY (`instrumentID`);

--
-- Indexes for table `returningrecord`
--
ALTER TABLE `returningrecord`
  ADD PRIMARY KEY (`returnID`),
  ADD KEY `recordID` (`recordID`),
  ADD KEY `userID` (`userID`),
  ADD KEY `instrumentID` (`instrumentID`);

--
-- Indexes for table `return_history`
--
ALTER TABLE `return_history`
  ADD PRIMARY KEY (`historyID`),
  ADD KEY `instrumentID` (`instrumentID`),
  ADD KEY `uid` (`uid`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`uid`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `adminID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `borrowingrecord`
--
ALTER TABLE `borrowingrecord`
  MODIFY `recordID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `generatereports`
--
ALTER TABLE `generatereports`
  MODIFY `reportID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `instruments`
--
ALTER TABLE `instruments`
  MODIFY `instrumentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `returningrecord`
--
ALTER TABLE `returningrecord`
  MODIFY `returnID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `return_history`
--
ALTER TABLE `return_history`
  MODIFY `historyID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `uid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `borrowingrecord`
--
ALTER TABLE `borrowingrecord`
  ADD CONSTRAINT `borrowingrecord_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`uid`),
  ADD CONSTRAINT `borrowingrecord_ibfk_2` FOREIGN KEY (`instrumentID`) REFERENCES `instruments` (`instrumentID`);

--
-- Constraints for table `returningrecord`
--
ALTER TABLE `returningrecord`
  ADD CONSTRAINT `returningrecord_ibfk_1` FOREIGN KEY (`recordID`) REFERENCES `borrowingrecord` (`recordID`),
  ADD CONSTRAINT `returningrecord_ibfk_2` FOREIGN KEY (`userID`) REFERENCES `users` (`uid`),
  ADD CONSTRAINT `returningrecord_ibfk_3` FOREIGN KEY (`instrumentID`) REFERENCES `instruments` (`instrumentID`);

--
-- Constraints for table `return_history`
--
ALTER TABLE `return_history`
  ADD CONSTRAINT `return_history_ibfk_1` FOREIGN KEY (`instrumentID`) REFERENCES `instruments` (`instrumentID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `return_history_ibfk_2` FOREIGN KEY (`uid`) REFERENCES `users` (`uid`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
