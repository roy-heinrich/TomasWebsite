<?php
require_once 'config.php';

// Fetch achievements from PostgreSQL
try {
    $stmt = $conn->prepare("SELECT * FROM achieve_tbl WHERE status = 'Active' AND visibility = 'Yes' ORDER BY created_at DESC");
    $stmt->execute();
    $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $achievements = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Achievement Page</title>
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
    
    .slide-in-bottom {
        transform: translateY(50px);
    }
    
    .slide-in-bottom.fade-in {
        transform: translateY(0);
    }
    
    .delayed-animation {
        transition-delay: 0.2s;
    }
    
    .delayed-animation-2 {
        transition-delay: 0.4s;
    }
    
    .delayed-animation-3 {
        transition-delay: 0.6s;
    }
    
    /* Staggered animations for achievement items */
    .achievement-item-animate {
        opacity: 0;
        transform: translateY(30px);
        transition: opacity 0.8s ease, transform 0.8s ease;
    }
    
    .achievement-item-animate.fade-in {
        opacity: 1;
        transform: translateY(0);
    }
    
    /* Prevent overflow issues on mobile */
    @media (max-width: 768px) {
        .animate-on-scroll, .achievement-item-animate {
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


.achievement-container {
        height: 400px;
        overflow: hidden;
        border-radius: 12px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
        transition: transform 0.3s ease;
    }

    @media (max-width: 768px) {
        .achievement-container {
            height: 300px;
        }
    }

    @media (max-width: 576px) {
        .achievement-container {
            height: 250px;
        }
    }

    .achievement-container:hover {
        transform: translateY(-5px);
    }

    .achievement-item {
        position: relative;
        width: 100%;
        height: 100%;
        overflow: hidden;
    }

    .achievement-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: filter 0.3s ease;
    }

    .achievement-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        opacity: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        transition: opacity 0.3s ease;
        padding: 1.5rem;
        text-align: justify;
        color: white;
        font-size: 0.85rem; /* base size */
    }

    .achievement-item:hover .achievement-overlay {
        opacity: 1;
    }

    .achievement-item:hover .achievement-image {
        filter: brightness(70%);
    }

    .carousel-control-prev,
    .carousel-control-next {
        width: 50px;
        height: 50px;
        top: 50%;
        transform: translateY(-50%);
        opacity: 0.7;
        transition: opacity 0.3s;
    }

    .carousel-control-prev:hover,
    .carousel-control-next:hover {
        opacity: 1;
    }

    .carousel-control-prev {
        left: -12px;
    }

    .carousel-control-next {
        right: -12px;
    }

    .carousel-indicators {
        bottom: 15px;
    }

    .carousel-indicators button {
        width: 12px;
        height: 12px;
        border-radius: 0%;
        margin: 0 5px;
    }

    .achievement-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: white;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
        transition: all 0.3s ease;
        letter-spacing: 0.5px;
    }

    .achievement-title:hover {
        transform: scale(1.03);
        text-shadow: 0 4px 8px rgba(255, 215, 0, 0.3);
    }

    @media (max-width: 768px) {
        .achievement-title {
            font-size: 1rem;
        }

        .achievement-container .achievement-overlay {
            font-size: 0.60rem !important;
            padding: 0.60rem !important;
        }
    }

    @media (max-width: 480px) {
        .achievement-container .achievement-overlay {
            font-size: 0.55rem !important;
            padding: 0.55rem !important;
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


<!-- Achievements Section -->
<section class="achievements-section py-5" id="achievements" style="background: url('images/bluee.webp') no-repeat center center / cover; margin-top: 100px;">
    <div class="container py-1">
        <div class="text-center mb-5">
            <h1 class="fw-bold text-white section-heading animate-on-scroll">
                <i class='bx bx-trophy me-2'></i>ACHIEVEMENTS
            </h1>
            <p class="mt-2 text-white section-description animate-on-scroll slide-in-bottom">
                Celebrating the accomplishments of our students and school community
            </p>
        </div>

        <div class="row g-4">
            <?php
            if (!empty($achievements)) {
                $counter = 0;
                foreach ($achievements as $row) {
                    $images = !empty($row['images']) ? array_filter(explode(',', $row['images'])) : [];
                    $hasMultiple = count($images) > 1;
                    $counter++;

                    // Create staggered delay classes
                    $delayClass = '';
                    if ($counter % 3 === 1) $delayClass = 'delayed-animation';
                    if ($counter % 3 === 2) $delayClass = 'delayed-animation-2';
                    if ($counter % 3 === 0) $delayClass = 'delayed-animation-3';
                    ?>
                    <div class="col-lg-6 col-md-6 mb-4 achievement-item-animate <?= $delayClass ?>">
                        <div class="text-center mb-3">
                            <div class="achievement-title animate-on-scroll"><?= htmlspecialchars($row['title']) ?></div>
                        </div>
                        <div class="achievement-container animate-on-scroll slide-in-bottom">
                            <?php if ($hasMultiple): ?>
                                <!-- Carousel for multiple images -->
                                <div id="carousel-<?= $counter ?>" class="carousel slide h-100" data-bs-ride="carousel">
                                    <div class="carousel-inner h-100">
                                        <?php foreach ($images as $index => $image): ?>
                                            <div class="carousel-item h-100 <?= $index === 0 ? 'active' : '' ?>">
                                                <div class="achievement-item">
                                                    <img src="<?= htmlspecialchars(getSupabaseUrl(trim($image), 'achievement_pic')) ?>" 
                                                         alt="<?= htmlspecialchars($row['title']) . ' image ' . ($index + 1) ?>" 
                                                         class="achievement-image">
                                                    <?php if ($index === 0): ?>
                                                        <div class="achievement-overlay">
                                                            <?= nl2br(htmlspecialchars($row['description'])) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button class="carousel-control-prev" type="button" data-bs-target="#carousel-<?= $counter ?>" data-bs-slide="prev">
                                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                        <span class="visually-hidden">Previous</span>
                                    </button>
                                    <button class="carousel-control-next" type="button" data-bs-target="#carousel-<?= $counter ?>" data-bs-slide="next">
                                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                        <span class="visually-hidden">Next</span>
                                    </button>
                                    <div class="carousel-indicators">
                                        <?php foreach ($images as $index => $image): ?>
                                            <button type="button" data-bs-target="#carousel-<?= $counter ?>" 
                                                    data-bs-slide-to="<?= $index ?>" 
                                                    class="<?= $index === 0 ? 'active' : '' ?>" 
                                                    aria-label="Slide <?= $index + 1 ?>"></button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Single image display -->
                                <div class="achievement-item">
                                    <img src="<?= htmlspecialchars(getSupabaseUrl(trim($images[0]), 'achievement_pic')) ?>" 
                                         alt="<?= htmlspecialchars($row['title']) ?>" 
                                         class="achievement-image">
                                    <div class="achievement-overlay">
                                        <?= nl2br(htmlspecialchars($row['description'])) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo '<div class="col-12 text-center"><p class="text-white">No achievements found</p></div>';
            }
            ?>
        </div>
    </div>
    <div style="padding: 30px;"></div>
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
        document.querySelectorAll('.animate-on-scroll, .achievement-item-animate').forEach(el => {
            observer.observe(el);
        });
    });
</script>

<?php include 'footer.php'; ?>