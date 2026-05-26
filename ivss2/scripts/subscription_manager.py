#!/usr/bin/env python3
"""
╔══════════════════════════════════════════════════════╗
║   IVSS — Subscription Manager CLI                   ║
║   Integrated Vehicle Support System                 ║
║   Admin Tool · May 2026                             ║
╚══════════════════════════════════════════════════════╝

Usage:
  python3 subscription_manager.py add <garage_id> <plan_id> [--method card|cash]
  python3 subscription_manager.py list [--active] [--expired] [--expiring]
  python3 subscription_manager.py extend <garage_id> <days>
  python3 subscription_manager.py cancel <garage_id>
  python3 subscription_manager.py info <garage_id>
  python3 subscription_manager.py plans
  python3 subscription_manager.py garages [--unsubscribed]
  python3 subscription_manager.py report

Plan IDs:
  1 = Weekly  (7 days  · 15.000 OMR)
  2 = Monthly (30 days · 45.000 OMR)
  3 = Yearly  (365 days· 400.000 OMR)

Environment Variables (required):
  DB_HOST, DB_USER, DB_PASS, DB_NAME
"""

import os
import sys
import argparse
from datetime import date, datetime, timedelta

# ─────────────────────────────────────────────
# Dependency check
# ─────────────────────────────────────────────
try:
    import mysql.connector
    from mysql.connector import Error as MySQLError
except ImportError:
    print("\n[ERROR] mysql-connector-python is not installed.")
    print("  Run: pip3 install mysql-connector-python --user\n")
    sys.exit(1)


# ─────────────────────────────────────────────
# ANSI colors
# ─────────────────────────────────────────────
class C:
    RESET  = "\033[0m"
    BOLD   = "\033[1m"
    RED    = "\033[91m"
    GREEN  = "\033[92m"
    YELLOW = "\033[93m"
    BLUE   = "\033[94m"
    CYAN   = "\033[96m"
    GRAY   = "\033[90m"
    GOLD   = "\033[33m"
    TEAL   = "\033[36m"
    WHITE  = "\033[97m"

def ok(msg):    print(f"  {C.GREEN}✔{C.RESET}  {msg}")
def err(msg):   print(f"  {C.RED}✘{C.RESET}  {msg}")
def warn(msg):  print(f"  {C.YELLOW}⚠{C.RESET}  {msg}")
def info(msg):  print(f"  {C.CYAN}ℹ{C.RESET}  {msg}")
def bold(msg):  return f"{C.BOLD}{msg}{C.RESET}"
def dim(msg):   return f"{C.GRAY}{msg}{C.RESET}"

def header(title):
    line = "─" * 54
    print(f"\n{C.GOLD}{line}{C.RESET}")
    print(f"{C.GOLD}  {title}{C.RESET}")
    print(f"{C.GOLD}{line}{C.RESET}")


# ─────────────────────────────────────────────
# Database connection
# ─────────────────────────────────────────────
def get_db():
    """Connect using environment variables."""
    host = os.getenv("DB_HOST", "localhost")
    user = os.getenv("DB_USER", "root")
    password = os.getenv("DB_PASS", "")
    database = os.getenv("DB_NAME", "ivss2_db")

    try:
        conn = mysql.connector.connect(
            host=host,
            user=user,
            password=password,
            database=database,
            autocommit=False,
            charset="utf8mb4"
        )
        return conn
    except MySQLError as e:
        err(f"Database connection failed: {e}")
        print(f"\n  {dim('Check your environment variables:')}")
        print(f"    DB_HOST={host}  DB_USER={user}  DB_NAME={database}")
        sys.exit(1)


# ─────────────────────────────────────────────
# Helpers
# ─────────────────────────────────────────────
STATUS_COLORS = {
    "active":          C.TEAL,
    "pending_payment": C.YELLOW,
    "expired":         C.RED,
    "cancelled":       C.GRAY,
}

def colorize_status(status):
    color = STATUS_COLORS.get(status, C.RESET)
    return f"{color}{status}{C.RESET}"

def colorize_days(days):
    if days <= 0:
        return f"{C.RED}expired{C.RESET}"
    elif days <= 3:
        return f"{C.YELLOW}{days}d left{C.RESET}"
    else:
        return f"{C.TEAL}{days}d left{C.RESET}"

