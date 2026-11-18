# Tech SG Invoicing System

A web-based invoicing and business management system developed for Tech SG Studio. The system helps manage clients, items, invoices, quotations, payments, receipts, recurring invoices, and business reports.

## Features

### Authentication
- User login and authentication
- Staff and administrator roles
- User management

### Dashboard
- Business overview
- Invoice and payment summaries
- Quick access to system modules

### Client Management
- Add, view, edit, and manage clients
- Store client information

### Item Management
- Add and manage products or services
- Maintain item details and pricing

### Invoice Management
- Create and manage invoices
- Save invoice records
- Generate invoice PDFs
- Print invoices
- Send invoices through email

### Quotation Management
- Create and manage quotations
- View quotation details
- Convert quotations into invoices

### Payments and Receipts
- Manage payment records
- Track invoice payments
- Generate and manage receipts

### Additional Modules
- Recurring invoices
- Reports
- Staff and user management
- Settings
- Audit log

## Technologies Used

- HTML5
- CSS3
- JavaScript
- PHP
- MySQL / MariaDB
- Font Awesome
- Gmail SMTP for email testing

## Project Structure

The project is organized into frontend and backend folders.

```text
techsg-invoicing/
│
├── frontend/
│   ├── index.html
│   ├── invoices.html
│   ├── quotations.html
│   ├── clients.html
│   ├── items.html
│   ├── payments.html
│   ├── reports.html
│   ├── settings.html
│   ├── users.html
│   ├── core.js
│   ├── app.js
│   └── other frontend files
│
├── backend/
│   ├── config.php
│   └── PHP API and backend files
│
└── README.md
```

## Requirements

- Windows, Linux, or macOS
- PHP
- MySQL or MariaDB
- XAMPP (recommended for local development)
- Web browser
- phpMyAdmin

## Installation and Setup

### 1. Clone the Repository

```bash
git clone YOUR_GITHUB_REPOSITORY_URL
```

Or download the project ZIP file from GitHub and extract it.

### 2. Install and Start XAMPP

- Install XAMPP.
- Start Apache.
- Start MySQL.

### 3. Create the Database

1. Open phpMyAdmin:

   `http://localhost/phpmyadmin`

2. Create a database named:

   `techsg_invoice`

3. Select the database.
4. Open the Import tab.
5. Select the supplied SQL database export file.
6. Click Import.

### 4. Configure the Backend

Open the backend configuration file:

`backend/config.php`

Update the database connection settings according to your local environment.

Example:

```php
// Example database configuration
// Use the actual settings required by your environment.

DB_HOST: localhost
DB_NAME: techsg_invoice
DB_USER: root
DB_PASS: your_database_password
```

Keep actual credentials private. Do not upload database passwords or SMTP credentials to GitHub.

### 5. Run the Application

Run the application using the local PHP development setup configured for this project.

The local development URL used during development is:

`http://localhost:8000`

Open the application in your browser.

## Email Configuration

Email sending has been tested using Gmail SMTP.

For production deployment, the company's official email configuration is required.

Planned company email accounts:

- info@techsgstudio.com
- no-reply@techsgstudio.com

Expected SMTP configuration, subject to confirmation:

- SMTP Host: smtp.hostinger.com
- Port: 465
- Encryption: SSL

The official mailbox and SMTP credentials must be configured before production email testing.

**Important:** Never publish SMTP passwords or private email credentials in this repository.

## Database

The project uses MySQL/MariaDB to store application data.

The database export file should be supplied separately as:

`techsg_invoice_database.sql`

Import this file through phpMyAdmin to create the database structure and restore the included data.

## Testing

The following functionality has been tested during development:

- User login
- Invoice creation and saving
- PDF generation
- Email sending through Gmail SMTP
- Quotation management
- Quotation-to-invoice conversion
- Frontend navigation and sidebar consistency

## Production Deployment

The application is developed and tested in a local environment.

The following tasks are required before production use:

- Deploy the application to the company's hosting account.
- Configure the production database.
- Update database connection credentials.
- Configure the official company email and SMTP credentials.
- Test invoice PDF generation and email delivery on the live server.
- Create and verify the supervisor's Administrator account.

Hosting and official email configuration will be managed by the company.

## Developer
**Maryam Salam**

**Maryam Ayub**

Software Engineering Student  
Frontend / Full-Stack Development

GitHub: https://github.com/maryamfew77-art



---

**Project:** Tech SG Invoicing System  
**Status:** Development completed; production deployment and official email setup pending.

