# 🐍 IVSS — Python Scripts

> **Integrated Vehicle Support System** · Subscription Automation & Admin Tools

This folder contains two Python scripts that support the IVSS subscription system.  
One runs automatically every night, and one is used manually by the admin.

```
scripts/
├── check_subscriptions.py   ← Daily cron job — expiry checker & alerts
└── subscription_manager.py  ← CLI admin tool — manage garage subscriptions
```

---

## 📋 Table of Contents

- [Requirements](#-requirements)
- [Environment Setup](#-environment-setup)
- [check\_subscriptions.py](#-check_subscriptionspy--daily-cron-job)
- [subscription\_manager.py](#-subscription_managerpy--cli-admin-tool)
- [Cron Job Setup](#-cron-job-setup-wap-servers)
- [Protect the Scripts](#-protect-the-scripts-folder)

---

## ✅ Requirements

| Requirement | Version |
|---|---|
| Python | 3.8 or higher |
| mysql-connector-python | latest |

**Install the dependency:**

```bash
pip3 install mysql-connector-python --user
```

---

## 🔧 Environment Setup

Both scripts read database credentials from **environment variables**.  
Never hardcode credentials in the files.

```bash
export DB_HOST=localhost
export DB_USER=your_db_user
export DB_PASS=your_password
export DB_NAME=ivss2_db
```

> **Tip:** On WAP Servers, set these in the cron command directly (see [Cron Job Setup](#-cron-job-setup-wap-servers)).

---

## 📅 `check_subscriptions.py` — Daily Cron Job

### What it does

Runs automatically every night and performs three tasks:

1. **Expires subscriptions** — marks any subscription where `end_date < TODAY` as `expired`
2. **Alerts on upcoming expiries** — finds subscriptions expiring within 3 days and logs a notification
3. **Prints a summary report** — overview of all plans, active counts, and expired counts

### How to run manually

```bash
export DB_HOST=localhost DB_USER=root DB_PASS=pass DB_NAME=ivss2_db

python3 check_subscriptions.py
```

### Sample output

```
============================
 IVSS Subscription Checker
 2026-05-26 02:00:01
============================

[INFO] Checking for expired subscriptions...
[EXPIRED] Garage #3 (Al Wadi Garage) — Weekly plan expired on 2026-05-25
[EXPIRED] Garage #7 (Fast Fix) — Monthly plan expired on 2026-05-24

[INFO] Checking for upcoming expiries...
[ALERT] Garage #2 (Muscat Motors) — Monthly plan expires in 2 days (2026-05-28)
[ALERT] Garage #9 (Nizwa Auto) — Weekly plan expires in 3 days (2026-05-29)

============================================================
  SUBSCRIPTION REPORT
============================================================
Plan        | Total | Active | Expired
------------+-------+--------+--------
Weekly      |   5   |   3    |   2
Monthly     |   8   |   6    |   2
Yearly      |   2   |   2    |   0

SUMMARY:
  • Active Subscriptions:      11
  • Expired Today:              2
  • Expiring Within 3 Days:     2
  • Notifications Logged:       2
============================================================
```

---

## 🛠️ `subscription_manager.py` — CLI Admin Tool

A command-line tool for managing garage subscriptions directly from the terminal.  
Useful for admin operations without going through the web panel.

### Plan IDs

| ID | Plan | Duration | Price |
|---|---|---|---|
| `1` | Weekly | 7 days | OMR 15.000 |
| `2` | Monthly | 30 days | OMR 45.000 |
| `3` | Yearly | 365 days | OMR 400.000 |

---

### Commands

#### `add` — Assign a subscription to a garage

```bash
python3 subscription_manager.py add <garage_id> <plan_id> [--method card|cash|admin]
```

```bash
# Assign Monthly plan to garage #5 (card payment)
python3 subscription_manager.py add 5 2

# Assign Yearly plan to garage #3 via cash
python3 subscription_manager.py add 3 3 --method cash

# Assign directly by admin (no payment)
python3 subscription_manager.py add 8 1 --method admin
```

> ⚠️ If the garage already has an active subscription, the script will ask for confirmation before replacing it.

---

#### `list` — List subscriptions

```bash
python3 subscription_manager.py list [--active] [--expired] [--expiring]
```

```bash
# Show all subscriptions
python3 subscription_manager.py list

# Show only active subscriptions
python3 subscription_manager.py list --active

# Show only expired subscriptions
python3 subscription_manager.py list --expired

# Show subscriptions expiring within 3 days
python3 subscription_manager.py list --expiring
```

**Sample output:**

```
──────────────────────────────────────────────────────
  ACTIVE SUBSCRIPTIONS
──────────────────────────────────────────────────────

  #     Garage                 Plan       Start        End          Status               Remaining      Amount
  ----- ---------------------- ---------- ------------ ------------ -------------------- -------------- ------------
  12    Muscat Motors          Monthly    2026-04-28   2026-05-28   active               2d left        OMR 45.000
  15    Al Wadi Garage         Yearly     2026-01-01   2026-12-31   active               219d left      OMR 400.000

  Total: 2 subscription(s)
```

---

#### `extend` — Extend a subscription

```bash
python3 subscription_manager.py extend <garage_id> <days>
```

```bash
# Extend garage #5 subscription by 30 days
python3 subscription_manager.py extend 5 30

# Extend garage #2 subscription by 7 days
python3 subscription_manager.py extend 2 7
```

> If the subscription is already expired, the extension starts from **today** instead of the old end date.

---

#### `cancel` — Cancel a subscription

```bash
python3 subscription_manager.py cancel <garage_id>
```

```bash
python3 subscription_manager.py cancel 5
```

> Asks for `yes/no` confirmation before cancelling.  
> Cancelled subscriptions are **archived** to `subscription_history`.

---

#### `info` — Show garage subscription details

```bash
python3 subscription_manager.py info <garage_id>
```

```bash
python3 subscription_manager.py info 5
```

**Sample output:**

```
──────────────────────────────────────────────────────
  GARAGE INFO  —  #5
──────────────────────────────────────────────────────

  Garage Details
  Name:              Al Wadi Garage
  Owner:             Mohammed Al Balushi
  Email:             alwadi@ivss.om
  Phone:             91234567

  Current Subscription
  Plan:              Monthly
  Status:            active
  Start Date:        2026-05-01
  End Date:          2026-05-31
  Remaining:         5d left
  Amount Paid:       OMR 45.000
  Payment Method:    card

  Subscription History (last 5)
  #      Plan       Start        End          Status               Amount
  ------ ---------- ------------ ------------ -------------------- ----------
  8      Weekly     2026-04-01   2026-04-08   expired              OMR 15.000
  12     Monthly    2026-05-01   2026-05-31   active               OMR 45.000
```

---

#### `plans` — List available plans

```bash
python3 subscription_manager.py plans
```

---

#### `garages` — List garages with subscription status

```bash
python3 subscription_manager.py garages [--unsubscribed]
```

```bash
# Show all garages and their subscription status
python3 subscription_manager.py garages

# Show only garages without an active subscription
python3 subscription_manager.py garages --unsubscribed
```

---

#### `report` — Full summary report

```bash
python3 subscription_manager.py report
```

Prints a table of per-plan stats and a summary of total garages, revenue, and upcoming expiries.

---

### All commands at a glance

| Command | Description |
|---|---|
| `add <garage_id> <plan_id>` | Assign a plan to a garage |
| `list` | List all subscriptions |
| `list --active` | Active subscriptions only |
| `list --expired` | Expired subscriptions only |
| `list --expiring` | Expiring within 3 days |
| `extend <garage_id> <days>` | Extend by N days |
| `cancel <garage_id>` | Cancel a subscription |
| `info <garage_id>` | Full details for one garage |
| `plans` | Show all available plans |
| `garages` | All garages + sub status |
| `garages --unsubscribed` | Unsubscribed garages only |
| `report` | Full stats report |

---

## ⏰ Cron Job Setup (WAP Servers)

Schedule `check_subscriptions.py` to run every night at 2:00 AM.

**WAP Panel → Cron Jobs → Add New:**

```
Schedule : 0 2 * * *
Command  : DB_HOST=localhost DB_USER=root DB_PASS=pass DB_NAME=ivss2_db \
           /usr/bin/python3 /home/username/public_html/ivss/scripts/check_subscriptions.py
```

**Or using the full path format:**

```bash
0 2 * * * cd /home/username/public_html/ivss && \
  DB_HOST=localhost DB_USER=root DB_PASS=pass DB_NAME=ivss2_db \
  python3 scripts/check_subscriptions.py >> /home/username/logs/ivss_cron.log 2>&1
```

> The `>> logs/ivss_cron.log` part saves the output to a log file for review.

---

## 🔒 Protect the Scripts Folder

Prevent `.py` files from being accessed through the browser.  
Create a file at `scripts/.htaccess` with this content:

```apache
<FilesMatch "\.py$">
    Deny from all
</FilesMatch>
```

---

## Database Tables Used

| Table | Used by |
|---|---|
| `garages` | Both scripts |
| `subscription_plans` | Both scripts |
| `garage_subscriptions` | Both scripts |
| `subscription_history` | `subscription_manager.py` (archiving) |

---

## Subscription Status Reference

| Status | Meaning |
|---|---|
| `active` | Paid and valid |
| `pending_payment` | Created but payment not completed |
| `expired` | `end_date` passed |
| `cancelled` | Manually cancelled |

---

*IVSS · Academic CICS Project · Oman Vision 2040 · May 2026*
