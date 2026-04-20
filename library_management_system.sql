-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 20, 2026 at 09:31 AM
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
-- Database: `library_management_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `genre` varchar(100) DEFAULT NULL,
  `year` int(4) DEFAULT NULL,
  `isbn` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`id`, `title`, `author`, `genre`, `year`, `isbn`, `description`, `created_at`) VALUES
(1, 'The Lord of the Rings', 'J.R.R. Tolkien', 'Fantasy', 1954, '978-0-618-34399-7', 'An epic fantasy novel that follows the hobbit Frodo Baggins on a quest to destroy the One Ring.', '2026-04-01 10:59:16'),
(2, '1984', 'George Orwell', 'Fiction', 1949, '978-0-452-28423-4', 'A dystopian social science fiction novel set in a totalitarian society ruled by Big Brother.', '2026-04-01 10:59:16'),
(3, 'The Design of Everyday Things', 'Don Norman', 'Design', 2013, '978-0-465-05065-9', 'A powerful primer on how and why some products satisfy customers while others frustrate them.', '2026-04-01 10:59:16'),
(4, 'Strategic Writing for UX', 'Torrey Podmajersky', 'Design', 2019, '978-1-492-05290-7', 'Drive engagement, conversion, and retention with every word.', '2026-04-01 10:59:16'),
(5, 'Web Design: Evolution', 'Multiple Authors', 'Technology', 2020, '978-1-234-56789-0', 'A comprehensive look at how web design has evolved from the early internet to today.', '2026-04-01 10:59:16'),
(6, 'Steve Jobs', 'Walter Isaacson', 'Biography', 2011, '978-1-451-64853-9', 'The exclusive biography of Apple co-founder Steve Jobs.', '2026-04-01 10:59:16'),
(7, 'The Hobbit', 'J.R.R. Tolkien', 'Fantasy', 1937, '978-0-261-10221-7', 'A fantasy novel about the adventures of Bilbo Baggins in Middle-earth.', '2026-04-01 10:59:16'),
(8, '101 Amazing Switzerland', 'Various', 'Non-Fiction', 2018, '978-0-000-00001-1', 'Discover 101 incredible things to see and do in beautiful Switzerland.', '2026-04-01 10:59:16'),
(9, 'Logo Design Love', 'David Airey', 'Design', 2010, '978-0-321-98544-5', 'A guide to creating iconic brand identities for designers.', '2026-04-01 10:59:16'),
(10, 'One Year on a Bike', 'Martijn Doolaard', 'Non-Fiction', 2020, '978-3-96704-003-5', 'A stunning visual journey across Eurasia from Amsterdam to Southeast Asia.', '2026-04-01 10:59:16'),
(11, 'Sapiens', 'Yuval Noah Harari', 'History', 2011, '978-0-099-59008-8', 'A brief history of humankind that challenges everything we know about being human.', '2026-04-01 10:59:16'),
(12, 'Atomic Habits', 'James Clear', 'Self-Help', 2018, '978-0-593-18999-0', 'An easy and proven way to build good habits and break bad ones.', '2026-04-01 10:59:16'),
(13, 'Crazy', 'SameerD', 'Fiction', 2026, '67', 'Hello', '2026-04-01 11:06:21'),
(14, 'GroupMeeting', 'Tezus', 'Science', 2026, '66666', 'hello', '2026-04-01 15:35:50'),
(15, 'Book of Five RIngs', 'Mushashi', 'History', 1880, '8888888', '', '2026-04-08 18:16:56');

-- --------------------------------------------------------

--
-- Table structure for table `book_returns`
--

CREATE TABLE `book_returns` (
  `id` int(11) NOT NULL,
  `borrowing_id` int(11) DEFAULT NULL COMMENT 'FK to borrowings.id',
  `user_id` int(11) NOT NULL COMMENT 'FK to users.id',
  `book_id` int(11) NOT NULL COMMENT 'FK to books.id',
  `condition_status` varchar(20) NOT NULL COMMENT 'excellent | good | fair | bad | damaged',
  `description` text DEFAULT NULL COMMENT 'Required when condition is bad or damaged',
  `returned_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `book_returns`
--

INSERT INTO `book_returns` (`id`, `borrowing_id`, `user_id`, `book_id`, `condition_status`, `description`, `returned_at`) VALUES
(1, 7, 2, 11, 'excellent', '', '2026-04-09 17:50:33'),
(2, 9, 2, 1, 'excellent', 'The book was lovely, enjoyed reading it.', '2026-04-09 18:09:42'),
(3, 10, 2, 2, 'good', '', '2026-04-09 21:09:42');

