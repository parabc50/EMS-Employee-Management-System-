# Employee Management System (EMS)

A comprehensive, modern, web-based Employee Management System built with PHP and MySQL. Designed for organizations to efficiently manage their workforce with a professional, interactive interface and role-based access control.

## 🚀 Current Progress: UI Modernization (April 2026)

The project has recently undergone a major UI/UX overhaul to provide a professional, "SaaS-like" experience. Key updates include:

- **Modern Visual Design**: Implemented a new design system using a refined Indigo/Slate color palette, "Inter" typography, and consistent spacing.
- **Interactive Dashboards**: Redesigned Admin and Employee dashboards with animated stat widgets, interactive charts (Chart.js), and intuitive layouts.
- **Enhanced Mobile Experience**: Fully responsive design with a mobile-optimized sidebar that adapts to any screen size.
- **Glassmorphism & Aesthetics**: Added subtle gradients, glassmorphism effects on landing pages, and smooth transitions for a premium feel.
- **Improved Components**: Modernized tables with hover states, refined form designs, and standardized badges for status tracking.

## Features

### Core Functionality
... (rest of the features)

- **User Authentication**: Secure login system with role-based access control
- **Attendance Management**: Track employee clock-in/clock-out with daily attendance records
- **Leave Management**: Request, approve, and manage employee leaves
- **Salary Management**: Manage employee salaries and payment tracking
- **Task Management**: Assign and track employee tasks with priority levels
- **Department Management**: Organize employees by departments with manager assignments
- **User Management**: Create, edit, and manage employee profiles
- **Dashboard**: Role-specific dashboards for admins, managers, and employees
- **Notifications**: In-app notification system for important updates

### Admin Features

- View and manage all employees
- Edit employee information and salary details
- Import/export attendance records
- Manage departments and department assignments
- Approve/reject leave requests
- Create and assign tasks
- Generate reports

### Manager Features

- View department-specific employees
- Manage attendance for department members
- Approve/reject leave requests for team
- Assign and track tasks for team members
- View department salary information

### Employee Features

- View personal dashboard
- Mark attendance
- Request leaves
- View tasks and deadlines
- Check salary information
- View personal notifications

## System Requirements

- **Web Server**: Apache with PHP support
- **PHP**: Version 7.4 or higher
- **MySQL/MariaDB**: Version 5.7 or higher
- **Browser**: Modern web browser (Chrome, Firefox, Safari, Edge)

### Required PHP Extensions

- MySQLi
- Session support

## Installation

### 1. Download/Clone the Project

Download the project files to your web root directory:

```bash
# Using XAMPP
cd C:\xampp\htdocs\
# Copy the ems_php_sql folder here
```

### 2. Create MySQL Database

```bash
# Open MySQL/MariaDB command line or phpMyAdmin
mysql -u root -p

# Create the database
CREATE DATABASE ems_php_sql;
USE ems_php_sql;

# Import the database schema
SOURCE database.sql;
```

Or import using phpMyAdmin:
- Open phpMyAdmin
- Create a new database named `ems_php_sql`
- Import the `database.sql` file

### 3. Configure Database Connection

Edit [include/db.php](ems_php_sql/include/db.php) with your database credentials:

```php
$server = "localhost";
$username = "root";
$password = "";
$db = "ems_php_sql";
```

### 4. Access the Application

Open your web browser and navigate to:

```
http://localhost/ems_php_sql/
```

## Configuration

### Timezone Settings

The application is configured for Indian Standard Time (Asia/Kolkata) by default. To change the timezone:

Edit [include/config.php](ems_php_sql/include/config.php):

```php
date_default_timezone_set('Your/Timezone');
```

## User Roles

The system supports four user roles with different permissions:

| Role | Permissions |
|------|-------------|
| **Admin** | Full access to all features and user management |
| **Employee** | Access to personal dashboard, submit leaves, view tasks |
| **Manager** | Department-level access, approve leaves for team, manage team tasks |
| **HR** | Human resources functions, salary management |

### Default Credentials

```
Username: admin
Password: (configured in database.sql)

Username: employee
Password: (configured in database.sql)
```

> ⚠️ **Security Note**: Change default credentials immediately after installation.

## Project Structure

