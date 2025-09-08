-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 03, 2025 at 04:58 PM
-- Server version: 10.4.21-MariaDB
-- PHP Version: 8.0.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `assetmanagersystem`
--

-- --------------------------------------------------------

--
-- Table structure for table `assetattributes`
--

CREATE TABLE `assetattributes` (
  `AttributeID` int(11) NOT NULL,
  `TypeID` int(11) NOT NULL,
  `Name` varchar(100) NOT NULL,
  `DataType` enum('Text','Number','Date','Boolean') DEFAULT 'Text',
  `is_required` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `assetattributes`
--

INSERT INTO `assetattributes` (`AttributeID`, `TypeID`, `Name`, `DataType`, `is_required`, `created_at`) VALUES
(1, 1, 'Ram', 'Text', 1, '2025-08-27 19:35:54'),
(2, 1, 'windows', 'Text', 1, '2025-08-27 19:35:59'),
(3, 1, 'core', 'Text', 0, '2025-08-27 19:36:03');

-- --------------------------------------------------------

--
-- Table structure for table `assethistory`
--

CREATE TABLE `assethistory` (
  `HistoryID` int(11) NOT NULL,
  `AssetID` int(11) NOT NULL,
  `OldValue` varchar(255) DEFAULT NULL,
  `NewValue` varchar(255) DEFAULT NULL,
  `ActionDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `Notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `assethistory`
--

INSERT INTO `assethistory` (`HistoryID`, `AssetID`, `OldValue`, `NewValue`, `ActionDate`, `Notes`) VALUES
(175, 32, 'Unassigned', 'Amgad Mamdouh', '2025-09-01 11:06:27', ''),
(176, 32, 'Unassigned', 'Pola Mina', '2025-09-03 14:01:04', ''),
(177, 32, 'Pola Mina', 'Unassigned', '2025-09-03 14:11:18', ''),
(178, 31, 'Working', 'In Repair', '2025-09-03 14:13:50', 'dfsdfsdfdsfdsf');

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

CREATE TABLE `assets` (
  `AssetID` int(11) NOT NULL,
  `TypeID` int(11) NOT NULL,
  `SerialNumber` varchar(120) NOT NULL,
  `TagNumber` varchar(100) DEFAULT NULL,
  `Status` enum('Working','In Repair','Trashed') DEFAULT 'Working',
  `AssignedTo` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `assets`
--

INSERT INTO `assets` (`AssetID`, `TypeID`, `SerialNumber`, `TagNumber`, `Status`, `AssignedTo`, `created_at`) VALUES
(28, 1, '1', '12', 'Working', 8, '2025-09-01 10:12:10'),
(29, 1, '2', '23', 'Working', 6, '2025-09-01 10:12:24'),
(30, 1, '3', '34', 'Working', 10, '2025-09-01 10:12:46'),
(31, 1, '4', '45', 'In Repair', 7, '2025-09-01 10:13:10'),
(32, 1, '6', '56', 'Working', NULL, '2025-09-01 10:13:38');

-- --------------------------------------------------------

--
-- Table structure for table `assetspecs`
--

CREATE TABLE `assetspecs` (
  `SpecID` int(11) NOT NULL,
  `AssetID` int(11) NOT NULL,
  `AttributeID` int(11) NOT NULL,
  `ValueText` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `assetspecs`
--

INSERT INTO `assetspecs` (`SpecID`, `AssetID`, `AttributeID`, `ValueText`, `created_at`) VALUES
(85, 28, 1, '1', '2025-09-01 10:12:10'),
(86, 28, 2, '1', '2025-09-01 10:12:10'),
(87, 28, 3, '1', '2025-09-01 10:12:10'),
(88, 29, 1, '2', '2025-09-01 10:12:24'),
(89, 29, 2, '2', '2025-09-01 10:12:24'),
(90, 29, 3, '2', '2025-09-01 10:12:24'),
(91, 30, 1, '32', '2025-09-01 10:12:46'),
(92, 30, 2, '3', '2025-09-01 10:12:46'),
(93, 30, 3, '3', '2025-09-01 10:12:46'),
(94, 31, 1, '4', '2025-09-01 10:13:10'),
(95, 31, 2, '4', '2025-09-01 10:13:10'),
(96, 31, 3, '4', '2025-09-01 10:13:10'),
(97, 32, 1, '5', '2025-09-01 10:13:38'),
(98, 32, 2, '10', '2025-09-01 10:13:38'),
(99, 32, 3, '5', '2025-09-01 10:13:38');

-- --------------------------------------------------------

--
-- Table structure for table `assettypes`
--

CREATE TABLE `assettypes` (
  `TypeID` int(11) NOT NULL,
  `TypeName` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `assettypes`
--

INSERT INTO `assettypes` (`TypeID`, `TypeName`, `created_at`) VALUES
(1, 'Laptop', '2025-08-25 07:14:39');

-- --------------------------------------------------------

--
-- Table structure for table `asset_assignments`
--

CREATE TABLE `asset_assignments` (
  `AssignmentID` int(11) NOT NULL,
  `AssetID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `DateReceived` datetime DEFAULT NULL,
  `DateReturned` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `asset_assignments`
--

INSERT INTO `asset_assignments` (`AssignmentID`, `AssetID`, `EmployeeID`, `DateReceived`, `DateReturned`, `created_at`) VALUES
(55, 32, 6, '0005-05-05 00:00:00', '0100-01-01 00:00:00', '2025-09-01 11:09:22'),
(56, 31, 7, '0004-04-04 00:00:00', NULL, '2025-09-01 11:09:37'),
(57, 30, 10, '0003-03-03 00:00:00', NULL, '2025-09-01 11:09:52'),
(58, 29, 6, '0002-02-02 00:00:00', NULL, '2025-09-01 11:10:05'),
(59, 28, 8, '0001-01-01 00:00:00', NULL, '2025-09-01 11:10:16'),
(60, 32, 6, '0005-05-05 00:00:00', '0100-01-01 00:00:00', '2025-09-02 08:57:37'),
(62, 32, 7, '1990-09-13 00:00:00', '1999-09-01 00:00:00', '2025-09-03 14:10:14'),
(63, 32, 7, '1990-09-13 00:00:00', '1999-09-01 00:00:00', '2025-09-03 14:11:18'),
(64, 31, 7, '0004-04-04 00:00:00', NULL, '2025-09-03 14:13:50');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `DepartmentID` int(11) NOT NULL,
  `DepartmentName` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`DepartmentID`, `DepartmentName`) VALUES
(1, 'IT'),
(2, 'HR'),
(3, 'Finance'),
(4, 'Administration');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `EmployeeID` int(11) NOT NULL,
  `Name` varchar(100) NOT NULL,
  `TitleID` int(11) DEFAULT NULL,
  `DepartmentID` int(11) DEFAULT NULL,
  `LocationID` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`EmployeeID`, `Name`, `TitleID`, `DepartmentID`, `LocationID`, `created_at`) VALUES
(6, 'Amgad Mamdouh', 13, 1, 8, '2025-09-01 10:09:29'),
(7, 'Pola Mina', 12, 3, 9, '2025-09-01 10:09:46'),
(8, 'Adham Amr', 11, 1, 8, '2025-09-01 10:10:03'),
(9, 'mariam hatem', 10, 4, 10, '2025-09-01 10:10:22'),
(10, 'Fady Amr', 13, 1, 6, '2025-09-01 10:10:44');

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `LocationID` int(11) NOT NULL,
  `LocationName` varchar(100) NOT NULL,
  `ParentID` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`LocationID`, `LocationName`, `ParentID`) VALUES
(6, 'Elgouna', NULL),
(8, 'GHQ', 6),
(9, 'Sea cinema', 6),
(10, 'cairo', NULL),
(11, 'tagmo3', 10);

-- --------------------------------------------------------

--
-- Table structure for table `titles`
--

CREATE TABLE `titles` (
  `TitleID` int(11) NOT NULL,
  `TitleName` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `titles`
--

INSERT INTO `titles` (`TitleID`, `TitleName`) VALUES
(10, 'General Manger'),
(11, 'IT Help Desk'),
(12, 'Finacial controller'),
(13, 'Software developer');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assetattributes`
--
ALTER TABLE `assetattributes`
  ADD PRIMARY KEY (`AttributeID`),
  ADD KEY `idx_typeid` (`TypeID`);

--
-- Indexes for table `assethistory`
--
ALTER TABLE `assethistory`
  ADD PRIMARY KEY (`HistoryID`),
  ADD KEY `AssetID` (`AssetID`);

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`AssetID`),
  ADD UNIQUE KEY `SerialNumber` (`SerialNumber`),
  ADD UNIQUE KEY `TagNumber` (`TagNumber`),
  ADD KEY `TypeID` (`TypeID`),
  ADD KEY `AssignedTo` (`AssignedTo`);

--
-- Indexes for table `assetspecs`
--
ALTER TABLE `assetspecs`
  ADD PRIMARY KEY (`SpecID`),
  ADD UNIQUE KEY `uq_asset_attr` (`AssetID`,`AttributeID`),
  ADD KEY `AttributeID` (`AttributeID`);

--
-- Indexes for table `assettypes`
--
ALTER TABLE `assettypes`
  ADD PRIMARY KEY (`TypeID`),
  ADD UNIQUE KEY `TypeName` (`TypeName`);

--
-- Indexes for table `asset_assignments`
--
ALTER TABLE `asset_assignments`
  ADD PRIMARY KEY (`AssignmentID`),
  ADD KEY `idx_asset` (`AssetID`),
  ADD KEY `idx_employee` (`EmployeeID`),
  ADD KEY `idx_current` (`DateReturned`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`DepartmentID`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`EmployeeID`),
  ADD KEY `TitleID` (`TitleID`),
  ADD KEY `DepartmentID` (`DepartmentID`),
  ADD KEY `LocationID` (`LocationID`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`LocationID`);

--
-- Indexes for table `titles`
--
ALTER TABLE `titles`
  ADD PRIMARY KEY (`TitleID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assetattributes`
--
ALTER TABLE `assetattributes`
  MODIFY `AttributeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `assethistory`
--
ALTER TABLE `assethistory`
  MODIFY `HistoryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=179;

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `AssetID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `assetspecs`
--
ALTER TABLE `assetspecs`
  MODIFY `SpecID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT for table `assettypes`
--
ALTER TABLE `assettypes`
  MODIFY `TypeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `asset_assignments`
--
ALTER TABLE `asset_assignments`
  MODIFY `AssignmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `DepartmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `EmployeeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `LocationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `titles`
--
ALTER TABLE `titles`
  MODIFY `TitleID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assetattributes`
--
ALTER TABLE `assetattributes`
  ADD CONSTRAINT `fk_assetattributes_type` FOREIGN KEY (`TypeID`) REFERENCES `assettypes` (`TypeID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `assethistory`
--
ALTER TABLE `assethistory`
  ADD CONSTRAINT `assethistory_ibfk_1` FOREIGN KEY (`AssetID`) REFERENCES `assets` (`AssetID`) ON DELETE CASCADE;

--
-- Constraints for table `assets`
--
ALTER TABLE `assets`
  ADD CONSTRAINT `assets_ibfk_1` FOREIGN KEY (`TypeID`) REFERENCES `assettypes` (`TypeID`),
  ADD CONSTRAINT `assets_ibfk_2` FOREIGN KEY (`AssignedTo`) REFERENCES `employees` (`EmployeeID`) ON DELETE SET NULL;

--
-- Constraints for table `assetspecs`
--
ALTER TABLE `assetspecs`
  ADD CONSTRAINT `assetspecs_ibfk_1` FOREIGN KEY (`AssetID`) REFERENCES `assets` (`AssetID`) ON DELETE CASCADE,
  ADD CONSTRAINT `assetspecs_ibfk_2` FOREIGN KEY (`AttributeID`) REFERENCES `assetattributes` (`AttributeID`) ON DELETE CASCADE;

--
-- Constraints for table `asset_assignments`
--
ALTER TABLE `asset_assignments`
  ADD CONSTRAINT `fk_asset_assignments_asset` FOREIGN KEY (`AssetID`) REFERENCES `assets` (`AssetID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_asset_assignments_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`EmployeeID`) ON DELETE CASCADE;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`TitleID`) REFERENCES `titles` (`TitleID`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`DepartmentID`) REFERENCES `departments` (`DepartmentID`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_ibfk_3` FOREIGN KEY (`LocationID`) REFERENCES `locations` (`LocationID`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
