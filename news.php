<?php
require_once 'config.php';

// Date calculations
$today = date('Y-m-d');
$fiveDaysAgo = date('Y-m-d', strtotime('-5 days'));

// ✅ Recent News: Today and last 5 days
try {
    $recentStmt = $conn->prepare("SELECT * FROM news_tbl WHERE status='Active' AND visibility='Yes' AND event_date BETWEEN :fiveDaysAgo AND :today ORDER BY event_date DESC");
    $recentStmt->execute([':fiveDaysAgo' => $fiveDaysAgo, ':today' => $today]);
    $recentNews = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recentNews = [];
}

// ✅ Upcoming Events: Anything from tomorrow onward
try {
    $upcomingStmt = $conn->prepare("SELECT * FROM news_tbl WHERE status='Active' AND visibility='Yes' AND event_date > :today ORDER BY event_date ASC");
    $upcomingStmt->execute([':today' => $today]);
    $upcomingNews = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $upcomingNews = [];
}

// ✅ Past News: Older than 5 days ago
try {
    $pastStmt = $conn->prepare("SELECT * FROM news_tbl WHERE status='Active' AND visibility='Yes' AND event_date < :fiveDaysAgo ORDER BY event_date DESC");
    $pastStmt->execute([':fiveDaysAgo' => $fiveDaysAgo]);
    $pastNews = $pastStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pastNews = [];
}

// Function to get badge color
function getBadgeColor($category) {
    switch ($category) {
        case 'Announcement': return 'background-color: teal;';
        case 'Sports': return 'background-color: orange;';
        case 'Academic': return 'background-color: green;';
        case 'Community': return 'background-color: #6a0dad;';
        case 'School Event': return 'background-color: blue;';
        default: return 'background-color: gray;';
    }
}

