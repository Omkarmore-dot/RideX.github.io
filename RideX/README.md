# RideX – Smart Multi-Vehicle Rental, Booking & Courier Delivery System

**RideX** is a complete, production-ready, full-stack web application designed for a BSc Computer Science final year project demonstration. It unifies on-demand ride hailing across 7 distinct vehicle categories, multi-day self-drive vehicle rentals, and end-to-end parcel courier delivery with real-time tracking telemetry.

---

## 🚀 Technology Stack

- **Frontend:** HTML5, CSS3 (Modern Responsive Design System with CSS variables), Vanilla JavaScript (ES6+)
- **Backend:** PHP 8.x (Modular MVC/procedural architecture, object-oriented database layer, prepared statements)
- **Database:** MySQL / MariaDB (`ridex_db`) with relational foreign keys, indexes, and timestamps
- **Web Server:** Apache (via XAMPP / WAMP / LAMP)
- **Icons & Fonts:** FontAwesome 6, Google Fonts Inter

---

## 📂 Project Directory Structure

```
RideX/
│
├── config/
│   └── db.php                     # MySQL connection, session handling, global helpers & error screens
│
├── database/
│   └── ridex_db.sql               # Complete database schema (9 tables) + rich seed data
│
├── css/
│   └── style.css                  # Modern responsive design system, animations, badges & cards
│
├── js/
│   └── script.js                  # Dynamic fare calculators, duration calculator, courier weight fees
│
├── includes/
│   ├── header.php                 # Global navigation, notification indicator, flash alert banners
│   └── footer.php                 # Global footer with quick links, contacts & status
│
├── index.php                      # Homepage: 3-in-1 quick launcher, categories, featured fleet, stats
├── vehicles.php                   # Vehicle fleet catalog with multi-criteria search & filters
├── vehicle_details.php            # Vehicle specs, pricing breakdown & verified customer reviews
├── booking.php                    # Ride booking with live distance-based fare calculator & booking code
├── rental.php                     # Self-drive rental with duration & daily rate calculator
├── courier.php                    # Courier parcel booking with weight-based fee calculation
├── tracking.php                   # Real-time courier tracking portal with visual 6-stage milestone stepper
├── dashboard.php                  # User dashboard: live trips, rentals, shipments & notifications
├── profile.php                    # Profile management & secure password change
├── rate_booking.php               # Customer star rating & review submission
├── login.php                      # User login with password_verify() and session management
├── register.php                   # User registration with validation and password_hash()
├── logout.php                     # Secure session termination
│
├── admin/
│   ├── admin.css                  # Dedicated admin console stylesheet
│   ├── includes/
│   │   ├── admin_header.php       # Admin sidebar navigation, session guard & topbar
│   │   └── admin_footer.php       # Admin footer layout
│   ├── login.php                  # Admin authentication portal
│   ├── dashboard.php              # Control center: revenue, stats, live ride & courier dispatch
│   ├── vehicles.php               # Full vehicle fleet CRUD & availability toggle
│   ├── bookings.php               # Ride booking status manager & payment updates
│   ├── rentals.php                # Vehicle rental approval & driver license inspector
│   ├── courier.php                # Courier milestone updater & hub tracking logger
│   ├── users.php                  # Registered customer accounts & suspension toggle
│   ├── reviews.php                # Rating & review moderation console
│   └── logout.php                 # Admin session termination
│
└── README.md                      # Complete setup manual, testing workflows & viva notes
```

---

## 🗄️ Database Architecture (`ridex_db`)

The database consists of **9 fully normalized tables** with strict foreign keys, cascading actions, and performance indexes:

1. **`users`**: Customer credentials, contact numbers, residential pickup addresses, and status (`active`/`suspended`).
2. **`admins`**: Staff credentials, roles, and administrative access control.
3. **`vehicles`**: Fleet across **7 categories**:
   - `Bike`
   - `Scooter`
   - `Auto Rickshaw`
   - `Car`
   - `SUV`
   - `Van`
   - `Commercial Vehicle`
   Contains seating capacities, fuel types (`Petrol`, `Diesel`, `Electric`, `CNG`, `Hybrid`), transmissions (`Manual`, `Automatic`), base fares, rates per km, and daily rental rates.
4. **`bookings`**: Instant ride transactions, pickup/drop addresses, scheduled date/time, distance (km), total calculated fare, payment method, payment status, and status (`Confirmed`, `Driver Assigned`, `On The Way`, `Completed`, `Cancelled`).
5. **`rentals`**: Multi-day self-drive agreements, start/end dates, duration in days, driving license numbers, and status (`Pending Approval`, `Active`, `Completed`, `Cancelled`).
6. **`courier_bookings`**: Parcel orders, sender info, receiver info, parcel category, weight (kg), delivery options (`Standard`, `Express`, `Same Day`), and current status.
7. **`courier_tracking`**: History log of checkpoint milestones (`Booking Confirmed`, `Pickup Assigned`, `Picked Up`, `In Transit`, `Out for Delivery`, `Delivered`), hub locations, and timestamps.
8. **`ratings`**: Verified customer reviews (1–5 stars) tied to users, bookings, and vehicles.
9. **`notifications`**: User event notifications with read/unread tracking and direct action links.

