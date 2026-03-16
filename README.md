# PantryPrep
Web application for submitting and preparing food pantry orders providing a digital picklist system  
to queue picklist orders for a volunteer/employee team from a client submission page.  

FILES  
─────  
  index.php        → Customer order form (public-facing)  
  employee.php     → Employee pick queue dashboard  
  admin.php        → Admin: configure order form items  
  submit_order.php → Form submission handler (POST target)  
  api.php          → AJAX API for employee dashboard  
  db.php           → SQLite database setup & connection  
  picklist.db      → Auto-created SQLite database (on first run)  

REQUIREMENTS  
────────────  
  • PHP 7.4+ with PDO and pdo_sqlite extensions enabled  
  • A web server (Apache, Nginx, or PHP's built-in server)  
  • Write permissions on the folder (for SQLite database)  

QUICK START (Local Testing)  
────────────────────────────  
  1. Unzip all files into a folder, e.g. /var/www/html/footprints/  
  2. Make sure the folder is writable:  
       chmod 755 /var/www/html/footprints/  
  3. Open your browser:  
       http://localhost/footprints/index.php       ← Customer Form  
       http://localhost/footprints/employee.php    ← Employee Dashboard  
       http://localhost/footprints/admin.php       ← Manage Items  

  OR run PHP's built-in server:  
       cd /path/to/footprints  
       php -S localhost:8080  
       Then visit http://localhost:8080/  

PRODUCTION SETUP  
────────────────  
  • Place files inside your web root (public_html, www, htdocs, etc.)  
  • Ensure the directory is writable by the web server user  
  • Optionally move picklist.db OUTSIDE the web root for security,  
    then update the $dbPath in db.php accordingly  
  • Consider adding HTTP Basic Auth to employee.php and admin.php  

HOW IT WORKS  
────────────  
  CUSTOMER FLOW:  
  1. Customer opens index.php  
  2. Enters their name, # of adults/children  
  3. Checks desired items (by category)  
  4. For items with size (diapers), a size field appears  
  5. Submits → order saved to database → confirmation shown  

  EMPLOYEE FLOW:
  1. Employee opens employee.php  
  2. Sidebar shows all pending orders (auto-refreshes every 30s)  
  3. Click an order to open its picklist  
  4. Click each item to mark it as picked (green check)  
  5. Once ALL items are picked, "Mark Complete" button activates  
  6. Click "Mark Complete" → order removed from queue  

  ADMIN FLOW:  
  1. Open admin.php  
  2. Add/remove/toggle items  
  3. Drag rows to reorder  
  4. Click "Save All Changes"  
  5. New orders will immediately use the updated item list  

DEFAULT ITEMS  
─────────────  
  DAIRY:        Salted Butter, Unsalted Butter, Eggs  
  DRY GOODS:    Canned Tuna, Canned Chicken, Almond Milk, Kid's Snacks (16 and Under)  
  FROZEN ITEMS: Ground Beef, Fish Nuggets, Whole Turkey  
  SPECIALS:     Coffee, Tea  
  OTHER ITEMS:  Diapers (Child) w/ size, Diapers (Adult) w/ size  

═══════════════════════════════════════════════════════════  

This web application was originally created for:  
<p align="center">
  <img src="https://repository-images.githubusercontent.com/1180328768/30b1f8a5-d5a5-4aa6-975d-faa79e792771" />
</p>