// Fetch contact information
try {
    $contactStmt = $conn->query("SELECT * FROM contact_info ORDER BY id DESC LIMIT 1");
    $contact = [
        'telephone_primary' => '(123) 456-7890',
        'email_general' => 'tomasbautista910@gmail.com',
        'fb_page' => 'Tomas Bautista ES',
        'fb_link' => '#',
        'address' => '123 Education St., Bautista City'
    ];
    if ($contactStmt && $row = $contactStmt->fetch()) {
        $contact = $row;
    }
} catch (PDOException $e) {
    $contact = [
        'telephone_primary' => '(123) 456-7890',
        'email_general' => 'tomasbautista910@gmail.com',
        'fb_page' => 'Tomas Bautista ES',
        'fb_link' => '#',
        'address' => '123 Education St., Bautista City'
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News & Event</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/boxicons/2.1.4/css/boxicons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.3/dist/css/splide.min.css" />
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
        
        .slide-in-left, .slide-in-right {
            transform: none !important;
        }
    }
    
    


  /* Add these styles for the new filter controls */
       /* Filter controls styling */
        .filter-container {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: center;
        }
        
      .filter-select {
    flex: 0 0 auto;
    min-width: 220px;
    border-radius: 50px;
    padding: 0.5rem 1.2rem;
    border: 1px solid #0d6efd;
    background-color: #f8f9fa;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    color: #0d6efd; /* make text blue too */
}

/* 🔵 Fix black border on focus */
.filter-select:focus {
    outline: none;
    border-color: #0d6efd;
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25); /* subtle blue glow */
}
        
        .sort-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1.2rem;
            border-radius: 50px;
            border: 1px solid #0d6efd;
            background-color: #f8f9fa;
            color: #0d6efd;
            transition: all 0.3s ease;
            min-width: 220px;
        }
        
        .sort-btn:hover, .sort-btn.active {
            background-color: #0d6efd;
            color: white;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
           .filter-container {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem; /* reduce gap only for mobile */
    }

    .filter-select, .sort-btn {
        width: 100%;
        min-width: unset;
    }

    .sort-btn {
        justify-content: center;
        margin-top: 0.3rem; /* tighter spacing */
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


 /* Organizational Chart Section Padding */
.news-section {
  padding-top: 4rem;
  padding-bottom: 5rem; /* Increased bottom padding for desktop */
}

@media (max-width: 768px) {
  .news-section {
    padding-top: 3rem;
    padding-bottom: 4rem; /* Slightly less padding on mobile */
  }
}

/* Adjust the container padding if needed */
.news-section .container {
  padding-bottom: 5rem; /* Additional space inside container */
}

@media (max-width: 768px) {
  .news-section .container {
    padding-bottom: 2rem;
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




    .back-btn {
    border-radius: 50px;
    padding: 0.8rem 2rem;
    font-weight: 600;
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
    z-index: 1;
    border: 2px solid #0f318f;
    color: #0f318f;
    background: transparent;
}

.back-btn:before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 0%;
    height: 100%;
    background: #0f318f;
    z-index: -1;
    transition: all 0.5s;
}

.back-btn:hover:before {
    width: 100%;
}

.back-btn:hover {
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(15, 49, 143, 0.2);
}

@media (max-width: 576px) {
    .back-btn {
        padding: 0.5rem 1.5rem !important;
        font-size: 0.9rem !important;
        width: 100%; /* Optional: makes button full-width on mobile */
        max-width: 280px; /* Limits width on larger mobile screens */
        margin: 0 auto; /* Centers the button */
        display: block; /* Needed for centering */
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


<section class="news-section py-5" id="news" style="background: url('images/blue5.webp') no-repeat center center / cover; margin-top: 100px;">
  <div class="container">
    <!-- Section Header -->
    <div class="text-center mb-5 animate-on-scroll">
      <h1 class="fw-bold text-white"><i class='bx bx-news me-2'></i>NEWS & UPDATES</h1>
      <p class="mt-2 text-white">Latest updates from Tomas SM. Bautista Elementary School</p>
    </div>

    <!-- ✅ Recent News Carousel -->
    <div class="mb-5 animate-on-scroll slide-in-left">
      <h3 class="text-white fw-bold mb-4 border-bottom pb-2"><i class='bx bx-time me-2'></i>Recent News</h3>
      <?php if (!empty($recentNews)): ?>
        <div class="splide" id="recent-carousel" aria-label="Recent News Carousel">
          <div class="splide__track">
            <ul class="splide__list">
              <?php foreach ($recentNews as $row): ?>
                <li class="splide__slide">
                  <div class="card news-card shadow-sm border-0 rounded-3 position-relative h-100" >
                    <span class="badge category-badge text-white" style="<?= getBadgeColor($row['category']); ?>">
                      <?= htmlspecialchars($row['category']); ?>
                    </span>
                    <img src="<?= htmlspecialchars(getSupabaseUrl($row['image'], 'news_pic')) ?>" class="card-img-top" style="height: 200px; object-fit: cover;">
                    <div class="card-body">
                      <h6 class="card-title fw-bold"><?= htmlspecialchars($row['title']); ?></h6>
                      <p class="text-muted small"><i class='bx bx-calendar'></i> <?= date("F d, Y", strtotime($row['event_date'])); ?></p>
                      <p class="card-text"><?= htmlspecialchars($row['short_info']); ?></p>
                      <a href="#" class="btn btn-outline-primary btn-sm mt-3 read-more-btn"
                         data-title="<?= htmlspecialchars($row['title']); ?>"
                         data-date="<?= date("F d, Y", strtotime($row['event_date'])); ?>"
                         data-description="<?= htmlspecialchars($row['full_desc']); ?>"
                         data-image="<?= htmlspecialchars(getSupabaseUrl($row['image'], 'news_pic')) ?>">
                         Read More</a>
                    </div>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
     <?php else: ?>
  <div class="no-events-message">
    <i class='bx bx-info-circle'></i>
    <h5>No recent news at the moment</h5>
    <p>Check back later for updates</p>
  </div>
<?php endif; ?>
    </div>

    <!-- ✅ Upcoming Events Carousel -->
    <div class="mb-5 animate-on-scroll slide-in-right">
      <h3 class="text-white fw-bold mb-4 border-bottom pb-2"><i class='bx bx-calendar-check me-2'></i>Upcoming Events</h3>
      <?php if (!empty($upcomingNews)): ?>
        <div class="splide" id="upcoming-carousel" aria-label="Upcoming Events Carousel">
          <div class="splide__track">
            <ul class="splide__list">
              <?php foreach ($upcomingNews as $row): ?>
                <li class="splide__slide">
                  <div class="card news-card shadow-sm border-0 rounded-3 position-relative h-100">
                    <span class="badge category-badge text-white" style="<?= getBadgeColor($row['category']); ?>">
                      <?= htmlspecialchars($row['category']); ?>
                    </span>
                    <img src="<?= htmlspecialchars(getSupabaseUrl($row['image'], 'news_pic')) ?>" class="card-img-top" style="height: 200px; object-fit: cover;">
                    <div class="card-body">
                      <h6 class="card-title fw-bold"><?= htmlspecialchars($row['title']); ?></h6>
                      <p class="text-muted small"><i class='bx bx-calendar'></i> <?= date("F d, Y", strtotime($row['event_date'])); ?></p>
                      <p class="card-text"><?= htmlspecialchars($row['short_info']); ?></p>
                      <a href="#" class="btn btn-outline-primary btn-sm mt-3 read-more-btn"
                         data-title="<?= htmlspecialchars($row['title']); ?>"
                         data-date="<?= date("F d, Y", strtotime($row['event_date'])); ?>"
                         data-description="<?= htmlspecialchars($row['full_desc']); ?>"
                         data-image="<?= htmlspecialchars(getSupabaseUrl($row['image'], 'news_pic')) ?>">
                         Read More</a>
                    </div>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
     <?php else: ?>
  <div class="no-events-message">
    <i class='bx bx-info-circle'></i>
    <h5>No upcoming events at the moment</h5>
    <p>Check back later for updates</p>
  </div>
<?php endif; ?>
    </div>

   <!-- ✅ Past News Grid with Pagination -->
<div class="mb-5 animate-on-scroll delayed-animation">
    <h3 class="text-white fw-bold mb-4 border-bottom pb-2"><i class='bx bx-rotate-right me-2'></i>Past News</h3>
      <div class="bg-white p-4 shadow-sm rounded-3">
        <!-- Filter Controls - UPDATED LAYOUT -->
        <div class="filter-container">
          <select id="categoryFilter" class="filter-select">
            <option value="All">All Categories</option>
            <option value="School Event">School Event</option>
            <option value="Announcement">Announcement</option>
            <option value="Academic">Academic</option>
            <option value="Sports">Sports</option>
            <option value="Community">Community</option>
          </select>

          <button id="dateSortButton" class="sort-btn active">
            <i class='bx bx-sort-down'></i> Sort: Newest First
          </button>
     </div>

      <div class="row g-4" id="past-news-container">
        <?php
        $pastNewsData = [];
        foreach ($pastNews as $row):
            ob_start(); ?>
            <div class="col-md-4 news-item" 
                 data-category="<?= htmlspecialchars($row['category']); ?>"
                 data-date="<?= $row['event_date']; ?>">
                <div class="card h-100 news-card shadow border-0 rounded-3 position-relative">
                    <span class="badge category-badge text-white" style="<?= getBadgeColor($row['category']); ?>">
                        <?= htmlspecialchars($row['category']); ?>
                    </span>
                    <img src="<?= htmlspecialchars(getSupabaseUrl($row['image'], 'news_pic')) ?>" class="card-img-top" style="height: 200px; object-fit: cover;">
                    <div class="card-body">
                        <h6 class="card-title fw-bold"><?= htmlspecialchars($row['title']); ?></h6>
                        <p class="text-muted small"><i class='bx bx-calendar'></i> <?= date("F d, Y", strtotime($row['event_date'])); ?></p>
                        <p class="card-text"><?= htmlspecialchars($row['short_info']); ?></p>
                        <a href="#" class="btn btn-outline-primary btn-sm mt-3 read-more-btn"
                           data-title="<?= htmlspecialchars($row['title']); ?>"
                           data-date="<?= date("F d, Y", strtotime($row['event_date'])); ?>"
                           data-description="<?= htmlspecialchars($row['full_desc']); ?>"
                           data-image="<?= htmlspecialchars(getSupabaseUrl($row['image'], 'news_pic')) ?>">
                           Read More</a>
                    </div>
                </div>
            </div>
        <?php 
            $pastNewsData[] = [
                'html' => ob_get_clean(),
                'date' => $row['event_date'],
                'category' => $row['category']
            ];
        endforeach;
        echo "<script>const pastNewsData = " . json_encode($pastNewsData) . ";</script>";
        ?>
      </div>
                    
      <div id="no-results-message" class="d-none">
        <div class="col-12 text-center py-5">
            <div class="d-flex flex-column align-items-center">
                <i class='bx bx-search-alt text-muted mb-3' style="font-size: 3rem;"></i>
                <h5 class="text-muted mb-2">No news found</h5>
                <p class="text-muted">Try changing the category or check back later for updates.</p>
            </div>
        </div>
      </div>
                    
      <nav aria-label="Past news pagination" class="mt-4">
        <ul class="pagination justify-content-center" id="pagination"></ul>
      </nav>
    </div>
</div>
</section>

<!-- News Modal -->
<div class="modal fade" id="newsModal" tabindex="-1" aria-labelledby="newsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 700px;">
    <div class="modal-content rounded-4 border-0 shadow overflow-hidden">
      <div class="modal-body p-0">
        <img id="modal-image" src="" class="img-fluid w-100" style="object-fit: cover;" alt="News Image">
        <div class="p-4">
          <h4 id="modal-title" class="fw-bold"></h4>
          <p class="text-muted small mb-3">
            <i class='bx bx-calendar'></i> <span id="modal-date"></span>
          </p>
          <div class="mb-4">
            <h7 class="font-weight-bold mb-2" style="color: #222;"><strong>Summary:</strong></h7>
            <p id="modal-short-info" class="modal-text-content"></p>
          </div>
          <div>
            <h7 class="font-weight-bold mb-2" style="color: #222;"><strong>Full Content:</strong></h7>
            <p id="modal-description" class="modal-text-content"></p>
          </div>
          <div class="modal-footer justify-content-center">
            <button type="button" class="back-btn mt-4" data-bs-dismiss="modal"><i class='bx bx-arrow-back me-2'></i>Back to Page</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>





<style>
  

.modal-text-content {
  text-align: justify;
  text-justify: inter-word;
  line-height: 1.6;
  margin-bottom: 1rem;
  white-space: pre-line; /* Preserve line breaks */
  font-size: 0.95rem;
}



  .splide__slide {
    height: auto;
}

.splide__slide > div {
    height: 100%;
}
  
 .read-more-btn {
            align-self: flex-start;
            font-size: 0.85rem;
            font-weight: 500;
            padding: 6px 15px;
            border-radius: 50px;
            transition: all 0.3s ease;
        }

  
  @media (max-width: 576px) {
    #categoryFilter {
      font-size: 0.85rem;
      padding: 0.4rem 0.8rem;
      max-width: 100%;
    }
  }

  
  @media (max-width: 576px) {
    .description-text {
      font-size: 0.85rem;
    }
  }

 .modal-content {
  border-radius: 2rem !important; /* overrides Bootstrap default */
  overflow: hidden;
}
 
.more-rounded {
  border-radius: 1.25rem; /* 20px */
}

.category-badge {
  position: absolute;
  top: 10px;
  left: 10px;
  background-color: #0d6efd; /* Bootstrap primary */
  color: white;
  font-size: 0.7rem;
  padding: 3px 8px;
  border-radius: 5px;
  z-index: 2;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}

.news-card .read-more-btn {
    margin-top: auto;
    align-self: flex-start;
}

  .news-card {
    background: rgba(255, 255, 255, 0.95);
    transition: transform 0.3s, box-shadow 0.3s;
    display: flex;
    flex-direction: column;
    min-height: 350px; /* Minimum height */
}

  .news-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
  }


  

  .modal-content {
  border-radius: 2rem !important;
}

.modal-body h4 {
 /* color: #054ab0ff; */
   color: #1f2123f9;
  margin-bottom: 0.5rem;
}

.modal-body h6 {
  font-size: 1.1rem;
  border-bottom: 1px solid #eee;
  padding-bottom: 0.3rem;
  margin-bottom: 0.8rem;
}

@media (max-width: 768px) {
  .modal-text-content {
    font-size: 0.86rem;
     line-height: 1.6;
  }
  
  .modal-body h6 {
    font-size: 1rem;
  }
}


 .news-card .card-body {
    display: flex;
    flex-direction: column;
    flex-grow: 1;
    padding: 1.25rem;
}

  .news-card .card-text {
     font-size: 0.9rem;
    flex-grow: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 6;
     line-clamp: 6;
    -webkit-box-orient: vertical;
}

  .pagination .page-link {
    color: #0d6efd;
  }

  .pagination .page-item.active .page-link {
    background-color: #0d6efd;
    border-color: #0d6efd;
    color: white;
  }

  /* Move pagination indicators down */
#upcoming-carousel .splide__pagination,
#recent-carousel .splide__pagination {
  bottom: -1.5rem;
}

/* Optional: Ensure arrows don't show on desktop if slides are 3 or less (handled via JS too) */
@media (min-width: 993px) {
  #upcoming-carousel.few-items .splide__arrows,
  #recent-carousel.few-items .splide__arrows {
    display: none;
  }
}

/* Adjust Splide arrow positions */
#upcoming-carousel .splide__arrow--prev,
#recent-carousel .splide__arrow--prev {
  left: -2.8rem; /* Move further to the left */
  background-color: rgba(251, 251, 251, 0.94); /* Light transparent background */
}

