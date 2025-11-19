<?php
require_once 'config.php';

// Fetch history data (PostgreSQL)
try {
    $stmt = $conn->prepare("SELECT * FROM history_page WHERE history_id = 1 LIMIT 1");
    $stmt->execute();
    $history = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $history = [
        'title' => 'History',
        'par1' => '',
        'par2' => '',
        'par3' => ''
    ];
}

// Fetch all history images from Supabase bucket "history_pic"
$historyImages = [];
try {
    $imgStmt = $conn->prepare("SELECT * FROM history_images WHERE history_id = 1 ORDER BY upload_date");
    $imgStmt->execute();
    $historyImages = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $historyImages = [];
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History Page</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/boxicons/2.1.4/css/boxicons.min.css">
    <style>


        /* Existing styles remain unchanged */
        .main-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 11;
            background: rgb(255, 255, 255, 0.9);
            transition: 0.3s background;
        }
        
        /* ... other existing styles ... */
        

   /* Add text justification to history paragraphs */
.history-content p {
    text-align: justify;
    text-justify: inter-word;
    line-height: 1.5;
    margin-bottom: 1.5rem;
    padding: 0 1rem; /* Default padding for desktop */
}

/* For mobile devices - significantly reduced padding */
@media (max-width: 768px) {
    .history-content {
        padding: 1rem 0.25rem !important; /* Reduced container padding */
    }
    
    .history-content p {
        line-height: 1.4;
        margin-bottom: 1.2rem;
        padding: 0 0.25rem !important; /* Minimal side padding */
    }
    
    /* Optional: Remove first-line indent on mobile if it looks better */
    .history-content p + p {
        text-indent: 0;
    }
}

/* Update the h3 style to match paragraph alignment */
.history-content h3 {
    color: #ffe9cc;
    margin-bottom: 1.5rem;
    padding: 0 1rem; /* Match paragraph padding */
    text-align: left; /* Ensure left alignment */
}

/* For mobile devices */
@media (max-width: 768px) {
    .history-content h3 {
        padding: 0 0.25rem !important; /* Match mobile paragraph padding */
    }
}


      /* Modified styles for carousel */
    .history-carousel-container {
        border-radius: 0.5rem;
        overflow: hidden;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    /* Desktop view - fixed height for carousel */
   @media (min-width: 992px) {
  .history-carousel-container {
    height: 700px; /* Fixed height */
     margin-top: 80px;
    margin-bottom: 0;
  }

  .history-carousel {
    height: 100%;
  }

  .history-carousel .carousel-inner {
    height: 100%;
    border-radius: 0.5rem;
  }

  .history-carousel .carousel-item {
    height: 100%;
  }

  .history-carousel .carousel-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  
  /* Add this to ensure description section doesn't affect carousel height */
  .history-section .row {
    align-items: flex-start;
  }
}

    
    /* Mobile view - fixed size and reorder content */
    @media (max-width: 991.98px) {
        .history-section .row {
            display: flex;
            flex-direction: column;
        }
        
        /* Make carousel come first in mobile */
        .history-section .row > .col-lg-6:first-child {
            order: 2; /* Description comes second */
        }
        
        .history-section .row > .col-lg-6:last-child {
            order: 1; /* Carousel comes first */
        }
        
        /* Fixed size for mobile carousel */
        .history-carousel-container {
            height: 300px;
            width: 100%;
            margin-bottom: 2rem;
        }
        
        .history-carousel .carousel-item img {
            width: 100%;
            height: 300px;
            object-fit: cover;
        }
    }
    
    /* Content section */
    .history-content {
        padding: 2rem;
        border-radius: 0.5rem;
        color: white;
    }
    
    .history-content h3 {
        color: #ffe9cc;
        margin-bottom: 1.5rem;
    }
    
    /* Carousel indicators */
    .history-carousel .carousel-indicators {
        bottom: 15px;
    }
    
    .history-carousel .carousel-indicators button {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background-color: rgba(255, 255, 255, 0.5);
        margin: 0 5px;
        border: none;
    }
    
    .history-carousel .carousel-indicators .active {
        background-color: #fff;
    }
    
    /* Carousel controls */
    .history-carousel .carousel-control-prev,
    .history-carousel .carousel-control-next {
        width: 40px;
        height: 40px;
        background-color: rgba(0,0,0,0.3);
        border-radius: 50%;
        top: 50%;
        transform: translateY(-50%);
    }
    
    .history-carousel .carousel-control-prev {
        left: 15px;
    }
    
    .history-carousel .carousel-control-next {
        right: 15px;
    }

      .history-section {
  background: url('images/qwqw.jpg') no-repeat center center / cover;
  position: relative;
  z-index: 1;
}

/* Dark overlay */
.history-section .overlay {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.35); /* Adjust opacity here */
  z-index: 0;
}

