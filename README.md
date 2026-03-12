# Kitengela Mall Parking System

![Docker](https://img.shields.io/badge/docker-ready-blue)
![MIT License](https://img.shields.io/badge/license-MIT-green)
![Build Status](https://img.shields.io/badge/build-passing-brightgreen)

## Overview

This is a web-based parking management system for Kitengela Mall. It supports admin, staff, and driver roles, manages parking bays, invoicing, payments (including M-Pesa integration), and provides real-time parking status displays.

---

## Quick Start (Docker)

You can run the entire system using Docker and Docker Compose:

```bash
docker-compose up --build
```

This will start both the PHP/Apache server and a MySQL database. The app will be available at [http://localhost:8080](http://localhost:8080).

**Default MySQL credentials:**

- Database: `parking_db`
- User: `parking_user`
- Password: `parking_pass`
- Root Password: `root_pass`

You can change these in `docker-compose.yml` or use a `.env` file.

---

---

## System Structure

### Main Folders

- **public/**: All user-facing pages (admin, staff, driver, gate, index, etc.)
- **backend/**: Application logic, database models, controllers, and services.
- **assets/**: CSS and static assets.

---

## Main Application Files

### Root Level

- **index.php**: Landing page for the parking system.
- **routes.php**: (If used) Central router for the application, dispatches requests to the correct page.
- **debug_tables.php**: Utility for database debugging (not for production).
- **led_display.php**: Public display for available parking spots (for LED screens).
- **staff.php**: Staff parking view (shows staff vehicles and entry times).

---

### public/admin/

- **dashboard.php**: Admin dashboard with system stats (vehicles inside, revenue, etc.). Added a date-range form for downloading revenue reports as CSV.
- **login.php**: Admin login page.
- **logout.php**: Logs out the admin.
- **owners.php**: Manage business owner vehicles, add owners, view status, and invoice info.
- **restricted.php**: Manage restricted/banned vehicles (add, view, remove).
- **add_user.php**: Add new admin users.
- **reset_admin.php**: Reset admin credentials (use with caution).
- **staff.php**: Manage staff vehicles (add, view, remove).

---

### public/driver/

- **pay.php**: Driver payment page (M-Pesa integration).
- **check_status.php**: Check payment or parking status.
- **payment_status_sse.php**: Server-sent events for real-time payment status.
- **process_mpesa.php**: Handles M-Pesa payment callbacks.
- **simulate_payment.php**: Simulate a payment (for testing).
- **waiting.php**: Waiting page for drivers after payment.

---

### public/gate/

- **entry.php**: Gate entry logic (records vehicle entry, assigns bay).
- **exit.php**: Gate exit logic (records vehicle exit, calculates fees).

---

### backend/app/

- **config/database.php**: Centralized database connection and migration logic.
- **controllers/GateController.php**: (If used) Handles gate logic.
- **models/Vehicle.php**: Main vehicle model (parking, fee calculation, etc.).
- **services/MpesaService.php**: Handles M-Pesa payment integration.
- **services/CallBack.php**: Handles M-Pesa payment callbacks.
- **services/admin_bypass.php**: Admin override for parking actions.

---

## How the System Works

### 1. Admin Panel

- **Login** via `admin/login.php`.
- **Dashboard** (`admin/dashboard.php`) shows stats: vehicles inside, revenue, etc.  There is also a link/form to generate and download a CSV report for a custom date range (opens `admin/revenue_report.php`).

  The system now keeps an **audit trail** of administrator activity. Each successful login, page visit and revenue report download is recorded in a new
  `admin_activity` table (created automatically). Management can inspect this table to see which admin accessed the system, when, and what actions they performed.

  Example schema:
  ```sql
  CREATE TABLE admin_activity (
      id INT AUTO_INCREMENT PRIMARY KEY,
      admin_id INT NOT NULL,
      action VARCHAR(255) NOT NULL,
      ip_address VARCHAR(45),
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );
  ```

  (the table is created on first use by `backend/app/services/AdminAudit.php`.)

  A convenient interface for viewing the audit log has been added at
  `admin/activity.php` – the admin menu now includes an “Activity Log” link
  where management can see timestamps, usernames, actions, and IP addresses.
- **Owners** (`admin/owners.php`): Add business owners, manage their invoicing and parking status.
- **Staff** (`admin/staff.php`): Add and manage staff vehicles (free parking).
- **Restricted** (`admin/restricted.php`): Ban or unban vehicles.
- **Add User** (`admin/add_user.php`): Add new admin users.
- **Reset Admin** (`admin/reset_admin.php`): Reset admin credentials.

### 2. Driver Experience

- **Entry**: At the gate, `gate/entry.php` records entry and assigns a bay.
- **Payment**: Drivers pay via `driver/pay.php` (M-Pesa), status is checked via `driver/check_status.php` and `driver/payment_status_sse.php`.
- **Exit**: At the gate, `gate/exit.php` records exit, checks payment, and calculates fees.

### 3. Staff Parking

- **Staff** park for free. Their vehicles are managed in `admin/staff.php` and visible in `public/staff.php`.

### 4. Owner Parking

- **Owners** are invoiced monthly. Their status and dues are managed in `admin/owners.php`.

### 5. Public Display

- **led_display.php**: Shows available parking spots by floor for display screens.

---

## File Functions (Summary Table)

| File/Folder                | Function/Description                                                                 |
|---------------------------|--------------------------------------------------------------------------------------|
| public/index.php           | Landing page for the system                                                         |
| public/routes.php          | (If used) Central router for the application                                        |
| public/debug_tables.php    | Debug utility for database tables                                                   |
| public/led_display.php     | Public display for available parking spots                                          |
| public/staff.php           | Staff parking view                                                                  |
| public/admin/dashboard.php | Admin dashboard                                                                     |
| public/admin/login.php     | Admin login page                                                                    |
| public/admin/logout.php    | Admin logout                                                                        |
| public/admin/owners.php    | Manage business owner vehicles                                                      |
| public/admin/restricted.php| Manage restricted/banned vehicles                                                   |
| public/admin/add_user.php  | Add new admin users                                                                 |
| public/admin/reset_admin.php| Reset admin credentials                                                            |
| public/admin/staff.php     | Manage staff vehicles                                                               |
| public/driver/pay.php      | Driver payment page                                                                 |
| public/driver/check_status.php | Check payment/parking status                                                    |
| public/driver/payment_status_sse.php | Real-time payment status updates                                          |
| public/driver/process_mpesa.php | Handles M-Pesa payment callbacks                                               |
| public/driver/simulate_payment.php | Simulate a payment (testing)                                                |
| public/driver/waiting.php  | Waiting page for drivers after payment                                              |
| public/gate/entry.php      | Gate entry logic                                                                    |
| public/gate/exit.php       | Gate exit logic                                                                     |
| backend/app/config/database.php | Centralized DB connection and migration logic                                  |
| backend/app/controllers/GateController.php | (If used) Handles gate logic                                        |
| backend/app/models/Vehicle.php | Main vehicle model (parking, fee calculation, etc.)                             |
| backend/app/services/MpesaService.php | Handles M-Pesa payment integration                                       |
| backend/app/services/CallBack.php | Handles M-Pesa payment callbacks                                             |
| backend/app/services/admin_bypass.php | Admin override for parking actions                                       |

---

## Notes

- **Deleted Files**: All root-level utility scripts (for DB migration, repair, or testing) have been removed for production safety.
- **Database**: The system uses MySQL/MariaDB. All migrations are handled automatically on first run.
- **Payments**: M-Pesa integration is handled via backend services and callbacks.
- **Security**: Admin pages require login. Driver and public pages are open as needed.

---