#upcoming-carousel .splide__arrow--next,
#recent-carousel .splide__arrow--next {
  right: -2.8rem; /* Move further to the right */
  background-color: rgba(247, 239, 239, 0.91); /* Light transparent background */
}

/* Optional: style the arrows for better visibility */
#upcoming-carousel .splide__arrow,
#recent-carousel .splide__arrow {
  width: 2.5rem;
  height: 2.5rem;
  color: white;
  transition: background-color 0.3s;
  border-radius: 50%;
}

#upcoming-carousel .splide__arrow:hover,
#recent-carousel .splide__arrow:hover {
  background-color: rgba(0, 0, 0, 0.4); /* Slightly darker on hover */
}

@media (max-width: 768px) {
  #upcoming-carousel .splide__arrow,
  #recent-carousel .splide__arrow {
    display: none !important;
  }
}


 @media (max-width: 576px) {
    .news-card .card-title {
      font-size: 1.1rem; /* smaller title */
    }

    .news-card .card-text {
    font-size: 0.80rem !important;
    flex-grow: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 6;
     line-clamp: 6;
    -webkit-box-orient: vertical;
  }

    .news-card .text-muted.small {
      font-size: 0.75rem; /* date info */
    }

  }

  /* Styling for the no upcoming events message */
.no-events-message {
  background: rgba(0, 123, 255, 0.15);
  padding: 2rem;
  margin: 3rem auto;
  font-size: 1.2rem;
  border-radius: 12px;
  max-width: 600px;
  border: 1px solid rgba(255, 255, 255, 0.2);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  backdrop-filter: blur(5px);
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 380px;
  transition: all 0.3s ease;
}

