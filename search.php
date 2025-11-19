<?php
// search.php
require_once 'config.php'; 

$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];

// Define static page keywords (add more as needed)
$staticPages = [
    'history' => [
        'keywords' => ['history', 'school history', 'tomas history', 'historical', 'background', 'origin', 'establishment', 'founding'],
        'title' => 'School History',
        'description' => 'Discover the journey of Tomas SM. Bautista Elementary School',
        'url' => 'history.php'
    ],
    'mission' => [
        'keywords' => ['mission', 'vision', 'vmgo', 'core values', 'maka-diyos', 'maka-tao', 'makakalikasan', 'makabansa'],
        'title' => 'Vision, Mission & Core Values',
        'description' => 'Guiding principles of our school',
        'url' => 'mission.php'
    ],
    'org_chart' => [
        'keywords' => ['organizational chart', 'org chart', 'organization chart', 'structure', 'hierarchy', 'leadership'],
        'title' => 'Organizational Chart',
        'description' => 'TSMBES academic and admin team structure',
        'url' => 'org_chart.php'
    ],
    'homepage' => [
        'keywords' => ['landing page', 'home', 'welcome message', 'download', 'downloadables', 'documents', 'resources', 'event', 'calendar', 'event calendar', 'school event', 'school calendar', 'chatbot', 'ai'],
        'title' => 'Home Page',
        'description' => 'Landing Page of Tomas SM. Bautista Elementary School — explore latest news, downloads, school events, and essential resources in this section.',
        'url' => 'index.php'
    ],
    'contact' => [
        'keywords' => ['contact', 'contact us', 'get in touch', 'reach us', 'phone', 'email', 'address', 'location', 'map', 'message', 'number', 'facebook'],
        'title' => 'Contact Information',
        'description' => 'Connect with Tomas SM. Bautista Elementary School',
        'url' => 'contact.php'
    ]
];

