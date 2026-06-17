-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 12, 2026 at 05:52 AM
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
-- Database: `helpdesk`
--

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `category` enum('Complaint','Concern','Service Request') NOT NULL,
  `description` text NOT NULL,
  `status` enum('Pending','Ongoing','Resolved') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `ai_emotion` varchar(50) DEFAULT NULL,
  `ai_polarity` varchar(50) DEFAULT NULL,
  `ai_summary` text DEFAULT NULL,
  `ai_severity` varchar(50) DEFAULT NULL,
  `ai_polarity_score` float DEFAULT 0,
  `ai_subjectivity_score` float DEFAULT 0,
  `assigned_to` int(11) DEFAULT NULL,
  `action_taken` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`id`, `user_id`, `title`, `category`, `description`, `status`, `created_at`, `latitude`, `longitude`, `ai_emotion`, `ai_polarity`, `ai_summary`, `ai_severity`, `ai_polarity_score`, `ai_subjectivity_score`) VALUES
(22, NULL, 'fire', 'Concern', 'On April 10, 2026, a major fire broke out at the Acme Manufacturing warehouse on 4th Street, causing extensive damage to the facility. The fire was reported at approximately 6:30 PM, and firefighters from the local department arrived within ten minutes to find the building heavily involved in smoke and flames. Although the building was occupied, all employees were safely evacuated, and no injuries or fatalities were reported. Preliminary investigation suggests the fire began in the storage area due to a faulty electrical panel and spread rapidly due to the presence of stored cleaning products. Firefighters managed to bring the blaze under control within two hours, preventing it from reaching neighboring businesses, but an estimated \\(\\$\\text{500,000}\\) in damages has been reported, with the storage and shipping bays fully destroyed.', 'Pending', '2026-05-12 03:37:02', 14.59510672, 120.98170681, 'Fear', 'Neutral', '• on april 10, 2026, a major fire broke out at the acme manufacturing warehouse on 4th street, causing extensive damage to the facility\n• the fire was reported at approximately 6:30 pm, and firefighters from the local department arrived within ten minutes to find the building heavily involved in smoke and flames\n• although the building was occupied, all employees were safely evacuated, and no injuries or fatalities were reported\n• preliminary investigation suggests the fire began in the storage area due to a faulty electrical panel and spread rapidly due to the presence of stored cleaning products', 'High', -0.0319444, 0.353704),
(23, NULL, 'quema', 'Concern', 'Reporte del Incidente: El Quema en el Pueblo\r\n\r\nAyer, un grande quema ya ocurri cerca del oficina y el escuela. El fuego ya empieza accidentalmente porque un persona ya abandona un vela prendido cerca de un cajon de madera. De alisto, el daño ya queda grande; el edificio ya queda machucado y mucho kasangkapan ya queda quemado. El maga gente ya dalih para saca balde con agua para ayuda, mientras el maga vecino ya dale aviso a los bomberos. Gracias a Dios, nadie ya queda muerto, pero el lugar ahora esta abandonado y lleno de uling. Es importante tene paciencia y cuidado para evita este clase de accidente en el futuro.', 'Pending', '2026-05-12 03:47:29', 14.58874355, 120.97739075, 'Sadness', 'Positive', '• incident report: the burning in the town\r\n\r\nYesterday, a large fire occurred near the office and school\n• The fire starts accidentally because a person leaves a lit candle near a wooden box\n• ready, the damage is already great; The building is already damaged and much of the kasangkapan is already burned\n• The people are already there to take out a bucket of water to help, while the neighbor is already alerting the firefighters', 'Critical', 0.172024, 0.479464),
(24, NULL, 'kema', 'Concern', 'chene kema aki na zamboanga city onde chene ta bende carbon pabor liba bombero para ayuda kunamun aki', 'Pending', '2026-05-12 03:49:29', NULL, NULL, 'Neutral', 'Neutral', '• chain fire aki na zamboanga city where chaine ta binde carbon pabor liba bombero para help kunamun aki', 'High', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','resident') DEFAULT 'resident',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'admin', 'admin@gmail.com', '$2y$10$FjpfRwJGLhFri.5Xx45kiO5Ha4iFDh0fvDURT9x4z4bu8RW3Xean6', 'admin', '2026-04-14 12:36:14'),
(2, 'resident', 'resident@gmail.com', '$2y$10$OxzBApOP8enXKgXChGUcvei4nDJ7BC1L4cE..Kz7yW2ntZJVxlkuW', 'resident', '2026-04-14 15:09:33'),
(3, 'test', 'test123@gmail.com', '$2y$10$/i1AHAZfVhyfavZWVcIlwOKS65jRFw26kL8lQo2as2dCpsGLvnrSG', 'resident', '2026-04-29 13:02:43');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reports` (Task Assignment)
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
