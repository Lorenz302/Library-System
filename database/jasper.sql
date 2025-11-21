-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 21, 2025 at 03:31 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `jasper`
--

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `book_id` int(11) NOT NULL,
  `book_name` varchar(255) NOT NULL,
  `book_description` text DEFAULT NULL,
  `category` varchar(255) NOT NULL,
  `favorite` tinyint(1) NOT NULL DEFAULT 0,
  `author` varchar(255) DEFAULT NULL,
  `publication_year` year(4) DEFAULT NULL,
  `total_copies` int(11) NOT NULL DEFAULT 1,
  `available_copies` int(11) NOT NULL DEFAULT 1,
  `book_image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`book_id`, `book_name`, `book_description`, `category`, `favorite`, `author`, `publication_year`, `total_copies`, `available_copies`, `book_image_path`) VALUES
(2, 'blue', 'blue', 'Electronics', 0, 'lorens', '2011', 5, 5, 'Book_Images/book_690461b11a09d8.33575919.jpg'),
(3, 'red', 'garatihin kita', 'Martial arts', 0, 'james', '2023', 5, 5, 'Book_Images/book_690461b6c54be7.31502369.jpg'),
(4, 'yellow', 'taba', 'cookbook', 0, 'chrsitian', '2012', 5, 5, 'Book_Images/book_690461d77b5cd9.39305234.jpg'),
(5, 'template1', 'asd', 'Breakfast', 0, 'chrsitian', '2012', 1, 1, 'Book_Images/book_69045d7f042b40.04259692.jpeg'),
(6, 'templat2', 'wad', 'Breakfast', 0, 'chrsitian', '2010', 23, 23, 'Book_Images/book_690461ce4e7b24.68065226.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `borrow_requests`
--

CREATE TABLE `borrow_requests` (
  `borrow_id` bigint(20) UNSIGNED NOT NULL,
  `id_number` int(11) NOT NULL,
  `firstname` varchar(255) NOT NULL,
  `lastname` varchar(255) NOT NULL,
  `gsuit_account` varchar(255) NOT NULL,
  `program_and_year` varchar(255) NOT NULL,
  `borrow_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `return_date` date NOT NULL,
  `borrow_status` varchar(255) NOT NULL DEFAULT 'Pending',
  `book_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrow_requests`
--

