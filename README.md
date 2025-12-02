# ADESCO Accounting System - Setup Instructions

## Prerequisites
- XAMPP with Apache and MySQL installed and running
- PHP and MySQL in your system PATH variables

## Setup Steps

1. Place the `adesco_accounting` folder in your XAMPP `htdocs` directory:
   - Usually located at `C:\xampp\htdocs\`

2. Start XAMPP Control Panel:
   - Start Apache and MySQL services

3. Create the database:
   - Option 1: Use phpMyAdmin
     - Go to http://localhost/phpmyadmin
     - Create a new database named `adesco_accounting`
     - Import the `create_database.sql` file
   
   - Option 2: Use MySQL command line
     - Open Command Prompt as Administrator
     - Run: `mysql -u root -p < create_database.sql`
     - Enter your MySQL password when prompted (or press Enter if no password is set)

4. Access the application:
   - Open your browser
   - Go to http://localhost/adesco_accounting/

## Troubleshooting
- If you get connection errors, make sure MySQL is running in XAMPP
- If the page doesn't load, ensure Apache is running in XAMPP
- If you have issues with the database, verify the database name and credentials in config.php

## Features
- View and manage accounting entries (entradas and salidas)
- Automatic calculation of current balance
- Add, edit, and delete entries
- Responsive Bootstrap UI