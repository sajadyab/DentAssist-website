-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 31, 2026 at 08:25 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dental_clinic`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `duration` int(11) DEFAULT 30,
  `end_time` time GENERATED ALWAYS AS (addtime(`appointment_time`,sec_to_time(`duration` * 60))) VIRTUAL,
  `treatment_type` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `chair_number` int(11) DEFAULT NULL,
  `status` enum('scheduled','checked-in','in-treatment','completed','cancelled','no-show','follow-up') DEFAULT 'scheduled',
  `cancellation_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `reminder_sent_48h` tinyint(1) DEFAULT 0,
  `reminder_sent_24h` tinyint(1) DEFAULT 0,
  `reminder_sent_at` timestamp NULL DEFAULT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_id`, `doctor_id`, `appointment_date`, `appointment_time`, `duration`, `treatment_type`, `description`, `chair_number`, `status`, `cancellation_reason`, `notes`, `reminder_sent_48h`, `reminder_sent_24h`, `reminder_sent_at`, `invoice_id`, `created_at`, `updated_at`, `created_by`) VALUES
(2, 2, 1, '2026-03-01', '10:00:00', 30, 'Filling', NULL, 1, 'completed', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(3, 3, 1, '2026-03-01', '11:00:00', 30, 'Root Canal', NULL, 2, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(4, 4, 1, '2026-03-02', '09:00:00', 30, 'Extraction', NULL, 1, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(5, 5, 1, '2026-03-02', '10:00:00', 30, 'Whitening', NULL, 2, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(6, 6, 1, '2026-03-02', '11:00:00', 30, 'Crown', NULL, 1, 'completed', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(7, 7, 1, '2026-03-03', '09:00:00', 30, 'Cleaning', NULL, 1, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(8, 8, 1, '2026-03-03', '10:00:00', 30, 'Filling', NULL, 2, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(9, 9, 1, '2026-03-03', '11:00:00', 30, 'Root Canal', NULL, 1, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(10, 10, 1, '2026-03-04', '09:00:00', 30, 'Extraction', NULL, 2, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(11, 11, 1, '2026-03-04', '10:00:00', 30, 'Whitening', NULL, 1, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(12, 12, 1, '2026-03-04', '11:00:00', 30, 'Crown', NULL, 2, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(13, 13, 1, '2026-03-05', '09:00:00', 30, 'Cleaning', NULL, 1, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(14, 14, 1, '2026-03-05', '10:00:00', 30, 'Filling', NULL, 2, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(15, 15, 1, '2026-03-05', '11:00:00', 30, 'Root Canal', NULL, 1, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(16, 16, 1, '2026-03-06', '09:00:00', 30, 'Extraction', NULL, 2, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(17, 17, 1, '2026-03-06', '10:00:00', 30, 'Whitening', NULL, 1, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(18, 18, 1, '2026-03-06', '11:00:00', 30, 'Crown', NULL, 2, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(19, 19, 1, '2026-03-07', '09:00:00', 30, 'Cleaning', NULL, 1, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(20, 20, 1, '2026-03-07', '10:00:00', 30, 'Filling', NULL, 2, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(21, 21, 1, '2026-03-07', '11:00:00', 30, 'Root Canal', NULL, 1, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(22, 22, 1, '2026-03-08', '09:00:00', 30, 'Extraction', NULL, 2, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(23, 23, 1, '2026-03-08', '10:00:00', 30, 'Whitening', NULL, 1, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(24, 24, 1, '2026-03-08', '11:00:00', 30, 'Crown', NULL, 2, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(25, 25, 1, '2026-03-09', '09:00:00', 30, 'Cleaning', NULL, 1, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(26, 27, 26, '2026-03-26', '18:21:00', 30, 'Filling', '', 0, 'scheduled', NULL, '', 0, 0, NULL, NULL, '2026-03-25 11:21:23', '2026-03-25 11:21:23', 26),
(27, 27, 26, '2026-03-29', '11:45:00', 30, 'Cleaning', '', 0, 'scheduled', NULL, '', 0, 1, '2026-03-28 09:49:07', NULL, '2026-03-28 09:45:46', '2026-03-28 09:49:07', 26),
(28, 28, 1, '2026-03-29', '13:50:00', 30, 'Cleaning', '', 0, 'scheduled', NULL, '', 0, 1, '2026-03-28 09:49:07', NULL, '2026-03-28 09:48:53', '2026-03-28 09:49:07', 26),
(29, 28, 32, '2026-04-03', '10:30:00', 45, 'Crown', '', 3, 'scheduled', NULL, '', 0, 0, NULL, NULL, '2026-03-28 17:49:30', '2026-03-28 17:49:30', 32),
(30, 29, 26, '2026-03-31', '16:17:00', 30, 'Filling', 'nothing', 0, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-30 11:16:38', '2026-03-30 11:16:38', 33),
(31, 29, 1, '2026-04-02', '18:20:00', 30, 'Whitening', '', 0, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-30 11:17:09', '2026-03-30 11:17:09', 33),
(32, 29, 32, '2026-04-02', '14:43:00', 30, 'Crown', '', 0, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-30 11:37:20', '2026-03-30 11:37:20', 33),
(33, 29, 32, '2026-04-03', '17:36:00', 30, '🦷 Extraction', '', 0, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-30 14:32:36', '2026-03-30 14:32:36', 33),
(34, 29, 1, '2026-04-09', '17:40:00', 30, '🦷 Extraction', '', 0, 'scheduled', NULL, NULL, 0, 0, NULL, NULL, '2026-03-30 14:36:52', '2026-03-30 14:36:52', 33);

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `performed_at`) VALUES
(1, 1, 'INSERT', 'patients', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(2, 1, 'INSERT', 'appointments', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(3, 2, 'INSERT', 'invoices', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(4, 2, 'UPDATE', 'patients', 2, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(5, 1, 'INSERT', 'treatment_plans', 3, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(6, 1, 'UPDATE', 'appointments', 2, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(7, 2, 'INSERT', 'inventory', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(8, 2, 'INSERT', 'inventory_transactions', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(9, 1, 'INSERT', 'xrays', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(10, 2, 'INSERT', 'payments', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(11, 1, 'UPDATE', 'tooth_chart', 3, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(12, 2, 'INSERT', 'notifications', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(13, 1, 'INSERT', 'treatment_steps', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(14, 2, 'UPDATE', 'invoices', 2, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(15, 1, 'INSERT', 'waiting_queue', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(16, 2, 'INSERT', 'patients', 5, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(17, 1, 'UPDATE', 'appointments', 5, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(18, 2, 'INSERT', 'inventory', 5, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(19, 1, 'INSERT', 'xrays', 5, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(20, 2, 'UPDATE', 'payments', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(21, 1, 'INSERT', 'treatment_plans', 10, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(22, 2, 'INSERT', 'notifications', 10, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(23, 1, 'UPDATE', 'patients', 10, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(24, 2, 'INSERT', 'audit_log', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(25, 1, 'UPDATE', 'invoices', 6, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(26, 1, 'INSERT', 'patients', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(27, 1, 'INSERT', 'appointments', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(28, 2, 'INSERT', 'invoices', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(29, 2, 'UPDATE', 'patients', 2, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(30, 1, 'INSERT', 'treatment_plans', 3, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(31, 1, 'UPDATE', 'appointments', 2, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(32, 2, 'INSERT', 'inventory', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(33, 2, 'INSERT', 'inventory_transactions', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(34, 1, 'INSERT', 'xrays', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(35, 2, 'INSERT', 'payments', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(36, 1, 'UPDATE', 'tooth_chart', 3, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(37, 2, 'INSERT', 'notifications', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(38, 1, 'INSERT', 'treatment_steps', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(39, 2, 'UPDATE', 'invoices', 2, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(40, 1, 'INSERT', 'waiting_queue', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(41, 2, 'INSERT', 'patients', 5, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(42, 1, 'UPDATE', 'appointments', 5, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(43, 2, 'INSERT', 'inventory', 5, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(44, 1, 'INSERT', 'xrays', 5, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(45, 2, 'UPDATE', 'payments', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(46, 1, 'INSERT', 'treatment_plans', 10, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(47, 2, 'INSERT', 'notifications', 10, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(48, 1, 'UPDATE', 'patients', 10, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(49, 2, 'INSERT', 'audit_log', 1, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(50, 1, 'UPDATE', 'invoices', 6, NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(51, 26, 'CREATE', 'patients', 26, NULL, '{\"full_name\":\"Zeina Ayoub\",\"date_of_birth\":\"\",\"gender\":\"\",\"phone\":\"+961 70389543\",\"email\":\"72230640@students.liu.edu.lb\",\"address_line1\":\"\",\"address_line2\":\"\",\"city\":\"\",\"state\":\"\",\"postal_code\":\"\",\"country\":\"USA\",\"emergency_contact_name\":\"\",\"emergency_contact_phone\":\"\",\"emergency_contact_relation\":\"\",\"insurance_provider\":\"\",\"insurance_id\":\"\",\"insurance_type\":\"None\",\"insurance_coverage\":\"0\",\"medical_history\":\"\",\"allergies\":\"\",\"current_medications\":\"\",\"past_surgeries\":\"\",\"chronic_conditions\":\"\",\"dental_history\":\"\",\"previous_dentist\":\"\",\"last_visit_date\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-24 21:30:30'),
(52, 26, 'CREATE', 'patients', 27, NULL, '{\"full_name\":\"Zeina Ayoub\",\"date_of_birth\":\"\",\"gender\":\"\",\"phone\":\"+961 70389543\",\"email\":\"72230640@students.liu.edu.lb\",\"address_line1\":\"\",\"address_line2\":\"\",\"city\":\"\",\"state\":\"\",\"postal_code\":\"\",\"country\":\"USA\",\"emergency_contact_name\":\"\",\"emergency_contact_phone\":\"\",\"emergency_contact_relation\":\"\",\"insurance_provider\":\"\",\"insurance_id\":\"\",\"insurance_type\":\"None\",\"insurance_coverage\":\"0\",\"medical_history\":\"\",\"allergies\":\"\",\"current_medications\":\"\",\"past_surgeries\":\"\",\"chronic_conditions\":\"\",\"dental_history\":\"\",\"previous_dentist\":\"\",\"last_visit_date\":\"\",\"create_user\":\"1\",\"save_and_continue\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-24 21:30:54'),
(53, 26, 'CREATE', 'appointments', 26, NULL, '{\"patient_id\":\"27\",\"doctor_id\":\"26\",\"appointment_date\":\"2026-03-26\",\"appointment_time\":\"18:21\",\"duration\":\"30\",\"chair_number\":\"\",\"treatment_type\":\"Filling\",\"description\":\"\",\"notes\":\"\",\"save_and_new\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-25 11:21:23'),
(54, 26, 'CREATE', 'appointments', 27, NULL, '{\"patient_id\":\"27\",\"doctor_id\":\"26\",\"appointment_date\":\"2026-03-29\",\"appointment_time\":\"11:45\",\"duration\":\"30\",\"chair_number\":\"\",\"treatment_type\":\"Cleaning\",\"description\":\"\",\"notes\":\"\",\"save_and_new\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-28 09:45:46'),
(55, 26, 'CREATE', 'patients', 28, NULL, '{\"full_name\":\"jawad\",\"date_of_birth\":\"\",\"gender\":\"male\",\"phone\":\"+961 71217984\",\"email\":\"722306@students.liu.edu.lb\",\"address_line1\":\"\",\"address_line2\":\"\",\"city\":\"\",\"state\":\"\",\"postal_code\":\"\",\"country\":\"USA\",\"emergency_contact_name\":\"\",\"emergency_contact_phone\":\"\",\"emergency_contact_relation\":\"\",\"insurance_provider\":\"\",\"insurance_id\":\"\",\"insurance_type\":\"None\",\"insurance_coverage\":\"0\",\"medical_history\":\"\",\"allergies\":\"\",\"current_medications\":\"\",\"past_surgeries\":\"\",\"chronic_conditions\":\"\",\"dental_history\":\"\",\"previous_dentist\":\"\",\"last_visit_date\":\"\",\"create_user\":\"1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-28 09:47:30'),
(56, 26, 'CREATE', 'appointments', 28, NULL, '{\"patient_id\":\"28\",\"doctor_id\":\"1\",\"appointment_date\":\"2026-03-29\",\"appointment_time\":\"13:50\",\"duration\":\"30\",\"chair_number\":\"\",\"treatment_type\":\"Cleaning\",\"description\":\"\",\"notes\":\"\",\"save_and_new\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-28 09:48:53'),
(57, 32, 'INSERT', 'treatment_plans', 26, NULL, '{\"patient_id\":\"28\",\"plan_name\":\"Plan 8\",\"description\":\"d\",\"teeth_affected\":\"21\",\"status\":\"proposed\",\"priority\":\"medium\",\"estimated_cost\":\"01244\",\"discount\":\"0\",\"start_date\":\"2026-03-28\",\"estimated_end_date\":\"2026-03-31\",\"notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-28 17:07:42'),
(58, 32, 'CREATE', 'treatment_steps', 26, NULL, '{\"id\":\"\",\"plan_id\":\"26\",\"step_number\":\"1\",\"procedure_name\":\"filling\",\"description\":\"w\",\"tooth_numbers\":\"21\",\"duration_minutes\":\"30\",\"cost\":\"122\",\"status\":\"pending\",\"notes\":\"e\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-28 17:08:00'),
(59, 32, 'UPDATE', 'treatment_steps', 26, NULL, '{\"id\":\"26\",\"plan_id\":\"26\",\"step_number\":\"1\",\"procedure_name\":\"filling\",\"description\":\"wht\",\"tooth_numbers\":\"21\",\"duration_minutes\":\"30\",\"cost\":\"122.00\",\"status\":\"pending\",\"notes\":\"e\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-28 17:08:06'),
(60, 32, 'UPDATE', 'treatment_plans', 26, NULL, '{\"patient_approved\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-28 17:08:14'),
(61, 32, 'DELETE', 'treatment_plans', 4, '{\"id\":4,\"patient_id\":4,\"plan_name\":\"Plan 4\",\"description\":\"Extraction case\",\"teeth_affected\":\"30\",\"estimated_cost\":\"300.00\",\"actual_cost\":null,\"discount\":\"0.00\",\"status\":\"approved\",\"priority\":\"high\",\"start_date\":\"2026-03-02\",\"estimated_end_date\":null,\"actual_end_date\":null,\"notes\":null,\"patient_approved\":0,\"approval_date\":null,\"approval_signature\":null,\"created_at\":\"2026-03-24 23:04:18\",\"updated_at\":\"2026-03-24 23:04:18\",\"created_by\":1}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-28 17:31:19'),
(62, 32, 'UPDATE', 'treatment_steps', 26, NULL, '{\"id\":\"26\",\"plan_id\":\"26\",\"step_number\":\"1\",\"procedure_name\":\"filling\",\"description\":\"wht\",\"tooth_numbers\":\"21\",\"duration_minutes\":\"30\",\"cost\":\"122.00\",\"status\":\"completed\",\"notes\":\"e\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-28 17:32:48'),
(63, 32, 'UPDATE', 'treatment_plans', 26, '{\"id\":26,\"patient_id\":28,\"plan_name\":\"Plan 8\",\"description\":\"d\",\"teeth_affected\":\"21\",\"estimated_cost\":\"1244.00\",\"actual_cost\":null,\"discount\":\"0.00\",\"status\":\"proposed\",\"priority\":\"medium\",\"start_date\":\"2026-03-28\",\"estimated_end_date\":\"2026-03-31\",\"actual_end_date\":null,\"notes\":\"\",\"patient_approved\":1,\"approval_date\":\"2026-03-28 19:08:14\",\"approval_signature\":null,\"created_at\":\"2026-03-28 19:07:42\",\"updated_at\":\"2026-03-28 19:08:14\",\"created_by\":32}', '{\"plan_name\":\"Plan 8\",\"description\":\"d\",\"teeth_affected\":\"21\",\"status\":\"in-progress\",\"priority\":\"medium\",\"estimated_cost\":\"1244.00\",\"discount\":\"0.00\",\"start_date\":\"2026-03-28\",\"estimated_end_date\":\"2026-03-31\",\"notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-28 17:33:35'),
(64, 32, 'CREATE', 'tooth_chart', 26, NULL, '{\"patient_id\":28,\"tooth_number\":21,\"status\":\"filled\",\"diagnosis\":\"\",\"treatment\":\"\",\"notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-28 17:45:39'),
(65, 32, 'CREATE', 'tooth_chart', 27, NULL, '{\"patient_id\":28,\"tooth_number\":24,\"status\":\"cavity\",\"diagnosis\":\"\",\"treatment\":\"\",\"notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-28 17:45:44'),
(66, 32, 'CREATE', 'tooth_chart', 28, NULL, '{\"patient_id\":28,\"tooth_number\":28,\"status\":\"missing\",\"diagnosis\":\"\",\"treatment\":\"\",\"notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-28 17:46:10'),
(67, 32, 'UPDATE', 'patients', 28, '{\"id\":28,\"user_id\":30,\"full_name\":\"jawad\",\"date_of_birth\":\"0000-00-00\",\"gender\":\"male\",\"phone\":\"+961 71217984\",\"email\":\"722306@students.liu.edu.lb\",\"emergency_contact_name\":\"\",\"emergency_contact_phone\":\"\",\"emergency_contact_relation\":\"\",\"insurance_provider\":\"\",\"insurance_id\":\"\",\"insurance_type\":\"\",\"insurance_coverage\":0,\"medical_history\":\"\",\"allergies\":\"\",\"current_medications\":\"\",\"past_surgeries\":\"\",\"chronic_conditions\":\"\",\"dental_history\":\"\",\"previous_dentist\":\"\",\"last_visit_date\":\"0000-00-00\",\"address_line1\":\"\",\"address_line2\":\"\",\"city\":\"\",\"state\":\"\",\"postal_code\":\"\",\"country\":\"USA\",\"points\":0,\"subscription_type\":\"none\",\"subscription_start_date\":null,\"subscription_end_date\":null,\"referral_code\":null,\"referred_by\":null,\"created_at\":\"2026-03-28 11:47:29\",\"updated_at\":\"2026-03-28 11:47:30\",\"created_by\":26}', '{\"full_name\":\"jawad\",\"date_of_birth\":\"\",\"gender\":\"male\",\"phone\":\"+961 71217984\",\"email\":\"722306@students.liu.edu.lb\",\"address_line1\":\"Tyre\",\"address_line2\":\"\",\"city\":\"\",\"state\":\"\",\"postal_code\":\"\",\"country\":\"USA\",\"emergency_contact_name\":\"\",\"emergency_contact_phone\":\"\",\"emergency_contact_relation\":\"\",\"insurance_provider\":\"\",\"insurance_id\":\"\",\"insurance_type\":\"None\",\"insurance_coverage\":\"0\",\"medical_history\":\"\",\"allergies\":\"\",\"current_medications\":\"\",\"past_surgeries\":\"\",\"chronic_conditions\":\"\",\"dental_history\":\"\",\"previous_dentist\":\"\",\"last_visit_date\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-28 17:47:02'),
(68, 32, 'UPDATE', 'patients', 28, '{\"id\":28,\"user_id\":30,\"full_name\":\"jawad\",\"date_of_birth\":\"0000-00-00\",\"gender\":\"male\",\"phone\":\"+961 71217984\",\"email\":\"722306@students.liu.edu.lb\",\"emergency_contact_name\":\"\",\"emergency_contact_phone\":\"\",\"emergency_contact_relation\":\"\",\"insurance_provider\":\"\",\"insurance_id\":\"\",\"insurance_type\":\"\",\"insurance_coverage\":0,\"medical_history\":\"\",\"allergies\":\"\",\"current_medications\":\"\",\"past_surgeries\":\"\",\"chronic_conditions\":\"\",\"dental_history\":\"\",\"previous_dentist\":\"\",\"last_visit_date\":\"0000-00-00\",\"address_line1\":\"Tyre\",\"address_line2\":\"\",\"city\":\"\",\"state\":\"\",\"postal_code\":\"\",\"country\":\"USA\",\"points\":0,\"subscription_type\":\"none\",\"subscription_start_date\":null,\"subscription_end_date\":null,\"referral_code\":null,\"referred_by\":null,\"created_at\":\"2026-03-28 11:47:29\",\"updated_at\":\"2026-03-28 19:47:02\",\"created_by\":26}', '{\"full_name\":\"jawad\",\"date_of_birth\":\"\",\"gender\":\"male\",\"phone\":\"+961 71217984\",\"email\":\"722306@students.liu.edu.lb\",\"address_line1\":\"Tyre\",\"address_line2\":\"\",\"city\":\"\",\"state\":\"\",\"postal_code\":\"\",\"country\":\"USA\",\"emergency_contact_name\":\"\",\"emergency_contact_phone\":\"\",\"emergency_contact_relation\":\"\",\"insurance_provider\":\"\",\"insurance_id\":\"\",\"insurance_type\":\"None\",\"insurance_coverage\":\"0\",\"medical_history\":\"\",\"allergies\":\"\",\"current_medications\":\"\",\"past_surgeries\":\"\",\"chronic_conditions\":\"\",\"dental_history\":\"\",\"previous_dentist\":\"\",\"last_visit_date\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-28 17:47:04'),
(69, 32, 'CREATE', 'appointments', 29, NULL, '{\"patient_id\":\"28\",\"doctor_id\":\"32\",\"appointment_date\":\"2026-04-03\",\"appointment_time\":\"10:30\",\"duration\":\"45\",\"chair_number\":\"3\",\"treatment_type\":\"Crown\",\"description\":\"\",\"notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-28 17:49:30'),
(70, 32, 'CREATE', 'invoices', 26, NULL, '{\"patient_id\":\"28\",\"appointment_id\":\"\",\"invoice_date\":\"2026-03-28\",\"due_date\":\"2026-04-27\",\"subtotal\":\"0123\",\"discount_type\":\"percentage\",\"discount_value\":\"012\",\"tax_rate\":\"0\",\"notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-28 17:50:05'),
(71, 32, 'DELETE', 'patients', 1, '{\"id\":1,\"user_id\":3,\"full_name\":\"Patient 1\",\"date_of_birth\":null,\"gender\":\"male\",\"phone\":\"3000000001\",\"email\":\"p1@mail.com\",\"emergency_contact_name\":null,\"emergency_contact_phone\":null,\"emergency_contact_relation\":null,\"insurance_provider\":null,\"insurance_id\":null,\"insurance_type\":\"Private\",\"insurance_coverage\":0,\"medical_history\":null,\"allergies\":null,\"current_medications\":null,\"past_surgeries\":null,\"chronic_conditions\":null,\"dental_history\":null,\"previous_dentist\":null,\"last_visit_date\":null,\"address_line1\":null,\"address_line2\":null,\"city\":null,\"state\":null,\"postal_code\":null,\"country\":\"USA\",\"points\":10,\"subscription_type\":\"none\",\"subscription_start_date\":null,\"subscription_end_date\":null,\"referral_code\":\"REF1\",\"referred_by\":null,\"created_at\":\"2026-03-24 23:04:18\",\"updated_at\":\"2026-03-24 23:04:18\",\"created_by\":2}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-28 17:52:27'),
(72, 32, 'UPDATE', 'patients', 28, '{\"id\":28,\"user_id\":30,\"full_name\":\"jawad\",\"date_of_birth\":\"0000-00-00\",\"gender\":\"male\",\"phone\":\"+961 71217984\",\"email\":\"722306@students.liu.edu.lb\",\"emergency_contact_name\":\"\",\"emergency_contact_phone\":\"\",\"emergency_contact_relation\":\"\",\"insurance_provider\":\"\",\"insurance_id\":\"\",\"insurance_type\":\"\",\"insurance_coverage\":0,\"medical_history\":\"\",\"allergies\":\"\",\"current_medications\":\"\",\"past_surgeries\":\"\",\"chronic_conditions\":\"\",\"dental_history\":\"\",\"previous_dentist\":\"\",\"last_visit_date\":\"0000-00-00\",\"address_line1\":\"Tyre\",\"address_line2\":\"\",\"city\":\"\",\"state\":\"\",\"postal_code\":\"\",\"country\":\"USA\",\"points\":0,\"subscription_type\":\"none\",\"subscription_start_date\":null,\"subscription_end_date\":null,\"referral_code\":null,\"referred_by\":null,\"created_at\":\"2026-03-28 11:47:29\",\"updated_at\":\"2026-03-28 19:47:02\",\"created_by\":26}', '{\"full_name\":\"jawad\",\"date_of_birth\":\"\",\"gender\":\"other\",\"phone\":\"+961 71217984\",\"email\":\"722306@students.liu.edu.lb\",\"address_line1\":\"Tyre\",\"address_line2\":\"\",\"city\":\"\",\"state\":\"\",\"postal_code\":\"\",\"country\":\"USA\",\"emergency_contact_name\":\"\",\"emergency_contact_phone\":\"\",\"emergency_contact_relation\":\"\",\"insurance_provider\":\"\",\"insurance_id\":\"\",\"insurance_type\":\"None\",\"insurance_coverage\":\"0\",\"medical_history\":\"\",\"allergies\":\"\",\"current_medications\":\"\",\"past_surgeries\":\"\",\"chronic_conditions\":\"\",\"dental_history\":\"\",\"previous_dentist\":\"\",\"last_visit_date\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-29 16:31:28'),
(73, 32, 'UPDATE', 'patients', 28, '{\"id\":28,\"user_id\":30,\"full_name\":\"jawad\",\"date_of_birth\":\"0000-00-00\",\"gender\":\"other\",\"phone\":\"+961 71217984\",\"email\":\"722306@students.liu.edu.lb\",\"emergency_contact_name\":\"\",\"emergency_contact_phone\":\"\",\"emergency_contact_relation\":\"\",\"insurance_provider\":\"\",\"insurance_id\":\"\",\"insurance_type\":\"\",\"insurance_coverage\":0,\"medical_history\":\"\",\"allergies\":\"\",\"current_medications\":\"\",\"past_surgeries\":\"\",\"chronic_conditions\":\"\",\"dental_history\":\"\",\"previous_dentist\":\"\",\"last_visit_date\":\"0000-00-00\",\"address_line1\":\"Tyre\",\"address_line2\":\"\",\"city\":\"\",\"state\":\"\",\"postal_code\":\"\",\"country\":\"USA\",\"points\":0,\"subscription_type\":\"none\",\"subscription_start_date\":null,\"subscription_end_date\":null,\"referral_code\":null,\"referred_by\":null,\"created_at\":\"2026-03-28 11:47:29\",\"updated_at\":\"2026-03-29 19:31:28\",\"created_by\":26}', '{\"full_name\":\"jawad\",\"date_of_birth\":\"\",\"gender\":\"male\",\"phone\":\"+961 71217984\",\"email\":\"722306@students.liu.edu.lb\",\"address_line1\":\"Tyre\",\"address_line2\":\"\",\"city\":\"\",\"state\":\"\",\"postal_code\":\"\",\"country\":\"USA\",\"emergency_contact_name\":\"\",\"emergency_contact_phone\":\"\",\"emergency_contact_relation\":\"\",\"insurance_provider\":\"\",\"insurance_id\":\"\",\"insurance_type\":\"None\",\"insurance_coverage\":\"0\",\"medical_history\":\"\",\"allergies\":\"\",\"current_medications\":\"\",\"past_surgeries\":\"\",\"chronic_conditions\":\"\",\"dental_history\":\"\",\"previous_dentist\":\"\",\"last_visit_date\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-29 16:31:39'),
(74, 33, 'UPDATE', 'patients', 29, '{\"id\":29,\"user_id\":33,\"full_name\":\"Saja dyab\",\"date_of_birth\":\"0000-00-00\",\"gender\":\"female\",\"phone\":\"+961 81 665 330\",\"email\":\"dyabsaja@gmail.com\",\"emergency_contact_name\":\"Fatima haydar\",\"emergency_contact_phone\":\"+961 71532470\",\"emergency_contact_relation\":\"mother\",\"insurance_provider\":null,\"insurance_id\":null,\"insurance_type\":\"None\",\"insurance_coverage\":0,\"medical_history\":null,\"allergies\":null,\"current_medications\":null,\"past_surgeries\":null,\"chronic_conditions\":null,\"dental_history\":null,\"previous_dentist\":null,\"last_visit_date\":null,\"address_line1\":null,\"address_line2\":null,\"city\":null,\"state\":null,\"postal_code\":null,\"country\":\"USA\",\"points\":0,\"subscription_type\":\"none\",\"subscription_start_date\":null,\"subscription_end_date\":null,\"referral_code\":null,\"referred_by\":null,\"created_at\":\"2026-03-29 19:43:52\",\"updated_at\":\"2026-03-30 14:15:31\",\"created_by\":null}', '{\"full_name\":\"Saja dyab\",\"date_of_birth\":\"2005-11-12\",\"gender\":\"female\",\"phone\":\"+961 81 665 330\",\"email\":\"dyabsaja@gmail.com\",\"emergency_contact_name\":\"Fatima haydar\",\"emergency_contact_phone\":\"+961 71532470\",\"emergency_contact_relation\":\"mother\",\"address_line1\":\"\",\"address_line2\":\"\",\"city\":\"\",\"state\":\"\",\"postal_code\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-30 11:15:53'),
(75, 33, 'UPDATE', 'patients', 29, '{\"id\":29,\"user_id\":33,\"full_name\":\"Saja dyab\",\"date_of_birth\":\"2005-11-12\",\"gender\":\"female\",\"phone\":\"+961 81 665 330\",\"email\":\"dyabsaja@gmail.com\",\"emergency_contact_name\":\"Fatima haydar\",\"emergency_contact_phone\":\"+961 71532470\",\"emergency_contact_relation\":\"mother\",\"insurance_provider\":null,\"insurance_id\":null,\"insurance_type\":\"None\",\"insurance_coverage\":0,\"medical_history\":null,\"allergies\":null,\"current_medications\":null,\"past_surgeries\":null,\"chronic_conditions\":null,\"dental_history\":null,\"previous_dentist\":null,\"last_visit_date\":null,\"address_line1\":\"\",\"address_line2\":\"\",\"city\":\"\",\"state\":\"\",\"postal_code\":\"\",\"country\":\"USA\",\"points\":0,\"subscription_type\":\"none\",\"subscription_start_date\":null,\"subscription_end_date\":null,\"referral_code\":null,\"referred_by\":null,\"created_at\":\"2026-03-29 19:43:52\",\"updated_at\":\"2026-03-30 14:15:52\",\"created_by\":null}', '{\"full_name\":\"Saja dyab\",\"date_of_birth\":\"2005-11-12\",\"gender\":\"female\",\"phone\":\"+961 81 665 330\",\"email\":\"dyabsaja@gmail.com\",\"emergency_contact_name\":\"Fatima haydar\",\"emergency_contact_phone\":\"+961 71532470\",\"emergency_contact_relation\":\"mother\",\"address_line1\":\"\",\"address_line2\":\"\",\"city\":\"tyre\",\"state\":\"\",\"postal_code\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-30 11:16:03'),
(76, 33, 'CREATE', 'appointments', 30, NULL, '{\"doctor_id\":\"26\",\"appointment_date\":\"2026-03-31\",\"appointment_time\":\"16:17\",\"duration\":\"30\",\"chair_number\":\"\",\"treatment_type\":\"Filling\",\"description\":\"nothing\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-30 11:16:38'),
(77, 33, 'CREATE', 'appointments', 31, NULL, '{\"doctor_id\":\"1\",\"appointment_date\":\"2026-04-02\",\"appointment_time\":\"18:20\",\"duration\":\"30\",\"chair_number\":\"\",\"treatment_type\":\"Whitening\",\"description\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-30 11:17:09'),
(78, 33, 'UPDATE', 'patients', 29, '{\"id\":29,\"user_id\":33,\"full_name\":\"Saja dyab\",\"date_of_birth\":\"2005-11-12\",\"gender\":\"female\",\"phone\":\"+961 81 665 330\",\"email\":\"dyabsaja@gmail.com\",\"emergency_contact_name\":\"Fatima haydar\",\"emergency_contact_phone\":\"+961 71532470\",\"emergency_contact_relation\":\"mother\",\"insurance_provider\":null,\"insurance_id\":null,\"insurance_type\":\"None\",\"insurance_coverage\":0,\"medical_history\":null,\"allergies\":null,\"current_medications\":null,\"past_surgeries\":null,\"chronic_conditions\":null,\"dental_history\":null,\"previous_dentist\":null,\"last_visit_date\":null,\"address_line1\":\"\",\"address_line2\":\"\",\"city\":\"tyre\",\"state\":\"\",\"postal_code\":\"\",\"country\":\"USA\",\"points\":0,\"subscription_type\":\"none\",\"subscription_start_date\":null,\"subscription_end_date\":null,\"referral_code\":null,\"referred_by\":null,\"created_at\":\"2026-03-29 19:43:52\",\"updated_at\":\"2026-03-30 14:16:03\",\"created_by\":null}', '{\"full_name\":\"Saja Dyab\",\"date_of_birth\":\"2005-11-12\",\"gender\":\"female\",\"phone\":\"+961 81 665 330\",\"email\":\"dyabsaja@gmail.com\",\"emergency_contact_name\":\"Fatima haydar\",\"emergency_contact_phone\":\"+961 71532470\",\"emergency_contact_relation\":\"mother\",\"address_line1\":\"\",\"address_line2\":\"\",\"city\":\"tyre\",\"state\":\"\",\"postal_code\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-30 11:21:13'),
(79, 33, 'UPDATE', 'patients', 29, '{\"id\":29,\"user_id\":33,\"full_name\":\"Saja Dyab\",\"date_of_birth\":\"2005-11-12\",\"gender\":\"female\",\"phone\":\"+961 81 665 330\",\"email\":\"dyabsaja@gmail.com\",\"emergency_contact_name\":\"Fatima haydar\",\"emergency_contact_phone\":\"+961 71532470\",\"emergency_contact_relation\":\"mother\",\"insurance_provider\":null,\"insurance_id\":null,\"insurance_type\":\"None\",\"insurance_coverage\":0,\"medical_history\":null,\"allergies\":null,\"current_medications\":null,\"past_surgeries\":null,\"chronic_conditions\":null,\"dental_history\":null,\"previous_dentist\":null,\"last_visit_date\":null,\"address_line1\":\"\",\"address_line2\":\"\",\"city\":\"tyre\",\"state\":\"\",\"postal_code\":\"\",\"country\":\"USA\",\"points\":0,\"subscription_type\":\"none\",\"subscription_start_date\":null,\"subscription_end_date\":null,\"referral_code\":null,\"referred_by\":null,\"created_at\":\"2026-03-29 19:43:52\",\"updated_at\":\"2026-03-30 14:21:13\",\"created_by\":null}', '{\"full_name\":\"Saja Dyab\",\"date_of_birth\":\"2005-11-12\",\"gender\":\"female\",\"phone\":\"+961 81 665 330\",\"email\":\"dyabsaja@gmail.com\",\"emergency_contact_name\":\"Fatima haydar\",\"emergency_contact_phone\":\"+961 71532470\",\"emergency_contact_relation\":\"mother\",\"address_line1\":\"\",\"address_line2\":\"\",\"city\":\"tyre\",\"state\":\"\",\"postal_code\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-30 11:21:23'),
(80, 33, 'UPDATE', 'patients', 29, '{\"id\":29,\"user_id\":33,\"full_name\":\"Saja Dyab\",\"date_of_birth\":\"2005-11-12\",\"gender\":\"female\",\"phone\":\"+961 81 665 330\",\"email\":\"dyabsaja@gmail.com\",\"emergency_contact_name\":\"Fatima haydar\",\"emergency_contact_phone\":\"+961 71532470\",\"emergency_contact_relation\":\"mother\",\"insurance_provider\":null,\"insurance_id\":null,\"insurance_type\":\"None\",\"insurance_coverage\":0,\"medical_history\":null,\"allergies\":null,\"current_medications\":null,\"past_surgeries\":null,\"chronic_conditions\":null,\"dental_history\":null,\"previous_dentist\":null,\"last_visit_date\":null,\"address_line1\":\"\",\"address_line2\":\"\",\"city\":\"tyre\",\"state\":\"\",\"postal_code\":\"\",\"country\":\"USA\",\"points\":0,\"subscription_type\":\"none\",\"subscription_start_date\":null,\"subscription_end_date\":null,\"referral_code\":null,\"referred_by\":null,\"created_at\":\"2026-03-29 19:43:52\",\"updated_at\":\"2026-03-30 14:21:13\",\"created_by\":null}', '{\"full_name\":\"Saja Dyab\",\"date_of_birth\":\"2005-11-12\",\"gender\":\"female\",\"phone\":\"+961 81 665 330\",\"email\":\"dyabsaja@gmail.com\",\"emergency_contact_name\":\"Fatima haydar\",\"emergency_contact_phone\":\"+961 71532470\",\"emergency_contact_relation\":\"mother\",\"address_line1\":\"\",\"address_line2\":\"\",\"city\":\"tyre\",\"state\":\"\",\"postal_code\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-30 11:29:51'),
(81, 33, 'CREATE', 'appointments', 32, NULL, '{\"doctor_id\":\"32\",\"appointment_date\":\"2026-04-02\",\"appointment_time\":\"14:43\",\"duration\":\"30\",\"chair_number\":\"\",\"treatment_type\":\"Crown\",\"description\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-30 11:37:20'),
(82, 32, 'CREATE', 'invoices', 27, NULL, '{\"patient_id\":\"29\",\"appointment_id\":\"\",\"invoice_date\":\"2026-03-30\",\"due_date\":\"2026-04-29\",\"subtotal\":\"11000\",\"discount_type\":\"fixed\",\"discount_value\":\"0\",\"tax_rate\":\"0\",\"notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-30 12:26:43'),
(83, 33, 'UPDATE', 'patients', 29, '{\"id\":29,\"user_id\":33,\"full_name\":\"Saja Dyab\",\"date_of_birth\":\"2005-11-12\",\"gender\":\"female\",\"phone\":\"+961 81 665 330\",\"email\":\"dyabsaja@gmail.com\",\"emergency_contact_name\":\"Fatima haydar\",\"emergency_contact_phone\":\"+961 71532470\",\"emergency_contact_relation\":\"mother\",\"insurance_provider\":null,\"insurance_id\":null,\"insurance_type\":\"None\",\"insurance_coverage\":0,\"medical_history\":null,\"allergies\":null,\"current_medications\":null,\"past_surgeries\":null,\"chronic_conditions\":null,\"dental_history\":null,\"previous_dentist\":null,\"last_visit_date\":null,\"address_line1\":\"\",\"address_line2\":\"\",\"city\":\"tyre\",\"state\":\"\",\"postal_code\":\"\",\"country\":\"USA\",\"points\":0,\"subscription_type\":\"none\",\"subscription_start_date\":null,\"subscription_end_date\":null,\"referral_code\":null,\"referred_by\":null,\"created_at\":\"2026-03-29 19:43:52\",\"updated_at\":\"2026-03-30 14:21:13\",\"created_by\":null}', '{\"full_name\":\"Saja Dyab\",\"date_of_birth\":\"2005-11-12\",\"gender\":\"other\",\"phone\":\"+961 81 665 330\",\"email\":\"dyabsaja@gmail.com\",\"emergency_contact_name\":\"Fatima haydar\",\"emergency_contact_phone\":\"+961 71532470\",\"emergency_contact_relation\":\"mother\",\"address_line1\":\"\",\"address_line2\":\"\",\"city\":\"tyre\",\"state\":\"\",\"postal_code\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-30 13:34:35'),
(84, 33, 'UPDATE', 'patients', 29, '{\"id\":29,\"user_id\":33,\"full_name\":\"Saja Dyab\",\"date_of_birth\":\"2005-11-12\",\"gender\":\"other\",\"phone\":\"+961 81 665 330\",\"email\":\"dyabsaja@gmail.com\",\"emergency_contact_name\":\"Fatima haydar\",\"emergency_contact_phone\":\"+961 71532470\",\"emergency_contact_relation\":\"mother\",\"insurance_provider\":null,\"insurance_id\":null,\"insurance_type\":\"None\",\"insurance_coverage\":0,\"medical_history\":null,\"allergies\":null,\"current_medications\":null,\"past_surgeries\":null,\"chronic_conditions\":null,\"dental_history\":null,\"previous_dentist\":null,\"last_visit_date\":null,\"address_line1\":\"\",\"address_line2\":\"\",\"city\":\"tyre\",\"state\":\"\",\"postal_code\":\"\",\"country\":\"USA\",\"points\":0,\"subscription_type\":\"none\",\"subscription_start_date\":null,\"subscription_end_date\":null,\"referral_code\":null,\"referred_by\":null,\"created_at\":\"2026-03-29 19:43:52\",\"updated_at\":\"2026-03-30 16:34:35\",\"created_by\":null}', '{\"full_name\":\"Saja Dyab\",\"date_of_birth\":\"2005-11-12\",\"gender\":\"female\",\"phone\":\"+961 81 665 330\",\"email\":\"dyabsaja@gmail.com\",\"emergency_contact_name\":\"Fatima haydar\",\"emergency_contact_phone\":\"+961 71532470\",\"emergency_contact_relation\":\"mother\",\"address_line1\":\"\",\"address_line2\":\"\",\"city\":\"tyre\",\"state\":\"\",\"postal_code\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-30 13:34:41'),
(85, 33, 'CREATE', 'appointments', 33, NULL, '{\"doctor_id\":\"32\",\"appointment_date\":\"2026-04-03\",\"appointment_time\":\"17:36\",\"duration\":\"30\",\"chair_number\":\"\",\"treatment_type\":\"\\ud83e\\uddb7 Extraction\",\"description\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-30 14:32:36'),
(86, 33, 'CREATE', 'appointments', 34, NULL, '{\"doctor_id\":\"1\",\"appointment_date\":\"2026-04-09\",\"appointment_time\":\"17:40\",\"duration\":\"30\",\"chair_number\":\"\",\"treatment_type\":\"\\ud83e\\uddb7 Extraction\",\"description\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2026-03-30 14:36:52');

-- --------------------------------------------------------

--
-- Table structure for table `clinic_settings`
--

CREATE TABLE `clinic_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinic_settings`
--

INSERT INTO `clinic_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'clinic_name', 'Dental Clinic', '2026-03-30 17:46:50'),
(2, 'clinic_phone', '+961 81665330', '2026-03-30 18:03:08'),
(3, 'clinic_email', 'info@dentalclinic.com', '2026-03-30 17:46:50'),
(4, 'clinic_address', '123 Main St, Anytown, USA', '2026-03-30 17:46:50'),
(5, 'opening_hours', 'Monday-Friday: 9am - 5pm\r\nSaturday: 9am - 1pm\r\nSunday: Closed', '2026-03-30 17:50:14');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `unit` varchar(20) DEFAULT NULL,
  `reorder_level` int(11) DEFAULT 10,
  `reorder_quantity` int(11) DEFAULT 0,
  `supplier_name` varchar(100) DEFAULT NULL,
  `supplier_contact` varchar(100) DEFAULT NULL,
  `cost_per_unit` decimal(10,2) DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `item_name`, `category`, `description`, `quantity`, `unit`, `reorder_level`, `reorder_quantity`, `supplier_name`, `supplier_contact`, `cost_per_unit`, `selling_price`, `expiry_date`, `created_at`, `updated_at`, `created_by`) VALUES