INSERT INTO `borrow_requests` (`borrow_id`, `id_number`, `firstname`, `lastname`, `gsuit_account`, `program_and_year`, `borrow_date`, `due_date`, `return_date`, `borrow_status`, `book_id`) VALUES
(15, 2201004, 'Lorenz', 'Feliciano', 'lorenzfeliciano303@gmail.com', 'N/A', '2025-10-31', NULL, '2025-11-07', 'Returned', 2),
(29, 2201004, 'Lorenz', 'Feliciano', 'lorenzfeliciano303@gmail.com', 'IT-3', '2025-10-31', '2025-11-12', '2025-10-31', 'Returned', 3),
(30, 2201004, 'Lorenz', 'Feliciano', 'lorenzfeliciano303@gmail.com', 'IT-4', '2025-10-31', '2025-11-12', '2025-10-31', 'Returned', 5),
(31, 2201004, '', '', '', '', '2025-10-31', '2025-11-14', '2025-10-31', 'Returned', 5),
(32, 2201004, 'Lorenz', 'Feliciano', 'lorenzfeliciano303@gmail.com', 'IT-4', '2025-10-31', '2025-11-12', '2025-10-31', 'Returned', 5),
(33, 2201004, '', '', '', '', '2025-10-31', '2025-11-14', '2025-10-31', 'Returned', 5),
(34, 2201004, 'Lorenz', 'Feliciano', 'lorenzfeliciano303@gmail.com', 'IT-4', '2025-10-31', '2025-11-13', '2025-10-31', 'Returned', 5),
(35, 2201004, 'Lorenz', 'Feliciano', 'lorenzfeliciano303@gmail.com', 'IT-4', '2025-10-31', '2025-11-12', '2025-10-31', 'Returned', 5),
(39, 2201004, 'Lorenz', 'Feliciano', 'lorenzfeliciano303@gmail.com', 'IT-4', '2025-10-31', '2025-11-02', '2025-10-31', 'Returned', 5),
(40, 2201004, 'Lorenz', 'Feliciano', 'lorenzfeliciano303@gmail.com', 'IT-4', '2025-10-31', '2025-11-04', '2025-10-31', 'Returned', 5),
(41, 123456, '', '', '', '', '2025-10-31', '2025-11-14', '2025-10-31', 'Returned', 5),
(42, 2201004, '', '', '', '', '2025-10-31', '2025-11-14', '2025-10-31', 'Returned', 5),
(43, 2201004, 'Van', 'ryan samiano', 'lorenzfeliciano303@gmail.com', 'IT-47', '2025-10-31', '2025-11-01', '2025-10-31', 'Returned', 5),
(44, 123456, '', '', '', '', '2025-10-31', '2025-11-14', '2025-10-31', 'Returned', 5),
(45, 2201004, '', '', '', '', '2025-10-31', '2025-11-14', '2025-10-31', 'Returned', 5),
(47, 123456, 'Lorenz', 'Feliciano', 'soonecamz@gmail.com', 'IT-4', '2025-10-31', '2025-11-02', '2025-10-31', 'Returned', 4),
(48, 2201004, 'Van', 'ryan samiano', 'lorenzfeliciano303@gmail.com', 'IT-47', '2025-10-31', '2025-11-01', '2025-10-31', 'Returned', 2),
(49, 2201004, 'Van', 'ryan samiano', 'lorenzfeliciano303@gmail.com', 'IT-47', '2025-10-31', '2025-11-22', '2025-10-31', 'Returned', 6),
(51, 2201004, 'Van', 'ryan samiano', 'lorenzfeliciano303@gmail.com', 'IT-47', '2025-10-31', NULL, '0000-00-00', 'Rejected', 2),
(52, 2201004, 'Van', 'ryan samiano', 'lorenzfeliciano303@gmail.com', 'IT-47', '2025-10-31', NULL, '0000-00-00', 'Rejected', 4),
(53, 2201004, 'Van', 'ryan samiano', 'lorenzfeliciano303@gmail.com', 'IT-47', '2025-10-31', NULL, '0000-00-00', 'Rejected', 2),
(54, 2201004, 'Van', 'ryan samiano', 'lorenzfeliciano303@gmail.com', 'IT-47', '2025-10-31', NULL, '0000-00-00', 'Rejected', 2),
(55, 2201004, 'Van', 'ryan samiano', 'lorenzfeliciano303@gmail.com', 'IT-47', '2025-10-31', '2025-11-02', '2025-10-31', 'Returned', 2),
(56, 2201004, 'Van', 'ryan samiano', 'lorenzfeliciano303@gmail.com', 'IT-47', '2025-10-31', NULL, '0000-00-00', 'Rejected', 5),
(57, 2201004, 'Van', 'ryan samiano', 'lorenzfeliciano303@gmail.com', 'IT-47', '2025-10-31', NULL, '0000-00-00', 'Rejected', 2),
(58, 2201004, 'Van', 'ryan samiano', 'lorenzfeliciano303@gmail.com', 'IT-47', '2025-10-31', NULL, '0000-00-00', 'Rejected', 6),
(59, 2201004, 'Van', 'ryan samiano', 'lorenzfeliciano303@gmail.com', 'IT-47', '2025-10-31', NULL, '0000-00-00', 'Rejected', 6);

-- --------------------------------------------------------

--
-- Table structure for table `notification`
--

