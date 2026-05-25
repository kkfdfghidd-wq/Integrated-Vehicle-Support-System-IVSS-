"""
IVSS - Subscription Checker
Run daily via Windows Task Scheduler or cron
"""

import os
import sys
from datetime import datetime, date

try:
    import mysql.connector
except ImportError:
    print("ERROR: mysql-connector-python not installed.")
    print("Run: pip install mysql-connector-python")
    sys.exit(1)


# ── Config (edit these or use environment variables) ──────────────
DB_HOST = os.environ.get("DB_HOST", "localhost")
DB_USER = os.environ.get("DB_USER", "root")
DB_PASS = os.environ.get("DB_PASS", "")
DB_NAME = os.environ.get("DB_NAME", "ivss2_db")
SITE_URL = os.environ.get("SITE_URL", "http://localhost/ivss2")

# Days before expiry to send alert
ALERT_DAYS_BEFORE = 3
# Log file path (Windows-friendly)
LOG_FILE = os.path.join(os.environ.get("TEMP", "/tmp"), "ivss_subscription_notifications.log")


def connect():
    return mysql.connector.connect(
        host=DB_HOST,
        user=DB_USER,
        password=DB_PASS,
        database=DB_NAME,
        charset="utf8mb4",
    )


def mark_expired(cursor):
    """Mark subscriptions whose end_date has passed as expired."""
    sql = """
        UPDATE garage_subscriptions
        SET    status = 'expired'
        WHERE  status = 'active'
          AND  end_date < CURDATE()
    """
    cursor.execute(sql)
    return cursor.rowcount


def get_expiring_soon(cursor):
    """Return subscriptions expiring within ALERT_DAYS_BEFORE days."""
    sql = """
        SELECT gs.subscription_id,
               gs.garage_id,
               g.garage_name,
               g.email,
               sp.plan_name,
               gs.end_date,
               DATEDIFF(gs.end_date, CURDATE()) AS days_left
        FROM   garage_subscriptions gs
        JOIN   garages               g  ON gs.garage_id = g.garage_id
        JOIN   subscription_plans    sp ON gs.plan_id   = sp.plan_id
        WHERE  gs.status = 'active'
          AND  DATEDIFF(gs.end_date, CURDATE()) BETWEEN 1 AND %s
        ORDER  BY days_left ASC
    """
    cursor.execute(sql, (ALERT_DAYS_BEFORE,))
    return cursor.fetchall()


def get_active_count(cursor):
    cursor.execute(
        "SELECT COUNT(*) FROM garage_subscriptions WHERE status='active' AND end_date >= CURDATE()"
    )
    return cursor.fetchone()[0]


def get_plan_stats(cursor):
    sql = """
        SELECT sp.plan_name,
               COUNT(gs.subscription_id)                                         AS total,
               SUM(gs.status = 'active'   AND gs.end_date >= CURDATE())          AS active,
               SUM(gs.status = 'expired'  OR  gs.end_date <  CURDATE())          AS expired
        FROM   subscription_plans    sp
        LEFT   JOIN garage_subscriptions gs ON gs.plan_id = sp.plan_id
        WHERE  sp.is_active = 1
        GROUP  BY sp.plan_id, sp.plan_name
        ORDER  BY sp.duration_days ASC
    """
    cursor.execute(sql)
    return cursor.fetchall()


def log_notification(garage_name, email, plan_name, days_left, end_date):
    """Append notification entry to log file."""
    try:
        with open(LOG_FILE, "a", encoding="utf-8") as f:
            f.write(
                f"[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] "
                f"ALERT | {garage_name} ({email}) | Plan: {plan_name} | "
                f"Expires: {end_date} | Days left: {days_left}\n"
            )
    except OSError:
        pass  # Non-fatal


def print_report(plan_stats, expired_count, expiring_list, active_count):
    sep = "=" * 60
    now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    print(f"\n{sep}")
    print("SUBSCRIPTION REPORT")
    print(sep)
    print(f"Generated: {now}\n")

    # Header
    print(f"{'Plan':<15} {'Total':>7} {'Active':>8} {'Expired':>9}")
    print("-" * 42)
    for row in plan_stats:
        plan, total, active, expired = row
        print(f"{plan:<15} {int(total or 0):>7} {int(active or 0):>8} {int(expired or 0):>9}")

    print("-" * 42)
    print(f"\nSUMMARY:")
    print(f"  • Active Subscriptions : {active_count}")
    print(f"  • Expired Today        : {expired_count}")
    print(f"  • Expiring in ≤{ALERT_DAYS_BEFORE} Days : {len(expiring_list)}")
    print(f"  • Notifications Logged : {len(expiring_list)}")
    print(sep)


def main():
    print("=" * 60)
    print(" IVSS Subscription Checker")
    print(f" {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("=" * 60)

    try:
        conn   = connect()
        cursor = conn.cursor()
        print("✓ Connected to database")

        # 1. Mark expired
        expired_count = mark_expired(cursor)
        conn.commit()
        if expired_count:
            print(f"✓ Marked {expired_count} subscription(s) as expired")
        else:
            print("✓ No newly expired subscriptions")

        # 2. Expiring soon
        expiring = get_expiring_soon(cursor)
        if expiring:
            print(f"✓ Found {len(expiring)} subscription(s) expiring soon:")
            for row in expiring:
                sub_id, garage_id, garage_name, email, plan_name, end_date, days_left = row
                print(f"  → {garage_name}: {days_left} day(s) remaining ({end_date})")
                log_notification(garage_name, email, plan_name, days_left, end_date)
        else:
            print("✓ No subscriptions expiring in the next 3 days")

        # 3. Active count
        active_count = get_active_count(cursor)
        print(f"✓ Active subscriptions: {active_count}")

        # 4. Report
        plan_stats = get_plan_stats(cursor)
        print_report(plan_stats, expired_count, expiring, active_count)

        cursor.close()
        conn.close()
        print("\n✓ Subscription check completed successfully!")
        return 0

    except mysql.connector.Error as e:
        print(f"\nERROR: Database error — {e}")
        print("Check DB_HOST / DB_USER / DB_PASS / DB_NAME settings.")
        return 1
    except Exception as e:
        print(f"\nERROR: Unexpected error — {e}")
        return 1


if __name__ == "__main__":
    sys.exit(main())
