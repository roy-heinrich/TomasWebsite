<?php
require_once 'config.php';

// Set default values
$telephone = $email = $fb_page = $fb_link = $address = 'Not available';
$teacher_name = $teacher_title = $teacher_image = '';

// Fetch latest contact info from PostgreSQL
try {
    $stmt = $conn->query("SELECT * FROM contact_info ORDER BY date_updated DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $telephone = htmlspecialchars($row['telephone_primary']);
        $email = htmlspecialchars($row['email_general']);
        $fb_page = htmlspecialchars($row['fb_page']);
        $fb_link = htmlspecialchars($row['fb_link']);
        $address = htmlspecialchars($row['address']);
    }
} catch (PDOException $e) {
    // Use defaults if error
}

// Fetch welcome message (teacher)
try {
    $welcome_stmt = $conn->query("SELECT * FROM welcome_message LIMIT 1");
    $welcome = $welcome_stmt->fetch(PDO::FETCH_ASSOC);
    if ($welcome) {
        $teacher_name = htmlspecialchars($welcome['teacher_name']);
        $teacher_title = htmlspecialchars($welcome['teacher_title']);
        if (!empty($welcome['teacher_image'])) {
            if (strpos($welcome['teacher_image'], 'http') === 0) {
                $teacher_image = $welcome['teacher_image'];
            } else {
                $teacher_image = getSupabaseUrl($welcome['teacher_image'], 'welc_profile');
            }
        } else {
            $teacher_image = "https://ui-avatars.com/api/?name=" . urlencode($teacher_name) . "&size=200&background=random";
        }
    }
} catch (PDOException $e) {
    // Use defaults if error
    $teacher_image = "https://ui-avatars.com/api/?name=Teacher&size=200&background=random";
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Page</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/boxicons/2.1.4/css/boxicons.min.css">
    <style>
      /* Animation classes */
    .animate-on-scroll {
        opacity: 0;
        transform: translateY(30px);
        transition: opacity 0.8s ease, transform 0.8s ease;
    }
    
    .animate-on-scroll.fade-in {
        opacity: 1;
        transform: translateY(0);
    }
    
    .slide-in-left {
        transform: translateX(-50px);
    }
    
    .slide-in-right {
        transform: translateX(50px);
    }
    
    .slide-in-left.fade-in, .slide-in-right.fade-in {
        transform: translateX(0);
    }
    
    .delayed-animation {
        transition-delay: 0.3s;
    }
    
    .delayed-animation-2 {
        transition-delay: 0.6s;
    }
    
    /* Prevent overflow issues on mobile */
    @media (max-width: 768px) {
        .animate-on-scroll {
            transform: none !important;
            transition: opacity 0.8s ease;
        }
    }


        .main-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 11;
    background: rgb(255, 255, 255, 0.9);
    transition: 0.3s background;
}
   /*Navigation bar bg color */
  .fixed-header .main-header {
    background:rgb(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    box-shadow: 0 0.125rem 0.25rem rgba(55,55,55, 0.07);
}

@media (min-width: 992px) and (max-width: 1399px) {
  .main-header .navbar-nav > li {
    padding: 0 8px;
  }
  
  .main-header .navbar-nav > li > .nav-link {
    font-size: 13px;
  }

  .navbar-brand img {
    width: 50px;
    height: 50px;
  }

  .school-name,
  .subtext {
    font-size: 0.65rem;
  }

  /* Adjust dropdown menu */
  .main-header .navbar-nav .dropdown-menu {
    min-width: 200px;
  }

  /* Hide admin login on medium screens */
  .nav-item.d-none.d-lg-block {
    display: none !important;
  }

  /* Show Admin link in dropdown */
  .main-header .navbar-nav .dropdown-menu .d-lg-none {
    display: block !important;
  }

  /* Adjust search form */
  .form-control {
    width: 170px;
    padding: 0.25rem 0.5rem;
  }

  .btn-outline-success {
    padding: 0.25rem 0.5rem;
  }
}

/* Additional adjustment for smaller large screens */
@media (min-width: 992px) and (max-width: 1200px) {
  .main-header .navbar-nav > li {
    padding: 0 6px;
  }
  
  .main-header .navbar-nav > li > .nav-link {
    font-size: 12px;
  }
  .form-control {
    width: 170px;
}
}

 
/* Contact Section Styles */
    .contact-section {       
        background: linear-gradient(135deg, #0946a2ff 0%, #460dccff 100%); 
        margin-top: 100px;
        padding-top: 4rem;
        padding-bottom: 6rem;
    }
    
    /* Card Header Styles */
    .contact-card {
        border-radius: 0.75rem;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        overflow: hidden;
        height: 100%;
    }
    
    .contact-card-header {
        background: linear-gradient(135deg, #0f318f 0%, #1a56e2 100%);
        color: white;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .contact-card-header h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .contact-card-body {
        padding: 1.5rem;
        background: white;
    }
    
    /* Contact Info List */
    .contact-info-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .contact-info-list li {
        padding: 0.75rem 0;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        display: flex;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .contact-info-list li:last-child {
        border-bottom: none;
    }
    
    .contact-info-list i {
        font-size: 1.25rem;
        color: rgb(15, 49, 143);
        margin-top: 0.25rem;
        flex-shrink: 0;
    }
    
    .contact-info-list strong {
        color: #333;
        font-weight: 600;
    }
    
    .contact-info-list a {
        color: rgb(15, 49, 143);
        text-decoration: none;
        transition: all 0.2s;
    }
    
    .contact-info-list a:hover {
        color: #0a2d7a;
        text-decoration: underline;
    }
    
    /* Teacher Message Card */
    .teacher-card {
        border-radius: 0.75rem;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        overflow: hidden;
        height: 100%;
    }
    
    .teacher-card-header {
        background: linear-gradient(135deg, #0f318f 0%, #1a56e2 100%);
        color: white;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .teacher-card-header h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .teacher-card-body {
        padding: 1.5rem;
        background: white;
    }
    
    .teacher-message {
        font-size: 0.95rem;
        line-height: 1.6;
        color: #444;
        margin-bottom: 1.5rem;
       font-style: italic;
    }
    
    .teacher-profile {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-top: 1.5rem;
    }
    
    .teacher-profile img {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(15, 49, 143, 0.2);
    }
    
    .teacher-profile-text strong {
        color: #333;
        font-weight: 600;
        display: block;
        margin-bottom: 0.25rem;
    }
    
    .teacher-profile-text small {
        color: #666;
        font-size: 0.85rem;
    }
    
    /* Map Card */
    .map-card {
        border-radius: 0.75rem;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        overflow: hidden;
        height: 100%;
    }
    
    .map-card-header {
        background: linear-gradient(135deg, #0f318f 0%, #1a56e2 100%);
        color: white;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .map-card-header h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .map-card-body {
        padding: 0;
        background: white;
        height: calc(100% - 56px); /* Subtract header height */
    }
    
    .map-container {
        width: 100%;
        height: 100%;
    }
    
    .map-container iframe {
        width: 100%;
        height: 100%;
        border: none;
        display: block;
    }
    
    /* Responsive Adjustments */
    @media (max-width: 991.98px) {
        .contact-section {
            padding-top: 3rem;
            padding-bottom: 5rem;
        }
        
        .contact-card, 
        .teacher-card,
        .map-card {
            margin-bottom: 1.5rem;
        }
        
        .map-card-body {
            height: 300px; /* Fixed height for mobile */
        }
    }
    
    @media (max-width: 767.98px) {
        .contact-card-header h3,
        .teacher-card-header h3,
        .map-card-header h3 {
            font-size: 1.1rem;
        }
        
        .contact-card-body,
        .teacher-card-body {
            padding: 1.25rem;
        }
        
        .contact-info-list li {
            padding: 0.5rem 0;
            font-size: 0.9rem;
        }
        
        .teacher-message {
            font-size: 0.9rem;
        }
        
        .teacher-profile img {
            width: 50px;
            height: 50px;
        }
    }



    /* Organizational Chart Section Padding */
.contact-section {
  padding-top: 4rem;
  padding-bottom: 5rem; /* Increased bottom padding for desktop */
}

@media (max-width: 768px) {
  .contact-section {
    padding-top: 3rem;
    padding-bottom: 4rem; /* Slightly less padding on mobile */
  }
}

/* Adjust the container padding if needed */
.contact-section .container {
  padding-bottom: 5rem; /* Additional space inside container */
}

@media (max-width: 768px) {
  .contact-section .container {
    padding-bottom: 3rem;
  }
}

 /* Updated Search Bar Styles */
    .search-form {
        display: flex;
        align-items: center;
    }
    
    .search-input {
        border-radius: 50px 0 0 50px !important;
        border-right: none !important;
        padding: 0.375rem 1rem !important;
        height: 40px; /* Match original height */
        width: 185px; /* Match original width */
        transition: all 0.3s;
        border: 1px solid #0d6efd !important; /* Blue outline */
    }
    
    .search-input:focus {
        box-shadow: none;
        border-color: #ced4da;
    }
    
    .search-btn {
        border-radius: 0 50px 50px 0 !important;
        border-left: none !important;
        background-color: #1821d7ff;
        color: white;
        width: 38px; /* Match button height */
        height: 40px; /* Match input height */
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        transition: all 0.3s;
          border: 1px solid #0d6efd !important; /* Blue outline */
    }
    
    .search-btn:hover {
        background-color: #152095ff;
        color: white;
    }
    
    .search-btn i {
        font-size: 1.1rem;
    }
    
    /* Adjustments for medium screens */
    @media (min-width: 992px) and (max-width: 1399px) {
        .search-input {
            width: 150px;
            padding: 0.25rem 0.75rem !important;
        }
    }
    
    /* Mobile view adjustments */
    @media (max-width: 991.98px) {
        .search-form {
            margin-top: 1rem;
            width: 100%;
              display: flex;
        justify-content: center; /* ✅ Center horizontally */
        }
        
        .search-input {
            width: 100%;
            flex-grow: 1;
        }
        
        .search-btn {
            flex-shrink: 0;
        }
    }
    </style>
</head>
<body>

<!-- Header -->
<header class="main-header">
    <nav class="navbar header-nav navbar-expand-lg">
        <div class="container">

            <!--Brand name/Logo -->
            <a href="#" class="navbar-brand">
            <img src="bautista.png" alt="Logo" width="60" height="60" class="d-inline-block align-text-top">
              <div class="brand-text">
               <span class="subtext">Tomas SM.</span>
               <span class="school-name">Bautista Elementary School</span>
      
              </div>
            </a>

            <div class="collapse navbar-collapse justify-content-end" id="navbar-collapse-toggle">
                 <ul class="navbar-nav mx-auto">
                    <li>
                        <a href="index.php" class="nav-link">Home</a>
                    </li>
                    <li class="nav-item dropdown">
    <a href="#" class="nav-link dropdown-toggle" id="aboutDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
      
       <span>About <i class='bx bxs-down-arrow'></i></span>
    </a>
    <ul class="dropdown-menu" aria-labelledby="aboutDropdown">
         <li><a class="dropdown-item" href="org_chart.php">Organizational Chart</a></li>
        <li><a class="dropdown-item" href="teacher.php">Faculty & Staff</a></li>
        <li><a class="dropdown-item" href="history.php">History</a></li>
        <li><a class="dropdown-item" href="mission.php">VMGO</a></li>
    </ul>
</li>

                    <li>
                        <a href="news.php" class="nav-link">News & Events</a>
                    </li>
                    <li class="nav-item dropdown">
    <a href="#" class="nav-link dropdown-toggle" id="aboutDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
      
       <span>More <i class='bx bxs-down-arrow'></i></span>
    </a>
    <ul class="dropdown-menu" aria-labelledby="aboutDropdown">
        <li><a class="dropdown-item" href="achievements.php">Achievements</a></li>
        <li><a class="dropdown-item" href="gallery.php">Gallery</a></li>
        <li class="d-lg-none">
      <a class="dropdown-item" href="login.php">Staff Login</a>
    </li>
    </ul>
</li>
                    <li>
                        <a href="contact.php" class="nav-link">Contact</a>
                    </li>
                    <li class="nav-item d-none d-lg-block" >
  <a class="btn btn-outline-primary ms-2" href="login.php"> <i class="bx bx-user" style="font-size: 1.4rem; position: relative; top: 4px;"></i> Staff Login</a>
</li> 
                 </ul>                
                  <form class="search-form" action="search.php" method="GET" role="search">
    <input class="form-control search-input" name="q" type="search" placeholder="Search" aria-label="Search" required>
    <button class="btn search-btn" type="submit">
        <i class='bx bx-search'></i>
    </button>
</form>
                 </div>
           <!-- Mobile Menu -->
            <button class="navbar-toggler collapsed" type="button" data-bs-toggle="collapse"
            data-bs-target="#navbar-collapse-toggle">
            <span></span>
            <span></span>
            <span></span>
         </button>
        </div>
    </nav>
</header>


<!-- Contact Section -->
<section class="contact-section py-5" id="contact">
    <div class="container">
        <div class="text-center mb-5 animate-on-scroll">
            <h1 class="fw-bold text-white"><i class='bx bx-phone-call me-2'></i>CONNECT WITH US</h1>          
            <p class="mt-2 text-white opacity-75 animate-on-scroll slide-in-bottom">We'd love to hear from you!</p>
        </div>

        <div class="row g-4">
            <!-- Left Column -->
            <div class="col-lg-6 d-flex flex-column">
                <!-- Contact Info Card -->
                <div class="contact-card animate-on-scroll slide-in-left">
                    <div class="contact-card-header">
                        <h3><i class='bx bx-info-circle'></i> Contact Information</h3>
                    </div>
                    <div class="contact-card-body">
                        <ul class="contact-info-list">
                            <li>
                                <i class='bx bxs-phone-call'></i>
                                <div>
                                    <strong>Tel. Number:</strong> <?= $telephone ?>
                                </div>
                            </li>
                            <li>
                                <i class='bx bxl-facebook-circle'></i>
                                <div>
                                    <strong>Facebook:</strong> 
                                    <a href="<?= $fb_link ?>" target="_blank">
                                        <?= $fb_page ?>
                                    </a>
                                </div>
                            </li>
                            <li>
                                <i class='bx bxs-map'></i>
                                <div>
                                    <strong>Address:</strong> <?= $address ?>
                                </div>
                            </li>
                            <li>
                                <i class='bx bxs-envelope'></i>
                                <div>
                                    <strong>Email:</strong> <?= $email ?>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Head Teacher Message Card -->
                <div class="teacher-card mt-4 animate-on-scroll slide-in-left delayed-animation">
                    <div class="teacher-card-header">
                        <h3><i class='bx bx-message-rounded-dots'></i> Message from the Head Teacher</h3>
                    </div>
                    <div class="teacher-card-body">
                        <p class="teacher-message">
                            "Thank you! We're happy to answer any questions you have. Just send us a message through e-mail or direct message to TSMBES FB page. You can also call us using the telephone number provided."
                        </p>
                        <div class="teacher-profile">
                            <img src="<?= htmlspecialchars($teacher_image) ?>" alt="<?= $teacher_name ?>">
                            <div class="teacher-profile-text">
                                <strong><?= $teacher_name ?></strong>
                                <small><?= $teacher_title ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column (Google Map) -->
            <div class="col-lg-6">
                <div class="map-card h-100 animate-on-scroll slide-in-right">
                    <div class="map-card-header">
                        <h3><i class='bx bx-map'></i> Find Us on the Map</h3>
                    </div>
                    <div class="map-card-body">
                        <div class="map-container">
                            <iframe
                                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3755.5273928995725!2d122.48945547763651!3d11.60972083944819!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33a5858650dcc9df%3A0x6664bb9d26fb98f8!2sTomas%20SM%20Bautista%20Elementary%20School!5e1!3m2!1sen!2sph!4v1745140711845!5m2!1sen!2sph"
                                allowfullscreen="" loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade">
                            </iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    // Intersection Observer for scroll animations
    document.addEventListener('DOMContentLoaded', function() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });
        
        // Observe elements with animate-on-scroll class
        document.querySelectorAll('.animate-on-scroll').forEach(el => {
            observer.observe(el);
        });
    });
</script>
<?php include 'footer.php'; ?>