<?php
require_once __DIR__ . '/../includes/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/pages/login.php');

$db        = getDB();
$userId    = $_SESSION['user_id'];
$requestId = intval($_GET['request_id'] ?? 0);

if (!$requestId) redirect(SITE_URL . '/pages/my_requests.php');

// Fetch the request — must be completed and belong to this user
$req = $db->prepare("
    SELECT r.*, g.garage_name, g.garage_id, g.location AS garage_location,
           g.rating AS garage_current_rating
    FROM service_requests r
    JOIN garages g ON g.garage_id = r.garage_id
    WHERE r.request_id = ? AND r.user_id = ? AND r.status = 'completed'
");
$req->execute([$requestId, $userId]);
$req = $req->fetch();

if (!$req) redirect(SITE_URL . '/pages/my_requests.php');

// Check if already rated
$alreadyRated = $db->prepare(
    "SELECT feedback_id FROM feedback WHERE request_id = ? AND user_id = ? LIMIT 1"
);
$alreadyRated->execute([$requestId, $userId]);
$existingFeedback = $alreadyRated->fetch();

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existingFeedback) {
    $rating  = intval($_POST['rating']   ?? 0);
    $comment = sanitize($_POST['comment'] ?? '');

    if ($rating < 1 || $rating > 5) $errors[] = 'Please select a rating between 1 and 5 stars.';

    if (empty($errors)) {
        $db->prepare(
            "INSERT INTO feedback (request_id, user_id, garage_id, rating, comment)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([$requestId, $userId, $req['garage_id'], $rating, $comment]);

        // Recalculate garage average rating
        $db->prepare(
            "UPDATE garages
             SET rating = (
                 SELECT ROUND(AVG(f.rating), 2)
                 FROM feedback f
                 WHERE f.garage_id = ?
             )
             WHERE garage_id = ?"
        )->execute([$req['garage_id'], $req['garage_id']]);

        $success = 'Thank you for your feedback!';
        // Reload existing feedback
        $alreadyRated->execute([$requestId, $userId]);
        $existingFeedback = $alreadyRated->fetch();
    }
}

// Load existing feedback if present
$feedbackData = null;
if ($existingFeedback) {
    $fd = $db->prepare("SELECT * FROM feedback WHERE request_id=? AND user_id=? LIMIT 1");
    $fd->execute([$requestId, $userId]);
    $feedbackData = $fd->fetch();
}

$pageTitle = 'Rate Your Experience';
include __DIR__ . '/../includes/header.php';
?>

<style>
.feedback-wrap { max-width:600px; margin:48px auto; padding:0 20px 64px; }

/* Stars */
.star-row {
  display:flex; gap:8px; justify-content:center; margin:20px 0 8px;
  flex-direction: row-reverse; /* CSS trick: hover works right-to-left */
}
.star-row input[type=radio] { display:none; }
.star-row label {
  font-size:40px; cursor:pointer; color:#ddd;
  transition: color .15s, transform .15s;
  line-height:1;
}
/* Fill stars on hover and checked — using row-reverse */
.star-row label:hover,
.star-row label:hover ~ label,
.star-row input:checked ~ label { color:#f5a623; }
.star-row label:hover { transform: scale(1.15); }

.rating-caption {
  text-align:center; font-size:14px; font-weight:700;
  color:var(--muted); min-height:22px; margin-bottom:20px;
  transition: color .2s;
}

/* Garage card */
.garage-card {
  background:linear-gradient(135deg, var(--navy) 0%, #1a3a5c 100%);
  border-radius:14px; padding:24px; margin-bottom:28px;
  display:flex; align-items:center; gap:18px; color:#fff;
}
.garage-card .icon {
  width:56px; height:56px; border-radius:14px;
  background:rgba(255,255,255,.1);
  display:flex; align-items:center; justify-content:center; font-size:28px;
  flex-shrink:0;
}
.garage-card h3 { font-size:18px; font-weight:800; margin:0 0 4px; }
.garage-card p  { font-size:13px; color:rgba(255,255,255,.55); margin:0; }

/* Already rated */
.rated-stars { display:flex; gap:4px; justify-content:center; font-size:36px; margin:20px 0; }
.rated-stars span.filled { color:#f5a623; }
.rated-stars span.empty  { color:#ddd; }

.service-info-row {
  display:flex; justify-content:space-between; align-items:center;
  padding:10px 14px; background:var(--bg); border-radius:8px;
  font-size:13px; margin-bottom:8px;
}
.service-info-row .lbl { color:var(--muted); }
.service-info-row .val { font-weight:700; }
</style>

<div class="feedback-wrap">

  <!-- Garage card -->
  <div class="garage-card">
    <div class="icon">🔧</div>
    <div>
      <h3><?= htmlspecialchars($req['garage_name']) ?></h3>
      <p>📍 <?= htmlspecialchars($req['garage_location']) ?></p>
    </div>
    <div style="margin-left:auto;text-align:right;flex-shrink:0;">
      <div style="font-size:22px;font-weight:800;color:var(--gold);">
        ★ <?= number_format($req['garage_current_rating'], 1) ?>
      </div>
      <div style="font-size:11px;color:rgba(255,255,255,.4);">Current Rating</div>
    </div>
  </div>

  <!-- Service summary -->
  <div style="margin-bottom:24px;">
    <div class="service-info-row">
      <span class="lbl">Service</span>
      <span class="val"><?= ucfirst($req['service_type']) ?></span>
    </div>
    <div class="service-info-row">
      <span class="lbl">Vehicle</span>
      <span class="val"><?= ucfirst(str_replace('_',' ',$req['vehicle_type'])) ?></span>
    </div>
    <div class="service-info-row">
      <span class="lbl">Date</span>
      <span class="val"><?= date('d M Y', strtotime($req['created_at'])) ?></span>
    </div>
    <div class="service-info-row">
      <span class="lbl">Request ID</span>
      <span class="val" style="color:var(--muted);">IVSS-<?= str_pad($req['request_id'],4,'0',STR_PAD_LEFT) ?></span>
    </div>
  </div>

  <!-- Already rated -->
  <?php if ($existingFeedback && $feedbackData): ?>
  <div class="card" style="text-align:center;padding:32px;">
    <div style="font-size:40px;margin-bottom:12px;">✅</div>
    <div style="font-size:18px;font-weight:800;color:var(--navy);margin-bottom:8px;">You've already rated this service</div>
    <div class="rated-stars">
      <?php for ($i = 1; $i <= 5; $i++): ?>
        <span class="<?= $i <= $feedbackData['rating'] ? 'filled' : 'empty' ?>">★</span>
      <?php endfor; ?>
    </div>
    <div style="font-size:24px;font-weight:800;color:var(--gold);margin-bottom:12px;">
      <?= $feedbackData['rating'] ?> / 5
    </div>
    <?php if ($feedbackData['comment']): ?>
    <div style="background:var(--bg);border-radius:10px;padding:14px 18px;font-size:14px;
                color:var(--text);line-height:1.7;text-align:left;margin-top:12px;">
      💬 "<?= htmlspecialchars($feedbackData['comment']) ?>"
    </div>
    <?php endif; ?>
    <div style="margin-top:24px;display:flex;gap:12px;justify-content:center;">
      <a href="my_requests.php" class="btn btn-dark btn-sm">← My Requests</a>
    </div>
  </div>

  <?php else: ?>

  <!-- Success -->
  <?php if ($success): ?>
  <div class="alert alert-success">✅ <?= $success ?> Redirecting...</div>
  <script>setTimeout(()=>location.href='my_requests.php',1800);</script>
  <?php endif; ?>

  <!-- Errors -->
  <?php foreach ($errors as $e): ?>
  <div class="alert alert-danger">⚠️ <?= $e ?></div>
  <?php endforeach; ?>

  <!-- Rating Form -->
  <div class="card">
    <h3 style="font-size:18px;font-weight:800;text-align:center;margin-bottom:4px;">
      Rate Your Experience
    </h3>
    <p style="text-align:center;font-size:13px;color:var(--muted);margin-bottom:4px;">
      How would you rate the service provided by this garage?
    </p>

    <form method="POST" id="rateForm">
      <!-- Stars -->
      <div class="star-row" id="starRow">
        <?php for ($i = 5; $i >= 1; $i--): ?>
        <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>">
        <label for="star<?= $i ?>" title="<?= $i ?> star<?= $i>1?'s':'' ?>"
               onmouseover="setCaption(<?= $i ?>)"
               onmouseout="resetCaption()">★</label>
        <?php endfor; ?>
      </div>
      <div class="rating-caption" id="ratingCaption">Tap a star to rate</div>

      <!-- Comment -->
      <div class="form-group">
        <label>Your Review <span style="color:var(--muted);font-weight:400;">(optional)</span></label>
        <textarea name="comment" class="form-control" rows="4"
                  placeholder="Tell others about your experience with this garage..."><?= htmlspecialchars($_POST['comment'] ?? '') ?></textarea>
      </div>

      <!-- Labels reference -->
      <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin:-12px 0 16px;padding:0 4px;">
        <span>Terrible</span><span>Bad</span><span>OK</span><span>Good</span><span>Excellent</span>
      </div>

      <button type="submit" class="btn btn-primary btn-full" style="font-size:15px;padding:13px;">
        ⭐ Submit Rating
      </button>
      <div style="text-align:center;margin-top:12px;">
        <a href="my_requests.php" style="font-size:13px;color:var(--muted);text-decoration:none;">
          ← Skip and go back
        </a>
      </div>
    </form>
  </div>

  <?php endif; ?>
</div>

<script>
const captions = ['','Terrible — Very disappointed 😤','Bad — Not satisfied 😕','OK — Average experience 😐','Good — Happy with the service 😊','Excellent — Outstanding service! 🌟'];

function setCaption(n) {
  const el = document.getElementById('ratingCaption');
  el.textContent  = captions[n];
  el.style.color  = ['','var(--danger)','var(--warning)','#888','var(--teal)','var(--gold)'][n];
}

function resetCaption() {
  const checked = document.querySelector('input[name=rating]:checked');
  if (checked) setCaption(parseInt(checked.value));
  else {
    document.getElementById('ratingCaption').textContent = 'Tap a star to rate';
    document.getElementById('ratingCaption').style.color = 'var(--muted)';
  }
}

document.querySelectorAll('input[name=rating]').forEach(r => {
  r.addEventListener('change', () => setCaption(parseInt(r.value)));
});

// Validate star selected
document.getElementById('rateForm')?.addEventListener('submit', function(e) {
  const picked = document.querySelector('input[name=rating]:checked');
  if (!picked) {
    e.preventDefault();
    document.getElementById('ratingCaption').textContent = '⚠️ Please select a star rating first';
    document.getElementById('ratingCaption').style.color = 'var(--danger)';
    document.getElementById('starRow').scrollIntoView({behavior:'smooth', block:'center'});
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