if (!empty($searchTerm)) {
    // Search static pages
    foreach ($staticPages as $page) {
        foreach ($page['keywords'] as $keyword) {
            if (stripos($searchTerm, $keyword) !== false) {
                $results[] = [
                    'title' => $page['title'],
                    'short_info' => $page['description'],
                    'type' => 'page',
                    'url' => $page['url']
                ];
                break; // Stop checking other keywords for this page
            }
        }
    }

    // Search news_tbl
    $newsQuery = $conn->prepare("SELECT news_id, title, short_info, 'news' as type FROM news_tbl 
                                WHERE (title ILIKE :term OR short_info ILIKE :term OR full_desc ILIKE :term) 
                                AND visibility = 'Yes' AND status = 'Active'");
    $likeTerm = "%$searchTerm%";
    $newsQuery->execute([':term' => $likeTerm]);
    $newsResults = $newsQuery->fetchAll(PDO::FETCH_ASSOC);
    $results = array_merge($results, $newsResults);

    // Search faculty
   $facultyQuery = $conn->prepare("SELECT faculty_id, fullname as title, position as short_info, 'teacher' as type 
                               FROM faculty 
                               WHERE (fullname ILIKE :term OR position ILIKE :term OR advisory ILIKE :term) 
                               AND visible = 'Yes' AND status = 'Active'");
    $facultyQuery->execute([':term' => $likeTerm]);
    $facultyResults = $facultyQuery->fetchAll(PDO::FETCH_ASSOC);
    $results = array_merge($results, $facultyResults);

    // Search achievements
    $achieveQuery = $conn->prepare("SELECT ach_id, title, description as short_info, 'achievement' as type 
                                    FROM achieve_tbl 
                                    WHERE (title ILIKE :term OR description ILIKE :term) 
                                    AND visibility = 'Yes' AND status = 'Active'");
    $achieveQuery->execute([':term' => $likeTerm]);
    $achieveResults = $achieveQuery->fetchAll(PDO::FETCH_ASSOC);
    $results = array_merge($results, $achieveResults);
    
    // Search gallery_tbl
    $galleryQuery = $conn->prepare("SELECT gallery_id, title, '' as short_info, 'gallery' as type 
                                    FROM gallery_tbl 
                                    WHERE title ILIKE :term 
                                    AND visibility = 'Yes' AND status = 'Active'");
    $galleryQuery->execute([':term' => $likeTerm]);
    $galleryResults = $galleryQuery->fetchAll(PDO::FETCH_ASSOC);
    $results = array_merge($results, $galleryResults);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - Tomas SM. Bautista Elementary School</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/boxicons/2.1.4/css/boxicons.min.css">
    <style>
      /* Modern Search Results Section */
.search-results-section {
    padding-top: 5rem;
    padding-bottom: 14rem;
    margin-top: 100px;
     background-color: rgb(15, 49, 143);
}

.results-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    padding: 2rem;
    margin-top: 0.7rem;
    margin-bottom: 2rem;
}

 .results-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(15, 49, 143, 0.1);
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

          .search-header-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .search-icon-container {
            background: #0d6efd;
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

 .search-icon-container i {
            color: white;
            font-size: 1.5rem;
        }

        .results-count {
            font-size: 1.05rem;
            font-weight: 500;
            color: #495057;
            padding: 0.5rem 0;
        }

        .results-count strong {
            color: #0d6efd;
        }

        /* Mobile Optimizations */
        @media (max-width: 767.98px) {
            .search-icon-container {
                width: 42px;
                height: 42px;
                  
                margin-bottom: 0.5rem;
            }
            
            .results-header h2 {
                font-size: 1.3rem;
            }
            
            .results-count {
                font-size: 0.95rem;
            }

               .search-header-container {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
        }


.results-list {
    max-height: 60vh;
    overflow-y: auto;
    padding-right: 10px;
}

/* Scrollbar styling */
.results-list::-webkit-scrollbar {
    width: 8px;
}

.results-list::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.results-list::-webkit-scrollbar-thumb {
    background: #0d6efd;
    border-radius: 10px;
}

.results-list::-webkit-scrollbar-thumb:hover {
    background: #0a58ca;
}

/* Modern Result Item */
.search-result-item {
    background: white;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    border-left: 4px solid #0d6efd;
    margin-bottom: 1rem;
    padding: 1.25rem 1.5rem;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}

.search-result-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
    border-left: 4px solid #0a58ca;
}

.result-content {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    width: 100%;
}

.result-text {
    flex: 1;
    min-width: 0; /* Prevents overflow issues */
}

.search-type-badge {
    background: linear-gradient(135deg, #0f318f 0%, #1a56e2 100%);
    border-radius: 20px;
    padding: 0.35rem 1rem;
    font-weight: 500;
    font-size: 0.85rem;
    box-shadow: 0 2px 5px rgba(13, 110, 253, 0.2);
    flex-shrink: 0; /* Prevents badge from shrinking */
    margin-left: 0.5rem;
}

.result-icon {
    margin-right: 0.75rem;
    color: #0d6efd;
    font-size: 1.25rem;
    flex-shrink: 0;
}

/* ADD new styles for no-results container */
.no-results-container {
    min-height: 50vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    border-radius: 12px;
    padding: 2rem;
    margin-top: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

.no-results-content {
    text-align: center;
}

.no-results-content i {
    font-size: 3.5rem;
    color: #6c757d; /* Grey color */
    margin-bottom: 1.5rem;
}

.no-results-content h3 {
    color: #343a40; /* Dark grey */
    font-weight: 600;
}

.no-results-content p {
    color: #6c757d; /* Grey */
}
/* Mobile Optimizations */
@media (max-width: 767.98px) {
    .search-results-section {
        padding-top: 4rem;
        padding-bottom: 9rem;
        margin-top: 100px;
    }
    
    .results-container {
        padding: 1.25rem;
        margin-top: 0rem;
        margin-bottom: 1.5rem;
    }
    
    .results-header h2 {
        font-size: 1.4rem;
    }
    
    .results-list {
        max-height: 55vh;
    }
    
    /* Thinner scrollbar for mobile */
    .results-list::-webkit-scrollbar {
        width: 5px;
    }
    
    .search-result-item {
        padding: 1rem;
    }
    
    .result-content {
        flex-direction: column;
    }
    
    .result-text {
        width: 100%;
    }
    
    .result-text h5 {
        font-size: 1rem;
        display: -webkit-box;
        -webkit-line-clamp: 2; /* Limit to 2 lines */
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.4;
        margin-bottom: 0.5rem;
    }
    
    .result-text p {
        font-size: 0.85rem;
        margin-bottom: 0.75rem;
    }
    
    .search-type-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.8rem;
        align-self: flex-start; /* Align badge to left */
        margin-left: 0;
        margin-top: 0.25rem;
    }
    
    .result-icon {
        font-size: 1.1rem;
        margin-right: 0.5rem;
    }
    
    .no-results-content h3 {
        font-size: 1.3rem;
    }
    
    .no-results-content p {
        font-size: 0.95rem;
    }
    
    .no-results i {
        font-size: 2.5rem;
    }
}

/* Small mobile devices (portrait phones, less than 576px) */
@media (max-width: 575.98px) {
    .results-header h2 {
        font-size: 1.25rem;
    }
    
    .result-text h5 {
        font-size: 0.95rem;
    }
    
    .result-text p {
        font-size: 0.8rem;
    }
    
    .search-type-badge {
        font-size: 0.7rem;
    }
    
    .no-results-content h3 {
        font-size: 1.2rem;
    }
}

 
    .bold-icon {
    text-shadow: 
      0 0 1px currentColor,
      0 0 1px currentColor,
      0 0 1px currentColor;
  }
  




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
    <?php include 'header.php'; ?>
    
   <!-- Modern Search Results Section -->
    <section class="search-results-section">
        <div class="container">
            <div class="results-container">
                <div class="results-header">
                    <div class="search-header-container">
                        <div class="search-icon-container">
                            <i class='bx bx-search-alt-2'></i>
                        </div>
                        <h2 class="mb-0 fw-bold">Search Results for "<?= htmlspecialchars($searchTerm) ?>"</h2>
                    </div>
                    
                    <?php if (!empty($searchTerm)): ?>
                        <div class="results-count">
                            Found <strong><?= count($results) ?> result<?= count($results) !== 1 ? 's' : '' ?></strong> matching your query
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($searchTerm)): ?>
                    <?php if (!empty($results)): ?>
                        <div class="results-list">
                            <div class="list-group">
                                <?php foreach ($results as $result): ?>
                                    <a href="<?= isset($result['url']) ? $result['url'] : getLinkForType($result['type'], $result) ?>" class="list-group-item list-group-item-action search-result-item">
                                        <div class="result-content">
                                            <div class="result-text">
                                                <div class="d-flex align-items-center mb-2">
                                                    <?php $icon = getIconForType($result['type']); ?>
                                                    <i class='bx <?= $icon ?> result-icon'></i>
                                                    <h5 class="mb-0"><?= htmlspecialchars($result['title']) ?></h5>
                                                </div>
                                                <?php if (!empty($result['short_info'])): ?>
                                                    <p class="mb-0 text-muted"><?= htmlspecialchars(substr($result['short_info'], 0, 150)) ?>...</p>
                                                <?php endif; ?>
                                            </div>
                                            <span class="badge search-type-badge"><?= ucfirst(isset($result['type']) ? $result['type'] : 'page') ?></span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="no-results-container">
                            <div class="no-results-content">
                                <i class='bx bx-search-alt'></i>
                                <h3>No results found</h3>
                                <p>Try different or more general keywords</p>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-results-container">
                        <div class="no-results-content">
                            <i class='bx bx-search-alt'></i>
                            <h3>Please enter a search term</h3>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>


<?php
    function getLinkForType($type, $result) {
        switch ($type) {
            case 'news':
                return "news_detail.php?id=" . $result['news_id'];
            case 'teacher':
                return "teacher.php#faculty-" . $result['faculty_id'];
            case 'achievement':
                return "achievements.php#achieve-" . $result['ach_id'];
            case 'gallery':
                return "gallery.php?search=" . urlencode($result['title']);
            default:
                return "#";
        }
    }

    // New function to get icons for result types
    function getIconForType($type) {
        switch ($type) {
            case 'news': return 'bx-news';
            case 'teacher': return 'bx-user';
            case 'achievement': return 'bx-medal';
            case 'gallery': return 'bx-image';
            default: return 'bx-link';
        }
    }
    ?>


    <?php include 'footer.php'; ?>