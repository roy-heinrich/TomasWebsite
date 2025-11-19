<?php
require_once 'config.php';

// Fetch active faculty from PostgreSQL
try {
    $stmt = $conn->prepare("SELECT * FROM faculty WHERE visible = 'Yes' AND status = 'Active' ORDER BY display_order ASC, faculty_id DESC");
    $stmt->execute();
    $facultyList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $facultyList = [];
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Page</title>
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
   background:rgb(255, 255, 255, 0.9);
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




 /* Organizational Chart Section Padding */
.gallery-section {
  padding-top: 4rem;
  padding-bottom: 6rem; /* Increased bottom padding for desktop */
}

@media (max-width: 768px) {
  .gallery-section {
    padding-top: 3rem;
    padding-bottom: 5rem; /* Slightly less padding on mobile */
  }
}

/* Adjust the container padding if needed */
.gallery-section .container {
  padding-bottom: 6rem; /* Additional space inside container */
}

@media (max-width: 768px) {
  .gallery-section .container {
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
            transition: opacity 0.6s ease-in-out, transform 0.6s ease-in-out;
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
        
        /* Prevent overflow issues on mobile */
        @media (max-width: 768px) {
            .animate-on-scroll {
                transform: none !important;
                transition: opacity 0.6s ease-in-out;
            }
         

        }
        
        /* Enhance card animation */
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease, opacity 0.6s ease, transform 0.6s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .card:hover {
  transform: scale(1.02);
  box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
  transition: transform 0.3s ease, box-shadow 0.3s ease;
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



<!-- Faculty and Staff Section -->
<section class="gallery-section py-5" id="faculty" style="background: url('images/sun-tornado1.webp') no-repeat center center / cover; margin-top: 100px;">
    <div class="container">
        <div class="text-center mb-5 ">
            <h1 class="fw-bold text-white"><i class='bx bx-group me-2'></i>FACULTY & STAFF</h1>
            <p class="mt-2 text-white">Meet the dedicated educators of Tomas SM. Bautista Elementary School</p>
        </div>

        <div class="row g-4 justify-content-center">
            <?php
            $delayClasses = ['', 'delayed-animation', 'delayed-animation-2', 'delayed-animation-3'];
            $counter = 0;

            if (count($facultyList) > 0) {
                foreach ($facultyList as $row) {
                    $delayClass = $delayClasses[$counter % count($delayClasses)];
                    $counter++;
                    $imageUrl = getSupabaseUrl($row["image_path"], 'faculty_prof');
                    ?>
                    <div class="col-md-4 col-sm-6">
                        <div class="card text-center border-0 shadow-lg h-100 animate-on-scroll slide-in-bottom <?= $delayClass ?>" style="border-radius: 20px; overflow: hidden;">
                            <img src="<?= htmlspecialchars($imageUrl) ?>" class="card-img-top" alt="<?= htmlspecialchars($row["fullname"]) ?>" style="height: 300px; object-fit: cover;">
                            <div class="card-body bg-light">
                                <h5 class="card-title fw-bold"><?= htmlspecialchars($row["fullname"]) ?></h5>
                                <p class="text-muted mb-1"><?= htmlspecialchars($row["position"]) ?></p>
                                <?php if (!empty($row["advisory"])): ?>
                                    <p class="mb-0"><?= htmlspecialchars($row["advisory"]) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo '<p class="text-white">No active faculty members to display.</p>';
            }
            ?>
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