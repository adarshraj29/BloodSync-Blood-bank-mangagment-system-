<?php
// ============================================================
// BloodSync - Modern Landing Page
// ============================================================
session_start();

// Include DB if available, else suppress errors for landing page
$db_path = __DIR__ . '/config/db.php';
try {
    if (file_exists($db_path)) {
        include_once $db_path;
    }
} catch (Throwable $e) {
    // MySQL is offline or connection refused.
    $conn = null;
}

// Redirect logged-in users to their respective dashboards
if (isset($_SESSION['user_id'])) {
    $loc = ($_SESSION['role'] ?? '') === 'admin' ? '/blood_bank/admin/dashboard.php' : '/blood_bank/user/dashboard.php';
    header("Location: $loc");
    exit();
}

// Default Stats (Fallback)
$stats = [
    'donors' => 1250,
    'lives_saved' => 3700,
    'units' => 850,
    'requests' => 45
];

$bgs = [];

// Try fetching real data if DB is connected
if (isset($conn) && !$conn->connect_error) {
    try {
        $stats['donors'] = (int)@$conn->query("SELECT COUNT(*) as c FROM users WHERE role_type='donor'")->fetch_assoc()['c'];
        $stats['requests'] = (int)@$conn->query("SELECT COUNT(*) as c FROM blood_requests WHERE status='approved'")->fetch_assoc()['c'];
        $stats['units'] = (int)@$conn->query("SELECT SUM(units) as c FROM blood_stock")->fetch_assoc()['c'];
        $stats['lives_saved'] = $stats['requests'] * 3;
        
        // Fetch upcoming camps
        $camps_query = @$conn->query("SELECT * FROM donation_camps WHERE date >= CURDATE() ORDER BY date ASC LIMIT 4");

        // Fetch Live Blood Stock
        $stock_res = @$conn->query("SELECT bg.name as grp, bs.units as qty FROM blood_stock bs JOIN blood_groups bg ON bs.blood_group_id=bg.id ORDER BY bg.id");
        if($stock_res) {
            while($row = $stock_res->fetch_assoc()) {
                $qty = (int)$row['qty'];
                $pct = min(100, $qty * 5); // Visual scale
                if($qty >= 10) {
                    $color = '#2ecc71'; $status = 'Good'; $scls = 'status-good';
                } elseif($qty >= 4) {
                    $color = '#f1c40f'; $status = 'Low'; $scls = 'status-low';
                } else {
                    $color = '#e74c3c'; $status = 'Critical'; $scls = 'status-critical';
                }
                $bgs[] = ['grp'=>$row['grp'], 'qty'=>$qty, 'pct'=>$pct, 'color'=>$color, 'status'=>$status, 'scls'=>$scls];
            }
        }
    } catch (Throwable $e) {
        // Fallback to static if tables missing
    }
}

