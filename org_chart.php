<?php
require_once 'config.php';

// Fetch organizational chart image from PostgreSQL (Supabase)
$imagePath = '';
try {
    $stmt = $conn->prepare("SELECT image_path FROM organizational_chart ORDER BY uploaded_at DESC LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['image_path'])) {
        $imagePath = getSupabaseUrl($row['image_path'], 'organizational_chart');
    }
} catch (PDOException $e) {
    $imagePath = '';
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizational Chart</title>
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

.card:hover {
  transform: scale(1.02);
  box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card {
  transition: transform 0.3s ease, box-shadow 0.3s ease;
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); /* base shadow */
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


<!-- Organizational Chart Section -->
<section class="gallery-section py-5" id="faculty" style="background: url('images/blue4.webp') no-repeat center center / cover; margin-top: 100px;">
  <div class="container">
    <div class="text-center mb-5">
      <h1 class="fw-bold text-white"><i class='bx bx-sitemap me-2'></i>ORGANIZATIONAL CHART</h1>
      <p class="mt-2 text-white">TSMBES academic and admin team.</p>
    </div>

    <div class="row g-4 justify-content-center">
      <!-- Organizational Chart Image Container -->
      <div class="col-12">
        <div class="card border-0 shadow-sm overflow-hidden fade-in-image" style="border-radius: 15px;">
          <div class="position-relative">
            <?php if ($imagePath): ?>
              <img src="<?= htmlspecialchars($imagePath) ?>" class="img-fluid w-100 org-chart-img" alt="Organizational Chart" style="transition: transform 0.3s ease;">
            <?php else: ?>
              <div class="text-center py-5 bg-light">
                <i class="bx bx-sitemap display-4 text-muted mb-3"></i>
                <p class="text-muted">No organizational chart available</p>
              </div>
            <?php endif; ?>
            <!-- Fullscreen Button -->
            <button class="btn btn-primary btn-sm position-absolute bottom-0 end-0 m-3 fullscreen-btn" 
                    style="z-index: 10; opacity: 0; transition: opacity 0.3s ease;"
                    onclick="toggleFullscreen()">
              <i class="bx bx-fullscreen"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<style>
  /* Fade-in animation */
  .fade-in-image {
    opacity: 0;
    animation: fadeIn 1s ease-in forwards;
  }
  
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
  }
  
  /* Hover effects */
  .org-chart-img:hover {
    transform: scale(1.01);
  }
  
  .card:hover .fullscreen-btn {
    opacity: 1 !important;
  }
  
  /* Fullscreen mode */
  .fullscreen {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.9);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }
  
  .fullscreen img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
  }
  
  .close-fullscreen {
    position: absolute;
    top: 20px;
    right: 20px;
    color: white;
    font-size: 2rem;
    cursor: pointer;
    z-index: 10000;
  }
</style>

<script>
  // Fade-in animation trigger
  document.addEventListener('DOMContentLoaded', function() {
    const elements = document.querySelectorAll('.fade-in-image');
    elements.forEach(el => {
      el.style.opacity = '0';
      setTimeout(() => {
        el.style.opacity = '1';
      }, 100);
    });
  });

  // Fullscreen functionality
  function toggleFullscreen() {
    const orgChart = document.querySelector('.org-chart-img');
    if (!orgChart) return;
    
    // Create fullscreen container if it doesn't exist
    let fullscreenContainer = document.querySelector('.fullscreen-container');
    
    if (!fullscreenContainer) {
      fullscreenContainer = document.createElement('div');
      fullscreenContainer.className = 'fullscreen-container fullscreen';
      fullscreenContainer.innerHTML = `
        <img src="${orgChart.src}" alt="Organizational Chart Fullscreen">
        <span class="close-fullscreen" onclick="closeFullscreen()">&times;</span>
      `;
      document.body.appendChild(fullscreenContainer);
    } else {
      closeFullscreen();
    }
  }

  function closeFullscreen() {
    const fullscreenContainer = document.querySelector('.fullscreen-container');
    if (fullscreenContainer) {
      fullscreenContainer.remove();
    }
  }
</script>



<?php include 'footer.php'; ?>