def fmt_omr(amount):
    """Format as OMR with 3 decimal places."""
    try:
        return f"OMR {float(amount):.3f}"
    except (TypeError, ValueError):
        return "OMR 0.000"

def get_plan(conn, plan_id):
    cur = conn.cursor(dictionary=True)
    cur.execute("SELECT * FROM subscription_plans WHERE plan_id = %s AND is_active = 1", (plan_id,))
    plan = cur.fetchone()
    cur.close()
    return plan

def get_garage(conn, garage_id):
    cur = conn.cursor(dictionary=True)
    cur.execute("SELECT garage_id, garage_name, owner_name, email, phone FROM garages WHERE garage_id = %s", (garage_id,))
    garage = cur.fetchone()
    cur.close()
    return garage

def get_active_subscription(conn, garage_id):
    cur = conn.cursor(dictionary=True)
    cur.execute("""
        SELECT gs.*, sp.plan_name, sp.duration_days, sp.price
        FROM garage_subscriptions gs
        JOIN subscription_plans sp ON gs.plan_id = sp.plan_id
        WHERE gs.garage_id = %s
          AND gs.status = 'active'
          AND gs.end_date >= CURDATE()
        ORDER BY gs.end_date DESC
        LIMIT 1
    """, (garage_id,))
    sub = cur.fetchone()
    cur.close()
    return sub

def get_latest_subscription(conn, garage_id):
    cur = conn.cursor(dictionary=True)
    cur.execute("""
        SELECT gs.*, sp.plan_name, sp.duration_days, sp.price
        FROM garage_subscriptions gs
        JOIN subscription_plans sp ON gs.plan_id = sp.plan_id
        WHERE gs.garage_id = %s
        ORDER BY gs.subscription_id DESC
        LIMIT 1
    """, (garage_id,))
    sub = cur.fetchone()
    cur.close()
    return sub

def archive_subscription(conn, sub):
    """Copy subscription row to subscription_history."""
    cur = conn.cursor()
    cur.execute("""
        INSERT INTO subscription_history
            (garage_id, plan_id, start_date, end_date, status, amount_paid)
        VALUES (%s, %s, %s, %s, %s, %s)
    """, (
        sub["garage_id"], sub["plan_id"],
        sub["start_date"], sub["end_date"],
        sub["status"], sub["amount_paid"]
    ))
    cur.close()


# ─────────────────────────────────────────────
# Commands
# ─────────────────────────────────────────────

def cmd_add(args):
    """Add / assign a subscription to a garage."""
    garage_id  = args.garage_id
    plan_id    = args.plan_id
    method     = args.method or "card"

    header(f"ADD SUBSCRIPTION  —  Garage #{garage_id}")

    conn = get_db()
    try:
        garage = get_garage(conn, garage_id)
        if not garage:
            err(f"Garage #{garage_id} not found.")
            return

        plan = get_plan(conn, plan_id)
        if not plan:
            err(f"Plan #{plan_id} not found or is inactive.")
            info("Run 'plans' to see available plans.")
            return

        # Check for existing active subscription
        existing = get_active_subscription(conn, garage_id)
        if existing:
            warn(f"Garage already has an active {existing['plan_name']} subscription.")
            warn(f"Expires: {existing['end_date']}  ({colorize_days((existing['end_date'] - date.today()).days)})")
            print()
            answer = input(f"  {C.YELLOW}Override and create new subscription? (yes/no):{C.RESET} ").strip().lower()
            if answer not in ("yes", "y"):
                info("Aborted.")
                return
            # Cancel existing
            cur = conn.cursor()
            cur.execute(
                "UPDATE garage_subscriptions SET status='cancelled' WHERE subscription_id = %s",
                (existing["subscription_id"],)
            )
            archive_subscription(conn, existing)
            cur.close()
            conn.commit()
            warn(f"Previous subscription #{existing['subscription_id']} cancelled.")

        today      = date.today()
        end_date   = today + timedelta(days=plan["duration_days"])

        cur = conn.cursor()
        cur.execute("""
            INSERT INTO garage_subscriptions
                (garage_id, plan_id, start_date, end_date, status,
                 payment_status, payment_method, amount_paid, paid_at)
            VALUES (%s, %s, %s, %s, 'active', 'paid', %s, %s, NOW())
        """, (garage_id, plan_id, today, end_date, method, plan["price"]))
        sub_id = cur.lastrowid
        cur.close()
        conn.commit()

        print()
        ok(f"Subscription #{sub_id} created successfully!")
        print(f"\n  {'Garage:':<18} {bold(garage['garage_name'])}  (#{garage_id})")
        print(f"  {'Owner:':<18} {garage['owner_name']}")
        print(f"  {'Plan:':<18} {C.TEAL}{plan['plan_name']}{C.RESET}")
        print(f"  {'Duration:':<18} {plan['duration_days']} days")
        print(f"  {'Amount Paid:':<18} {C.GREEN}{fmt_omr(plan['price'])}{C.RESET}")
        print(f"  {'Payment Method:':<18} {method}")
        print(f"  {'Start Date:':<18} {today}")
        print(f"  {'End Date:':<18} {C.TEAL}{end_date}{C.RESET}")
        print(f"  {'Status:':<18} {colorize_status('active')}")
        print()

    finally:
        conn.close()


