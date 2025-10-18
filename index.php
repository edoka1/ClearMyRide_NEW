<?php
session_start();

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/assets/app/alerts.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClearMyRide - Clear Tickets & Complete Your MD Renewal</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">


    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="icon" href="assets/images/favicon.png" type="image/png">
    <link rel="stylesheet" href="assets/js/script.js">
</head>

<body>
    <!-- NAV -->
    <header class="nav" role="banner">
        <div class="container">
            <a class="brand" href="#">
                <img src="assets/images/CMR Transparent BG.png" alt="Clear My Ride logo" />
                <span class="brand-text">ClearMyRide</span>
            </a>

            <div class="nav-actions" role="navigation" aria-label="Primary">
                <button class="cta-primary"><a href="#about" style="color: white; text-decoration: none;">Learn More</a></button>
            </div>
        </div>
    </header>

    <!-- HERO -->
    <main>
        <section class="hero reveal" aria-label="Hero">
            <div class="container">
                <div class="hero-grid">


                    <div class="hero-copy">
                        <h1 class="hero-title">
                            Your one stop shop for clearing MD citations, flags, and renewing your tags / license —
                            without stress.
                        </h1>

                        <div class="hero-sub">Serving Maryland drivers blocked by MVA flags, tolls, and tickets.</div>

                        <div class="hero-cta">
                            <button id="middle" class="cta-primary"><a href="#form" style="color: white; text-decoration: none;">Check My Status</a></button>
                        </div>
                    </div>


                    <div class="hero-illustration">
                        <img src="assets/images/car.jpg"
                            alt="Vehicle registration and renewal illustration" class="hero-img">
                    </div>


                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section class="how-section reveal">
            <div class="container">
                <header class="how-header">
                    <h2 class="how-title">How it works</h2>
                    <p class="how-intro">A simple six-step process — we handle citations, clear flags, and complete your
                        renewal end-to-end.</p>
                </header>

                <div class="how-grid" role="list" aria-label="How it works steps">

                    <article class="how-step" role="listitem" aria-labelledby="step1-title">
                        <div class="step-badge">1</div>
                        <h3 id="step1-title" class="step-title">Submit your info</h3>
                        <p class="step-copy">Start by filling out our secure intake form. Provide your license plate
                            number, driver's license details, and upload any citation or flag letters you've received.
                        </p>
                    </article>

                    <article class="how-step" role="listitem" aria-labelledby="step2-title">
                        <div class="step-badge">2</div>
                        <h3 id="step2-title" class="step-title">We assess your case</h3>
                        <p class="step-copy">Our team reviews citations and flags across jurisdictions, organizes
                            everything in a secure dashboard, and estimates total costs (government fees + our service
                            fee).</p>
                    </article>

                    <article class="how-step" role="listitem" aria-labelledby="step3-title">
                        <div class="step-badge">3</div>
                        <h3 id="step3-title" class="step-title">Approve & authorize</h3>
                        <p class="step-copy">After you review the estimate, sign a consent form authorizing us to access
                            your driver record and process payments. We send a secure payment link to finalize
                            authorization.</p>
                    </article>

                    <article class="how-step" role="listitem" aria-labelledby="step4-title">
                        <div class="step-badge">4</div>
                        <h3 id="step4-title" class="step-title">We get to work</h3>
                        <p class="step-copy">Once consent and payment are received, we begin resolving citations, tolls,
                            and flags. You receive daily status updates by email or text.</p>
                    </article>


                    <article class="how-step" role="listitem" aria-labelledby="step5-title">
                        <div class="step-badge">5</div>
                        <h3 id="step5-title" class="step-title">We renew for you</h3>
                        <p class="step-copy">After flags are cleared we immediately renew your registration and/or
                            driver's license — no separate action needed. These costs are included unless noted.</p>
                    </article>

                    <article class="how-step" role="listitem" aria-labelledby="step6-title">
                        <div class="step-badge">6</div>
                        <h3 id="step6-title" class="step-title">Delivery + Final Reconciliation</h3>
                        <p class="step-copy">We confirm renewal and delivery of your tags or license. If there are any
                            additional government fees outside of the original estimate, we'll notify you for final
                            reconciliation.</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- ABOUT US -->
        <section id="about" class="about-section reveal" aria-labelledby="about-heading">
            <div class="container about-grid">
                <!-- LEFT: image -->
                <figure class="about-image" aria-hidden="false">
                    <img src="assets/images/office.jpg"
                        alt="Person handing over vehicle documents to a service agent" />
                </figure>

                <!-- RIGHT: copy -->
                <div class="about-text">
                    <h2 id="about-heading" class="about-heading">About — Why we exist</h2>

                    <p class="about-brief">
                        ClearMyRide was built to take the frustration out of vehicle and license renewals — especially
                        when tolls, tickets, or MVA flags from multiple jurisdictions get in the way. Resolving these
                        issues often means hours of calling different agencies, navigating confusing portals, and
                        chasing down paperwork.
                    </p>

                    <p class="about-commit">
                        We handle the full process for you — so you can stay on the road and focus on what matters. Let
                        us deal with the wait times, the forms, and the follow-ups — efficiently, securely, and with
                        real-time updates every step of the way.
                    </p>

                </div>
            </div>
        </section>



        <section class="intake-section reveal" id="form" aria-labelledby="intake-heading">
            <div class="container py-5">
                <div class="section-header">
                    <h2 id="intake-heading" class="section-title">Start Your Renewal</h2>
                    <p class="section-subtitle">Select the correct form below and provide the requested details. We'll review, provide an estimate, obtain authorization, and start resolving flags/citations.</p>
                </div>

                <?php show_flash(); ?>

                <div class="intake-card">

                    <!-- Tabs -->
                    <div class="p-3">
                        <ul class="nav nav-pills nav-intake justify-content-center gap-2" id="intakeTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="tab-vehicle" data-bs-toggle="pill" data-bs-target="#panel-vehicle" type="button" role="tab" aria-controls="panel-vehicle" aria-selected="true">
                                    Vehicle Registration
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-license" data-bs-toggle="pill" data-bs-target="#panel-license" type="button" role="tab" aria-controls="panel-license" aria-selected="false">
                                    Driver's License
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="intake-body">
                        <div class="tab-content" id="intakeTabsContent">

                            <!-- VEHICLE PANEL -->
                            <div class="tab-pane fade show active" id="panel-vehicle" role="tabpanel" aria-labelledby="tab-vehicle">
                                <div class="form-header">
                                    <h3 class="form-title">Vehicle Registration / Renewal Intake Form</h3>
                                    <p class="form-description">Used when a customer wants to renew vehicle registration, check flags, or clear citations tied to a tag.</p>
                                </div>

                                <form id="vehicle-form" class="needs-validation" novalidate method="POST" action="assets/app/submit_vehicle.php" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                    <div class="form-grid">

                                        <div class="form-group">
                                            <label for="v-fullname" class="form-label required">Full Name</label>
                                            <input id="v-fullname" name="fullName" type="text" class="form-control" required placeholder="Jane A. Doe">
                                            <div class="invalid-feedback"></div>
                                        </div>

                                        <div class="form-group">
                                            <label for="v-email" class="form-label required">Email Address</label>
                                            <input id="v-email" name="email" type="email" class="form-control" required placeholder="jane@example.com">
                                            <div class="invalid-feedback"></div>
                                        </div>

                                        <div class="form-group">
                                            <label for="v-phone" class="form-label required">Phone Number</label>
                                            <input id="v-phone" name="phone" type="tel" class="form-control" required placeholder="(410) 555-1234">
                                            <div class="invalid-feedback"></div>
                                        </div>

                                        <div class="form-group">
                                            <label for="v-dob" class="form-label required">Date of Birth</label>
                                            <input id="v-dob" name="dob" type="date" class="form-control" required placeholder="YYYY-MM-DD">
                                            <div class="invalid-feedback"></div>
                                        </div>

                                        <div class="form-group">
                                            <label for="v-plate" class="form-label required">Maryland License Plate Number</label>
                                            <input id="v-plate" name="plate" type="text" class="form-control" required placeholder="e.g. ABC-1234 or 7AB1234">
                                            <div class="invalid-feedback"></div>
                                        </div>

                                        <div class="form-group">
                                            <label for="v-vin" class="form-label">Vehicle VIN</label>
                                            <input id="v-vin" name="vin" type="text" class="form-control" inputmode="text" placeholder="17-character VIN (e.g. 1HGCM82633A004352)">
                                            <div class="invalid-feedback"></div>
                                        </div>

                                        <div class="form-group">
                                            <label for="v-exp" class="form-label">Registration Expiration Date</label>
                                            <input id="v-exp" name="regExp" type="date" class="form-control" placeholder="">
                                            <div class="invalid-feedback"></div>
                                        </div>

                                        <div class="form-group">
                                            <label for="v-renew" class="form-label required">When is your renewal due?</label>
                                            <select id="v-renew" name="renewWhen" class="form-select" required>
                                                <option value="">Select timeframe</option>
                                                <option value="within-month">Within a month</option>
                                                <option value="1-3">1-3 months</option>
                                                <option value="3-6">3-6 months</option>
                                                <option value="6-plus">6 months +</option>
                                            </select>
                                            <div class="invalid-feedback"></div>
                                        </div>

                                        <div class="form-group full-width">
                                            <label class="form-label required">Do you have outstanding tickets, tolls, or flags?</label>
                                            <div class="radio-group d-flex gap-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="v-hasIssues" id="v-has-yes" value="yes" required>
                                                    <label class="form-check-label" for="v-has-yes">Yes</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="v-hasIssues" id="v-has-no" value="no" required>
                                                    <label class="form-check-label" for="v-has-no">No</label>
                                                </div>
                                            </div>
                                            <!-- inline feedback for radio group -->
                                            <div id="v-hasIssues-feedback" class="invalid-feedback"></div>
                                        </div>

                                        <div class="form-group full-width">
                                            <label class="form-label">Upload citation or toll letters (optional)</label>
                                            <input id="v-files" name="vehicleFiles[]" type="file" accept=".pdf,image/*" multiple class="form-control">
                                            <small class="form-text">Allowed: PDF, JPG, PNG. Max 10MB each.</small>
                                            <div class="invalid-feedback"></div>
                                        </div>

                                        <div class="form-group">
                                            <label for="v-delivery" class="form-label required">Preferred Delivery Method</label>
                                            <select id="v-delivery" name="delivery" class="form-select" required>
                                                <option value="">Select method</option>
                                                <option value="email">Email</option>
                                                <option value="mail">Mail</option>
                                            </select>
                                            <div class="invalid-feedback"></div>
                                        </div>

                                        <div class="form-group">
                                            <label for="v-ref" class="form-label required">How did you hear about us?</label>
                                            <select id="v-ref" name="referral" class="form-select" required>
                                                <option value="">Select</option>
                                                <option value="instagram">Instagram</option>
                                                <option value="tiktok">TikTok</option>
                                                <option value="facebook">Facebook</option>
                                                <option value="peer">Friend/Peer</option>
                                                <option value="other">Other</option>
                                            </select>
                                            <div class="invalid-feedback"></div>
                                        </div>

                                        <div class="form-group full-width">
                                            <div class="consent-box">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="v-consent" name="consent" required>
                                                    <label class="form-check-label required" for="v-consent">
                                                        I consent to ClearMyRide accessing my MVA records to process my renewal request.
                                                    </label>
                                                </div>
                                                <div class="consent-text small text-muted mt-2">This authorization is required to check and resolve any flags or citations on your record.</div>
                                                <div id="v-consent-feedback" class="invalid-feedback"></div>
                                            </div>
                                        </div>

                                        <div class="form-group full-width">
                                            <button type="submit" class="btn btn-primary intake">Submit Vehicle Registration Request</button>
                                        </div>

                                    </div>
                                </form>
                            </div>

                            <!-- LICENSE PANEL -->
                            <div class="tab-pane fade" id="panel-license" role="tabpanel" aria-labelledby="tab-license">
                                <div class="form-header">
                                    <h3 class="form-title">Driver's License Renewal Intake Form</h3>
                                    <p class="form-description">Used when a customer wants to renew their license and needs to check for MVA flags, suspensions, etc.</p>
                                </div>

                                <form id="license-form" class="needs-validation" novalidate method="POST" action="assets/app/submit_license.php" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                    <div class="form-grid">

                                        <div class="form-group">
                                            <label for="l-fullname" class="form-label required">Full Name</label>
                                            <input id="l-fullname" name="fullName" type="text" class="form-control" required placeholder="Jane A. Doe">
                                            <div class="invalid-feedback"></div>
                                        </div>

                                        <div class="form-group">
                                            <label for="l-email" class="form-label required">Email Address</label>
                                            <input id="l-email" name="email" type="email" class="form-control" required placeholder="jane@example.com">
                                            <div class="invalid-feedback"></div>
                                        </div>

                                        <div class="form-group">
                                            <label for="l-phone" class="form-label required">Phone Number</label>
                                            <input id="l-phone" name="phone" type="tel" class="form-control" required placeholder="(410) 555-1234">
                                            <div class="invalid-feedback"></div>
                                        </div>

                                        <div class="form-group">
                                            <label for="l-dob" class="form-label required">Date of Birth</label>
                                            <input id="l-dob" name="dob" type="date" class="form-control" required placeholder="YYYY-MM-DD">
                                            <div class="invalid-feedback"></div>
                                        </div>

                                        <div class="form-group">
                                            <label for="l-number" class="form-label required">Driver's License Number</label>
                                            <input id="l-number" name="licenseNumber" type="text" class="form-control" required placeholder="e.g. D12345678">
                                            <div class="invalid-feedback"></div>
                                        </div>

                                        <div class="form-group">
                                            <label for="l-exp" class="form-label">License Expiration Date</label>
                                            <input id="l-exp" name="licenseExp" type="date" class="form-control" placeholder="">
                                            <div class="invalid-feedback"></div>
                                        </div>

                                        <div class="form-group full-width">
                                            <label class="form-label required">Any unpaid tickets or MVA holds?</label>
                                            <div class="radio-group d-flex gap-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="l-hasIssues" id="l-has-yes" value="yes" required>
                                                    <label class="form-check-label" for="l-has-yes">Yes</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="l-hasIssues" id="l-has-no" value="no" required>
                                                    <label class="form-check-label" for="l-has-no">No</label>
                                                </div>
                                            </div>
                                            <div id="l-hasIssues-feedback" class="invalid-feedback"></div>
                                        </div>

                                        <div class="form-group full-width">
                                            <label class="form-label">Upload any MVA letters (optional)</label>
                                            <input id="l-files" name="licenseFiles[]" type="file" accept=".pdf,image/*" multiple class="form-control">
                                            <small class="form-text">Allowed: PDF, JPG, PNG. Max 10MB each.</small>
                                            <div class="invalid-feedback"></div>
                                        </div>

                                        <div class="form-group full-width">
                                            <label for="l-sign" class="form-label required">Signature — type full name</label>
                                            <input id="l-sign" name="signature" type="text" class="form-control" required placeholder="Type your full name">
                                            <div class="invalid-feedback"></div>
                                        </div>

                                        <div class="form-group full-width">
                                            <div class="consent-box">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="l-consent" name="consent" required>
                                                    <label class="form-check-label required" for="l-consent">
                                                        I consent to ClearMyRide accessing my driver record to process my license renewal request.
                                                    </label>
                                                </div>
                                                <div class="consent-text small text-muted mt-2">This authorization is required for us to check and resolve any flags or suspensions on your driving record.</div>
                                                <div id="l-consent-feedback" class="invalid-feedback"></div>
                                            </div>
                                        </div>

                                        <div class="form-group full-width">
                                            <button type="submit" class="btn btn-primary intake">Submit License Renewal Request</button>
                                        </div>

                                    </div>
                                </form>
                            </div>

                        </div> <!-- end tab-content -->
                    </div> <!-- end intake-body -->
                </div> <!-- end intake-card -->
            </div>
        </section>

        <!-- Footer -->
        <footer class="footer reveal">
            <div class="container">
                <div class="footer-content">
                    <div class="footer-brand">
                        <div class="footer-logo">ClearMyRide</div>
                        <p class="footer-tagline">Simplifying vehicle and license renewals for Maryland drivers. Let us
                            handle the paperwork while you stay on the road.</p>
                    </div>

                    <div class="footer-contact">
                        <a href="tel:+15551234567" class="contact-item">
                            <div class="contact-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path
                                        d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z" />
                                </svg>
                            </div>
                            <span>+1 240-240-6393</span>
                        </a>

                        <a href="mailto:support@clearmyride.com" class="contact-item">
                            <div class="contact-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path
                                        d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" />
                                </svg>
                            </div>
                            <span>support@clearmyride.com</span>
                        </a>
                    </div>
                </div>

                <div class="footer-divider"></div>

                <div class="footer-bottom">
                    <div class="footer-links">
                        <a href="privacy_policy.php" target="_blank" class="footer-link">Privacy Policy</a>
                        <a href="terms.php" target="_blank" class="footer-link">Terms of Service</a>
                        <a href="#not-mva" class="footer-link">"Not the MVA" Disclosure</a>
                    </div>

                    <div class="footer-social">
                        <a href="https://www.instagram.com/clearmyride?igsh=eWl0aGx3bG9yZzBz" class="social-link" aria-label="Instagram" target="_blank">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                            </svg>
                        </a>
                        <a href="#" class="social-link" aria-label="TikTok">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z" />
                            </svg>
                        </a>
                        <a href="https://www.facebook.com/61581920192951/" target="_blank" class="social-link" aria-label="Facebook">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                            </svg>
                        </a>
                    </div>

                    <p class="copyright">© 2025 ClearMyRide (a DriveShyft company). All rights reserved.</p>
                </div>
            </div>
        </footer>

