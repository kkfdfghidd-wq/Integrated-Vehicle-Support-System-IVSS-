<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Terms of Service & Privacy Policy';
include __DIR__ . '/../includes/header.php';
?>

<style>
  .legal-wrap {
    max-width: 820px;
    margin: 48px auto;
    padding: 0 20px 64px;
  }

  .legal-hero {
    background: linear-gradient(135deg, var(--navy) 0%, #1a3a5c 100%);
    border-radius: 16px;
    padding: 40px 48px;
    color: #fff;
    margin-bottom: 36px;
    display: flex;
    align-items: center;
    gap: 24px;
  }

  .legal-hero-icon {
    font-size: 48px;
    flex-shrink: 0;
  }

  .legal-hero h1 {
    font-size: 26px;
    font-weight: 800;
    margin: 0 0 8px;
    color: #fff;
  }

  .legal-hero p {
    margin: 0;
    font-size: 14px;
    color: rgba(255, 255, 255, 0.6);
    line-height: 1.6;
  }

  .legal-toc {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px 28px;
    margin-bottom: 32px;
  }

  .legal-toc h3 {
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--muted);
    margin: 0 0 14px;
  }

  .legal-toc ol {
    margin: 0;
    padding-left: 20px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px 24px;
  }

  .legal-toc li a {
    font-size: 13px;
    color: var(--teal);
    text-decoration: none;
    font-weight: 600;
  }

  .legal-toc li a:hover {
    text-decoration: underline;
  }

  .legal-section {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 32px 36px;
    margin-bottom: 20px;
  }

  .legal-section h2 {
    font-size: 18px;
    font-weight: 800;
    color: var(--navy);
    margin: 0 0 6px;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .section-num {
    background: var(--navy);
    color: var(--gold);
    font-size: 11px;
    font-weight: 800;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .legal-section .updated {
    font-size: 12px;
    color: var(--muted);
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border);
  }

  .legal-section p {
    font-size: 14px;
    line-height: 1.8;
    color: var(--text);
    margin: 0 0 14px;
  }

  .legal-section ul,
  .legal-section ol {
    font-size: 14px;
    line-height: 1.8;
    color: var(--text);
    padding-left: 22px;
    margin: 0 0 14px;
  }

  .legal-section li {
    margin-bottom: 6px;
  }

  .legal-section h3 {
    font-size: 14px;
    font-weight: 700;
    color: var(--navy);
    margin: 20px 0 8px;
  }

  .highlight-box {
    background: rgba(26, 158, 138, 0.06);
    border: 1px solid rgba(26, 158, 138, 0.2);
    border-left: 3px solid var(--teal);
    border-radius: 8px;
    padding: 14px 18px;
    font-size: 13px;
    line-height: 1.7;
    color: var(--text);
    margin: 16px 0;
  }

  .warning-box {
    background: rgba(212, 168, 67, 0.07);
    border: 1px solid rgba(212, 168, 67, 0.25);
    border-left: 3px solid var(--gold);
    border-radius: 8px;
    padding: 14px 18px;
    font-size: 13px;
    line-height: 1.7;
    color: var(--text);
    margin: 16px 0;
  }

  .back-btn-wrap {
    position: sticky;
    top: 16px;
    z-index: 10;
    display: flex;
    justify-content: flex-start;
    margin-bottom: 28px;
  }

  .back-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 20px;
    font-size: 13px;
    font-weight: 700;
    color: var(--navy);
    text-decoration: none;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    transition: box-shadow 0.15s, border-color 0.15s;
  }

  .back-btn:hover {
    border-color: var(--teal);
    box-shadow: 0 4px 16px rgba(26, 158, 138, 0.12);
    color: var(--teal);
  }

  .divider-label {
    display: flex;
    align-items: center;
    gap: 14px;
    margin: 36px 0 24px;
  }

  .divider-label span {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--muted);
    white-space: nowrap;
  }

  .divider-label::before,
  .divider-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border);
  }
</style>

