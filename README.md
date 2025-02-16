# Finance Manager

<div align="center">

![Finance Manager Logo](https://via.placeholder.com/150)

[![PHP Version](https://img.shields.io/badge/PHP-8.x-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

A powerful and user-friendly financial management system built with PHP and JavaScript.

</div>

## 🌟 Features

- 💰 Income and expense tracking
- 📊 Financial reporting and analytics
- 📱 Responsive design for mobile and desktop
- 🔒 Secure user authentication
- 💳 Budget management
- 📈 Transaction history and categorization

## 🚀 Technologies Used

- **Backend:** PHP (88%)
- **Frontend:** 
  - JavaScript (9.2%)
  - CSS (0.3%)
- **Additional:** Hack (2.5%)
- **Database:** MySQL

## ⚙️ Requirements

- PHP 8.0 or higher
- MySQL/MariaDB
- Web server (Apache/Nginx)
- Modern web browser

## 📥 Installation

1. Clone the repository
```bash
git clone https://github.com/minggudevv/finance_manager.git
```

2. Navigate to the project directory
```bash
cd finance_manager
```

3. Configure your database
- Navigate to `/src/components/config/database.php`
- Update the database configuration according to your environment:
```php
$conn = new PDO(
    "mysql:host=your_host;dbname=your_database;charset=utf8mb4",
    "your_username",
    "your_password",
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]
);
```

4. Create a new MySQL database named `keuangan`
```sql
CREATE DATABASE keuangan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

5. Import the database schema (if provided) or run your database migrations

## 🔧 Database Configuration

The database configuration is located in `/src/components/config/database.php`. By default, it uses these settings:

```php
Host: localhost
Database: keuangan
Username: root
Password: (empty)
Charset: utf8mb4
Collation: utf8mb4_unicode_ci
```

To modify these settings, edit the PDO connection string and credentials in the database.php file.

## 📖 Usage

1. Configure your web server to point to the project directory
2. Access the application through your web browser
3. Start managing your finances:
   - Add income and expenses
   - Create budgets
   - Generate reports
   - Track your financial goals

## 🔐 Security Features

- PDO prepared statements for SQL injection prevention
- UTF-8 encoding for proper character handling
- Error handling for database connections
- Exception handling for database operations

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👤 Author

**minggudevv**

- GitHub: [@minggudevv](https://github.com/minggudevv)
- Last Updated: 2025-02-16

## 🏗️ Project Structure

```
finance_manager/
├── src/
│   └── components/
│       └── config/
│           └── database.php    # Database configuration
├── ...
```

## 🙏 Acknowledgments

- PHP Community
- Contributors and users of this project
- Open source packages used in this project

---

<div align="center">

Made with ❤️ by [minggudevv](https://github.com/minggudevv)

</div>