</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
<script>
(function(){
  // validators
  const validators = {
    email: v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test((v||'').trim()),
    phoneMD: v => { const digits = (v||'').replace(/\D/g,''); return digits.length >= 7 && digits.length <= 15; },
    // stricter MD plate: require at least one letter AND one digit; allow letters, digits, spaces, hyphens; 4-10 chars total
    plateMD: v => {
      if(!v) return false;
      const s = String(v).trim();
      if (/[IOQ]/i.test(s)) return false; // disallow I/O/Q for standard plates
      if (!/^[A-Za-z0-9\s\-]{4,10}$/.test(s)) return false;
      // require at least one letter & one digit
      return /[A-Za-z]/.test(s) && /\d/.test(s);
    },
    vinMD: v => {
      if(!v) return true; // optional: only validate when filled
      const s = String(v).replace(/\s+/g,'').toUpperCase();
      return /^[A-HJ-NPR-Z0-9]{17}$/.test(s);
    },
    required: v => v !== null && v !== undefined && String(v).trim() !== ''
  };

  // helper: find immediate invalid-feedback after element
  function findFB(el){
    if(!el) return null;
    let next = el.nextElementSibling;
    if(next && next.classList && next.classList.contains('invalid-feedback')) return next;
    return null;
  }

  function showError(el, msg){
    if(!el) return;
    el.classList.add('is-invalid');
    el.classList.remove('is-valid');
    const fb = findFB(el);
    if(fb) fb.textContent = msg;
  }
  function clearError(el){
    if(!el) return;
    el.classList.remove('is-invalid');
    el.classList.add('is-valid');
    const fb = findFB(el);
    if(fb) fb.textContent = '';
  }

  // vehicle form validation
  function validateVehicle(form){
    let ok = true;
    const q = sel => form.querySelector(sel);

    const full = q('[name="fullName"]');
    if(!validators.required(full?.value)){ showError(full,'Please enter your full name.'); ok=false; } else clearError(full);

    const email = q('[name="email"]');
    if(!validators.required(email?.value) || !validators.email(email?.value)){ showError(email,'Please enter a valid email (e.g. jane@example.com).'); ok=false; } else clearError(email);

    const phone = q('[name="phone"]');
    if(!validators.required(phone?.value) || !validators.phoneMD(phone.value)){ showError(phone,'Please enter a valid phone number (e.g. (410) 555-1234).'); ok=false; } else clearError(phone);

    const dob = q('[name="dob"]');
    if(!validators.required(dob?.value)){ showError(dob,'Please provide your date of birth.'); ok=false; } else clearError(dob);

    const plate = q('[name="plate"]');
    if(!validators.required(plate?.value) || !validators.plateMD(plate.value)){ showError(plate,'Enter a valid Maryland plate that includes letters and numbers (example: ABC-1234).'); ok=false; } else clearError(plate);

    const vin = q('[name="vin"]');
    if(vin && vin.value.trim() !== '' && !validators.vinMD(vin.value)){ showError(vin,'VIN must be 17 characters; letters/numbers only; avoid I, O, Q.'); ok=false; } else if(vin) clearError(vin);

    const renew = q('[name="renewWhen"]');
    if(!validators.required(renew?.value)){ showError(renew,'Please select when your renewal is due.'); ok=false; } else clearError(renew);

    // radio group v-hasIssues
    const has = form.querySelector('input[name="v-hasIssues"]:checked');
    const hasFb = document.getElementById('v-hasIssues-feedback');
    if(!has){ if(hasFb){ hasFb.classList.add('d-block'); hasFb.textContent = 'Please indicate whether you have outstanding tickets or flags.'; } ok=false; } else { if(hasFb){ hasFb.classList.remove('d-block'); hasFb.textContent=''; } }

    const delivery = q('[name="delivery"]');
    if(!validators.required(delivery?.value)){ showError(delivery,'Please select a delivery method.'); ok=false; } else clearError(delivery);

    const referral = q('[name="referral"]');
    if(!validators.required(referral?.value)){ showError(referral,'Please tell us how you heard about us.'); ok=false; } else clearError(referral);

    const consent = q('[name="consent"]');
    const consentFb = document.getElementById('v-consent-feedback');
    if(!consent || !consent.checked){ if(consentFb){ consentFb.classList.add('d-block'); consentFb.textContent = 'You must consent to allow us access to your MVA records.'; } if(consent) consent.classList.add('is-invalid'); ok=false; } else { if(consentFb){ consentFb.classList.remove('d-block'); consentFb.textContent=''; } if(consent) consent.classList.remove('is-invalid'); }

    return ok;
  }

  // license form validation
  function validateLicense(form){
    let ok = true;
    const q = sel => form.querySelector(sel);

    const full = q('[name="fullName"]');
    if(!validators.required(full?.value)){ showError(full,'Please enter your full name.'); ok=false; } else clearError(full);

    const email = q('[name="email"]');
    if(!validators.required(email?.value) || !validators.email(email.value)){ showError(email,'Please enter a valid email (e.g. jane@example.com).'); ok=false; } else clearError(email);

    const phone = q('[name="phone"]');
    if(!validators.required(phone?.value) || !validators.phoneMD(phone.value)){ showError(phone,'Please enter a valid phone number (e.g. (410) 555-1234).'); ok=false; } else clearError(phone);

    const dob = q('[name="dob"]');
    if(!validators.required(dob?.value)){ showError(dob,'Please provide your date of birth.'); ok=false; } else clearError(dob);

    const lic = q('[name="licenseNumber"]');
    if(!validators.required(lic?.value)){ showError(lic,'Please provide your driver\'s license number.'); ok=false; }
    else if(!/^[A-Z0-9\-\s]{4,20}$/i.test(lic.value.trim())){ showError(lic,'Use letters, numbers or dashes. Example: D12345678'); ok=false; } else clearError(lic);

    // radio
    const has = form.querySelector('input[name="l-hasIssues"]:checked');
    const hasFb = document.getElementById('l-hasIssues-feedback');
    if(!has){ if(hasFb){ hasFb.classList.add('d-block'); hasFb.textContent = 'Please indicate whether you have unpaid tickets or MVA holds.';} ok=false; } else { if(hasFb){ hasFb.classList.remove('d-block'); hasFb.textContent=''; } }

    const sign = q('[name="signature"]');
    if(!validators.required(sign?.value)){ showError(sign,'Please type your full name as signature.'); ok=false; } else clearError(sign);

    const consent = q('[name="consent"]');
    const consentFb = document.getElementById('l-consent-feedback');
    if(!consent || !consent.checked){ if(consentFb){ consentFb.classList.add('d-block'); consentFb.textContent='You must consent for us to access your record.'; } if(consent) consent.classList.add('is-invalid'); ok=false; } else { if(consentFb){ consentFb.classList.remove('d-block'); consentFb.textContent=''; } if(consent) consent.classList.remove('is-invalid'); }

    return ok;
  }

  // attach behavior
  document.addEventListener('DOMContentLoaded', function(){
    const vForm = document.getElementById('vehicle-form');
    const lForm = document.getElementById('license-form');

    function attachClear(form){
      form.querySelectorAll('input,select,textarea').forEach(el=>{
        el.addEventListener('input', ()=> { el.classList.remove('is-invalid'); const fb = findFB(el); if(fb) fb.textContent=''; });
        el.addEventListener('change', ()=> { el.classList.remove('is-invalid'); const fb = findFB(el); if(fb) fb.textContent=''; });
      });
      form.querySelectorAll('input[type="radio"], input[type="checkbox"]').forEach(el=>{
        el.addEventListener('change', ()=> {
          // clear associated feedback boxes with known ids
          const name = el.name;
          const fb = document.getElementById(name+'-feedback') || document.getElementById('v-hasIssues-feedback') || document.getElementById('l-hasIssues-feedback');
          if(fb){ fb.classList.remove('d-block'); fb.textContent=''; }
        });
      });
    }

    if(vForm){
      attachClear(vForm);
      vForm.addEventListener('submit', function(e){
        // clear prior group messages
        const hasFb = document.getElementById('v-hasIssues-feedback'); if(hasFb){ hasFb.classList.remove('d-block'); hasFb.textContent=''; }
        const consFb = document.getElementById('v-consent-feedback'); if(consFb){ consFb.classList.remove('d-block'); consFb.textContent=''; }

        if(!validateVehicle(vForm)){
          e.preventDefault(); e.stopPropagation();
          const firstInvalid = vForm.querySelector('.is-invalid, .invalid-feedback.d-block');
          if(firstInvalid) firstInvalid.scrollIntoView({behavior:'smooth', block:'center'});
        }
      }, false);
    }

    if(lForm){
      attachClear(lForm);
      lForm.addEventListener('submit', function(e){
        const hasFb = document.getElementById('l-hasIssues-feedback'); if(hasFb){ hasFb.classList.remove('d-block'); hasFb.textContent=''; }
        const consFb = document.getElementById('l-consent-feedback'); if(consFb){ consFb.classList.remove('d-block'); consFb.textContent=''; }

        if(!validateLicense(lForm)){
          e.preventDefault(); e.stopPropagation();
          const firstInvalid = lForm.querySelector('.is-invalid, .invalid-feedback.d-block');
          if(firstInvalid) firstInvalid.scrollIntoView({behavior:'smooth', block:'center'});
        }
      }, false);
    }
  });
})();
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // small helper to restore a button (exposed for AJAX)
  window.restoreSubmitButton = function(btn) {
    if (!btn) return;
    try {
      btn.dataset.loading = 'false';
      btn.classList.remove('btn-loading');
      if (btn.tagName.toLowerCase() === 'input') {
        btn.value = btn.dataset.origHtml || 'Submit';
      } else {
        btn.innerHTML = btn.dataset.origHtml || '';
      }
      btn.disabled = false;
      if (btn.hasAttribute('data-fixed-width')) {
        btn.style.width = '';
        btn.removeAttribute('data-fixed-width');
      }
    } catch (err) {
      console.error('restoreSubmitButton error', err);
    }
  };

  // Mark the clicked submit button so submit handler knows which button started submit.
  document.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function(btn) {
    btn.addEventListener('click', function() {
      // clear clicked flag on all buttons in same form
      if (btn.form) {
        btn.form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(b => b.dataset.clicked = 'false');
      }
      btn.dataset.clicked = 'true';
    }, {passive:true});
  });

  // Attach submit handlers for all forms
  document.querySelectorAll('form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
      try {
        // If an earlier handler (your validators) called preventDefault(), stop — do not start loader.
        if (e.defaultPrevented) {
          return;
        }

        // find clicked button (or fallback to first submit in the form)
        var submitBtn = form.querySelector('button[type="submit"][data-clicked="true"], input[type="submit"][data-clicked="true"]')
                     || form.querySelector('button[type="submit"], input[type="submit"]');
        if (!submitBtn) return;

        // Prevent double work if already loading
        if (submitBtn.dataset.loading === 'true') {
          // already loading — prevent further submit
          e.preventDefault();
          return;
        }

        // record original content & set fixed width to avoid layout jump
        if (!submitBtn.dataset.origHtml) submitBtn.dataset.origHtml = submitBtn.innerHTML || submitBtn.value || '';
        if (!submitBtn.hasAttribute('data-fixed-width')) {
          // compute rendered width and fix it so changing text doesn't shift layout
          var rect = submitBtn.getBoundingClientRect();
          submitBtn.style.width = Math.ceil(rect.width) + 'px';
          submitBtn.setAttribute('data-fixed-width', '1');
        }

        // apply loading state
        submitBtn.dataset.loading = 'true';
        submitBtn.classList.add('btn-loading');

        // detect whether this is an <input> or <button> and set the loading text accordingly
        var isInput = submitBtn.tagName.toLowerCase() === 'input';
        var spinnerHTML = '<span class="spinner" aria-hidden="true"></span>';
        var loadingText = 'Processing...';

        if (isInput) {
          // input[type=submit] uses value
          submitBtn.value = loadingText;
        } else {
          // button element: replace innerHTML with spinner + text
          submitBtn.innerHTML = spinnerHTML + loadingText;
        }

        // disable the button to prevent double clicks
        submitBtn.disabled = true;

        // safety fallback: if form doesn't navigate away within 30s, re-enable and restore
        var fallback = setTimeout(function() {
          if (submitBtn && submitBtn.dataset && submitBtn.dataset.loading === 'true') {
            window.restoreSubmitButton(submitBtn);
          }
        }, 30000);

        // if client-side validation fails the browser will fire 'invalid' events.
        // listen once for invalid events; if they occur, restore the button so user can fix inputs.
        var invalidHandler = function(ev) {
          // only restore if validation prevented submission
          window.restoreSubmitButton(submitBtn);
          form.removeEventListener('invalid', invalidHandler, true);
        };
        form.addEventListener('invalid', invalidHandler, true);

        // If you use AJAX for submission, call window.restoreSubmitButton(submitBtn) on failure.

      } catch (err) {
        // if anything goes wrong, don't block the form — restore button
        console.error('Submit loader failure', err);
      }
    }, false);
  });
});
</script>

</html>
<?php show_flash(); ?>