.no-events-message:hover {
  background: rgba(0, 123, 255, 0.2);
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
}

.no-events-message i {
  font-size: 4rem;
  margin-bottom: 1.5rem;
  color: rgba(255, 255, 255, 0.8);
}

.no-events-message h5 {
  font-size: 1.4rem;
  margin-bottom: 0.5rem;
  color: white;
  font-weight: 600;
}

.no-events-message p {
  color: rgba(255, 255, 255, 0.8);
  margin-bottom: 0;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .no-events-message {
    padding: 1.5rem;
    margin: 2rem auto;
    min-height: 450px;
  }
  
  .no-events-message i {
    font-size: 4rem;
  }
  
  .no-events-message h5 {
    font-size: 1.2rem;
  }
}

@media (max-width: 576px) {

   .no-events-message h5 {
    font-size: 1.1rem;
  }

  .no-events-message {
    padding: 1.25rem;
    margin: 1.5rem auto;
    min-height: 450px;
    font-size: 1rem;
  }
  
  .no-events-message i {
    font-size: 3.5rem;
    margin-bottom: 1.5rem;
  }
}

@media (max-width: 768px) {
    .news-card {
        min-height: 300px;
    }
    
    .news-card .card-text {
        -webkit-line-clamp: 6;
        line-clamp: 6;
    }
}