def cmd_list(args):
    """List subscriptions with optional filters."""
    conn = get_db()
    try:
        where_clauses = []
        params = []

        if args.active:
            where_clauses.append("gs.status = 'active' AND gs.end_date >= CURDATE()")
            label = "ACTIVE SUBSCRIPTIONS"
        elif args.expired:
            where_clauses.append("(gs.status = 'expired' OR (gs.status = 'active' AND gs.end_date < CURDATE()))")
            label = "EXPIRED SUBSCRIPTIONS"
        elif args.expiring:
            where_clauses.append(
                "gs.status = 'active' AND gs.end_date >= CURDATE() "
                "AND gs.end_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)"
            )
            label = "EXPIRING WITHIN 3 DAYS"
        else:
            label = "ALL SUBSCRIPTIONS"

        where_sql = ("WHERE " + " AND ".join(where_clauses)) if where_clauses else ""

        header(label)

        cur = conn.cursor(dictionary=True)
        cur.execute(f"""
            SELECT
                gs.subscription_id,
                gs.garage_id,
                g.garage_name,
                sp.plan_name,
                gs.start_date,
                gs.end_date,
                gs.status,
                gs.payment_status,
                gs.payment_method,
                gs.amount_paid,
                DATEDIFF(gs.end_date, CURDATE()) AS days_remaining
            FROM garage_subscriptions gs
            JOIN garages g ON gs.garage_id = g.garage_id
            JOIN subscription_plans sp ON gs.plan_id = sp.plan_id
            {where_sql}
            ORDER BY gs.end_date ASC
        """, params)
        rows = cur.fetchall()
        cur.close()

        if not rows:
            info("No subscriptions found matching your filter.")
            return

        # Table header
        print(f"\n  {C.BOLD}"
              f"{'#':<5} {'Garage':<22} {'Plan':<10} {'Start':<12} {'End':<12} "
              f"{'Status':<20} {'Remaining':<14} {'Amount'}"
              f"{C.RESET}")
        print(f"  {'─'*5} {'─'*22} {'─'*10} {'─'*12} {'─'*12} {'─'*20} {'─'*14} {'─'*12}")

        for r in rows:
            days = r["days_remaining"] if r["days_remaining"] is not None else 0
            name = r["garage_name"][:21] if r["garage_name"] else "—"
            status_col = colorize_status(r["status"])
            days_col   = colorize_days(days)
            print(
                f"  {r['subscription_id']:<5} {name:<22} {r['plan_name']:<10} "
                f"{str(r['start_date']):<12} {str(r['end_date']):<12} "
                f"{status_col:<30} {days_col:<24} {fmt_omr(r['amount_paid'])}"
            )

        print(f"\n  {dim(f'Total: {len(rows)} subscription(s)')}\n")

    finally:
        conn.close()


