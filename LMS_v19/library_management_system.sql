-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 17, 2026 at 03:37 PM
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
-- Table structure for table `admin_balance`
--

CREATE TABLE `admin_balance` (
  `id` int(11) NOT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_balance`
--

INSERT INTO `admin_balance` (`id`, `total`, `updated_at`) VALUES
(1, 23960.00, '2026-05-17 19:02:38');

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
  `color` varchar(20) NOT NULL DEFAULT 'color-1',
  `price` decimal(8,2) NOT NULL DEFAULT 1500.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`id`, `title`, `author`, `genre`, `year`, `isbn`, `description`, `color`, `price`, `created_at`) VALUES
(1, 'The Lord of the Rings', 'J.R.R. Tolkien', 'Fantasy', 1954, '978-0-618-34399-7', 'An epic fantasy novel that follows the hobbit Frodo Baggins on a quest to destroy the One Ring.', 'color-1', 1500.00, '2026-04-01 10:59:16'),
(2, '1984', 'George Orwell', 'Fiction', 1949, '978-0-452-28423-4', 'A dystopian social science fiction novel set in a totalitarian society ruled by Big Brother.', 'color-2', 1500.00, '2026-04-01 10:59:16'),
(3, 'The Design of Everyday Things', 'Don Norman', 'Design', 2013, '978-0-465-05065-9', 'A powerful primer on how and why some products satisfy customers while others frustrate them.', 'color-3', 1500.00, '2026-04-01 10:59:16'),
(4, 'Strategic Writing for UX', 'Torrey Podmajersky', 'Design', 2019, '978-1-492-05290-7', 'Drive engagement, conversion, and retention with every word.', 'color-4', 1500.00, '2026-04-01 10:59:16'),
(5, 'Web Design: Evolution', 'Multiple Authors', 'Technology', 2020, '978-1-234-56789-0', 'A comprehensive look at how web design has evolved from the early internet to today.', 'color-5', 1500.00, '2026-04-01 10:59:16'),
(6, 'Steve Jobs', 'Walter Isaacson', 'Biography', 2011, '978-1-451-64853-9', 'The exclusive biography of Apple co-founder Steve Jobs.', 'color-6', 1500.00, '2026-04-01 10:59:16'),
(7, 'The Hobbit', 'J.R.R. Tolkien', 'Fantasy', 1937, '978-0-261-10221-7', 'A fantasy novel about the adventures of Bilbo Baggins in Middle-earth.', 'color-7', 1500.00, '2026-04-01 10:59:16'),
(8, '101 Amazing Switzerland', 'Various', 'Non-Fiction', 2018, '978-0-000-00001-1', 'Discover 101 incredible things to see and do in beautiful Switzerland.', 'color-8', 1500.00, '2026-04-01 10:59:16'),
(9, 'Logo Design Love', 'David Airey', 'Design', 2010, '978-0-321-98544-5', 'A guide to creating iconic brand identities for designers.', 'color-1', 1500.00, '2026-04-01 10:59:16'),
(10, 'One Year on a Bike', 'Martijn Doolaard', 'Non-Fiction', 2020, '978-3-96704-003-5', 'A stunning visual journey across Eurasia from Amsterdam to Southeast Asia.', 'color-2', 1500.00, '2026-04-01 10:59:16'),
(11, 'Sapiens', 'Yuval Noah Harari', 'History', 2011, '978-0-099-59008-8', 'A brief history of humankind that challenges everything we know about being human.', 'color-3', 1500.00, '2026-04-01 10:59:16'),
(12, 'Atomic Habits', 'James Clear', 'Self-Help', 2018, '978-0-593-18999-0', 'An easy and proven way to build good habits and break bad ones.', 'color-4', 1500.00, '2026-04-01 10:59:16'),
(13, 'Crazy', 'SameerD', 'Fiction', 2026, '67', 'Hello', 'color-5', 1500.00, '2026-04-01 11:06:21'),
(14, 'GroupMeeting', 'Tezus', 'Science', 2026, '66666', 'hello', 'color-6', 1500.00, '2026-04-01 15:35:50'),
(15, 'Book of Five RIngs', 'Mushashi', 'History', 1880, '8888888', '', 'color-7', 1500.00, '2026-04-08 18:16:56'),
(16, 'Harry Potter and the Philosopher\'s Stone', 'J.K. Rowling', 'Fantasy', 1997, '978-0-439-70818-8', 'A young boy discovers he is a wizard and enters Hogwarts School of Witchcraft and Wizardry, where he learns of his remarkable destiny.', 'color-8', 1500.00, '2026-05-07 10:14:45'),
(17, 'A Game of Thrones', 'George R.R. Martin', 'Fantasy', 1996, '978-0-553-57340-5', 'The first book in A Song of Ice and Fire, following the power struggle for the Iron Throne of the Seven Kingdoms of Westeros.', 'color-1', 1500.00, '2026-05-07 10:14:45'),
(18, 'Dune', 'Frank Herbert', 'Fantasy', 1965, '978-0-441-17271-9', 'Set in a distant future, it follows Paul Atreides as his family takes control of the desert planet Arrakis, the universe\'s most valuable substance.', 'color-2', 1500.00, '2026-05-07 10:14:45'),
(19, 'The Name of the Wind', 'Patrick Rothfuss', 'Fantasy', 2007, '978-0-756-40407-1', 'The autobiography of the legendary Kvothe, a man known as the most notorious wizard his world has ever seen.', 'color-3', 1500.00, '2026-05-07 10:14:45'),
(20, 'The Chronicles of Narnia', 'C.S. Lewis', 'Fantasy', 1950, '978-0-064-40488-7', 'A series of seven fantasy novels set in the world of Narnia, where animals talk, magic is real, and the great lion Aslan watches over all.', 'color-4', 1500.00, '2026-05-07 10:14:45'),
(21, 'To Kill a Mockingbird', 'Harper Lee', 'Fiction', 1960, '978-0-061-93534-6', 'Young Scout Finch watches her father Atticus defend a Black man falsely accused of a crime in the American South, exploring justice and morality.', 'color-5', 1500.00, '2026-05-07 10:14:45'),
(22, 'The Great Gatsby', 'F. Scott Fitzgerald', 'Fiction', 1925, '978-0-743-27356-5', 'A portrait of the Jazz Age in all its decadence, centering on the mysterious millionaire Jay Gatsby and his obsession with Daisy Buchanan.', 'color-6', 1500.00, '2026-05-07 10:14:45'),
(23, 'Pride and Prejudice', 'Jane Austen', 'Fiction', 1813, '978-0-141-43951-8', 'The romantic story of Elizabeth Bennet and the proud Mr. Darcy, exploring love, class, and social expectations in Regency England.', 'color-7', 1500.00, '2026-05-07 10:14:45'),
(24, 'The Alchemist', 'Paulo Coelho', 'Fiction', 1988, '978-0-061-22101-4', 'A philosophical novel about a young shepherd named Santiago who travels from Spain to Egypt in search of his personal legend.', 'color-8', 1500.00, '2026-05-07 10:14:45'),
(25, 'Brave New World', 'Aldous Huxley', 'Fiction', 1932, '978-0-060-85052-4', 'A dystopian novel set in a future World State of genetically modified citizens and an intelligence-based social hierarchy.', 'color-1', 1500.00, '2026-05-07 10:14:45'),
(26, 'Animal Farm', 'George Orwell', 'Fiction', 1945, '978-0-451-52634-2', 'A satirical allegory about a group of farm animals who rebel against their farmer, hoping to create an equal society, only to find new tyrants emerge.', 'color-2', 1500.00, '2026-05-07 10:14:45'),
(27, 'The Kite Runner', 'Khaled Hosseini', 'Fiction', 2003, '978-1-594-48000-3', 'A story of friendship, betrayal, and redemption set against the backdrop of Afghanistan from the 1970s through the early 2000s.', 'color-3', 1500.00, '2026-05-07 10:14:45'),
(28, 'The Da Vinci Code', 'Dan Brown', 'Fiction', 2003, '978-0-385-50420-5', 'Symbologist Robert Langdon investigates a murder in the Louvre Museum and uncovers a trail of clues hidden in the works of Leonardo da Vinci.', 'color-4', 1500.00, '2026-05-07 10:14:45'),
(29, 'Ender\'s Game', 'Orson Scott Card', 'Science Fiction', 1985, '978-0-765-37085-2', 'A child prodigy named Ender Wiggin is trained at a space military academy to lead Earth\'s forces against an alien invasion.', 'color-5', 1500.00, '2026-05-07 10:14:45'),
(30, 'The Martian', 'Andy Weir', 'Science Fiction', 2011, '978-0-553-41802-6', 'Astronaut Mark Watney is stranded alone on Mars after a fierce storm and must use his ingenuity and humor to survive.', 'color-6', 1500.00, '2026-05-07 10:14:45'),
(31, 'Foundation', 'Isaac Asimov', 'Science Fiction', 1951, '978-0-553-29335-7', 'A group of scientists preserve the knowledge of humanity as the Galactic Empire begins to crumble around them.', 'color-7', 1500.00, '2026-05-07 10:14:45'),
(32, 'The Hitchhiker\'s Guide to the Galaxy', 'Douglas Adams', 'Science Fiction', 1979, '978-0-345-39180-3', 'The comedic adventure of Arthur Dent, the last surviving human, swept into a wildly improbable journey across the galaxy.', 'color-8', 1500.00, '2026-05-07 10:14:45'),
(33, 'Ready Player One', 'Ernest Cline', 'Science Fiction', 2011, '978-0-307-88743-6', 'In a dystopian future, teenager Wade Watts discovers clues to a massive fortune hidden inside a virtual reality game.', 'color-1', 1500.00, '2026-05-07 10:14:45'),
(34, 'The 7 Habits of Highly Effective People', 'Stephen Covey', 'Self-Help', 1989, '978-0-743-26951-3', 'A framework for personal and professional effectiveness built on seven core habits that align character and competence.', 'color-2', 1500.00, '2026-05-07 10:14:45'),
(35, 'Think and Grow Rich', 'Napoleon Hill', 'Self-Help', 1937, '978-1-585-42433-2', 'One of the best-selling self-help books ever written, distilling the philosophy of personal achievement from 500 successful people.', 'color-3', 1500.00, '2026-05-07 10:14:45'),
(36, 'How to Win Friends and Influence People', 'Dale Carnegie', 'Self-Help', 1936, '978-0-671-02703-1', 'Timeless advice on building relationships, communicating effectively, and becoming a more likable and influential person.', 'color-4', 1500.00, '2026-05-07 10:14:45'),
(37, 'Deep Work', 'Cal Newport', 'Self-Help', 2016, '978-1-455-58669-1', 'Rules for focused success in a distracted world. The ability to perform deep work is becoming rare and increasingly valuable.', 'color-5', 1500.00, '2026-05-07 10:14:45'),
(38, 'Rich Dad Poor Dad', 'Robert Kiyosaki', 'Self-Help', 1997, '978-1-612-68061-9', 'A personal finance classic contrasting two fathers\' financial philosophies, advocating for financial literacy and investing in assets.', 'color-6', 1500.00, '2026-05-07 10:14:45'),
(39, 'The Psychology of Money', 'Morgan Housel', 'Self-Help', 2020, '978-0-857-19769-9', 'Timeless lessons on wealth, greed, and happiness, exploring the strange ways people think about money.', 'color-7', 1500.00, '2026-05-07 10:14:45'),
(40, 'The Diary of a Young Girl', 'Anne Frank', 'History', 1947, '978-0-553-29698-3', 'The wartime diary of Anne Frank, a Jewish girl hiding in Amsterdam during the Nazi occupation, a poignant testament to the human spirit.', 'color-8', 1500.00, '2026-05-07 10:14:45'),
(41, 'A Brief History of Time', 'Stephen Hawking', 'Science', 1988, '978-0-553-38016-3', 'A landmark in science writing exploring the cosmos, the Big Bang, black holes, and the fundamental laws governing the universe.', 'color-1', 1500.00, '2026-05-07 10:14:45'),
(42, 'Guns, Germs, and Steel', 'Jared Diamond', 'History', 1997, '978-0-393-31755-8', 'A study of why some civilizations came to dominate others, exploring the geographic and environmental factors that shaped history.', 'color-2', 1500.00, '2026-05-07 10:14:45'),
(43, 'Homo Deus', 'Yuval Noah Harari', 'History', 2015, '978-0-062-46469-8', 'A look at the future of humanity once we surpass the limitations that have defined our species for millennia, from death to meaning.', 'color-3', 1500.00, '2026-05-07 10:14:45'),
(44, 'Becoming', 'Michelle Obama', 'Biography', 2018, '978-1-524-76313-8', 'The memoir of the former First Lady of the United States, tracing her journey from Chicago\'s South Side to the White House and beyond.', 'color-4', 1500.00, '2026-05-07 10:14:45'),
(45, 'Long Walk to Freedom', 'Nelson Mandela', 'Biography', 1994, '978-0-316-54818-3', 'The autobiography of Nelson Mandela, covering his childhood, his fight against apartheid, 27 years in prison, and his rise to presidency.', 'color-5', 1500.00, '2026-05-07 10:14:45'),
(46, 'Elon Musk', 'Walter Isaacson', 'Biography', 2023, '978-1-982-18126-1', 'An intimate biography of Elon Musk exploring the polarizing entrepreneur behind Tesla, SpaceX, and X.', 'color-6', 1500.00, '2026-05-07 10:14:45'),
(47, 'Leonardo da Vinci', 'Walter Isaacson', 'Biography', 2017, '978-1-501-12010-1', 'A biography of history\'s most creative genius, Leonardo da Vinci, drawing on 7,200 pages of his notebook entries.', 'color-7', 1500.00, '2026-05-07 10:14:45'),
(48, 'Don\'t Make Me Think', 'Steve Krug', 'Design', 2000, '978-0-321-96551-6', 'A classic guide to web usability explaining how real users interact with websites and how to design for intuitive, effortless navigation.', 'color-8', 1500.00, '2026-05-07 10:14:45'),
(49, 'Clean Code', 'Robert C. Martin', 'Technology', 2008, '978-0-132-35088-4', 'A handbook of agile software craftsmanship presenting best practices for writing clean, readable, and maintainable code.', 'color-1', 1500.00, '2026-05-07 10:14:45'),
(50, 'The Pragmatic Programmer', 'Andrew Hunt', 'Technology', 1999, '978-0-135-95705-9', 'A guide covering the core processes of software development, helping programmers become more effective and pragmatic in their craft.', 'color-2', 1500.00, '2026-05-07 10:14:45'),
(51, 'Zero to One', 'Peter Thiel', 'Business', 2014, '978-0-804-13929-8', 'Notes on startups and how to build the future. Peter Thiel challenges conventional thinking about innovation and entrepreneurship.', 'color-3', 1500.00, '2026-05-07 10:14:45'),
(52, 'Good to Great', 'Jim Collins', 'Business', 2001, '978-0-066-62099-2', 'A research-driven study of how certain companies made the leap from good to truly great, and sustained that greatness for 15+ years.', 'color-4', 1500.00, '2026-05-07 10:14:45'),
(53, 'The Lean Startup', 'Eric Ries', 'Business', 2011, '978-0-307-88789-4', 'A new approach to building companies and launching products that is changing how startups operate around the world.', 'color-5', 1500.00, '2026-05-07 10:14:45'),
(54, 'Start with Why', 'Simon Sinek', 'Business', 2009, '978-1-591-84280-8', 'Explores how leaders like Apple and Martin Luther King inspired action by starting with WHY rather than what or how.', 'color-6', 1500.00, '2026-05-07 10:14:45'),
(55, 'Thinking, Fast and Slow', 'Daniel Kahneman', 'Business', 2011, '978-0-374-27563-1', 'A groundbreaking tour through the two systems that drive the way we think, make decisions, and understand the world.', 'color-7', 1500.00, '2026-05-07 10:14:45'),
(56, 'Into the Wild', 'Jon Krakauer', 'Non-Fiction', 1996, '978-0-385-48680-4', 'The story of Chris McCandless who abandoned his possessions and hitchhiked to Alaska to live alone in the wilderness.', 'color-8', 1500.00, '2026-05-07 10:14:45'),
(57, 'The Art of War', 'Sun Tzu', 'History', 2002, '978-1-599-86997-6', 'An ancient Chinese military treatise on strategy, tactics, and the philosophy of conflict, applicable to business and everyday life.', 'color-1', 1500.00, '2026-05-07 10:14:45'),
(58, 'A Short History of Nearly Everything', 'Bill Bryson', 'Science', 2003, '978-0-767-90818-7', 'An accessible and witty tour through the history of science, covering the Big Bang, the rise of civilization, and everything in between.', 'color-2', 1500.00, '2026-05-07 10:14:45'),
(59, 'The Art of Travel', 'Alain de Botton', 'Non-Fiction', 2002, '978-0-375-72506-8', 'A philosophical meditation blending memoir with analysis on why we travel, what we seek, and what travel reveals about ourselves.', 'color-3', 1500.00, '2026-05-07 10:14:45'),
(60, 'Crime and Punishment', 'Fyodor Dostoevsky', 'Fiction', 1866, '978-0-140-44913-6', 'A psychological novel following Raskolnikov, a poverty-stricken student in St. Petersburg who commits a murder and grapples with guilt and redemption.', 'color-4', 1500.00, '2026-05-07 10:24:19'),
(61, 'The Count of Monte Cristo', 'Alexandre Dumas', 'Fiction', 1844, '978-0-140-44929-7', 'The gripping tale of Edmond Dant?s, wrongfully imprisoned and his elaborate plan of revenge against those who betrayed him.', 'color-5', 1500.00, '2026-05-07 10:24:19'),
(62, 'The Catcher in the Rye', 'J.D. Salinger', 'Fiction', 1951, '978-0-316-76948-0', 'Teenager Holden Caulfield narrates his experiences in New York City after being expelled from prep school, exploring themes of identity and alienation.', 'color-6', 1500.00, '2026-05-07 10:24:19'),
(63, 'One Hundred Years of Solitude', 'Gabriel Garcia Marquez', 'Fiction', 1967, '978-0-060-88328-7', 'The multi-generational story of the Buend?a family in the fictional town of Macondo, a landmark of magical realism.', 'color-7', 1500.00, '2026-05-07 10:24:19'),
(64, 'Fahrenheit 451', 'Ray Bradbury', 'Science Fiction', 1953, '978-0-345-34296-6', 'In a dystopian future where books are outlawed and burned, fireman Guy Montag begins to question the society he serves.', 'color-8', 1500.00, '2026-05-07 10:24:19'),
(65, 'Neuromancer', 'William Gibson', 'Science Fiction', 1984, '978-0-441-56956-4', 'The cyberpunk classic following a washed-up hacker hired for one last job that takes him deep into a world of artificial intelligence and crime.', 'color-1', 1500.00, '2026-05-07 10:24:19'),
(66, 'I, Robot', 'Isaac Asimov', 'Science Fiction', 1950, '978-0-553-29438-5', 'A collection of stories exploring the relationship between humans and robots, governed by Asimov\'s famous Three Laws of Robotics.', 'color-2', 1500.00, '2026-05-07 10:24:19'),
(67, 'The Hunger Games', 'Suzanne Collins', 'Science Fiction', 2008, '978-0-439-02352-8', 'In a dystopian future, teenager Katniss Everdeen volunteers to participate in the televised Hunger Games fight-to-the-death competition in place of her sister.', 'color-3', 1500.00, '2026-05-07 10:24:19'),
(68, 'Divergent', 'Veronica Roth', 'Science Fiction', 2011, '978-0-062-02402-2', 'In a future Chicago divided into factions, Beatrice Prior discovers she is Divergent and uncovers a conspiracy threatening her society.', 'color-4', 1500.00, '2026-05-07 10:24:19'),
(69, 'Eragon', 'Christopher Paolini', 'Fantasy', 2003, '978-0-375-82668-5', 'A young farm boy named Eragon finds a mysterious stone that hatches into a dragon, beginning an epic journey of adventure and destiny.', 'color-5', 1500.00, '2026-05-07 10:24:19'),
(70, 'Percy Jackson and the Lightning Thief', 'Rick Riordan', 'Fantasy', 2005, '978-0-786-83827-3', 'A 12-year-old boy discovers he is the son of a Greek god and embarks on a quest to prevent a war among the Olympians.', 'color-6', 1500.00, '2026-05-07 10:24:19'),
(71, 'The Power of Habit', 'Charles Duhigg', 'Self-Help', 2012, '978-1-400-06928-6', 'Explains the science behind why habits exist and how they can be changed, drawing on research in neuroscience and psychology.', 'color-7', 1500.00, '2026-05-07 10:24:19'),
(72, 'Outliers', 'Malcolm Gladwell', 'Non-Fiction', 2008, '978-0-316-01792-3', 'Examines the factors that contribute to high levels of success, exploring the stories of outliers and what makes them extraordinary.', 'color-8', 1500.00, '2026-05-07 10:24:19'),
(73, 'The Tipping Point', 'Malcolm Gladwell', 'Non-Fiction', 2000, '978-0-316-34662-7', 'Explores how little things can make a big difference, examining the moment when ideas, trends, and social behaviours cross a threshold and spread like wildfire.', 'color-1', 1500.00, '2026-05-07 10:24:19'),
(74, 'Blink', 'Malcolm Gladwell', 'Non-Fiction', 2005, '978-0-316-17232-5', 'Investigates the power of thinking without thinking, exploring how snap judgements and first impressions can be more powerful than careful deliberation.', 'color-2', 1500.00, '2026-05-07 10:24:19'),
(75, 'Ikigai', 'Hector Garcia', 'Self-Help', 2016, '978-0-143-13021-3', 'The Japanese concept of ikigai ? a reason for being ? explored through the lives of the world\'s longest-living people on the island of Okinawa.', 'color-3', 1500.00, '2026-05-07 10:24:19'),
(76, 'The Selfish Gene', 'Richard Dawkins', 'Science', 1976, '978-0-198-86093-7', 'A landmark work in evolutionary biology arguing that genes, not individuals or species, are the primary unit of natural selection.', 'color-4', 1500.00, '2026-05-07 10:24:19'),
(77, 'Cosmos', 'Carl Sagan', 'Science', 1980, '978-0-345-53943-4', 'A sweeping exploration of the universe and humanity\'s place in it, blending science, philosophy, and wonder in Carl Sagan\'s signature style.', 'color-5', 1500.00, '2026-05-07 10:24:19'),
(78, 'Freakonomics', 'Steven Levitt', 'Non-Fiction', 2005, '978-0-060-73132-5', 'A rogue economist explores the hidden side of everything, applying economic thinking to topics ranging from drug dealing to parenting.', 'color-6', 1500.00, '2026-05-07 10:24:19'),
(79, 'I Am Malala', 'Malala Yousafzai', 'Biography', 2013, '978-0-316-32241-3', 'The remarkable story of a young Pakistani girl who defied the Taliban to advocate for girls\' education and survived an assassination attempt.', 'color-7', 1500.00, '2026-05-07 10:24:19'),
(80, 'Open', 'Andre Agassi', 'Biography', 2009, '978-0-307-26817-8', 'The candid autobiography of tennis legend Andre Agassi, revealing his love-hate relationship with tennis and his extraordinary journey to redemption.', 'color-8', 1500.00, '2026-05-07 10:24:19'),
(81, 'Me Before You', 'Jojo Moyes', 'Fiction', 2012, '978-0-143-12454-0', 'A heartwarming and emotional love story about a small-town girl and a recently paralyzed man who opens her world in unexpected ways.', 'color-1', 1500.00, '2026-05-07 10:24:19'),
(82, 'The Notebook', 'Nicholas Sparks', 'Fiction', 1996, '978-0-446-60523-5', 'A timeless love story about two young people from different backgrounds who fall deeply in love during one magical summer in the 1940s.', 'color-2', 1500.00, '2026-05-07 10:24:19'),
(83, 'The Subtle Art of Not Giving a F*ck', 'Mark Manson', 'Self-Help', 2016, '978-0-062-45771-3', 'A counterintuitive approach to living a good life, arguing that improving our lives hinges on choosing what to give a f*ck about.', 'color-3', 1500.00, '2026-05-07 10:24:19'),
(84, 'Man Search for Meaning', 'Viktor Frankl', 'Non-Fiction', 1946, '978-0-807-01427-1', 'A Holocaust survivor and psychiatrist describes his psychotherapeutic method of finding meaning in all forms of existence, even the most brutal ones.', 'color-4', 1500.00, '2026-05-07 10:24:19'),
(85, 'To Kill a Mockingbird', 'Harper Lee', 'Fiction', 1960, '978-0061935466', 'A gripping tale of racial injustice and moral growth in the American South, seen through the eyes of young Scout Finch.', 'color-5', 1500.00, '2026-05-08 12:28:41'),
(86, 'The Great Gatsby', 'F. Scott Fitzgerald', 'Fiction', 1925, '978-0743273565', 'A portrait of the Jazz Age and the American Dream, following the mysterious millionaire Jay Gatsby and his obsession with Daisy Buchanan.', 'color-6', 1500.00, '2026-05-08 12:28:41'),
(87, 'Pride and Prejudice', 'Jane Austen', 'Fiction', 1813, '978-0141439518', 'A witty and romantic novel following Elizabeth Bennet as she navigates love, class, and the insufferable Mr. Darcy.', 'color-7', 1500.00, '2026-05-08 12:28:41'),
(88, 'The Catcher in the Rye', 'J.D. Salinger', 'Fiction', 1951, '978-0316769174', 'Teenager Holden Caulfield wanders New York City after being expelled from prep school in this classic coming-of-age story.', 'color-8', 1500.00, '2026-05-08 12:28:41'),
(89, 'Brave New World', 'Aldous Huxley', 'Fiction', 1932, '978-0060850524', 'A dystopian vision of a future society built on pleasure, conditioning, and the elimination of individuality.', 'color-1', 1500.00, '2026-05-08 12:28:41'),
(90, 'One Hundred Years of Solitude', 'Gabriel García Márquez', 'Fiction', 1967, '978-0060883287', 'The Buendía family saga spanning seven generations in the fictional town of Macondo, blending reality and magic.', 'color-2', 1500.00, '2026-05-08 12:28:41'),
(91, 'The Alchemist', 'Paulo Coelho', 'Fiction', 1988, '978-0062315007', 'A young shepherd travels from Spain to Egypt in search of treasure, discovering the meaning of his personal legend.', 'color-3', 1500.00, '2026-05-08 12:28:41'),
(92, 'Harry Potter and the Philosopher\'s Stone', 'J.K. Rowling', 'Fantasy', 1997, '978-0439708180', 'An orphan boy discovers he is a wizard and begins his education at Hogwarts School of Witchcraft and Wizardry.', 'color-4', 1500.00, '2026-05-08 12:28:41'),
(93, 'The Hobbit', 'J.R.R. Tolkien', 'Fantasy', 1937, '978-0547928227', 'Bilbo Baggins, a comfort-loving hobbit, is swept into an epic quest to reclaim a mountain treasure guarded by a dragon.', 'color-5', 1500.00, '2026-05-08 12:28:41'),
(94, 'A Game of Thrones', 'George R.R. Martin', 'Fantasy', 1996, '978-0553573404', 'Noble families fight for control of the Iron Throne while an ancient enemy awakens beyond the kingdom\'s northern border.', 'color-6', 1500.00, '2026-05-08 12:28:41'),
(95, 'The Name of the Wind', 'Patrick Rothfuss', 'Fantasy', 2007, '978-0756404741', 'The legendary wizard Kvothe recounts his extraordinary life story from childhood prodigy to the most notorious man alive.', 'color-7', 1500.00, '2026-05-08 12:28:41'),
(96, 'And Then There Were None', 'Agatha Christie', 'Mystery', 1939, '978-0062073488', 'Ten strangers are lured to an isolated island and begin dying one by one in this ingenious and chilling mystery.', 'color-8', 1500.00, '2026-05-08 12:28:41'),
(97, 'Gone Girl', 'Gillian Flynn', 'Mystery', 2012, '978-0307588371', 'On their fifth wedding anniversary, Amy Dunne disappears and her husband Nick becomes the prime suspect.', 'color-1', 1500.00, '2026-05-08 12:28:41'),
(98, 'The Girl with the Dragon Tattoo', 'Stieg Larsson', 'Mystery', 2005, '978-0307949486', 'A disgraced journalist and a brilliant hacker investigate a decades-old disappearance within a powerful Swedish family.', 'color-2', 1500.00, '2026-05-08 12:28:41'),
(99, 'The Da Vinci Code', 'Dan Brown', 'Mystery', 2003, '978-0307474278', 'Harvard symbologist Robert Langdon unravels a conspiracy hidden within the works of Leonardo Da Vinci.', 'color-3', 1500.00, '2026-05-08 12:28:41'),
(100, 'A Brief History of Time', 'Stephen Hawking', 'Science', 1988, '978-0553380163', 'Hawking explores the nature of space, time, black holes, and the origins of the universe in clear, accessible prose.', 'color-4', 1500.00, '2026-05-08 12:28:41'),
(101, 'The Selfish Gene', 'Richard Dawkins', 'Science', 1976, '978-0198788607', 'A revolutionary view of evolution that places the gene, not the organism, at the center of natural selection.', 'color-5', 1500.00, '2026-05-08 12:28:41'),
(102, 'Cosmos', 'Carl Sagan', 'Science', 1980, '978-0345539434', 'A sweeping exploration of the universe and humanity\'s place in it, blending science, philosophy, and wonder.', 'color-6', 1500.00, '2026-05-08 12:28:41'),
(103, 'Surely You\'re Joking, Mr. Feynman!', 'Richard P. Feynman', 'Science', 1985, '978-0393316049', 'Hilarious and insightful adventures of Nobel Prize-winning physicist Richard Feynman, from cracking safes to playing bongo drums.', 'color-7', 1500.00, '2026-05-08 12:28:41'),
(104, 'Sapiens: A Brief History of Humankind', 'Yuval Noah Harari', 'Non-Fiction', 2011, '978-0062316097', 'A sweeping narrative of humanity\'s history from the Stone Age to the 21st century, examining how Homo sapiens came to dominate Earth.', 'color-8', 1500.00, '2026-05-08 12:28:41'),
(105, 'Thinking, Fast and Slow', 'Daniel Kahneman', 'Non-Fiction', 2011, '978-0374533557', 'Nobel laureate Kahneman explores the two systems that drive the way we think and how they shape our judgments and decisions.', 'color-1', 1500.00, '2026-05-08 12:28:41'),
(106, 'Guns, Germs, and Steel', 'Jared Diamond', 'Non-Fiction', 1997, '978-0393317558', 'An examination of why Western civilizations came to dominate the world through geography, biology, and technology.', 'color-2', 1500.00, '2026-05-08 12:28:41'),
(107, 'Atomic Habits', 'James Clear', 'Self-Help', 2018, '978-0735211292', 'A practical guide to building good habits and breaking bad ones through small, incremental changes that compound over time.', 'color-3', 1500.00, '2026-05-08 12:28:41'),
(108, 'The 7 Habits of Highly Effective People', 'Stephen R. Covey', 'Self-Help', 1989, '978-0743269513', 'A principle-centered approach to personal and professional effectiveness that has transformed millions of lives.', 'color-4', 1500.00, '2026-05-08 12:28:41'),
(109, 'How to Win Friends and Influence People', 'Dale Carnegie', 'Self-Help', 1936, '978-0671027032', 'Timeless advice on communication, leadership, and human relations that remains as relevant today as when first published.', 'color-5', 1500.00, '2026-05-08 12:28:41'),
(110, 'Deep Work', 'Cal Newport', 'Self-Help', 2016, '978-1455586691', 'The case for cultivating the ability to focus without distraction and the rules for transforming your working life.', 'color-6', 1500.00, '2026-05-08 12:28:41'),
(111, 'Steve Jobs', 'Walter Isaacson', 'Biography', 2011, '978-1451648539', 'The exclusive biography of Apple\'s visionary co-founder, drawing on over forty interviews with Jobs himself.', 'color-7', 1500.00, '2026-05-08 12:28:41'),
(112, 'The Diary of a Young Girl', 'Anne Frank', 'Biography', 1947, '978-0553577129', 'The remarkable diary kept by a Jewish girl hiding from the Nazis in Amsterdam during World War II.', 'color-8', 1500.00, '2026-05-08 12:28:41'),
(113, 'Long Walk to Freedom', 'Nelson Mandela', 'Biography', 1994, '978-0316548182', 'Nelson Mandela\'s autobiography recounting his childhood, his years in prison, and his path to the presidency of South Africa.', 'color-1', 1500.00, '2026-05-08 12:28:41'),
(114, 'The Silk Roads', 'Peter Frankopan', 'History', 2015, '978-1101912379', 'A bold new history of the world that places the routes between East and West at the center of global events.', 'color-2', 1500.00, '2026-05-08 12:28:41'),
(115, 'The Pragmatic Programmer', 'David Thomas', 'Technology', 1999, '978-0135957059', 'Essential wisdom for software developers on building better software, from career advice to specific coding practices.', 'color-3', 1500.00, '2026-05-08 12:28:41'),
(116, 'Mero Bhai', 'Dai', 'Self-Help', 2026, '676767', '', 'color-4', 1500.00, '2026-05-17 13:29:25'),
(117, 'Kanxa', 'Bhai', 'Non-Fiction', 2026, '69696969', '', 'color-2', 1500.00, '2026-05-17 13:30:54');

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
(3, 10, 2, 2, 'good', '', '2026-04-09 21:09:42'),
(4, 11, 6, 1, 'good', '', '2026-04-27 17:06:09'),
(5, 13, 2, 13, 'damaged', 'sorry for damaging the book.', '2026-04-27 17:08:08'),
(6, 14, 2, 1, 'excellent', '', '2026-04-28 21:13:24'),
(7, 15, 2, 13, 'fair', 'sjabjf', '2026-04-28 21:13:39'),
(8, 17, 2, 2, 'damaged', 'ashfbahubdfa', '2026-04-28 21:14:33'),
(9, 22, 2, 1, 'good', '', '2026-05-07 20:06:16'),
(10, 20, 2, 4, 'excellent', '', '2026-05-07 20:07:25'),
(11, 16, 2, 15, 'damaged', 'The book got wet in the process', '2026-05-07 20:07:57'),
(12, 21, 2, 22, 'damaged', 'sorry for the damage', '2026-05-07 20:08:28'),
(13, 19, 2, 13, 'excellent', '', '2026-05-07 20:15:35'),
(14, 18, 2, 11, 'good', NULL, '2026-05-07 20:52:18'),
(15, 23, 2, 34, 'lost', 'Marked as lost by admin', '2026-05-07 21:20:51'),
(16, 25, 12, 2, 'lost', 'Marked as lost by admin', '2026-05-08 10:53:30'),
(17, 24, 2, 1, 'fair', NULL, '2026-05-17 09:46:08');

-- --------------------------------------------------------

--
-- Table structure for table `book_reviews`
--

CREATE TABLE `book_reviews` (
  `id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `review` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `book_reviews`
--

INSERT INTO `book_reviews` (`id`, `book_id`, `user_id`, `rating`, `review`, `created_at`) VALUES
(1, 13, 2, 4, 'It was a great book', '2026-05-07 20:15:49'),
(2, 11, 2, 5, '', '2026-05-07 20:52:23'),
(3, 1, 2, 4, '', '2026-05-17 09:46:14');

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
  `status` varchar(20) DEFAULT 'borrowed',
  `lost_reported` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrowings`
--

INSERT INTO `borrowings` (`id`, `user_id`, `book_id`, `borrow_date`, `due_date`, `return_date`, `status`, `lost_reported`) VALUES
(1, 2, 1, '2026-04-02', NULL, '2026-04-02', 'returned', 0),
(2, 6, 10, '2026-04-02', NULL, '2026-04-02', 'returned', 0),
(3, 1, 14, '2026-04-02', NULL, '2026-04-02', 'returned', 0),
(4, 1, 13, '2026-04-02', NULL, '2026-04-02', 'returned', 0),
(5, 2, 2, '2026-04-02', NULL, '2026-04-08', 'returned', 0),
(6, 6, 8, '2026-04-08', '2026-04-11', '2026-04-09', 'returned', 0),
(7, 2, 11, '2026-04-08', '2026-04-14', '2026-04-09', 'returned', 0),
(8, 6, 1, '2026-04-09', '2026-04-15', '2026-04-09', 'returned', 0),
(9, 2, 1, '2026-04-09', '2026-04-10', '2026-04-09', 'returned', 0),
(10, 2, 2, '2026-04-09', '2026-04-14', '2026-04-09', 'returned', 0),
(11, 6, 1, '2026-04-10', '2026-04-18', '2026-04-27', 'returned', 0),
(12, 1, 3, '2026-04-11', '2026-04-18', NULL, 'borrowed', 0),
(13, 2, 13, '2026-04-11', '2026-04-20', '2026-04-27', 'returned', 0),
(14, 2, 1, '2026-04-27', '2026-05-04', '2026-04-28', 'returned', 0),
(15, 2, 13, '2026-04-28', '2026-05-04', '2026-04-28', 'returned', 0),
(16, 2, 15, '2026-04-28', '2026-05-02', '2026-05-07', 'returned', 0),
(17, 2, 2, '2026-04-28', '2026-04-29', '2026-04-28', 'returned', 0),
(18, 2, 11, '2026-05-02', '2026-05-06', '2026-05-07', 'returned', 0),
(19, 2, 13, '2026-05-02', '2026-05-05', '2026-05-07', 'returned', 0),
(20, 2, 4, '2026-05-06', '2026-05-08', '2026-05-07', 'returned', 0),
(21, 2, 22, '2026-05-07', '2026-05-10', '2026-05-07', 'returned', 0),
(22, 2, 1, '2026-05-07', '2026-05-11', '2026-05-07', 'returned', 0),
(23, 2, 34, '2026-05-07', '2026-05-10', '2026-05-07', 'lost', 1),
(24, 2, 1, '2026-05-07', '2026-05-10', '2026-05-17', 'returned', 0),
(25, 12, 2, '2026-05-08', '2026-05-10', '2026-05-08', 'lost', 1),
(26, 2, 14, '2026-05-17', '2026-05-22', NULL, 'borrowed', 0),
(27, 2, 20, '2026-05-17', '2026-05-30', NULL, 'borrowed', 0),
(28, 2, 8, '2026-05-17', '2026-05-24', NULL, 'borrowed', 0),
(29, 2, 9, '2026-05-17', '2026-05-22', NULL, 'borrowed', 0);

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
(3, 13, 2, 13, 7, 'asadfgsdgegs', '2026-04-13', '2026-04-20', '2026-04-11 20:47:14'),
(4, 15, 2, 13, 2, 'sasfae', '2026-04-29', '2026-05-01', '2026-04-28 21:10:07'),
(5, 15, 2, 13, 3, 'i kiked this book', '2026-05-01', '2026-05-04', '2026-04-28 21:10:21'),
(6, 26, 2, 14, 3, 'I loved this book so i gave a thought of extending it by 3 days', '2026-05-19', '2026-05-22', '2026-05-17 18:47:20'),
(7, 27, 2, 20, 6, 'I loved the book so i wanted to extend my borrowing time by 6 days more', '2026-05-24', '2026-05-30', '2026-05-17 18:58:10'),
(8, 28, 2, 8, 2, 'I wnated to read the book more', '2026-05-22', '2026-05-24', '2026-05-17 18:59:20'),
(9, 29, 2, 9, 1, 'i would love to have the book for 1 more day', '2026-05-21', '2026-05-22', '2026-05-17 19:02:38');

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
  `language` varchar(10) NOT NULL DEFAULT 'english' COMMENT 'Preferred book language: english | nepali',
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL,
  `seen_by_user` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=unseen by user, 1=seen'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrow_requests`
--

INSERT INTO `borrow_requests` (`id`, `user_id`, `book_id`, `username`, `borrow_days`, `note`, `language`, `status`, `requested_at`, `processed_at`, `seen_by_user`) VALUES
(1, 2, 11, 'sam', 6, '', 'english', 'approved', '2026-04-08 19:21:03', '2026-04-08 23:54:03', 0),
(2, 6, 8, 'tezu', 3, 'I wanted to borrow this book.', 'english', 'approved', '2026-04-08 19:28:12', '2026-04-08 23:53:56', 0),
(3, 6, 1, 'tezu', 6, 'jfkjf', 'english', 'approved', '2026-04-09 14:12:56', '2026-04-09 14:13:17', 0),
(4, 2, 1, 'sameer', 1, 'I wanted to borrow this book', 'english', 'approved', '2026-04-09 18:08:22', '2026-04-09 18:08:41', 0),
(5, 2, 2, 'sameer', 5, '', 'english', 'approved', '2026-04-09 21:06:42', '2026-04-09 21:06:56', 0),
(6, 6, 1, 'tezu', 4, 'i want to borrow this book', 'english', 'approved', '2026-04-10 11:01:52', '2026-04-10 11:28:39', 0),
(7, 1, 3, 'gritika', 2, 'hello', 'english', 'approved', '2026-04-11 20:43:45', '2026-04-11 20:44:11', 0),
(8, 2, 13, 'sameer', 2, 'afbaijenfa', 'english', 'approved', '2026-04-11 20:46:20', '2026-04-11 20:46:35', 0),
(9, 2, 1, 'Sameer', 7, 'I want to borrow this book.', 'english', 'approved', '2026-04-27 17:08:39', '2026-04-27 17:08:55', 0),
(10, 2, 13, 'sameer', 1, 'jagwduavhb', 'english', 'approved', '2026-04-28 21:08:10', '2026-04-28 21:09:45', 0),
(11, 2, 2, 'sameer', 1, '', 'english', 'approved', '2026-04-28 21:12:49', '2026-04-28 21:14:10', 0),
(12, 2, 15, 'sameer', 4, '', 'english', 'approved', '2026-04-28 21:13:57', '2026-04-28 21:14:08', 0),
(13, 2, 13, 'sameer', 3, '', 'english', 'approved', '2026-05-02 10:20:33', '2026-05-02 10:20:57', 0),
(14, 2, 11, 'sameer', 4, '', 'english', 'approved', '2026-05-02 10:20:43', '2026-05-02 10:20:56', 0),
(15, 2, 4, 'sameer', 2, '', 'nepali', 'approved', '2026-05-06 17:01:08', '2026-05-06 17:01:21', 0),
(16, 2, 21, 'sameer', 2, '', 'english', 'rejected', '2026-05-07 16:19:37', '2026-05-07 16:19:51', 0),
(17, 2, 22, 'sameer', 3, '', 'english', 'approved', '2026-05-07 16:20:16', '2026-05-07 16:20:29', 0),
(18, 2, 1, 'sameer', 4, '', 'english', 'approved', '2026-05-07 16:21:02', '2026-05-07 16:21:12', 0),
(19, 2, 34, 'sameer', 3, '', 'english', 'approved', '2026-05-07 21:19:15', '2026-05-07 21:19:28', 0),
(20, 2, 1, 'sameer', 3, '', 'english', 'approved', '2026-05-07 21:24:38', '2026-05-07 21:24:50', 0),
(21, 12, 2, 'ankit', 2, '', 'english', 'approved', '2026-05-08 10:48:45', '2026-05-08 10:52:03', 0),
(22, 2, 14, 'sameer', 2, 'Hi i wanted to borrow this book', 'english', 'approved', '2026-05-17 18:45:52', '2026-05-17 18:46:04', 0),
(23, 2, 20, 'sameer', 7, '', 'nepali', 'approved', '2026-05-17 18:54:02', '2026-05-17 18:54:12', 0),
(24, 2, 8, 'sameer', 5, '', 'english', 'approved', '2026-05-17 18:58:52', '2026-05-17 18:58:59', 0),
(25, 2, 9, 'sameer', 4, 'hello', 'english', 'approved', '2026-05-17 19:02:00', '2026-05-17 19:02:08', 0);

-- --------------------------------------------------------

--
-- Table structure for table `borrow_waitlist`
--

CREATE TABLE `borrow_waitlist` (
  `id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 1,
  `status` enum('waiting','notified','expired','fulfilled') DEFAULT 'waiting',
  `joined_at` datetime DEFAULT current_timestamp(),
  `notified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrow_waitlist`
--

INSERT INTO `borrow_waitlist` (`id`, `book_id`, `user_id`, `position`, `status`, `joined_at`, `notified_at`) VALUES
(1, 1, 11, 1, 'notified', '2026-05-07 19:01:55', '2026-05-17 09:46:47'),
(2, 3, 2, 1, 'waiting', '2026-05-08 10:28:49', NULL);

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
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 'borrow_rejected', 'Borrow Request Rejected', 'Your request to borrow \"To Kill a Mockingbird\" was not approved by the admin.', 1, '2026-05-07 16:19:51'),
(2, 2, 'borrow_approved', 'Borrow Request Approved', 'Your request to borrow \"The Great Gatsby\" has been approved! Rs 300.00 has been deducted from your wallet. Due date: 2026-05-10.', 1, '2026-05-07 16:20:29'),
(3, 2, 'borrow_approved', 'Borrow Request Approved', 'Your request to borrow \"The Lord of the Rings\" has been approved! Rs 400.00 has been deducted from your wallet. Due date: 2026-05-11.', 1, '2026-05-07 16:21:12'),
(4, 2, 'due_reminder', '📅 Due Tomorrow', 'Reminder: \"Strategic Writing for UX\" is due tomorrow. Return it on time to avoid fines.', 1, '2026-05-07 17:41:40'),
(5, 2, 'book_returned', 'Book Returned Successfully', 'You\'ve successfully returned \"The Lord of the Rings\". No fines — thanks for returning on time!', 1, '2026-05-07 20:06:16'),
(6, 11, 'waitlist_ready', 'Book Now Available!', '\"The Lord of the Rings\" is now available. Head to the library to borrow it before someone else does!', 0, '2026-05-07 20:06:16'),
(7, 2, 'book_returned', 'Book Returned — Fines Charged', 'You\'ve returned \"The Lord of the Rings\". Fines charged: Damage (fair) (Rs 200.00). Total deducted: Rs 200.00.', 1, '2026-05-07 20:06:31'),
(8, 11, 'waitlist_ready', 'Book Now Available!', '\"The Lord of the Rings\" is now available. Head to the library to borrow it before someone else does!', 0, '2026-05-07 20:06:31'),
(9, 2, 'book_returned', 'Book Returned — Fines Charged', 'You\'ve returned \"The Lord of the Rings\". Fines charged: Damage (fair) (Rs 200.00). Total deducted: Rs 200.00.', 1, '2026-05-07 20:06:33'),
(10, 11, 'waitlist_ready', 'Book Now Available!', '\"The Lord of the Rings\" is now available. Head to the library to borrow it before someone else does!', 0, '2026-05-07 20:06:33'),
(11, 2, 'book_returned', 'Book Returned Successfully', 'You\'ve successfully returned \"The Lord of the Rings\". No fines — thanks for returning on time!', 1, '2026-05-07 20:06:40'),
(12, 11, 'waitlist_ready', 'Book Now Available!', '\"The Lord of the Rings\" is now available. Head to the library to borrow it before someone else does!', 0, '2026-05-07 20:06:40'),
(13, 2, 'book_returned', 'Book Returned Successfully', 'You\'ve successfully returned \"The Lord of the Rings\". No fines — thanks for returning on time!', 1, '2026-05-07 20:06:41'),
(14, 11, 'waitlist_ready', 'Book Now Available!', '\"The Lord of the Rings\" is now available. Head to the library to borrow it before someone else does!', 0, '2026-05-07 20:06:41'),
(15, 2, 'book_returned', 'Book Returned Successfully', 'You\'ve successfully returned \"The Lord of the Rings\". No fines — thanks for returning on time!', 1, '2026-05-07 20:06:41'),
(16, 11, 'waitlist_ready', 'Book Now Available!', '\"The Lord of the Rings\" is now available. Head to the library to borrow it before someone else does!', 0, '2026-05-07 20:06:41'),
(17, 2, 'book_returned', 'Book Returned Successfully', 'You\'ve successfully returned \"The Lord of the Rings\". No fines — thanks for returning on time!', 1, '2026-05-07 20:06:42'),
(18, 11, 'waitlist_ready', 'Book Now Available!', '\"The Lord of the Rings\" is now available. Head to the library to borrow it before someone else does!', 0, '2026-05-07 20:06:42'),
(19, 2, 'book_returned', 'Book Returned Successfully', 'You\'ve successfully returned \"The Lord of the Rings\". No fines — thanks for returning on time!', 1, '2026-05-07 20:06:43'),
(20, 11, 'waitlist_ready', 'Book Now Available!', '\"The Lord of the Rings\" is now available. Head to the library to borrow it before someone else does!', 0, '2026-05-07 20:06:43'),
(21, 2, 'book_returned', 'Book Returned Successfully', 'You\'ve successfully returned \"Strategic Writing for UX\". No fines — thanks for returning on time!', 1, '2026-05-07 20:07:25'),
(22, 2, 'book_returned', 'Book Returned — Fines Charged', 'You\'ve returned \"Book of Five RIngs\". Fines charged: Overdue (5 days) (Rs 500.00), Damage (damaged) (Rs 1,200.00). Total deducted: Rs 1,700.00.', 1, '2026-05-07 20:07:57'),
(23, 2, 'book_returned', 'Book Returned — Fines Charged', 'You\'ve returned \"The Great Gatsby\". Fines charged: Damage (damaged) (Rs 1,200.00). Total deducted: Rs 1,200.00.', 1, '2026-05-07 20:08:28'),
(24, 2, 'book_returned', 'Book Returned — Fines Charged', 'You\'ve returned \"Crazy\". Fines charged: Overdue (2 days) (Rs 200.00). Total deducted: Rs 200.00.', 1, '2026-05-07 20:15:35'),
(25, 2, 'book_returned', 'Return Submitted — Pending Verification', 'Your return of \"Sapiens\" has been submitted. An admin will verify the book condition and finalise the return shortly.', 1, '2026-05-07 20:52:18'),
(26, 2, 'borrow_approved', 'Borrow Request Approved', 'Your request to borrow \"The 7 Habits of Highly Effective People\" has been approved! Due date: 2026-05-10.', 1, '2026-05-07 21:19:28'),
(27, 2, 'book_lost_pending', 'Lost Book Report Submitted', 'You\'ve reported \"The 7 Habits of Highly Effective People\" as lost. The admin will review and process the charge (Rs 1,500.00). The book remains on your account until confirmed.', 1, '2026-05-07 21:20:22'),
(28, 2, 'book_lost', 'Book Marked as Lost by Admin', 'The book \"The 7 Habits of Highly Effective People\" has been marked as lost by the admin. Rs 1,500.00 has been charged to your wallet.', 1, '2026-05-07 21:20:51'),
(29, 2, 'borrow_approved', 'Borrow Request Approved', 'Your request to borrow \"The Lord of the Rings\" has been approved! Due date: 2026-05-10.', 1, '2026-05-07 21:24:50'),
(30, 2, 'book_returned', 'Return Approved — Fines Charged', 'Your return of \"Sapiens\" has been verified. Admin condition: good. Fines: Overdue (1 days) (Rs 100.00). Total deducted: Rs 100.00.', 1, '2026-05-07 21:27:24'),
(31, 12, 'borrow_approved', 'Borrow Request Approved', 'Your request to borrow \"1984\" has been approved! Due date: 2026-05-10.', 1, '2026-05-08 10:52:03'),
(32, 12, 'book_lost_pending', 'Lost Book Report Submitted', 'You\'ve reported \"1984\" as lost. The admin will review and process the charge (Rs 1,500.00). The book remains on your account until confirmed.', 1, '2026-05-08 10:53:10'),
(33, 12, 'book_lost', 'Book Marked as Lost by Admin', 'The book \"1984\" has been marked as lost by the admin. Rs 1,500.00 has been charged to your wallet.', 1, '2026-05-08 10:53:30'),
(34, 2, 'book_returned', 'Return Submitted — Pending Verification', 'Your return of \"The Lord of the Rings\" has been submitted. An admin will verify the book condition and finalise the return shortly.', 1, '2026-05-17 09:46:08'),
(35, 2, 'book_returned', 'Return Approved — Fines Charged', 'Your return of \"The Lord of the Rings\" has been verified. Admin condition: fair. Fines: Overdue (7 days) (Rs 700.00), Damage (fair) (Rs 200.00). Total deducted: Rs 900.00.', 1, '2026-05-17 09:46:47'),
(36, 11, 'waitlist_ready', 'Book Now Available!', '\"The Lord of the Rings\" is now available. Head to the library to borrow it!', 0, '2026-05-17 09:46:47'),
(37, 2, 'borrow_approved', 'Borrow Request Approved', 'Your request to borrow \"GroupMeeting\" has been approved! Due date: 2026-05-19.', 1, '2026-05-17 18:46:04'),
(38, 2, 'borrow_extended', 'Borrow Extended', 'Your borrow of \"GroupMeeting\" has been extended by 3 day(s). New due date: 2026-05-22. Rs 240.00 deducted from your wallet.', 1, '2026-05-17 18:47:20'),
(39, 2, 'borrow_approved', 'Borrow Request Approved', 'Your request to borrow \"The Chronicles of Narnia\" has been approved! Due date: 2026-05-24.', 1, '2026-05-17 18:54:12'),
(40, 2, 'borrow_extended', 'Borrow Extended', 'Your borrow of \"The Chronicles of Narnia\" has been extended by 6 day(s). New due date: 2026-05-30. Rs 480.00 deducted from your wallet.', 1, '2026-05-17 18:58:10'),
(41, 2, 'borrow_approved', 'Borrow Request Approved', 'Your request to borrow \"101 Amazing Switzerland\" has been approved! Due date: 2026-05-22.', 1, '2026-05-17 18:58:59'),
(42, 2, 'borrow_extended', 'Borrow Extended', 'Your borrow of \"101 Amazing Switzerland\" has been extended by 2 day(s). New due date: 2026-05-24. Rs 160.00 deducted from your wallet.', 1, '2026-05-17 18:59:20'),
(43, 2, 'borrow_approved', 'Borrow Request Approved', 'Your request to borrow \"Logo Design Love\" has been approved! Due date: 2026-05-21.', 1, '2026-05-17 19:02:08'),
(44, 2, 'borrow_extended', 'Borrow Extended', 'Your borrow of \"Logo Design Love\" has been extended by 1 day(s). New due date: 2026-05-22. Rs 80.00 deducted from your wallet.', 1, '2026-05-17 19:02:38');

-- --------------------------------------------------------

--
-- Table structure for table `pdf_download_logs`
--

CREATE TABLE `pdf_download_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `downloaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pdf_download_logs`
--

INSERT INTO `pdf_download_logs` (`id`, `user_id`, `book_id`, `downloaded_at`) VALUES
(1, 2, 1, '2026-05-07 21:22:31'),
(2, 2, 2, '2026-05-08 10:33:56'),
(3, 12, 1, '2026-05-08 10:47:36'),
(4, 12, 2, '2026-05-08 11:16:16'),
(5, 13, 10, '2026-05-08 11:29:11'),
(6, 13, 2, '2026-05-08 11:33:51'),
(7, 13, 14, '2026-05-08 11:34:25'),
(8, 13, 21, '2026-05-08 11:36:23'),
(9, 2, 17, '2026-05-08 17:47:46'),
(10, 2, 21, '2026-05-08 17:47:55'),
(11, 2, 28, '2026-05-16 18:00:40'),
(12, 2, 27, '2026-05-16 18:01:54'),
(13, 2, 29, '2026-05-16 18:16:13'),
(14, 2, 32, '2026-05-16 18:16:28'),
(15, 2, 25, '2026-05-17 09:55:37');

-- --------------------------------------------------------

--
-- Table structure for table `pdf_purchases`
--

CREATE TABLE `pdf_purchases` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `purchased_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pdf_purchases`
--

INSERT INTO `pdf_purchases` (`id`, `user_id`, `book_id`, `purchased_at`) VALUES
(1, 2, 2, '2026-04-27 17:09:52'),
(2, 2, 14, '2026-04-28 21:06:13'),
(3, 2, 1, '2026-05-02 21:24:40'),
(4, 2, 81, '2026-05-07 16:24:50'),
(5, 2, 5, '2026-05-07 16:29:43'),
(6, 2, 7, '2026-05-07 16:29:55'),
(7, 2, 31, '2026-05-07 16:40:44'),
(8, 12, 1, '2026-05-08 10:47:36'),
(9, 12, 2, '2026-05-08 11:16:16'),
(10, 13, 10, '2026-05-08 11:29:11'),
(11, 13, 2, '2026-05-08 11:33:51'),
(12, 13, 14, '2026-05-08 11:34:25'),
(13, 13, 21, '2026-05-08 11:36:22'),
(14, 2, 17, '2026-05-08 17:47:46'),
(15, 2, 21, '2026-05-08 17:47:55'),
(16, 2, 28, '2026-05-16 18:00:40'),
(17, 2, 27, '2026-05-16 18:01:54'),
(18, 2, 29, '2026-05-16 18:16:13'),
(19, 2, 32, '2026-05-16 18:16:28'),
(20, 2, 25, '2026-05-17 09:55:37');

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
  `role` varchar(20) NOT NULL DEFAULT 'user',
  `balance` decimal(10,2) NOT NULL DEFAULT 35000.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `username`, `password`, `role`, `balance`) VALUES
(1, 'Gritika', 'Shrestha', 'gritika@gmail.com', 'gritika', '$2y$10$lGL6XIPun/ISjVOSJhD.x.Z10yZWX9pr2FmyZOuxXLpdGUgtK227S', 'user', 35000.00),
(2, 'Sameer', 'Dahal', 'sam@gmail.com', 'sameer', '$2y$10$icMqpWlusJoDN.Qdt.fTjegKOJiqnP89yU.YX9E3lhmjYNRnoTboO', 'user', 20840.00),
(3, 'Tezus', 'P', 'tezus@gmail.com', 'tezus', '$2y$10$FB2chuU8lklQCtHUWsencOrMzZjpkoXlf5slGIgoFV9T6RgaDn4Pq', 'user', 35000.00),
(4, 'Deepika', 'D', 'deepika@gmail.com', 'deepika', '$2y$10$YOOcM.aoNhBO.Pfx28pQ/uxxw3wX1U7SmEW1GYLlwcyRVUogMI0q2', 'user', 35000.00),
(5, 'Deepika', 'Shrestha', 'dipika@gmail.com', 'deepika12', '$2y$10$EZEorghNReLHARhyoDTYy./pEdmtnbPCLKiX40jDAIAHqD7q1yl.a', 'user', 35000.00),
(6, 'Tezu', 'P', 'tezu@gmail.com', 'tezu', '$2y$10$qn4142bnmI2wjL0ShwOKAuFVN.W5vgGUcstzSovFQANcL9GucMjqC', 'user', 34100.00),
(7, 'Samujwal', 'Shrestha', 'samujwal@gmail.com', 'samujwal', '$2y$10$zh9tD3dMF68YWeYVVRZ3wekQJt/X3nD6q4F4NXt/eBStI/VZ2PnXS', 'user', 35000.00),
(8, 'Fury', 'Parker', 'fury@gmail.com', 'fury', '$2y$10$OMAVMItBfyckxuvsgY.tTetcPMnvFXIl/jRg0tGw4KbQFd61fiG2W', 'user', 35000.00),
(9, 'Shuvam', 'Jha', 'shuvam@gmail.com', 'shuvamj', '$2y$10$Oy9PJxpCOpL49xQSGhDCpuIyaAv9EAGLQ.d0rjX7RP.uA6jC7Waz2', 'user', 35000.00),
(10, '123', '1231', 'hacker@gmail.com', 'hacker', '$2y$10$p5P5OrwR.O2kwVyorL2PGuMsSBM3L62GMs76SuIn57gqTECD0jWly', 'user', 35000.00),
(11, 'Nikesh', 'Katuwal', 'nikesh@gmail.com', 'nikesh', '$2y$10$T8UQStjwXSEotD9Zq/S5Jeqbmm9T30mBwoNEWJhOJYBRjOj24nmwe', 'user', 35000.00),
(12, 'Ankit', 'Tamrakar', 'miseja4122@ellbit.com', 'ankit', '$2y$10$Y9yfSxJMlAt7hgYCs4fcROAQ3yxkAqOxIvIahE7yJiTUMf75Kq6Su', 'user', 32700.00),
(13, 'Ava', 'Lynn', 'ava@gmail.com', 'Ava', '$2y$10$OEBqciPIwxVSrTvdnbv9PeXkCaOG5dhAJxAUwiBxPFwnlpG/L.nyG', 'user', 4400.00);

-- --------------------------------------------------------

--
-- Table structure for table `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'The user whose wallet was affected',
  `type` varchar(30) NOT NULL COMMENT 'borrow_fee | extension_fee | pdf_purchase | overdue_fine | damage_fine | top_up',
  `amount` decimal(10,2) NOT NULL COMMENT 'Negative = deducted from user, Positive = added (top_up)',
  `description` varchar(255) NOT NULL COMMENT 'Human-readable label',
  `reference_id` int(11) DEFAULT NULL COMMENT 'borrowing_id / extension_id / book_return_id depending on type',
  `book_id` int(11) DEFAULT NULL COMMENT 'Book this transaction relates to',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wallet_transactions`
--

INSERT INTO `wallet_transactions` (`id`, `user_id`, `type`, `amount`, `description`, `reference_id`, `book_id`, `created_at`) VALUES
(1, 6, 'overdue_fine', -900.00, 'Overdue fine: \"The Lord of the Rings\" — 9 day(s) late', 11, 1, '2026-04-27 17:06:09'),
(2, 2, 'overdue_fine', -700.00, 'Overdue fine: \"Crazy\" — 7 day(s) late', 13, 13, '2026-04-27 17:08:08'),
(3, 2, 'damage_fine', -1200.00, 'Damage fine: \"Crazy\" — condition: damaged', 13, 13, '2026-04-27 17:08:08'),
(4, 2, 'borrow_fee', -700.00, 'Borrow fee: \"The Lord of the Rings\" for 7 day(s)', 14, 1, '2026-04-27 17:08:55'),
(5, 2, 'pdf_purchase', -400.00, 'Digital purchase: \"1984\" (PDF)', NULL, 2, '2026-04-27 17:09:52'),
(6, 2, 'pdf_purchase', -400.00, 'Digital purchase: \"GroupMeeting\" (PDF)', NULL, 14, '2026-04-28 21:06:13'),
(7, 2, 'borrow_fee', -100.00, 'Borrow fee: \"Crazy\" for 1 day(s)', 15, 13, '2026-04-28 21:09:45'),
(8, 2, 'extension_fee', -160.00, 'Extension fee: \"Crazy\" +2 day(s)', 4, 13, '2026-04-28 21:10:07'),
(9, 2, 'extension_fee', -240.00, 'Extension fee: \"Crazy\" +3 day(s)', 5, 13, '2026-04-28 21:10:21'),
(10, 2, 'damage_fine', -200.00, 'Damage fine: \"Crazy\" — condition: fair', 15, 13, '2026-04-28 21:13:39'),
(11, 2, 'borrow_fee', -400.00, 'Borrow fee: \"Book of Five RIngs\" for 4 day(s)', 16, 15, '2026-04-28 21:14:08'),
(12, 2, 'borrow_fee', -100.00, 'Borrow fee: \"1984\" for 1 day(s)', 17, 2, '2026-04-28 21:14:10'),
(13, 2, 'damage_fine', -1200.00, 'Damage fine: \"1984\" — condition: damaged', 17, 2, '2026-04-28 21:14:33'),
(14, 2, 'borrow_fee', -400.00, 'Borrow fee: \"Sapiens\" for 4 day(s)', 18, 11, '2026-05-02 10:20:56'),
(15, 2, 'borrow_fee', -300.00, 'Borrow fee: \"Crazy\" for 3 day(s)', 19, 13, '2026-05-02 10:20:57'),
(16, 2, 'pdf_purchase', -400.00, 'Digital purchase: \"The Lord of the Rings\" (PDF)', NULL, 1, '2026-05-02 21:24:40'),
(17, 2, 'borrow_fee', -200.00, 'Borrow fee: \"Strategic Writing for UX\" for 2 day(s)', 20, 4, '2026-05-06 17:01:21'),
(18, 2, 'borrow_fee', -300.00, 'Borrow fee: \"The Great Gatsby\" for 3 day(s)', 21, 22, '2026-05-07 16:20:29'),
(19, 2, 'borrow_fee', -400.00, 'Borrow fee: \"The Lord of the Rings\" for 4 day(s)', 22, 1, '2026-05-07 16:21:12'),
(20, 2, 'pdf_purchase', -400.00, 'Digital purchase: \"Me Before You\" (PDF)', NULL, 81, '2026-05-07 16:24:50'),
(21, 2, 'pdf_purchase', -400.00, 'Digital purchase: \"Web Design: Evolution\" (PDF)', NULL, 5, '2026-05-07 16:29:43'),
(22, 2, 'pdf_purchase', -400.00, 'Digital purchase: \"The Hobbit\" (PDF)', NULL, 7, '2026-05-07 16:29:55'),
(23, 2, 'pdf_purchase', -400.00, 'Digital purchase: \"Foundation\" (PDF)', NULL, 31, '2026-05-07 16:40:44'),
(24, 2, 'damage_fine', -200.00, 'Damage fine: \"The Lord of the Rings\" — condition: fair', NULL, 1, '2026-05-07 20:06:31'),
(25, 2, 'damage_fine', -200.00, 'Damage fine: \"The Lord of the Rings\" — condition: fair', NULL, 1, '2026-05-07 20:06:33'),
(26, 2, 'overdue_fine', -500.00, 'Overdue fine: \"Book of Five RIngs\" — 5 day(s) late', 16, 15, '2026-05-07 20:07:57'),
(27, 2, 'damage_fine', -1200.00, 'Damage fine: \"Book of Five RIngs\" — condition: damaged', 16, 15, '2026-05-07 20:07:57'),
(28, 2, 'damage_fine', -1200.00, 'Damage fine: \"The Great Gatsby\" — condition: damaged', 21, 22, '2026-05-07 20:08:28'),
(29, 2, 'overdue_fine', -200.00, 'Overdue fine: \"Crazy\" — 2 day(s) late', 19, 13, '2026-05-07 20:15:35'),
(30, 2, 'top_up', 5000.00, 'Self top-up of Rs 5,000.00', NULL, NULL, '2026-05-07 21:18:35'),
(31, 2, 'lost_book', -1500.00, 'Lost book charge (admin): \"The 7 Habits of Highly Effective People\"', 23, 34, '2026-05-07 21:20:51'),
(32, 2, 'overdue_fine', -100.00, 'Overdue fine: \"Sapiens\" — 1 day(s) late', 18, 11, '2026-05-07 21:27:24'),
(33, 12, 'pdf_purchase', -400.00, 'Digital purchase: \"The Lord of the Rings\" (PDF)', NULL, 1, '2026-05-08 10:47:36'),
(34, 12, 'lost_book', -1500.00, 'Lost book charge (admin): \"1984\"', 25, 2, '2026-05-08 10:53:30'),
(35, 12, 'pdf_purchase', -400.00, 'Digital purchase: \"1984\" (PDF)', NULL, 2, '2026-05-08 11:16:16'),
(36, 13, 'top_up', 1000.00, 'Self top-up of Rs 1,000.00', NULL, NULL, '2026-05-08 11:29:02'),
(37, 13, 'pdf_purchase', -400.00, 'Digital purchase: \"One Year on a Bike\" (PDF)', NULL, 10, '2026-05-08 11:29:11'),
(38, 13, 'pdf_purchase', -400.00, 'Digital purchase: \"1984\" (PDF)', NULL, 2, '2026-05-08 11:33:51'),
(39, 13, 'top_up', 5000.00, 'Self top-up of Rs 5,000.00', NULL, NULL, '2026-05-08 11:34:20'),
(40, 13, 'pdf_purchase', -400.00, 'Digital purchase: \"GroupMeeting\" (PDF)', NULL, 14, '2026-05-08 11:34:25'),
(41, 13, 'pdf_purchase', -400.00, 'Digital purchase: \"To Kill a Mockingbird\" (PDF)', NULL, 21, '2026-05-08 11:36:22'),
(42, 2, 'pdf_purchase', -400.00, 'Digital purchase: \"A Game of Thrones\" (PDF)', NULL, 17, '2026-05-08 17:47:46'),
(43, 2, 'pdf_purchase', -400.00, 'Digital purchase: \"To Kill a Mockingbird\" (PDF)', NULL, 21, '2026-05-08 17:47:55'),
(44, 2, 'pdf_purchase', -400.00, 'Digital purchase: \"The Da Vinci Code\" (PDF)', NULL, 28, '2026-05-16 18:00:40'),
(45, 2, 'pdf_purchase', -400.00, 'Digital purchase: \"The Kite Runner\" (PDF)', NULL, 27, '2026-05-16 18:01:54'),
(46, 2, 'pdf_purchase', -400.00, 'Digital purchase: \"Ender\'s Game\" (PDF)', NULL, 29, '2026-05-16 18:16:13'),
(47, 2, 'pdf_purchase', -400.00, 'Digital purchase: \"The Hitchhiker\'s Guide to the Galaxy\" (PDF)', NULL, 32, '2026-05-16 18:16:28'),
(48, 2, 'overdue_fine', -700.00, 'Overdue fine: \"The Lord of the Rings\" — 7 day(s) late', 24, 1, '2026-05-17 09:46:47'),
(49, 2, 'damage_fine', -200.00, 'Damage fine: \"The Lord of the Rings\" — condition: fair', 24, 1, '2026-05-17 09:46:47'),
(50, 2, 'pdf_purchase', -400.00, 'Digital purchase: \"Brave New World\" (PDF)', NULL, 25, '2026-05-17 09:55:37'),
(51, 2, 'extension_fee', -240.00, 'Extension fee: \"GroupMeeting\" +3 day(s)', 6, 14, '2026-05-17 18:47:20'),
(52, 2, 'extension_fee', -480.00, 'Extension fee: \"The Chronicles of Narnia\" +6 day(s)', 7, 20, '2026-05-17 18:58:10'),
(53, 2, 'extension_fee', -160.00, 'Extension fee: \"101 Amazing Switzerland\" +2 day(s)', 8, 8, '2026-05-17 18:59:20'),
(54, 2, 'extension_fee', -80.00, 'Extension fee: \"Logo Design Love\" +1 day(s)', 9, 9, '2026-05-17 19:02:38');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_balance`
--
ALTER TABLE `admin_balance`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `book_reviews`
--
ALTER TABLE `book_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_book` (`book_id`,`user_id`),
  ADD KEY `idx_book` (`book_id`);

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
-- Indexes for table `borrow_waitlist`
--
ALTER TABLE `borrow_waitlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_book` (`book_id`,`user_id`),
  ADD KEY `idx_book_status` (`book_id`,`status`);

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
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_unread` (`user_id`,`is_read`);

--
-- Indexes for table `pdf_download_logs`
--
ALTER TABLE `pdf_download_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_book` (`user_id`,`book_id`);

--
-- Indexes for table `pdf_purchases`
--
ALTER TABLE `pdf_purchases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_purchase` (`user_id`,`book_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `type` (`type`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `wt_book_fk` (`book_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_balance`
--
ALTER TABLE `admin_balance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=118;

--
-- AUTO_INCREMENT for table `book_returns`
--
ALTER TABLE `book_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `book_reviews`
--
ALTER TABLE `book_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `borrowings`
--
ALTER TABLE `borrowings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `borrow_extensions`
--
ALTER TABLE `borrow_extensions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `borrow_requests`
--
ALTER TABLE `borrow_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `borrow_waitlist`
--
ALTER TABLE `borrow_waitlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `pdf_download_logs`
--
ALTER TABLE `pdf_download_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `pdf_purchases`
--
ALTER TABLE `pdf_purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

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

--
-- Constraints for table `pdf_purchases`
--
ALTER TABLE `pdf_purchases`
  ADD CONSTRAINT `pp_book_fk` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pp_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD CONSTRAINT `wt_book_fk` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `wt_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
