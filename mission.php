<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VMGO</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/boxicons/2.1.4/css/boxicons.min.css">
    <style>
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

@media (min-width: 992px) {
  .image-container {
    margin-top: 50px;
  }
}


  /* Mobile-specific styles */
@media (max-width: 767.98px) {
  .image-container {
    margin-top: 10px !important;
    margin-bottom: 10px !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
  }

  .image-container img {
    width: 330px !important;
    height: auto !important;
    display: block;
    margin: 0 auto;
  }
}


/* Organizational Chart Section Padding */
.history-section {
  padding-top: 4rem;
  padding-bottom: 6rem; /* Increased bottom padding for desktop */
}

@media (max-width: 768px) {
  .history-section {
    padding-top: 3rem;
    padding-bottom: 5rem; /* Slightly less padding on mobile */
  }
}

/* Adjust the container padding if needed */
.history-section .container {
  padding-bottom: 5rem; /* Additional space inside container */
}

@media (max-width: 768px) {
  .history-section .container {
    padding-bottom: 4rem;
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



        /* Animation styles */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(20px);
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
        
        .delayed-animation-3 {
            transition-delay: 0.9s;
        }
        
        /* Prevent overflow issues on mobile */
        @media (max-width: 768px) {
            .animate-on-scroll {
                transform: none !important;
                transition: opacity 0.8s ease;
            }
            
            .slide-in-left, .slide-in-right {
                transform: none !important;
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



<!-- Mission, Vision & Core Values Section -->
<section class="history-section py-5" id="mission-vision-values" style="background: url('images/blue3.webp') no-repeat center center / cover; margin-top: 100px;">
  <div class="container">
    <div class="text-center mb-5 animate-on-scroll">
      <h1 class="fw-bold text-white"><i class='bx bx-medal me-3'></i>VISION, MISSION & CORE VALUES</h1>
      <p class="mt-2 text-white">Guiding principles of Tomas SM. Bautista Elementary School</p>
    </div>

    <div class="row g-4 align-items-stretch">
      <!-- Right Column: School Image (appears first on mobile) -->
      <div class="col-lg-6 order-1 order-lg-2 position-relative animate-on-scroll slide-in-right">
        <div class="h-100 rounded-3 overflow-hidden position-relative image-container">
          <img src="images/hdd.png" alt="School Vision and Mission" 
               class="img-fluid d-block mx-auto rounded" 
               style="width: 550px; height: 550px; object-fit: contain;">
          <div class="position-absolute top-0 start-0 w-100 h-100"></div>
        </div>
      </div>

      <!-- Left Column: Content -->
      <div class="col-lg-6 order-2 order-lg-1 d-flex flex-column justify-content-center">
        <div class="h-100 p-3 text-white d-flex flex-column justify-content-center">
          <div class="animate-on-scroll slide-in-left">
            <h3 class="fw-bold mb-3" style="color: #ffffff;">Vision</h3>
            <p>
              We dream of Filipinos who passionately love their country and whose values and competencies enable them to realize their full potential and contribute meaningfully to building the nation.
            </p>
            <p>
              As a learner-centered public institution, the Department of Education continuously improves itself to better serve its stakeholders.
            </p>
          </div>

          <div class="animate-on-scroll slide-in-left delayed-animation">
            <h3 class="fw-bold mt-4 mb-3" style="color: #ffffff;">Mission</h3>
            <p>
              To protect and promote the right of every Filipino to quality, equitable, culture-based, and complete basic education where:
            </p>
            <ul>
              <li>Students learn in a child-friendly, gender-sensitive, safe, and motivating environment.</li>
              <li>Teachers facilitate learning and constantly nurture every learner.</li>
              <li>Administrators and staff ensure an enabling and supportive environment for effective learning to happen.</li>
              <li>Family, community, and other stakeholders are actively engaged and share responsibility for developing life-long learners.</li>
            </ul>
          </div>

          <div class="animate-on-scroll slide-in-left delayed-animation">
            <h3 class="fw-bold mt-4 mb-3" style="color: #ffffff;">Our Core Values</h3>
            <ul>
              <li>Maka-Diyos</li>
              <li>Maka-tao</li>
              <li>Makakalikasan</li>
              <li>Makabansa</li>
            </ul>
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