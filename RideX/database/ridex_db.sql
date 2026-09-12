-- ========================================================
-- RideX - Smart Multi-Vehicle Rental, Booking & Courier System
-- Database: ridex_db
-- Compatibility: MySQL 5.7+ / MariaDB / MySQL 8.x / phpMyAdmin
-- ========================================================

CREATE DATABASE IF NOT EXISTS `ridex_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ridex_db`;

-- Drop tables in proper dependency order if re-importing
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `ratings`;
DROP TABLE IF EXISTS `courier_tracking`;
DROP TABLE IF EXISTS `courier_bookings`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `rentals`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `vehicles`;
DROP TABLE IF EXISTS `admins`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- ========================================================
-- 1. Table: users
-- ========================================================
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(120) NOT NULL UNIQUE,
  `phone` VARCHAR(20) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `address` TEXT NULL,
  `avatar` VARCHAR(255) DEFAULT 'default-avatar.png',
  `status` ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 2. Table: admins
-- ========================================================
CREATE TABLE `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL DEFAULT 'System Administrator',
  `role` VARCHAR(50) NOT NULL DEFAULT 'Super Admin',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 3. Table: vehicles
-- ========================================================
CREATE TABLE `vehicles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `category` ENUM('Bike', 'Scooter', 'Auto Rickshaw', 'Car', 'SUV', 'Van', 'Commercial Vehicle') NOT NULL,
  `brand` VARCHAR(60) NOT NULL,
  `model_year` INT NOT NULL,
  `plate_number` VARCHAR(30) NOT NULL UNIQUE,
  `seating_capacity` INT NOT NULL DEFAULT 1,
  `fuel_type` ENUM('Petrol', 'Diesel', 'Electric', 'CNG', 'Hybrid') NOT NULL DEFAULT 'Petrol',
  `transmission` ENUM('Manual', 'Automatic') NOT NULL DEFAULT 'Manual',
  `image_url` VARCHAR(255) NOT NULL,
  `base_fare` DECIMAL(10,2) NOT NULL DEFAULT 30.00,
  `price_per_km` DECIMAL(10,2) NOT NULL DEFAULT 10.00,
  `price_per_day` DECIMAL(10,2) NOT NULL DEFAULT 500.00,
  `status` ENUM('available', 'booked', 'rented', 'maintenance') NOT NULL DEFAULT 'available',
  `description` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_category` (`category`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 4. Table: bookings (Ride Bookings)
-- ========================================================
CREATE TABLE `bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_code` VARCHAR(25) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `vehicle_id` INT NOT NULL,
  `pickup_location` VARCHAR(255) NOT NULL,
  `drop_location` VARCHAR(255) NOT NULL,
  `ride_date` DATE NOT NULL,
  `ride_time` TIME NOT NULL,
  `distance_km` DECIMAL(8,2) NOT NULL,
  `base_fare` DECIMAL(10,2) NOT NULL,
  `rate_per_km` DECIMAL(10,2) NOT NULL,
  `total_fare` DECIMAL(10,2) NOT NULL,
  `payment_method` ENUM('Cash', 'Card', 'UPI', 'Wallet', 'Razorpay') NOT NULL DEFAULT 'Cash',
  `payment_status` ENUM('Pending', 'Paid', 'Refunded') NOT NULL DEFAULT 'Pending',
  `status` ENUM('Payment Pending', 'Confirmed', 'Driver Assigned', 'On The Way', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Payment Pending',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE,
  INDEX `idx_booking_code` (`booking_code`),
  INDEX `idx_ride_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 5. Table: rentals (Vehicle Rentals)
-- ========================================================
CREATE TABLE `rentals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `rental_code` VARCHAR(25) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `vehicle_id` INT NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `total_days` INT NOT NULL,
  `price_per_day` DECIMAL(10,2) NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `pickup_address` VARCHAR(255) NOT NULL,
  `driving_license_number` VARCHAR(60) NOT NULL,
  `status` ENUM('Pending Approval', 'Active', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending Approval',
  `payment_status` ENUM('Pending', 'Paid') NOT NULL DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE,
  INDEX `idx_rental_code` (`rental_code`),
  INDEX `idx_rental_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 6. Table: courier_bookings
