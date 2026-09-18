-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Хост: MySQL-8.4:3306
-- Время создания: Авг 24 2026 г., 11:36
-- Версия сервера: 8.4.8
-- Версия PHP: 8.5.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `hydra`
--

-- --------------------------------------------------------

--
-- Структура таблицы `admin_account`
--

CREATE TABLE `admin_account` (
  `id` tinyint UNSIGNED NOT NULL,
  `login` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `admin_account`
--

INSERT INTO `admin_account` (`id`, `login`, `password_hash`, `updated_at`) VALUES
(1, 'spaider3', '$2y$12$zPKYd.NDBqIYCDiLWI7I4.J2CO9WbOtJ9sY1QZxGX1aAczP9IHYlG', '2026-08-23 00:22:15');

-- --------------------------------------------------------

--
-- Структура таблицы `comments`
--

CREATE TABLE `comments` (
  `id_comment` int NOT NULL,
  `name` varchar(15) NOT NULL,
  `comment` text NOT NULL,
  `id_profile` varchar(250) NOT NULL,
  `commenter_id` int DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `comments`
--

INSERT INTO `comments` (`id_comment`, `name`, `comment`, `id_profile`, `commenter_id`) VALUES
(1, 'Mr.Green', '*devil*', '10', NULL),
(2, 'Mr.Green', '*yahoo* *yahoo* *yahoo* зачипись', '25', NULL),
(6, 'Hadzho', 'Порнуха прибыльное дело))<br />\r\nJa,Ja, Das ist fantastisch!!', '7', NULL),
(67, 'SlavaBoss', 'Может по откровений ?:) ', '25', NULL),
(68, 'енот))))))', 'Ух ты Анька с 5 подъезда!!!!!', '56', NULL),
(66, 'dimo4ka', 'поставьте ей фотку получше ', '25', NULL),
(9, '|_S_p_I_r_T_|', 'Ещё бы !!! Гордость сайта =_))', '25', NULL),
(11, '|_S_p_I_r_T|', 'Вот это ножки =О', '37', NULL),
(12, 'Mr.Green', 'Больше голосов и она попадёт на 1 место :)', '25', NULL),
(13, '|_S_p_I_r_T_|', 'Надо заменить её фото на новое где она в розовом =_))', '25', NULL),
(14, 'Mr.Green', 'сменим чуть позже. пока движок доделываю', '25', NULL),
(33, '|slavaboss|', 'Веселись народ !!! *yahoo*', '55', NULL),
(18, '|_S_p_I_r_T_|', ':)', '25', NULL),
(35, 'абогрив', 'может согреть тебя детка?', '38', NULL),
(40, '/|\\', 'нечево телка!!!)', '7', NULL),
(41, 'Dimo4ka39Rus', 'Точно гордость)))<br />\r\n', '25', NULL),
(44, '+100500', 'Я тебя щас сломаю придурок! Я тебя сломаю. Ты зачем бабушку побил? Да я тебя щас побью, тарантино', '55', NULL),
(75, 'Dimasik39rus', 'пидарасик 39 русский', '45', NULL),
(77, 'Lyuba Popova', 'Кто мои фото украл?))', '37', NULL),
(47, 'Ух ты', 'я б тебя )))))))))))', '22', NULL),
(78, 'Dimasik39rus', 'Я бы у неё отлизал!', '8', NULL),
(49, 'Евгения ', 'Я с с<br />\r\nкруть<br />\r\nда и я лизби', '24', NULL),
(79, 'Dimasik39rus', 'Это ваще моё фото.<br />\r\n', '37', NULL),
(70, 'Dimasik39rus', 'а мне не нравиться!!!<br />\r\nслишком наиграно!!!<br />\r\nаххааха', '1', NULL),
(80, 'Mr.Green', 'dfgfdgfdhdfh', '60', NULL),
(90, 'Mr.Green', 'dfggdgs', '60', NULL),
(84, 'Mr.Green', 'fdghfhgfhs', '60', NULL),
(85, 'Mr.Green', 'gfdddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd', '60', NULL),
(86, 'Mr.Green', 'fdgdffghfdh<br />\r\nfdgdfgdsfgggdf', '60', NULL),
(89, 'Mr.Green', '*devil*', '60', NULL),
(88, 'Mr.Green', 'ghfhhgvnchxchgf', '60', NULL),
(91, 'Mr.Green', 'fdfhdfh', '57', NULL),
(92, 'Mr.Green', 'fggd', '1', NULL),
(93, 'max', '99999999', '1', NULL),
(94, 'Mr.Green', '666', '60', NULL),
(95, 'Mr.Green', 'dgfg', '1', NULL),
(96, 'Mr.Green', 'hdfhfd', '76', NULL),
(97, 'Mr.Green', '543543', '76', NULL),
(98, 'dgsfgsdfg', 'gdfsgsd', '76', NULL),
(99, 'Mr.Green', 'fghdhf', '76', NULL),
(100, 'ghfhfh', 'fdhdfh', '76', NULL),
(101, 'Mr.Green', '*devil*', '1', NULL),
(102, 'Manjac', 'ffggdfh', '77', NULL),
(103, 'Manjac', 'fbdgf', '77', NULL),
(104, 'crewcrew', 'hi all', '79', NULL),
(107, 'Киря Апроксия', 'ddgsgsdf', '2', NULL),
(106, 'lor', '678', '76', NULL),
(108, 'Киря Апроксия', 'мсчвра', '2', NULL),
(109, 'Киря Апроксия', 'авпаврпв', '2', NULL),
(110, 'Киря Апроксия', 'аврпаврпо', '2', NULL),
(111, 'Киря Апроксия', '*devil*', '2', NULL),
(112, 'lor', 'ghdh', '63', NULL),
(113, 'Киря Апроксия', 'варв', '60', NULL),
(114, 'lor', 'hi', '69', 76),
(115, 'Grigory Retota', '999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999', '69', 69),
(116, 'spaider3367', 'fdgdfh', '83', 83),
(117, 'spaider3367', 'арспаа', '69', 83),
(118, 'Киря Апроксия', '*devil*', '60', 60),
(119, 'dfgdfgdf', 'truyturt', '63', 63),
(120, 'dfgdfgdf', 'паааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааав', '63', 63),
(121, 'dfgdfgdf', 'паппппппппппппппппппппппппппппппппв', '63', 63),
(122, 'dfgdfgdf', 'рвр', '63', 63),
(123, 'dfgdfgdf', 'dfg', '60', 63),
(124, 'dfgdfgdf', 'dfgfg', '63', 63),
(125, 'dfgdfgdf', 'gfdfgf', '63', 63),
(126, 'dfgdfgdf', 'dfggd', '63', 63),
(127, 'dfgdfgdf', 'dfgdgd', '63', 63),
(128, 'dfgdfgdf', 'апрв', '63', 63),
(129, 'dfgdfgdf', 'аправрва', '63', 63),
(130, 'dfgdfgdf', 'авра', '69', 63),
(131, 'dfgdfgdf', 'аврарпа', '69', 63),
(132, 'dfgdfgdf', '56453654', '63', 63),
(133, 'dfgdfgdf', '645654', '63', 63),
(134, 'Киря Апроксия', 'fdhh', '60', 60),
(135, 'Киря Апроксия', 'dfhfh', '60', 60),
(136, 'Киря Апроксия', 'fdhfdhgfgfg', '60', 60),
(137, 'Киря Апроксия', 'fdghfgdh', '60', 60),
(138, 'Киря Апроксия', 'fghfghghgfff', '60', 60),
(139, 'Киря Апроксия', 'hfghfghfg', '60', 60),
(140, 'Киря Апроксия', 'fgdffgfh', '60', 60),
(141, 'Киря Апроксия', 'fdhfdhdf', '60', 60),
(142, 'Киря Апроксия', 'fghyhtyr', '60', 60),
(143, 'Киря Апроксия', 'hfghdhdgf', '60', 60),
(144, 'Киря Апроксия', '*devil*', '60', 60),
(145, 'dfgdfgdf', 'hdfgfhgh', '63', 63),
(146, 'dfgdfgdf', 'gfhgfhfgh', '63', 63),
(147, 'fdgdfg', 'dfgg', '86', 86),
(148, 'fdgdfg', 'hyisy', '83', 86),
(149, 'lor', 'gdfgdf', '76', 76),
(150, 'fdhfdghd_', 'hfdh', '88', 88),
(151, 'dfjklhgkjhdf', 'вапавар', '89', 89),
(152, 'dfjklhgkjhdf', 'папоапо', '89', 89),
(154, 'dfgdfgdf', 'внеукнеку', '63', 63);

-- --------------------------------------------------------

--
-- Структура таблицы `crypto_wallets`
--

CREATE TABLE `crypto_wallets` (
  `id` bigint UNSIGNED NOT NULL,
  `currency` varchar(10) NOT NULL,
  `label` varchar(100) DEFAULT NULL,
  `address` varchar(255) NOT NULL,
  `tag` varchar(64) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `crypto_wallets`
--

INSERT INTO `crypto_wallets` (`id`, `currency`, `label`, `address`, `tag`, `is_active`, `created_at`) VALUES
(1, 'BTC', 'Основной BTC кошелёк', 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh', NULL, 1, '2026-08-21 19:37:28'),
(2, 'ETH', 'Основной ETH кошелёк', '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', NULL, 1, '2026-08-21 19:37:28'),
(3, 'XRP', 'Основной XRP кошелёк', 'rHb9CJAWyB4rj91VRWn96DkukG4bwdtyTh', '1234567890', 1, '2026-08-21 19:37:28');

-- --------------------------------------------------------

--
-- Структура таблицы `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` bigint UNSIGNED NOT NULL,
  `scope` varchar(20) NOT NULL,
  `identifier` varchar(191) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `scope`, `identifier`, `ip`, `success`, `created_at`) VALUES
(11, 'user', 'cream', '127.0.0.1', 1, '2026-08-22 14:47:27'),
(12, 'user', 'cream', '127.0.0.1', 1, '2026-08-22 14:54:15'),
(13, 'user', 'cream', '127.0.0.1', 1, '2026-08-22 15:04:26'),
(14, 'user', 'cream', '127.0.0.1', 1, '2026-08-22 19:24:30'),
(15, 'user', 'cream', '127.0.0.1', 1, '2026-08-22 21:05:58'),
(16, 'user', 'spaider3394', '127.0.0.1', 1, '2026-08-22 21:09:06'),
(17, 'user', 'spaider3394', '127.0.0.1', 1, '2026-08-22 21:09:30'),
(18, 'user', 'cream', '127.0.0.1', 1, '2026-08-22 21:11:33'),
(19, 'user', 'spaider3367', '127.0.0.1', 1, '2026-08-22 21:16:04'),
(20, 'user', 'spaider3367', '127.0.0.1', 1, '2026-08-22 21:56:55'),
(21, 'user', 'spaider3367', '127.0.0.1', 1, '2026-08-22 21:59:06'),
(22, 'user', 'cream', '127.0.0.1', 1, '2026-08-22 22:09:07'),
(23, 'user', 'spaider3367', '127.0.0.1', 1, '2026-08-23 00:17:49'),
(24, 'user', 'cream', '127.0.0.1', 1, '2026-08-23 00:35:26'),
(25, 'user', 'cream', '127.0.0.1', 1, '2026-08-23 00:46:00'),
(26, 'user', 'fdgdfg', '127.0.0.1', 1, '2026-08-23 08:20:08'),
(27, 'user', 'fdgdfg', '127.0.0.1', 1, '2026-08-23 08:54:25'),
(29, 'user', 'fdgdfg', '127.0.0.1', 1, '2026-08-23 08:57:38'),
(30, 'user', 'spaider3367', '127.0.0.1', 1, '2026-08-23 08:59:23');

-- --------------------------------------------------------

--
-- Структура таблицы `messages`
--

CREATE TABLE `messages` (
  `id` int NOT NULL,
  `sender` varchar(50) NOT NULL,
  `receiver` varchar(50) NOT NULL,
  `sender_id` int DEFAULT NULL,
  `receiver_id` int DEFAULT NULL,
  `content` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `messages`
--

INSERT INTO `messages` (`id`, `sender`, `receiver`, `sender_id`, `receiver_id`, `content`, `is_read`, `created_at`) VALUES
(1, 'user1', 'user2', NULL, NULL, 'ffdgdh', 1, '2026-08-19 17:05:41'),
(2, 'user1', 'user2', NULL, NULL, 'ffdgdhfgdklgkdfj', 1, '2026-08-19 17:08:29'),
(3, '76', '60', NULL, NULL, 'привет', 1, '2026-08-19 22:08:05'),
(4, '76', '60', NULL, NULL, 'как дела?', 1, '2026-08-19 22:08:13'),
(5, '76', '60', NULL, NULL, 'вы тут?', 1, '2026-08-19 22:08:46'),
(6, '76', '60', NULL, NULL, 'ghbfd', 1, '2026-08-19 22:21:24'),
(7, 'lor', 'Киря Апроксия', NULL, NULL, 'привет', 1, '2026-08-19 22:28:41'),
(8, 'lor', 'Киря Апроксия', NULL, NULL, 'как дела?', 1, '2026-08-19 22:28:50'),
(9, 'lor', 'Викуся Кантр', NULL, NULL, 'привет', 1, '2026-08-19 22:41:39'),
(10, 'lor', 'Викуся Кантр', NULL, NULL, 'как дела?', 1, '2026-08-19 22:41:48'),
(11, 'Manjac', 'Киря Апроксия', NULL, NULL, 'привет', 1, '2026-08-19 22:52:53'),
(12, 'Manjac', 'lor', NULL, NULL, 'hi мэн как дела?', 1, '2026-08-19 22:53:28'),
(13, 'lor', 'Manjac', NULL, NULL, 'хорошо', 1, '2026-08-19 22:54:41'),
(14, 'crewcrew', 'Киря Апроксия', NULL, NULL, 'привет!!!!!!!!', 1, '2026-08-19 23:10:20'),
(15, 'lor', 'crewcrew', NULL, NULL, 'привет привет', 1, '2026-08-19 23:11:40'),
(16, 'crewcrew', 'lor', NULL, NULL, '777', 1, '2026-08-19 23:12:28'),
(17, 'Киря Апроксия', 'Manjac', NULL, NULL, 'привет', 1, '2026-08-20 00:29:17'),
(18, 'lor', 'привет', NULL, NULL, 'приа', 1, '2026-08-20 00:59:24'),
(19, 'lor', 'Киря Апроксия', NULL, NULL, 'хорошо', 1, '2026-08-20 01:21:11'),
(20, 'Киря Апроксия', '', NULL, NULL, 'прваип', 1, '2026-08-20 03:01:57'),
(21, 'Киря Апроксия', 'Елена  Михедова', NULL, NULL, 'привет', 1, '2026-08-20 03:25:11'),
(22, 'Киря Апроксия', 'Елена  Михедова', NULL, NULL, 'как дела?', 1, '2026-08-20 03:25:18'),
(23, 'lor', 'Grigoriy', NULL, NULL, 'ghdsbjhf', 1, '2026-08-20 04:54:51'),
(24, 'lor', 'Киря Апроксия', 76, 60, 'jfsaaf', 1, '2026-08-20 05:32:41'),
(25, 'Киря Апроксия', 'manchik', 60, 81, '666444', 1, '2026-08-20 05:42:51'),
(26, 'Киря Апроксия', 'lor', 60, 76, 'ппривет', 1, '2026-08-20 12:09:13'),
(27, 'lor', 'Grigory Retota', 76, 69, 'приветствую', 1, '2026-08-20 12:56:24'),
(28, 'lor', 'Grigory Retota', 76, 69, 'как дела?', 1, '2026-08-20 12:59:10'),
(29, 'Киря Апроксия', 'Grigory Retota', 60, 69, 'jhjfdhjfhjk', 1, '2026-08-20 13:12:32'),
(30, 'Grigory Retota', '678347468734987', 69, 68, 'тпаврп', 1, '2026-08-20 13:18:48'),
(31, 'Grigory Retota', 'Киря Апроксия', 69, 60, 'варварпа', 1, '2026-08-20 13:21:17'),
(32, 'Spat', 'Grigory Retota', 71, 69, 'fdggdsdfsg', 1, '2026-08-20 13:23:50'),
(33, 'Grigory Retota', 'Киря Апроксия', 69, 60, 'арвавр', 1, '2026-08-20 13:28:00'),
(34, 'Grigory Retota', 'fdgfdghfdhdhdf', 69, 1, 'hdfggf', 1, '2026-08-20 13:44:41'),
(35, 'spaider3367', 'Татьяна Сычёва', 83, 54, 'fhf', 1, '2026-08-20 13:59:22'),
(36, 'spaider3367', 'Grigory Retota', 83, 69, '6654', 1, '2026-08-20 14:01:20'),
(37, 'spaider3367', 'Grigory Retota', 83, 69, 'рпаа', 1, '2026-08-20 14:44:31'),
(38, 'Киря Апроксия', 'Grigoriy', 60, 64, 'kfdsghihdsjkl\r\n\r\n\r\ndghdfhfd', 1, '2026-08-20 16:11:36'),
(39, 'Киря Апроксия', 'Grigoriy', 60, 64, '6544nhj\r\ngfdlkmj\r\nfgmiojfd\r\nmfdnjhnf', 1, '2026-08-20 16:11:59'),
(40, 'Киря Апроксия', 'Grigory Retota', 60, 69, 'fgsdg', 0, '2026-08-20 17:34:12'),
(41, 'lor', 'fdgfdghfdhdhdf', 76, 1, 'fdgfdsg', 0, '2026-08-20 17:36:35'),
(42, 'lor', 'Киря Апроксия', 76, 60, 'авап', 1, '2026-08-20 17:59:54'),
(43, 'lor', 'Grigory Retota', 76, 65, 'gjjjgh', 0, '2026-08-20 19:49:31'),
(44, 'Киря Апроксия', 'lor', 60, 76, 'fgjjg', 1, '2026-08-20 20:04:43'),
(45, 'fdgdfg', 'spaider3367', 86, 83, 'gghd', 0, '2026-08-20 20:25:11'),
(46, 'lor', 'fdgfdghfdhdhdf', 76, 1, 'dggd', 0, '2026-08-21 08:21:29'),
(47, 'gfhfghf', 'Grigory Retota', 60, 69, 'yyyrytryrt', 0, '2026-08-21 08:30:36'),
(48, 'gfhfghf', 'Grigory Retota', 60, 69, 'rtyreyey', 0, '2026-08-21 08:30:40'),
(49, 'dfgdfgdf', 'fdgfdghfdhdhdf', 63, 1, 'hdfhhdshfgf', 0, '2026-08-21 09:21:51'),
(50, 'gfhfghf', 'dfgdfgdf', 60, 63, 'Пользователь gfhfghf (ID: 60) запрашивает пополнение кошелька. Пожалуйста, пополните его баланс.', 1, '2026-08-21 09:27:38'),
(51, 'gfhfghf', 'dfgdfgdf', 60, 63, 'Пользователь gfhfghf (ID: 60) запрашивает пополнение кошелька. Пожалуйста, пополните его баланс.', 1, '2026-08-21 09:30:29'),
(52, 'gfhfghf', 'dfgdfgdf', 60, 63, 'Пользователь gfhfghf (ID: 60) запрашивает пополнение кошелька. Пожалуйста, пополните его баланс.', 1, '2026-08-21 09:32:46'),
(53, 'gfhfghf', 'dfgdfgdf', 60, 63, 'Пользователь gfhfghf (ID: 60) запрашивает пополнение кошелька. Пожалуйста, пополните его баланс.', 1, '2026-08-21 09:38:53'),
(54, 'dfgdfgdf', 'gfhfghf', 63, 60, 'Пользователь dfgdfgdf (ID: 63) запрашивает пополнение кошелька. Пожалуйста, пополните его баланс.', 1, '2026-08-21 09:43:08'),
(55, 'dfgdfgdf', 'gfhfghf', 63, 60, 'Пользователь dfgdfgdf (ID: 63) запрашивает пополнение кошелька. Пожалуйста, пополните его баланс.', 1, '2026-08-21 09:45:14'),
(56, 'dfgdfgdf', 'gfhfghf', 63, 60, 'Пользователь dfgdfgdf (ID: 63) запрашивает пополнение кошелька. Пожалуйста, пополните его баланс.', 1, '2026-08-21 09:49:05'),
(57, 'dfgdfgdf', 'gfhfghf', 63, 60, 'Пользователь dfgdfgdf (ID: 63) запрашивает пополнение кошелька. Пожалуйста, пополните его баланс.', 1, '2026-08-21 09:54:50'),
(58, 'dfgdfgdf', 'gfhfghf', 63, 60, 'Пользователь dfgdfgdf (ID: 63) запрашивает пополнение кошелька. Пожалуйста, пополните его баланс.', 1, '2026-08-21 10:13:50'),
(59, 'spudi', 'gfhfghf', 87, 60, 'Пользователь spudi (ID: 87) запрашивает пополнение кошелька. Пожалуйста, пополните его баланс.', 1, '2026-08-21 10:22:34'),
(60, 'dfgdfgdf', 'gfhfghf', 63, 60, 'Пользователь dfgdfgdf (ID: 63) запрашивает пополнение кошелька. Пожалуйста, пополните его баланс.', 1, '2026-08-21 10:30:35'),
(61, 'dfgdfgdf', 'gfhfghf', 63, 60, 'равар', 1, '2026-08-21 10:33:59'),
(62, 'fdhfdghd_', 'gfhfghf', 88, 60, 'Пользователь fdhfdghd_ (ID: 88) запрашивает пополнение кошелька. Пожалуйста, пополните его баланс.', 1, '2026-08-21 10:57:47'),
(63, 'fdhfdghd_', 'gfhfghf', 88, 60, 'Пользователь fdhfdghd_ (ID: 88) запрашивает пополнение кошелька. Пожалуйста, пополните его баланс.', 1, '2026-08-21 11:13:54'),
(64, 'fdhfdghd_', 'gfhfghf', 88, 60, 'Пользователь fdhfdghd_ (ID: 88) запрашивает пополнение кошелька. Пожалуйста, пополните его баланс.', 1, '2026-08-21 11:35:09'),
(65, 'fdhfdghd_', 'gfhfghf', 88, 60, 'Пользователь fdhfdghd_ (ID: 88) запрашивает пополнение кошелька. Пожалуйста, пополните его баланс.', 1, '2026-08-21 12:26:21'),
(66, 'fdhfdghd_', 'gfhfghf', 88, 60, 'Пользователь fdhfdghd_ (ID: 88) запрашивает пополнение кошелька. Пожалуйста, пополните его баланс.', 1, '2026-08-21 12:43:51'),
(67, 'dfjklhgkjhdf', 'gfhfghf', 89, 60, 'Пользователь dfjklhgkjhdf (ID: 89) запрашивает пополнение кошелька. Пожалуйста, пополните его баланс.', 1, '2026-08-21 12:53:56'),
(68, 'dfjklhgkjhdf', 'gfhfghf', 89, 60, 'Пользователь dfjklhgkjhdf (ID: 89) запрашивает пополнение кошелька. Пожалуйста, пополните его баланс.', 1, '2026-08-21 15:52:57'),
(69, 'lor', 'fdgfdghfdhdhdf', 76, 1, 'hdsfgdf', 0, '2026-08-21 20:07:25'),
(70, 'gfhfghf', 'dfgdfgdf', 60, 63, 'Заявка на пополнение #1: пользователь gfhfghf (ID: 60) запросил 6 000,00 € через BTC. Заявка доступна в панели управления.', 1, '2026-08-21 21:30:08'),
(71, 'gfhfghf', 'dfgdfgdf', 60, 63, 'Заявка на пополнение #2: пользователь gfhfghf (ID: 60) запросил 33 333,00 € через XRP. Заявка доступна в панели управления.', 1, '2026-08-21 22:18:09'),
(72, 'gfhfghf', 'dfgdfgdf', 60, 63, 'Заявка на пополнение #3: пользователь gfhfghf (ID: 60) запросил 444,00 € через BTC. Заявка доступна в панели управления.', 1, '2026-08-21 22:44:07'),
(73, 'lor', 'fdgfdghfdhdhdf', 76, 1, 'dfgdfg', 0, '2026-08-21 23:08:52'),
(74, 'lor', 'gfhfghf', 76, 60, 'gfdgdg', 1, '2026-08-21 23:09:01'),
(75, 'lor', 'gfhfghf', 76, 60, 'dfgd', 1, '2026-08-21 23:09:05'),
(76, 'lor', 'gfhfghf', 76, 60, 'Заявка на пополнение #4: пользователь lor (ID: 76) запросил 6 666,00 € через ETH. Заявка доступна в панели управления.', 1, '2026-08-21 23:13:04'),
(77, 'dfgdfgdf', 'gfhfghf', 63, 60, 'Заявка на пополнение #5: пользователь dfgdfgdf (ID: 63) запросил 443,00 € через BTC. Заявка доступна в панели управления.', 1, '2026-08-22 01:21:50'),
(78, 'lor', 'gfhfghf', 76, 60, 'Заявка на пополнение #6: пользователь lor (ID: 76) запросил 5 435,00 € через ETH. Заявка доступна в панели управления.', 1, '2026-08-22 01:27:32'),
(79, 'lor', 'dfgdfgdf', 76, 63, 'Заявка на пополнение #6: пользователь lor (ID: 76) запросил 5 435,00 € через ETH. Заявка доступна в панели управления.', 1, '2026-08-22 01:27:32'),
(80, 'lor', 'spaider3367', 76, 83, 'Заявка на пополнение #6: пользователь lor (ID: 76) запросил 5 435,00 € через ETH. Заявка доступна в панели управления.', 0, '2026-08-22 01:27:32'),
(81, 'lor', 'fdgdfg', 76, 86, 'Заявка на пополнение #6: пользователь lor (ID: 76) запросил 5 435,00 € через ETH. Заявка доступна в панели управления.', 0, '2026-08-22 01:27:32'),
(82, 'dfgdfgdf', 'gfhfghf', 63, 60, 'Заявка на пополнение #7: пользователь dfgdfgdf (ID: 63) запросил 54,00 € через XRP. Заявка доступна в панели управления.', 1, '2026-08-22 01:35:24'),
(83, 'dfgdfgdf', 'lor', 63, 76, 'Заявка на пополнение #7: пользователь dfgdfgdf (ID: 63) запросил 54,00 € через XRP. Заявка доступна в панели управления.', 1, '2026-08-22 01:35:24'),
(84, 'dfgdfgdf', 'spaider3367', 63, 83, 'Заявка на пополнение #7: пользователь dfgdfgdf (ID: 63) запросил 54,00 € через XRP. Заявка доступна в панели управления.', 0, '2026-08-22 01:35:24'),
(85, 'dfgdfgdf', 'fdgdfg', 63, 86, 'Заявка на пополнение #7: пользователь dfgdfgdf (ID: 63) запросил 54,00 € через XRP. Заявка доступна в панели управления.', 0, '2026-08-22 01:35:24'),
(86, 'lor', 'gfhfghf', 76, 60, 'Заявка на пополнение #8: пользователь lor (ID: 76) запросил 4,00 € через ETH. Заявка доступна в панели управления.', 1, '2026-08-22 01:48:04'),
(87, 'lor', 'dfgdfgdf', 76, 63, 'Заявка на пополнение #8: пользователь lor (ID: 76) запросил 4,00 € через ETH. Заявка доступна в панели управления.', 1, '2026-08-22 01:48:04'),
(88, 'lor', 'spaider3367', 76, 83, 'Заявка на пополнение #8: пользователь lor (ID: 76) запросил 4,00 € через ETH. Заявка доступна в панели управления.', 0, '2026-08-22 01:48:04'),
(89, 'lor', 'fdgdfg', 76, 86, 'Заявка на пополнение #8: пользователь lor (ID: 76) запросил 4,00 € через ETH. Заявка доступна в панели управления.', 0, '2026-08-22 01:48:04'),
(90, 'dfjklhgkjhdf', 'gfhfghf', 89, 60, 'Заявка на пополнение #9: пользователь dfjklhgkjhdf (ID: 89) запросил 1,00 € через BTC. Заявка доступна в панели управления.', 1, '2026-08-22 02:04:05'),
(91, 'dfjklhgkjhdf', 'dfgdfgdf', 89, 63, 'Заявка на пополнение #9: пользователь dfjklhgkjhdf (ID: 89) запросил 1,00 € через BTC. Заявка доступна в панели управления.', 1, '2026-08-22 02:04:05'),
(92, 'dfjklhgkjhdf', 'lor', 89, 76, 'Заявка на пополнение #9: пользователь dfjklhgkjhdf (ID: 89) запросил 1,00 € через BTC. Заявка доступна в панели управления.', 1, '2026-08-22 02:04:05'),
(93, 'dfjklhgkjhdf', 'spaider3367', 89, 83, 'Заявка на пополнение #9: пользователь dfjklhgkjhdf (ID: 89) запросил 1,00 € через BTC. Заявка доступна в панели управления.', 0, '2026-08-22 02:04:05'),
(94, 'dfjklhgkjhdf', 'fdgdfg', 89, 86, 'Заявка на пополнение #9: пользователь dfjklhgkjhdf (ID: 89) запросил 1,00 € через BTC. Заявка доступна в панели управления.', 0, '2026-08-22 02:04:05'),
(95, 'lor', 'gfhfghf', 76, 60, 'Заявка на пополнение #10: пользователь lor (ID: 76) запросил 33,00 € через XRP. Заявка доступна в панели управления.', 1, '2026-08-22 21:30:19'),
(96, 'lor', 'dfgdfgdf', 76, 63, 'Заявка на пополнение #10: пользователь lor (ID: 76) запросил 33,00 € через XRP. Заявка доступна в панели управления.', 1, '2026-08-22 21:30:19'),
(97, 'lor', 'spaider3367', 76, 83, 'Заявка на пополнение #10: пользователь lor (ID: 76) запросил 33,00 € через XRP. Заявка доступна в панели управления.', 0, '2026-08-22 21:30:19'),
(98, 'lor', 'fdgdfg', 76, 86, 'Заявка на пополнение #10: пользователь lor (ID: 76) запросил 33,00 € через XRP. Заявка доступна в панели управления.', 0, '2026-08-22 21:30:19'),
(99, 'lor', 'gfhfghf', 76, 60, 'Заявка на пополнение #11: пользователь lor (ID: 76) запросил 13,00 € через ETH. Заявка доступна в панели управления.', 1, '2026-08-22 22:13:53'),
(100, 'lor', 'dfgdfgdf', 76, 63, 'Заявка на пополнение #11: пользователь lor (ID: 76) запросил 13,00 € через ETH. Заявка доступна в панели управления.', 1, '2026-08-22 22:13:53'),
(101, 'lor', 'spaider3367', 76, 83, 'Заявка на пополнение #11: пользователь lor (ID: 76) запросил 13,00 € через ETH. Заявка доступна в панели управления.', 0, '2026-08-22 22:13:53'),
(102, 'lor', 'fdgdfg', 76, 86, 'Заявка на пополнение #11: пользователь lor (ID: 76) запросил 13,00 € через ETH. Заявка доступна в панели управления.', 0, '2026-08-22 22:13:53'),
(103, 'gfhfghf', 'lor', 60, 76, 'fghfdh', 1, '2026-08-23 00:08:38'),
(104, 'lor', 'fdhfdghd_', 76, 88, 'dgsg', 0, '2026-08-23 02:36:15'),
(105, 'lor', 'gfhfghf', 76, 60, 'Заявка на пополнение #12: пользователь lor (ID: 76) запросил 33,00 € через XRP. Заявка доступна в панели управления.', 1, '2026-08-23 02:53:35'),
(106, 'lor', 'dfgdfgdf', 76, 63, 'Заявка на пополнение #12: пользователь lor (ID: 76) запросил 33,00 € через XRP. Заявка доступна в панели управления.', 1, '2026-08-23 02:53:35'),
(107, 'lor', 'spaider3367', 76, 83, 'Заявка на пополнение #12: пользователь lor (ID: 76) запросил 33,00 € через XRP. Заявка доступна в панели управления.', 0, '2026-08-23 02:53:35'),
(108, 'lor', 'fdgdfg', 76, 86, 'Заявка на пополнение #12: пользователь lor (ID: 76) запросил 33,00 € через XRP. Заявка доступна в панели управления.', 0, '2026-08-23 02:53:35'),
(109, 'dfgdfgdf', 'gfhfghf', 63, 60, 'Заявка на пополнение #13: пользователь dfgdfgdf (ID: 63) запросил 12,00 € через ETH. Заявка доступна в панели управления.', 1, '2026-08-23 10:55:26'),
(110, 'dfgdfgdf', 'lor', 63, 76, 'Заявка на пополнение #13: пользователь dfgdfgdf (ID: 63) запросил 12,00 € через ETH. Заявка доступна в панели управления.', 0, '2026-08-23 10:55:26'),
(111, 'dfgdfgdf', 'spaider3367', 63, 83, 'Заявка на пополнение #13: пользователь dfgdfgdf (ID: 63) запросил 12,00 € через ETH. Заявка доступна в панели управления.', 0, '2026-08-23 10:55:26'),
(112, 'dfgdfgdf', 'fdgdfg', 63, 86, 'Заявка на пополнение #13: пользователь dfgdfgdf (ID: 63) запросил 12,00 € через ETH. Заявка доступна в панели управления.', 0, '2026-08-23 10:55:26'),
(113, 'gfhfghf', 'dfgdfgdf', 60, 63, 'Заявка на пополнение #14: пользователь gfhfghf (ID: 60) запросил 12,00 € через BTC. Заявка доступна в панели управления.', 0, '2026-08-23 15:40:53'),
(114, 'gfhfghf', 'lor', 60, 76, 'Заявка на пополнение #14: пользователь gfhfghf (ID: 60) запросил 12,00 € через BTC. Заявка доступна в панели управления.', 0, '2026-08-23 15:40:53'),
(115, 'gfhfghf', 'spaider3367', 60, 83, 'Заявка на пополнение #14: пользователь gfhfghf (ID: 60) запросил 12,00 € через BTC. Заявка доступна в панели управления.', 0, '2026-08-23 15:40:53'),
(116, 'gfhfghf', 'fdgdfg', 60, 86, 'Заявка на пополнение #14: пользователь gfhfghf (ID: 60) запросил 12,00 € через BTC. Заявка доступна в панели управления.', 0, '2026-08-23 15:40:53'),
(117, 'Администрация', 'dfgdfgdf', 60, 63, 'По вашей заявке на пополнение #13 (12,00 € через ETH) назначен кошелёк для перевода «Основной ETH кошелёк»:\nАдрес: 0x742d35Cc6634C0532925a3b844Bc454e4438f44e\n\nПосле отправки перевода дождитесь подтверждения администратором — баланс зачислится автоматически.', 0, '2026-08-23 15:41:09'),
(118, 'gfhfghf', 'dfgdfgdf', 60, 63, 'Заявка на пополнение #15: пользователь gfhfghf (ID: 60) запросил 1,00 € через XRP. Заявка доступна в панели управления.', 0, '2026-08-23 15:42:42'),
(119, 'gfhfghf', 'lor', 60, 76, 'Заявка на пополнение #15: пользователь gfhfghf (ID: 60) запросил 1,00 € через XRP. Заявка доступна в панели управления.', 0, '2026-08-23 15:42:42'),
(120, 'gfhfghf', 'spaider3367', 60, 83, 'Заявка на пополнение #15: пользователь gfhfghf (ID: 60) запросил 1,00 € через XRP. Заявка доступна в панели управления.', 0, '2026-08-23 15:42:42'),
(121, 'gfhfghf', 'fdgdfg', 60, 86, 'Заявка на пополнение #15: пользователь gfhfghf (ID: 60) запросил 1,00 € через XRP. Заявка доступна в панели управления.', 0, '2026-08-23 15:42:42'),
(123, 'gfhfghf', 'dfgdfgdf', 60, 63, 'Заявка на пополнение #16: пользователь gfhfghf (ID: 60) запросил 33,00 € через BTC. Заявка доступна в панели управления.', 0, '2026-08-23 22:36:18'),
(124, 'gfhfghf', 'lor', 60, 76, 'Заявка на пополнение #16: пользователь gfhfghf (ID: 60) запросил 33,00 € через BTC. Заявка доступна в панели управления.', 0, '2026-08-23 22:36:18'),
(125, 'gfhfghf', 'spaider3367', 60, 83, 'Заявка на пополнение #16: пользователь gfhfghf (ID: 60) запросил 33,00 € через BTC. Заявка доступна в панели управления.', 0, '2026-08-23 22:36:18'),
(126, 'gfhfghf', 'fdgdfg', 60, 86, 'Заявка на пополнение #16: пользователь gfhfghf (ID: 60) запросил 33,00 € через BTC. Заявка доступна в панели управления.', 0, '2026-08-23 22:36:18');

-- --------------------------------------------------------

--
-- Структура таблицы `permissions`
--

CREATE TABLE `permissions` (
  `id` int NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `description`) VALUES
(1, 'manage_users', 'Управление пользователями системы'),
(2, 'manage_shop', 'Полное управление магазином'),
(3, 'moderate_shop', 'Управление контентом магазина'),
(4, 'sell_products', 'Продажа товаров'),
(5, 'buy_products', 'Покупка товаров'),
(6, 'ban_users', 'Блокировка и разблокировка пользователей');

-- --------------------------------------------------------

--
-- Структура таблицы `products`
--

CREATE TABLE `products` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `price`, `created_at`) VALUES
(1, 'фигнюшки', 'мвылодоипргшщрф\r\nпваждфыьполро\r\nждпывошгпргрвы\r\nвлджарыфшгщрпгшщ', 33.00, '2026-08-20 01:29:24');

-- --------------------------------------------------------

--
-- Структура таблицы `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` bigint UNSIGNED NOT NULL,
  `product_id` bigint UNSIGNED NOT NULL,
  `buyer_id` int NOT NULL,
  `rating` tinyint UNSIGNED NOT NULL,
  `review` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `product_reviews`
--

INSERT INTO `product_reviews` (`id`, `product_id`, `buyer_id`, `rating`, `review`, `created_at`) VALUES
(1, 2, 63, 5, 'оавырпопроы', '2026-08-21 23:34:24'),
(2, 15, 76, 5, 'gffgd', '2026-08-21 23:59:29'),
(3, 14, 76, 3, 'hgh', '2026-08-21 23:59:46'),
(4, 13, 76, 5, 'gfd', '2026-08-21 23:59:52'),
(5, 14, 60, 5, 'fgfgdf', '2026-08-22 21:21:49'),
(6, 15, 60, 5, 'dfgdg', '2026-08-22 21:21:53'),
(7, 8, 60, 5, 'аавап', '2026-08-24 09:35:31');

-- --------------------------------------------------------

--
-- Структура таблицы `profiles`
--

CREATE TABLE `profiles` (
  `Number` int NOT NULL,
  `User` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `UserLogin` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `Password` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `Photo` text NOT NULL,
  `Rating` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen` timestamp NULL DEFAULT NULL,
  `is_online` tinyint(1) NOT NULL DEFAULT '0',
  `banned` tinyint(1) NOT NULL DEFAULT '0',
  `wallet_balance` decimal(12,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `profiles`
--

INSERT INTO `profiles` (`Number`, `User`, `UserLogin`, `Password`, `Photo`, `Rating`, `created_at`, `last_seen`, `is_online`, `banned`, `wallet_balance`) VALUES
(1, 'fdgfdghfdhdhdf', NULL, '', 'images/upload_profiles/vvmt4.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 534.00),
(2, 'Елена  Михедова', NULL, '', 'images/upload_profiles/300_29029.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(3, 'fdgdg', NULL, '', 'images/upload_profiles/admin_63646cd5a303d2bde4f05ce2.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(4, 'Дарья  Артамонова', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(5, 'Лена Щетинина', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(6, 'Марина Городецкого', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 3535.00),
(7, 'Вика sexy Самойлова', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(8, 'Вика  Малютина', NULL, '', 'images/upload_profiles/avatar_fd955760de019fea987b2c0f.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(9, 'Кристина Леконцева', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(10, 'Ленка Якубышина', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(11, 'Ясюня Новикова', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(12, 'Викуська  Ефремова', NULL, '', 'images/upload_profiles/FP9116_WWE-John-Cena-Posters.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(13, 'Мариам Юрченкова', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(14, 'Алёнчик Зубакова', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(15, 'Настя  Булатова', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(16, 'Ксения  Улесова', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(17, 'Ольга  Мокриденко', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(18, 'Юля Кочегарова', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(19, 'Тома Мирончик', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(20, 'Валерия Лега', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(21, 'Юлия  Адашева', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(22, 'Даша  Соловьева', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(23, 'Анютка Ильина', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(24, 'Евгения Евдокимова', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(25, 'Катя Самбука', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(26, 'Мила Алыкеева', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(27, 'Регина Марте', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(28, 'Наталья Lisica', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(29, 'Ира  Тихомирова', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(30, 'Александра  Павлий', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(31, 'Елена  Усаевич ', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(32, 'Maria  Chemyakina', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(33, 'Кристинка Шаранкевич', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(34, 'Sanda Sudzumia', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(35, 'Светлана  Васильевна', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(36, 'Оленька (Городецкова)', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(37, 'Lyuba  Popova', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(38, 'Марина Миленькая Ковальчук', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(39, 'MaRiNkA  (Грядовкина)', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(40, 'Вера Гарбаренко', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(41, 'Екатерина  Шубина', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(42, 'Карина  Александровна', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(43, 'Ірчик Кібель', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(44, 'Алина (Таракашка)', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(45, 'Anna Krasnova', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(46, 'Настюша  Елисеева', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(47, 'Алёна  Лемешонок', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(48, 'Полина  Артёменко', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(49, 'Катя  (Комова)', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(50, 'Екатерина Ершакова', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(51, 'Kristusha  Fortynatova', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(52, 'Darya Dmitrieva', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(53, 'Елена Галкина', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(54, 'Татьяна Сычёва', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(55, '|_P_о_Z_i_T_i_F_|', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(56, 'Аня Прохорова', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(57, 'balvanka', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(58, 'Светуля Елисеева', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(59, 'Аня Крюкова', NULL, '', 'images/nophoto.jpg', 0, '2026-08-19 10:07:27', NULL, 0, 0, 0.00),
(60, 'gfhfghf', 'spaider3367', '$2y$12$VmYi5AsNhUtQJdpcwAhsFOvdLJWMxPByMZH5u3oyZDj0W/T.WKEse', 'images/upload_profiles/admin_a7ce3905ac48a9b81371d095.jpg', 10, '2026-08-19 10:07:27', '2026-08-23 08:59:23', 1, 0, 680.98),
(61, 'dsffds', 'dsfsdfds', 'dsdfsgds', 'images/nophoto.jpg', 0, '2026-08-19 13:16:22', NULL, 0, 0, 0.00),
(62, '36645365436', '5465464564', '6456546456', 'images/nophoto.jpg', 0, '2026-08-19 15:07:21', NULL, 0, 0, 0.00),
(63, 'dfgdfgdf', 'fdgdfg', '$2y$12$efM4IOsdGjGIRck2j659Cel3L./3S/DMbFTA.VNTPT0G.NGsXCrHW', 'images/upload_profiles/avatar_de4db9859da537dd5ce73c1b.jpg', 4, '2026-08-19 15:34:22', '2026-08-23 08:59:02', 0, 0, 980.02),
(64, 'Grigoriy', 'spaider3', '$2y$12$9ZxwszsBAoa3/vllERTpueFBTHRrJNH1uOjSwGJndG3b8Ll9EA5LS', 'images/nophoto.jpg', 0, '2026-08-19 15:35:13', '2026-08-19 15:35:13', 1, 0, 0.00),
(65, 'Grigory Retota', 'grigoryretota@gmail.com', '$2y$12$CC2Rkgq6AKqqIp6jOoCbJuHNppVbs8FP1MrAr6tdV3YgcSXn5DGhu', 'images/nophoto.jpg', 0, '2026-08-19 16:00:13', '2026-08-19 16:00:13', 1, 0, 0.00),
(66, '45646456', '6546435645', '$2y$12$9k55U92BeTkSmJVTK6I19eoKMwRoxHL91drVcOJX.Sag9b2FZ2eY2', 'images/nophoto.jpg', 0, '2026-08-19 16:02:57', '2026-08-19 16:02:57', 1, 0, 0.00),
(67, 'max', '12345', '$2y$12$aAoHoKU3h8D4ndk/Ef9xJuU.V6A9rJLPsyISwGZ9zf3gurw/P.jvy', 'images/nophoto.jpg', 0, '2026-08-19 16:18:00', '2026-08-19 16:18:00', 1, 0, 0.00),
(68, '678347468734987', '4363464356', '$2y$12$v1Q/JAcKpJkyl1QGYbMA2emmIJgam6QkKoFUgrMWk2NAL0xWgO/W2', 'images/nophoto.jpg', 0, '2026-08-19 16:29:36', '2026-08-19 16:29:36', 1, 0, 0.00),
(69, 'Grigory Retota', 'fdhhgfff_8', '$2y$12$WT45WcOFx2pBin9rVJXLwOSS.lGaUYoz8UE/sXpQeJ.nAkhfU4dmC', 'images/upload_profiles/a_00dbaa1a.jpg', 0, '2026-08-19 17:11:13', '2026-08-20 11:26:26', 1, 0, 0.00),
(70, '546457', 'Spudi', '$2y$12$6Lf6JYaYzJfAMHtjkNGqYuZap9wPAwJ87KOsINStvVVegbPerN7oS', 'images/nophoto.jpg', 0, '2026-08-19 17:13:59', '2026-08-19 17:13:59', 0, 0, 0.00),
(71, 'Spat', 'ivanka', '$2y$12$aNpKEo04jpMpnXtjMyLBJeZxJdtujak7r1gRsI1oZxgyGvdHrCBPK', 'images/nophoto.jpg', 0, '2026-08-19 17:14:55', '2026-08-20 11:26:18', 0, 0, 0.00),
(72, '656545', 'ignat', '$2y$12$xouS7KhwITxzUsZnqOoMcek8CjI8ntR4qlyYy7HyWMAhKL0hmXLFe', 'images/nophoto.jpg', 0, '2026-08-19 17:18:38', '2026-08-19 17:18:38', 0, 0, 0.00),
(73, '333333', '1234567', '$2y$12$mNQFvlSNC0h/NkGNceeSdONb6SfJ2MBraCJlXZVXxqT.dEOG5Jgd2', 'images/nophoto.jpg', 0, '2026-08-19 17:24:03', '2026-08-19 17:24:51', 1, 0, 0.00),
(74, '4736637', 'kvaka', '$2y$12$w7mUFu8n1z59u8i/4OgruuylEAAc4Cns3SRO.FjnqEIszBajGbCAy', 'images/nophoto.jpg', 0, '2026-08-19 18:27:48', '2026-08-19 18:28:23', 1, 0, 0.00),
(75, 'Grigory Retota', 'grigoryretota6', '$2y$12$ZmIfiHwvmZS.6asx7RdgtuX41nSGUYC4zny0L0LvYl1Xdd512U1/K', 'images/nophoto.jpg', 0, '2026-08-19 19:42:21', '2026-08-19 19:42:21', 0, 0, 0.00),
(76, 'lor', 'cream', '$2y$12$DdoAPj8qKDb8eeAh1C6ug.OeRTXjI/Oval81/iq3sOlnFyyzmWqmq', 'images/upload_profiles/b2efdfcd1d18e7bd4103b2552dc85a83.gif', 7, '2026-08-19 19:56:38', '2026-08-23 00:46:00', 1, 0, 12126.00),
(77, 'Manjac', 'hustler', '$2y$12$8.8q1ZvG17TYLxtTOjkaJuOFXvuNfOS.MpiUaatXbJ5gYe6UXhRai', 'images/nophoto.jpg', 0, '2026-08-19 20:43:27', '2026-08-19 20:53:51', 0, 0, 0.00),
(78, '43534', 'hustler333', '$2y$12$W0ZXH33/lVEjW7hwPlMqe.xtHmoCPDfAPsOfJgPl46hrKOFkNSJLK', 'images/upload_profiles/0slwbogjORBlt3QT79bmihNszjOl9mqmEk5qSEx765dU3-MWJl5uY7XgKwtX7asJvg18V-UJ.jpg', 0, '2026-08-19 20:57:45', '2026-08-19 20:57:45', 0, 0, 0.00),
(79, 'crewcrew', 'sabaka', '$2y$12$GpTELZx.LvCeiyRchCXCcucDsRIBq27MHeZQgvtOAeV34v0ulXB4e', 'images/nophoto.jpg', 0, '2026-08-19 21:08:52', '2026-08-19 21:12:04', 1, 0, 0.00),
(80, 'Grigory Retota', '57475486876487', '$2y$12$6jp67tx5PiWxBcgpxOvZquwNSqqRksh/YRS/aBKh9fiMCiZI1JkPK', 'images/nophoto.jpg', 0, '2026-08-19 23:30:18', '2026-08-19 23:30:18', 0, 0, 0.00),
(81, 'manchik', 'forest', '$2y$12$PEz.94fjKDFfRBdVpg6iWOExXXlgwVkMFz5lAKAT0.4wUG1lxqdGS', 'images/nophoto.jpg', 0, '2026-08-20 03:33:36', '2026-08-20 03:33:36', 0, 0, 0.00),
(82, '5645645', '643556436', '$2y$12$SyKvQyQ.X2XA2TfQui0fHOuTGv1DdNFgrmqwc4X7hoGhDj9vEtbgi', 'images/nophoto.jpg', 0, '2026-08-20 03:44:56', '2026-08-20 03:44:56', 0, 0, 0.00),
(83, 'spaider3367', '199423gf', '$2y$12$i212J18rx4532PO1/m7RZOicfXoBN.4294RgukprEi7qaekQjbGmG', 'images/upload_profiles/2dc114c9ac0e44633aec143f74bd9ce7.jpg', 0, '2026-08-20 11:45:36', '2026-08-20 13:08:28', 0, 0, 0.00),
(84, 'fdgdfg', 'spaider336', '$2y$12$tocVskK5rKTa1CUvvWwJdezB8FYGa7eu7otYJ0Y5i1mFxOhqTmiOK', 'images/nophoto.jpg', 0, '2026-08-20 17:30:12', '2026-08-20 17:30:12', 0, 0, 0.00),
(85, 'fdgdfg', '7777777777777777777', '$2y$12$x.43qc8wahnbo5.o6v6Q5OdP4xAcuitW3jd9CStnmsD2k8tzhKk26', 'images/nophoto.jpg', 0, '2026-08-20 18:08:38', '2026-08-20 18:08:38', 0, 0, 500.00),
(86, 'fdgdfg', '333', '$2y$12$XqPH2rVuyesRO0wAh7CGQuFK/vqIuU5aXd6tag.E6uvqzYHCgTOEu', 'images/upload_profiles/avatar_bf501e830f47b9d33aa07c1a.jpg', 0, '2026-08-20 18:20:43', '2026-08-20 18:29:04', 0, 0, 0.00),
(87, 'spudi', 'gatsga', '$2y$12$YOoIjJDVYaJ9GjE5Cj3ekeZ3netEm/UoZg4jagiin3/otMQYD8BL2', 'images/upload_profiles/admin_5102fbe792ff48c08bfd9f28.jpg', 0, '2026-08-21 08:17:05', '2026-08-21 08:24:22', 0, 0, 168.00),
(88, 'fdhfdghd_', 'spaider3393', '$2y$12$aHqQkng2i6VgxW4aqY5lYOArgrNe/yy.UlBhjbA1NMibl.tVDOlrC', 'images/upload_profiles/avatar_0636cd537afe5cb273154c9e.jpg', 0, '2026-08-21 08:44:02', '2026-08-21 10:51:55', 0, 0, 50174.00),
(89, 'dfjklhgkjhdf', 'spaider3394', '$2y$12$.UTSF3pZuSkBm2OnGnKx2ekm1vtoLtCVu30Jsb7h51.QRx4HR1R4q', 'images/nophoto.jpg', 2, '2026-08-21 10:52:30', '2026-08-22 21:09:36', 0, 0, 34581.00);

-- --------------------------------------------------------

--
-- Структура таблицы `roles`
--

CREATE TABLE `roles` (
  `id` int NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'admin', 'Полный доступ ко всем функциям системы'),
(2, 'moderator', 'Права на управление контентом и пользователями'),
(3, 'user', 'Базовый уровень доступа: продажа и покупка товаров');

-- --------------------------------------------------------

--
-- Структура таблицы `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int NOT NULL,
  `permission_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(2, 3),
(1, 4),
(2, 4),
(3, 4),
(1, 5),
(2, 5),
(3, 5),
(1, 6),
(2, 6);

-- --------------------------------------------------------

--
-- Структура таблицы `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `data` mediumtext NOT NULL,
  `last_activity` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `sessions`
--

INSERT INTO `sessions` (`id`, `data`, `last_activity`) VALUES
('45a4753f7858d2a628dba97d2073b2e7', 'csrf_token|s:64:\"9c3edc22958186d09a06f9f82cb7d975c683d809e8fef11b2ce9e8a75b0e539a\";user_id|i:60;user_name|s:7:\"gfhfghf\";user_login|s:11:\"spaider3367\";logged_in|b:1;cart|a:0:{}', 1787564148);

-- --------------------------------------------------------

--
-- Структура таблицы `shop_orders`
--

CREATE TABLE `shop_orders` (
  `id` bigint UNSIGNED NOT NULL,
  `buyer_id` bigint UNSIGNED NOT NULL,
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` varchar(20) NOT NULL DEFAULT 'new',
  `approved_by` bigint UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approval_note` varchar(255) DEFAULT NULL,
  `payment_provider` varchar(32) DEFAULT NULL,
  `payment_id` varchar(255) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `shop_orders`
--

INSERT INTO `shop_orders` (`id`, `buyer_id`, `total`, `status`, `approved_by`, `approved_at`, `approval_note`, `payment_provider`, `payment_id`, `paid_at`, `created_at`) VALUES
(1, 76, 12.00, 'new', NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-20 03:32:53'),
(2, 76, 4.00, 'new', NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-20 10:50:49'),
(3, 60, 664.00, 'new', NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-20 11:04:14'),
(4, 69, 4.00, 'new', NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-20 11:19:54'),
(5, 76, 4.00, 'new', NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-20 16:01:01'),
(6, 86, 4.00, 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-20 18:23:18'),
(7, 86, 8.00, 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-20 18:27:29'),
(8, 60, 33.00, 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-21 06:29:26'),
(9, 89, 4.00, 'paid', NULL, NULL, NULL, 'wallet', NULL, '2026-08-21 19:11:48', '2026-08-21 19:11:48'),
(10, 60, 67.00, 'paid', NULL, NULL, NULL, 'wallet', NULL, '2026-08-21 19:29:11', '2026-08-21 19:29:11'),
(11, 60, 0.02, 'paid', NULL, NULL, NULL, 'wallet', NULL, '2026-08-21 19:30:54', '2026-08-21 19:30:54'),
(12, 60, 145.00, 'paid', NULL, NULL, NULL, 'wallet', NULL, '2026-08-21 21:15:36', '2026-08-21 21:15:36'),
(13, 60, 1.00, 'paid', NULL, NULL, NULL, 'wallet', NULL, '2026-08-21 21:42:42', '2026-08-21 21:42:42'),
(14, 60, 1.00, 'paid', NULL, NULL, NULL, 'wallet', NULL, '2026-08-21 22:05:27', '2026-08-21 22:05:27'),
(15, 89, 1.00, 'paid', NULL, NULL, NULL, 'wallet', NULL, '2026-08-21 22:10:32', '2026-08-21 22:10:32'),
(16, 76, 67.00, 'paid', NULL, NULL, NULL, 'wallet', NULL, '2026-08-21 22:20:15', '2026-08-21 22:20:15'),
(17, 76, 1.00, 'paid', NULL, NULL, NULL, 'wallet', NULL, '2026-08-21 23:12:54', '2026-08-21 23:12:54'),
(18, 63, 1.00, 'paid', NULL, NULL, NULL, 'wallet', NULL, '2026-08-21 23:25:29', '2026-08-21 23:25:29'),
(19, 63, 4.00, 'paid', NULL, NULL, NULL, 'wallet', NULL, '2026-08-21 23:33:23', '2026-08-21 23:33:23'),
(20, 63, 67.00, 'paid', NULL, NULL, NULL, 'wallet', NULL, '2026-08-22 00:07:27', '2026-08-22 00:07:27'),
(21, 76, 4.00, 'rejected', 76, '2026-08-22 19:29:28', 'не могу продать', NULL, NULL, NULL, '2026-08-22 19:28:57'),
(22, 76, 1.00, 'paid', 76, '2026-08-22 19:46:11', NULL, 'wallet', NULL, '2026-08-22 19:46:11', '2026-08-22 19:45:59'),
(23, 76, 89.00, 'paid', 76, '2026-08-22 20:33:52', 'hi', 'wallet', NULL, '2026-08-22 20:33:52', '2026-08-22 20:33:38'),
(24, 60, 3.00, 'rejected', 60, '2026-08-23 09:27:07', 'рпрп', NULL, NULL, NULL, '2026-08-23 09:15:39'),
(25, 60, 1.00, 'paid', 60, '2026-08-23 13:46:34', NULL, 'wallet', NULL, '2026-08-23 13:46:34', '2026-08-23 09:43:01'),
(26, 60, 34.00, 'pending_approval', NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-23 09:55:41'),
(27, 60, 3.00, 'pending_approval', NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-23 13:46:21'),
(28, 60, 3.00, 'pending_approval', NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-23 14:01:17');

-- --------------------------------------------------------

--
-- Структура таблицы `shop_order_items`
--

CREATE TABLE `shop_order_items` (
  `id` bigint UNSIGNED NOT NULL,
  `order_id` bigint UNSIGNED NOT NULL,
  `product_id` bigint UNSIGNED NOT NULL,
  `seller_id` bigint UNSIGNED NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `shop_order_items`
--

INSERT INTO `shop_order_items` (`id`, `order_id`, `product_id`, `seller_id`, `price`, `quantity`) VALUES
(1, 1, 2, 60, 4.00, 3),
(2, 2, 2, 60, 4.00, 1),
(3, 3, 3, 76, 664.00, 1),
(4, 4, 2, 60, 4.00, 1),
(5, 5, 2, 60, 4.00, 1),
(6, 6, 2, 60, 4.00, 1),
(7, 7, 2, 60, 4.00, 2),
(8, 8, 8, 76, 33.00, 1),
(9, 9, 2, 60, 4.00, 1),
(10, 10, 13, 88, 33.00, 1),
(11, 10, 14, 88, 33.00, 1),
(12, 10, 15, 88, 1.00, 1),
(13, 11, 11, 63, 0.02, 1),
(14, 12, 8, 76, 33.00, 1),
(15, 12, 12, 87, 56.00, 2),
(16, 13, 15, 88, 1.00, 1),
(17, 14, 15, 88, 1.00, 1),
(18, 15, 15, 88, 1.00, 1),
(19, 16, 13, 88, 33.00, 1),
(20, 16, 14, 88, 33.00, 1),
(21, 16, 15, 88, 1.00, 1),
(22, 17, 15, 88, 1.00, 1),
(23, 18, 15, 88, 1.00, 1),
(24, 19, 2, 60, 4.00, 1),
(25, 20, 8, 76, 33.00, 1),
(26, 20, 16, 76, 34.00, 1),
(27, 21, 2, 60, 4.00, 1),
(28, 22, 15, 88, 1.00, 1),
(29, 23, 12, 87, 56.00, 1),
(30, 23, 14, 88, 33.00, 1),
(31, 24, 17, 76, 3.00, 1),
(32, 25, 15, 88, 1.00, 1),
(33, 26, 16, 76, 34.00, 1),
(34, 27, 17, 76, 3.00, 1),
(35, 28, 17, 76, 3.00, 1);

-- --------------------------------------------------------

--
-- Структура таблицы `shop_products`
--

CREATE TABLE `shop_products` (
  `id` bigint UNSIGNED NOT NULL,
  `seller_id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `photo` varchar(250) NOT NULL DEFAULT 'images/nophoto.jpg',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `moderation_status` varchar(20) NOT NULL DEFAULT 'approved',
  `moderated_by` int DEFAULT NULL,
  `moderated_at` timestamp NULL DEFAULT NULL,
  `moderation_note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `shop_products`
--

INSERT INTO `shop_products` (`id`, `seller_id`, `name`, `description`, `price`, `photo`, `is_deleted`, `moderation_status`, `moderated_by`, `moderated_at`, `moderation_note`, `created_at`) VALUES
(2, 60, 'Grigory Retota', 'аврпв\r\nавдьпова\r\nапвдьорав', 4.00, 'images/upload_profiles/shop_60_1787191590_774.jpg', 1, 'approved', NULL, NULL, NULL, '2026-08-20 02:06:30'),
(3, 76, '64564575', 'hfdhfdhf', 664.00, 'images/upload_profiles/shop_76_1787222994_820.png', 1, 'approved', NULL, NULL, NULL, '2026-08-20 10:49:54'),
(5, 83, 'апвррв', 'аврар', 5.00, 'images/upload_profiles/shop_83_1787228875_824.png', 1, 'approved', NULL, NULL, NULL, '2026-08-20 12:27:55'),
(8, 76, 'fghdhf', 'gfhfh', 33.00, 'images/upload_profiles/shop_fd5ab3b8b9de85ea3927bed8.jpg', 0, 'approved', NULL, NULL, NULL, '2026-08-21 06:19:09'),
(11, 63, 'rwerwe', 'rewre', 0.02, 'images/upload_profiles/shop_1a88a50f029a0f68e3b7fa2e.jpg', 0, 'approved', NULL, NULL, NULL, '2026-08-21 07:09:03'),
(12, 87, 'gfdg', 'gfdhh', 56.00, 'images/shop_products/shop_0088966039b83cef891e8dbd69704c2b.jpg', 0, 'approved', NULL, NULL, NULL, '2026-08-21 08:19:23'),
(13, 88, 'gdgret', 'гривны', 33.00, 'images/shop_products/shop_61f8f17ece952d32e6729d7a914fdae1.jpg', 0, 'approved', NULL, NULL, NULL, '2026-08-21 08:45:45'),
(14, 88, 'gdgret', 'гривны', 33.00, 'images/shop_products/shop_f1feca2a5e6367e0ad6acfa5db6b1d66.jpg', 0, 'approved', NULL, NULL, NULL, '2026-08-21 08:53:46'),
(15, 88, 'ukstaz', 'tebls', 1.00, 'images/shop_products/shop_46694b4169bc3d992bf3aa64ed573d4c.jpg', 0, 'approved', NULL, NULL, NULL, '2026-08-21 10:50:26'),
(16, 76, 'авпвап', 'апввпв', 34.00, 'images/shop_products/shop_7f87210b3691fcdecc1fc5933dd6bf73.jpg', 0, 'approved', NULL, NULL, NULL, '2026-08-21 23:37:04'),
(17, 76, '33gf', 'fgfdg', 3.00, 'images/shop_products/shop_c55aacd3fe57a9022fefe98b96484efd.jpg', 0, 'approved', 60, '2026-08-22 21:20:47', NULL, '2026-08-22 20:57:20'),
(18, 76, 'dggdfg', 'dfffdhs', 34.00, 'images/shop_products/shop_d7703375fbcd62fea432220a5b6a3de9.png', 0, 'rejected', 60, '2026-08-22 21:20:49', NULL, '2026-08-22 21:12:22');

-- --------------------------------------------------------

--
-- Структура таблицы `site_modules`
--

CREATE TABLE `site_modules` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(64) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `site_modules`
--

INSERT INTO `site_modules` (`id`, `name`, `enabled`, `updated_at`) VALUES
(1, 'bitcoinrpc', 0, '2026-08-21 18:05:40');

-- --------------------------------------------------------

--
-- Структура таблицы `site_settings`
--

CREATE TABLE `site_settings` (
  `setting_key` varchar(64) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `site_settings`
--

INSERT INTO `site_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('login_throttle_enabled', '0', '2026-08-22 09:54:26'),
('site_name', 'Shop', '2026-08-22 21:58:32');

-- --------------------------------------------------------

--
-- Структура таблицы `topup_requests`
--

CREATE TABLE `topup_requests` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` int NOT NULL,
  `amount_eur` decimal(12,2) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `wallet_id` bigint UNSIGNED DEFAULT NULL,
  `txid` varchar(255) DEFAULT NULL,
  `handled_by` int DEFAULT NULL,
  `handled_at` timestamp NULL DEFAULT NULL,
  `credited_by` int DEFAULT NULL,
  `credited_at` timestamp NULL DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `topup_requests`
--

INSERT INTO `topup_requests` (`id`, `user_id`, `amount_eur`, `currency`, `status`, `wallet_id`, `txid`, `handled_by`, `handled_at`, `credited_by`, `credited_at`, `note`, `created_at`) VALUES
(1, 60, 6000.00, 'BTC', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-21 19:30:08'),
(2, 60, 33333.00, 'XRP', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-21 20:18:09'),
(3, 60, 444.00, 'BTC', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-21 20:44:07'),
(4, 76, 6666.00, 'ETH', 'credited', 2, '355436455645', 60, '2026-08-21 21:19:34', 60, '2026-08-21 21:44:52', NULL, '2026-08-21 21:13:04'),
(5, 63, 443.00, 'BTC', 'credited', 1, '3555553', 63, '2026-08-21 23:28:38', 63, '2026-08-21 23:28:51', NULL, '2026-08-21 23:21:50'),
(6, 76, 5435.00, 'ETH', 'credited', 2, '543', 63, '2026-08-21 23:28:13', 63, '2026-08-21 23:28:46', NULL, '2026-08-21 23:27:32'),
(7, 63, 54.00, 'XRP', 'credited', 3, '4535', 76, '2026-08-22 19:28:20', 76, '2026-08-22 20:21:58', 'djn gfd', '2026-08-21 23:35:24'),
(8, 76, 4.00, 'ETH', 'credited', 2, '436645', 76, '2026-08-22 20:21:38', 60, '2026-08-22 21:21:15', NULL, '2026-08-21 23:48:04'),
(9, 89, 1.00, 'BTC', 'credited', 1, '65465465', 76, '2026-08-22 20:21:35', 60, '2026-08-22 21:21:17', NULL, '2026-08-22 00:04:05'),
(10, 76, 33.00, 'XRP', 'credited', 3, '456456', 76, '2026-08-22 20:21:32', 60, '2026-08-22 21:21:19', NULL, '2026-08-22 19:30:19'),
(11, 76, 13.00, 'ETH', 'credited', 2, '45646', 76, '2026-08-22 20:21:28', 60, '2026-08-22 21:21:21', NULL, '2026-08-22 20:13:53'),
(12, 76, 33.00, 'XRP', 'credited', 3, '54353', 63, '2026-08-23 08:55:36', 60, '2026-08-23 13:39:08', NULL, '2026-08-23 00:53:35'),
(13, 63, 12.00, 'ETH', 'wallet_sent', 2, NULL, 60, '2026-08-23 13:41:09', NULL, NULL, NULL, '2026-08-23 08:55:26'),
(14, 60, 12.00, 'BTC', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-23 13:40:53'),
(15, 60, 1.00, 'XRP', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '554', '2026-08-23 13:42:42'),
(16, 60, 33.00, 'BTC', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-23 20:36:18');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `role_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `role_id`, `created_at`) VALUES
(1, 'user_1', 3, '2026-08-21 18:24:15'),
(2, 'user_2', 3, '2026-08-21 18:24:15'),
(3, 'user_3', 3, '2026-08-21 18:24:15'),
(4, 'user_4', 3, '2026-08-21 18:24:15'),
(5, 'user_5', 3, '2026-08-21 18:24:15'),
(6, 'user_6', 3, '2026-08-21 18:24:15'),
(7, 'user_7', 3, '2026-08-21 18:24:15'),
(8, 'user_8', 3, '2026-08-21 18:24:15'),
(9, 'user_9', 3, '2026-08-21 18:24:15'),
(10, 'user_10', 3, '2026-08-21 18:24:15'),
(11, 'user_11', 3, '2026-08-21 18:24:15'),
(12, 'user_12', 3, '2026-08-21 18:24:15'),
(13, 'user_13', 3, '2026-08-21 18:24:15'),
(14, 'user_14', 3, '2026-08-21 18:24:15'),
(15, 'user_15', 3, '2026-08-21 18:24:15'),
(16, 'user_16', 3, '2026-08-21 18:24:15'),
(17, 'user_17', 3, '2026-08-21 18:24:15'),
(18, 'user_18', 3, '2026-08-21 18:24:15'),
(19, 'user_19', 3, '2026-08-21 18:24:15'),
(20, 'user_20', 3, '2026-08-21 18:24:15'),
(21, 'user_21', 3, '2026-08-21 18:24:15'),
(22, 'user_22', 3, '2026-08-21 18:24:15'),
(23, 'user_23', 3, '2026-08-21 18:24:15'),
(24, 'user_24', 3, '2026-08-21 18:24:15'),
(25, 'user_25', 3, '2026-08-21 18:24:15'),
(26, 'user_26', 3, '2026-08-21 18:24:15'),
(27, 'user_27', 3, '2026-08-21 18:24:15'),
(28, 'user_28', 3, '2026-08-21 18:24:15'),
(29, 'user_29', 3, '2026-08-21 18:24:15'),
(30, 'user_30', 3, '2026-08-21 18:24:15'),
(31, 'user_31', 3, '2026-08-21 18:24:15'),
(32, 'user_32', 3, '2026-08-21 18:24:15'),
(33, 'user_33', 3, '2026-08-21 18:24:15'),
(34, 'user_34', 3, '2026-08-21 18:24:15'),
(35, 'user_35', 3, '2026-08-21 18:24:15'),
(36, 'user_36', 3, '2026-08-21 18:24:15'),
(37, 'user_37', 3, '2026-08-21 18:24:15'),
(38, 'user_38', 3, '2026-08-21 18:24:15'),
(39, 'user_39', 3, '2026-08-21 18:24:15'),
(40, 'user_40', 3, '2026-08-21 18:24:15'),
(41, 'user_41', 3, '2026-08-21 18:24:15'),
(42, 'user_42', 3, '2026-08-21 18:24:15'),
(43, 'user_43', 3, '2026-08-21 18:24:15'),
(44, 'user_44', 3, '2026-08-21 18:24:15'),
(45, 'user_45', 3, '2026-08-21 18:24:15'),
(46, 'user_46', 3, '2026-08-21 18:24:15'),
(47, 'user_47', 3, '2026-08-21 18:24:15'),
(48, 'user_48', 3, '2026-08-21 18:24:15'),
(49, 'user_49', 3, '2026-08-21 18:24:15'),
(50, 'user_50', 3, '2026-08-21 18:24:15'),
(51, 'user_51', 3, '2026-08-21 18:24:15'),
(52, 'user_52', 3, '2026-08-21 18:24:15'),
(53, 'user_53', 3, '2026-08-21 18:24:15'),
(54, 'user_54', 3, '2026-08-21 18:24:15'),
(55, 'user_55', 3, '2026-08-21 18:24:15'),
(56, 'user_56', 3, '2026-08-21 18:24:15'),
(57, 'user_57', 3, '2026-08-21 18:24:15'),
(58, '', 3, '2026-08-20 01:01:09'),
(59, 'user_59', 3, '2026-08-21 18:24:15'),
(60, 'spaider336', 1, '2026-08-20 01:01:09'),
(61, 'dsfsdfds', 3, '2026-08-20 01:01:09'),
(62, '5465464564', 3, '2026-08-20 01:01:09'),
(63, 'fdgdfg', 2, '2026-08-20 01:01:09'),
(64, 'spaider3', 3, '2026-08-20 01:01:09'),
(65, 'grigoryretota@gmail.com', 3, '2026-08-20 01:01:09'),
(66, '6546435645', 3, '2026-08-20 01:01:09'),
(67, '12345', 3, '2026-08-20 01:01:09'),
(68, '4363464356', 3, '2026-08-20 01:01:09'),
(69, 'fdhhgfff_8', 3, '2026-08-20 01:01:09'),
(70, 'Spudi', 3, '2026-08-20 01:01:09'),
(71, 'ivanka', 3, '2026-08-20 01:01:09'),
(72, 'ignat', 3, '2026-08-20 01:01:09'),
(73, '1234567', 3, '2026-08-20 01:01:09'),
(74, 'kvaka', 3, '2026-08-20 01:01:09'),
(75, 'grigoryretota6', 3, '2026-08-20 01:01:09'),
(76, 'cream', 2, '2026-08-20 01:01:09'),
(77, 'hustler', 3, '2026-08-20 01:01:09'),
(78, 'hustler333', 3, '2026-08-20 01:01:09'),
(79, 'sabaka', 3, '2026-08-20 01:01:09'),
(80, '57475486876487', 3, '2026-08-20 01:01:09'),
(81, 'forest', 3, '2026-08-20 03:33:36'),
(82, '643556436', 3, '2026-08-20 03:44:56'),
(83, '199423gf', 2, '2026-08-20 11:45:36'),
(84, 'user_84', 3, '2026-08-21 18:24:15'),
(85, '7777777777777777777', 3, '2026-08-20 18:08:38'),
(86, '333', 2, '2026-08-20 18:20:43'),
(87, 'gatsga', 3, '2026-08-21 08:17:05'),
(88, 'spaider3393', 3, '2026-08-21 08:44:02'),
(89, 'spaider3394', 3, '2026-08-21 10:52:30');

-- --------------------------------------------------------

--
-- Структура таблицы `wallets`
--

CREATE TABLE `wallets` (
  `id` int NOT NULL,
  `currency` varchar(10) NOT NULL,
  `label` varchar(255) NOT NULL,
  `address` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `wallets`
--

INSERT INTO `wallets` (`id`, `currency`, `label`, `address`) VALUES
(1, 'BTC', 'Основной BTC кошелёк', 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh'),
(2, 'ETH', 'Основной ETH кошелёк', '0x742d35Cc6634C0532925a3b844Bc454e4438f44e'),
(3, 'XRP', 'Основной XRP кошелёк', 'rHb9CJAWyB4rj91VRWn96DkukG4bwdtyTh');

-- --------------------------------------------------------

--
-- Структура таблицы `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` int NOT NULL,
  `actor_id` int DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `balance_after` decimal(12,2) NOT NULL,
  `type` varchar(32) NOT NULL,
  `related_order_id` bigint UNSIGNED DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `wallet_transactions`
--

INSERT INTO `wallet_transactions` (`id`, `user_id`, `actor_id`, `amount`, `balance_after`, `type`, `related_order_id`, `note`, `created_at`) VALUES
(1, 60, 60, 333.00, 333.00, 'top_up', NULL, 'hi', '2026-08-21 06:44:54'),
(2, 63, 63, 555.00, 555.00, 'top_up', NULL, NULL, '2026-08-21 07:43:47'),
(3, 85, NULL, 500.00, 500.00, 'top_up', NULL, 'fghhfg', '2026-08-21 08:59:38'),
(4, 88, 63, 50000.00, 50000.00, 'top_up', NULL, NULL, '2026-08-21 09:02:26'),
(5, 89, 63, 50.00, 50.00, 'top_up', NULL, 'fghdf', '2026-08-21 10:56:22'),
(6, 89, 89, -4.00, 46.00, 'purchase', 9, 'Оплата заказа #9', '2026-08-21 19:11:48'),
(7, 60, 89, 4.00, 337.00, 'sale', 9, 'Начисление за заказ #9', '2026-08-21 19:11:48'),
(8, 60, 60, -67.00, 270.00, 'purchase', 10, 'Оплата заказа #10', '2026-08-21 19:29:11'),
(9, 88, 60, 67.00, 50067.00, 'sale', 10, 'Начисление за заказ #10', '2026-08-21 19:29:11'),
(10, 60, 60, -0.02, 269.98, 'purchase', 11, 'Оплата заказа #11', '2026-08-21 19:30:54'),
(11, 63, 60, 0.02, 555.02, 'sale', 11, 'Начисление за заказ #11', '2026-08-21 19:30:54'),
(12, 60, 60, 6000.00, 6269.98, 'crypto_top_up', NULL, '[АННУЛИРОВАНО, self-approval] Crypto BTC, txid: 333', '2026-08-21 20:17:38'),
(13, 60, 60, 33333.00, 39602.98, 'crypto_top_up', NULL, '[АННУЛИРОВАНО, self-approval] Crypto XRP, txid: 12', '2026-08-21 20:37:06'),
(14, 60, 60, -145.00, 39457.98, 'purchase', 12, 'Оплата заказа #12', '2026-08-21 21:15:36'),
(15, 76, 60, 33.00, 33.00, 'sale', 12, 'Начисление за заказ #12', '2026-08-21 21:15:36'),
(16, 87, 60, 112.00, 112.00, 'sale', 12, 'Начисление за заказ #12', '2026-08-21 21:15:36'),
(17, 60, 60, -1.00, 39456.98, 'purchase', 13, 'Оплата заказа #13', '2026-08-21 21:42:42'),
(18, 88, 60, 1.00, 50068.00, 'sale', 13, 'Начисление за заказ #13', '2026-08-21 21:42:42'),
(19, 60, 60, 444.00, 39900.98, 'crypto_top_up', NULL, '[АННУЛИРОВАНО, self-approval] Crypto BTC, txid: 543534', '2026-08-21 21:44:48'),
(20, 76, 60, 6666.00, 6699.00, 'crypto_top_up', NULL, 'Crypto ETH, txid: 355436455645', '2026-08-21 21:44:52'),
(21, 60, 60, -1.00, 39899.98, 'purchase', 14, 'Оплата заказа #14', '2026-08-21 22:05:27'),
(22, 88, 60, 1.00, 50069.00, 'sale', 14, 'Начисление за заказ #14', '2026-08-21 22:05:27'),
(23, 60, 60, 555.00, 40454.98, 'top_up', NULL, NULL, '2026-08-21 22:07:10'),
(24, 1, 60, 534.00, 534.00, 'top_up', NULL, NULL, '2026-08-21 22:07:26'),
(25, 89, 60, 34535.00, 34581.00, 'top_up', NULL, NULL, '2026-08-21 22:07:30'),
(26, 6, 60, 3535.00, 3535.00, 'top_up', NULL, NULL, '2026-08-21 22:07:34'),
(27, 89, 89, -1.00, 34580.00, 'purchase', 15, 'Оплата заказа #15', '2026-08-21 22:10:32'),
(28, 88, 89, 1.00, 50070.00, 'sale', 15, 'Начисление за заказ #15', '2026-08-21 22:10:32'),
(29, 76, 76, -67.00, 6632.00, 'purchase', 16, 'Оплата заказа #16', '2026-08-21 22:20:15'),
(30, 88, 76, 67.00, 50137.00, 'sale', 16, 'Начисление за заказ #16', '2026-08-21 22:20:15'),
(31, 76, 76, -1.00, 6631.00, 'purchase', 17, 'Оплата заказа #17', '2026-08-21 23:12:54'),
(32, 88, 76, 1.00, 50138.00, 'sale', 17, 'Начисление за заказ #17', '2026-08-21 23:12:54'),
(33, 63, 63, -1.00, 554.02, 'purchase', 18, 'Оплата заказа #18', '2026-08-21 23:25:29'),
(34, 88, 63, 1.00, 50139.00, 'sale', 18, 'Начисление за заказ #18', '2026-08-21 23:25:29'),
(35, 76, 63, 5435.00, 12066.00, 'crypto_top_up', NULL, 'Crypto ETH, txid: 543', '2026-08-21 23:28:46'),
(36, 63, 63, 443.00, 997.02, 'crypto_top_up', NULL, 'Crypto BTC, txid: 3555553', '2026-08-21 23:28:51'),
(37, 63, 63, -4.00, 993.02, 'purchase', 19, 'Оплата заказа #19', '2026-08-21 23:33:23'),
(38, 60, 63, 4.00, 40458.98, 'sale', 19, 'Начисление за заказ #19', '2026-08-21 23:33:23'),
(39, 63, 63, -67.00, 926.02, 'purchase', 20, 'Оплата заказа #20', '2026-08-22 00:07:27'),
(40, 76, 63, 67.00, 12133.00, 'sale', 20, 'Начисление за заказ #20', '2026-08-22 00:07:27'),
(41, 76, 76, -1.00, 12132.00, 'purchase', 22, 'Одобренная покупка #22', '2026-08-22 19:46:11'),
(42, 88, 76, 1.00, 50140.00, 'sale', 22, 'Продажа по одобренному заказу #22', '2026-08-22 19:46:11'),
(43, 63, 76, 54.00, 980.02, 'crypto_top_up', NULL, 'Crypto XRP, txid: 4535', '2026-08-22 20:21:58'),
(44, 76, 76, -89.00, 12043.00, 'purchase', 23, 'Одобренная покупка #23', '2026-08-22 20:33:52'),
(45, 87, 76, 56.00, 168.00, 'sale', 23, 'Продажа по одобренному заказу #23', '2026-08-22 20:33:52'),
(46, 88, 76, 33.00, 50173.00, 'sale', 23, 'Продажа по одобренному заказу #23', '2026-08-22 20:33:52'),
(47, 76, 60, 4.00, 12047.00, 'crypto_top_up', NULL, 'Crypto ETH, txid: 436645', '2026-08-22 21:21:15'),
(48, 89, 60, 1.00, 34581.00, 'crypto_top_up', NULL, 'Crypto BTC, txid: 65465465', '2026-08-22 21:21:17'),
(49, 76, 60, 33.00, 12080.00, 'crypto_top_up', NULL, 'Crypto XRP, txid: 456456', '2026-08-22 21:21:19'),
(50, 76, 60, 13.00, 12093.00, 'crypto_top_up', NULL, 'Crypto ETH, txid: 45646', '2026-08-22 21:21:21'),
(51, 76, 60, 33.00, 12126.00, 'crypto_top_up', NULL, 'Crypto XRP, txid: 54353', '2026-08-23 13:39:08'),
(52, 60, 60, -1.00, 40457.98, 'purchase', 25, 'Одобренная покупка #25', '2026-08-23 13:46:34'),
(53, 88, 60, 1.00, 50174.00, 'sale', 25, 'Продажа по одобренному заказу #25', '2026-08-23 13:46:34'),
(54, 60, 60, 1.00, 40458.98, 'crypto_top_up', NULL, '[АННУЛИРОВАНО, self-approval] Crypto XRP, txid: 543', '2026-08-23 13:57:24'),
(55, 60, NULL, -39778.00, 680.98, 'correction', NULL, 'Откат 4 самоодобренных пополнений (id заявок 1,2,3,15) — self-approval, исправлено вручную', '2026-08-24 09:25:58');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `admin_account`
--
ALTER TABLE `admin_account`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id_comment`),
  ADD KEY `idx_comments_commenter_id` (`commenter_id`);

--
-- Индексы таблицы `crypto_wallets`
--
ALTER TABLE `crypto_wallets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_crypto_wallets_currency_active` (`currency`,`is_active`);

--
-- Индексы таблицы `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_attempts_lookup` (`scope`,`identifier`,`created_at`),
  ADD KEY `idx_login_attempts_ip` (`scope`,`ip`,`created_at`);

--
-- Индексы таблицы `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_messages_sender_id` (`sender_id`),
  ADD KEY `idx_messages_receiver_id` (`receiver_id`),
  ADD KEY `idx_messages_sender_receiver` (`sender_id`,`receiver_id`,`created_at`),
  ADD KEY `idx_messages_receiver_unread` (`receiver_id`,`is_read`,`sender_id`);

--
-- Индексы таблицы `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Индексы таблицы `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_product_reviews_product_buyer` (`product_id`,`buyer_id`),
  ADD KEY `idx_product_reviews_buyer` (`buyer_id`),
  ADD KEY `fk_product_reviews_product` (`product_id`);

--
-- Индексы таблицы `profiles`
--
ALTER TABLE `profiles`
  ADD PRIMARY KEY (`Number`),
  ADD UNIQUE KEY `uniq_profiles_userlogin` (`UserLogin`);

--
-- Индексы таблицы `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Индексы таблицы `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Индексы таблицы `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sessions_last_activity` (`last_activity`);

--
-- Индексы таблицы `shop_orders`
--
ALTER TABLE `shop_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shop_orders_buyer` (`buyer_id`),
  ADD KEY `idx_shop_orders_buyer_status` (`buyer_id`,`status`),
  ADD KEY `idx_shop_orders_payment_id` (`payment_id`);

--
-- Индексы таблицы `shop_order_items`
--
ALTER TABLE `shop_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shop_order_items_order` (`order_id`),
  ADD KEY `idx_shop_order_items_seller` (`seller_id`),
  ADD KEY `fk_shop_order_items_product` (`product_id`);

--
-- Индексы таблицы `shop_products`
--
ALTER TABLE `shop_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shop_products_seller` (`seller_id`);

--
-- Индексы таблицы `site_modules`
--
ALTER TABLE `site_modules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_site_modules_name` (`name`);

--
-- Индексы таблицы `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Индексы таблицы `topup_requests`
--
ALTER TABLE `topup_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_topup_requests_user_status` (`user_id`,`status`),
  ADD KEY `idx_topup_requests_status_created` (`status`,`created_at`),
  ADD KEY `fk_topup_requests_wallet` (`wallet_id`),
  ADD KEY `fk_topup_requests_handled_by` (`handled_by`),
  ADD KEY `fk_topup_requests_credited_by` (`credited_by`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `role_id` (`role_id`);

--
-- Индексы таблицы `wallets`
--
ALTER TABLE `wallets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `address` (`address`);

--
-- Индексы таблицы `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wallet_transactions_user` (`user_id`,`created_at`),
  ADD KEY `idx_wallet_transactions_actor` (`actor_id`),
  ADD KEY `idx_wallet_transactions_order` (`related_order_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `comments`
--
ALTER TABLE `comments`
  MODIFY `id_comment` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=156;

--
-- AUTO_INCREMENT для таблицы `crypto_wallets`
--
ALTER TABLE `crypto_wallets`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT для таблицы `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

--
-- AUTO_INCREMENT для таблицы `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `profiles`
--
ALTER TABLE `profiles`
  MODIFY `Number` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT для таблицы `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `shop_orders`
--
ALTER TABLE `shop_orders`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT для таблицы `shop_order_items`
--
ALTER TABLE `shop_order_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT для таблицы `shop_products`
--
ALTER TABLE `shop_products`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT для таблицы `site_modules`
--
ALTER TABLE `site_modules`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `topup_requests`
--
ALTER TABLE `topup_requests`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT для таблицы `wallets`
--
ALTER TABLE `wallets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `fk_product_reviews_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `profiles` (`Number`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_product_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `shop_order_items`
--
ALTER TABLE `shop_order_items`
  ADD CONSTRAINT `fk_shop_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE RESTRICT;

--
-- Ограничения внешнего ключа таблицы `topup_requests`
--
ALTER TABLE `topup_requests`
  ADD CONSTRAINT `fk_topup_requests_credited_by` FOREIGN KEY (`credited_by`) REFERENCES `profiles` (`Number`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_topup_requests_handled_by` FOREIGN KEY (`handled_by`) REFERENCES `profiles` (`Number`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_topup_requests_user` FOREIGN KEY (`user_id`) REFERENCES `profiles` (`Number`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_topup_requests_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `crypto_wallets` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Ограничения внешнего ключа таблицы `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD CONSTRAINT `fk_wallet_transactions_actor` FOREIGN KEY (`actor_id`) REFERENCES `profiles` (`Number`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_wallet_transactions_order` FOREIGN KEY (`related_order_id`) REFERENCES `shop_orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_wallet_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `profiles` (`Number`) ON DELETE RESTRICT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
