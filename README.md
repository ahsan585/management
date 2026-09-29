# School Management System (Core PHP + PostgreSQL)

A lightweight school management web application built with **Core PHP (no frameworks)**, **Vanilla JavaScript**, **HTML/CSS**, and **PostgreSQL**.

## ✅ Features

- Multi-role authentication: **Admin**, **Teacher**, **Student**
- Role-based dashboards
- Student/Teacher CRUD (Admin)
- Attendance tracking (Teacher/Admin)
- Notes upload (PDF/Image) by Teacher/Admin
- Notices/alerts system with popups on login
- Exam scheduling and result management
- Student ID Card generator (printable)
- Secure authentication with password hashing and prepared statements

## 🗂️ Project Structure

```
/management
  /admin
  /teacher
  /student
  /uploads
    /photos
    /notes
    /notices
  /assets
  /config
  /includes
  /db
  index.php
  logout.php
  README.md
```

## 📦 Requirements

- PHP 7.4+ with PDO + PostgreSQL driver (`pdo_pgsql`)
- PostgreSQL
- Web server (XAMPP, WAMP, etc.)

## 🚀 Setup (Windows / XAMPP)

1. **Ensure PostgreSQL is installed and running.**
2. **Create the database**:

   ```ps
   psql -U postgres -c "CREATE DATABASE school_management;"
   ```

3. **Run the schema script**:

   ```ps
   psql -U postgres -d school_management -f "C:\xampp\htdocs\management\db\schema.sql"
   ```

4. **Configure database connection**

   Edit `config/db.php` (or set environment variables):

   - `DB_HOST` (default `localhost`)
   - `DB_PORT` (default `5432`)
   - `DB_NAME` (default `school_management`)
   - `DB_USER` (default `postgres`)
   - `DB_PASS` (default `postgres`)

5. **Start Apache** (via XAMPP Control Panel)
6. **Open in browser**:

   `http://localhost/management/`

## 🔐 Default Accounts

| Role    | Username | Password   |
|--------|----------|------------|
| Admin   | `admin`  | `password` |
| Teacher | `200001` | `password` |
| Student | `100001` | `password` |

> You can change passwords via the user management UI (Admin panels for teachers/students).

## 🧩 Adding New Students

- Use the Admin → Students menu
- A new **Student Key** is generated automatically (starts at `100001`)
- Default password is `password` (change after first login)

## 🗄️ Uploads & Storage

Files are stored under `/uploads`:

- Photos: `/uploads/photos`
- Notes: `/uploads/notes`
- Notices: stored in database

## 🔧 Extending the System

This system is designed to be modular:

- Add new pages under `/admin`, `/teacher`, `/student`
- Use `config/init.php` and `includes/sidebar.php` to share layout
- Use prepared statements via `$pdo` (available globally)

---

If you want enhancements (e.g., email notifications, role-based permissions, multi-class support, or API endpoints), I can help you extend this further.