-- ========================================================
CREATE TABLE `courier_bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tracking_code` VARCHAR(30) NOT NULL UNIQUE,
  `user_id` INT NULL,
  `sender_name` VARCHAR(100) NOT NULL,
  `sender_phone` VARCHAR(20) NOT NULL,
  `pickup_address` TEXT NOT NULL,
  `receiver_name` VARCHAR(100) NOT NULL,
  `receiver_phone` VARCHAR(20) NOT NULL,
  `delivery_address` TEXT NOT NULL,
  `parcel_type` VARCHAR(80) NOT NULL,
  `parcel_weight` DECIMAL(6,2) NOT NULL,
  `delivery_option` ENUM('Standard Delivery', 'Express Delivery', 'Same Day Delivery') NOT NULL DEFAULT 'Standard Delivery',
  `delivery_fee` DECIMAL(10,2) NOT NULL,
  `payment_status` ENUM('Pending', 'Paid') NOT NULL DEFAULT 'Pending',
  `current_status` ENUM('Payment Pending', 'Booking Confirmed', 'Pickup Assigned', 'Picked Up', 'In Transit', 'Out for Delivery', 'Delivered', 'Cancelled') NOT NULL DEFAULT 'Payment Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_tracking_code` (`tracking_code`),
  INDEX `idx_courier_status` (`current_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 7. Table: courier_tracking (History Milestones)
-- ========================================================
CREATE TABLE `courier_tracking` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `courier_id` INT NOT NULL,
  `status` ENUM('Booking Confirmed', 'Pickup Assigned', 'Picked Up', 'In Transit', 'Out for Delivery', 'Delivered', 'Cancelled') NOT NULL,
  `location` VARCHAR(150) NOT NULL,
  `remarks` TEXT NULL,
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`courier_id`) REFERENCES `courier_bookings`(`id`) ON DELETE CASCADE,
  INDEX `idx_courier_id` (`courier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 8. Table: payments
-- ========================================================
CREATE TABLE `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_type` ENUM('ride','rental','courier') NOT NULL,
  `booking_id` INT NOT NULL,
  `razorpay_order_id` VARCHAR(100) NULL,
  `razorpay_payment_id` VARCHAR(100) NULL,
  `razorpay_signature` VARCHAR(255) NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'INR',
  `status` ENUM('created','paid','failed') NOT NULL DEFAULT 'created',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 9. Table: ratings
-- ========================================================
CREATE TABLE `ratings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `booking_type` ENUM('ride', 'rental') NOT NULL DEFAULT 'ride',
  `booking_id` INT NOT NULL,
  `vehicle_id` INT NOT NULL,
  `rating` INT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `review` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 9. Table: notifications
-- ========================================================
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `link` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_notification_user` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- SEED DATA
-- ========================================================

-- Admins: username 'admin', password 'admin123'
INSERT INTO `admins` (`id`, `username`, `email`, `password`, `full_name`, `role`) VALUES
(1, 'admin', 'admin@ridex.com', '$2y$10$HfB1WX6TmUlpaOPeI7rDzeG95WXYeciz2DmFVx6v1THZHFHdzlrdW', 'RideX Chief Admin', 'Super Admin');

-- Users: email 'user@example.com', password 'user123'
INSERT INTO `users` (`id`, `full_name`, `email`, `phone`, `password`, `address`, `status`) VALUES
(1, 'Alex Morgan', 'user@example.com', '+1 555-0199', '$2y$10$vN0oH1mZ4sC2XK3bYlS8x.L9zJ7X1wIr5S6g0e2c8b4d9f1h3j5l7', '742 Evergreen Terrace, Springfield', 'active'),
(2, 'Sarah Jenkins', 'sarah.j@example.com', '+1 555-0245', '$2y$10$vN0oH1mZ4sC2XK3bYlS8x.L9zJ7X1wIr5S6g0e2c8b4d9f1h3j5l7', '104 Ocean View Avenue, Metro City', 'active');

-- Vehicles (Covering all 7 required categories with high-quality unsplash car/bike photos)
INSERT INTO `vehicles` (`id`, `name`, `category`, `brand`, `model_year`, `plate_number`, `seating_capacity`, `fuel_type`, `transmission`, `image_url`, `base_fare`, `price_per_km`, `price_per_day`, `status`, `description`) VALUES
-- 1. Bike
(1, 'Yamaha MT-15 V2', 'Bike', 'Yamaha', 2023, 'RX-BK-101', 2, 'Petrol', 'Manual', 'https://images.unsplash.com/photo-1558981806-ec527fa84c39?auto=format&fit=crop&w=800&q=80', 25.00, 8.50, 450.00, 'available', 'Agile, hyper-naked street bike perfect for quick urban commutes and solo exploration.'),
(2, 'Royal Enfield Classic 350', 'Bike', 'Royal Enfield', 2022, 'RX-BK-102', 2, 'Petrol', 'Manual', 'https://images.unsplash.com/photo-1558981403-c5f9899a28bc?auto=format&fit=crop&w=800&q=80', 35.00, 10.00, 750.00, 'available', 'Iconic retro cruiser with commanding road presence, deep thump, and comfortable long-distance ride.'),

-- 2. Scooter
(3, 'Honda Activa 6G', 'Scooter', 'Honda', 2023, 'RX-SC-201', 2, 'Petrol', 'Automatic', 'https://images.unsplash.com/photo-1593764592116-bfb2a97c642a?auto=format&fit=crop&w=800&q=80', 20.00, 7.00, 350.00, 'available', 'Highly fuel-efficient, light, and convenient scooter for swift city errands and traffic navigating.'),
(4, 'Vespa SXL 150', 'Scooter', 'Vespa', 2022, 'RX-SC-202', 2, 'Petrol', 'Automatic', 'https://images.unsplash.com/photo-1525160354320-d8e92641c563?auto=format&fit=crop&w=800&q=80', 25.00, 8.00, 500.00, 'available', 'Italian retro styling with punchy 150cc performance and comfortable cushioned seating.'),

-- 3. Auto Rickshaw
(5, 'Bajaj RE Compact CNG', 'Auto Rickshaw', 'Bajaj', 2023, 'RX-AR-301', 3, 'CNG', 'Manual', 'https://images.unsplash.com/photo-1596707323863-1250269f8841?auto=format&fit=crop&w=800&q=80', 25.00, 9.00, 600.00, 'available', 'Affordable, breezy 3-wheeler ideal for short to medium intra-city transit with luggage space.'),
(6, 'Piaggio Ape City EV', 'Auto Rickshaw', 'Piaggio', 2024, 'RX-AR-302', 3, 'Electric', 'Automatic', 'https://images.unsplash.com/photo-1568605117036-5fe5e7bab0b7?auto=format&fit=crop&w=800&q=80', 20.00, 7.50, 550.00, 'available', 'Eco-friendly zero-emission electric passenger auto rickshaw with silent and smooth operation.'),

-- 4. Car
(7, 'Honda Civic Turbo', 'Car', 'Honda', 2023, 'RX-CR-401', 5, 'Petrol', 'Automatic', 'https://images.unsplash.com/photo-1549399542-7e3f8b79c341?auto=format&fit=crop&w=800&q=80', 50.00, 14.00, 1800.00, 'available', 'Premium sedan with advanced safety, plush interior, heated seats, and great driving dynamics.'),
(8, 'Hyundai i20 Asta', 'Car', 'Hyundai', 2023, 'RX-CR-402', 5, 'Petrol', 'Manual', 'https://images.unsplash.com/photo-1541899481282-d53bffe3c35d?auto=format&fit=crop&w=800&q=80', 40.00, 12.00, 1300.00, 'available', 'Modern compact hatchback with touchscreen infotainment, sunroof, and excellent city mileage.'),

-- 5. SUV
(9, 'Toyota Fortuner 4x4', 'SUV', 'Toyota', 2023, 'RX-SV-501', 7, 'Diesel', 'Automatic', 'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?auto=format&fit=crop&w=800&q=80', 80.00, 20.00, 3200.00, 'available', 'Mighty full-size 7-seater SUV with all-terrain 4WD capability, leather comfort, and great safety.'),
(10, 'Hyundai Creta SX', 'SUV', 'Hyundai', 2023, 'RX-SV-502', 5, 'Diesel', 'Automatic', 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=800&q=80', 60.00, 16.00, 2200.00, 'available', 'Feature-packed compact SUV offering panoramic sunroof, cruise control, and superb suspension.'),

-- 6. Van
(11, 'Toyota HiAce Commuter', 'Van', 'Toyota', 2022, 'RX-VN-601', 12, 'Diesel', 'Manual', 'https://images.unsplash.com/photo-1563720223185-11003d516935?auto=format&fit=crop&w=800&q=80', 100.00, 22.00, 3800.00, 'available', 'Spacious multi-passenger van for group excursions, corporate shuttle, or family holiday roadtrips.'),
(12, 'Ford Transit Custom', 'Van', 'Ford', 2023, 'RX-VN-602', 9, 'Diesel', 'Automatic', 'https://images.unsplash.com/photo-1570125909232-eb263c188f7e?auto=format&fit=crop&w=800&q=80', 90.00, 20.00, 3500.00, 'available', 'Comfortable tourer van equipped with rear dual AC, captain seats, and ample cargo storage.'),

-- 7. Commercial Vehicle
(13, 'Tata Ace Gold Mini Truck', 'Commercial Vehicle', 'Tata', 2023, 'RX-CV-701', 2, 'Diesel', 'Manual', 'https://images.unsplash.com/photo-1601584115197-04ecc0da31d7?auto=format&fit=crop&w=800&q=80', 80.00, 18.00, 1900.00, 'available', 'Versatile small commercial vehicle capable of 1-ton payload for house moving and bulk logistics.'),
(14, 'Isuzu D-Max Heavy Duty', 'Commercial Vehicle', 'Isuzu', 2022, 'RX-CV-702', 4, 'Diesel', 'Manual', 'https://images.unsplash.com/photo-1559416523-140ddc3d238c?auto=format&fit=crop&w=800&q=80', 90.00, 22.00, 2600.00, 'available', 'Rugged commercial flatbed pickup truck with towing strength and heavy goods transport capability.');

-- Sample Ride Bookings
INSERT INTO `bookings` (`id`, `booking_code`, `user_id`, `vehicle_id`, `pickup_location`, `drop_location`, `ride_date`, `ride_time`, `distance_km`, `base_fare`, `rate_per_km`, `total_fare`, `payment_method`, `payment_status`, `status`, `notes`) VALUES
(1, 'RX-RIDE-1001', 1, 7, 'Downtown Grand Central Station', 'Skyline International Airport Terminal 2', CURDATE(), '14:30:00', 24.50, 50.00, 14.00, 393.00, 'Card', 'Paid', 'Confirmed', 'Need spacious trunk for 2 large suitcases.'),
(2, 'RX-RIDE-1002', 1, 3, 'North Green Park Road', 'City Central Library', DATE_SUB(CURDATE(), INTERVAL 2 DAY), '10:15:00', 6.00, 20.00, 7.00, 62.00, 'UPI', 'Paid', 'Completed', 'Quick morning trip.');

-- Sample Vehicle Rentals
INSERT INTO `rentals` (`id`, `rental_code`, `user_id`, `vehicle_id`, `start_date`, `end_date`, `total_days`, `price_per_day`, `total_amount`, `pickup_address`, `driving_license_number`, `status`, `payment_status`) VALUES
(1, 'RX-RENT-2001', 1, 9, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 4, 3200.00, 12800.00, '742 Evergreen Terrace, Springfield', 'DL-984214-B', 'Active', 'Paid');

-- Sample Courier Bookings
INSERT INTO `courier_bookings` (`id`, `tracking_code`, `user_id`, `sender_name`, `sender_phone`, `pickup_address`, `receiver_name`, `receiver_phone`, `delivery_address`, `parcel_type`, `parcel_weight`, `delivery_option`, `delivery_fee`, `current_status`) VALUES
(1, 'RX-EXP-88901', 1, 'Alex Morgan', '+1 555-0199', 'Building 4, Silicon Tech Park, North Wing', 'David Wilson', '+1 555-0782', '24 Baker Street, West Hill, Block B', 'Electronics & Accessories', 2.80, 'Express Delivery', 145.00, 'In Transit'),
(2, 'RX-EXP-88902', 2, 'Sarah Jenkins', '+1 555-0245', 'Suite 12, Financial District Center', 'Maria Garcia', '+1 555-0391', '89 Palm Boulevard, Harbor Town', 'Important Legal Documents', 0.50, 'Same Day Delivery', 110.00, 'Delivered');

-- Courier Tracking Milestones for RX-EXP-88901
INSERT INTO `courier_tracking` (`courier_id`, `status`, `location`, `remarks`, `timestamp`) VALUES
(1, 'Booking Confirmed', 'RideX Central Dispatch Portal', 'Courier order registered and pickup scheduled.', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(1, 'Pickup Assigned', 'Metro Logistics Hub #4', 'Courier executive John Carter assigned.', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(1, 'Picked Up', 'Silicon Tech Park Hub', 'Parcel inspected and received from sender.', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(1, 'In Transit', 'Central City Sorting Hub', 'Parcel sorted and dispatched toward destination hub.', DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- Courier Tracking Milestones for RX-EXP-88902
INSERT INTO `courier_tracking` (`courier_id`, `status`, `location`, `remarks`, `timestamp`) VALUES
(2, 'Booking Confirmed', 'RideX Online Portal', 'Order placed successfully.', DATE_SUB(NOW(), INTERVAL 24 HOUR)),
(2, 'Pickup Assigned', 'Harbor Zone Hub', 'Executive David Miller assigned.', DATE_SUB(NOW(), INTERVAL 22 HOUR)),
(2, 'Picked Up', 'Financial District Center', 'Package safely packed.', DATE_SUB(NOW(), INTERVAL 20 HOUR)),
(2, 'In Transit', 'Regional Cross-Dock', 'In transit on express van.', DATE_SUB(NOW(), INTERVAL 16 HOUR)),
(2, 'Out for Delivery', 'West Hill Local Depot', 'Out for final delivery to recipient.', DATE_SUB(NOW(), INTERVAL 12 HOUR)),
(2, 'Delivered', '89 Palm Boulevard', 'Delivered and signed by recipient Maria Garcia.', DATE_SUB(NOW(), INTERVAL 10 HOUR));

-- Sample Ratings
INSERT INTO `ratings` (`id`, `user_id`, `booking_type`, `booking_id`, `vehicle_id`, `rating`, `review`, `created_at`) VALUES
(1, 1, 'ride', 2, 3, 5, 'Super smooth ride! Scooter was spotless and the driver was punctual and courteous.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 2, 'ride', 1, 7, 5, 'Honda Civic Turbo is fantastic. Extremely comfortable ride to the airport!', DATE_SUB(NOW(), INTERVAL 2 DAY));

-- Sample Notifications
INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `is_read`, `link`, `created_at`) VALUES
(1, 1, 'Ride Booking Confirmed', 'Your ride booking RX-RIDE-1001 for today has been confirmed. Tap to view details.', 0, 'dashboard.php#bookings', NOW()),
(2, 1, 'Courier Status Update', 'Your parcel RX-EXP-88901 is now In Transit at Central City Sorting Hub.', 0, 'tracking.php?code=RX-EXP-88901', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(3, 1, 'Rental Approved', 'Your rental booking RX-RENT-2001 for Toyota Fortuner is active.', 1, 'dashboard.php#rentals', DATE_SUB(NOW(), INTERVAL 1 DAY));
