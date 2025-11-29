
# Telecom Billing and Invoicing System

This project is a role-based telecom billing and invoicing system developed using PHP and MySQL. It allows administrators to manage customers, usage records, billing rates, and invoice generation, while customers can view their usage, invoices, payment status, and billing history. The system simulates a real-world telecom billing workflow with modules for usage tracking, automated invoice generation, and financial reporting.

## Overview

The application supports two primary user roles:

### Admin

* Manage users and customer accounts
* Add usage records (calls, SMS, internet data)
* Set and update billing rates
* Generate monthly invoices
* Access revenue and invoice reports
* Review customer usage and billing history

### Customer

* View monthly invoices
* Review detailed billing items
* Track usage history
* View billing summaries
* Submit mock payments
* Access a role-specific dashboard

The system begins at **login.php**, and redirects users to their appropriate dashboard based on role.
There is **no public landing page** in this implementation.

## Key Features

### Billing and Invoicing

* Automatic invoice calculation
* Support for taxes, discounts, rate changes, and late fees
* Itemized billing details
* Viewable invoice summaries and details

### Usage Tracking

* Records for calls, SMS, and data
* Monthly aggregation and review
* Admin-controlled submission and updates

### Authentication and Role Management

* Separate login and registration pages
* Role-based redirects and session control

### Reporting

* Monthly invoice summaries
* Revenue reports
* Usage analytics

### System Documentation

All UML diagrams, use case descriptions, and system models are provided in the project documentation report.
These include:

* Use Case Diagrams
* Class Diagrams
* Sequence Diagrams
* Activity Diagrams

## Project Structure

```
Billing-System/
│
├── config/
│   └── db.php
│
├── admin/
│   ├── admin.php
│   ├── admin-dashboard.php
│   ├── admin-navbar.php
│   ├── add_usage.php
│   ├── manage_users.php
│   ├── generate_invoice.php
│   ├── monthly_invoice_report.php
│   ├── usage_data.php
│   ├── reports.php
│   └── invoices.php
│
├── customer/
│   ├── customer.php
│   ├── customer-dashboard.php
│   ├── customer_navbar.php
│   ├── customer-usage.php
│   ├── customer-billing-history.php
│   ├── customer-invoices.php
│   ├── customer-invoice-details.php
│   └── pay_invoice.php
│
├── billing/
│   ├── billing.php
│   └── rates.php
│
├── auth/
│   ├── login.php
│   ├── register.php
│   └── logout.php
│
├── public/
│   └── index.php   (optional; not used in this implementation)
│
└── README.md
```

## Database Notice

The original SQL database file is **not included**.
This repository focuses on demonstrating the application’s structure, billing logic, reporting features, and role-based functionality.
The database tables can be recreated manually based on the queries inside the PHP files.

Common tables include:

* `users`
* `usage_records`
* `invoices`
* `invoice_items`
* `rates`
* `payments`

## How the System Works

1. The system begins at `login.php`
2. Users authenticate with role-based redirect:

   * Admin → `admin-dashboard.php`
   * Customer → `customer-dashboard.php`
3. Each role accesses its own set of features and views
4. Admins generate invoices and manage usage data
5. Customers review invoices, usage, and perform mock payments
6. Reports and analytics support administrative decision-making

## Summary

This project implements a complete telecom billing workflow using PHP and MySQL. It includes authentication, role-based dashboards, usage tracking, automated invoicing, and financial reporting. The modular structure makes the system easy to maintain and extend. All system diagrams and models are available in the attached project documentation.