/* Ensure text and carousel are above overlay */
.history-section .container {
  position: relative;
  z-index: 2;
}

@media (max-width: 576px) {
  .history-section {
    background-position: center top;
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
  padding-bottom: 6rem; /* Additional space inside container */
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
            transition: opacity 0.6s ease-in-out, transform 0.6s ease-in-out;
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
            transform: translateY(20px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }
        
        .staggered-item.fade-in {
            opacity: 1;
            transform: translateY(0);
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
                transition: opacity 0.6s ease-in-out;
            }
            
            .slide-in-left, .slide-in-right {
                transform: none !important;
            }
        }
    </style>
</head>
<body>

<!-- Header (unchanged) -->
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



<!-- History Section -->
<section class="history-section py-5 position-relative" id="history" style="margin-top: 100px;">
  <div class="overlay"></div>
  <div class="container position-relative">
    <div class="text-center mb-5 animate-on-scroll">
      <h1 class="fw-bold text-white"><i class='bx bx-book me-2'></i>OUR HISTORY</h1>
      <p class="mt-2 text-white animate-on-scroll delayed-animation">Discover the journey of Tomas SM. Bautista Elementary School</p>
    </div>

    <div class="row g-4 align-items-stretch">
      <!-- Left Column: History Description -->
      <div class="col-lg-6">
        <div class="history-content h-100 animate-on-scroll slide-in-left delayed-animation">
          <h3 class="fw-bold animate-on-scroll slide-in-left"><?= htmlspecialchars($history['title']) ?></h3>
          <p><?= nl2br(htmlspecialchars($history['par1'])) ?></p>
        </div>
      </div>

      <!-- Right Column: Carousel -->
      <div class="col-lg-6 animate-on-scroll slide-in-right delayed-animation">
        <div class="history-carousel-container">
          <?php if (!empty($historyImages)): ?>
            <div id="historyCarousel" class="carousel slide history-carousel" data-bs-ride="carousel">
              <div class="carousel-indicators">
                <?php foreach ($historyImages as $index => $image): ?>
                  <button type="button" data-bs-target="#historyCarousel" data-bs-slide-to="<?= $index ?>" <?= $index === 0 ? 'class="active"' : '' ?>></button>
                <?php endforeach; ?>
              </div>
              <div class="carousel-inner">
                <?php foreach ($historyImages as $index => $image): ?>
                  <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                    <img src="<?= htmlspecialchars(getSupabaseUrl($image['image_path'], 'history_pic')) ?>" class="d-block w-100" alt="History Image <?= $index + 1 ?>">
                  </div>
                <?php endforeach; ?>
              </div>
              <button class="carousel-control-prev" type="button" data-bs-target="#historyCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
              </button>
              <button class="carousel-control-next" type="button" data-bs-target="#historyCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
              </button>
            </div>
          <?php else: ?>
            <div class="d-flex align-items-center justify-content-center bg-light" style="height: 300px;">
              <p class="text-muted">No images available</p>
            </div>
          <?php endif; ?>
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
        
        // Staggered items (for paragraphs in history section)
        document.querySelectorAll('.staggered-item').forEach((el, index) => {
            setTimeout(() => {
                observer.observe(el);
            }, index * 300);
        });
    });
</script>
<?php include 'footer.php'; ?>