/* Style for the no results message */
#no-results-message {
  min-height: 300px;
  display: flex;
  align-items: center;
  justify-content: center;
}

#no-results-message i {
  color: #6c757d;
  margin-bottom: 1rem;
}

#no-results-message h5 {
  color: #495057;
  font-weight: 500;
}

#no-results-message p {
  color: #6c757d;
  font-size: 0.9rem;
}
</style>


<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-4 col-md-6 mb-4 mb-lg-0">
                <h4 class="footer-title">About Our School</h4>
                <p class="mb-4 footer-desc" style="color: rgba(255,255,255,0.7);">Tomas SM. Bautista Elementary School is committed to providing quality education and holistic development for our students in a nurturing environment.</p>
                <div class="social-icons">
                    <a href="<?= htmlspecialchars($contact['fb_link']) ?>" target="_blank" title="Visit our Facebook page">
                        <i class="bx bxl-facebook"></i>
                    </a>
                    <a href="#" onclick="sendEmail('<?= htmlspecialchars($contact['email_general']) ?>')" title="Email us">
                        <i class="bx bx-envelope"></i>
                    </a>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-4 mb-lg-0">
    <h4 class="footer-title">Quick Links</h4>
    <div class="d-flex">
        <ul class="footer-links list-unstyled me-3">
            <li><a href="index.php"><i class='bx bx-chevron-right me-2'></i>Home</a></li>
            <li><a href="history.php"><i class='bx bx-chevron-right me-2'></i>History</a></li>
            <li><a href="news.php"><i class='bx bx-chevron-right me-2'></i>News & Events</a></li>
            <li><a href="gallery.php"><i class='bx bx-chevron-right me-2'></i>Gallery</a></li>
            <li><a href="contact.php"><i class='bx bx-chevron-right me-2'></i>Contact</a></li>
        </ul>
        <ul class="footer-links list-unstyled ms-1">
            <li><a href="mission.php"><i class='bx bx-chevron-right me-2'></i>VMGO</a></li>
            <li><a href="org_chart.php"><i class='bx bx-chevron-right me-2'></i>Organizational Chart</a></li>
            <li><a href="teacher.php"><i class='bx bx-chevron-right me-2'></i>Faculty & Staff</a></li>
            <li><a href="achievements.php"><i class='bx bx-chevron-right me-2'></i>Achievements</a></li>
            <li><a href="login.php"><i class='bx bx-chevron-right me-2'></i>Staff Login</a></li>
        </ul>
    </div>
