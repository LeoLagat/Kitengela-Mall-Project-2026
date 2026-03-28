# Kitengela Mall Parking Management System
## User Manual

---

**Document Title:** User Manual — Kitengela Mall Parking Management System  
**Version:** 1.0  
**Date:** March 2026  
**Prepared by:** Development Team  
**Classification:** Internal Use

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [System Overview](#2-system-overview)
3. [System Requirements and Installation](#3-system-requirements-and-installation)
4. [User Roles and Access Levels](#4-user-roles-and-access-levels)
5. [Accessing the System](#5-accessing-the-system)
6. [Gate Operations — Vehicle Entry](#6-gate-operations--vehicle-entry)
7. [Gate Operations — Vehicle Exit](#7-gate-operations--vehicle-exit)
8. [Driver and Visitor Guide — Making a Payment](#8-driver-and-visitor-guide--making-a-payment)
9. [Admin Panel — Logging In](#9-admin-panel--logging-in)
10. [Admin Panel — Dashboard](#10-admin-panel--dashboard)
11. [Admin Panel — Revenue Reports](#11-admin-panel--revenue-reports)
12. [Admin Panel — Staff Vehicle Management](#12-admin-panel--staff-vehicle-management)
13. [Admin Panel — Owner Vehicle Management and Monthly Billing](#13-admin-panel--owner-vehicle-management-and-monthly-billing)
14. [Admin Panel — Restricted Vehicles](#14-admin-panel--restricted-vehicles)
15. [Admin Panel — User Account Management](#15-admin-panel--user-account-management)
16. [Admin Panel — Activity Logs and Audit Trail](#16-admin-panel--activity-logs-and-audit-trail)
17. [Admin Panel — Database Tools](#17-admin-panel--database-tools)
18. [Admin Panel — Profile and Password Management](#18-admin-panel--profile-and-password-management)
19. [Public Display Pages](#19-public-display-pages)
20. [Manual Gate Bypass (Emergency Override)](#20-manual-gate-bypass-emergency-override)
21. [M-Pesa Payment Integration — Technical Overview](#21-m-pesa-payment-integration--technical-overview)
22. [Business Rules and Pricing Policy](#22-business-rules-and-pricing-policy)
23. [Troubleshooting Guide](#23-troubleshooting-guide)
24. [Glossary of Terms](#24-glossary-of-terms)
25. [Appendix A — Database Table Reference](#25-appendix-a--database-table-reference)
26. [Appendix B — Quick Reference Card](#26-appendix-b--quick-reference-card)

---

## 1. Introduction

Welcome to the **Kitengela Mall Parking Management System**. This manual is intended to guide all users — gate staff, drivers, business owners, and administrators — through the operation of the system from day-to-day tasks to advanced administrative functions.

The system was developed to solve the challenges of managing a large multi-level parking facility in a busy commercial mall environment. Before its introduction, tracking occupancy, collecting fees, issuing receipts, and maintaining audit records were largely manual processes prone to errors, revenue leakage, and slow vehicle throughput.

This User Manual is organized by role and workflow so that each user can quickly locate the section most relevant to their daily activities. Gate operators will primarily use Chapters 6 and 7. Customers paying for parking will find Chapter 8 useful. Administrators and managers should read Chapters 9 through 18 thoroughly.

### 1.1 Purpose of this Document

This document provides:

- Step-by-step instructions for all system functions.
- Explanations of core business rules including pricing, grace periods, and monthly invoicing.
- Guidance on exception handling, emergency overrides, and troubleshooting.
- Reference material for training new staff and administrators.

### 1.2 Intended Audience

| Audience | Relevant Chapters |
|---|---|
| Gate staff (entry and exit operators) | 3, 5, 6, 7, 23 |
| Drivers and visitors (paying customers) | 5, 8, 23 |
| Business owners (monthly invoice accounts) | 7, 8, 13 |
| Sub-administrators | 9–12, 14, 16, 18 |
| Super-administrators | All chapters |

---

## 2. System Overview

### 2.1 What the System Does

The Kitengela Mall Parking Management System is a web-based application that automates the full lifecycle of vehicle parking in the mall's two-basement parking facility. The key capabilities of the system are:

1. **Vehicle Entry Management** — Records every vehicle entering the facility, assigns a parking bay, and enforces access control by blocking restricted vehicles.

2. **Vehicle Exit and Fee Calculation** — Calculates the correct parking fee based on duration, then routes the driver through payment or a free-exit path depending on their vehicle category.

3. **M-Pesa Payment Processing** — Collects payment directly from the driver's mobile phone via the Safaricom Daraja STK Push (Lipa Na M-Pesa) service so no cash changes hands at the gate.

4. **Staff Free Parking** — Registered employee vehicles are recognized automatically and allowed to exit without any fee.

5. **Monthly Owner Invoicing** — Registered business owner vehicles are given a 30% discount and their fees are accumulated and invoiced on a monthly basis rather than collected at each exit.

6. **Bay Management** — Tracks the real-time occupancy of all 262 parking bays across two basement floors (Basement 1 and Basement 2) and prevents over-allocation.

7. **Restricted Vehicle Block** — Vehicles added to the restricted list are denied entry outright.

8. **Administrative Oversight** — Provides a full admin panel with dashboards, revenue reports, user management, activity logs, and emergency override capabilities.

### 2.2 Facility Layout

The parking facility consists of two underground levels:

| Floor | Bay Range | Total Bays |
|---|---|---|
| Basement 1 (B1) | B1-001 to B1-131 | 131 bays |
| Basement 2 (B2) | B2-001 to B2-131 | 131 bays |
| **Total** | | **262 bays** |

Bay numbers are assigned sequentially starting from the lowest available number on any floor. The system does not yet differentiate between floor preferences.

### 2.3 Technology Stack

The system runs on an XAMPP stack:

| Component | Technology |
|---|---|
| Web Server | Apache (via XAMPP) |
| Server-side Language | PHP 8.2 |
| Database | MySQL / MariaDB 10.4 |
| Payment Gateway | Safaricom Daraja (M-Pesa STK Push) |
| Front-end | HTML5, CSS3, Vanilla JavaScript |
| Architecture | MVC-inspired (Controllers, Models, Services under `backend/`) |

---

## 3. System Requirements and Installation

### 3.1 Server Requirements

| Requirement | Minimum Specification |
|---|---|
| Operating System | Windows 10/11 or Ubuntu 20.04+ |
| Web Server | Apache 2.4+ |
| PHP | PHP 8.0 or higher |
| Database | MySQL 5.7+ or MariaDB 10.4+ |
| Internet Connection | Required for M-Pesa API calls |
| RAM | 4 GB minimum, 8 GB recommended |
| Disk Space | 2 GB minimum |

### 3.2 Accessing the System

The system is designed to run as a local networked application. All devices on the mall's internal network can access the application via a browser. A typical local URL will be:

```
http://[server-ip]/Kitengela_Parking/public/
```

For example:

```
http://192.168.1.10/Kitengela_Parking/public/
```

No installation or software download is required on client devices — only a modern web browser is needed.

### 3.3 Recommended Browsers

| Browser | Minimum Version |
|---|---|
| Google Chrome | Version 90+ |
| Mozilla Firefox | Version 88+ |
| Microsoft Edge | Version 90+ |
| Safari (macOS/iOS) | Version 14+ |

Internet Explorer is not supported.

### 3.4 Network Setup

All gate terminals, admin workstations, and display screens should be connected to the same local area network as the server running XAMPP. The server must have a **static IP address** to ensure that the URLs printed on receipts (if any) and device bookmarks remain valid. Wi-Fi access points in the parking area should provide adequate coverage for gate tablet devices.

---

## 4. User Roles and Access Levels

The system defines four distinct roles. Each role has a specific set of pages and actions available to it.

### 4.1 Role Overview

| Role | Who Uses It | Authentication Required |
|---|---|---|
| **Super Admin** | Senior system administrator (e.g., facility manager) | Yes (username + password) |
| **Sub-Admin** | Junior administrator (e.g., supervisor on duty) | Yes (username + password) |
| **Gate Staff** | Physical gate operators at entry and exit points | No (direct page access) |
| **Driver / Visitor** | Customers paying for parking | No (plate number is their identifier) |

### 4.2 Super Admin Capabilities

The Super Admin has unrestricted access to every feature in the system. Specific powers that only Super Admins hold include:

- Creating and deleting other admin accounts (sub-admins and super admins).
- Viewing the complete activity log for all admin users.
- Viewing the dedicated sub-admin activity auditing page.
- Using the Database Tools page to search across all tables and clear data.
- Performing hard deletes on staff, owner, and restricted vehicles.
- Clearing vehicle logs (with automatic revenue archiving).

> **Note:** The username `leolagat` is permanently assigned the `super_admin` role at the system level and cannot be downgraded through the standard interface.

### 4.3 Sub-Admin (Admin) Capabilities

Sub-admins can perform all day-to-day administrative tasks:

- View the dashboard with live occupancy and revenue figures.
- Perform manual gate bypass for any vehicle.
- Manage staff vehicles (add, view, soft-delete).
- Manage owner vehicles (view, compute billing, record payments).
- Manage restricted vehicles (add, view, soft-restore).
- Download revenue CSV reports.
- View their own activity log.
- Change their own password.

Sub-admins **cannot**:

- Add or delete user accounts.
- View other admins' activity logs.
- Access database tools.
- Clear vehicle logs.

### 4.4 Gate Staff Capabilities

Gate staff access the system via dedicated bookmarked URLs on gate terminals:

- **Entry gate:** Open the entry page, enter a plate number, and confirm the vehicle has been logged.
- **Exit gate:** Open the exit page, enter a plate number, and guide the driver to payment or free exit.

Gate staff do **not** log in and have no access to admin pages.

### 4.5 Driver / Visitor Capabilities

Drivers interact with the system only at the payment stage:

- Enter their M-Pesa phone number on the payment page.
- Approve the STK Push prompt on their mobile phone.
- Retry the payment if the first attempt fails.
- View the payment waiting/status page.

---

## 5. Accessing the System

### 5.1 Home Page (Public)

The home page is accessible at:

```
http://[server-ip]/Kitengela_Parking/public/
```

It displays a real-time grid of all parking bays showing which bays are **vacant** (green) and which are **occupied** (red). Bay counts per floor and overall occupancy percentage are shown at the top. This page is suitable for display on a screen in the lobby or entrance area.

No login is required to view the home page.

### 5.2 Gate Entry Page (Staff)

```
http://[server-ip]/Kitengela_Parking/public/gate/entry.php
```

This page is used by gate staff at the vehicle entry point. Bookmark this URL on the entry gate terminal.

### 5.3 Gate Exit Page (Staff)

```
http://[server-ip]/Kitengela_Parking/public/gate/exit.php
```

This page is used by gate staff at the vehicle exit point. Bookmark this URL on the exit gate terminal.

### 5.4 Admin Login Page

```
http://[server-ip]/Kitengela_Parking/public/admin/login.php
```

Administrators access the system through this login page using their username and password.

### 5.5 LED Display Page

```
http://[server-ip]/Kitengela_Parking/public/led_display.php
```

This is a full-screen dark display designed to be shown on large monitors or LED boards. It lists vacant bays per floor and refreshes every 10 seconds. No interaction or login is required.

### 5.6 Staff Parking Display

```
http://[server-ip]/Kitengela_Parking/public/staff.php
```

A public page listing all staff vehicles currently parked in the facility, along with their entry times.

---

## 6. Gate Operations — Vehicle Entry

This chapter is intended for **gate staff** operating the **entry gate terminal**.

### 6.1 Overview

Every vehicle entering the parking facility must be logged through the entry page. The system will:

1. Check if the vehicle is on the restricted list — and deny entry if so.
2. Check if the vehicle is already registered as being inside — and prevent a duplicate record.
3. Assign the next available parking bay.
4. Record the entry time.

This process takes only a few seconds.

### 6.2 Step-by-Step: Logging a Vehicle Entry

**Step 1:** Open the entry page on the gate terminal:
```
http://[server-ip]/Kitengela_Parking/public/gate/entry.php
```

**Step 2:** When a vehicle arrives at the boom gate, observe and note the **license plate number**.

**Step 3:** Type the plate number into the **"Enter Plate Number"** input field.
- The system accepts plates in any case (e.g., `KCA 123A` or `kca 123a`) — it will automatically convert them to uppercase.
- Include spaces if they appear on the physical plate (e.g., `KBZ 456G`).

**Step 4:** Click the **"Log Entry"** button (or press Enter).

**Step 5:** Wait for the system response (usually within 1-2 seconds):

| Outcome | What You See | Action Required |
|---|---|---|
| **Success** | Green banner showing the assigned bay number (e.g., "Entry recorded — Bay B1-023") | Raise the boom gate. Tell the driver their bay number. |
| **Restricted Vehicle** | Red banner: "Entry Denied — Restricted Vehicle" | Keep boom gate down. Do not allow entry. Alert supervisor. |
| **Already Inside** | Red banner: "Vehicle already logged as inside" | Do not raise gate. Check with exit gate — possible plate confusion. |
| **Parking Full** | Red banner: "Parking Full — No vacant bays" | Do not raise gate. Politely turn driver away. |

**Step 6:** The page will automatically redirect back to the entry form after 3 seconds so you are ready for the next vehicle.

### 6.3 Important Notes for Gate Entry Staff

- **Never skip logging a vehicle** — the gate relies entirely on this record for exit processing and payment calculation.
- If a vehicle enters while the system is temporarily offline (network outage), note the plate number and time manually and log it as soon as the system is back online. Notify the supervisor.
- If a driver claims their vehicle has already been logged but the plate is not showing as "already inside," verify the plate spelling carefully — a single character difference creates a separate record.
- The system assigns bays automatically. You cannot manually select a specific bay number for a vehicle.

---

## 7. Gate Operations — Vehicle Exit

This chapter is intended for **gate staff** operating the **exit gate terminal**.

### 7.1 Overview

When a vehicle wants to leave, the exit gate staff initiates a lookup on the exit page. The system calculates the parking fee based on how long the vehicle has been parked and then either:

- Allows the vehicle to exit **immediately for free** (staff vehicles, vehicles within the free grace period, or registered owner vehicles that have already completed payment/invoicing), or
- Directs the **driver to the payment kiosk or payment page** to pay via M-Pesa before the boom gate opens.

### 7.2 Step-by-Step: Processing a Vehicle Exit

**Step 1:** Open the exit page on the gate terminal:
```
http://[server-ip]/Kitengela_Parking/public/gate/exit.php
```

**Step 2:** Ask the driver for their **plate number**.

**Step 3:** Type the plate number into the **"Enter Plate Number"** field and click **"Process Exit"**.

**Step 4:** The system calculates the fee and shows one of the following outcomes:

| Outcome | What It Means | Action |
|---|---|---|
| **Free Exit — Staff Vehicle** | Plate is in the staff vehicles list | Raise boom gate immediately. |
| **Free Exit — Grace Period** | Vehicle parked ≤ 30 minutes | Raise boom gate immediately. |
| **Free Exit — Owner Invoiced** | Plate belongs to a registered business owner | Raise boom gate. Fee will be invoiced. |
| **Payment Required — KES [amount]** | Vehicle must pay before exit | Direct driver to payment page. |
| **Plate Not Found** | No active record for this plate | Do not open gate. Verify plate, check with admin. |

**Step 5:** If payment is required, the system will automatically load the payment page for the driver. The driver should be directed to a payment kiosk, tablet, or their own phone to complete M-Pesa payment. The boom gate will open automatically once payment is confirmed.

**Step 6:** For free exits, the bay is freed immediately and the screen will confirm exit with a goodbye message.

### 7.3 Parking Fee Structure

| Duration | Fee |
|---|---|
| 0 – 30 minutes | **Free** (Grace Period) |
| 31 – 60 minutes | **KES 50** (flat) |
| 61 – 120 minutes | **KES 70** (KES 50 + KES 20 for 1 extra hour) |
| 121 – 180 minutes | **KES 90** (KES 50 + KES 40 for 2 extra hours) |
| Each additional hour after the first | **+ KES 20** |
| 12 hours or more (all-day) | **KES 1,000** (daily cap) |

> **Example:** A vehicle parked for 2 hours and 45 minutes = KES 50 + (2 extra hours × KES 20) = **KES 90**.

### 7.4 Fee Exceptions

| Vehicle Category | Fee Treatment |
|---|---|
| Staff vehicles (registered employees) | Always **KES 0** |
| Owner vehicles (registered monthly accounts) | 30% discount; balance invoiced monthly |
| Any vehicle ≤ 30 minutes | Always **KES 0** (grace period) |
| Owner vehicles > 12 hours | Day cap applies (KES 1,000 nominal, KES 700 billed) |

---

## 8. Driver and Visitor Guide — Making a Payment

This chapter is for **drivers and visitors** who need to pay for parking via M-Pesa.

### 8.1 Overview

Kitengela Mall uses the **Lipa Na M-Pesa** (M-Pesa Paybill / STK Push) system for cashless parking payment. No cash is collected at the gate. Payment is made directly from the driver's mobile phone.

### 8.2 Step-by-Step: Paying for Parking

When you are ready to leave and your vehicle requires a payment:

**Step 1 — Arrive at the payment page:**  
You will either be directed to a payment kiosk/tablet near the exit gate, or the gate staff will hand you a device showing the payment page. The page will display:
- Your plate number
- The amount due in Kenyan Shillings (KES)
- An input field for your M-Pesa phone number

**Step 2 — Enter your M-Pesa phone number:**  
Type in your Safaricom (M-Pesa) phone number. You can enter it in any of these formats:
- `0712 345 678`
- `07 12 34 56 78`
- `+254712345678`
- `254712345678`

The system will normalize your number automatically.

**Step 3 — Click "Pay Now":**  
The system sends an STK Push request to your phone. This will cause an **M-Pesa pop-up screen** to appear on your phone within a few seconds.

**Step 4 — Approve the prompt on your phone:**  
On your mobile phone, you will see a prompt similar to:

> *"Kitengela Parking — Pay KES [amount] to [shortcode]? Enter M-Pesa PIN to confirm."*

Enter your **M-Pesa PIN** to approve the payment.

**Step 5 — Wait for confirmation:**  
The waiting page on the kiosk/tablet will display a spinner and the message "Waiting for payment confirmation...". Once your payment is approved, the page will automatically show a green success message and the boom gate will open.

**Step 6 — Proceed to the gate:**  
Drive to the exit boom gate. It will be raised automatically or the gate staff will have received notification that your payment is complete.

### 8.3 What to Do If Payment Fails

If the M-Pesa pop-up does not appear, or you accidentally dismissed it, or your phone was off/without signal:

1. The waiting page will show a **"Retry Payment"** button after the first attempt times out.
2. Click **"Retry Payment"** to send a new STK Push to your phone.
3. Note: there is a **30-second cooldown** between retry attempts to prevent duplicate charges.
4. If the problem persists, please call a gate attendant for assistance. The administrator can perform a **manual bypass** (see Chapter 20) to release your vehicle while payment is resolved separately.

### 8.4 Frequently Asked Questions

**Q: Can I pay with cash?**  
A: No. The system is fully cashless and requires M-Pesa.

**Q: Will I receive a receipt?**  
A: Yes. Once payment is confirmed, Safaricom will send an SMS receipt to your phone with the M-Pesa transaction receipt number (e.g., `RKA1234XYZ`).

**Q: What if I was incorrectly charged?**  
A: Speak with a gate attendant or the mall management office. Administrators can look up your transaction by plate number and correct any discrepancies.

**Q: My M-Pesa PIN was wrong — am I charged?**  
A: No. A failed PIN entry on the M-Pesa pop-up cancels the transaction. No money is deducted. You can retry from the waiting page.

**Q: I parked for less than 30 minutes but was asked to pay?**  
A: This may occur if you re-entered after a previous session. Contact gate staff for clarification.

---

## 9. Admin Panel — Logging In

### 9.1 The Login Page

Navigate to:
```
http://[server-ip]/Kitengela_Parking/public/admin/login.php
```

You will see a login form asking for:
- **Username** — your assigned admin username
- **Password** — your admin password

### 9.2 Logging In — Step by Step

1. Enter your username in the **Username** field.
2. Enter your password in the **Password** field.
3. Click **"Login"**.

If your credentials are correct, you will be redirected to the **Admin Dashboard**.

If your credentials are incorrect, you will see an error message: **"Invalid username or password."** Check your username and password, paying attention to capitalisation, and try again.

### 9.3 Failed Login Attempts

The system logs all login attempts, successful or otherwise, with your IP address. If you are repeatedly unable to log in, contact the Super Admin to:
- Confirm your username.
- Reset your password.

### 9.4 Security Reminder

- **Never share your login credentials** with colleagues. Each admin should have their own account so that the audit trail accurately reflects who performed each action.
- Log out (see Chapter 18) whenever you leave your workstation unattended.
- Change your password regularly (see Chapter 18).

---

## 10. Admin Panel — Dashboard

The Dashboard is the first page you see after logging in and provides a live overview of the entire parking facility.

### 10.1 Dashboard Sections

#### 10.1.1 Summary Statistics (Top Cards)

The top of the dashboard shows four key metrics at a glance:

| Card | Description |
|---|---|
| **Vehicles Currently Inside** | Count of vehicles with active parking sessions (not yet exited) |
| **Vacant Bays** | Number of parking bays currently unoccupied |
| **Occupied Bays** | Number of parking bays currently in use |
| **Today's Revenue** | Total confirmed M-Pesa payments collected today (KES) |

These figures update each time the page is loaded. Refresh the page to get the latest figures.

#### 10.1.2 Active Vehicles Table

Below the summary cards is a table listing every vehicle currently parked including:

- **Plate Number** — The vehicle's registration.
- **Bay Number** — Which bay the vehicle occupies.
- **Entry Time** — When the vehicle entered the facility.
- **Duration** — How long the vehicle has been parked (calculated live).
- **Payment Status** — Whether payment is `pending`, `paid`, `invoiced`, or `failed`.
- **Actions** — Manual Bypass button for emergency override.

Rows highlighted in **orange or red** indicate vehicles that have been parked for an unusually long time (overstays of more than 8 hours).

#### 10.1.3 Total Revenue (All Time)

A revenue summary section shows the cumulative total revenue collected across all time, combining current vehicle log records with any previously archived revenue snapshots.

### 10.2 Using the Dashboard — Common Tasks

**To see which bays are occupied:**  
View the Active Vehicles table. Each row corresponds to one occupied bay.

**To identify overstays:**  
Look for rows highlighted in orange or red in the active vehicles table. These are vehicles that have been parked for more than 8 hours and may need attention.

**To perform a manual bypass:**  
See Chapter 20.

**To refresh the data:**  
Press F5 or click your browser's refresh button.

---

## 11. Admin Panel — Revenue Reports

The Revenue Report feature allows administrators to download a detailed record of all parking fee transactions as a CSV (spreadsheet) file for accounting and audit purposes.

### 11.1 Accessing Revenue Reports

From the admin panel, click **"Revenue Report"** in the navigation menu.

### 11.2 Generating a Report

1. On the Revenue Report page, select a **Start Date** and **End Date** using the date pickers.
2. Click **"Download CSV"**.
3. A CSV file will be downloaded to your computer. Open it in Microsoft Excel, LibreOffice Calc, or Google Sheets.

### 11.3 CSV Report Contents

The downloaded file contains one row per completed payment and includes the following columns:

| Column | Description |
|---|---|
| Transaction ID | Unique record identifier |
| Plate Number | Vehicle's registration number |
| Bay Number | Parking bay used |
| Entry Time | When the vehicle entered |
| Exit Time | When the vehicle exited |
| Duration | Parking duration (hours:minutes) |
| Nominal Fee (KES) | Full calculated fee before any discounts |
| Amount Paid (KES) | Actual amount collected |
| Payment Status | `paid`, `invoiced`, or `manual_bypass` |
| M-Pesa Receipt | Safaricom transaction receipt number |
| Phone Number | M-Pesa number used |
| Date Paid | Date and time payment was confirmed |

### 11.4 Revenue Archiving

When vehicle logs are cleared by a super admin (see Chapter 17), the total revenue figures from those logs are automatically archived to a separate history table. The **total revenue** figure on the dashboard always includes both live records and all archived revenue snapshots, ensuring no data is ever lost.

---

## 12. Admin Panel — Staff Vehicle Management

Staff vehicles are vehicles belonging to mall employees or approved personnel that are granted **free parking** at all times regardless of duration.

### 12.1 Accessing Staff Management

From the admin panel navigation, click **"Staff Vehicles"**.

### 12.2 Adding a New Staff Vehicle

1. Under the **"Add Staff Vehicle"** section, enter:
   - **Plate Number** — The employee's vehicle registration number.
   - **Employee Name** — The name of the employee who owns the vehicle.
2. Click **"Add Vehicle"**.
3. The vehicle will appear in the staff list immediately. From the next entry, this vehicle will be given free parking.

> **Important:** Only add vehicles belonging to verified current employees. Free parking is a benefit that should only be extended to active staff.

### 12.3 Viewing Staff Vehicles

The page displays a table of all registered staff vehicles showing:
- Plate number
- Employee name
- Date added

### 12.4 Removing a Staff Vehicle (Soft Delete)

To remove a staff vehicle (e.g., when an employee leaves):

1. Find the vehicle in the staff list.
2. Click the **"Remove"** button next to the entry.
3. The vehicle is **soft-deleted** — it is removed from the active list but the record is retained in the database for audit purposes.

A super admin can permanently delete the record or restore it if removed in error.

> **Note:** If a staff member's vehicle is removed but the vehicle is currently in the parking facility, they will be charged normally on their next exit.

### 12.5 Public Staff Display Page

The staff vehicles currently parked in the facility are visible on the public-facing page at:
```
http://[server-ip]/Kitengela_Parking/public/staff.php
```
This can be projected on a screen in the staff room or used by supervisors to see who is on-site.

---

## 13. Admin Panel — Owner Vehicle Management and Monthly Billing

Business owners (tenants of the mall or approved corporate accounts) are registered as **monthly invoice accounts**. Their parking fees are accumulated over the month and settled periodically rather than paid at each exit.

### 13.1 How Owner Accounts Work

- Owner vehicles are registered with the owner's name in the **owner_accounts** table.
- Every time an owner vehicle exits, the system calculates the normal fee, applies a **30% discount**, and records it as `invoiced` rather than collecting M-Pesa payment.
- The discounted amount accumulates under the owner's record.
- The **Owners page** allows an administrator to compute the total outstanding and record a payment when the owner settles their account.

### 13.2 Accessing Owner Management

From the admin panel, click **"Owners"**.

### 13.3 Adding a New Owner Vehicle

1. On the Owners page, find the **"Add Business Owner Vehicle"** form.
2. Enter:
   - **Plate Number** — The vehicle's registration.
   - **Owner Name** — The business owner or company name.
3. Check the **"Invoice Monthly"** checkbox to enable monthly billing.
4. Click **"Add Owner"**.

### 13.4 Viewing the Owner Billing Summary

The owners page displays a table of all registered owner vehicles with:

| Column | Description |
|---|---|
| Plate Number | Vehicle registration |
| Owner Name | Business name |
| Nominal Fee (KES) | Total full fees accumulated since last payment |
| Total Due (KES) | 70% of nominal fee (after 30% discount) — this is what the owner owes |
| Due Period | The billing period (month/year) |

### 13.5 Computing Monthly Totals

Before issuing an invoice or recording a payment, click the **"Compute Totals"** button. This will:

1. Scan all vehicle log records marked as `invoiced` for each owner.
2. Sum the nominal fees.
3. Calculate the total due (70% of nominal sum).
4. Update the owner billing table.

Always click Compute Totals before reviewing invoices to ensure the figures are current.

### 13.6 Recording a Payment

When an owner settles their account:

1. Find the owner's row in the billing table.
2. In the **"Record Payment"** column, click the **"Record Payment"** button next to their plate.
3. Confirm the action.
4. The system will:
   - Mark all outstanding `invoiced` vehicle log records for that plate as `paid`.
   - Reset the `total_due` for that owner to KES 0.
   - Advance the billing period to the next month.

### 13.7 Removing an Owner Account (Soft Delete)

To remove a business owner (e.g., if their tenancy ends):

1. Find the owner in the list.
2. Click **"Remove"**.
3. The record is soft-deleted and the vehicle will be treated as a regular paying customer from the next exit.

A super admin can restore soft-deleted owners or permanently delete the record.

---

## 14. Admin Panel — Restricted Vehicles

The restricted vehicles list allows administrators to block specific vehicles from entering the mall parking facility. This feature is useful for vehicles associated with criminal activity, unpaid fines, fraudulent activity, or any other reason management deems appropriate.

### 14.1 Accessing Restricted Vehicles

From the admin navigation, click **"Restricted Vehicles"**.

### 14.2 Why Restrict a Vehicle?

Typical reasons for restriction include:

- Persistent non-payment of parking fees.
- Vehicle associated with theft or fraud incidents reported at the mall.
- Order from mall management or security.

### 14.3 Adding a Restricted Vehicle

1. In the **"Add Restricted Vehicle"** form, enter:
   - **Plate Number** — The plate to be blocked.
   - **Reason** — A brief justification for the restriction.
2. Click **"Add to Restricted List"**.

Once added, the next time this plate attempts to enter, the gate system will display **"Entry Denied — Restricted Vehicle"** and the boom gate will not be raised.

### 14.4 Viewing the Restricted List

The restricted vehicles page shows all active restrictions with:
- Plate number
- Reason for restriction
- Date added

### 14.5 Removing or Restoring a Restricted Vehicle

If a restriction needs to be lifted:

1. Locate the plate in the restricted list.
2. Click **"Remove"** to soft-delete (send to recycle bin).
3. If you wish to restore a soft-deleted entry, click **"Restore"** (available to super admins).

Permanently deleted records are gone from the system entirely and cannot be recovered.

---

## 15. Admin Panel — User Account Management

> **Super Admin access only.** Sub-admins cannot access this section.

### 15.1 Overview

The User Account Management page allows the Super Admin to add, view, and remove administrator accounts. This is the only way to create new admin accounts — there is no self-registration.

### 15.2 Accessing User Management

From the admin navigation, click **"Add User"** or go directly to:
```
http://[server-ip]/Kitengela_Parking/public/admin/add_user.php
```

### 15.3 Creating a New Admin Account

1. Fill in the **"Create New Admin"** form:
   - **Username** — A unique username for the new admin (cannot already exist).
   - **Password** — A temporary password (the new user should change this on first login).
   - **Role** — Select either `admin` (sub-admin) or `super_admin`.
2. Click **"Create Account"**.
3. The new account is created immediately with the password stored securely using bcrypt hashing.

> **Note:** Never share passwords via insecure channels. Instruct new admins to change their password immediately after first login using the Profile page (Chapter 18).

### 15.4 Viewing All Admin Accounts

The page displays a table of all current administrator accounts with:
- Username
- Role
- Date created
- Delete button (for eligible accounts)

### 15.5 Deleting an Admin Account

1. Click the **"Delete"** button next to the account to remove.
2. Confirm the deletion in the dialog box.

**Protection rules:**
- A Super Admin **cannot delete their own account**.
- The system will **refuse to delete the last remaining Super Admin** account, preventing accidental lockout.

---

## 16. Admin Panel — Activity Logs and Audit Trail

The system maintains a comprehensive audit trail of every significant action performed by administrators. This ensures full accountability and makes it possible to investigate any anomalies.

### 16.1 What Is Logged?

Every single one of the following events is recorded in the audit log along with the admin's username, IP address, and a timestamp:

- Successful and failed login attempts
- Logout events
- Manual gate bypasses (by plate, with old/new status)
- Staff vehicle additions and removals
- Owner vehicle additions and payment recordings
- Restricted vehicle additions and removals
- User account creation and deletion
- Password changes
- Revenue report downloads
- Database searches and table clears

### 16.2 Accessing Your Own Activity Log

From the admin navigation, click **"My Activity"** or **"Activity Log"**.

By default, you see your own actions. You can filter by:
- **Date range** — select a start and end date.
- **Action keyword** — filter by action type (e.g., "bypass", "login").

Click **"Download CSV"** to export the filtered log to a spreadsheet.

### 16.3 Super Admin — Viewing All Admin Activity

Super admins can switch the view to see **all admins' activity** by using the "All Users" toggle at the top of the Activity Log page.

### 16.4 Sub-Admin Activity (Super Admin View)

Super admins have a dedicated page for reviewing specifically what sub-admins have been doing:

```
http://[server-ip]/Kitengela_Parking/public/admin/subadmin_activity.php
```

This page shows actions performed by all accounts with the `admin` role (i.e., sub-admins), making staff supervision easier.

---

## 17. Admin Panel — Database Tools

> **Super Admin access only.** This section contains powerful tools that can permanently alter or delete data. Use with extreme caution.

### 17.1 Accessing Database Tools

From the admin navigation (Super Admin only), click **"Database Tools"**.

### 17.2 Database Search

The search tool allows you to look up records across any table in the system. This is useful for:

- Resolving disputes by looking up a specific plate's full history.
- Investigating a transaction ID.
- Verifying owner or staff details.

**To use the search:**
1. Select the **table** to search from the dropdown.
2. Enter a **search term** (e.g., a plate number, receipt number, or username).
3. Click **"Search"**.
4. Matching rows are displayed in a results table below.

### 17.3 Clearing Vehicle Logs (Archiving)

Over time, the vehicle_logs table accumulates thousands of records. Periodically, admins may want to clear old records to improve performance. This action is protected by a **CSRF token** and requires double confirmation.

**Before clearing logs, the system automatically:**
1. Calculates total revenue in the current logs.
2. Saves that revenue amount to the `revenue_archive` table with a timestamp, your username, and the number of records cleared.
3. Only then truncates the `vehicle_logs` and `mpesa_transactions` tables.

This means that even after clearing, the total revenue figure on the dashboard will still correctly include all historical earnings.

**To clear vehicle logs:**
1. On the Database Tools page, click **"Clear Vehicle Logs"**.
2. Read the warning dialog carefully.
3. Confirm **twice** when prompted.

> **Warning:** Clearing logs is irreversible. The individual vehicle records will be gone — only the aggregate revenue total is preserved.

### 17.4 Clearing Individual Tables

Super admins can also clear specific individual tables via the Database Tools page. All clears are protected with:
- A session-bound CSRF token.
- A double-confirmation dialog.

Tables that involve revenue are archived before clearing. Tables like `admin_activity` and `mpesa_transactions` will warn you that data loss is permanent.

---

## 18. Admin Panel — Profile and Password Management

### 18.1 Accessing Your Profile

From the admin navigation, click **"Profile"**.

The profile page shows:
- Your username
- Your role (admin or super_admin)
- Your account creation date
- A form to change your password

### 18.2 Changing Your Password

1. On the Profile page, scroll to the **"Change Password"** section.
2. Enter your **current password** in the first field.
3. Enter your **new password** in the second field.
4. Re-enter the **new password** in the confirmation field.
5. Click **"Update Password"**.

If the current password is correct and the two new password entries match, your password will be updated immediately.

**Password guidelines:**
- Use at least 8 characters.
- Use a mix of uppercase, lowercase, numbers, and special characters.
- Do not re-use recent passwords.
- Do not share your password with anyone.

All passwords are stored using **bcrypt hashing** — the system never stores your actual password in plain text.

### 18.3 Logging Out

To log out of the admin panel:

1. Click **"Logout"** in the navigation menu.
2. Your session will be destroyed and you will be redirected to the login page.

Your logout event is recorded in the activity audit log including your username, IP address, and the time of logout.

> **Best practice:** Always log out before leaving your workstation unattended, even if briefly.

---

## 19. Public Display Pages

### 19.1 Home Page Bay Grid

**URL:** `http://[server-ip]/Kitengela_Parking/public/`

A visual grid of all 262 parking bays, color-coded:
- **Green** = Vacant
- **Red** = Occupied

Per-floor counts (Basement 1 and Basement 2) are shown at the top with a total occupancy percentage. Suitable for a public lobby screen.

### 19.2 LED Display Board

**URL:** `http://[server-ip]/Kitengela_Parking/public/led_display.php`

Designed for large LED or television screens mounted at the parking entrance or lobby. Features:
- Dark background optimized for bright display panels.
- Large, clearly legible text showing vacant bay counts by floor.
- Summary of how many bays are available vs. total capacity.
- **Auto-refreshes every 10 seconds** so the information stays current without any interaction.

**Recommended display setup:**
1. Connect a dedicated computer or Raspberry Pi to the display screen.
2. Open a browser in full-screen mode (F11 in most browsers).
3. Navigate to the LED display URL.
4. The display will remain current indefinitely.

### 19.3 Staff Parking Display

**URL:** `http://[server-ip]/Kitengela_Parking/public/staff.php`

Shows a table of all staff vehicles currently parked with:
- Plate number
- Employee name
- Entry time

This page is useful for managers checking who is on-site or for displaying at a security desk.

---

## 20. Manual Gate Bypass (Emergency Override)

### 20.1 What Is a Manual Bypass?

A manual gate bypass is an emergency function available on the Admin Dashboard that allows an administrator to forcibly release a vehicle from the parking system without requiring an M-Pesa payment. This is used in genuine emergency situations to prevent vehicles from being trapped.

### 20.2 When to Use a Bypass

Appropriate situations for a bypass include:

- The driver's phone has no M-Pesa balance and no means of payment is available.
- The M-Pesa service is completely unavailable (Safaricom outage).
- There is a medical or security emergency requiring immediate vehicle release.
- A vehicle was entered with the wrong plate number and needs to be cleared.

> **Important:** Bypass actions are **irrevocable** once confirmed and are **permanently recorded in the audit log** with the administrator's name, IP address, and timestamp. Abuse of this feature will be visible in audit reviews.

### 20.3 How to Perform a Manual Bypass

1. Log in to the Admin Panel.
2. Go to the **Dashboard**.
3. Find the vehicle requiring bypass in the **Active Vehicles** table.
4. Click the **"Manual Bypass"** button in the Actions column for that row.
5. A **first confirmation dialog** will appear — read it and click **"Yes, bypass this vehicle"**.
6. A **second confirmation dialog** will appear — this is a final check. Click **"Confirm"**.
7. The system will:
   - Record the current time as the exit time.
   - Mark the payment status as `paid` (with a manual bypass flag).
   - Free the parking bay.
   - Log the full bypass event in `admin_activity`.
8. You will see a success message. Gate staff can now let the vehicle through.

### 20.4 Bypass Variants

| Scenario | What Happens |
|---|---|
| Vehicle is currently inside the facility | Normal bypass — exits the vehicle and frees the bay |
| Vehicle has already exited | Emergency override flag is stamped on the last log record |
| Vehicle has no record in the system at all | "Emergency Access" record is created and logged |

### 20.5 After a Bypass

After performing a bypass, note the following for compliance:
- The revenue report will show this record as a bypass (not a collected M-Pesa payment).
- The activity log will show exactly which admin performed the bypass and when.
- If money is owed, pursue collection through the mall's standard debt recovery process.

---

## 21. M-Pesa Payment Integration — Technical Overview

This chapter is intended for **technical staff** and **administrators** who need to understand how the M-Pesa integration works for troubleshooting purposes.

### 21.1 What Is M-Pesa STK Push?

STK Push (SIM Toolkit Push) is a Safaricom service that lets businesses trigger a payment prompt directly on a customer's phone. The customer sees a pop-up asking them to enter their M-Pesa PIN. No app download is required — the prompt works on any M-Pesa-enabled SIM.

### 21.2 Payment Flow Diagram

```
Driver at exit gate
        |
        v
Exit gate staff processes exit in system
        |
        v
Fee calculated (pay.php)
        |
        v
Driver enters phone number → clicks "Pay Now" (process_mpesa.php)
        |
        v
System calls Safaricom Daraja API (MpesaService::stkPush)
        |
        v
Safaricom sends STK Push to driver's phone
        |
        +--------------------------+
        |                          |
Driver APPROVES PIN           Driver CANCELS
        |                          |
        v                          v
Safaricom sends              Safaricom POST callback
SUCCESS callback             to CallBack.php (failed)
to CallBack.php                    |
        |                          v
        v                  vehicle_logs → 'failed'
vehicle_logs → 'paid'      Driver shown "Retry" button
exit_time = NOW()
Bay freed
        |
        v
SSE stream (payment_status_sse.php)
detects 'paid' status
        |
        v
Waiting page (waiting.php) shows SUCCESS
Boom gate opens automatically
```

### 21.3 Callback Security

The Safaricom callback URL is the endpoint to which Safaricom posts the payment result. This URL must be:
- **Publicly accessible** from the internet (not behind a private LAN) for production use.
- During development, a tool such as **ngrok** is used to expose the local server to the internet for testing.
- The callback URL can be configured via the `MPESA_CALLBACK_URL` environment variable.

### 21.4 Common M-Pesa Error Codes

| Error Code | Meaning | What to Do |
|---|---|---|
| 1 | Insufficient funds | Ask driver to top up M-Pesa and retry |
| 1032 | Transaction cancelled by user | Driver dismissed the pop-up — ask them to retry |
| 1037 | STK Push request timeout | Driver did not respond in time — click Retry |
| 2001 | Wrong PIN entered three times | M-Pesa may have locked — driver should call Safaricom |
| Network error | Safaricom API unreachable | Check internet connection; if Safaricom outage — use bypass |

### 21.5 Retry Logic

The system automatically retries a failed STK Push network request:
- Up to 2 retries on connection timeouts (60-second, then 90-second timeout).
- 1 retry on Safaricom server errors (HTTP 5xx).
- A 30-second cooldown is enforced between driver-initiated retries to prevent duplicate charges.

---

## 22. Business Rules and Pricing Policy

This chapter summarizes all enforced business rules as programmed in the system.

### 22.1 Fee Calculation Rules

| Rule | Policy |
|---|---|
| Grace period | Vehicles parked ≤ 30 minutes exit for free |
| Standard first hour | KES 50 flat fee for 31–60 minutes |
| Additional hours | KES 20 per additional started hour after the first |
| Daily cap | Any vehicle parked 12 hours or more is charged a flat KES 1,000 |
| Staff vehicles | Always KES 0, regardless of duration |
| Owner vehicles | Standard fee calculated, then 30% discount applied; balance invoiced monthly |

### 22.2 Access Control Rules

| Rule | Policy |
|---|---|
| Restricted vehicles | Entry denied at gate; no exceptions except admin bypass |
| Double-entry prevention | Vehicle already logged inside cannot be logged as entering again |
| Full parking | Entry denied when all 262 bays are occupied |

### 22.3 Owner Billing Rules

| Rule | Policy |
|---|---|
| Discount | 30% discount on all parking fees for registered owners |
| Invoice frequency | Monthy |
| Billing reset | `total_due` resets to zero after payment is recorded |

### 22.4 Admin Security Rules

| Rule | Policy |
|---|---|
| Password storage | bcrypt (one-way hash) — never stored in plain text |
| Session protection | Admin sessions validated on every page load |
| Self-delete prevention | Super admin cannot delete their own account |
| Last admin protection | Cannot delete the last remaining super_admin account |
| Audit logging | Every significant action logged with username, IP, and timestamp |
| CSRF protection | Destructive database operations require a session-bound CSRF token |

### 22.5 Revenue Integrity Rules

| Rule | Policy |
|---|---|
| Revenue archiving | Revenue is archived before any vehicle log is cleared |
| Total revenue | Dashboard always shows live + archived revenue combined |
| Plates stored as uppercase | All plate numbers normalized to uppercase before storage |

---

## 23. Troubleshooting Guide

### 23.1 Gate Issues

| Problem | Likely Cause | Solution |
|---|---|---|
| Plate not found on exit | Plate was not logged on entry, or different plate format used | Search for the plate in Database Tools (admin) or check entry log |
| "Vehicle already inside" on entry | Duplicate entry attempt or previous session not properly closed | Check dashboard — if vehicle is legitimately re-entering, use admin bypass to close old session |
| "Parking Full" message | All 262 bays marked as occupied | Check dashboard for stuck occupied bays; use bypass to clear any ghost records |
| System shows wrong bay | System assigned a bay that appears physically vacant | Verify occupancy manually; report to admin for investigation |

### 23.2 Payment Issues

| Problem | Likely Cause | Solution |
|---|---|---|
| STK Push not received | Incorrect phone number, phone off, no signal | Verify phone number format, check phone signal, retry |
| STK Push received but payment staying "pending" | Safaricom callback delayed or not received | Wait 2 minutes; if still pending, check callback URL accessibility |
| Paid but gate won't open | SSE stream disconnected before status update | Refresh waiting page or ask admin to check dashboard |
| Double charge on M-Pesa | Two STK pushes sent | Check mpesa_transactions table; issue one refund if confirmed double charge |

### 23.3 Admin Panel Issues

| Problem | Likely Cause | Solution |
|---|---|---|
| Can't log in | Wrong credentials, or account deleted | Verify username with super admin; request password reset |
| Dashboard shows stale data | Page not refreshed | Press F5 to reload |
| Revenue figure looks wrong | Archive not including recent cleared data | Revenue archive is always summed — verify individual records |
| Activity log empty | No actions logged yet, or wrong date filter | Clear date filter; try a wider date range |
| Can't delete a user account | Trying to delete own account or last super_admin | Choose a different account, or create a new super_admin first |

### 23.4 System-Level Issues

| Problem | Likely Cause | Solution |
|---|---|---|
| Pages loading slowly | Database performance, many records | Super admin can clear old logs with archiving |
| Database connection error | MySQL service stopped | Restart XAMPP, check MySQL service in XAMPP control panel |
| M-Pesa API errors | No internet, Safaricom outage, ngrok tunnel expired | Check internet; check Safaricom service status; renew ngrok tunnel |
| SSE stream not working | Browser/proxy blocking event streams | Try a different browser; check proxy settings |

---

## 24. Glossary of Terms

| Term | Definition |
|---|---|
| **Bay** | A single numbered parking space within the facility |
| **Boom Gate** | The mechanical arm barrier that controls entry/exit |
| **Bypass** | An emergency admin action to release a vehicle without M-Pesa payment |
| **Callback** | A server-to-server notification sent by Safaricom to the system confirming payment |
| **CSV** | Comma-Separated Values — a spreadsheet file format |
| **Daraja** | Safaricom's API platform for M-Pesa integration |
| **Grace Period** | The first 30 minutes of parking are free |
| **Invoiced** | Payment method used for owner accounts — accumulated and billed monthly |
| **M-Pesa** | Safaricom's mobile money service used for cashless payment |
| **Nominal Fee** | The full calculated parking fee before any discounts |
| **Occupied** | A parking bay that currently has a vehicle in it |
| **Owner Account** | A registered business or corporate account with monthly invoicing and discounts |
| **Plate Number** | A vehicle's registration number as displayed on its number plates |
| **Restricted Vehicle** | A vehicle that has been blocked from entering the facility |
| **Soft Delete** | Marking a record as deleted without actually removing it from the database — reversible |
| **SSE** | Server-Sent Events — a browser technology that lets the server push updates to the page without the driver refreshing |
| **STK Push** | SIM Toolkit Push — a Safaricom mechanism that sends a payment prompt directly to a customer's phone |
| **Sub-admin** | An admin user with restricted access (role = `admin`) |
| **Super Admin** | An admin user with full unrestricted access (role = `super_admin`) |
| **Vacant** | A parking bay that is currently empty and available |
| **Vehicle Log** | A database record tracking a single parking session from entry to exit |

---

## 25. Appendix A — Database Table Reference

| Table | Purpose |
|---|---|
| `administrators` | All admin user accounts; stores username, bcrypt-hashed password, and role |
| `admin_activity` | Append-only audit log of all admin actions with username, IP, action text, and timestamp |
| `vehicle_logs` | Core table — one row per vehicle parking session; entry time, exit time, bay, fees, M-Pesa reference |
| `mpesa_transactions` | One row per STK Push attempt; linked to vehicle_logs; stores receipt number and completion status |
| `parking_bays` | All 262 physical bays; tracks current occupancy status (vacant/occupied) |
| `owner_accounts` | Registered business owner vehicles; flags monthly invoicing |
| `owner_vehicle_fees` | Running fee ledger per owner plate; accumulates nominal fees and calculates discounted total due |
| `staff_vehicles` | Registered employee vehicles; these vehicles are always given free parking |
| `restricted_vehicles` | Blocked vehicles — any plate on this list is denied entry |
| `revenue_archive` | Historical revenue snapshots created when vehicle_logs is cleared; ensures revenue is never lost |

---

## 26. Appendix B — Quick Reference Card

### Quick Reference — Gate Entry Staff
1. Open `gate/entry.php` on your terminal.
2. Enter plate number → click **"Log Entry"**.
3. **Green banner** = success, raise gate, tell driver their bay.
4. **Red banner** = check message, do NOT raise gate.

---

### Quick Reference — Gate Exit Staff
1. Open `gate/exit.php` on your terminal.
2. Enter plate number → click **"Process Exit"**.
3. **Free exit** = raise gate immediately.
4. **Payment required** = direct driver to payment page.
5. Gate opens automatically once payment is confirmed.

---

### Quick Reference — Parking Fees

| Duration | Fee |
|---|---|
| 0 – 30 min | Free |
| 31 – 60 min | KES 50 |
| +1 hour extra | +KES 20 |
| All-day (12h+) | KES 1,000 |
| Staff vehicle | Always free |
| Owner vehicle | 30% discount, invoiced monthly |

---

### Quick Reference — Admin Login
- URL: `.../public/admin/login.php`
- Enter username and password.
- **Super admin:** Full access to all features.
- **Sub-admin:** Dashboard, vehicles, staff, owners, restricted, revenue reports, own activity log.

---

### Quick Reference — Emergency Bypass
1. Log in to Admin Panel → **Dashboard**.
2. Find vehicle in Active Vehicles table.
3. Click **"Manual Bypass"** → confirm **twice**.
4. Action is permanent and audit-logged.
5. Notify gate staff to raise the boom gate.

---

*End of User Manual — Kitengela Mall Parking Management System v1.0*

*For technical support, contact the system administrator.*
