# 🚗 IVSS — Integrated Vehicle Support System

> An academic CICS project — Roadside Assistance Platform for Oman  
> Aligned with **Oman Vision 2040**

---

## 📖 About

IVSS connects drivers experiencing vehicle breakdowns with certified garages across Oman. Drivers can submit service requests, track their progress in real time, pay securely, and rate the experience — all in one platform.

**Stack:** PHP 8 · MySQL/MariaDB · HTML/CSS/JS · Python 3 · Leaflet.js  
**Hosting:** WAP Servers

---

## ✨ Features

### For Drivers
- Submit broadcast or direct service requests
- Live GPS-based garage finder (Leaflet.js, no API key required)
- Real-time request tracking with 30-second auto-refresh
- Secure payments — Credit Card or Cash on Site
- 5-star feedback system after completed services
- Full request history with status tracking

### For Garages
- Accept open broadcast requests or receive direct requests
- Set custom pricing per job
- Subscription plans (Weekly / Monthly / Yearly)
- Interactive location picker on Leaflet map
- Complaints management
- Dashboard with key metrics

### For Admins
- Full platform statistics and subscription revenue metrics
- Manage users, garages, requests, payments, and complaints
- Assign or cancel garage subscriptions
- Toggle user/garage active status

---

## 🗂️ Project Structure

```
ivss/
├── ivss2_db.sql                    ← Full DB schema + seed data
├── css/
│   └── style.css
├── includes/
│   ├── config.php                  ← DB connection, helpers, subscription functions
│   ├── header.php
│   └── footer.php
├── pages/
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   ├── garage_register.php
│   ├── dashboard.php
│   ├── my_requests.php
│   ├── garages.php
│   ├── profile.php
│   ├── request.php
│   ├── track.php
│   ├── payment.php
│   ├──invoice.php
│   ├── subscription_payment.php
│   ├── map.php
│   ├── feedback.php
│   ├── garage_dashboard.php
│   ├── garage_requests.php
│   ├── garage_payments.php
│   ├── garage_subscriptions.php
│   ├── garage_profile.php
│   ├── garage_complaints.php
│   ├── terms.php
│   └── garage_terms.php
├── admin/
│   ├── admin_header.php
│   ├── admin_footer.php
│   ├── admin_sidebar.php
│   ├── dashboard.php
│   ├── users.php
│   ├── garages_admin.php
│   ├── requests.php
│   ├── payments.php
│   ├── complaints.php
│   ├── admin_subscriptions.php
│   └── profile_admin.php
└── scripts/
    ├── check_subscriptions.py      ← Daily cron job for expiry checks
    └── subscription_manager.py     ← CLI admin tool
```

---

## 🗄️ Database

### Core Tables

| Table | Purpose |
|-------|---------|
| `users` | Registered drivers |
| `garages` | Registered garage partners |
| `admins` | Admin accounts |
| `service_requests` | All service requests (broadcast or direct) |
| `payments` | Payment records |
| `feedback` | Driver ratings and reviews |
| `complaints` | Driver complaints against garages |
| `login_attempts` | Brute-force protection log |

### Subscription Plans

| Plan | Price (OMR) | Duration | Requests/day |
|------|-------------|----------|--------------|
| Weekly | 15.000 | 7 days | 10 |
| Monthly | 45.000 | 30 days | 50 |
| Yearly | 400.000 | 365 days | Unlimited |

---

## 🔐 Security

- Login rate limiting: 3 attempts per 15 minutes (per email + role)
- Registration rate limiting: 3 registrations per IP per hour
- Oman phone number validation (8 digits, starts with 7 or 9)
- Card expiry validation — both client-side (JS) and server-side (PHP)
- Python scripts protected from web access via `.htaccess`

---

## 🗺️ Map Feature

- Powered by **Leaflet.js** — no API key needed
- CartoDB Dark tile layer (free)
- Gold-ranked pins with garage names
- "📍 Locate Me" button uses browser GPS
- Garages sorted by distance using the Haversine formula
- Garages must set their coordinates in their profile to appear on the map

---

## 🤖 Python Automation

### Daily Subscription Check (`check_subscriptions.py`)

Marks expired subscriptions, flags garages expiring within 3 days, and prints a summary report. Run via cron:

```bash
0 2 * * * cd /home/username/public_html/ivss && \
  DB_HOST=localhost DB_USER=root DB_PASS=pass DB_NAME=ivss2_db \
  python3 scripts/check_subscriptions.py
```

### CLI Subscription Manager (`subscription_manager.py`)

```bash
python3 scripts/subscription_manager.py add 5 2       # Add Monthly plan to garage 5
python3 scripts/subscription_manager.py list --active # List active subscriptions
python3 scripts/subscription_manager.py extend 5 30   # Extend by 30 days
python3 scripts/subscription_manager.py cancel 5      # Cancel subscription
```

---

## ⚙️ Setup Instructions

### 1. Import the Database

```bash
mysql -u root -p < ivss2_db.sql
```

If upgrading an existing database:

```sql
ALTER TABLE service_requests MODIFY garage_id INT NULL DEFAULT NULL;
ALTER TABLE garages ADD COLUMN latitude  DECIMAL(10,7) DEFAULT NULL;
ALTER TABLE garages ADD COLUMN longitude DECIMAL(10,7) DEFAULT NULL;
```

### 2. Configure the Application

Edit `includes/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_password');
define('DB_NAME', 'ivss2_db');
define('SITE_URL', 'https://yourdomain.com/ivss');
```

### 3. Upload Files

Upload all files to your server at:
```
/home/username/public_html/ivss/
```
Using WAP File Manager, FileZilla, or WinSCP.

### 4. Set Up Python Scripts

```bash
pip3 install mysql-connector-python --user

export DB_HOST=localhost DB_USER=root DB_PASS=pass DB_NAME=ivss2_db
python3 scripts/check_subscriptions.py
```

### 5. Protect the Scripts Folder

Create `/scripts/.htaccess`:

```apache
<FilesMatch "\.py$">
    Deny from all
</FilesMatch>
```

---

## 🧪 Demo Credentials

| Role | Email | Password |
|------|-------|----------|
| Driver | `driver1@ivss.om` | `password` |
| Garage | `alwadi@ivss.om` | `password` |
| Admin | `admin@ivss.om` | `password` |

Admin panel: `/admin/dashboard.php`

---

## 🎨 Design System

**Fonts:** Outfit + Tajawal (Google Fonts)

| Variable | Color |
|----------|-------|
| `--navy` | `#0a1628` |
| `--gold` | `#d4a843` |
| `--teal` | `#1a9e8a` |
| `--bg` | `#f8f7f3` |
| `--danger` | `#e24b4a` |

---

## 🔄 Service Request Lifecycle

```
Driver submits request
        ↓
Request created (broadcast, open to all garages)
        ↓
Garage accepts → assigned atomically (subscription required)
        ↓
Status: accepted → in_progress → completed
        ↓
Payment created automatically (custom or default 15 OMR)
        ↓
Driver pays → Driver rates the garage
```

---

## 🚧 Planned Features

- Invoice view and print
- Email & SMS notifications
- Real payment gateway integration (Stripe / local Omani gateway)
- Auto-renewal subscriptions
- Password reset / forgot-password flow
- Mobile-responsive hamburger navigation
- Garage-side feedback responses
- Subscription upgrade/downgrade with proration

---

## 📄 License

This is an academic project developed for educational purposes.

---

*IVSS — May 2026*