</div>

            
             <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                <h4 class="footer-title">Contact Info</h4>
                <ul class="footer-contact list-unstyled">
                    <li><i class="bx bx-map"></i> <?= htmlspecialchars($contact['address']) ?></li>
                    <li><i class="bx bx-phone"></i> <?= htmlspecialchars($contact['telephone_primary']) ?></li>
                    <li><i class="bx bx-time"></i> Mon-Fri: 7:30 AM - 4:30 PM</li>
                </ul>
            </div>
        </div>
        
        <div class="copyright">
            &copy; 2025 Tomas SM. Bautista Elementary School. All Rights Reserved.
        </div>
    </div>
</footer>

<style>
.footer-links li {
    white-space: nowrap;
}

.footer {
    background: #1d3557;
    color: white;
    padding: 60px 0 30px;
    position: relative;
}

.footer::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
}

.footer-title {
    font-weight: 700;
    font-size: 1.3rem;
    margin-bottom: 25px;
    position: relative;
    padding-bottom: 15px;
}

.footer-title::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 50px;
    height: 3px;
    background: var(--secondary-color);
}

.footer-links a {
    display: inline-block;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    transition: all 0.3s ease-in-out;
    transform: translateX(0);
}

.footer-links a:hover {
    color: white;
    transform: translateX(5px);
    padding-left: 3px;
}

.footer-contact li {
    margin-bottom: 15px;
    display: flex;
    align-items: flex-start;
    color: rgba(255, 255, 255, 0.8);
    transition: color 0.3s ease-in-out;
}

.footer-contact li:hover {
    color: white;
}

.footer-contact i {
    margin-right: 10px;
    color: var(--accent-color);
    font-size: 1.1rem;
    transition: transform 0.3s ease-in-out;
}

.footer-contact li:hover i {
    transform: scale(1.1);
}

.social-icons {
    display: flex;
    gap: 15px;
    margin-top: 20px;
}

.social-icons a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border-radius: 50%;
    transition: all 0.3s ease-in-out;
    font-size: 1.2rem;
}

.social-icons a:hover {
    background: #0000FF;
    transform: translateY(-5px) scale(1.1);
}

.copyright {
    text-align: center;
    padding-top: 30px;
    margin-top: 40px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.95rem;
}

