<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMS - Employee Management System</title>
    <link rel="stylesheet" type="text/css" href="/ems_php_sql/css/style.css">
    <script>
        // Toggle mobile menu
        document.addEventListener('DOMContentLoaded', function() {
            const toggle = document.getElementById('menuToggle');
            const menu = document.getElementById('navMenu');
            
            if (toggle && menu) {
                toggle.addEventListener('click', function() {
                    menu.classList.toggle('active');
                });
                
                menu.querySelectorAll('.nav-link').forEach(link => {
                    link.addEventListener('click', function() {
                        menu.classList.remove('active');
                    });
                });
            }
        });
    </script>
</head>
<body>