-- --------------------------------------------------------

--
-- Table structure for table `borrowings`
--

CREATE TABLE `borrowings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `borrow_date` date NOT NULL DEFAULT curdate(),
  `due_date` date DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `status` varchar(20) DEFAULT 'borrowed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrowings`
--

INSERT INTO `borrowings` (`id`, `user_id`, `book_id`, `borrow_date`, `due_date`, `return_date`, `status`) VALUES
(1, 2, 1, '2026-04-02', NULL, '2026-04-02', 'returned'),
(2, 6, 10, '2026-04-02', NULL, '2026-04-02', 'returned'),
(3, 1, 14, '2026-04-02', NULL, '2026-04-02', 'returned'),
(4, 1, 13, '2026-04-02', NULL, '2026-04-02', 'returned'),
(5, 2, 2, '2026-04-02', NULL, '2026-04-08', 'returned'),
(6, 6, 8, '2026-04-08', '2026-04-11', '2026-04-09', 'returned'),
(7, 2, 11, '2026-04-08', '2026-04-14', '2026-04-09', 'returned'),
(8, 6, 1, '2026-04-09', '2026-04-15', '2026-04-09', 'returned'),
(9, 2, 1, '2026-04-09', '2026-04-10', '2026-04-09', 'returned'),
(10, 2, 2, '2026-04-09', '2026-04-14', '2026-04-09', 'returned'),
(11, 6, 1, '2026-04-10', '2026-04-18', NULL, 'borrowed'),
(12, 1, 3, '2026-04-11', '2026-04-18', NULL, 'borrowed'),
(13, 2, 13, '2026-04-11', '2026-04-20', NULL, 'borrowed');

-- --------------------------------------------------------

--
-- Table structure for table `borrow_extensions`
--