@media (max-width: 576px) {
    .footer-links {
        min-width: 0;
    }
    
    .footer-links li {
        white-space: normal;
        word-break: break-word;
        margin-bottom: 12px; /* Increased bottom gap for each link */
    }
    
    .footer-links a {
        white-space: normal;
        display: flex;
        align-items: center;
        color: rgba(255, 255, 255, 0.7);
        transition: all 0.2s ease; /* Added transition for mobile */
    }
    
    /* Mobile hover/tap effect */
    .footer-links a:active {
        color: white;
        transform: translateX(3px);
    }
    
    .footer-links a i {
        flex-shrink: 0;
    }
    
    .footer {
        padding: 40px 0 25px; /* Slightly increased bottom padding */
    }
    
    /* Increased gap between quick links columns */
    .footer-links.list-unstyled.me-3 {
        margin-right: 1.5rem !important;
    }
    
    /* Increased bottom margin for quick links section */
    .col-lg-4.col-md-6.mb-4.mb-lg-0 {
        margin-bottom: 2rem !important;
    }
}
</style>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="Jquery/Jquery3.7.1.min.js"></script>
<script src="script.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.3/dist/js/splide.min.js"></script>
<script>
  // Initialize Splide for Recent News
  document.addEventListener('DOMContentLoaded', function () {
    // Recent News Carousel
    const recentSplideEl = document.querySelector('#recent-carousel');
    if (recentSplideEl) {
      const recentList = recentSplideEl.querySelector('.splide__list');
      const recentSlides = recentList.querySelectorAll('.splide__slide');
      
      const recentSplideOptions = {
        perPage: 3,
        gap: '1rem',
        breakpoints: {
          992: { perPage: 2 },
          768: { perPage: 1 },
        },
      };

      if (recentSlides.length <= 3) {
        new Splide('#recent-carousel', {
          ...recentSplideOptions,
          type: 'slide',
          arrows: false,
          pagination: true,
          autoplay: false,
        }).mount();
      } else {
        new Splide('#recent-carousel', {
          ...recentSplideOptions,
          type: 'loop',
          autoplay: true,
        }).mount();
      }
    }

    // Upcoming Events Carousel
    const upcomingSplideEl = document.querySelector('#upcoming-carousel');
    if (upcomingSplideEl) {
      const upcomingList = upcomingSplideEl.querySelector('.splide__list');
      const upcomingSlides = upcomingList.querySelectorAll('.splide__slide');

      const upcomingSplideOptions = {
        perPage: 3,
        gap: '1rem',
        breakpoints: {
          992: { perPage: 2 },
          768: { perPage: 1 },
        },
      };

      if (upcomingSlides.length <= 3) {
        new Splide('#upcoming-carousel', {
          ...upcomingSplideOptions,
          type: 'slide',
          arrows: false,
          pagination: true,
          autoplay: false,
        }).mount();
      } else {
        new Splide('#upcoming-carousel', {
          ...upcomingSplideOptions,
          type: 'loop',
          autoplay: true,
        }).mount();
      }
    }
  });
</script>