def cmd_extend(args):
    """Extend a garage's active subscription by N days."""
    garage_id = args.garage_id
    days      = args.days

    if days <= 0:
        err("Days must be a positive integer.")
        return

    header(f"EXTEND SUBSCRIPTION  —  Garage #{garage_id}")

    conn = get_db()
    try:
        garage = get_garage(conn, garage_id)
        if not garage:
            err(f"Garage #{garage_id} not found.")
            return

        sub = get_latest_subscription(conn, garage_id)
        if not sub:
            err(f"No subscription found for garage #{garage_id}.")
            info("Use 'add' to create one.")
            return

        old_end  = sub["end_date"]
        # If subscription is expired, extend from today instead
        base     = max(old_end, date.today())
        new_end  = base + timedelta(days=days)

        cur = conn.cursor()
        cur.execute("""
            UPDATE garage_subscriptions
            SET end_date = %s,
                status   = 'active'
            WHERE subscription_id = %s
        """, (new_end, sub["subscription_id"]))
        conn.commit()
        cur.close()

        print()
        ok(f"Subscription #{sub['subscription_id']} extended by {days} day(s)!")
        print(f"\n  {'Garage:':<18} {bold(garage['garage_name'])}")
        print(f"  {'Plan:':<18} {sub['plan_name']}")
        print(f"  {'Previous End:':<18} {C.RED}{old_end}{C.RESET}")
        print(f"  {'New End Date:':<18} {C.TEAL}{new_end}{C.RESET}")
        print(f"  {'Status:':<18} {colorize_status('active')}")
        print()

    finally:
        conn.close()


def cmd_cancel(args):
    """Cancel a garage's active subscription."""
    garage_id = args.garage_id

    header(f"CANCEL SUBSCRIPTION  —  Garage #{garage_id}")

    conn = get_db()
    try:
        garage = get_garage(conn, garage_id)
        if not garage:
            err(f"Garage #{garage_id} not found.")
            return

        sub = get_active_subscription(conn, garage_id)
        if not sub:
            sub = get_latest_subscription(conn, garage_id)
            if not sub:
                err(f"No subscription found for garage #{garage_id}.")
                return
            if sub["status"] in ("cancelled", "expired"):
                warn(f"Subscription #{sub['subscription_id']} is already {sub['status']}.")
                return

        print(f"\n  {'Garage:':<18} {bold(garage['garage_name'])}")
        print(f"  {'Plan:':<18} {sub['plan_name']}")
        print(f"  {'End Date:':<18} {sub['end_date']}")
        print(f"  {'Status:':<18} {colorize_status(sub['status'])}")
        print()
        answer = input(f"  {C.RED}Confirm cancellation? (yes/no):{C.RESET} ").strip().lower()
        if answer not in ("yes", "y"):
            info("Aborted — no changes made.")
            return

        cur = conn.cursor()
        cur.execute(
            "UPDATE garage_subscriptions SET status = 'cancelled' WHERE subscription_id = %s",
            (sub["subscription_id"],)
        )
        archive_subscription(conn, {**sub, "status": "cancelled"})
        conn.commit()
        cur.close()

        print()
        ok(f"Subscription #{sub['subscription_id']} has been cancelled.")
        warn("Garage will lose access to accepting requests immediately.")
        print()

    finally:
        conn.close()