```
ems_php_sql/
├── admin/                          # Admin dashboard and management pages
│   ├── attendance.php              # Manage attendance records
│   ├── attendance_import.php        # Import attendance data
│   ├── attendance_export.php        # Export attendance reports
│   ├── dashboard.php               # Admin dashboard
│   ├── departments.php             # Manage departments
│   ├── users.php                   # Manage users
│   ├── leaves.php                  # Manage leave requests
│   ├── salaries.php                # Manage salaries
│   ├── tasks.php                   # Create and assign tasks
│   └── ...                         # Other admin pages
├── employee/                       # Employee dashboard and pages
│   ├── dashboard.php               # Employee dashboard
│   ├── attendance.php              # View/mark attendance
│   ├── leaves.php                  # Request leaves
│   ├── salary.php                  # View salary information
│   └── tasks.php                   # View assigned tasks
├── include/                        # Core configuration and includes
│   ├── config.php                  # Application configuration
│   ├── db.php                      # Database connection and helpers
│   ├── header.php                  # Common header template
│   ├── footer.php                  # Common footer template
│   ├── admin_header.php            # Admin header template
│   └── admin_footer.php            # Admin footer template
├── css/                            # Stylesheets
│   └── style.css                   # Main application styles
├── scripts/                        # JavaScript files
│   ├── validation.js               # Client-side validation
│   └── mark_absent.php             # Mark absent functionality
├── docs/                           # Documentation
│   └── run_tests.php               # Test runner
├── database.sql                    # Database schema and initial data
├── index.php                       # Home page
├── login.php                       # User login page
├── logout.php                      # User logout handler
├── notifications_mark_read.php     # Mark notifications as read
└── README.md                       # This file
```

## Database Schema

### Users Table

Stores employee and admin information with roles and department assignments.

### Attendance Table

Tracks daily clock-in/clock-out records with attendance status:
- **Status**: present, absent, on_leave
- **Fields**: user_id, date, clock_in, clock_out, status, notes

### Leaves Table

Manages employee leave requests with approval workflow:
- **Status**: pending, approved, rejected

### Salaries Table

Maintains salary information and payment records:
- **Status**: paid, unpaid

### Tasks Table

Stores task assignments and tracking:
- **Priority**: low, medium, high
- **Status**: pending, in_progress, completed

### Departments Table

Organizes employees into departments with manager assignments.

### Notifications Table

Manages in-app notifications for users.

## Usage

### For Employees

1. **Login** with your credentials
2. **Dashboard** - View overview of your information
3. **Mark Attendance** - Clock in/out for the day
4. **Request Leave** - Submit leave applications
5. **View Tasks** - Check assigned tasks and deadlines
6. **View Salary** - Review salary information

### For Managers

1. **Login** with manager credentials
2. **Access dashboard** - Department-specific overview
3. **Manage Team** - View and manage department members
4. **Approve Leaves** - Review and approve team leave requests
5. **Assign Tasks** - Create tasks for team members
6. **Monitor Attendance** - Track team member attendance

### For Admins

1. **Login** with admin credentials
2. **System Dashboard** - Organization-wide overview
3. **User Management** - Add, edit, delete employees
4. **Attendance Management** - View, edit, import/export attendance
5. **Leave Approval** - Manage leave requests across organization
6. **Salary Management** - Manage all employee salaries
7. **Department Management** - Create and manage departments
8. **Task Assignment** - Create and track tasks

## Security Features

- **Password Hashing**: Uses PHP's `password_hash()` with automatic legacy SHA1 migration
- **Session Management**: Session regeneration to prevent session fixation
- **SQL Injection Prevention**: Prepared statements for all database queries
- **Role-Based Access Control**: Enforced access control based on user roles
- **Login Required**: Authentication check on all protected pages
- **Logout Functionality**: Secure session termination

## Support & Documentation

For more information, refer to:
- Database schema: [database.sql](ems_php_sql/database.sql)
- Configuration: [include/config.php](ems_php_sql/include/config.php)
- Project files in `/admin`, `/employee`, and `/include` directories

## License

This project is provided as-is for internal organizational use.

---

**Version**: 1.0  
**Last Updated**: April 2026  
**Organization**: Employee Management System (EMS)
