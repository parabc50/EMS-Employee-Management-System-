<?php
// admin sidebar
?>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:32px;width:100%">
      <h3 style="margin:0">EMS<span> Admin</span></h3>
      <button class="menu-toggle" id="menuToggle" style="display:none;background:none;border:none;color:#fff;font-size:20px;cursor:pointer;padding:4px">☰</button>
    </div>
    <nav id="navMenu">
      <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false) ? 'active' : ''; ?>" href="/ems_php_sql/admin/dashboard.php">Dashboard</a>
      <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'users.php') !== false) ? 'active' : ''; ?>" href="/ems_php_sql/admin/users.php">Users</a>
      <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'tasks.php') !== false) ? 'active' : ''; ?>" href="/ems_php_sql/admin/tasks.php">Tasks</a>
      <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'leaves.php') !== false) ? 'active' : ''; ?>" href="/ems_php_sql/admin/leaves.php">Leaves</a>
      <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'salaries.php') !== false) ? 'active' : ''; ?>" href="/ems_php_sql/admin/salaries.php">Salaries</a>
      <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'departments.php') !== false) ? 'active' : ''; ?>" href="/ems_php_sql/admin/departments.php">Departments</a>
      <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'attendance.php') !== false) ? 'active' : ''; ?>" href="/ems_php_sql/admin/attendance.php">Attendance</a>
      <div style="margin-top:auto; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1)">
        <a class="nav-link" href="/ems_php_sql/logout.php">Logout</a>
      </div>
    </nav>
  </aside>
  <main class="main">
    <div class="container">