<script>
// Handle 'Read More' button clicks
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('read-more-btn')) {
    e.preventDefault();

    const title = e.target.getAttribute('data-title');
    const date = e.target.getAttribute('data-date');
    const description = e.target.getAttribute('data-description');
    const shortInfo = e.target.closest('.card-body').querySelector('.card-text').textContent;
    const image = e.target.getAttribute('data-image');

    document.getElementById('modal-title').textContent = title;
    document.getElementById('modal-date').textContent = date;
    
    // Preserve line breaks in text content
    document.getElementById('modal-short-info').innerHTML = shortInfo.replace(/\n/g, '<br>');
    document.getElementById('modal-description').innerHTML = description.replace(/\n/g, '<br>');

    document.getElementById('modal-image').src = image;

    const newsModal = new bootstrap.Modal(document.getElementById('newsModal'));
    newsModal.show();
  }
});
</script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const container = document.getElementById('past-news-container');
        const pagination = document.getElementById('pagination');
        const categoryFilter = document.getElementById('categoryFilter');
        const dateSortButton = document.getElementById('dateSortButton');
        const noResultsMessage = document.getElementById('no-results-message');
        const itemsPerPage = 6;
        let currentPage = 1;
        let currentSortOrder = 'desc'; // Default: newest first
        let filteredCards = [...pastNewsData]; // initialize with all cards

        // Function to sort cards by date
        function sortCards(cards, order) {
            return cards.sort((a, b) => {
                const dateA = new Date(a.date);
                const dateB = new Date(b.date);
                return order === 'asc' ? dateA - dateB : dateB - dateA;
            });
        }

        // Function to update the sort button text and icon
        function updateSortButton() {
            const icon = dateSortButton.querySelector('i');
            if (currentSortOrder === 'desc') {
                dateSortButton.innerHTML = '<i class="bx bx-sort-down"></i> Sort: Newest First';
                dateSortButton.classList.add('active');
            } else {
                dateSortButton.innerHTML = '<i class="bx bx-sort-up"></i> Sort: Oldest First';
                dateSortButton.classList.remove('active');
            }
        }

        // Function to filter and sort cards
        function filterAndSortCards() {
            const category = categoryFilter.value;
            
            if (category === 'All') {
                filteredCards = [...pastNewsData];
            } else {
                filteredCards = pastNewsData.filter(item => item.category === category);
            }
            
            // Sort the filtered cards
            filteredCards = sortCards(filteredCards, currentSortOrder);
            
            currentPage = 1;
            displayPage(currentPage);
        }

        function displayPage(page) {
            container.innerHTML = '';
            const start = (page - 1) * itemsPerPage;
            const end = start + itemsPerPage;

            // Show empty state if no cards
            if (filteredCards.length === 0) {
                container.classList.add('d-none');
                noResultsMessage.classList.remove('d-none');
                pagination.innerHTML = '';
                return;
            }
            
            // Show results if cards exist
            container.classList.remove('d-none');
            noResultsMessage.classList.add('d-none');

            for (let i = start; i < end && i < filteredCards.length; i++) {
                container.innerHTML += filteredCards[i].html;
            }

            generatePaginationButtons(page);
        }

        function generatePaginationButtons(current) {
            pagination.innerHTML = '';
            const totalPages = Math.ceil(filteredCards.length / itemsPerPage);

            if (totalPages <= 1) {
                return; // Don't show pagination if only one page
            }

            // Prev button
            const prev = document.createElement('li');
            prev.className = `page-item ${current === 1 ? 'disabled' : ''}`;
            prev.innerHTML = `<a class="page-link" href="#">Previous</a>`;
            prev.addEventListener('click', function (e) {
                e.preventDefault();
                if (current > 1) {
                    displayPage(current - 1);
                    currentPage = current - 1;
                }
            });
            pagination.appendChild(prev);

            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                const li = document.createElement('li');
                li.className = `page-item ${i === current ? 'active' : ''}`;
                li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
                li.addEventListener('click', function (e) {
                    e.preventDefault();
                    displayPage(i);
                    currentPage = i;
                });
                pagination.appendChild(li);
            }

            // Next button
            const next = document.createElement('li');
            next.className = `page-item ${current === totalPages ? 'disabled' : ''}`;
            next.innerHTML = `<a class="page-link" href="#">Next</a>`;
            next.addEventListener('click', function (e) {
                e.preventDefault();
                if (current < totalPages) {
                    displayPage(current + 1);
                    currentPage = current + 1;
                }
            });
            pagination.appendChild(next);
        }

        // Event listener for category dropdown
        categoryFilter.addEventListener('change', function () {
            filterAndSortCards();
        });

        // Event listener for date sort button
        dateSortButton.addEventListener('click', function () {
            currentSortOrder = currentSortOrder === 'desc' ? 'asc' : 'desc';
            updateSortButton();
            filterAndSortCards();
        });

        if (typeof pastNewsData !== 'undefined' && pastNewsData.length > 0) {
            // Initial sort
            filteredCards = sortCards(filteredCards, currentSortOrder);
            updateSortButton();
            displayPage(currentPage);
        } else {
            // If no past news at all, show the no results message
            container.classList.add('d-none');
            noResultsMessage.classList.remove('d-none');
            pagination.innerHTML = '';
        }
    });
    </script>


<script>
function sendEmail(email) {
  const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
  const subject = "Inquiry from School Website";
  const body = "Dear Tomas SM. Bautista Elementary School,\n\n";
  
  if (isMobile) {
    window.location.href = `mailto:${email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
  } else {
    window.open(`https://mail.google.com/mail/?view=cm&fs=1&to=${email}&su=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`, '_blank');
  }
}
</script>

<script>
    // Intersection Observer for scroll animations
    document.addEventListener('DOMContentLoaded', function() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                    
                    // Special handling for past news grid
                    if (entry.target.classList.contains('past-news-grid')) {
                        // Add staggered animation to each news item
                        const newsItems = entry.target.querySelectorAll('.news-item');
                        newsItems.forEach((item, index) => {
                            setTimeout(() => {
                                item.style.opacity = 1;
                                item.style.transform = 'translateY(0)';
                            }, index * 150);
                        });
                    }
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
        
        // Special handling for past news grid animation
        const pastNewsGrid = document.querySelector('.past-news-grid');
        if (pastNewsGrid) {
            observer.observe(pastNewsGrid);
        }
    });
</script>
<?php include 'TomasChatBot/chatbot_widget.php'; ?>
</body>
</html>    