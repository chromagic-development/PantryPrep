PantryPrep — Digital Picklist System

Created by **Bruce Alexander**

---

## OVERVIEW

This is a web-based food pantry order management system.
* **Customer Interaction:** Customers submit orders through an online form.
* **Staff Workflow:** Staff pick items from a live queue dashboard.
* **Administration:** Administrators configure available items and view usage reports.
* **Database:** The application is self-contained and requires no external database server—it uses SQLite, which stores all data in a single local file (`picklist.db`).

---

## FILE STRUCTURE

### Root Directory (`/footprints/`)
* `index.php`: Customer-facing order form.
* `submit_order.php`: Handles order form POST submission (no UI).
* `api.php`: JSON API for AJAX calls from dashboards.
* `db.php`: Database initialization and shared helpers.
* `Footprint_logo.jpg`: Organization logo (required).
* `favicon.ico`: Browser tab icon (optional).
* `picklist.db`: SQLite database (auto-created on first run).

### Employee & Admin Folders
* `/footprints/orders/orders.php`: Employee pick queue dashboard.
* `/footprints/admin/admin.php`: Administrator configuration panel.
* `/footprints/admin/report.php`: Item usage reports with charts.

---

## SERVER REQUIREMENTS

* **PHP:** Version 7.2 or higher.
* **Extensions:** PDO and `pdo_sqlite` must be enabled.
* **Permissions:** The application folder must be writable by PHP to allow for the creation and updating of `picklist.db`.

---

## INSTALLATION

1.  **Upload:** Transfer all PHP files and images to your web server following the folder structure above.
2.  **Permissions:** Ensure the directory is writable by the web server.
3.  **Initialize:** Open `index.php` in your browser. The database and tables are created automatically on the first load.
4.  **Configure:** Navigate to `admin/admin.php`.
    * **Default Password:** `admin`
    * **Action:** Change this password immediately after your first login.

---

## PAGES & FEATURES

### Customer Order Form (`index.php`)
* **Item Selection:** Grouped by category in a two-column grid using toggle buttons.
* **Family Factor:** Quantities are determined by family size (Adults/Children).
    * *Calculation:* `ceil(Family Size * Factor)`. Example: Factor 0.5 for a family of 3 = 2 units.
* **Translation:** Includes a Google Translate widget supporting 11 languages, including Spanish, Arabic, and Vietnamese.

### Employee Pick Queue (`orders.php`)
* **Live Dashboard:** Shows pending orders with real-time progress bars.
* **Interactive Picking:** Toggle items as "picked" with a green checkmark.
* **Auto-Refresh:** The queue updates every 30 seconds to show new orders.

### Reports (`report.php`)
* **Anonymity:** Customer names are anonymized as "Client 1, Client 2," etc.
* **Visuals:** Includes inline quantity bars and bar charts that adjust layouts based on item count.

---

## SECURITY & SETTINGS

* **IP Restriction:** Access to the order form and employee dashboard can be restricted to a specific IP address in the Admin settings.
* **Authentication:** The admin login persists for 2 months via cookie. Changing the admin password instantly invalidates all existing sessions.
* **Privacy:** No personal data is exposed in reports, and the system is designed to be self-hosted for maximum data control.

---

## SUPPORT

For issues or questions, contact **Bruce Alexander**.
