<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Terms of Service & Garage Partner Agreement';
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

  .gold-box {
    background: rgba(212, 168, 67, 0.05);
    border: 1.5px solid rgba(212, 168, 67, 0.3);
    border-radius: 10px;
    padding: 20px 24px;
    margin: 20px 0;
  }

  .gold-box h4 {
    font-size: 13px;
    font-weight: 700;
    color: #a07820;
    margin: 0 0 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .gold-box ul {
    margin: 0;
    color: var(--text);
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

  .obligation-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin: 16px 0;
  }

  .obligation-card {
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 16px 18px;
  }

  .obligation-card h4 {
    font-size: 13px;
    font-weight: 700;
    margin: 0 0 10px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .obligation-card ul {
    margin: 0;
    font-size: 13px;
  }
</style>

<div class="legal-wrap">

  <!-- Back Button -->
  <div class="back-btn-wrap">
    <a href="garage_register.php" class="back-btn">← Back to Registration</a>
  </div>

  <!-- Hero -->
  <div class="legal-hero">
    <div class="legal-hero-icon">🔧</div>
    <div>
      <h1>Terms of Service &amp; Garage Partner Agreement</h1>
      <p>Please read these documents carefully before registering your garage on the IVSS platform.<br>
        Last updated: <?= date('d F Y') ?> &nbsp;·&nbsp; Effective upon registration</p>
    </div>
  </div>

  <!-- Table of Contents -->
  <div class="legal-toc">
    <h3>Contents</h3>
    <ol>
      <li><a href="#acceptance">Acceptance of Terms</a></li>
      <li><a href="#services">Platform Services</a></li>
      <li><a href="#eligibility">Eligibility &amp; Registration</a></li>
      <li><a href="#obligations">Partner Obligations</a></li>
      <li><a href="#pricing">Pricing &amp; Payments</a></li>
      <li><a href="#subscription">Subscription</a></li>
      <li><a href="#quality">Quality Standards</a></li>
      <li><a href="#termination">Termination</a></li>
      <li><a href="#liability">Liability</a></li>
      <li><a href="#contact">Contact</a></li>
    </ol>
  </div>

  <!-- ══ TERMS OF SERVICE ══ -->
  <div class="divider-label"><span>Terms of Service</span></div>

  <div class="legal-section" id="acceptance">
    <h2><span class="section-num">1</span> Acceptance of Terms</h2>
    <div class="updated">Effective date: <?= date('d F Y') ?></div>
    <p>By registering your garage on the IVSS platform, you and your business confirm that you have read, understood, and agree to be bound by these Terms of Service and the Garage Partner Agreement below.</p>
    <div class="highlight-box">
      ℹ️ These terms form a binding legal agreement between you ("Garage Partner") and IVSS. If you do not agree to these terms, do not proceed with registration.
    </div>
    <p>These terms govern your participation as a service provider on the IVSS platform and apply to all interactions with drivers, payments, and platform features.</p>
  </div>

  <div class="legal-section" id="services">
    <h2><span class="section-num">2</span> Platform Services</h2>
    <p>IVSS provides a technology platform that connects drivers experiencing vehicle emergencies with nearby garage partners. As a garage partner, you gain access to:</p>
    <ul>
      <li>A dedicated garage dashboard to manage incoming service requests</li>
      <li>Real-time notifications for new requests in your area</li>
      <li>Broadcast and direct request assignment system</li>
      <li>Integrated payment processing and invoice generation</li>
      <li>Performance analytics and ratings dashboard</li>
      <li>Customer complaint and resolution management</li>
    </ul>
    <p>IVSS acts solely as a technology intermediary and does not employ garage partners. You operate as an independent business.</p>
  </div>

  <div class="legal-section" id="eligibility">
    <h2><span class="section-num">3</span> Eligibility &amp; Registration</h2>
    <h3>Requirements</h3>
    <p>To register as a garage partner, you must:</p>
    <ul>
      <li>Operate a legitimate, licensed automotive service business in Oman</li>
      <li>Hold all required commercial registrations and trade licenses</li>
      <li>Employ qualified technicians for the services you offer</li>
      <li>Provide accurate business information during registration</li>
      <li>Have a valid Omani phone number starting with 7 or 9</li>
    </ul>
    <div class="warning-box">
      ⚠️ Providing false information during registration is grounds for immediate account termination and may result in legal action under Omani commercial law.
    </div>
    <h3>Verification</h3>
    <p>IVSS reserves the right to verify your business credentials at any time. Failure to provide requested documentation within 7 business days may result in account suspension.</p>
  </div>

  <!-- ══ GARAGE PARTNER AGREEMENT ══ -->
  <div class="divider-label"><span>Garage Partner Agreement</span></div>

  <div class="legal-section" id="obligations">
    <h2><span class="section-num">4</span> Partner Obligations</h2>
    <p>As a registered IVSS Garage Partner, you agree to the following obligations:</p>

    <div class="obligation-row">
      <div class="obligation-card">
        <h4>✅ You Must</h4>
        <ul>
          <li>Respond to accepted requests promptly</li>
          <li>Provide services as described in your profile</li>
          <li>Maintain honest and fair pricing</li>
          <li>Treat all drivers with professionalism</li>
          <li>Keep your service list accurate and up to date</li>
          <li>Honor agreed service prices</li>
        </ul>
      </div>
      <div class="obligation-card">
        <h4>🚫 You Must Not</h4>
        <ul>
          <li>Accept requests you cannot fulfill</li>
          <li>Charge prices different from what was agreed</li>
          <li>Contact drivers outside the IVSS platform</li>
          <li>Engage in fraudulent service claims</li>
          <li>Discriminate against any driver</li>
          <li>Share account credentials with others</li>
        </ul>
      </div>
    </div>

    <h3>Service Acceptance</h3>
    <p>When you accept a service request, you enter into a direct service agreement with the driver. IVSS is not a party to this agreement and assumes no liability for service delivery outcomes.</p>
  </div>

  <div class="legal-section" id="pricing">
    <h2><span class="section-num">5</span> Pricing &amp; Payments</h2>
    <p>All service prices are set and communicated by you, the garage partner. IVSS provides pricing tools but does not dictate service fees.</p>

    <div class="gold-box">
      <h4>💰 Payment Terms</h4>
      <ul>
        <li>Payments are collected through the IVSS platform upon service completion</li>
        <li>Cash payments must be marked as received in your dashboard within 24 hours</li>
        <li>Invoices are auto-generated — do not issue separate invoices unless requested</li>
        <li>Disputed payments must be flagged within 48 hours of the transaction</li>
        <li>All amounts are in Omani Rial (OMR) inclusive of applicable taxes</li>
      </ul>
    </div>

    <h3>Refunds &amp; Disputes</h3>
    <p>If a driver raises a payment dispute, IVSS will investigate and mediate. You agree to cooperate fully with dispute resolution processes. IVSS's decision on payment disputes is final.</p>
  </div>

  <div class="legal-section" id="subscription">
    <h2><span class="section-num">6</span> Subscription</h2>
    <p>Access to the IVSS Garage Partner features requires an active subscription. Available plans:</p>
    <ul>
      <li><strong>Weekly</strong> — 15.000 OMR for 7 days</li>
      <li><strong>Monthly</strong> — 45.000 OMR for 30 days</li>
      <li><strong>Yearly</strong> — 400.000 OMR for 365 days</li>
    </ul>
    <div class="highlight-box">
      ⭐ An active subscription is required to accept new service requests. You may view incoming requests without a subscription, but acceptance is restricted until payment is made.
    </div>
    <p>Subscriptions are non-refundable once activated unless IVSS terminates the service without cause. IVSS reserves the right to modify subscription prices with 30 days advance notice.</p>
  </div>

  <div class="legal-section" id="quality">
    <h2><span class="section-num">7</span> Quality Standards</h2>
    <p>Maintaining service quality is essential to the IVSS ecosystem. You agree to:</p>
    <ul>
      <li>Maintain a minimum average rating of 3.0 out of 5.0</li>
      <li>Respond to open complaints within 48 hours</li>
      <li>Complete accepted requests — cancellations should be rare and justified</li>
      <li>Keep your garage location, services, and contact information accurate</li>
    </ul>
    <div class="warning-box">
      ⚠️ Garages with persistent low ratings, unresolved complaints, or high cancellation rates may be temporarily suspended or permanently removed from the platform after review.
    </div>
  </div>

  <div class="legal-section" id="termination">
    <h2><span class="section-num">8</span> Termination</h2>
    <h3>By You</h3>
    <p>You may terminate your garage partner account at any time by contacting IVSS support. Active subscriptions will not be refunded upon voluntary termination.</p>
    <h3>By IVSS</h3>
    <p>IVSS may suspend or terminate your account immediately without notice for:</p>
    <ul>
      <li>Fraudulent activity or misrepresentation</li>
      <li>Repeated violations of these terms</li>
      <li>Persistent quality failures despite warnings</li>
      <li>Legal or regulatory non-compliance</li>
    </ul>
    <p>Upon termination, all pending payments for completed services will still be processed according to the standard payment schedule.</p>
  </div>

  <div class="legal-section" id="liability">
    <h2><span class="section-num">9</span> Limitation of Liability</h2>
    <p>IVSS provides the platform "as is" and makes no warranties regarding:</p>
    <ul>
      <li>Continuity or volume of service requests</li>
      <li>Platform availability (though we target 99% uptime)</li>
      <li>Actions or conduct of drivers using the platform</li>
    </ul>
    <p>IVSS shall not be liable for any indirect, incidental, or consequential damages arising from your participation as a garage partner. Your sole remedy for platform dissatisfaction is to terminate your account.</p>
    <p>You agree to indemnify and hold harmless IVSS from any claims arising out of your service delivery, conduct, or violation of these terms.</p>
  </div>

  <div class="legal-section" id="contact">
    <h2><span class="section-num">10</span> Contact Us</h2>
    <p>For questions about these terms or the Partner Agreement, contact our partner support team:</p>
    <ul>
      <li>📧 Email: <strong>support@ivss.om</strong></li>
      <li>📞 Phone: <strong>+968 9400 0001</strong></li>
      <li>📍 Address: <strong>Muscat, Sultanate of Oman</strong></li>
      <li>🌐 Portal: <strong><?= SITE_URL ?>/pages/garage_register.php</strong></li>
    </ul>
    <p>Partner support inquiries are responded to within 1 business day during business hours (Sunday–Thursday, 8am–5pm GST).</p>
  </div>

  <!-- Back to Registration -->
  <div style="text-align:center;margin-top:40px;">
    <a href="garage_register.php" class="btn btn-primary" style="padding:14px 40px;font-size:15px;">
      ← Back to Registration
    </a>
  </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>