// Fallback blood groups if DB is empty or off
if(empty($bgs)) {
    $bgs = [
        ['grp'=>'A+', 'qty'=>45, 'pct'=>80, 'color'=>'#2ecc71', 'status'=>'Good', 'scls'=>'status-good'],
        ['grp'=>'O+', 'qty'=>12, 'pct'=>30, 'color'=>'#f1c40f', 'status'=>'Low', 'scls'=>'status-low'],
        ['grp'=>'B+', 'qty'=>56, 'pct'=>90, 'color'=>'#2ecc71', 'status'=>'Good', 'scls'=>'status-good'],
        ['grp'=>'AB+', 'qty'=>4,  'pct'=>15, 'color'=>'#e74c3c', 'status'=>'Critical', 'scls'=>'status-critical'],
        ['grp'=>'A-', 'qty'=>8,  'pct'=>25, 'color'=>'#e74c3c', 'status'=>'Critical', 'scls'=>'status-critical'],
        ['grp'=>'O-', 'qty'=>22, 'pct'=>50, 'color'=>'#f1c40f', 'status'=>'Low', 'scls'=>'status-low'],
        ['grp'=>'B-', 'qty'=>15, 'pct'=>35, 'color'=>'#f1c40f', 'status'=>'Low', 'scls'=>'status-low'],
        ['grp'=>'AB-', 'qty'=>2,  'pct'=>10, 'color'=>'#e74c3c', 'status'=>'Critical', 'scls'=>'status-critical']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BloodSync | Connecting Donors, Saving Lives</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Custom Landing CSS -->
    <link rel="stylesheet" href="/blood_bank/assets/css/landing.css">
</head>
<body>

    <!-- Background Elements -->
    <div class="landing-bg"></div>
    <div class="particles" id="particles-js"></div>

    <!-- ================= NAVBAR ================= -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-droplet-fill"></i> BloodSync
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" style="border-color: rgba(192,57,43,0.5); filter: invert(0.8);">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#live-stock">Blood Stock</a></li>
                    <li class="nav-item"><a class="nav-link" href="#camps">Blood Camps</a></li>
                    <li class="nav-item"><a class="nav-link" href="#why-donate">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                </ul>
                <div class="d-flex align-items-center gap-3 mt-3 mt-lg-0">
                    <button class="btn btn-link p-0 text-decoration-none" id="themeToggle" style="color:var(--text-color); font-size: 1.2rem;">
                        <i class="bi bi-sun" id="themeIcon"></i>
                    </button>
                    <a href="/blood_bank/auth/login.php" class="btn btn-login">Login</a>
                    <a href="/blood_bank/auth/register.php" class="btn btn-register">Sign Up</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- ================= HERO SECTION ================= -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right">
                    <h1 class="hero-title">Connecting Donors,<br>Saving Lives <i class="bi bi-heart-fill text-danger" style="font-size: 3rem; animation: heartbeat 1.5s infinite;"></i></h1>
                    <p class="hero-subtitle">BloodSync is a smart digital blood bank management system that helps donors, receivers, hospitals, and blood banks connect efficiently during emergencies.</p>
                    <div class="hero-buttons">
                        <a href="/blood_bank/auth/register.php?type=donor" class="btn btn-glow"><i class="bi bi-droplet-fill me-2"></i>Donate Blood</a>
                        <a href="/blood_bank/auth/register.php?type=receiver" class="btn btn-outline-glow ms-lg-3 mt-3 mt-lg-0"><i class="bi bi-search me-2"></i>Request Blood</a>
                    </div>
                </div>
                <div class="col-lg-6 mt-5 mt-lg-0" data-aos="fade-left">
                    <div class="hero-illustration">
                        <div class="pulse-ring"></div>
                        <div class="pulse-ring"></div>
                        <img src="https://cdn-icons-png.flaticon.com/512/3063/3063205.png" alt="Blood Bag" class="floating-bag" style="max-width: 300px; display: block; margin: 0 auto;">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= LIVE BLOOD STOCK ================= -->
    <section id="live-stock" class="section-padding">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Live <span>Blood Stock</span></h2>
            
            <div class="glass-card" data-aos="zoom-in">
                <div class="blood-group-grid">
                    <?php foreach($bgs as $bg): ?>
                    <div class="stock-card">
                        <div class="progress-ring" data-percent="<?= $bg['pct'] ?>" style="--percent: 0%; --fill-color: <?= $bg['color'] ?>;">
                            <span style="color: <?= $bg['color'] ?>;"><?= $bg['grp'] ?></span>
                        </div>
                        <div class="text-muted small mb-1"><?= $bg['qty'] ?> Units Available</div>
                        <div class="status-badge <?= $bg['scls'] ?>"><?= $bg['status'] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= MAP & CAMPS ================= -->
    <section id="camps" class="section-padding">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Blood Donation <span>Camps</span></h2>
            
            <div class="row align-items-center">
                <div class="col-lg-5 mb-4 mb-lg-0" data-aos="fade-right">
                    <div class="map-container">
                        <!-- Simple abstract India/Bihar representation using SVG -->
                        <svg viewBox="0 0 500 500" class="india-map">
                            <path d="M250,50 Q300,100 350,150 T400,300 Q350,450 250,450 Q150,450 100,300 T150,150 Q200,100 250,50 Z" />
                            <!-- Bihar Highlighted -->
                            <path class="bihar" d="M300,200 Q330,190 350,220 Q360,250 320,260 Q280,240 300,200 Z" />
                            
                            <!-- Location Marker -->
                            <g transform="translate(320, 220)">
                                <path d="M0,0 C-10,-10 -15,-20 -15,-30 A15,15 0 1,1 15,-30 C15,-20 10,-10 0,0 Z" fill="#fff"/>
                                <circle cx="0" cy="-30" r="6" fill="#c0392b"/>
                            </g>
                        </svg>
                    </div>
                </div>
                <div class="col-lg-7" data-aos="fade-left">
                    <div class="glass-card camp-list">
                        <?php 
                        if (isset($camps_query) && $camps_query && $camps_query->num_rows > 0): 
                            while($camp = $camps_query->fetch_assoc()):
                        ?>
                        <div class="camp-item">
                            <div>
                                <h5 class="mb-1 fw-bold"><?= htmlspecialchars($camp['camp_name']) ?></h5>
                                <div class="text-muted small"><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= htmlspecialchars($camp['city'] ?? '') ?> - <?= htmlspecialchars($camp['address'] ?? '') ?></div>
                                <div class="text-muted small mt-1"><i class="bi bi-calendar-event me-1"></i><?= date('d M Y', strtotime($camp['date'])) ?>, <?= date('h:i A', strtotime($camp['start_time'])) ?> - <?= date('h:i A', strtotime($camp['end_time'])) ?></div>
                                <div class="text-muted small mt-1"><i class="bi bi-person-fill me-1"></i>Organizer: <?= htmlspecialchars($camp['organizer'] ?? 'Unknown') ?></div>
                            </div>
                        </div>
                        <?php endwhile; else: ?>
                        <!-- Fallback static camp -->
                        <div class="camp-item">
                            <div>
                                <h5 class="mb-1 fw-bold">Patna Mega Blood Drive</h5>
                                <div class="text-muted small"><i class="bi bi-geo-alt-fill text-danger me-1"></i>Patna - Gandhi Maidan Central</div>
                                <div class="text-muted small mt-1"><i class="bi bi-calendar-event me-1"></i>15 Jun 2026, 10:00 AM - 05:00 PM</div>
                                <div class="text-muted small mt-1"><i class="bi bi-person-fill me-1"></i>Organizer: Red Cross Society</div>
                            </div>
                        </div>
                        <div class="camp-item">
                            <div>
                                <h5 class="mb-1 fw-bold">Begusarai Lifeline Camp</h5>
                                <div class="text-muted small"><i class="bi bi-geo-alt-fill text-danger me-1"></i>Begusarai - Civil Hospital Premises</div>
                                <div class="text-muted small mt-1"><i class="bi bi-calendar-event me-1"></i>20 Jun 2026, 09:00 AM - 03:00 PM</div>
                                <div class="text-muted small mt-1"><i class="bi bi-person-fill me-1"></i>Organizer: Bihar Voluntary Donors Org.</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= WHY DONATE ================= -->
    <section id="why-donate" class="section-padding">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Why <span>Donate Blood?</span></h2>
            
            <div class="row g-4">
                <div class="col-md-3" data-aos="fade-up" data-aos-delay="100">
                    <div class="glass-card text-center h-100">
                        <div class="feature-icon mx-auto"><i class="bi bi-heart-pulse"></i></div>
                        <h5 class="fw-bold">Save Lives</h5>
                        <p class="text-muted small mb-0">A single donation can save up to three lives in emergency situations.</p>
                    </div>
                </div>
                <div class="col-md-3" data-aos="fade-up" data-aos-delay="200">
                    <div class="glass-card text-center h-100">
                        <div class="feature-icon mx-auto"><i class="bi bi-shield-check"></i></div>
                        <h5 class="fw-bold">Health Benefits</h5>
                        <p class="text-muted small mb-0">Regular donation stimulates the production of new blood cells.</p>
                    </div>
                </div>
                <div class="col-md-3" data-aos="fade-up" data-aos-delay="300">
                    <div class="glass-card text-center h-100">
                        <div class="feature-icon mx-auto"><i class="bi bi-hospital"></i></div>
                        <h5 class="fw-bold">Emergency Support</h5>
                        <p class="text-muted small mb-0">Hospitals critically depend on voluntary donations for surgeries.</p>
                    </div>
                </div>
                <div class="col-md-3" data-aos="fade-up" data-aos-delay="400">
                    <div class="glass-card text-center h-100">
                        <div class="feature-icon mx-auto"><i class="bi bi-people"></i></div>
                        <h5 class="fw-bold">Community</h5>
                        <p class="text-muted small mb-0">Join a network of heroes who step up when society needs them most.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= LIVE STATS COUNTER ================= -->
    <section class="section-padding py-0">
        <div class="container" data-aos="zoom-in">
            <div class="stats-container row g-4">
                <div class="col-6 col-md-3 stat-item">
                    <span class="stat-number" data-target="<?= $stats['donors'] ?>">0</span>
                    <div class="fw-semibold opacity-75">Registered Donors</div>
                </div>
                <div class="col-6 col-md-3 stat-item">
                    <span class="stat-number" data-target="<?= $stats['lives_saved'] ?>">0</span>
                    <div class="fw-semibold opacity-75">Lives Saved</div>
                </div>
                <div class="col-6 col-md-3 stat-item">
                    <span class="stat-number" data-target="<?= $stats['units'] ?>">0</span>
                    <div class="fw-semibold opacity-75">Units Available</div>
                </div>
                <div class="col-6 col-md-3 stat-item">
                    <span class="stat-number" data-target="<?= $stats['requests'] ?>">0</span>
                    <div class="fw-semibold opacity-75">Fulfilled Requests</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= TOP DONORS ================= -->
    <section class="section-padding">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Hero <span>Donors</span></h2>
            
            <div class="row justify-content-center g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="glass-card donor-card silver">
                        <i class="bi bi-award-fill badge-icon"></i>
                        <img src="https://ui-avatars.com/api/?name=Priya+S&background=random" alt="Donor" class="donor-avatar">
                        <h4 class="fw-bold">Priya Sharma</h4>
                        <p class="text-muted mb-2">18 Donations</p>
                        <span class="badge bg-secondary">Silver Donor</span>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200" style="transform: translateY(-20px);">
                    <div class="glass-card donor-card gold" style="border-color: #f1c40f;">
                        <i class="bi bi-trophy-fill badge-icon"></i>
                        <img src="https://ui-avatars.com/api/?name=Rahul+K&background=random" alt="Donor" class="donor-avatar">
                        <h4 class="fw-bold text-warning">Rahul Kumar</h4>
                        <p class="text-muted mb-2">32 Donations</p>
                        <span class="badge bg-warning text-dark">Gold Donor</span>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="glass-card donor-card bronze">
                        <i class="bi bi-award-fill badge-icon"></i>
                        <img src="https://ui-avatars.com/api/?name=Amit+P&background=random" alt="Donor" class="donor-avatar">
                        <h4 class="fw-bold">Amit Patel</h4>
                        <p class="text-muted mb-2">12 Donations</p>
                        <span class="badge bg-warning bg-opacity-50 text-dark">Bronze Donor</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= SECURITY & FEATURES ================= -->
    <section class="section-padding py-0">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-5 mb-lg-0" data-aos="fade-right">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="glass-card p-4 text-center h-100">
                                <i class="bi bi-shield-lock text-danger fs-1 mb-3"></i>
                                <h6 class="fw-bold">OTP Verification</h6>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="glass-card p-4 text-center h-100">
                                <i class="bi bi-graph-up text-danger fs-1 mb-3"></i>
                                <h6 class="fw-bold">Smart Analytics</h6>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="glass-card p-4 text-center h-100">
                                <i class="bi bi-bell text-danger fs-1 mb-3"></i>
                                <h6 class="fw-bold">Real-Time Alerts</h6>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="glass-card p-4 text-center h-100">
                                <i class="bi bi-search text-danger fs-1 mb-3"></i>
                                <h6 class="fw-bold">Smart Matching</h6>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5 offset-lg-1" data-aos="fade-left">
                    <h2 class="heading-font fw-bold mb-4">Advanced <span class="text-danger">Features</span></h2>
                    <p class="text-muted mb-4">BloodSync is engineered with modern security and smart algorithms to ensure every drop of blood reaches the right patient at the right time.</p>
                    <ul class="list-unstyled">
                        <li class="mb-3"><i class="bi bi-check-circle-fill text-danger me-2"></i> End-to-end secure user authentication.</li>
                        <li class="mb-3"><i class="bi bi-check-circle-fill text-danger me-2"></i> Comprehensive admin dashboard for inventory management.</li>
                        <li class="mb-3"><i class="bi bi-check-circle-fill text-danger me-2"></i> Automated activity logging and tracking.</li>
                        <li class="mb-3"><i class="bi bi-check-circle-fill text-danger me-2"></i> Location-based blood camp highlighting.</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= DASHBOARD MOCKUP ================= -->
    <section class="section-padding">
        <div class="container text-center" data-aos="zoom-in-up">
            <h2 class="section-title">Powerful <span>Dashboards</span></h2>
            <div class="mockup-frame d-inline-block w-100" style="max-width: 900px; padding: 5px; border-radius: 12px; border: 1px solid rgba(192,57,43,0.3); background: #0a0505;">
                <div id="dashboardCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="3000">
                    <div class="carousel-inner rounded" style="border-radius: 8px !important;">
                        <!-- The 5 screenshots from the user, copied to assets/img -->
                        <div class="carousel-item active">
                            <img src="/blood_bank/assets/img/media__1778924587790.png" class="d-block w-100" alt="Admin Dashboard Overview">
                        </div>
                        <div class="carousel-item">
                            <img src="/blood_bank/assets/img/media__1778924587861.png" class="d-block w-100" alt="User Achievements & Stock">
                        </div>
                        <div class="carousel-item">
                            <img src="/blood_bank/assets/img/media__1778924587503.png" class="d-block w-100" alt="Admin Hospitals View">
                        </div>
                        <div class="carousel-item">
                            <img src="/blood_bank/assets/img/media__1778924587654.png" class="d-block w-100" alt="Admin Blood Stock Management">
                        </div>
                        <div class="carousel-item">
                            <img src="/blood_bank/assets/img/media__1778911759060.png" class="d-block w-100" alt="User Camps View">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= TESTIMONIALS ================= -->
    <section class="section-padding py-0">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">What People <span>Say</span></h2>
            
            <div class="row justify-content-center" data-aos="fade-up">
                <div class="col-lg-8">
                    <div id="testimonialCarousel" class="carousel slide glass-card testimonial-carousel" data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <div class="carousel-item active">
                                <i class="bi bi-quote quote-icon"></i>
                                <p class="testimonial-text">"BloodSync made it incredibly easy to find O- blood during my father's surgery. The emergency response was phenomenally fast!"</p>
                                <h5 class="fw-bold text-danger mb-0">Vikash Singh</h5>
                                <small class="text-muted">Receiver</small>
                            </div>
                            <div class="carousel-item">
                                <i class="bi bi-quote quote-icon"></i>
                                <p class="testimonial-text">"As a regular donor, the platform keeps me updated on upcoming camps and my donation history. A brilliant initiative."</p>
                                <h5 class="fw-bold text-danger mb-0">Neha Gupta</h5>
                                <small class="text-muted">Gold Donor</small>
                            </div>
                            <div class="carousel-item">
                                <i class="bi bi-quote quote-icon"></i>
                                <p class="testimonial-text">"Our hospital inventory management has become seamless. The smart matching algorithm saves critical time."</p>
                                <h5 class="fw-bold text-danger mb-0">Dr. Sharma</h5>
                                <small class="text-muted">City Hospital Director</small>
                            </div>
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="prev" style="width: 50px;">
                            <span class="carousel-control-prev-icon" aria-hidden="true" style="filter: invert(1) sepia(1) saturate(5) hue-rotate(300deg);"></span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="next" style="width: 50px;">
                            <span class="carousel-control-next-icon" aria-hidden="true" style="filter: invert(1) sepia(1) saturate(5) hue-rotate(300deg);"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= TOAST NOTIFICATION PREVIEW ================= -->
    <div class="toast-container">
        <div class="custom-toast" id="demoToast">
            <div class="toast-icon">
                <i class="bi bi-check-lg"></i>
            </div>
            <div>
                <h6 class="mb-0 fw-bold">Donation Successful</h6>
                <small class="text-muted">Thank you for saving a life today!</small>
            </div>
        </div>
    </div>

    <!-- ================= FOOTER & CONTACT ================= -->
    <footer id="contact" class="footer">
        <div class="container">
            <div class="row g-4 mb-5">
                <div class="col-lg-4">
                    <h3 class="footer-title text-danger"><i class="bi bi-droplet-fill"></i> BloodSync</h3>
                    <p class="text-muted small pe-lg-4">A smart digital blood bank management system bridging the gap between blood donors, receivers, and hospitals during critical emergencies.</p>
                </div>
                <div class="col-lg-2 col-6">
                    <h5 class="fw-bold text-white mb-3">Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#camps">Donation Camps</a></li>
                        <li><a href="#why-donate">About Us</a></li>
                        <li><a href="/blood_bank/auth/login.php">Login</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-6">
                    <h5 class="fw-bold text-white mb-3">Legal</h5>
                    <ul class="footer-links">
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms of Service</a></li>
                        <li><a href="#">Security</a></li>
                    </ul>
                </div>
                <div class="col-lg-4">
                    <h5 class="fw-bold text-white mb-3">Emergency Support</h5>
                    <div class="helpline-box d-flex align-items-center gap-3">
                        <i class="bi bi-telephone-fill"></i>
                        <div>
                            <div class="fw-bold text-white">All India Blood Helpline</div>
                            <div class="text-danger fw-bold fs-5">8097091234</div>
                        </div>
                    </div>
                    <div class="helpline-box d-flex align-items-center gap-3">
                        <i class="bi bi-envelope-fill"></i>
                        <div>
                            <div class="fw-bold text-white">Admin Contact</div>
                            <div class="text-danger">support@bloodsync.in</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-center pt-4 border-top" style="border-color: rgba(255,255,255,0.1) !important;">
                <p class="text-muted small mb-0">&copy; 2026 BloodSync. All rights reserved. BCA Final Year Project.</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <!-- Optional Particles.js for background effect -->
    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
    
    <script src="/blood_bank/assets/js/landing.js"></script>
    
    <script>
        // Init minimal particles
        if(typeof particlesJS !== 'undefined') {
            particlesJS("particles-js", {
                "particles": {
                    "number": { "value": 30 },
                    "color": { "value": "#e74c3c" },
                    "shape": { "type": "circle" },
                    "opacity": { "value": 0.5, "random": true },
                    "size": { "value": 4, "random": true },
                    "line_linked": { "enable": false },
                    "move": { "enable": true, "speed": 1, "direction": "top", "out_mode": "out" }
                },
                "interactivity": { "events": { "onhover": { "enable": false } } }
            });
        }
    </script>
</body>
</html>
