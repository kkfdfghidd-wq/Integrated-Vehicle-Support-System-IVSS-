<?php
// ============================================
//  IVSS - Database Configuration
// ============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ivss2_db');
define('SITE_NAME', 'IVSS');
define('SITE_URL', 'http://localhost/ivss2/');

function getDB()
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:20px;color:red;">
                 <strong>Database Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>');
        }
    }
    return $pdo;
}

session_start();

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}
function isGarageLoggedIn()
{
    return isset($_SESSION['garage_id']);
}
function isAdminLoggedIn()
{
    return isset($_SESSION['admin_id']);
}

function redirect($url)
{
    header("Location: $url");
    exit;
}

function sanitize($input)
{
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function generateInvoiceNumber()
{
    return 'IVSS-' . strtoupper(substr(uniqid(), -6)) . '-' . date('Y');
}

// ================================================================
//  SUBSCRIPTION FUNCTIONS
//  All wrapped in try/catch — site never crashes even if
//  subscriptions.sql has not been imported yet.
// ================================================================

function getGarageSubscription($garageId)
{
    try {
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT gs.*, sp.plan_name, sp.duration_days, sp.price, sp.features
            FROM   garage_subscriptions gs
            JOIN   subscription_plans   sp ON gs.plan_id = sp.plan_id
            WHERE  gs.garage_id = ?
            ORDER  BY gs.created_at DESC
            LIMIT  1
        ");
        $stmt->execute([(int)$garageId]);
        return $stmt->fetch() ?: false;
    } catch (Exception $e) {
        return false;
    }
}

function isSubscriptionActive($garageId)
{
    try {
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT subscription_id FROM garage_subscriptions
            WHERE  garage_id = ? AND status = 'active' AND end_date >= CURDATE()
            LIMIT  1
        ");
        $stmt->execute([(int)$garageId]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

function getRemainingDays($garageId)
{
    try {
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT DATEDIFF(end_date, CURDATE()) AS days_left
            FROM   garage_subscriptions
            WHERE  garage_id = ? AND status = 'active' AND end_date >= CURDATE()
            ORDER  BY end_date DESC LIMIT 1
        ");
        $stmt->execute([(int)$garageId]);
        $row = $stmt->fetch();
        return $row ? max(0, (int)$row['days_left']) : 0;
    } catch (Exception $e) {
        return 0;
    }
}

function getSubscriptionStatus($garageId)
{
    try {
        $sub  = getGarageSubscription($garageId);
        $days = getRemainingDays($garageId);

        if (!$sub) {
            return [
                'status' => 'none',
                'message' => 'No active subscription',
                'color' => 'var(--danger)',
                'days' => 0,
                'plan' => null
            ];
        }
        if ($sub['status'] === 'cancelled') {
            return [
                'status' => 'cancelled',
                'message' => 'Subscription cancelled',
                'color' => 'var(--muted)',
                'days' => 0,
                'plan' => $sub['plan_name']
            ];
        }
        if ($sub['status'] === 'pending_payment') {
            return [
                'status' => 'pending_payment',
                'message' => 'Awaiting payment',
                'color' => 'var(--warning)',
                'days' => 0,
                'plan' => $sub['plan_name']
            ];
        }
        if ($days <= 0 || $sub['status'] === 'expired') {
            return [
                'status' => 'expired',
                'message' => 'Expired on ' . date('d M Y', strtotime($sub['end_date'])),
                'color' => 'var(--danger)',
                'days' => 0,
                'plan' => $sub['plan_name']
            ];
        }
        if ($days <= 3) {
            return [
                'status' => 'expiring',
                'message' => "Expires in {$days} day(s) — " . date('d M Y', strtotime($sub['end_date'])),
                'color' => 'var(--warning)',
                'days' => $days,
                'plan' => $sub['plan_name']
            ];
        }
        return [
            'status' => 'active',
            'message' => 'Active until ' . date('d M Y', strtotime($sub['end_date'])),
            'color' => 'var(--teal)',
            'days' => $days,
            'plan' => $sub['plan_name']
        ];
    } catch (Exception $e) {
        return [
            'status' => 'none',
            'message' => 'No active subscription',
            'color' => 'var(--danger)',
            'days' => 0,
            'plan' => null
        ];
    }
}

/**
 * Step 1 — Create a PENDING subscription (before payment).
 * Returns the new subscription_id, or false on failure.
 */
function createPendingSubscription($garageId, $planId)
{
    try {
        $db = getDB();

        $plan = $db->prepare("SELECT * FROM subscription_plans WHERE plan_id=? AND is_active=1");
        $plan->execute([(int)$planId]);
        $plan = $plan->fetch();
        if (!$plan) return false;

        $startDate = date('Y-m-d');
        $endDate   = date('Y-m-d', strtotime("+{$plan['duration_days']} days"));

        $db->beginTransaction();

        // Cancel any previous pending-payment record for this garage
        $db->prepare("
            DELETE FROM garage_subscriptions
            WHERE garage_id=? AND status='pending_payment'
        ")->execute([(int)$garageId]);

        // Archive any current active/expired subscription
        $old = $db->prepare("
            SELECT * FROM garage_subscriptions
            WHERE  garage_id=? AND status IN ('active','expired')
            ORDER  BY created_at DESC LIMIT 1
        ");
        $old->execute([(int)$garageId]);
        $oldSub = $old->fetch();
        if ($oldSub) {
            $db->prepare("
                INSERT INTO subscription_history
                    (garage_id,plan_id,start_date,end_date,status,amount_paid)
                VALUES (?,?,?,?,?,?)
            ")->execute([
                $garageId,
                $oldSub['plan_id'],
                $oldSub['start_date'],
                $oldSub['end_date'],
                $oldSub['status'],
                $oldSub['amount_paid'],
            ]);
            $db->prepare("UPDATE garage_subscriptions SET status='expired' WHERE subscription_id=?")
                ->execute([$oldSub['subscription_id']]);
        }

        // Insert new pending subscription
        $db->prepare("
            INSERT INTO garage_subscriptions
                (garage_id,plan_id,start_date,end_date,status,payment_status,amount_paid)
            VALUES (?,?,?,?,'pending_payment','pending',?)
        ")->execute([
            (int)$garageId,
            (int)$planId,
            $startDate,
            $endDate,
            $plan['price'],
        ]);

        $subId = (int)$db->lastInsertId();
        $db->commit();
        return $subId;
    } catch (Exception $e) {
        try {
            getDB()->rollBack();
        } catch (Exception $ex) {
        }
        return false;
    }
}

/**
 * Step 2 — Activate subscription after payment confirmed.
 */
function activateSubscription($subscriptionId, $paymentMethod)
{
    try {
        $db = getDB();

        $stmt = $db->prepare("
            SELECT * FROM garage_subscriptions
            WHERE  subscription_id=? AND status='pending_payment'
        ");
        $stmt->execute([(int)$subscriptionId]);
        $sub = $stmt->fetch();
        if (!$sub) return false;

        $db->prepare("
            UPDATE garage_subscriptions
            SET    status='active', payment_status='paid',
                   payment_method=?, paid_at=NOW()
            WHERE  subscription_id=?
        ")->execute([$paymentMethod, (int)$subscriptionId]);

        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get a single subscription row by ID (for payment page).
 */
function getSubscriptionById($subId)
{
    try {
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT gs.*, sp.plan_name, sp.duration_days, sp.price, sp.features
            FROM   garage_subscriptions gs
            JOIN   subscription_plans   sp ON gs.plan_id = sp.plan_id
            WHERE  gs.subscription_id = ?
        ");
        $stmt->execute([(int)$subId]);
        return $stmt->fetch() ?: false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Directly create (or renew) a subscription — kept for admin/CLI use.
 */
function createSubscription($garageId, $planId)
{
    try {
        $subId = createPendingSubscription($garageId, $planId);
        if (!$subId) return false;
        return activateSubscription($subId, 'manual');
    } catch (Exception $e) {
        return false;
    }
}

// Helper — shared sidebar subscription badge
function sidebarSubBadge($subStatus)
{
    $s = $subStatus['status'];
    if ($s === 'none' || $s === 'expired') {
        echo '<span style="background:var(--danger);color:#fff;border-radius:10px;
                           padding:1px 7px;font-size:11px;font-weight:700;margin-left:4px;">!</span>';
    } elseif ($s === 'expiring') {
        echo '<span style="background:#d4a843;color:#fff;border-radius:10px;
                           padding:1px 7px;font-size:11px;font-weight:700;margin-left:4px;">'
            . $subStatus['days'] . 'd</span>';
    } elseif ($s === 'pending_payment') {
        echo '<span style="background:var(--warning);color:#fff;border-radius:10px;
                           padding:1px 7px;font-size:11px;font-weight:700;margin-left:4px;">$</span>';
    }
}
