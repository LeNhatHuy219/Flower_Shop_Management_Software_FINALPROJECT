🌸 Blossom

🌟 Project Overview

Blossom is an elegant and user-friendly E-commerce Web Application developed to provide a seamless flower shopping experience. It bridges the gap between beautiful floral arrangements and customers through a digital platform, covering everything from product discovery to secure checkout and order tracking.

This project demonstrates the implementation of a full-stack web solution using native technologies, featuring distinct modules for product filtering, wishlist management, and a comprehensive administration panel for efficient inventory and order control.

🚀 Technology Stack

Category

Technology

Details

Backend

PHP (Native)

Handles server-side logic, session management, and database interactions without relying on heavy frameworks.

Database

MySQL

Manages relational data including Products, Categories, User Profiles, Orders, and Wishlists.

Frontend

HTML5, CSS3, JavaScript

Built for a responsive and visually appealing interface, ensuring smooth navigation across devices.

Server

Apache (XAMPP)

Local development environment used for hosting the application.

✨ Key Features

The system is structured to support both customers and administrators effectively:

1. Customer Shopping Experience

Advanced Search & Filtering: Customers can easily find flowers using keywords or filter products based on price range, categories, and availability.

Wishlist Management: Allows users to save their favorite floral arrangements for future purchase considerations.

Seamless Checkout Flow: A streamlined process to add items to the cart, review details, and complete the purchase securely.

2. User Account Management

Order Tracking: Users can monitor the real-time status of their current orders and access a complete history of past purchases.

Profile Customization: Provides a secure interface for users to update their personal details, contact information, and account passwords.

3. Administration Panel

Product & Category Management: rigorous tools for Admins to create, update, or delete flower products and manage product categories efficiently.

Order Processing: Admins can view incoming orders, update payment statuses, and manage the delivery workflow.

Dashboard Analytics: (Future Scope) Visual overview of sales performance and inventory levels.

🛠 Installation Guide

Prerequisites

XAMPP (or any standard PHP/MySQL environment like WAMP/MAMP).

Setup Steps

Clone/Download the Project:
Place the project folder into your server directory (e.g., htdocs inside XAMPP).

# Example path
C:/xampp/htdocs/flower


Initialize Database:

Start Apache and MySQL services in your XAMPP Control Panel.

Navigate to http://localhost/phpmyadmin.

Create a new database (e.g., shop_db).

Import the provided SQL file (shop_db.sql) from the project root directory.

Configure Connection:

Open the config.php file in your code editor.

Ensure the database connection settings match your local MySQL credentials:

$conn = mysqli_connect('localhost', 'root', '', 'shop_db');


Run the Application:

Access the website in your browser:

http://localhost/flower


🔐 Test Accounts

Use these pre-configured accounts to test the different user roles and permissions:

Role

Email

Password

Admin

admin01@gmail.com

123

Staff

staff01@gmail.com

123456

User

user01@gmail.com

123

