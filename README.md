# Student Registration and Academic Management System

Adama Polytechnic College. Built with PHP, HTML, CSS and MySQL to run on XAMPP.

## Portable version (nothing to install)

Run `.\build-portable.ps1 -Zip` on a PC that has XAMPP. It creates `..\SRMS-Portable` and `..\SRMS-Portable.zip` (about 17 MB). The package contains PHP, MySQL (MariaDB), the app and a ready database.

On any 64-bit Windows PC, unzip it and double-click **`Start.bat`**. The browser opens automatically. Press Enter in the black window to stop. It uses its own ports (3307 and 8080, or the next free ones), so it works alongside an existing XAMPP or MySQL. See `USER GUIDE.txt` inside the package, a short guide for non-technical users.

## Quick setup (installer)

1. Install XAMPP from https://www.apachefriends.org.
2. Double-click **`setup.bat`** in this folder.

The installer finds XAMPP, starts MySQL and Apache if they aren't running, and copies the project to `xampp\htdocs\srms`. It then creates the database and admin account, removes `install.php` from the installed copy, and opens http://localhost/srms/.

- If an earlier copy is already installed, it's renamed to `srms_backup_<date>` rather than deleted.
- Run `setup.bat` again whenever you change the project files, so the installed copy gets the changes.

Options (from PowerShell, in this folder):

| Option | Use |
|---|---|
| `-XamppPath "D:\xampp"` | XAMPP is somewhere other than `<drive>:\xampp` |
| `-AppName myname` | Install to `htdocs\myname`, opened at `http://localhost/myname/` |
| `-DbPassword "secret"` | Your MySQL root user has a password |
| `-KeepInstaller` | Keep `install.php` in the installed copy |
| `-NoBrowser` | Don't open the browser when finished |

Example: `.\setup.ps1 -XamppPath "D:\xampp" -DbPassword "secret"`

## Manual setup

1. Install XAMPP from https://www.apachefriends.org (it includes Apache, PHP 8, MySQL/MariaDB and phpMyAdmin).
2. Open the **XAMPP Control Panel** and click **Start** next to **Apache** and **MySQL**.
3. Copy this whole `srms` folder to `C:\xampp\htdocs\`, so you have `C:\xampp\htdocs\srms\index.php`.
4. In your browser, open **http://localhost/srms/install.php**. This creates the `srms` database, the tables and the first admin account.
5. Delete `install.php` once setup is complete.
6. Open **http://localhost/srms/** and log in.

### Default login

| Username | Password   |
|----------|------------|
| `admin`  | `admin123` |

The default login is set in `includes/config.php` (`DEFAULT_ADMIN_USERNAME` / `DEFAULT_ADMIN_PASSWORD`) and is only used when `install.php` creates the first account.

**Other ways to create the database:** in phpMyAdmin (http://localhost/phpmyadmin), use **Import** to load `database.sql`. That creates the tables but no user account, so run `install.php` afterwards to add the admin.

If your MySQL `root` user has a password, put it in `DB_PASS` in `includes/config.php`.

## Features

| Requirement | Where |
|---|---|
| Login / logout, internal pages protected | `login.php`, `logout.php`, `require_login()` in `includes/config.php` |
| Dashboard with totals and navigation | `dashboard.php` |
| Student registration, view, search (ID / name / department), update, delete | `students.php`, `student_form.php` |
| Course registration, view, search (code / title), update, delete | `courses.php`, `course_form.php` |
| Grade insertion, view, update, delete | `grades.php`, `grade_form.php` |
| Automatic grade and pass/fail | `calc_grade()` in `includes/config.php` |
| Academic report searched by student ID, printable | `report.php` (use **Print report**) |
| Administrator and registrar accounts (admin only) | `users.php` |
| Change own password | `account.php` (click your name in the top bar) |
| MySQL database | `database.sql` |

### Grading scale

| Mark | Grade | Points |
|---|---|---|
| 85–100 | A | 4 |
| 70–84 | B | 3 |
| 60–69 | C | 2 |
| 50–59 | D | 1 |
| 0–49 | F | 0 |

A mark of 50 or above is a **Pass** (`PASS_MARK` in `includes/config.php`). To use your college's own scale, change `calc_grade()` and `grade_points()` in `includes/config.php`, and the JavaScript preview in `grade_form.php`.

### Protecting historical records

A student or course that already has grades cannot be deleted. The page shows a message instead, and the database enforces the same rule with `ON DELETE RESTRICT` foreign keys. Students and courses with no grades can be deleted normally.

## Security

- Passwords are stored as hashes (`password_hash` / `password_verify`).
- Every query uses prepared statements, which prevents SQL injection.
- All output is escaped with `htmlspecialchars`, which prevents XSS.
- Every form that changes data carries a CSRF token.
- The session ID is regenerated when a user logs in.

## Files

```
srms/
├── setup.bat / setup.ps1  installer: copies to XAMPP and sets up the database
├── build-portable.ps1   builds the portable package (uses files in portable\)
├── users.php / account.php
├── index.php            redirects to login or dashboard
├── install.php          one-time setup (delete after use)
├── database.sql         database schema
├── login.php / logout.php
├── dashboard.php
├── students.php / student_form.php
├── courses.php  / course_form.php
├── grades.php   / grade_form.php
├── report.php
├── includes/
│   ├── config.php       DB settings, helpers, grading rules
│   ├── header.php       shared top navigation
│   └── footer.php
└── assets/style.css
```