<div class="legal-wrap">

  <!-- Back Button -->
  <div class="back-btn-wrap">
    <a href="register.php" class="back-btn">← Back to Register</a>
  </div>

  <!-- Hero -->
  <div class="legal-hero">
    <div class="legal-hero-icon">📄</div>
    <div>
      <h1>Terms of Service &amp; Privacy Policy</h1>
      <p>Please read these documents carefully before creating your IVSS account.<br>
        Effective: <?= date('d F Y') ?></p>
    </div>
  </div>

  <!-- Table of Contents -->
  <div class="legal-toc">
    <h3>Contents</h3>
    <ol>
      <li><a href="#acceptance">Acceptance of Terms</a></li>
      <li><a href="#services">Description of Services</a></li>
      <li><a href="#accounts">User Accounts</a></li>
      <li><a href="#conduct">Acceptable Use</a></li>
      <li><a href="#payments">Payments &amp; Fees</a></li>
      <li><a href="#liability">Limitation of Liability</a></li>
      <li><a href="#privacy">Privacy Policy</a></li>
      <li><a href="#data">Data Collection &amp; Use</a></li>
      <li><a href="#contact">Contact Us</a></li>
    </ol>
  </div>

  <!-- ══ TERMS OF SERVICE ══ -->
  <div class="divider-label"><span>Terms of Service</span></div>

  <div class="legal-section" id="acceptance">
    <h2><span class="section-num">1</span> Acceptance of Terms</h2>
    <div class="updated">Effective date: <?= date('d F Y') ?></div>

    <p>By creating an account or using the IVSS (Integrated Vehicle Support System) platform, you confirm that you have read, understood, and agree to be bound by these Terms of Service and our Privacy Policy.</p>

    <div class="highlight-box">
      ℹ️ If you do not agree to these terms, please do not register or use the IVSS platform. Continued use of our services after any modifications constitutes acceptance of the updated terms.
    </div>

    <p>These terms apply to all users of the platform, including drivers requesting roadside assistance and garage partners providing services.</p>
  </div>

  <div class="legal-section" id="services">
    <h2><span class="section-num">2</span> Description of Services</h2>
    <p>IVSS is a digital platform that connects drivers in Oman experiencing vehicle breakdowns with nearby certified garage partners. Our services include:</p>
    <ul>
      <li>GPS-based garage discovery and real-time service requests</li>
      <li>Roadside assistance coordination (towing, battery, tire change, fuel delivery, lockout, repairs)</li>
      <li>Real-time tracking of service progress</li>
      <li>In-app payment processing</li>
      <li>Ratings and feedback system</li>
    </ul>
    <p>IVSS acts as a technology intermediary. We do not directly provide mechanical services and are not responsible for the quality of services delivered by garage partners.</p>
  </div>

  <div class="legal-section" id="accounts">
    <h2><span class="section-num">3</span> User Accounts</h2>
    <h3>Registration</h3>
    <p>To use IVSS services, you must register with accurate and complete information. You agree to:</p>
    <ul>
      <li>Provide truthful personal information (name, email, phone number)</li>
      <li>Keep your account credentials confidential</li>
      <li>Notify us immediately of any unauthorized access to your account</li>
      <li>Be at least 18 years of age</li>
    </ul>
    <h3>Account Termination</h3>
    <p>IVSS reserves the right to suspend or terminate accounts that violate these terms, engage in fraudulent activity, or misuse the platform.</p>
  </div>

  <div class="legal-section" id="conduct">
    <h2><span class="section-num">4</span> Acceptable Use</h2>
    <p>You agree not to use IVSS for any unlawful purpose or in any way that could damage, disable, or impair the service. Prohibited activities include:</p>
    <ul>
      <li>Submitting false or fraudulent service requests</li>
      <li>Harassing or threatening garage partners or other users</li>
      <li>Attempting to circumvent the platform's payment system</li>
      <li>Scraping, hacking, or reverse-engineering any part of the platform</li>
      <li>Impersonating another user or entity</li>
    </ul>
    <div class="warning-box">
      ⚠️ Violation of these terms may result in immediate account termination and may be reported to relevant authorities in accordance with Omani law.
    </div>
  </div>

  <div class="legal-section" id="payments">
    <h2><span class="section-num">5</span> Payments &amp; Fees</h2>
    <p>All service fees are displayed in Omani Rial (OMR) and are agreed upon between the driver and the garage partner prior to service commencement. IVSS facilitates the payment process but does not set service prices.</p>
    <ul>
      <li>Payments are processed securely through the IVSS platform</li>
      <li>Accepted payment methods: credit/debit card, online transfer, cash (where applicable)</li>
      <li>Invoices are generated automatically upon service completion</li>
      <li>Refund requests must be submitted within 48 hours of service completion</li>
    </ul>
  </div>

  <div class="legal-section" id="liability">
    <h2><span class="section-num">6</span> Limitation of Liability</h2>
    <p>To the fullest extent permitted by Omani law, IVSS shall not be liable for:</p>
    <ul>
      <li>Any indirect, incidental, or consequential damages arising from use of the platform</li>
      <li>Actions or omissions of garage partners</li>
      <li>Service delays caused by traffic, weather, or force majeure events</li>
      <li>Loss of data resulting from technical failures beyond our control</li>
    </ul>
    <p>Our total liability in any matter arising out of these terms shall not exceed the amount you paid for the specific service in question.</p>
  </div>

  <!-- ══ PRIVACY POLICY ══ -->
  <div class="divider-label"><span>Privacy Policy</span></div>

  <div class="legal-section" id="privacy">
    <h2><span class="section-num">7</span> Privacy Policy</h2>
    <div class="updated">Effective date: <?= date('d F Y') ?></div>

    <p>Your privacy is important to us. This Privacy Policy explains how IVSS collects, uses, and protects your personal information when you use our platform.</p>

    <div class="highlight-box">
      🔒 IVSS is committed to safeguarding your personal data in compliance with applicable data protection regulations in the Sultanate of Oman.
    </div>
  </div>

  <div class="legal-section" id="data">
    <h2><span class="section-num">8</span> Data Collection &amp; Use</h2>

    <h3>Information We Collect</h3>
    <ul>
      <li><strong>Account data:</strong> Full name, email address, phone number, and password (stored encrypted)</li>
      <li><strong>Location data:</strong> Your location at the time of service requests (used to find nearby garages)</li>
      <li><strong>Usage data:</strong> Service request history, ratings, payment records</li>
      <li><strong>Device data:</strong> Browser type, IP address, device identifiers (for security purposes)</li>
    </ul>

    <h3>How We Use Your Data</h3>
    <ul>
      <li>To provide and improve IVSS services</li>
      <li>To connect you with nearby garage partners</li>
      <li>To process payments and generate invoices</li>
      <li>To send service notifications and account alerts</li>
      <li>To ensure platform security and prevent fraud</li>
    </ul>

    <h3>Data Sharing</h3>
    <p>We do not sell your personal data to third parties. Your contact information is shared with garage partners only as necessary to fulfill your service request. We may share data with law enforcement if required by Omani law.</p>

    <h3>Data Retention</h3>
    <p>We retain your account data for as long as your account is active. You may request deletion of your account and associated data by contacting our support team.</p>

    <h3>Cookies</h3>
    <p>IVSS uses session cookies essential for platform functionality (login sessions, security tokens). We do not use tracking or advertising cookies.</p>

    <div class="warning-box">
      ⚠️ By using IVSS, you consent to the collection and use of your data as described in this policy. You may withdraw consent at any time by deleting your account.
    </div>
  </div>

  <div class="legal-section" id="contact">
    <h2><span class="section-num">9</span> Contact Us</h2>
    <p>If you have questions about these terms or our privacy practices, please contact us:</p>
    <ul>
      <li>📧 Email: <strong>support@ivss.om</strong></li>
      <li>📞 Phone: <strong>+968 9400 0001</strong></li>
      <li>📍 Address: <strong>Muscat, Sultanate of Oman</strong></li>
      <li>🌐 Website: <strong><?= SITE_URL ?></strong></li>
    </ul>
    <p>We will respond to all inquiries within 2 business days.</p>
  </div>

  <!-- Back to Register -->
  <div style="text-align:center;margin-top:40px;">
    <a href="register.php" class="btn btn-primary" style="padding:14px 40px;font-size:15px;">
      ← Back to Register
    </a>
  </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>