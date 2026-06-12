BilikGo — room rental platform (improved iBilik concept)
=========================================================

A complete, self-contained PHP web app: tenants swipe rooms Tinder-style
to build a shortlist, owners manage their listings, admins moderate the site.

REQUIREMENTS
------------
- PHP 8.0 or newer with the pdo_sqlite extension (enabled by default on
  almost all shared hosting: cPanel, Hostinger, Exabytes, etc.)
- Apache or Nginx (or any host that runs PHP). No MySQL needed —
  the SQLite database creates and seeds itself on first visit.

DEPLOY (shared hosting)
-----------------------
1. Upload the contents of this folder to your web root
   (e.g. public_html/ or a subfolder like public_html/bilikgo/).
2. If using SQLite, make sure the web server can WRITE to data/
   (permissions 755, or 775 if writes fail). Owner photo uploads are
   stored INSIDE the database, so no writable uploads folder is needed
   and every database backup automatically includes all photos.
3. Open https://yoursite/setup.php in a browser. It checks your PHP
   environment, creates all tables, seeds the demo data, and tells you
   exactly what to fix if anything fails. DELETE setup.php afterwards.
   (Simply opening index.php also auto-creates the database.)

DATABASE OPTIONS
----------------
The app supports two databases — pick one in config.php (DB_DRIVER):

A) SQLITE (default, zero configuration)
   Nothing to do. The database file data/bilikgo.sqlite is created and
   seeded automatically on first visit. Best for demos and small sites.

B) MYSQL (recommended on InfinityFree for reliability)
   1. In the InfinityFree control panel open "MySQL Databases" and
      create a database (e.g. bilikgo).
   2. Copy the credentials it shows into config.php:
         define('DB_DRIVER', 'mysql');
         define('MYSQL_HOST', 'sqlXXX.infinityfree.com');
         define('MYSQL_NAME', 'epiz_12345678_bilikgo');
         define('MYSQL_USER', 'epiz_12345678');
         define('MYSQL_PASS', 'your-vpanel-password');
   3. Open setup.php — tables and demo data are created automatically.
      (Alternative: import database/install_mysql.sql via phpMyAdmin.)

Standalone SQL scripts (schema + demo seed, password Demo@123):
   database/install_sqlite.sql   for SQLite
   database/install_mysql.sql    for MySQL/MariaDB via phpMyAdmin
These are optional — setup.php / first page visit does the same thing.

DEMO ACCOUNTS (password for all: Demo@123)
------------------------------------------
   admin@bilikgo.test    - admin   (moderate users + all listings)
   owner@bilikgo.test    - owner   (manage own listings)
   owner2@bilikgo.test   - owner
   tenant@bilikgo.test   - tenant  (swipe + shortlist)

ROLES & ACCESS
--------------
   Tenant : swipe deck (swipe.php), shortlist, listing details,
            owner contact info for liked rooms.
   Owner  : dashboard with like-stats, create/edit/hide/delete the
            listings THEY uploaded, photo upload. No swipe pages.
   Admin  : site dashboard, manage every user (change role, suspend,
            delete), moderate every listing (suspend/restore/delete).
            No swipe pages.
   Sign-up offers tenant or owner only; admins are promoted by an
   existing admin from Users page (or seeded).

PASSWORD RESET
--------------
forgot.php emails a one-hour reset link via PHP mail(). Because many
hosts have mail() unconfigured, DEMO_MODE in config.php is set to true,
which also displays the reset link on screen. Set DEMO_MODE to false
in config.php once your host's email works.

GOING TO PRODUCTION
-------------------
- Set DEMO_MODE to false in config.php.
- Change the demo account passwords (or delete the demo users from
  the admin Users page) and remove the demo box from index.php.
- Serve over HTTPS.
- data/.htaccess and uploads/.htaccess already block direct DB access
  and PHP execution of uploads on Apache. On Nginx, add:
      location ^~ /data/    { deny all; }
      location ~* ^/uploads/.*\.php$ { deny all; }

FILE MAP
--------
config.php            core: DB, auth, helpers, layout
index.php             landing page
login.php / register.php / forgot.php / reset.php / logout.php
swipe.php             tenant swipe deck      (tenant only)
api_swipe.php         records likes/passes   (tenant only)
shortlist.php         liked rooms + contacts (tenant only)
listing.php           listing detail         (all roles, scoped)
owner_dashboard.php / owner_listings.php / owner_edit.php   (owner only)
admin_dashboard.php / admin_users.php / admin_listings.php  (admin only)
assets/               CSS, JS, seed images
database/             standalone SQL install scripts
data/                 SQLite database (auto-created, sqlite mode only)
image.php             serves photos stored in the database