def cmd_info(args):
    """Show full subscription info for a specific garage."""
    garage_id = args.garage_id

    header(f"GARAGE INFO  —  #{garage_id}")

    conn = get_db()
    try:
        garage = get_garage(conn, garage_id)
        if not garage:
            err(f"Garage #{garage_id} not found.")
            return

        print(f"\n  {bold('Garage Details')}")
        print(f"  {'Name:':<18} {garage['garage_name']}")
        print(f"  {'Owner:':<18} {garage['owner_name']}")
        print(f"  {'Email:':<18} {garage['email']}")
        print(f"  {'Phone:':<18} {garage['phone']}")

        # Active subscription
        sub = get_active_subscription(conn, garage_id)
        print(f"\n  {bold('Current Subscription')}")
        if sub:
            days = (sub["end_date"] - date.today()).days
            print(f"  {'Plan:':<18} {C.TEAL}{sub['plan_name']}{C.RESET}")
            print(f"  {'Status:':<18} {colorize_status(sub['status'])}")
            print(f"  {'Start Date:':<18} {sub['start_date']}")
            print(f"  {'End Date:':<18} {sub['end_date']}")
            print(f"  {'Remaining:':<18} {colorize_days(days)}")
            print(f"  {'Amount Paid:':<18} {fmt_omr(sub['amount_paid'])}")
            print(f"  {'Payment Method:':<18} {sub['payment_method'] or '—'}")
        else:
            warn("No active subscription.")

        # Subscription history
        cur = conn.cursor(dictionary=True)
        cur.execute("""
            SELECT gs.subscription_id, sp.plan_name, gs.start_date, gs.end_date,
                   gs.status, gs.amount_paid, gs.payment_method
            FROM garage_subscriptions gs
            JOIN subscription_plans sp ON gs.plan_id = sp.plan_id
            WHERE gs.garage_id = %s
            ORDER BY gs.subscription_id DESC
            LIMIT 5
        """, (garage_id,))
        history = cur.fetchall()
        cur.close()

        print(f"\n  {bold('Subscription History')} {dim('(last 5)')}")
        if history:
            print(f"\n  {'#':<6} {'Plan':<10} {'Start':<12} {'End':<12} {'Status':<20} {'Amount'}")
            print(f"  {'─'*6} {'─'*10} {'─'*12} {'─'*12} {'─'*20} {'─'*10}")
            for h in history:
                print(
                    f"  {h['subscription_id']:<6} {h['plan_name']:<10} "
                    f"{str(h['start_date']):<12} {str(h['end_date']):<12} "
                    f"{colorize_status(h['status']):<30} {fmt_omr(h['amount_paid'])}"
                )
        else:
            info("No history found.")

        print()

    finally:
        conn.close()


def cmd_plans(args):
    """List all subscription plans."""
    header("SUBSCRIPTION PLANS")

    conn = get_db()
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("""
            SELECT plan_id, plan_name, duration_days, price, features, is_active
            FROM subscription_plans
            ORDER BY plan_id
        """)
        plans = cur.fetchall()
        cur.close()

        if not plans:
            info("No plans found.")
            return

        print()
        for p in plans:
            status = f"{C.TEAL}Active{C.RESET}" if p["is_active"] else f"{C.RED}Inactive{C.RESET}"
            print(f"  {C.GOLD}[Plan #{p['plan_id']}] {bold(p['plan_name'])}{C.RESET}  —  {status}")
            print(f"    Duration : {p['duration_days']} days")
            print(f"    Price    : {C.GREEN}{fmt_omr(p['price'])}{C.RESET}")
            if p["features"]:
                print(f"    Features : {dim(p['features'])}")
            print()

    finally:
        conn.close()


def cmd_garages(args):
    """List garages with subscription status, optionally filter unsubscribed."""
    header("GARAGES — SUBSCRIPTION STATUS")

    conn = get_db()
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("""
            SELECT
                g.garage_id,
                g.garage_name,
                g.owner_name,
                g.is_active,
                gs.subscription_id,
                gs.status         AS sub_status,
                sp.plan_name,
                gs.end_date,
                DATEDIFF(gs.end_date, CURDATE()) AS days_remaining
            FROM garages g
            LEFT JOIN (
                SELECT gs2.*
                FROM garage_subscriptions gs2
                INNER JOIN (
                    SELECT garage_id, MAX(subscription_id) AS max_id
                    FROM garage_subscriptions
                    GROUP BY garage_id
                ) latest ON gs2.garage_id = latest.garage_id AND gs2.subscription_id = latest.max_id
            ) gs ON g.garage_id = gs.garage_id
            LEFT JOIN subscription_plans sp ON gs.plan_id = sp.plan_id
            ORDER BY g.garage_id
        """)
        rows = cur.fetchall()
        cur.close()

        if args.unsubscribed:
            rows = [
                r for r in rows
                if not r["sub_status"]
                or r["sub_status"] in ("expired", "cancelled", "pending_payment")
                or (r["sub_status"] == "active" and r["days_remaining"] is not None and r["days_remaining"] < 0)
            ]
            label_suffix = " (UNSUBSCRIBED ONLY)"
        else:
            label_suffix = ""

        if not rows:
            info("No garages found.")
            return

        print(f"\n  {C.BOLD}"
              f"{'ID':<6} {'Garage Name':<24} {'Owner':<20} {'Plan':<10} {'Sub Status':<22} {'Expires'}"
              f"{C.RESET}")
        print(f"  {'─'*6} {'─'*24} {'─'*20} {'─'*10} {'─'*22} {'─'*12}")

        subscribed = 0
        unsubscribed = 0

        for r in rows:
            is_active_sub = (
                r["sub_status"] == "active"
                and r["days_remaining"] is not None
                and r["days_remaining"] >= 0
            )
            if is_active_sub:
                sub_col  = colorize_status("active")
                exp_col  = colorize_days(r["days_remaining"])
                plan_col = r["plan_name"] or "—"
                subscribed += 1
            else:
                sub_col  = colorize_status(r["sub_status"] or "none")
                exp_col  = dim(str(r["end_date"]) if r["end_date"] else "—")
                plan_col = r["plan_name"] or dim("—")
                unsubscribed += 1

            name  = (r["garage_name"] or "")[:23]
            owner = (r["owner_name"] or "")[:19]
            print(
                f"  {r['garage_id']:<6} {name:<24} {owner:<20} {plan_col:<10} "
                f"{sub_col:<32} {exp_col}"
            )

        print(f"\n  {C.GREEN}✔ Subscribed: {subscribed}{C.RESET}   "
              f"{C.RED}✘ Unsubscribed/Expired: {unsubscribed}{C.RESET}\n")

    finally:
        conn.close()