CREATE TABLE `notification` (
  `notification_id` int(11) NOT NULL,
  `notification_title` varchar(255) NOT NULL,
  `notification_body` text NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservation_requests`
--

CREATE TABLE `reservation_requests` (
  `reservation_id` bigint(20) UNSIGNED NOT NULL,
  `id_number` int(11) NOT NULL,
  `reservation_date` datetime NOT NULL DEFAULT current_timestamp(),
  `reservation_status` enum('Pending','Available','Fulfilled','Expired','Cancelled') NOT NULL DEFAULT 'Pending',
  `notified_date` datetime DEFAULT NULL,
  `pickup_expiry_date` datetime DEFAULT NULL,
  `book_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservation_requests`
--

INSERT INTO `reservation_requests` (`reservation_id`, `id_number`, `reservation_date`, `reservation_status`, `notified_date`, `pickup_expiry_date`, `book_id`) VALUES
(2, 2201004, '2025-10-31 14:57:18', 'Fulfilled', '2025-10-31 14:57:26', '2025-11-02 14:57:26', 5),
(3, 2201004, '2025-10-31 14:59:13', 'Fulfilled', '2025-10-31 14:59:38', '2025-11-02 14:59:38', 5),
(4, 2201004, '2025-10-31 15:06:32', 'Expired', '2025-10-31 15:06:41', '2025-11-02 15:06:41', 5),
(5, 123456, '2025-10-31 16:06:18', 'Expired', '2025-10-31 16:07:04', '2025-11-02 16:07:04', 5),
(6, 2201004, '2025-10-31 16:06:53', 'Expired', '2025-10-31 16:07:16', '2025-11-02 16:07:16', 5),
(7, 123456, '2025-10-31 16:11:55', 'Fulfilled', '2025-10-31 16:12:30', '2025-11-02 16:12:30', 5),
(8, 2201004, '2025-10-31 16:12:17', 'Fulfilled', '2025-10-31 16:13:05', '2025-11-02 16:13:05', 5),
(9, 123456, '2025-10-31 16:18:47', 'Fulfilled', '2025-10-31 16:19:31', '2025-11-02 16:19:31', 5),
(10, 2201004, '2025-10-31 16:19:02', 'Fulfilled', '2025-10-31 16:20:04', '2025-11-02 16:20:04', 5);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id_number` int(11) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `program_and_year` varchar(100) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('student','librarian','admin') NOT NULL DEFAULT 'student',
  `status` varchar(50) NOT NULL DEFAULT 'Active',
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id_number`, `fullname`, `program_and_year`, `email`, `password`, `user_id`, `role`, `status`, `otp_code`, `otp_expiry`) VALUES
(999999, 'Admin User', NULL, 'lorenzfeliciano12@gmail.com', '1', 2, 'librarian', 'Active', NULL, NULL),
(2201004, 'Van ryan samiano', 'IT-47', 'lorenzfeliciano303@gmail.com', '1', 4, 'student', 'Active', NULL, NULL),
(123456, 'Lorenz Feliciano', 'IT-4', 'soonecamz@gmail.com', 'asd', 5, 'librarian', 'Active', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_favorites`
--

CREATE TABLE `user_favorites` (
  `favorite_id` int(11) NOT NULL,
  `id_number` int(11) NOT NULL,
  `book_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_favorites`
--

INSERT INTO `user_favorites` (`favorite_id`, `id_number`, `book_id`) VALUES
(21, 2201004, 6);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`book_id`);

--
-- Indexes for table `borrow_requests`
--
ALTER TABLE `borrow_requests`
  ADD PRIMARY KEY (`borrow_id`),
  ADD KEY `fk_borrow_user_id` (`id_number`),
  ADD KEY `fk_borrow_book_id` (`book_id`);

--
-- Indexes for table `notification`
--
ALTER TABLE `notification`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `fk_user_notification` (`user_id`);

--
-- Indexes for table `reservation_requests`
--
ALTER TABLE `reservation_requests`
  ADD PRIMARY KEY (`reservation_id`),
  ADD KEY `fk_reservation_user` (`id_number`),
  ADD KEY `fk_book_reservation` (`book_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `id_number` (`id_number`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_favorites`
--
ALTER TABLE `user_favorites`
  ADD PRIMARY KEY (`favorite_id`),
  ADD UNIQUE KEY `user_book_favorite` (`id_number`,`book_id`),
  ADD KEY `fk_favorite_user` (`id_number`),
  ADD KEY `fk_favorite_book` (`book_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `book_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `borrow_requests`
--
ALTER TABLE `borrow_requests`
  MODIFY `borrow_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `notification`
--
ALTER TABLE `notification`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservation_requests`
--
ALTER TABLE `reservation_requests`
  MODIFY `reservation_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_favorites`
--
ALTER TABLE `user_favorites`
  MODIFY `favorite_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `borrow_requests`
--
ALTER TABLE `borrow_requests`
  ADD CONSTRAINT `fk_borrow_book_id` FOREIGN KEY (`book_id`) REFERENCES `books` (`book_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_borrow_user_id` FOREIGN KEY (`id_number`) REFERENCES `users` (`id_number`) ON DELETE CASCADE;

--
-- Constraints for table `notification`
--
ALTER TABLE `notification`
  ADD CONSTRAINT `fk_user_notification` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `reservation_requests`
--
ALTER TABLE `reservation_requests`
  ADD CONSTRAINT `fk_book_reservation` FOREIGN KEY (`book_id`) REFERENCES `books` (`book_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reservation_user` FOREIGN KEY (`id_number`) REFERENCES `users` (`id_number`) ON DELETE CASCADE;

--
-- Constraints for table `user_favorites`
--
ALTER TABLE `user_favorites`
  ADD CONSTRAINT `fk_favorite_book` FOREIGN KEY (`book_id`) REFERENCES `books` (`book_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_favorite_user` FOREIGN KEY (`id_number`) REFERENCES `users` (`id_number`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
