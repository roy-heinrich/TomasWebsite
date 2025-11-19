<?php
require_once 'config.php';

// Fetch gallery records where status is Active and visibility is Yes
try {
    $stmt = $conn->prepare("SELECT * FROM gallery_tbl WHERE status = 'Active' AND visibility = 'Yes' ORDER BY created_at DESC");
    $stmt->execute();
    $galleries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $galleries = [];
}

// Group media files by title
$groupedEvents = [];
foreach ($galleries as $row) {
    $title = $row['title'];
    $mediaFiles = explode(',', $row['mediafiles']);

    $formattedMedia = [];
    foreach ($mediaFiles as $file) {
        $trimmedFile = trim($file);
        if (!empty($trimmedFile)) {
            // Use Supabase public URL for gallery_pic bucket
            $formattedMedia[] = getSupabaseUrl($trimmedFile, 'gallery_pic');
        }
    }

    if (!isset($groupedEvents[$title])) {
        $groupedEvents[$title] = $formattedMedia;
    } else {
        $groupedEvents[$title] = array_merge($groupedEvents[$title], $formattedMedia);
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery Page</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/boxicons/2.1.4/css/boxicons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.min.css"/>
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
    
    .staggered-item {
        opacity: 0;
        transform: translateY(30px);
        transition: all 0.8s ease;
    }
    
    .staggered-item.fade-in {
        opacity: 1;
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
        .animate-on-scroll, .staggered-item {
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

/* Gallery specific styles */
  .gallery-section {
    padding-bottom: 2rem !important;
  }
  
  .gallery-item {
    transition: transform 0.3s ease;
    margin-bottom: 0.5rem;
  }
  
  .gallery-item:hover {
    transform: scale(1.02);
  }
  
  .video-thumbnail video {
    filter: brightness(0.9);
    transition: filter 0.3s ease;
  }
  
  .video-thumbnail:hover video {
    filter: brightness(1);
  }
  
  /* Mobile specific styles */
  @media (max-width: 767.98px) {
   
    
    .gallery-item {
      margin-bottom: 0.3rem;
    }
    
    .col-6 {
      padding-left: 0.25rem;
      padding-right: 0.25rem;
    }
  }
  
  /* Fancybox customization */
  .fancybox__toolbar {
    background: rgba(0,0,0,0.7) !important;
  }
  
  .fancybox__nav {
    --f-button-width: 50px;
    --f-button-height: 50px;
    --f-button-border-radius: 50%;
    --f-button-color: #fff;
    --f-button-hover-color: #fff;
    --f-button-bg: rgba(0, 60, 150, 0.8);
    --f-button-hover-bg: rgba(0, 80, 200, 0.9);
    --f-button-active-bg: rgba(0, 60, 150, 0.9);
  }

   /* Fancybox customization for mobile */
  @media (max-width: 767.98px) {
    .fancybox__nav {
      --f-button-bg: rgba(199, 215, 239, 0.24) !important;
      --f-button-hover-bg: rgba(199, 206, 216, 0.31) !important;
      --f-button-active-bg: rgba(191, 199, 211, 0.23) !important;
    }
    
    .fancybox__nav button {
      opacity: 0.8;
      transition: opacity 0.3s ease;
    }
    
    .fancybox__nav button:hover {
      opacity: 1;
    }
    
    /* Make the navigation arrows smaller on mobile */
    .fancybox__nav button {
      --f-button-width: 40px;
      --f-button-height: 40px;
    }
    
    /* Position arrows closer to the edges on mobile */
    .fancybox__nav button[data-fancybox-prev] {
      left: 5px;
    }
    
    .fancybox__nav button[data-fancybox-next] {
      right: 5px;
    }
  }
  
  /* Keep desktop styles */
  @media (min-width: 768px) {
    .fancybox__nav {
      --f-button-bg: rgba(0, 60, 150, 0.8);
      --f-button-hover-bg: rgba(0, 80, 200, 0.9);
      --f-button-active-bg: rgba(0, 60, 150, 0.9);
    }
  }


  /* Organizational Chart Section Padding */
.gallery-section {
  padding-top: 4rem;
  padding-bottom: 5rem; /* Increased bottom padding for desktop */
}

@media (max-width: 768px) {
  .gallery-section {
    padding-top: 3rem;
    padding-bottom: 4rem; /* Slightly less padding on mobile */
  }
}

/* Adjust the container padding if needed */
.gallery-section .container {
  padding-bottom: 5rem; /* Additional space inside container */
}

@media (max-width: 768px) {
  .gallery-section .container {
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


<!-- Add animations to the HTML structure -->
<section class="gallery-section py-5" id="gallery" style="background: url('images/bluee.webp') no-repeat center center / cover; margin-top: 100px;">
  <div class="container">
    <div class="text-center mb-5 animate-on-scroll">
      <h1 class="fw-bold text-white"><i class='bx bx-images me-2'></i>GALLERY</h1>
      <p class="mt-2 text-white animate-on-scroll slide-in-bottom">Explore events and moments at Tomas SM. Bautista Elementary School</p>
    </div>

    <?php 
    $counter = 0;
    foreach ($groupedEvents as $title => $mediaFiles): 
        $counter++;
        $delayClass = '';
        if ($counter % 3 === 1) $delayClass = 'delayed-animation';
        if ($counter % 3 === 2) $delayClass = 'delayed-animation-2';
        if ($counter % 3 === 0) $delayClass = 'delayed-animation-3';
    ?>
      <div class="mb-5 animate-on-scroll <?= $delayClass ?>">
        <div class="mb-3 px-4 py-3 text-center" style="background: rgba(0, 60, 150, 0.4); border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
          <h3 class="m-0 fw-bold text-white text-break animate-on-scroll slide-in-left" style="font-size: clamp(1rem, 2.5vw, 1.5rem); word-wrap: break-word;">
            <?php echo htmlspecialchars($title); ?>
          </h3>
        </div>
        
        <div class="row g-3">
          <?php 
          $itemCounter = 0;
          foreach ($mediaFiles as $file): 
              $itemCounter++;
              $itemDelay = ($itemCounter % 4 === 1) ? '' : (($itemCounter % 4 === 2) ? 'delayed-animation' : (($itemCounter % 4 === 3) ? 'delayed-animation-2' : 'delayed-animation-3'));
              $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
          ?>
            <div class="col-6 col-md-4 col-lg-3 mb-0 staggered-item <?= $itemDelay ?>">
              <div class="gallery-item">
                <?php
                if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
                    // Image with 4:3 aspect ratio
                    echo '<a href="' . $file . '" data-fancybox="gallery" data-caption="' . htmlspecialchars($title) . '">
                            <img src="' . $file . '" class="img-fluid" alt="' . htmlspecialchars($title) . '" style="width:100%; aspect-ratio:4/3; object-fit:cover; border-radius:8px; cursor:pointer;">
                          </a>';
                } elseif ($extension === 'mp4') {
                    // Video thumbnail with play button overlay
                    echo '<a href="' . $file . '" data-fancybox="gallery" data-caption="' . htmlspecialchars($title) . '">
                            <div class="video-thumbnail" style="position:relative; width:100%; aspect-ratio:4/3; border-radius:8px; overflow:hidden;">
                              <video style="width:100%; height:100%; object-fit:cover;">
                                <source src="' . $file . '" type="video/mp4">
                              </video>
                              <div class="play-icon" style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); color:white; font-size:3rem; text-shadow:0 0 10px rgba(0,0,0,0.5);">
                                <i class="bx bx-play-circle"></i>
                              </div>
                            </div>
                          </a>';
                }
                ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>


<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
<script>
  // Initialize Fancybox with better mobile controls
  Fancybox.bind("[data-fancybox]", {
    Thumbs: {
      autoStart: false,
    },
    Toolbar: {
      display: {
        left: [],
        middle: [],
        right: ["close"],
      },
    },
    on: {
      "Carousel.ready": (fancybox, carousel) => {
        // Add swipe support for mobile
        if ('ontouchstart' in window) {
          const slide = carousel.getSlide(fancybox.getSlide().index);
          
          slide.panzoom = {
            touchAction: 'auto',
          };
        }
      },
    },
  });
</script>

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
        document.querySelectorAll('.animate-on-scroll, .staggered-item').forEach(el => {
            observer.observe(el);
        });
    });
</script>
<?php include 'footer.php'; ?>  