def cmd_report(args):
    """Print a full subscription summary report."""
    header("SUBSCRIPTION REPORT")

    conn = get_db()
    try:
        cur = conn.cursor(dictionary=True)

        # Per-plan stats
        cur.execute("""
            SELECT
                sp.plan_name,
                COUNT(gs.subscription_id)                                        AS total,
                SUM(gs.status = 'active' AND gs.end_date >= CURDATE())           AS active,
                SUM(gs.status = 'expired' OR gs.end_date < CURDATE())            AS expired,
                SUM(gs.status = 'pending_payment')                               AS pending,
                SUM(gs.status = 'cancelled')                                     AS cancelled,
                SUM(CASE WHEN gs.status='active' AND gs.end_date >= CURDATE()
                         THEN gs.amount_paid ELSE 0 END)                         AS revenue_active,
                SUM(gs.amount_paid)                                              AS revenue_total
            FROM subscription_plans sp
            LEFT JOIN garage_subscriptions gs ON sp.plan_id = gs.plan_id
            GROUP BY sp.plan_id, sp.plan_name
            ORDER BY sp.plan_id
        """)
        plan_rows = cur.fetchall()

        # Summary stats
        cur.execute("""
            SELECT
                COUNT(DISTINCT CASE WHEN gs.status='active' AND gs.end_date >= CURDATE()
                                    THEN gs.garage_id END)           AS subscribed_garages,
                (SELECT COUNT(*) FROM garages)                       AS total_garages,
                SUM(CASE WHEN gs.status='active'
                         AND gs.end_date BETWEEN CURDATE()
                         AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
                         THEN 1 ELSE 0 END)                          AS expiring_soon,
                SUM(CASE WHEN gs.end_date = CURDATE()
                         AND gs.status='active'
                         THEN 1 ELSE 0 END)                          AS expiring_today,
                SUM(gs.amount_paid)                                  AS total_revenue
            FROM garage_subscriptions gs
        """)
        summary = cur.fetchone()
        cur.close()

        # Print plan table
        print(f"\n  {C.BOLD}"
              f"{'Plan':<12} {'Total':>7} {'Active':>8} {'Expired':>9} "
              f"{'Pending':>9} {'Cancelled':>11} {'Revenue (OMR)'}"
              f"{C.RESET}")
        print(f"  {'─'*12} {'─'*7} {'─'*8} {'─'*9} {'─'*9} {'─'*11} {'─'*14}")

        for r in plan_rows:
            print(
                f"  {r['plan_name']:<12} "
                f"{(r['total'] or 0):>7} "
                f"{C.TEAL}{(r['active'] or 0):>8}{C.RESET} "
                f"{C.RED}{(r['expired'] or 0):>9}{C.RESET} "
                f"{C.YELLOW}{(r['pending'] or 0):>9}{C.RESET} "
                f"{C.GRAY}{(r['cancelled'] or 0):>11}{C.RESET} "
                f"{fmt_omr(r['revenue_active'] or 0)}"
            )

        # Summary
        total_g      = summary["total_garages"] or 0
        subscribed_g = summary["subscribed_garages"] or 0
        unsub_g      = total_g - subscribed_g
        expiring     = summary["expiring_soon"] or 0
        exp_today    = summary["expiring_today"] or 0
        revenue      = summary["total_revenue"] or 0

        print(f"\n  {'─'*54}")
        print(f"\n  {bold('SUMMARY')}")
        print(f"  {'Total Garages:':<28} {total_g}")
        print(f"  {'Subscribed Garages:':<28} {C.TEAL}{subscribed_g}{C.RESET}")
        print(f"  {'Unsubscribed / Expired:':<28} {C.RED}{unsub_g}{C.RESET}")
        print(f"  {'Expiring Within 3 Days:':<28} {C.YELLOW}{expiring}{C.RESET}")
        print(f"  {'Expiring Today:':<28} {C.YELLOW}{exp_today}{C.RESET}")
        print(f"  {'Total Revenue Collected:':<28} {C.GREEN}{fmt_omr(revenue)}{C.RESET}")
        print(f"\n  Generated: {dim(datetime.now().strftime('%Y-%m-%d %H:%M:%S'))}\n")

    finally:
        conn.close()