---

## 🛠️ Step-by-Step XAMPP Setup Instructions

Follow these 12 steps to set up and run RideX on your machine:

### 1. Where to Place the RideX Folder
- Copy or move the entire **`RideX`** project folder into your XAMPP `htdocs` directory:
  ```
  C:\xampp\htdocs\RideX
  ```
- Your folder structure will look like:
  ```
  C:\xampp\htdocs\RideX\index.php
  C:\xampp\htdocs\RideX\config\db.php
  C:\xampp\htdocs\RideX\database\ridex_db.sql
  ...
  ```

### 2. How to Start Apache
- Open the **XAMPP Control Panel** (from Start Menu or `C:\xampp\xampp-control.exe`).
- Locate **Apache** in the module list and click the **Start** button.
- The module background will turn green and display port `80, 443`.

### 3. How to Start MySQL
- In the **XAMPP Control Panel**, locate **MySQL** and click the **Start** button.
- The module background will turn green and display port `3306`.

### 4. How to Open phpMyAdmin
- Open your web browser (Google Chrome, Firefox, or Edge) and navigate to:
  ```
  http://localhost/phpmyadmin
  ```

### 5. How to Create and Import `ridex_db.sql`
- In phpMyAdmin:
  1. Click **New** in the left sidebar to create a database.
  2. In the **Database name** field, enter: `ridex_db`.
  3. Select Collation: `utf8mb4_unicode_ci` (or default).
  4. Click the **Create** button.
  5. Select the newly created `ridex_db` database on the left sidebar.
  6. Click on the **Import** tab at the top.
  7. Under **File to import**, click **Choose File / Browse** and navigate to:
     ```
     C:\xampp\htdocs\RideX\database\ridex_db.sql
     ```
  8. Scroll to the bottom and click **Import** (or **Go**).
  9. You will see a success message: *"Import has been successfully finished, 9 tables created."*

### 6. How `config/db.php` is Configured
- Open `config/db.php`. The default credentials are pre-configured for standard XAMPP:
  ```php
  define('DB_HOST', 'localhost');
  define('DB_USER', 'root');
  define('DB_PASS', '');
  define('DB_NAME', 'ridex_db');
  define('DB_PORT', 3306);
  ```
- *Note:* If you ever change your MySQL root password or port, simply update `config/db.php`.
- *Smart Diagnostic Screen:* If MySQL is running but you forgot to import the SQL file, RideX automatically displays an intuitive step-by-step import guide rather than crashing!

### 7. How to Run RideX in Browser
- Open your browser and navigate to:
  ```
  http://localhost/RideX/
  ```
- The RideX homepage will load with the interactive booking widgets, categories, and vehicle fleet.

---

## 🔑 Default Demonstration Accounts

| Role | Login URL | Email / Username | Password |
|---|---|---|---|
| **System Administrator** | `http://localhost/RideX/admin/login.php` | `admin` | `admin123` |
| **Test Customer (Alex)** | `http://localhost/RideX/login.php` | `user@example.com` | `user123` |
| **Test Customer (Sarah)** | `http://localhost/RideX/login.php` | `sarah.j@example.com` | `user123` |

*(You can also register brand-new accounts anytime via `register.php`)*

---

## 🧪 Complete Testing & Demonstration Guide

### 8. How to Test Registration
1. Visit `http://localhost/RideX/register.php`.
2. Enter Full Name, Phone, a new Email (e.g. `david@example.com`), and Password (min 6 characters).
3. Click **Register Account**.
4. **Backend verification:** The password is encrypted using `password_hash($pass, PASSWORD_DEFAULT)`, the new user is saved into `users`, an automated welcome notification is pushed to `notifications`, a session is created, and the user is redirected straight into `dashboard.php`.

### 9. How to Test Login & Logout
1. Click **Sign Out** or navigate to `http://localhost/RideX/logout.php`.
2. Visit `http://localhost/RideX/login.php`.
3. Enter `user@example.com` and password `user123` (or use the one-click demo credentials helper).
4. Click **Sign In**.
5. **Backend verification:** PHP executes a prepared statement query, verifies credentials with `password_verify()`, regenerates the session ID to prevent fixation, and loads the user's dashboard.

### 10. How to Test Live Ride Booking
1. Click **Book Ride** in the top navigation or go to `http://localhost/RideX/booking.php`.
2. Enter Pickup: `Downtown Central Station` and Drop: `City Airport Terminal 1`.
3. Select any vehicle from the dropdown (e.g. `Honda Civic Turbo (Car)`).
4. Enter Distance: `18` km.
5. **Live calculation:** Observe the real-time breakdown update instantly:
   $$\text{Total Fare} = \text{Base Fare } (\$50.00) + (18 \text{ km} \times \$14.00/\text{km}) = \$302.00$$