(1, 'Gloves', 'Consumable', NULL, 200, 'box', 20, 0, NULL, NULL, 5.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(2, 'Masks', 'Consumable', NULL, 300, 'box', 30, 0, NULL, NULL, 3.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(3, 'Syringes', 'Equipment', NULL, 150, 'pcs', 15, 0, NULL, NULL, 2.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(4, 'Anesthetic', 'Medicine', NULL, 50, 'ml', 10, 0, NULL, NULL, 15.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(5, 'Filling Material', 'Material', NULL, 100, 'pcs', 10, 0, NULL, NULL, 20.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(6, 'Crown Kit', 'Material', NULL, 40, 'pcs', 5, 0, NULL, NULL, 50.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(7, 'Whitening Gel', 'Material', NULL, 60, 'pcs', 10, 0, NULL, NULL, 25.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(8, 'Implant Screw', 'Material', NULL, 30, 'pcs', 5, 0, NULL, NULL, 100.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(9, 'Cotton Rolls', 'Consumable', NULL, 500, 'pcs', 50, 0, NULL, NULL, 1.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(10, 'Disinfectant', 'Consumable', NULL, 80, 'bottle', 10, 0, NULL, NULL, 8.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(11, 'Dental Mirror', 'Equipment', NULL, 20, 'pcs', 5, 0, NULL, NULL, 15.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(12, 'Scaler Tip', 'Equipment', NULL, 25, 'pcs', 5, 0, NULL, NULL, 30.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(13, 'Composite', 'Material', NULL, 70, 'pcs', 10, 0, NULL, NULL, 18.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(14, 'Etchant', 'Material', NULL, 40, 'pcs', 5, 0, NULL, NULL, 12.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(15, 'Bonding Agent', 'Material', NULL, 35, 'pcs', 5, 0, NULL, NULL, 14.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(16, 'X-ray Film', 'Material', NULL, 90, 'pcs', 10, 0, NULL, NULL, 4.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(17, 'Cement', 'Material', NULL, 60, 'pcs', 10, 0, NULL, NULL, 16.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(18, 'Orthodontic Wire', 'Material', NULL, 45, 'pcs', 5, 0, NULL, NULL, 22.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(19, 'Bracket Set', 'Material', NULL, 30, 'set', 5, 0, NULL, NULL, 60.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(20, 'Saliva Ejector', 'Consumable', NULL, 400, 'pcs', 40, 0, NULL, NULL, 0.50, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(21, 'Needles', 'Consumable', NULL, 250, 'pcs', 20, 0, NULL, NULL, 1.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(22, 'Tray Covers', 'Consumable', NULL, 150, 'pcs', 15, 0, NULL, NULL, 2.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(23, 'Impression Material', 'Material', NULL, 55, 'pcs', 5, 0, NULL, NULL, 20.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(24, 'Surgical Blade', 'Equipment', NULL, 75, 'pcs', 10, 0, NULL, NULL, 5.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(25, 'Gauze', 'Consumable', NULL, 600, 'pcs', 60, 0, NULL, NULL, 0.20, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(26, 'Gloves', 'Consumable', NULL, 200, 'box', 20, 0, NULL, NULL, 5.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(27, 'Masks', 'Consumable', NULL, 300, 'box', 30, 0, NULL, NULL, 3.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(28, 'Syringes', 'Equipment', NULL, 150, 'pcs', 15, 0, NULL, NULL, 2.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(29, 'Anesthetic', 'Medicine', NULL, 50, 'ml', 10, 0, NULL, NULL, 15.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(30, 'Filling Material', 'Material', NULL, 100, 'pcs', 10, 0, NULL, NULL, 20.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(31, 'Crown Kit', 'Material', NULL, 40, 'pcs', 5, 0, NULL, NULL, 50.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(32, 'Whitening Gel', 'Material', NULL, 60, 'pcs', 10, 0, NULL, NULL, 25.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(33, 'Implant Screw', 'Material', NULL, 30, 'pcs', 5, 0, NULL, NULL, 100.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(34, 'Cotton Rolls', 'Consumable', NULL, 500, 'pcs', 50, 0, NULL, NULL, 1.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(35, 'Disinfectant', 'Consumable', NULL, 80, 'bottle', 10, 0, NULL, NULL, 8.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(36, 'Dental Mirror', 'Equipment', NULL, 20, 'pcs', 5, 0, NULL, NULL, 15.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(37, 'Scaler Tip', 'Equipment', NULL, 25, 'pcs', 5, 0, NULL, NULL, 30.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(38, 'Composite', 'Material', NULL, 70, 'pcs', 10, 0, NULL, NULL, 18.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(39, 'Etchant', 'Material', NULL, 40, 'pcs', 5, 0, NULL, NULL, 12.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(40, 'Bonding Agent', 'Material', NULL, 35, 'pcs', 5, 0, NULL, NULL, 14.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(41, 'X-ray Film', 'Material', NULL, 90, 'pcs', 10, 0, NULL, NULL, 4.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(42, 'Cement', 'Material', NULL, 60, 'pcs', 10, 0, NULL, NULL, 16.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(43, 'Orthodontic Wire', 'Material', NULL, 45, 'pcs', 5, 0, NULL, NULL, 22.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(44, 'Bracket Set', 'Material', NULL, 30, 'set', 5, 0, NULL, NULL, 60.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(45, 'Saliva Ejector', 'Consumable', NULL, 400, 'pcs', 40, 0, NULL, NULL, 0.50, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(46, 'Needles', 'Consumable', NULL, 250, 'pcs', 20, 0, NULL, NULL, 1.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(47, 'Tray Covers', 'Consumable', NULL, 150, 'pcs', 15, 0, NULL, NULL, 2.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(48, 'Impression Material', 'Material', NULL, 55, 'pcs', 5, 0, NULL, NULL, 20.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(49, 'Surgical Blade', 'Equipment', NULL, 75, 'pcs', 10, 0, NULL, NULL, 5.00, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(50, 'Gauze', 'Consumable', NULL, 600, 'pcs', 60, 0, NULL, NULL, 0.20, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2));

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `transaction_type` enum('purchase','use','adjustment','return') NOT NULL,
  `quantity_change` int(11) NOT NULL,
  `new_quantity` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_transactions`
--

INSERT INTO `inventory_transactions` (`id`, `inventory_id`, `transaction_type`, `quantity_change`, `new_quantity`, `reason`, `performed_by`, `performed_at`) VALUES
(1, 1, 'purchase', 200, 200, NULL, 2, '2026-03-24 21:04:18'),
(2, 2, 'purchase', 300, 300, NULL, 2, '2026-03-24 21:04:18'),
(3, 3, 'purchase', 150, 150, NULL, 2, '2026-03-24 21:04:18'),
(4, 4, 'purchase', 50, 50, NULL, 2, '2026-03-24 21:04:18'),
(5, 5, 'purchase', 100, 100, NULL, 2, '2026-03-24 21:04:18'),
(6, 6, 'purchase', 40, 40, NULL, 2, '2026-03-24 21:04:18'),
(7, 7, 'purchase', 60, 60, NULL, 2, '2026-03-24 21:04:18'),
(8, 8, 'purchase', 30, 30, NULL, 2, '2026-03-24 21:04:18'),
(9, 9, 'purchase', 500, 500, NULL, 2, '2026-03-24 21:04:18'),
(10, 10, 'purchase', 80, 80, NULL, 2, '2026-03-24 21:04:18'),
(11, 11, 'purchase', 20, 20, NULL, 2, '2026-03-24 21:04:18'),
(12, 12, 'purchase', 25, 25, NULL, 2, '2026-03-24 21:04:18'),
(13, 13, 'purchase', 70, 70, NULL, 2, '2026-03-24 21:04:18'),
(14, 14, 'purchase', 40, 40, NULL, 2, '2026-03-24 21:04:18'),
(15, 15, 'purchase', 35, 35, NULL, 2, '2026-03-24 21:04:18'),
(16, 16, 'purchase', 90, 90, NULL, 2, '2026-03-24 21:04:18'),
(17, 17, 'purchase', 60, 60, NULL, 2, '2026-03-24 21:04:18'),
(18, 18, 'purchase', 45, 45, NULL, 2, '2026-03-24 21:04:18'),
(19, 19, 'purchase', 30, 30, NULL, 2, '2026-03-24 21:04:18'),
(20, 20, 'purchase', 400, 400, NULL, 2, '2026-03-24 21:04:18'),
(21, 21, 'purchase', 250, 250, NULL, 2, '2026-03-24 21:04:18'),
(22, 22, 'purchase', 150, 150, NULL, 2, '2026-03-24 21:04:18'),
(23, 23, 'purchase', 55, 55, NULL, 2, '2026-03-24 21:04:18'),
(24, 24, 'purchase', 75, 75, NULL, 2, '2026-03-24 21:04:18'),
(25, 25, 'purchase', 600, 600, NULL, 2, '2026-03-24 21:04:18');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `discount_type` enum('percentage','fixed') DEFAULT 'fixed',
  `discount_value` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) GENERATED ALWAYS AS (case when `discount_type` = 'percentage' then `subtotal` * `discount_value` / 100 else `discount_value` end) VIRTUAL,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) GENERATED ALWAYS AS ((`subtotal` - `discount_amount`) * `tax_rate` / 100) VIRTUAL,
  `total_amount` decimal(10,2) GENERATED ALWAYS AS (`subtotal` - `discount_amount` + `tax_amount`) VIRTUAL,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `balance_due` decimal(10,2) GENERATED ALWAYS AS (`total_amount` - `paid_amount`) VIRTUAL,
  `insurance_type` varchar(50) DEFAULT NULL,
  `insurance_claim_id` varchar(100) DEFAULT NULL,
  `insurance_coverage` decimal(10,2) DEFAULT 0.00,
  `insurance_status` enum('pending','approved','denied','paid') DEFAULT 'pending',
  `payment_status` enum('paid','partial','pending','overdue','cancelled') DEFAULT 'pending',
  `payment_method` enum('cash','card','insurance','online','check') DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `invoice_number`, `patient_id`, `appointment_id`, `invoice_date`, `due_date`, `subtotal`, `discount_type`, `discount_value`, `tax_rate`, `paid_amount`, `insurance_type`, `insurance_claim_id`, `insurance_coverage`, `insurance_status`, `payment_status`, `payment_method`, `notes`, `created_at`, `created_by`, `paid_at`) VALUES
(2, 'INV002', 2, 2, '2026-03-01', '2026-03-10', 200.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'paid', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(3, 'INV003', 3, 3, '2026-03-01', '2026-03-10', 800.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(4, 'INV004', 4, 4, '2026-03-02', '2026-03-11', 300.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(5, 'INV005', 5, 5, '2026-03-02', '2026-03-11', 250.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(6, 'INV006', 6, 6, '2026-03-02', '2026-03-11', 600.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'paid', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(7, 'INV007', 7, 7, '2026-03-03', '2026-03-12', 100.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(8, 'INV008', 8, 8, '2026-03-03', '2026-03-12', 150.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(9, 'INV009', 9, 9, '2026-03-03', '2026-03-12', 750.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(10, 'INV010', 10, 10, '2026-03-04', '2026-03-13', 250.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(11, 'INV011', 11, 11, '2026-03-04', '2026-03-13', 300.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(12, 'INV012', 12, 12, '2026-03-04', '2026-03-13', 550.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(13, 'INV013', 13, 13, '2026-03-05', '2026-03-14', 100.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(14, 'INV014', 14, 14, '2026-03-05', '2026-03-14', 200.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(15, 'INV015', 15, 15, '2026-03-05', '2026-03-14', 850.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(16, 'INV016', 16, 16, '2026-03-06', '2026-03-15', 350.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(17, 'INV017', 17, 17, '2026-03-06', '2026-03-15', 200.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(18, 'INV018', 18, 18, '2026-03-06', '2026-03-15', 500.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(19, 'INV019', 19, 19, '2026-03-07', '2026-03-16', 120.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(20, 'INV020', 20, 20, '2026-03-07', '2026-03-16', 180.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(21, 'INV021', 21, 21, '2026-03-07', '2026-03-16', 780.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(22, 'INV022', 22, 22, '2026-03-08', '2026-03-17', 300.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(23, 'INV023', 23, 23, '2026-03-08', '2026-03-17', 260.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(24, 'INV024', 24, 24, '2026-03-08', '2026-03-17', 650.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(25, 'INV025', 25, 25, '2026-03-09', '2026-03-18', 110.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, NULL, '2026-03-24 21:04:18', 2, NULL),
(26, 'INV-20260328-6824', 28, NULL, '2026-03-28', '2026-04-27', 123.00, 'percentage', 12.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, '', '2026-03-28 17:50:05', 32, NULL),
(27, 'INV-20260330-1546', 29, NULL, '2026-03-30', '2026-04-29', 11000.00, 'fixed', 0.00, 0.00, 0.00, NULL, NULL, 0.00, 'pending', 'pending', NULL, '', '2026-03-30 12:26:43', 32, NULL),
(28, 'INV-20260330-2300', 29, NULL, '2026-03-30', '2026-04-06', 588.00, 'fixed', 0.00, 0.00, 588.00, NULL, NULL, 0.00, 'pending', 'paid', NULL, 'Subscription: premium plan (Annual) - Pending Payment', '2026-03-30 14:15:14', 33, '2026-03-30 14:29:36'),
(29, 'INV-20260330-4651', 30, NULL, '2026-03-30', '2026-04-06', 948.00, 'fixed', 0.00, 0.00, 948.00, NULL, NULL, 0.00, 'pending', 'paid', NULL, 'Subscription: family plan (Annual) - Pending Payment', '2026-03-30 14:47:45', 34, '2026-03-30 14:48:55');

-- --------------------------------------------------------

--
-- Table structure for table `monthly_expenses`
--

CREATE TABLE `monthly_expenses` (
  `id` int(11) NOT NULL,
  `month_year` date NOT NULL,
  `salaries_total` decimal(10,2) DEFAULT 0.00,
  `assistants_count` int(11) DEFAULT 0,
  `electricity` decimal(10,2) DEFAULT 0.00,
  `rent` decimal(10,2) DEFAULT 0.00,
  `other_expenses` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `monthly_expenses`
--

INSERT INTO `monthly_expenses` (`id`, `month_year`, `salaries_total`, `assistants_count`, `electricity`, `rent`, `other_expenses`, `notes`, `created_at`, `updated_at`) VALUES
(1, '2026-03-01', 0.00, 0, 0.00, 0.00, 0.00, NULL, '2026-03-28 16:39:44', '2026-03-28 16:39:44'),
(2, '2026-02-01', 0.00, 0, 0.00, 0.00, 0.00, NULL, '2026-03-30 15:16:17', '2026-03-30 15:16:17');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('appointment_reminder','treatment_instructions','payment_reminder','promotion','queue_update') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `sent_via` enum('sms','email','push','in-app') DEFAULT 'in-app',
  `sent_at` timestamp NULL DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `related_appointment_id` int(11) DEFAULT NULL,
  `related_invoice_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `sent_via`, `sent_at`, `read_at`, `related_appointment_id`, `related_invoice_id`, `created_at`) VALUES
(2, 4, 'payment_reminder', 'Payment Due', 'Please complete payment', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(3, 5, 'treatment_instructions', 'Post Treatment', 'Follow instructions carefully', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(5, 7, 'queue_update', 'Queue Update', 'You are next in line', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(6, 8, 'appointment_reminder', 'Reminder', 'Upcoming visit', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(7, 9, 'payment_reminder', 'Pending Invoice', 'Invoice still unpaid', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(8, 10, 'promotion', 'Offer', 'Teeth whitening discount', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(9, 11, 'queue_update', 'Queue', 'Position updated', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(10, 12, 'appointment_reminder', 'Reminder', 'Appointment tomorrow', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(11, 13, 'promotion', 'Offer', 'Cleaning discount', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(12, 14, 'payment_reminder', 'Invoice', 'Please pay soon', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(13, 15, 'appointment_reminder', 'Reminder', 'Visit reminder', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(14, 16, 'queue_update', 'Queue', 'Emergency priority', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(15, 17, 'promotion', 'Offer', 'Whitening special', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(16, 18, 'appointment_reminder', 'Reminder', 'Upcoming visit', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(17, 19, 'payment_reminder', 'Due', 'Invoice reminder', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(18, 20, 'promotion', 'Offer', 'Discount on crowns', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(19, 21, 'queue_update', 'Queue', 'You are #2', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(20, 22, 'appointment_reminder', 'Reminder', 'Visit tomorrow', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(21, 23, 'promotion', 'Offer', 'Free consultation', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(22, 24, 'payment_reminder', 'Due', 'Please pay', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(23, 25, 'queue_update', 'Queue', 'Your turn soon', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(24, 1, 'promotion', 'Clinic Update', 'New services available', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(26, 4, 'payment_reminder', 'Payment Due', 'Please complete payment', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(27, 5, 'treatment_instructions', 'Post Treatment', 'Follow instructions carefully', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(29, 7, 'queue_update', 'Queue Update', 'You are next in line', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(30, 8, 'appointment_reminder', 'Reminder', 'Upcoming visit', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(31, 9, 'payment_reminder', 'Pending Invoice', 'Invoice still unpaid', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(32, 10, 'promotion', 'Offer', 'Teeth whitening discount', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(33, 11, 'queue_update', 'Queue', 'Position updated', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(34, 12, 'appointment_reminder', 'Reminder', 'Appointment tomorrow', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(35, 13, 'promotion', 'Offer', 'Cleaning discount', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(36, 14, 'payment_reminder', 'Invoice', 'Please pay soon', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(37, 15, 'appointment_reminder', 'Reminder', 'Visit reminder', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(38, 16, 'queue_update', 'Queue', 'Emergency priority', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(39, 17, 'promotion', 'Offer', 'Whitening special', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(40, 18, 'appointment_reminder', 'Reminder', 'Upcoming visit', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(41, 19, 'payment_reminder', 'Due', 'Invoice reminder', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(42, 20, 'promotion', 'Offer', 'Discount on crowns', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(43, 21, 'queue_update', 'Queue', 'You are #2', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(44, 22, 'appointment_reminder', 'Reminder', 'Visit tomorrow', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(45, 23, 'promotion', 'Offer', 'Free consultation', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(46, 24, 'payment_reminder', 'Due', 'Please pay', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(47, 25, 'queue_update', 'Queue', 'Your turn soon', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(48, 1, 'promotion', 'Clinic Update', 'New services available', 'in-app', NULL, NULL, NULL, NULL, '2026-03-24 21:04:18'),
(49, 29, 'appointment_reminder', 'Appointment Scheduled', 'Your appointment has been scheduled for Mar 26, 2026 at 6:21 PM', 'in-app', NULL, NULL, NULL, NULL, '2026-03-25 11:21:23'),
(50, 29, 'appointment_reminder', 'Appointment Scheduled', 'Your appointment has been scheduled for Mar 29, 2026 at 11:45 AM', 'in-app', NULL, NULL, NULL, NULL, '2026-03-28 09:45:46'),
(51, 30, 'appointment_reminder', 'Appointment Scheduled', 'Your appointment has been scheduled for Mar 29, 2026 at 1:50 PM', 'in-app', NULL, NULL, NULL, NULL, '2026-03-28 09:48:53'),
(52, 30, 'appointment_reminder', 'Appointment Scheduled', 'Your appointment has been scheduled for Apr 03, 2026 at 10:30 AM', 'in-app', NULL, NULL, NULL, NULL, '2026-03-28 17:49:30');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `patient_id`, `token`, `created_at`, `expires_at`) VALUES
(18, 27, '84ab47ffd2e4ef1eec5c6845c646b40ce1c65b7534d9967cb6186b6ef511f701', '2026-03-25 15:21:21', '2026-03-25 16:21:21'),
(19, 27, 'd95c5bcb532fa190140f42be414210875930f6943184e6e2d47bdf1497e15453', '2026-03-25 15:23:13', '2026-03-25 16:23:13'),
(20, 27, '780fdaae93c09afd526e1d2743c33d40c670a9dc176717f9a943a1efeeb7c90e', '2026-03-25 15:24:32', '2026-03-25 16:24:32'),
(21, 27, '2dad2ea98de28fc24a773faf61271099d4d397515d3a8583a4058b09c6b834c6', '2026-03-25 15:34:34', '2026-03-25 16:34:34'),
(22, 27, 'ba98a55304d10b2b016271790a6016d9d685b0184781d71b04c76a1c42b57b82', '2026-03-25 15:55:41', '2026-03-25 16:55:41'),
(23, 27, 'f5a42ff04f40f91bbdeea776e640c46d6b962c4c978446b707474b6e66d34ab5', '2026-03-25 16:10:37', '2026-03-25 17:10:37'),
(24, 27, 'a1948c3a1e4c9647e1c18069f296709aaa8f8f027fd6d21d46757c936f79ad10', '2026-03-25 16:10:56', '2026-03-25 17:10:56'),
(25, 27, '155c8875492f80c14ef690d0310c110bcd4686ac3fe3ebceb61c6753692b0c2c', '2026-03-25 16:11:02', '2026-03-25 17:11:02'),
(26, 27, 'b2b55ffe36d64c62e9f048f2aea3fcdfefd2e8d8e2ca7269117e6859c61b8868', '2026-03-25 16:11:07', '2026-03-25 17:11:07'),
(27, 27, 'ed4b17cc2ca2e79e71d2dfc3176a921ef9ca0e2804c68ea22630099eabf387a6', '2026-03-25 16:12:41', '2026-03-25 17:12:41'),
(28, 27, '761d7c88475fe583904f2475f7a87e527c84eb3f472bffd645f3f766d70fa3fb', '2026-03-25 16:12:47', '2026-03-25 17:12:47'),
(29, 27, 'c328b7480e2eafdd59b3f156369b74c2b7180048ddee7be016b2a003e2ab4605', '2026-03-25 16:15:33', '2026-03-25 17:15:33'),
(30, 27, '0e44d7337616ebf1f64b3069b0a46c049b09c0bf56be795c598e98fa9f36c47c', '2026-03-25 16:35:29', '2026-03-25 17:35:29'),
(31, 27, '58a9ee64b350f5f791134a2f315f97adf06f104b6d2311fa54eb931556b2e65e', '2026-03-25 16:36:24', '2026-03-25 17:36:24'),
(32, 27, '3d90423cfd14ce1276e4e8ec154c034525e457a7a14cf747e965886bfbc73b47', '2026-03-25 16:38:59', '2026-03-25 17:38:59'),
(33, 27, '1fe5d1d65f87244b91276b299b7460ca2aeafd796982da0b6bab1fe6c32c1c75', '2026-03-25 16:39:23', '2026-03-25 17:39:23'),
(34, 27, 'ff44728240bd5623423396be1477bae6b1b3a875185c261a9d53939f618173df', '2026-03-25 16:41:22', '2026-03-25 17:41:22'),
(35, 27, '7c8f2015f4d26ad6a451e64b72f87d401e1860f446db6f87380ad802eca154a9', '2026-03-25 16:42:01', '2026-03-25 17:42:01'),
(36, 27, '329eb96de91d65ec98b8088aba67e96db7e8fefaa4bf1504cc497f175c61fca9', '2026-03-26 09:56:07', '2026-03-26 10:56:07'),
(37, 27, '9c7696ae06cb7c0fedafdf32822039935f0b2ae2821d8a7b82ff2c7847b946c5', '2026-03-26 09:57:39', '2026-03-26 10:57:39'),
(38, 27, '4185f5d69478918ce4ebf6230091ed86e95a869bb26f4c6b9a967f9fa2d1c25c', '2026-03-26 09:57:56', '2026-03-26 10:57:56'),
(39, 27, 'c66423b8e84e3f0c7760136e02c55fee26903588f9be0be7a50637640fe0c0cb', '2026-03-26 09:58:16', '2026-03-26 10:58:16'),
(40, 27, '473f67a27195a27c63ed25501e1fa79dbb7880c8139b94bbb73f6f6901612e18', '2026-03-26 10:04:55', '2026-03-26 11:04:55'),
(41, 27, 'd61c7372c6217ea4d26004d6283645c661f4ba56e8f6b7f9ef40d3aa9cabd6a0', '2026-03-26 10:09:51', '2026-03-26 11:09:51'),
(42, 27, '46ac027ed8cce783946d340c2d6402c5bb17e030d802d2343957e65932d06602', '2026-03-26 10:22:05', '2026-03-26 11:22:05'),
(45, 27, '6600142bc5a40398ee3d97ce0a5dc51da515a3c00abe492b68bc6aa4a492e98c', '2026-03-28 04:43:42', '2026-03-28 05:43:42'),
(46, 27, '8f7c16f2ffa63073f1051f52e471cf6fd5ac80b7cce8459d91e975a093f8e6fb', '2026-03-28 04:47:08', '2026-03-28 05:47:08'),
(47, 27, '6051dac21b279a6b5be06301da8bb28533f10c9cc458abb2359aded59cdce926', '2026-03-28 04:48:39', '2026-03-28 05:48:39'),
(50, 27, '06af817ed93c96338d4d1532c8e18d8d159aa07e57aba610f86c2c16701fbc6f', '2026-03-28 05:18:16', '2026-03-28 06:18:16'),
(51, 27, '98010ba99e0592d656610d9fe6ed041e589c9fc9768d741aef8bce450fc01abc', '2026-03-28 05:18:23', '2026-03-28 06:18:23'),
(52, 27, '2e2705c0bbe7c6e01725c05fa2c6041ef0725a8ff60913a5788f161088820bab', '2026-03-28 05:19:39', '2026-03-28 06:19:39'),
(53, 27, '3e631fc0cce4e704dbd06a497ee3f258f457088eca8950143e8b2dc0163b82d2', '2026-03-28 05:19:59', '2026-03-28 06:19:59');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `emergency_contact_relation` varchar(50) DEFAULT NULL,
  `insurance_provider` varchar(100) DEFAULT NULL,
  `insurance_id` varchar(50) DEFAULT NULL,
  `insurance_type` enum('Private','Social Security','Medicaid','None') DEFAULT 'None',
  `insurance_coverage` int(11) DEFAULT 0,
  `medical_history` text DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `current_medications` text DEFAULT NULL,
  `dental_history` text DEFAULT NULL,
  `last_visit_date` date DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `country` varchar(50) DEFAULT 'LB',
  `points` int(11) DEFAULT 0,
  `subscription_type` enum('none','basic','premium','family') DEFAULT 'none',
  `subscription_start_date` date DEFAULT NULL,
  `subscription_end_date` date DEFAULT NULL,
  `subscription_status` enum('none','pending','active','expired') NOT NULL DEFAULT 'none',
  `referral_code` varchar(20) DEFAULT NULL,
  `referred_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `user_id`, `full_name`, `date_of_birth`, `gender`, `phone`, `email`, `emergency_contact_name`, `emergency_contact_phone`, `emergency_contact_relation`, `insurance_provider`, `insurance_id`, `insurance_type`, `insurance_coverage`, `medical_history`, `allergies`, `current_medications`, `dental_history`, `last_visit_date`, `address`, `country`, `points`, `subscription_type`, `subscription_start_date`, `subscription_end_date`, `subscription_status`, `referral_code`, `referred_by`, `created_at`, `updated_at`, `created_by`) VALUES
(2, 4, 'Patient 2', NULL, 'female', '3000000002', 'p2@mail.com', NULL, NULL, NULL, NULL, NULL, 'None', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 20, 'none', NULL, NULL, 'none', 'REF2', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(3, 5, 'Patient 3', NULL, 'male', '3000000003', 'p3@mail.com', NULL, NULL, NULL, NULL, NULL, 'Private', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 15, 'none', NULL, NULL, 'none', 'REF3', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(4, NULL, 'Patient 4', NULL, 'female', '3000000004', 'p4@mail.com', NULL, NULL, NULL, NULL, NULL, 'None', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 5, 'none', NULL, NULL, 'none', 'REF4', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(5, 7, 'Patient 5', NULL, 'male', '3000000005', 'p5@mail.com', NULL, NULL, NULL, NULL, NULL, 'Private', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 0, 'none', NULL, NULL, 'none', 'REF5', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(6, 8, 'Patient 6', NULL, 'female', '3000000006', 'p6@mail.com', NULL, NULL, NULL, NULL, NULL, 'None', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 8, 'none', NULL, NULL, 'none', 'REF6', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(7, 9, 'Patient 7', NULL, 'male', '3000000007', 'p7@mail.com', NULL, NULL, NULL, NULL, NULL, 'Private', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 12, 'none', NULL, NULL, 'none', 'REF7', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(8, 10, 'Patient 8', NULL, 'female', '3000000008', 'p8@mail.com', NULL, NULL, NULL, NULL, NULL, 'None', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 6, 'none', NULL, NULL, 'none', 'REF8', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(9, 11, 'Patient 9', NULL, 'male', '3000000009', 'p9@mail.com', NULL, NULL, NULL, NULL, NULL, 'Private', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 3, 'none', NULL, NULL, 'none', 'REF9', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(10, 12, 'Patient 10', NULL, 'female', '3000000010', 'p10@mail.com', NULL, NULL, NULL, NULL, NULL, 'None', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 11, 'none', NULL, NULL, 'none', 'REF10', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(11, 13, 'Patient 11', NULL, 'male', '3000000011', 'p11@mail.com', NULL, NULL, NULL, NULL, NULL, 'Private', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 7, 'none', NULL, NULL, 'none', 'REF11', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(12, 14, 'Patient 12', NULL, 'female', '3000000012', 'p12@mail.com', NULL, NULL, NULL, NULL, NULL, 'None', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 9, 'none', NULL, NULL, 'none', 'REF12', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(13, 15, 'Patient 13', NULL, 'male', '3000000013', 'p13@mail.com', NULL, NULL, NULL, NULL, NULL, 'Private', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 4, 'none', NULL, NULL, 'none', 'REF13', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(14, 16, 'Patient 14', NULL, 'female', '3000000014', 'p14@mail.com', NULL, NULL, NULL, NULL, NULL, 'None', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 2, 'none', NULL, NULL, 'none', 'REF14', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(15, 17, 'Patient 15', NULL, 'male', '3000000015', 'p15@mail.com', NULL, NULL, NULL, NULL, NULL, 'Private', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 13, 'none', NULL, NULL, 'none', 'REF15', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(16, 18, 'Patient 16', NULL, 'female', '3000000016', 'p16@mail.com', NULL, NULL, NULL, NULL, NULL, 'None', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 1, 'none', NULL, NULL, 'none', 'REF16', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(17, 19, 'Patient 17', NULL, 'male', '3000000017', 'p17@mail.com', NULL, NULL, NULL, NULL, NULL, 'Private', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 14, 'none', NULL, NULL, 'none', 'REF17', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(18, 20, 'Patient 18', NULL, 'female', '3000000018', 'p18@mail.com', NULL, NULL, NULL, NULL, NULL, 'None', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 5, 'none', NULL, NULL, 'none', 'REF18', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(19, 21, 'Patient 19', NULL, 'male', '3000000019', 'p19@mail.com', NULL, NULL, NULL, NULL, NULL, 'Private', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 16, 'none', NULL, NULL, 'none', 'REF19', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(20, 22, 'Patient 20', NULL, 'female', '3000000020', 'p20@mail.com', NULL, NULL, NULL, NULL, NULL, 'None', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 7, 'none', NULL, NULL, 'none', 'REF20', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(21, 23, 'Patient 21', NULL, 'male', '3000000021', 'p21@mail.com', NULL, NULL, NULL, NULL, NULL, 'Private', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 6, 'none', NULL, NULL, 'none', 'REF21', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(22, 24, 'Patient 22', NULL, 'female', '3000000022', 'p22@mail.com', NULL, NULL, NULL, NULL, NULL, 'None', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 10, 'none', NULL, NULL, 'none', 'REF22', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(23, 25, 'Patient 23', NULL, 'male', '3000000023', 'p23@mail.com', NULL, NULL, NULL, NULL, NULL, 'Private', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 9, 'none', NULL, NULL, 'none', 'REF23', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(24, NULL, 'Walk-in 24', NULL, 'male', '4000000024', 'walk24@mail.com', NULL, NULL, NULL, NULL, NULL, 'None', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 0, 'none', NULL, NULL, 'none', 'REF24', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(25, NULL, 'Walk-in 25', NULL, 'female', '4000000025', 'walk25@mail.com', NULL, NULL, NULL, NULL, NULL, 'None', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 0, 'none', NULL, NULL, 'none', 'REF25', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 2),
(27, 29, 'Zeina Ayoub', NULL, NULL, '+961 70389543', '72230640@students.liu.edu.lb', '', '', '', NULL, NULL, 'None', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 0, 'none', NULL, NULL, 'none', NULL, NULL, '2026-03-24 21:30:54', '2026-03-24 21:30:54', 26),
(28, 30, 'jawad', NULL, 'male', '+961 71217984', '722306@students.liu.edu.lb', '', '', '', NULL, NULL, 'None', 0, NULL, NULL, NULL, NULL, NULL, 'Tyre', 'USA', 0, 'none', NULL, NULL, 'none', NULL, NULL, '2026-03-28 09:47:29', '2026-03-29 16:31:39', 26),
(29, 33, 'Saja Dyab', '2005-11-12', 'female', '+961 81 665 330', 'dyabsaja@gmail.com', 'Fatima haydar', '+961 71532470', 'mother', NULL, NULL, 'None', 0, NULL, NULL, NULL, NULL, NULL, 'tyre', 'USA', 0, 'premium', '2026-03-30', '2027-03-30', 'active', 'F240A658', NULL, '2026-03-29 16:43:52', '2026-03-30 14:29:36', NULL),
(30, 34, 'Ali Dyab', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'None', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'USA', 0, 'family', '2026-03-30', '2027-03-30', 'active', '49C86F50', NULL, '2026-03-30 14:45:00', '2026-03-30 14:50:25', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method` enum('cash','card','insurance','online','check') NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `invoice_id`, `amount`, `payment_date`, `payment_method`, `reference_number`, `notes`, `received_by`) VALUES
(2, 2, 200.00, '2026-03-24 21:04:18', 'card', NULL, NULL, 2),
(3, 6, 600.00, '2026-03-24 21:04:18', 'cash', NULL, NULL, 2);

-- --------------------------------------------------------

--
-- Table structure for table `subscription_payments`
--

CREATE TABLE `subscription_payments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `subscription_type` enum('basic','premium','family') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `payment_date` datetime NOT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `processed_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscription_payments`
--

INSERT INTO `subscription_payments` (`id`, `patient_id`, `subscription_type`, `amount`, `payment_method`, `payment_reference`, `payment_date`, `status`, `processed_by`, `notes`, `created_at`) VALUES
(1, 29, 'premium', 588.00, 'clinic', '', '2026-03-30 17:29:36', 'completed', 32, 'Pending payment at clinic - Please visit assistant', '2026-03-30 14:15:14'),
(2, 30, 'family', 948.00, 'clinic', '', '2026-03-30 17:48:55', 'completed', 32, 'Pending payment at clinic - Please visit assistant', '2026-03-30 14:47:45');

-- --------------------------------------------------------

--
-- Table structure for table `tooth_chart`
--

CREATE TABLE `tooth_chart` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `tooth_number` int(11) NOT NULL,
  `status` enum('healthy','cavity','filled','crown','root-canal','missing','implant','bridge') DEFAULT 'healthy',
  `diagnosis` text DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tooth_chart`
--

INSERT INTO `tooth_chart` (`id`, `patient_id`, `tooth_number`, `status`, `diagnosis`, `treatment`, `notes`, `last_updated`, `updated_by`) VALUES
(2, 2, 14, 'filled', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(3, 3, 19, 'root-canal', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(4, 4, 30, 'missing', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(5, 5, 8, 'healthy', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(6, 6, 3, 'crown', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(7, 7, 8, 'cavity', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(8, 8, 21, 'filled', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(9, 9, 17, 'cavity', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(10, 10, 2, 'missing', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(11, 11, 6, 'healthy', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(12, 12, 6, 'crown', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(13, 13, 9, 'healthy', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(14, 14, 25, 'cavity', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(15, 15, 18, 'root-canal', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(16, 16, 29, 'missing', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(17, 17, 12, 'healthy', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(18, 18, 12, 'crown', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(19, 19, 4, 'healthy', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(20, 20, 27, 'filled', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(21, 21, 10, 'cavity', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(22, 22, 32, 'missing', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(23, 23, 5, 'healthy', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(24, 24, 15, 'crown', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(25, 25, 22, 'healthy', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(26, 28, 21, 'filled', '', '', '', '2026-03-28 17:45:39', 32),
(27, 28, 24, 'cavity', '', '', '', '2026-03-28 17:45:44', 32),
(28, 28, 28, 'missing', '', '', '', '2026-03-28 17:46:10', 32);

-- --------------------------------------------------------

--
-- Table structure for table `treatment_instructions`
--

CREATE TABLE `treatment_instructions` (
  `id` int(11) NOT NULL,
  `treatment_type` varchar(100) NOT NULL,
  `instructions` text NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `treatment_instructions`
--

INSERT INTO `treatment_instructions` (`id`, `treatment_type`, `instructions`, `is_default`, `created_at`) VALUES
(1, 'Cleaning', '• No eating/drinking for 30 minutes\n• Avoid hot beverages for 2 hours\n• Brush gently tonight\n• Use desensitizing toothpaste if sensitive', 1, '2026-03-24 21:04:18'),
(2, 'Filling', '• Do not eat for 2 hours until numbness wears off\n• Avoid hard/sticky foods for 24 hours\n• Brush gently around the area\n• If sensitivity persists, use sensitive toothpaste', 1, '2026-03-24 21:04:18'),
(3, 'Root Canal', '• Avoid chewing on that side for 24 hours\n• Take prescribed antibiotics as directed\n• No hot drinks for 4 hours\n• Temporary crown may feel different - avoid flossing\n• Call if severe pain or swelling', 1, '2026-03-24 21:04:18'),
(4, 'Extraction', '• Do not rinse or spit for 24 hours\n• No drinking through straw for 3 days\n• Apply ice packs for first 24 hours\n• Eat soft foods only\n• Slight bleeding is normal - bite on gauze\n• Call if bleeding persists', 1, '2026-03-24 21:04:18'),
(5, 'Crown', '• Avoid sticky/hard foods for 24 hours\n• Temporary crown - do not floss\n• Permanent crown placement in 2 weeks\n• Sensitivity to hot/cold is normal', 1, '2026-03-24 21:04:18'),
(6, 'Whitening', '• Avoid dark foods/drinks for 48 hours (coffee, tea, wine)\n• No smoking for 24 hours\n• Use whitening toothpaste provided\n• Temporary sensitivity is normal', 1, '2026-03-24 21:04:18'),
(7, 'Implant', '• Soft foods only for 2 weeks\n• No chewing on implant site\n• Apply ice packs\n• Take all prescribed medications\n• Gentle rinsing with salt water\n• Follow up in 1 week', 1, '2026-03-24 21:04:18'),
(8, 'Orthodontics', '• Avoid hard/sticky foods\n• Brush after every meal\n• Use orthodontic wax if brackets irritate\n• Mild soreness is normal\n• Next adjustment in 4 weeks', 1, '2026-03-24 21:04:18');

-- --------------------------------------------------------

--
-- Table structure for table `treatment_plans`
--

CREATE TABLE `treatment_plans` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `teeth_affected` text DEFAULT NULL,
  `estimated_cost` decimal(10,2) DEFAULT NULL,
  `actual_cost` decimal(10,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `status` enum('proposed','approved','in-progress','completed','cancelled') DEFAULT 'proposed',
  `priority` enum('low','medium','high','emergency') DEFAULT 'medium',
  `start_date` date DEFAULT NULL,
  `estimated_end_date` date DEFAULT NULL,
  `actual_end_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `patient_approved` tinyint(1) DEFAULT 0,
  `approval_date` timestamp NULL DEFAULT NULL,
  `approval_signature` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `treatment_plans`
--

INSERT INTO `treatment_plans` (`id`, `patient_id`, `plan_name`, `description`, `teeth_affected`, `estimated_cost`, `actual_cost`, `discount`, `status`, `priority`, `start_date`, `estimated_end_date`, `actual_end_date`, `notes`, `patient_approved`, `approval_date`, `approval_signature`, `created_at`, `updated_at`, `created_by`) VALUES
(2, 2, 'Plan 2', 'Filling treatment', '14', 200.00, 200.00, 0.00, 'completed', 'medium', '2026-03-01', NULL, NULL, NULL, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1),
(3, 3, 'Plan 3', 'Root canal treatment', '19', 800.00, NULL, 0.00, 'in-progress', 'high', '2026-03-01', NULL, NULL, NULL, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1),
(5, 5, 'Plan 5', 'Whitening session', '', 250.00, NULL, 0.00, 'approved', 'low', '2026-03-02', NULL, NULL, NULL, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1),
(6, 6, 'Plan 6', 'Crown placement', '3', 600.00, 600.00, 0.00, 'completed', 'medium', '2026-03-02', NULL, NULL, NULL, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1),
(7, 7, 'Plan 7', 'Cleaning', '8', 100.00, NULL, 0.00, 'approved', 'low', '2026-03-03', NULL, NULL, NULL, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1),
(8, 8, 'Plan 8', 'Filling', '21', 150.00, NULL, 0.00, 'approved', 'medium', '2026-03-03', NULL, NULL, NULL, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1),
(9, 9, 'Plan 9', 'Root canal', '17', 750.00, NULL, 0.00, 'approved', 'high', '2026-03-03', NULL, NULL, NULL, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1),
(10, 10, 'Plan 10', 'Extraction', '2', 250.00, NULL, 0.00, 'approved', 'high', '2026-03-04', NULL, NULL, NULL, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1),
(11, 11, 'Plan 11', 'Whitening', '', 300.00, NULL, 0.00, 'approved', 'low', '2026-03-04', NULL, NULL, NULL, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1),
(12, 12, 'Plan 12', 'Crown', '6', 550.00, NULL, 0.00, 'approved', 'medium', '2026-03-04', NULL, NULL, NULL, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1),
(13, 13, 'Plan 13', 'Cleaning', '9', 100.00, NULL, 0.00, 'approved', 'low', '2026-03-05', NULL, NULL, NULL, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1),
(14, 14, 'Plan 14', 'Filling', '25', 200.00, NULL, 0.00, 'approved', 'medium', '2026-03-05', NULL, NULL, NULL, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1),
(15, 15, 'Plan 15', 'Root canal', '18', 850.00, NULL, 0.00, 'approved', 'high', '2026-03-05', NULL, NULL, NULL, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1),
(16, 16, 'Plan 16', 'Extraction', '29', 350.00, NULL, 0.00, 'approved', 'high', '2026-03-06', NULL, NULL, NULL, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1),
(17, 17, 'Plan 17', 'Whitening', '', 200.00, NULL, 0.00, 'approved', 'low', '2026-03-06', NULL, NULL, NULL, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1),
(18, 18, 'Plan 18', 'Crown', '12', 500.00, NULL, 0.00, 'approved', 'medium', '2026-03-06', NULL, NULL, NULL, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1),
(19, 19, 'Plan 19', 'Cleaning', '4', 120.00, NULL, 0.00, 'approved', 'low', '2026-03-07', NULL, NULL, NULL, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1),
(20, 20, 'Plan 20', 'Filling', '27', 180.00, NULL, 0.00, 'approved', 'medium', '2026-03-07', NULL, NULL, NULL, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1),
(21, 21, 'Plan 21', 'Root canal', '10', 780.00, NULL, 0.00, 'approved', 'high', '2026-03-07', NULL, NULL, NULL, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1),
(22, 22, 'Plan 22', 'Extraction', '32', 300.00, NULL, 0.00, 'approved', 'high', '2026-03-08', NULL, NULL, NULL, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1),
(23, 23, 'Plan 23', 'Whitening', '', 260.00, NULL, 0.00, 'approved', 'low', '2026-03-08', NULL, NULL, NULL, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1),
(24, 24, 'Plan 24', 'Crown', '15', 650.00, NULL, 0.00, 'approved', 'medium', '2026-03-08', NULL, NULL, NULL, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1),
(25, 25, 'Plan 25', 'Cleaning', '22', 110.00, NULL, 0.00, 'approved', 'low', '2026-03-09', NULL, NULL, NULL, 0, NULL, NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1),
(26, 28, 'Plan 8', 'd', '21', 1244.00, NULL, 0.00, 'in-progress', 'medium', '2026-03-28', '2026-03-31', NULL, '', 1, '2026-03-28 17:08:14', NULL, '2026-03-28 17:07:42', '2026-03-28 17:33:35', 32);

-- --------------------------------------------------------

--
-- Table structure for table `treatment_steps`
--

CREATE TABLE `treatment_steps` (
  `id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `step_number` int(11) NOT NULL,
  `procedure_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `tooth_numbers` text DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','in-progress','completed','skipped') DEFAULT 'pending',
  `completed_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `treatment_steps`
--

INSERT INTO `treatment_steps` (`id`, `plan_id`, `step_number`, `procedure_name`, `description`, `tooth_numbers`, `duration_minutes`, `cost`, `status`, `completed_date`, `notes`, `created_at`) VALUES
(2, 2, 1, 'Composite Filling', NULL, NULL, 45, 200.00, 'completed', NULL, NULL, '2026-03-24 21:04:18'),
(3, 3, 1, 'Canal Preparation', NULL, NULL, 60, 400.00, 'in-progress', NULL, NULL, '2026-03-24 21:04:18'),
(5, 5, 1, 'Whitening Gel Application', NULL, NULL, 50, 250.00, 'pending', NULL, NULL, '2026-03-24 21:04:18'),
(6, 6, 1, 'Crown Fixation', NULL, NULL, 60, 600.00, 'completed', NULL, NULL, '2026-03-24 21:04:18'),
(7, 7, 1, 'Scaling', NULL, NULL, 30, 100.00, 'pending', NULL, NULL, '2026-03-24 21:04:18'),
(8, 8, 1, 'Filling', NULL, NULL, 45, 150.00, 'pending', NULL, NULL, '2026-03-24 21:04:18'),
(9, 9, 1, 'Root Canal', NULL, NULL, 60, 750.00, 'pending', NULL, NULL, '2026-03-24 21:04:18'),
(10, 10, 1, 'Extraction', NULL, NULL, 40, 250.00, 'pending', NULL, NULL, '2026-03-24 21:04:18'),
(11, 11, 1, 'Whitening', NULL, NULL, 45, 300.00, 'pending', NULL, NULL, '2026-03-24 21:04:18'),
(12, 12, 1, 'Crown', NULL, NULL, 60, 550.00, 'pending', NULL, NULL, '2026-03-24 21:04:18'),
(13, 13, 1, 'Cleaning', NULL, NULL, 30, 100.00, 'pending', NULL, NULL, '2026-03-24 21:04:18'),
(14, 14, 1, 'Filling', NULL, NULL, 45, 200.00, 'pending', NULL, NULL, '2026-03-24 21:04:18'),
(15, 15, 1, 'Root Canal', NULL, NULL, 60, 850.00, 'pending', NULL, NULL, '2026-03-24 21:04:18'),
(16, 16, 1, 'Extraction', NULL, NULL, 40, 350.00, 'pending', NULL, NULL, '2026-03-24 21:04:18'),
(17, 17, 1, 'Whitening', NULL, NULL, 45, 200.00, 'pending', NULL, NULL, '2026-03-24 21:04:18'),
(18, 18, 1, 'Crown', NULL, NULL, 60, 500.00, 'pending', NULL, NULL, '2026-03-24 21:04:18'),
(19, 19, 1, 'Cleaning', NULL, NULL, 30, 120.00, 'pending', NULL, NULL, '2026-03-24 21:04:18'),
(20, 20, 1, 'Filling', NULL, NULL, 45, 180.00, 'pending', NULL, NULL, '2026-03-24 21:04:18'),
(21, 21, 1, 'Root Canal', NULL, NULL, 60, 780.00, 'pending', NULL, NULL, '2026-03-24 21:04:18'),
(22, 22, 1, 'Extraction', NULL, NULL, 40, 300.00, 'pending', NULL, NULL, '2026-03-24 21:04:18'),
(23, 23, 1, 'Whitening', NULL, NULL, 45, 260.00, 'pending', NULL, NULL, '2026-03-24 21:04:18'),
(24, 24, 1, 'Crown', NULL, NULL, 60, 650.00, 'pending', NULL, NULL, '2026-03-24 21:04:18'),
(25, 25, 1, 'Cleaning', NULL, NULL, 30, 110.00, 'pending', NULL, NULL, '2026-03-24 21:04:18'),
(26, 26, 1, 'filling', 'wht', '21', 30, 122.00, 'completed', NULL, 'e', '2026-03-28 17:08:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('doctor','assistant','patient') NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `phone` varchar(20) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`, `is_admin`, `phone`, `profile_image`, `created_at`, `updated_at`, `is_active`, `last_login`) VALUES
(1, 'doctor1', 'doctor@clinic.com', 'hash1', 'Dr. John Smith', 'doctor', 0, '1111111111', NULL, '2026-03-24 21:04:18', '2026-03-30 18:02:53', 1, NULL),
(2, 'assistant1', 'assistant@clinic.com', 'hash2', 'Anna White', 'assistant', 0, '2222222222', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1, NULL),
(4, 'patient2', 'p2@mail.com', 'hash', 'Patient 2', 'patient', 0, '3000000002', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1, NULL),
(5, 'patient3', 'p3@mail.com', 'hash', 'Patient 3', 'patient', 0, '3000000003', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1, NULL),
(7, 'patient5', 'p5@mail.com', 'hash', 'Patient 5', 'patient', 0, '3000000005', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1, NULL),
(8, 'patient6', 'p6@mail.com', 'hash', 'Patient 6', 'patient', 0, '3000000006', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1, NULL),
(9, 'patient7', 'p7@mail.com', 'hash', 'Patient 7', 'patient', 0, '3000000007', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1, NULL),
(10, 'patient8', 'p8@mail.com', 'hash', 'Patient 8', 'patient', 0, '3000000008', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1, NULL),
(11, 'patient9', 'p9@mail.com', 'hash', 'Patient 9', 'patient', 0, '3000000009', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1, NULL),
(12, 'patient10', 'p10@mail.com', 'hash', 'Patient 10', 'patient', 0, '3000000010', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1, NULL),
(13, 'patient11', 'p11@mail.com', 'hash', 'Patient 11', 'patient', 0, '3000000011', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1, NULL),
(14, 'patient12', 'p12@mail.com', 'hash', 'Patient 12', 'patient', 0, '3000000012', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1, NULL),
(15, 'patient13', 'p13@mail.com', 'hash', 'Patient 13', 'patient', 0, '3000000013', NULL, '2026-03-24 21:04:18', '2026-03-30 18:02:40', 0, NULL),
(16, 'patient14', 'p14@mail.com', 'hash', 'Patient 14', 'patient', 0, '3000000014', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1, NULL),
(17, 'patient15', 'p15@mail.com', 'hash', 'Patient 15', 'patient', 0, '3000000015', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1, NULL),
(18, 'patient16', 'p16@mail.com', 'hash', 'Patient 16', 'patient', 0, '3000000016', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1, NULL),
(19, 'patient17', 'p17@mail.com', 'hash', 'Patient 17', 'patient', 0, '3000000017', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1, NULL),
(20, 'patient18', 'p18@mail.com', 'hash', 'Patient 18', 'patient', 0, '3000000018', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1, NULL),
(21, 'patient19', 'p19@mail.com', 'hash', 'Patient 19', 'patient', 0, '3000000019', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1, NULL),
(22, 'patient20', 'p20@mail.com', 'hash', 'Patient 20', 'patient', 0, '3000000020', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1, NULL),
(23, 'patient21', 'p21@mail.com', 'hash', 'Patient 21', 'patient', 0, '3000000021', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1, NULL),
(24, 'patient22', 'p22@mail.com', 'hash', 'Patient 22', 'patient', 0, '3000000022', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1, NULL),
(25, 'patient23', 'p23@mail.com', 'hash', 'Patient 23', 'patient', 0, '3000000023', NULL, '2026-03-24 21:04:18', '2026-03-24 21:04:18', 1, NULL),
(26, 'admin', 'admin@clinic.com', '$2y$10$y4e0kIZn.g.jc7N7j6sGQuf8u9mAvCRoStNvYUB8CUEjOE0PaGMEa', 'Administrator', 'doctor', 1, '6666666666666666', NULL, '2026-03-24 21:04:18', '2026-03-30 18:21:59', 1, '2026-03-30 18:21:59'),
(29, '72230640', '72230640@students.liu.edu.lb', '$2y$10$eoWH3FcYwDSauj5hrCzSWeeFzMdbYDE.G4mZvoQA9oin2fD6DkQrW', 'Zeina Ayoub', 'patient', 0, '+96170389543', NULL, '2026-03-24 21:30:54', '2026-03-28 09:21:55', 1, '2026-03-28 09:21:55'),
(30, '722306', '722306@students.liu.edu.lb', '$2y$10$Wmi5dKdKtV9bVqc9RebqMe1ULjJOFur6odoZe/wiXcM/o2j1pmlb6', 'jawad', 'patient', 0, '+961 71217984', NULL, '2026-03-28 09:47:30', '2026-03-30 18:02:49', 1, NULL),
(32, 'DrAli', 'DrAli@clinic.com', '$2y$10$cZoXYeItBuSp8i6ZUiIDku/Z6JqvShtWb5ahifl4ohaDVrSfrhg7.', 'Dr. Ali', 'doctor', 0, '6835621556', NULL, '2026-03-28 16:34:41', '2026-03-30 18:08:44', 0, '2026-03-30 18:08:44'),
(33, 'sajadyab', 'dyabsaja@gmail.com', '$2y$10$S9A2F4x6VspVaMiPVm8Tv.lqVtvAVu51L5Ju0CNajyuTRYMBoHdEu', 'Saja Dyab', 'patient', 0, '+961 81 665 330', NULL, '2026-03-30 11:06:19', '2026-03-30 18:22:25', 1, '2026-03-30 18:22:25'),
(34, 'alidyab', 'dyabali@gmail.com', '$2y$10$RHFYIrMMLCc89qkqMEWOeuJ01k.vPsdo4tW22l6Ljv3Kv/ZgFkqvm', 'Ali Dyab', 'patient', 0, NULL, NULL, '2026-03-30 14:43:08', '2026-03-30 14:51:36', 1, '2026-03-30 14:51:36'),
(35, 'Mohammad', 'mohammad@gmail.com', '$2y$10$GbOhEVD3zxeuLOKc3PLp9u7bPI3P1kd1moxD4i5bF/0lTIxQ74cB.', 'Mohammad no', 'doctor', 0, '+961 70 339 444', NULL, '2026-03-30 17:42:16', '2026-03-30 17:42:16', 1, NULL),
(36, 'Rayan', 'rayan@gmail.com', '$2y$10$AWsuBpgikz1Plt4gVnHrougouaahR8YPK05Z29NXb31uK6U0FA7ey', 'Rayan nofamilyname', 'doctor', 0, '+961 70 333 440', NULL, '2026-03-30 18:01:11', '2026-03-30 18:01:11', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `waiting_queue`
--

CREATE TABLE `waiting_queue` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `patient_name` varchar(100) DEFAULT NULL,
  `queue_type` enum('daily','weekly') NOT NULL,
  `priority` enum('emergency','high','medium','low') DEFAULT 'medium',
  `reason` varchar(100) DEFAULT NULL,
  `preferred_treatment` varchar(100) DEFAULT NULL,
  `preferred_day` varchar(20) DEFAULT NULL,
  `estimated_wait_minutes` int(11) DEFAULT NULL,
  `position` int(11) DEFAULT NULL,
  `status` enum('waiting','notified','checked-in','cancelled') DEFAULT 'waiting',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notified_at` timestamp NULL DEFAULT NULL,
  `checked_in_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `waiting_queue`
--

INSERT INTO `waiting_queue` (`id`, `patient_id`, `patient_name`, `queue_type`, `priority`, `reason`, `preferred_treatment`, `preferred_day`, `estimated_wait_minutes`, `position`, `status`, `joined_at`, `notified_at`, `checked_in_at`, `notes`) VALUES
(1, NULL, NULL, 'daily', 'medium', 'Cleaning', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(2, 2, NULL, 'daily', 'high', 'Pain', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(3, 3, NULL, 'weekly', 'medium', 'Root Canal', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(4, 4, NULL, 'daily', 'high', 'Emergency', NULL, NULL, NULL, NULL, 'checked-in', '2026-03-24 21:04:18', '2026-03-30 17:09:51', '2026-03-30 17:09:52', NULL),
(5, 5, NULL, 'weekly', 'low', 'Whitening', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(6, 6, NULL, 'daily', 'medium', 'Crown', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(7, 7, NULL, 'daily', 'medium', 'Cleaning', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(8, 8, NULL, 'weekly', 'medium', 'Filling', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(9, 9, NULL, 'daily', 'high', 'Pain', NULL, NULL, NULL, NULL, 'checked-in', '2026-03-24 21:04:18', '2026-03-30 17:09:55', '2026-03-30 17:09:57', NULL),
(10, 10, NULL, 'weekly', 'medium', 'Extraction', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(11, 11, NULL, 'daily', 'low', 'Whitening', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(12, 12, NULL, 'weekly', 'medium', 'Crown', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(13, 13, NULL, 'daily', 'medium', 'Cleaning', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(14, 14, NULL, 'weekly', 'medium', 'Filling', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(15, 15, NULL, 'daily', 'high', 'Root Canal', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(16, 16, NULL, 'weekly', 'high', 'Extraction', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(17, 17, NULL, 'daily', 'low', 'Whitening', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(18, 18, NULL, 'weekly', 'medium', 'Crown', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(19, 19, NULL, 'daily', 'medium', 'Cleaning', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(20, 20, NULL, 'weekly', 'medium', 'Filling', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(21, 21, NULL, 'daily', 'high', 'Root Canal', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(22, 22, NULL, 'weekly', 'high', 'Extraction', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(23, 23, NULL, 'daily', 'low', 'Whitening', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(24, 24, NULL, 'weekly', 'medium', 'Crown', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(25, 25, NULL, 'daily', 'medium', 'Cleaning', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(26, NULL, NULL, 'daily', 'medium', 'Cleaning', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(27, 2, NULL, 'daily', 'high', 'Pain', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(28, 3, NULL, 'weekly', 'medium', 'Root Canal', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(29, 4, NULL, 'daily', 'high', 'Emergency', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(30, 5, NULL, 'weekly', 'low', 'Whitening', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(31, 6, NULL, 'daily', 'medium', 'Crown', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(32, 7, NULL, 'daily', 'medium', 'Cleaning', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(33, 8, NULL, 'weekly', 'medium', 'Filling', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(34, 9, NULL, 'daily', 'high', 'Pain', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(35, 10, NULL, 'weekly', 'medium', 'Extraction', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(36, 11, NULL, 'daily', 'low', 'Whitening', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(37, 12, NULL, 'weekly', 'medium', 'Crown', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(38, 13, NULL, 'daily', 'medium', 'Cleaning', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(39, 14, NULL, 'weekly', 'medium', 'Filling', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(40, 15, NULL, 'daily', 'high', 'Root Canal', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(41, 16, NULL, 'weekly', 'high', 'Extraction', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(42, 17, NULL, 'daily', 'low', 'Whitening', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(43, 18, NULL, 'weekly', 'medium', 'Crown', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(44, 19, NULL, 'daily', 'medium', 'Cleaning', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(45, 20, NULL, 'weekly', 'medium', 'Filling', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(46, 21, NULL, 'daily', 'high', 'Root Canal', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(47, 22, NULL, 'weekly', 'high', 'Extraction', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(48, 23, NULL, 'daily', 'low', 'Whitening', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(49, 24, NULL, 'weekly', 'medium', 'Crown', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(50, 25, NULL, 'daily', 'medium', 'Cleaning', NULL, NULL, NULL, NULL, 'notified', '2026-03-24 21:04:18', '2026-03-30 17:09:50', NULL, NULL),
(51, NULL, NULL, 'daily', 'medium', 'Cleaning', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(52, 2, NULL, 'daily', 'high', 'Pain', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(53, 3, NULL, 'weekly', 'medium', 'Root Canal', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(54, 4, NULL, 'daily', 'high', 'Emergency', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(55, 5, NULL, 'weekly', 'low', 'Whitening', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(56, 6, NULL, 'daily', 'medium', 'Crown', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(57, 7, NULL, 'daily', 'medium', 'Cleaning', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(58, 8, NULL, 'weekly', 'medium', 'Filling', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(59, 9, NULL, 'daily', 'high', 'Pain', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(60, 10, NULL, 'weekly', 'medium', 'Extraction', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(61, 11, NULL, 'daily', 'low', 'Whitening', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(62, 12, NULL, 'weekly', 'medium', 'Crown', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(63, 13, NULL, 'daily', 'medium', 'Cleaning', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(64, 14, NULL, 'weekly', 'medium', 'Filling', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(65, 15, NULL, 'daily', 'high', 'Root Canal', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(66, 16, NULL, 'weekly', 'high', 'Extraction', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(67, 17, NULL, 'daily', 'low', 'Whitening', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(68, 18, NULL, 'weekly', 'medium', 'Crown', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(69, 19, NULL, 'daily', 'medium', 'Cleaning', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(70, 20, NULL, 'weekly', 'medium', 'Filling', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(71, 21, NULL, 'daily', 'high', 'Root Canal', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(72, 22, NULL, 'weekly', 'high', 'Extraction', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(73, 23, NULL, 'daily', 'low', 'Whitening', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(74, 24, NULL, 'weekly', 'medium', 'Crown', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(75, 25, NULL, 'daily', 'medium', 'Cleaning', NULL, NULL, NULL, NULL, 'waiting', '2026-03-24 21:04:18', NULL, NULL, NULL),
(76, 29, NULL, 'daily', 'medium', '.', 'filling', 'Monday', NULL, NULL, 'checked-in', '2026-03-30 11:18:21', NULL, '2026-03-30 17:12:55', NULL),
(77, 29, NULL, 'daily', 'medium', 'm', '', 'Monday', NULL, NULL, 'checked-in', '2026-03-30 11:19:21', '2026-03-30 17:10:04', '2026-03-30 17:13:01', NULL),
(78, 29, 'Saja Dyab', 'weekly', 'emergency', '.', '', 'Friday', NULL, NULL, 'waiting', '2026-03-30 12:53:15', NULL, NULL, NULL),
(79, 29, 'Saja Dyab', 'weekly', 'high', '.', '', 'Thursday', NULL, NULL, 'waiting', '2026-03-30 13:42:12', NULL, NULL, NULL),
(80, 29, 'Saja Dyab', 'weekly', 'emergency', '.', '', 'Wednesday', NULL, NULL, 'waiting', '2026-03-30 14:37:33', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `xrays`
--

CREATE TABLE `xrays` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(50) DEFAULT NULL,
  `xray_type` enum('Panoramic','Bitewing','Periapical','CBCT','Intraoral','Other') DEFAULT 'Other',
  `tooth_numbers` text DEFAULT NULL,
  `findings` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `uploaded_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `xrays`
--

INSERT INTO `xrays` (`id`, `patient_id`, `file_name`, `file_path`, `file_size`, `mime_type`, `xray_type`, `tooth_numbers`, `findings`, `notes`, `uploaded_at`, `uploaded_by`) VALUES
(2, 2, 'x2.jpg', '/xrays/x2.jpg', NULL, NULL, 'Periapical', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(3, 3, 'x3.jpg', '/xrays/x3.jpg', NULL, NULL, 'Panoramic', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(4, 4, 'x4.jpg', '/xrays/x4.jpg', NULL, NULL, 'Periapical', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(5, 5, 'x5.jpg', '/xrays/x5.jpg', NULL, NULL, 'Other', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(6, 6, 'x6.jpg', '/xrays/x6.jpg', NULL, NULL, 'CBCT', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(7, 7, 'x7.jpg', '/xrays/x7.jpg', NULL, NULL, 'Bitewing', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(8, 8, 'x8.jpg', '/xrays/x8.jpg', NULL, NULL, 'Panoramic', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(9, 9, 'x9.jpg', '/xrays/x9.jpg', NULL, NULL, 'Periapical', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(10, 10, 'x10.jpg', '/xrays/x10.jpg', NULL, NULL, 'Other', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(11, 11, 'x11.jpg', '/xrays/x11.jpg', NULL, NULL, 'CBCT', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(12, 12, 'x12.jpg', '/xrays/x12.jpg', NULL, NULL, 'Bitewing', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(13, 13, 'x13.jpg', '/xrays/x13.jpg', NULL, NULL, 'Panoramic', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(14, 14, 'x14.jpg', '/xrays/x14.jpg', NULL, NULL, 'Periapical', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(15, 15, 'x15.jpg', '/xrays/x15.jpg', NULL, NULL, 'CBCT', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(16, 16, 'x16.jpg', '/xrays/x16.jpg', NULL, NULL, 'Other', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(17, 17, 'x17.jpg', '/xrays/x17.jpg', NULL, NULL, 'Bitewing', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(18, 18, 'x18.jpg', '/xrays/x18.jpg', NULL, NULL, 'Panoramic', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(19, 19, 'x19.jpg', '/xrays/x19.jpg', NULL, NULL, 'Periapical', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(20, 20, 'x20.jpg', '/xrays/x20.jpg', NULL, NULL, 'CBCT', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(21, 21, 'x21.jpg', '/xrays/x21.jpg', NULL, NULL, 'Other', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(22, 22, 'x22.jpg', '/xrays/x22.jpg', NULL, NULL, 'Panoramic', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(23, 23, 'x23.jpg', '/xrays/x23.jpg', NULL, NULL, 'Bitewing', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(24, 24, 'x24.jpg', '/xrays/x24.jpg', NULL, NULL, 'CBCT', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(25, 25, 'x25.jpg', '/xrays/x25.jpg', NULL, NULL, 'Other', NULL, NULL, NULL, '2026-03-24 21:04:18', 1),
(26, 28, '69c953fd87487.jpeg', 'C:/xampp/htdocs/Dental/assets/uploads/xrays/69c953fd87487.jpeg', 288480, 'image/jpeg', 'Panoramic', NULL, '', '', '2026-03-29 16:31:57', 32);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_slot` (`appointment_date`,`appointment_time`,`chair_number`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_date` (`appointment_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_patient` (`patient_id`),
  ADD KEY `idx_doctor` (`doctor_id`),
  ADD KEY `idx_appointments_date_status` (`appointment_date`,`status`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `clinic_settings`
--
ALTER TABLE `clinic_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_patient` (`patient_id`),
  ADD KEY `idx_status` (`payment_status`),
  ADD KEY `idx_date` (`invoice_date`),
  ADD KEY `idx_invoices_patient_status` (`patient_id`,`payment_status`);

--
-- Indexes for table `monthly_expenses`
--
ALTER TABLE `monthly_expenses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_month` (`month_year`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `related_appointment_id` (`related_appointment_id`),
  ADD KEY `related_invoice_id` (`related_invoice_id`),
  ADD KEY `idx_notifications_user_read` (`user_id`,`read_at`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `referral_code` (`referral_code`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `referred_by` (`referred_by`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_phone` (`phone`),
  ADD KEY `idx_insurance` (`insurance_type`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `tooth_chart`
--
ALTER TABLE `tooth_chart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_patient_tooth` (`patient_id`,`tooth_number`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `treatment_instructions`
--
ALTER TABLE `treatment_instructions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `treatment_plans`
--
ALTER TABLE `treatment_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_treatment_plans_patient` (`patient_id`);

--
-- Indexes for table `treatment_steps`
--
ALTER TABLE `treatment_steps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `waiting_queue`
--
ALTER TABLE `waiting_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `xrays`
--
ALTER TABLE `xrays`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `clinic_settings`
--
ALTER TABLE `clinic_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `monthly_expenses`
--
ALTER TABLE `monthly_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tooth_chart`
--
ALTER TABLE `tooth_chart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `treatment_instructions`
--
ALTER TABLE `treatment_instructions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `treatment_plans`
--
ALTER TABLE `treatment_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `treatment_steps`
--
ALTER TABLE `treatment_steps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `waiting_queue`
--
ALTER TABLE `waiting_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `xrays`
--
ALTER TABLE `xrays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_transactions_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `invoices_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`related_appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notifications_ibfk_3` FOREIGN KEY (`related_invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_resets_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `patients_ibfk_2` FOREIGN KEY (`referred_by`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `patients_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  ADD CONSTRAINT `subscription_payments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subscription_payments_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tooth_chart`
--
ALTER TABLE `tooth_chart`
  ADD CONSTRAINT `tooth_chart_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tooth_chart_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `treatment_plans`
--
ALTER TABLE `treatment_plans`
  ADD CONSTRAINT `treatment_plans_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `treatment_plans_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `treatment_steps`
--
ALTER TABLE `treatment_steps`
  ADD CONSTRAINT `treatment_steps_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `treatment_plans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `waiting_queue`
--
ALTER TABLE `waiting_queue`
  ADD CONSTRAINT `waiting_queue_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `xrays`
--
ALTER TABLE `xrays`
  ADD CONSTRAINT `xrays_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `xrays_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Optional migrations (existing databases): align `patients` + `inventory` with structure above
-- Run manually if upgrading; skip columns that already exist.
--
-- patients: single address, drop legacy columns, reorder subscription_status
-- ALTER TABLE `patients`
--   ADD COLUMN `address` varchar(255) DEFAULT NULL AFTER `last_visit_date`,
--   ADD COLUMN `subscription_status` enum('none','pending','active','expired') NOT NULL DEFAULT 'none' AFTER `subscription_end_date`;
-- UPDATE `patients` SET `address` = TRIM(BOTH ', ' FROM CONCAT_WS(', ',
--   NULLIF(TRIM(`address_line1`), ''), NULLIF(TRIM(`address_line2`), ''),
--   NULLIF(TRIM(`city`), ''), NULLIF(TRIM(`state`), ''), NULLIF(TRIM(`postal_code`), '')
-- )) WHERE `address` IS NULL OR `address` = '';
-- ALTER TABLE `patients`
--   DROP COLUMN `address_line1`, DROP COLUMN `address_line2`, DROP COLUMN `city`,
--   DROP COLUMN `state`, DROP COLUMN `postal_code`,
--   DROP COLUMN `past_surgeries`, DROP COLUMN `chronic_conditions`, DROP COLUMN `previous_dentist`,
--   MODIFY COLUMN `country` varchar(50) DEFAULT 'LB';

-- inventory: remove extra supplier / tracking columns
-- ALTER TABLE `inventory`
--   DROP COLUMN `supplier_phone`, DROP COLUMN `supplier_email`,
--   DROP COLUMN `lot_number`, DROP COLUMN `location`, DROP COLUMN `barcode`;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