# ─────────────────────────────────────────────
# Argument parser
# ─────────────────────────────────────────────
def build_parser():
    parser = argparse.ArgumentParser(
        prog="subscription_manager.py",
        description="IVSS — Subscription Manager CLI",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=__doc__
    )
    sub = parser.add_subparsers(dest="command", title="Commands")

    # add
    p_add = sub.add_parser("add", help="Assign a subscription plan to a garage")
    p_add.add_argument("garage_id", type=int, help="Garage ID")
    p_add.add_argument("plan_id",   type=int, help="Plan ID (1=Weekly, 2=Monthly, 3=Yearly)")
    p_add.add_argument("--method",  choices=["card", "cash", "admin"], default="card",
                       help="Payment method (default: card)")

    # list
    p_list = sub.add_parser("list", help="List subscriptions")
    p_list.add_argument("--active",   action="store_true", help="Show only active subscriptions")
    p_list.add_argument("--expired",  action="store_true", help="Show only expired subscriptions")
    p_list.add_argument("--expiring", action="store_true", help="Show subscriptions expiring within 3 days")

    # extend
    p_ext = sub.add_parser("extend", help="Extend a subscription by N days")
    p_ext.add_argument("garage_id", type=int, help="Garage ID")
    p_ext.add_argument("days",      type=int, help="Number of days to extend")

    # cancel
    p_can = sub.add_parser("cancel", help="Cancel a garage subscription")
    p_can.add_argument("garage_id", type=int, help="Garage ID")

    # info
    p_info = sub.add_parser("info", help="Show detailed subscription info for a garage")
    p_info.add_argument("garage_id", type=int, help="Garage ID")

    # plans
    sub.add_parser("plans", help="List all subscription plans")

    # garages
    p_gar = sub.add_parser("garages", help="List all garages with subscription status")
    p_gar.add_argument("--unsubscribed", action="store_true",
                       help="Show only unsubscribed / expired garages")

    # report
    sub.add_parser("report", help="Print full subscription summary report")

    return parser


# ─────────────────────────────────────────────
# Entry point
# ─────────────────────────────────────────────
def main():
    print(f"\n{C.GOLD}{'═'*56}{C.RESET}")
    print(f"{C.GOLD}  IVSS Subscription Manager{C.RESET}  {dim('v1.0 · May 2026')}")
    print(f"{C.GOLD}{'═'*56}{C.RESET}")

    parser = build_parser()
    args   = parser.parse_args()

    if not args.command:
        parser.print_help()
        print()
        sys.exit(0)

    dispatch = {
        "add":     cmd_add,
        "list":    cmd_list,
        "extend":  cmd_extend,
        "cancel":  cmd_cancel,
        "info":    cmd_info,
        "plans":   cmd_plans,
        "garages": cmd_garages,
        "report":  cmd_report,
    }

    try:
        dispatch[args.command](args)
    except KeyboardInterrupt:
        print(f"\n\n  {C.YELLOW}Interrupted by user.{C.RESET}\n")
        sys.exit(0)
    except Exception as e:
        err(f"Unexpected error: {e}")
        sys.exit(1)


if __name__ == "__main__":
    main()