6. Click **Confirm & Book Ride**.
7. **Database verification:** A unique Booking Code (`RX-RIDE-XXXXX`) is generated, inserted into `bookings`, a notification is created, and the confirmation screen appears.
8. Click **View in Dashboard** to see the new trip listed in the **Ride Bookings** tab!

### 11. How to Test Vehicle Rental
1. Click **Rent Vehicle** (`http://localhost/RideX/rental.php`).
2. Select a vehicle (e.g. `Toyota Fortuner 4x4 - $3,200.00/day`).
3. Select Start Date and End Date (e.g. 3 days).
4. Notice the live JS duration calculator auto-updating to: `3 Days` and Total Amount: `$9,600.00`.
5. Enter your Delivery Address and Driving License Number (e.g. `DL-992144-X`).
6. Click **Confirm Rental Reservation**.
7. **Database verification:** Saved in `rentals` with unique code `RX-RENT-XXXXX`, vehicle status marked as `rented`, and added to the user's rental history.

### 12. How to Test Courier Booking & Live Tracking
1. Click **Courier Delivery** (`http://localhost/RideX/courier.php`).
2. Fill in Sender and Receiver information.
3. Select parcel type (e.g. `Electronics & Gadgets`), set Weight: `3.5` kg, choose `Express Delivery`.
4. Submit the form.
5. **Database verification:** A new Tracking Code (e.g. `RX-EXP-94821`) is generated, saved into `courier_bookings`, and the first event is recorded into `courier_tracking`.
6. Click **Track Shipment Live** to view `tracking.php?code=RX-EXP-94821`.
7. Notice the interactive visual 6-stage milestone tracker:
   - `Booking Confirmed` (Active)
   - `Pickup Assigned`
   - `Picked Up`
   - `In Transit`
   - `Out for Delivery`
   - `Delivered`

### 13. How to Test Admin Panel & Live Milestone Updates
1. Navigate to: `http://localhost/RideX/admin/login.php`.
2. Login with `admin` and `admin123`.
3. In the Admin Dashboard:
   - View the aggregate revenue and metric counters.
   - Go to **Courier Deliveries** (`admin/courier.php`).
   - Find the package you just created.
   - In the **Advance Tracking Milestone** dropdown, select `In Transit`, enter Location: `Central Sorting Hub Station #3`, and click **Advance Milestone**.
4. Now, reload your customer tracking page (`tracking.php?code=...`) in another tab.
5. **Full-stack synchronization verification:** The customer's visual progress bar instantly advances to `In Transit`, and the new checkpoint appears in the chronological activity timeline!
6. In Admin, go to **Manage Vehicles** (`admin/vehicles.php`):
   - Add a new vehicle, edit its rates, or toggle availability between `Available`, `Booked`, `Rented`, and `Maintenance`.
   - Go to `vehicles.php` on the customer side to see the vehicle updated live.

### 14. How to Test Ratings & Reviews
1. In the customer dashboard (`dashboard.php#bookings`), find any booking marked as `Completed` (e.g. `RX-RIDE-1002`).
2. Click **Rate Ride**.
3. Select a 5-star rating, write a review, and click **Submit Review**.
4. The review is saved in `ratings` and displayed on the vehicle's detail page (`vehicle_details.php?id=3`) and in the admin review moderation console (`admin/reviews.php`).

---

## 🔒 Security Implementations

- **SQL Injection Prevention:** 100% of dynamic queries use parameterized prepared statements (`$stmt = $db->prepare(...)`).
- **Password Security:** One-way password hashing using PHP's native `password_hash($pass, PASSWORD_DEFAULT)` and verification via `password_verify()`.
- **Session Protection:** `session_regenerate_id(true)` prevents session fixation attacks upon login.
- **Cross-Site Scripting (XSS):** All dynamic database outputs are escaped using `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- **Route Authorization:** Protected customer routes enforce `requireLogin()`; administrative routes enforce `requireAdmin()`.

---

## 🎓 Viva & Presentation Highlights (BSc Computer Science)

When presenting this project to your examiner or panel:
1. **Three-Tier Architecture:** Emphasize the separation of Presentation (HTML5/CSS3/JS), Application Logic (PHP 8.x controllers and authentication), and Data Storage (MySQL `ridex_db`).
2. **Dynamic Mathematical Calculations:** Demonstrate how fare computation happens on the client side for instantaneous user feedback, and is independently re-verified on the server side to prevent client tampering.
3. **Relational Database Design:** Highlight the cascading deletes and foreign keys linking users, vehicles, bookings, rentals, and tracking milestones.
4. **Logistics Telemetry:** Show how the 6-stage milestone tracker in `tracking.php` provides real-time status updates without polling overhead.