CREATE TABLE `borrow_extensions` (
  `id` int(11) NOT NULL,
  `borrowing_id` int(11) NOT NULL COMMENT 'FK to borrowings.id',
  `user_id` int(11) NOT NULL COMMENT 'FK to users.id',
  `book_id` int(11) NOT NULL COMMENT 'FK to books.id',
  `extend_days` int(2) NOT NULL COMMENT 'Number of extra days requested (max 7)',
  `reason` text NOT NULL COMMENT 'Why the user wants to extend',
  `old_due_date` date NOT NULL COMMENT 'The due date before extension',
  `new_due_date` date NOT NULL COMMENT 'The new due date after extension',
  `requested_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrow_extensions`
--

INSERT INTO `borrow_extensions` (`id`, `borrowing_id`, `user_id`, `book_id`, `extend_days`, `reason`, `old_due_date`, `new_due_date`, `requested_at`) VALUES
(1, 11, 6, 1, 4, 'I really loved reading this book so i wanted to extend my time period, Sorry for the trouble it has caused.', '2026-04-14', '2026-04-18', '2026-04-10 18:36:39'),
(2, 12, 1, 3, 5, 'ahjsgfahjflekjkhliuagfhn', '2026-04-13', '2026-04-18', '2026-04-11 20:45:14'),
(3, 13, 2, 13, 7, 'asadfgsdgegs', '2026-04-13', '2026-04-20', '2026-04-11 20:47:14');

-- --------------------------------------------------------

--
-- Table structure for table `borrow_requests`
--

CREATE TABLE `borrow_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `borrow_days` int(2) NOT NULL DEFAULT 1,
  `note` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrow_requests`
--

INSERT INTO `borrow_requests` (`id`, `user_id`, `book_id`, `username`, `borrow_days`, `note`, `status`, `requested_at`, `processed_at`) VALUES
(1, 2, 11, 'sam', 6, '', 'approved', '2026-04-08 19:21:03', '2026-04-08 23:54:03'),
(2, 6, 8, 'tezu', 3, 'I wanted to borrow this book.', 'approved', '2026-04-08 19:28:12', '2026-04-08 23:53:56'),
(3, 6, 1, 'tezu', 6, 'jfkjf', 'approved', '2026-04-09 14:12:56', '2026-04-09 14:13:17'),
(4, 2, 1, 'sameer', 1, 'I wanted to borrow this book', 'approved', '2026-04-09 18:08:22', '2026-04-09 18:08:41'),
(5, 2, 2, 'sameer', 5, '', 'approved', '2026-04-09 21:06:42', '2026-04-09 21:06:56'),
(6, 6, 1, 'tezu', 4, 'i want to borrow this book', 'approved', '2026-04-10 11:01:52', '2026-04-10 11:28:39'),
(7, 1, 3, 'gritika', 2, 'hello', 'approved', '2026-04-11 20:43:45', '2026-04-11 20:44:11'),
(8, 2, 13, 'sameer', 2, 'afbaijenfa', 'approved', '2026-04-11 20:46:20', '2026-04-11 20:46:35');

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `book_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`id`, `book_id`, `user_id`, `comment`, `created_at`) VALUES
(1, 2, 2, 'Great Book', '2026-04-01 15:13:10'),
(2, 6, 2, 'Great Book!!!!', '2026-04-01 15:30:18');

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `favorites`
--

INSERT INTO `favorites` (`id`, `user_id`, `book_id`) VALUES
(5, 1, 2),
(2, 2, 4),
(6, 2, 6);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `username`, `password`, `role`) VALUES
(1, 'Gritika', 'Shrestha', 'gritika@gmail.com', 'gritika', '$2y$10$lGL6XIPun/ISjVOSJhD.x.Z10yZWX9pr2FmyZOuxXLpdGUgtK227S', 'user'),
(2, 'Sameer', 'Dahal', 'sam@gmail.com', 'sameer', '$2y$10$jbUGmRpFXTTC/E3SGSXN/e9eNrlE3aYfnTKIhxN7fyojVMy5uhq4O', 'user'),
(3, 'Tezus', 'P', 'tezus@gmail.com', 'tezus', '$2y$10$FB2chuU8lklQCtHUWsencOrMzZjpkoXlf5slGIgoFV9T6RgaDn4Pq', 'user'),
(4, 'Deepika', 'D', 'deepika@gmail.com', 'deepika', '$2y$10$YOOcM.aoNhBO.Pfx28pQ/uxxw3wX1U7SmEW1GYLlwcyRVUogMI0q2', 'user'),
(5, 'Deepika', 'Shrestha', 'dipika@gmail.com', 'deepika12', '$2y$10$EZEorghNReLHARhyoDTYy./pEdmtnbPCLKiX40jDAIAHqD7q1yl.a', 'user'),
(6, 'Tezu', 'P', 'tezu@gmail.com', 'tezu', '$2y$10$qn4142bnmI2wjL0ShwOKAuFVN.W5vgGUcstzSovFQANcL9GucMjqC', 'user'),
(7, 'Samujwal', 'Shrestha', 'samujwal@gmail.com', 'samujwal', '$2y$10$zh9tD3dMF68YWeYVVRZ3wekQJt/X3nD6q4F4NXt/eBStI/VZ2PnXS', 'user'),
(8, 'Fury', 'Parker', 'fury@gmail.com', 'fury', '$2y$10$OMAVMItBfyckxuvsgY.tTetcPMnvFXIl/jRg0tGw4KbQFd61fiG2W', 'user'),
(9, 'Shuvam', 'Jha', 'shuvam@gmail.com', 'shuvamj', '$2y$10$Oy9PJxpCOpL49xQSGhDCpuIyaAv9EAGLQ.d0rjX7RP.uA6jC7Waz2', 'user');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `book_returns`
--
ALTER TABLE `book_returns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `borrowing_id` (`borrowing_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `borrowings`
--
ALTER TABLE `borrowings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `borrow_extensions`
--
ALTER TABLE `borrow_extensions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `borrowing_id` (`borrowing_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `borrow_requests`
--
ALTER TABLE `borrow_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `book_id` (`book_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_fav` (`user_id`,`book_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `book_returns`
--
ALTER TABLE `book_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `borrowings`
--
ALTER TABLE `borrowings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `borrow_extensions`
--
ALTER TABLE `borrow_extensions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `borrow_requests`
--
ALTER TABLE `borrow_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `book_returns`
--
ALTER TABLE `book_returns`
  ADD CONSTRAINT `book_returns_ibfk_1` FOREIGN KEY (`borrowing_id`) REFERENCES `borrowings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `book_returns_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `book_returns_ibfk_3` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `borrowings`
--
ALTER TABLE `borrowings`
  ADD CONSTRAINT `borrowings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `borrowings_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`);

--
-- Constraints for table `borrow_extensions`
--
ALTER TABLE `borrow_extensions`
  ADD CONSTRAINT `borrow_extensions_ibfk_1` FOREIGN KEY (`borrowing_id`) REFERENCES `borrowings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `borrow_extensions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `borrow_extensions_ibfk_3` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `borrow_requests`
--
ALTER TABLE `borrow_requests`
  ADD CONSTRAINT `borrow_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `borrow_requests_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`),
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
