<?php
// news_detail.php
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: news.php");
    exit();
}

require_once 'config.php'; 

$newsId = (int)$_GET['id'];

// Fetch the main news article
$stmt = $conn->prepare("SELECT * FROM news_tbl WHERE news_id = :id AND visibility = 'Yes' AND status = 'Active'");
$stmt->execute([':id' => $newsId]);
$news = $stmt->fetch();

if (!$news) {
    header("Location: news.php");
    exit();
}

// Fetch latest 5 news articles
$latestStmt = $conn->prepare("SELECT news_id, title, image, created_at 
                FROM news_tbl 
                WHERE visibility = 'Yes' 
                AND status = 'Active' 
                AND news_id != :id
                ORDER BY created_at DESC 
                LIMIT 5");
$latestStmt->execute([':id' => $newsId]);
$latestNews = $latestStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($news['title']) ?> - Tomas SM. Bautista Elementary School</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/boxicons/2.1.4/css/boxicons.min.css">
    <style>
      
    
     /* Modern News Detail Section */
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e7f1 100%);         
            min-height: 100vh;
            padding-top: 100px;
        }
        
        .news-detail-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.12);
            overflow: hidden;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .news-header {
            padding: 2.5rem 2.5rem 1.5rem;      
          background: linear-gradient(120deg, #0f318f 0%, #1a56e2 100%);                     
            color: white;
            position: relative;
        }
        
        .news-date {
  font-size: 1rem; /* Default size for larger screens */
}

        .news-header h1 {
            font-weight: 700;
            letter-spacing: -0.5px;
            line-height: 1.2;
            max-width: 80%;
        }
        
        .news-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .news-badge {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 0.4rem 1.25rem;
            font-weight: 500;
            backdrop-filter: blur(5px);
        }
        
        .news-image-container {
            position: relative;
            overflow: hidden;
            height: 500px;
            border-top: 9px solid #e4e7f1; 
        }
        
        .news-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .news-content-container {
            padding: 2.5rem;
        }
        
        .news-content {
            font-size: 1rem;
            line-height: 1.7;
            color: #333;
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
        
        /* LATEST NEWS SECTION - REDESIGNED */
        .latest-news-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .latest-header {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 3px solid #0f318f;
            color: #0f318f;
            position: relative;
        }
        
        .latest-header:after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 80px;
            height: 3px;
           
        }
        
        /* Desktop layout - image above title */
        .latest-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        
        .latest-card {
            display: flex;
            flex-direction: column;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .latest-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.15);
        }
        
        .latest-image-wrapper {
            height: 180px;
            overflow: hidden;
        }
        
        .latest-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .latest-card:hover .latest-image {
            transform: scale(1.05);
        }
        
        .latest-content {
            padding: 1.2rem;
            background: white;
        }
        
        .latest-title {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: #333;
            line-height: 1.3;
        }
        
        .latest-date {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .more-btn {
            display: block;
            width: 100%;
            padding: 0.8rem;
            text-align: center;
            background: #0f318f;
            color: white;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 1.5rem;
            transition: all 0.3s;
        }
        
        .more-btn:hover {
            background: #1a56e2;
            color: white;
            text-decoration: none;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(15, 49, 143, 0.3);
        }
        
        /* Mobile layout - image and title side by side */
        @media (max-width: 991.98px) {
            .latest-grid {
                display: block;
            }
            
            .latest-card {
                display: flex;
                flex-direction: row;
                margin-bottom: 1rem;
            }
            
            .latest-image-wrapper {
                width: 120px;
                height: 100px;
                flex-shrink: 0;
            }
            
            .latest-content {
                flex: 1;
                padding: 0.8rem 1rem;
            }
            
            .latest-title {
                font-size: 1rem;
                margin-bottom: 0.3rem;
            }
        }
        
        /* Mobile Optimizations */
        @media (max-width: 991.98px) {
            .news-header {
                padding: 1.8rem 1.5rem 1rem;
            }
            
            .news-header h1 {
                font-size: 1.6rem;
                max-width: 100%;
            }
            
            .news-image-container {
                height: 350px;
            }
            
            .news-content-container {
                padding: 1.8rem 1.5rem;
            }
            
            .news-content {
                font-size: 1rem;
            }
            
            .latest-news-container {
                padding: 1.5rem;
            }
            
            .latest-header {
                font-size: 1.3rem;
            }
        }
        
        @media (max-width: 767.98px) {
            .news-image-container {
                height: 300px;
            }
            
            .news-header h1 {
                font-size: 1.4rem;
            }
            
            .news-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
            
            .news-content {
                font-size: 0.85rem;
                line-height: 1.6;
            }
            
            .latest-image-wrapper {
                width: 100px;
                height: 80px;
            }
             .news-date {
    font-size: 0.90rem; /* Smaller size for mobile view */
  }
        }
        
        @media (max-width: 575.98px) {
            .news-header {
                padding: 1.5rem 1.2rem 0.8rem;
            }
            
            .news-header h1 {
                font-size: 1.3rem;
            }
            
            .news-image-container {
                height: 215px;
            }
            
            .news-content-container {
                padding: 1.5rem 1.2rem;
            }
            
            .latest-news-container {
                padding: 1.2rem;
            }
            
            .latest-header {
                font-size: 1.2rem;
            }
            
            .latest-card {
                flex-direction: column;
            }
            
            .latest-image-wrapper {
                width: 100%;
                height: 150px;
            }
        }



    .main-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 11;
   background:rgb(255, 255, 255, 0.9); 
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); /* Bottom shadow */
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
    
   <div class="container py-5">
        <div class="row g-4">
            <!-- Main News Content -->
            <div class="col-lg-8">
                <div class="news-detail-container">
                    <div class="news-header">
                        <h1><?= htmlspecialchars($news['title']) ?></h1>
                        <div class="news-meta">
                            <span class="news-date"><?= date('F j, Y', strtotime($news['event_date'])) ?></span>
                            <span class="badge news-badge"><?= htmlspecialchars($news['category']) ?></span>
                        </div>
                    </div>
                    
                    <?php if (!empty($news['image'])): ?>
                        <div class="news-image-container">
                            <img src="<?= htmlspecialchars(getSupabaseUrl($news['image'], 'news_pic')) ?>" 
                                 alt="<?= htmlspecialchars($news['title']) ?>" 
                                 class="news-image">
                        </div>
                    <?php endif; ?>
                    
                    <div class="news-content-container">
                        <div class="news-content">
                            <?= nl2br(htmlspecialchars($news['full_desc'])) ?>
                        </div>
                        
                        <div class="mt-5 text-center">
                            <a href="news.php" class="btn back-btn">
                                <i class='bx bx-arrow-back me-2'></i> Back to News
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Latest News Sidebar -->
            <div class="col-lg-4">
                <div class="latest-news-container">
                    <h3 class="latest-header">Latest News</h3>
                    
                    <?php if (!empty($latestNews)): ?>
                        <div class="latest-grid">
                            <?php foreach ($latestNews as $item): ?>
                                <a href="news_detail.php?id=<?= $item['news_id'] ?>" class="latest-card">
                                    <div class="latest-image-wrapper">
                                        <?php if (!empty($item['image'])): ?>
                                            <img src="<?= htmlspecialchars(getSupabaseUrl($item['image'], 'news_pic')) ?>" 
                                                 alt="<?= htmlspecialchars($item['title']) ?>" 
                                                 class="latest-image">
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center h-100">
                                                <i class='bx bx-news text-muted' style="font-size: 3rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="latest-content">
                                        <div class="latest-title"><?= htmlspecialchars($item['title']) ?></div>
                                        <div class="latest-date"><?= date('M j, Y', strtotime($item['created_at'])) ?></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class='bx bx-news text-muted' style="font-size: 3rem;"></i>
                            <p class="mt-3">No recent news available</p>
                        </div>
                    <?php endif; ?>
                    
                    <a href="news.php" class="more-btn">
                        View More News <i class='bx bx-chevron-right'></i>
                    </a>
                </div>
            </div>
        </div>
    </div>


  <script>
        // Add hover effect to news images
        document.querySelectorAll('.news-image').forEach(img => {
            img.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.03)';
            });
            
            img.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });
        
        // Add animation to latest news cards
        document.querySelectorAll('.latest-card').forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.5s, transform 0.5s';
            
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 200 + (index * 100));
        });
    </script>
    <?php include 'footer.php'; ?>
