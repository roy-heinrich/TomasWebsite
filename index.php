
<?php
require_once 'config.php'; 

// Existing queries - converted to PostgreSQL
try {
    $sql = "SELECT * FROM welcome_message ORDER BY id DESC LIMIT 1";
    $stmt = $conn->query($sql);
    $welcome = $stmt->fetch();
    
    $contactSql = "SELECT * FROM contact_info ORDER BY id DESC LIMIT 1";
    $stmt = $conn->query($contactSql);
    $contact = $stmt->fetch();
    
    // New queries for calendar events and downloadables
    $eventSql = "SELECT id, title, short_desc, start_date, end_date FROM calendar_events";
    $stmt = $conn->query($eventSql);
    $eventResult = $stmt->fetchAll();
    
    $downloadSql = "SELECT * FROM downloadables ORDER BY upload_date DESC";
    $stmt = $conn->query($downloadSql);
    $downloadResult = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}

// Default welcome message
$welcomeData = [
    'teacher_name' => 'Mrs. Meliza A. Delgado',
    'teacher_title' => 'Head Teacher-1',
    'paragraph1' => "Welcome! I'm delighted to have you visit our school website. 
        Our school is more than just a place of learning — it's a nurturing community where each child is encouraged to grow, achieve, and dream big.",
    'paragraph2' => "Tomas SM. Bautista Elementary School continues to uphold its mission of guiding young minds with compassion, excellence, and dedication. 
        We take pride in our students, our teachers, and our supportive community that makes every achievement possible.",
    'paragraph3' => "Thank you for being a part of our journey. We invite you to explore and discover what makes our school truly special.",
    'teacher_image' => 'lead1.jpg'
];

if ($welcome) {
    $welcomeData = $welcome;
}

// Default contact info
$contactData = [
    'telephone_primary' => '(123) 456-7890',
    'email_general' => 'tomasbautista910@gmail.com',
    'fb_page' => 'Tomas Bautista ES',
    'fb_link' => '#',
    'address' => 'Fatima, New Washington, Aklan'
];

if ($contact) {
    $contactData = $contact;
}

// Prepare calendar events - now storing entire event objects
$events = [];
if ($eventResult && count($eventResult) > 0) {
    foreach ($eventResult as $row) {
        $events[] = [
            'title' => $row['title'],
            'description' => $row['short_desc'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'] ? $row['end_date'] : $row['start_date'] // Use start date if no end date
        ];
    }
}

// Prepare downloadables
$downloadables = [];
if ($downloadResult && count($downloadResult) > 0) {
    $downloadables = $downloadResult;
}

// Pass events to JavaScript
$eventsJson = json_encode($events);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tomas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/boxicons/2.1.4/css/boxicons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.3/dist/css/splide.min.css" />
   <style> 

  /* Existing styles remain unchanged */
        
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
            transform: translateX(-30px);
        }
        
        .slide-in-right {
            transform: translateX(30px);
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
        
        .staggered-item {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }
        
        .staggered-item.fade-in {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Hero section animation */
        .hero-content h3 {
            transform: translateY(20px);
            opacity: 0;
            transition: transform 0.8s ease, opacity 0.8s ease;
        }
        
        .hero-content h1 {
            transform: translateY(30px);
            opacity: 0;
            transition: transform 0.8s ease 0.3s, opacity 0.8s ease 0.3s;
        }
        
        .hero-content p {
            transform: translateY(30px);
            opacity: 0;
            transition: transform 0.8s ease 0.6s, opacity 0.8s ease 0.6s;
        }
        
        .hero-content .btn {
            transform: translateY(40px);
            opacity: 0;
            transition: transform 0.8s ease 0.9s, opacity 0.8s ease 0.9s;
        }
        
        .hero-content.animate-in h3,
        .hero-content.animate-in h1,
        .hero-content.animate-in p,
        .hero-content.animate-in .btn {
            transform: translateY(0);
            opacity: 1;
        }
        
        /* Prevent overflow issues on mobile */
        @media (max-width: 768px) {
            .animate-on-scroll {
                transform: none !important;
                transition: opacity 0.6s ease-in-out;
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

  .hero {
    background: url('images/tttt.jpg') no-repeat center top;
    background-size: cover; /* Ensure the image covers the whole area */
    background-position: center 10%; /* Shift the image up more to crop the bottom part */
    margin-top: 100px;
    color: white;
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.6);
    height: 100vh; /* Full viewport height */
    overflow: hidden;
    position: relative;
}


.hero::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    width: 100%;
    background-color: rgba(0, 0, 0, 0.4); /* Adjust darkness here */
    z-index: 1;
}

.hero .container {
    position: relative;
    z-index: 2;
}

    .hero h1 {
    font-size: 3.5rem;
    font-weight: 700;
    }

    .hero h3 {
    font-size: 2.5rem;
    font-weight: 500;
    }

    .hero p {
    font-size: 1.2rem;
    
    }

    .custom-btn {
  background: linear-gradient(135deg, rgb(26, 96, 216), rgb(15, 39, 160)) !important;
  border: none !important;
  color: white !important;
  transition: all 0.3s ease;
  border-radius: 50px;
}

.custom-btn:hover {
  background: linear-gradient(135deg, rgb(16, 33, 189), rgb(21, 53, 194)) !important;
  color: white !important;
  transform: scale(1.05);
  box-shadow: 0 6px 14px rgba(0, 0, 0, 0.2);
}


@media (max-width: 768px) {
    .hero {
        text-align: left;
        height: auto;
        padding: 60px 0;
        
    }

    .hero h1 {
        font-size: 2rem;
    }

    .hero h3 {
        font-size: 1.5rem;
        margin-left: 3px; 
    }

    .hero p {
        font-size: 1rem;
    }

    .custom-btn {
        width: auto;
        font-size: 1rem;
        margin-top: 15px;
        padding: 10px 20px;
    }
}

@media (min-width: 768px) and (max-width: 768px) {
    .hero p {
        padding-right: 150px;
    }
}

@media (max-width: 375px) {
    .hero {
       
        padding: 80px 0 30px 0;
    }

    .hero h1 {
        font-size: 1.6rem;
    }

    .hero h3 {
        font-size: 1.2rem;
        margin-bottom: 2px;
    }

    .hero p {
        font-size: .7rem;
    }

    .custom-btn {
        width: auto;
        font-size: 0.8rem;
        padding: 6px 12px;
        
    }
}                            

.welcome-message .welcome-img {
  max-width: 220px;
  transition: all 0.3s ease;
  /* move image slightly to the left on larger screens */
}

@media (min-width: 1440px) {
  .welcome-message .welcome-img {
    max-width: 320px;
    width: 320px;
  }
}

@media (max-width: 768px) {
  .welcome-message .welcome-img {
    max-width: 150px;
    margin-left: 0;
    margin-top: -30px;
  }

  .welcome-message p {
    font-size: 1rem;
  }
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

@media (max-width: 1199px) and (min-width: 992px) {
  .hero h1 {
    white-space: nowrap;
    font-size: 3.5rem; /* adjust as needed to fit */
    overflow-wrap: normal;
  }
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


/* Styling for the no upcoming events message */
.no-events-message {
 
  background: rgba(0, 123, 255, 0.2);
  padding: 1.5rem;
  margin-top: 7rem;
  margin-bottom: 10rem; /* ✅ Extra bottom padding */
  font-size: 1.2rem;
  border-radius: 10px;
  max-width: 600px;
  margin-left: auto;
  margin-right: auto;
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
    -webkit-line-clamp: 7;
     line-clamp: 7;
    -webkit-box-orient: vertical;
  }  

  .splide__slide {
    height: auto;
}

.splide__slide > div {
    height: 100%;
}

  /* Move pagination indicators down */
#upcoming-carousel .splide__pagination {
  bottom: -1.5rem;
}

/* Optional: Ensure arrows don't show on desktop if slides are 3 or less (handled via JS too) */
@media (min-width: 993px) {
  #upcoming-carousel.few-items .splide__arrows {
    display: none;
  }
}

/* Adjust Splide arrow positions */
#upcoming-carousel .splide__arrow--prev {
  left: -2.8rem; /* Move further to the left */
  background-color: rgba(251, 251, 251, 0.94); /* Light transparent background */
}

#upcoming-carousel .splide__arrow--next {
  right: -2.8rem; /* Move further to the right */
  background-color: rgba(247, 239, 239, 0.91); /* Light transparent background */
}

/* Optional: style the arrows for better visibility */
#upcoming-carousel .splide__arrow {
  width: 2.5rem;
  height: 2.5rem;
  color: white;
  transition: background-color 0.3s;
  border-radius: 50%;
}

#upcoming-carousel .splide__arrow:hover {
  background-color: rgba(0, 0, 0, 0.4); /* Slightly darker on hover */
}

@media (max-width: 768px) {
  #upcoming-carousel .splide__arrow {
    display: none !important;
  }
}

@media (max-width: 576px) {
    .news-card .card-title {
      font-size: 1.1rem; /* smaller title */
    }

    .news-card .card-text {
    font-size: 0.85rem !important;
    flex-grow: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 8;
     line-clamp: 8;
    -webkit-box-orient: vertical;
  }

    .news-card .text-muted.small {
      font-size: 0.75rem; /* date info */
    }

  }

  .py-6 {
  padding-top: 3rem !important;
  padding-bottom: 4rem !important;
}

@media (max-width: 768px) {
  .py-6 {
    padding-top: 2rem !important;
    padding-bottom: 3rem !important;
  }
}

/* Add hover effect for More News button */
.more-news-btn:hover {
  transform: translateY(-3px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
  background: linear-gradient(135deg, #5ab0d8, #2570d6) !important;
}

@media (max-width: 768px) {
    .news-card {
        min-height: 300px;
    }
    
    .news-card .card-text {
        -webkit-line-clamp: 7;
        line-clamp: 7;
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


 /* New styles for downloadables and calendar section */
        .downloadables-calendar-section {
            padding: 4rem 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding-top: 6rem;
            padding-bottom: 7rem;
        }
        
        .downloadables-card, .calendar-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }
        
        .downloadables-card:hover, .calendar-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            background: linear-gradient(135deg, #0d6efd 0%, #6f42c1 100%);
            color: white;
            padding: 20px 25px;
            position: relative;
        }
        
        .card-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(to right, #ffd700, #ff6b00);
        }
        
        .card-header h3 {
            margin: 0;
            font-weight: 700;
            display: flex;
            align-items: center;
        }
        
        .card-header h3 i {
            margin-right: 10px;
            font-size: 1.5rem;
        }
        
        .card-body {
            padding: 25px;
        }
        
        /* Downloadables styles - labels as buttons */
        .download-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
       .download-item {
    display: block;
    background: linear-gradient(to right, #4b6cb7, #1710a7ff);                    
    color: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    text-decoration: none;
    position: relative;
    padding: 15px 20px;
    padding-right: 50px;
}

.download-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    background: linear-gradient(to right, #3a56a5, #101c3a);
}

.download-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
    color: white;
}

.download-label span {
    display: flex;
    align-items: center;
    gap: 10px;
}

.download-label i {
    transition: transform 0.3s ease;
}

.download-item:hover .download-label i {
    transform: translateX(5px);
}

.file-info {
    display: flex;
    justify-content: flex-end; /* Align to right */
    margin-top: 10px;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.8);
}

.file-size {
    display: flex;
    align-items: center;
    gap: 5px;
}
        
        /* Calendar styles */
        .calendar-container {
            min-height: 450px;
            overflow: hidden;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .calendar-nav {
            display: flex;
            gap: 10px;
        }
        
        .calendar-nav button {
            background: #0d6efd;
            color: white;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .calendar-nav button:hover {
            background: #0b5ed7;
            transform: scale(1.1);
        }
        
        .calendar-title {
            font-weight: 600;
            color: #212529;
            font-size: 1.1rem;
        }
        
        .calendar-grid {
            padding: 20px;
            flex-grow: 1;
        }
        
        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
            font-weight: 600;
            color: #495057;
            margin-bottom: 10px;
        }
        
        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }
        
        .calendar-day {
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            font-weight: 500;
        }
        
     /*   .calendar-day:hover:not(.empty) {
            background: #e7f1ff;
        }   */

             .calendar-day:hover:not(.empty) {
            background: #4450f4ff;
             color: #ffffffff;
        }
        
        .calendar-day.today {
            background: #f2ea03ff;
            color: white;
            font-weight: 700;
        }
        
        .calendar-day.event {
            color: #0d6efd;
            font-weight: 600;
        }
        
        .calendar-day.event::after {
            content: '';
            position: absolute;
            bottom: 5px;
            width: 6px;
            height: 6px;
            background: #0d6efd;
            border-radius: 50%;
        }
        
        .calendar-day.selected {
            background: linear-gradient(135deg, #0d6efd 0%, #6f42c1 100%);
            color: white;
        }
        
        .event-details {
            padding: 20px;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            min-height: 100px;
        }
        
        .event-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: #212529;
        }
        
        .event-date {
            color: #6c757d;
            font-size: 0.9rem;
        }

          .download-label i.bx-download {
    margin-left: 10px;
}
        
        /* Responsive adjustments */
        @media (max-width: 768px) {

             .downloadables-calendar-section {          
            padding-top: 4rem;
            padding-bottom: 7rem;
        } 

            .downloadables-card {
                margin-top: 30px;
            }
            
            .calendar-card {
                margin-bottom: 30px;
            }
            
            .calendar-container {
                min-height: auto;
            }
            
            .calendar-days {
                grid-template-rows: repeat(6, 1fr);
                min-height: 250px;
            }
            
            .event-details {
                padding: 15px;
            }
        }
        
        @media (max-width: 576px) {
            .card-body {
                padding: 20px 15px;
            }
            
            .download-item {
                padding: 10px 12px;
                padding-right: 35px;
            }
            
            .calendar-grid {
                padding: 15px;
            }
            
            .calendar-day {
                height: 35px;
                font-size: 0.85rem;
            }
            
            .event-details {
                min-height: auto;
            }

             .download-label {
        font-size: 0.9rem;
    }
    
    .download-label i {
        font-size: 1.1rem;
    }
    
    .file-info {
        font-size: 0.75rem;
    }

    .download-label i.bx-download {
    margin-left: 5px;
}

        }
    
               .section-header {
            text-align: center;
            margin-bottom: 3rem;
        }

          /* Add this to the existing style block */
.section-header h2 {
    position: relative;
    display: inline-block;
    padding-bottom: 15px;
}

.section-header h2::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 4px;
    background: linear-gradient(90deg, #0d6efd, #6f42c1);
    border-radius: 2px;
}

/* Optional: Add animation on hover */
.section-header h2:hover::after {
    width: 120px;
    transition: width 0.3s ease;
}
        
/* Scrollable downloadables container */
.download-list-container {
    max-height: 500px;
    overflow-y: auto;
    padding: 25px;
    scrollbar-width: thin; /* For Firefox */
    scrollbar-color: #839cc2ff #e9ecef; /* For Firefox */
}

/* Custom scrollbar for Webkit browsers */
.download-list-container::-webkit-scrollbar {
    width: 4.5px;
    border-radius: 4px;
}

.download-list-container::-webkit-scrollbar-track {
    background: #e9ecef;
    border-radius: 4px;
}

.download-list-container::-webkit-scrollbar-thumb {
    background: #6d86abff;
    border-radius: 4px;
}

.download-list-container::-webkit-scrollbar-thumb:hover {
    background: #8aa5ceff;
}

/* Adjust padding for mobile */
@media (max-width: 576px) {
    .download-list-container {
        padding: 15px;
    }
}
.download-list-container::-webkit-scrollbar {
    width: 2.5px;
    border-radius: 3px;
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

  <!-- Hero Section -->
    <div class="hero vh-80 d-flex align-items-center"> 
        <div class="container">
            <div class="row">
                <div class="col-lg-7 hero-content">
                    <h3>Welcome to</h3>
                    <h1>Tomas SM. Bautista<br>Elementary School</h1>
                    <p>We're here to guide young learners in a safe and caring school where they can grow, explore, and succeed.</p> 
                    <button type="button" class="btn btn-warning px-4 py-2 d-inline-block custom-btn" onclick="scrollToWelcome()">
                        <i class="bx bx-down-arrow-circle me-2" style="font-size: 1.4rem; position: relative; top: 4px;"></i>
                        Learn More
                    </button>                          
                </div>
            </div>
        </div>
    </div>

  <!-- Welcome Message Section -->
    <section class="welcome-message bg-light" style="padding: 4rem 0;">
        <div class="container">
            <div class="row mb-5 animate-on-scroll slide-in-bottom">
                <div class="col text-center">
                    <h1 class="fw-bold" style="color:rgb(14, 89, 201);">WELCOME MESSAGE</h1>
                    <p class="text-muted mt-2">A message from our Head Teacher</p>
                </div>
            </div>
            <div class="row align-items-center justify-content-center text-md-start">
                <div class="col-md-7 mb-4 mb-md-0 order-2 order-md-1 text-start">
                    <p class="lead mb-3 animate-on-scroll slide-in-left delayed-animation"><?= nl2br(htmlspecialchars($welcomeData['paragraph1'])) ?></p>
                    <p class="lead mb-3 animate-on-scroll slide-in-left delayed-animation-2"><?= nl2br(htmlspecialchars($welcomeData['paragraph2'])) ?></p>
                    <p class="lead mb-3 animate-on-scroll slide-in-left delayed-animation-3"><?= nl2br(htmlspecialchars($welcomeData['paragraph3'])) ?></p>
                    <div class="d-none d-md-block">
                        <h4 class="fw-bold mt-4 mb-1 animate-on-scroll slide-in-left delayed-animation-3"><?= htmlspecialchars($welcomeData['teacher_name']) ?></h4>
                        <h6 class="fw-bold text-secondary animate-on-scroll slide-in-left delayed-animation-3"><?= htmlspecialchars($welcomeData['teacher_title']) ?></h6>
                    </div>
                </div>
                <div class="col-md-5 d-flex flex-column align-items-center order-1 order-md-2 mb-4 mb-md-0 animate-on-scroll slide-in-right delayed-animation-2">
                    <?php
// Compute teacher image URL from welc_profile bucket
$teacherImageUrl = '';
if (!empty($welcomeData['teacher_image'])) {
    if (strpos($welcomeData['teacher_image'], 'http') === 0) {
        $teacherImageUrl = $welcomeData['teacher_image'];
    } else {
        // Use welc_profile bucket for teacher image
        $teacherImageUrl = getSupabaseUrl($welcomeData['teacher_image'], 'welc_profile');
    }
} else {
    $teacherImageUrl = 'https://via.placeholder.com/220?text=No+Image';
}
?>
<img src="<?= htmlspecialchars($teacherImageUrl) ?>" alt="<?= htmlspecialchars($welcomeData['teacher_name']) ?>" class="rounded-circle img-fluid welcome-img mb-3 animate-on-scroll slide-in-right">
                    <div class="text-center d-md-none">
                        <h4 class="fw-bold mt-2 mb-1 animate-on-scroll slide-in-right"><?= htmlspecialchars($welcomeData['teacher_name']) ?></h4>
                        <h6 class="fw-bold text-secondary animate-on-scroll slide-in-right"><?= htmlspecialchars($welcomeData['teacher_title']) ?></h6>
                    </div>
                </div>
            </div>
        </div>
    </section>

  <section class="news-section py-6" id="news" style="background: linear-gradient(135deg, #084eb6ff 0%, #4528eaff 100%);"> 
  <div class="container pt-4 pb-4">
    <!-- Section Header -->
    <div class="text-center mb-5 animate-on-scroll slide-in-bottom">
      <h1 class="fw-bold text-white"></i>LATEST NEWS</h1>
      <p class="mt-2 text-white">Stay updated with the latest happenings</p>
    </div>

    <!-- Latest Events Carousel -->
    <div class="mb-5 animate-on-scroll slide-in-bottom delayed-animation-2">
      <div class="splide" id="upcoming-carousel" aria-label="Upcoming Events Carousel">
        <div class="splide__track">
          <ul class="splide__list">
            <!-- Each Slide -->
            <?php
            // Fetch 6 latest active, visible news using PostgreSQL
            try {
                $latestSql = "SELECT * FROM news_tbl 
                            WHERE status = 'Active' AND visibility = 'Yes' 
                            ORDER BY created_at DESC 
                            LIMIT 6";
                $stmt = $conn->query($latestSql);
                $newsItems = $stmt->fetchAll();
            } catch (PDOException $e) {
                die("News query failed: " . $e->getMessage());
            }

            // Category color mapping
            function getCategoryColor($category) {
                switch ($category) {
                    case 'Announcement': return 'background-color: teal;';
                    case 'Sports': return 'background-color: orange;';
                    case 'Academic': return 'background-color: green;';
                    case 'Community': return 'background-color: #6a0dad;';
                    case 'School Event': return 'background-color: blue;';
                    default: return 'background-color: gray;';
                }
            }

            if (count($newsItems) > 0):
                foreach ($newsItems as $row):
            ?>
            <li class="splide__slide">
                <div class="card news-card shadow-sm border-0 rounded-3 position-relative h-100">
                    <span class="badge category-badge text-white" style="<?= getCategoryColor($row['category']); ?>">
                        <?= htmlspecialchars($row['category']); ?>
                    </span>
                    <?php
$newsImageUrl = '';
if (!empty($row['image'])) {
    if (strpos($row['image'], 'http') === 0) {
        $newsImageUrl = $row['image'];
    } else {
        // Use news_pic bucket for news image
        $newsImageUrl = getSupabaseUrl($row['image'], 'news_pic');
    }
} else {
    $newsImageUrl = 'https://via.placeholder.com/200x150?text=No+Image';
}
?>
<img src="<?= htmlspecialchars($newsImageUrl) ?>" class="card-img-top" style="height: 200px; object-fit: cover;">
                    <div class="card-body">
                        <h6 class="card-title fw-bold"><?= htmlspecialchars($row['title']); ?></h6>
                        <p class="text-muted small"><i class='bx bx-calendar'></i> <?= date("F d, Y", strtotime($row['event_date'])); ?></p>
                        <p class="card-text"><?= htmlspecialchars($row['short_info']); ?></p>
                    </div>
                </div>
            </li>
            <?php 
                endforeach;
            else:
            ?>
            <li class="splide__slide">
                <div class="card news-card shadow-sm border-0 rounded-3 text-center text-muted p-5">
                    <p>No latest news available at the moment.</p>
                </div>
            </li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
      <!-- No upcoming events message -->
      <p id="no-upcoming-message" class="no-events-message text-white text-center fw-bold d-none">
        <i class='bx bx-info-circle me-2' style="font-size: 1.5rem; vertical-align: middle;"></i> No upcoming events at the moment.
      </p>
    </div>
    
    <!-- More News Button -->
    <div class="text-center mt-4 animate-on-scroll slide-in-bottom">
      <a href="news.php"
         class="text-white fw-bold px-5 py-2 rounded-pill shadow-sm border-0 more-news-btn"
         style="
           font-size: 1.125rem;
           background: linear-gradient(135deg, #6EC1E4, #2F80ED);
           display: inline-block;
           text-decoration: none;
           transition: all 0.3s ease;
         ">
        More News
      </a>
    </div>
  </div>
</section>

  <!-- Downloadables and Calendar Section -->
    <section class="downloadables-calendar-section" id="downloadables">
        <div class="container">
            <div class="section-header mb-5 animate-on-scroll slide-in-bottom">
                <h2 class="text-center mb-3 fw-bold position-relative pb-3">What You Need</h2>
                <p class="text-muted text-center">Access important school documents and stay updated with our event calendar.</p>
            </div>
            
            <div class="row">
                <!-- Calendar -->
                <div class="col-lg-6 order-lg-2 order-1 mb-4 mb-lg-0 animate-on-scroll slide-in-right delayed-animation-3">
                    <div class="calendar-card">
                        <div class="card-header">
                            <h3><i class='bx bx-calendar-event'></i> School Event Calendar</h3>
                        </div>
                        <div class="card-body">
                            <div class="calendar-container">
                                <div class="calendar-header">
                                    <div class="calendar-nav">
                                        <button id="prev-month"><i class='bx bx-chevron-left'></i></button>
                                        <button id="next-month"><i class='bx bx-chevron-right'></i></button>
                                    </div>
                                    <div class="calendar-title" id="current-month">August 2025</div>
                                </div>
                                
                                <div class="calendar-grid">
                                    <div class="calendar-weekdays">
                                        <div>Sun</div>
                                        <div>Mon</div>
                                        <div>Tue</div>
                                        <div>Wed</div>
                                        <div>Thu</div>
                                        <div>Fri</div>
                                        <div>Sat</div>
                                    </div>
                                    
                                    <div class="calendar-days" id="calendar-days">
                                        <!-- Calendar days populated by JavaScript -->
                                    </div>
                                </div>
                                
                                <div class="event-details">
                                    <div class="event-title" id="event-title">No event selected</div>
                                    <div class="event-date" id="event-date">Select a date with an event</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Downloadables -->
                <div class="col-lg-6 order-lg-1 order-2 animate-on-scroll slide-in-left delayed-animation-3">
                    <div class="downloadables-card ">
                        <div class="card-header">
                            <h3><i class='bx bx-download'></i> Downloadables</h3>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <div class="download-list-container" style="max-height: 500px; overflow-y: auto; padding: 25px;">
                                <div class="download-list">
                                    <?php if (count($downloadables) > 0): ?>
                                        <?php foreach ($downloadables as $item): ?>
                                            <a href="<?= htmlspecialchars($item['file_url']) ?>" class="download-item" target="_blank" download>
                                                <div class="download-label">
                                                    <span>
                                                        <i class='bx bxs-file-doc'></i> 
                                                        <?= htmlspecialchars($item['title_label']) ?>
                                                    </span>
                                                    <i class='bx bx-download'></i>
                                                </div>
                                                <div class="file-info">
                                                    <?php if (!empty($item['file_size'])): ?>
                                                        <span class="file-size"><i class='bx bx-data'></i> <?= htmlspecialchars($item['file_size']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <!-- Fallback message when no downloadables -->
                                        <div class="no-downloads text-center py-5 mt-lg-5">
                                            <i class='bx bx-cloud-download' style="font-size: 4rem; color: #6c757d;"></i>
                                            <h5 class="mt-3">No downloadables available</h5>
                                            <p>Check back later for resources.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-4 col-md-6 mb-4 mb-lg-0">
                <h4 class="footer-title">About Our School</h4>
                <p class="mb-4 footer-desc" style="color: rgba(255,255,255,0.7);">Tomas SM. Bautista Elementary School is committed to providing quality education and holistic development for our students in a nurturing environment.</p>
                <div class="social-icons">
                    <a href="<?= htmlspecialchars($contactData['fb_link']) ?>" target="_blank" title="Visit our Facebook page">
                        <i class="bx bxl-facebook"></i>
                    </a>
                    <a href="#" onclick="sendEmail('<?= htmlspecialchars($contactData['email_general']) ?>')" title="Email us">
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
                    <li><i class="bx bx-map"></i> <?= htmlspecialchars($contactData['address']) ?></li>
                    <li><i class="bx bx-phone"></i> <?= htmlspecialchars($contactData['telephone_primary']) ?></li>
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
function scrollToWelcome() {
  const welcomeSection = document.querySelector('.welcome-message');
  const headerHeight = document.querySelector('.main-header').offsetHeight;
  const offset = -20; // Additional offset if needed
  
  // Calculate the position to scroll to (section position minus header height plus some offset)
  const scrollPosition = welcomeSection.offsetTop - headerHeight - offset;
  
  window.scrollTo({
    top: scrollPosition,
    behavior: 'smooth'
  });
}

  // Initialize Splide with dynamic condition
 document.addEventListener('DOMContentLoaded', function () {
  const splideEl = document.querySelector('#upcoming-carousel');
  const upcomingList = splideEl.querySelector('.splide__list');
  const upcomingSlides = upcomingList.querySelectorAll('.splide__slide');
  const noUpcomingMessage = document.getElementById('no-upcoming-message');

  if (upcomingSlides.length === 0) {
    // Show message and hide the whole carousel
    noUpcomingMessage.classList.remove('d-none');
    splideEl.classList.add('d-none'); 
  } else {
    // Hide the message
    noUpcomingMessage.classList.add('d-none');

    const splideOptions = {
      perPage: 3,
      gap: '1rem',
      breakpoints: {
        992: { perPage: 2 },
        768: { perPage: 1 },
      },
    };

    // Disable loop and arrows if only few slides
    if (upcomingSlides.length <= 3) {
      new Splide('#upcoming-carousel', {
        ...splideOptions,
        type: 'slide',
        arrows: false,
        pagination: true,
        autoplay: false,
      }).mount();
    } else {
      new Splide('#upcoming-carousel', {
        ...splideOptions,
        type: 'loop',
        autoplay: true,
      }).mount();
    }
  }
});

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

    // Pass PHP events to JavaScript as array of objects
    var dbEvents = <?= $eventsJson ?>;
    
    // Calendar functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Current date
        const today = new Date();
        let currentMonth = today.getMonth();
        let currentYear = today.getFullYear();
        
        // DOM elements
        const calendarDays = document.getElementById('calendar-days');
        const currentMonthEl = document.getElementById('current-month');
        const prevMonthBtn = document.getElementById('prev-month');
        const nextMonthBtn = document.getElementById('next-month');
        const eventTitle = document.getElementById('event-title');
        const eventDate = document.getElementById('event-date');
        
        // Function to check if a date is part of any event
        function isEventDate(dateStr) {
            const date = new Date(dateStr);
            for (const event of dbEvents) {
                const startDate = new Date(event.start_date);
                const endDate = new Date(event.end_date);
                
                // Check if date is within event range (inclusive)
                if (date >= startDate && date <= endDate) {
                    return event;
                }
            }
            return null;
        }
        
        // Render calendar
        function renderCalendar() {
            // Clear existing days
            calendarDays.innerHTML = '';
            
            // Set current month text
            currentMonthEl.textContent = new Date(currentYear, currentMonth).toLocaleString('default', { 
                month: 'long', 
                year: 'numeric' 
            });
            
            // Get first day of month and days in month
            const firstDay = new Date(currentYear, currentMonth, 1).getDay();
            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
            
            // Add empty cells for days before the first day
            for (let i = 0; i < firstDay; i++) {
                const emptyDay = document.createElement('div');
                emptyDay.classList.add('calendar-day', 'empty');
                calendarDays.appendChild(emptyDay);
            }
            
            // Add days of the month
            for (let day = 1; day <= daysInMonth; day++) {
                const dayEl = document.createElement('div');
                dayEl.classList.add('calendar-day');
                dayEl.textContent = day;
                
                // Format date for event check
                const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                
                // Check if today
                if (currentYear === today.getFullYear() && 
                    currentMonth === today.getMonth() && 
                    day === today.getDate()) {
                    dayEl.classList.add('today');
                }
                
                // Check if date has event
                const event = isEventDate(dateStr);
                if (event) {
                    dayEl.classList.add('event');
                    
                    // Add click event to show event details
                    dayEl.addEventListener('click', function() {
                        // Remove selected class from all days
                        document.querySelectorAll('.calendar-day').forEach(day => {
                            day.classList.remove('selected');
                        });
                        
                        // Add selected class to clicked day
                        dayEl.classList.add('selected');
                        
                        // Format date range
                        let dateRange;
                        const startDate = new Date(event.start_date);
                        const endDate = new Date(event.end_date);
                        
                        if (event.start_date === event.end_date) {
                            // Single day event
                            dateRange = startDate.toLocaleDateString('en-US', { 
                                weekday: 'long', 
                                month: 'long', 
                                day: 'numeric', 
                                year: 'numeric' 
                            });
                        } else {
                            // Multi-day event
                            dateRange = startDate.toLocaleDateString('en-US', { 
                                month: 'long', 
                                day: 'numeric' 
                            }) + ' to ' + endDate.toLocaleDateString('en-US', { 
                                month: 'long', 
                                day: 'numeric', 
                                year: 'numeric' 
                            });
                        }
                        
                        // Show event details
                        eventTitle.textContent = event.title;
                        eventDate.textContent = dateRange + ' - ' + event.description;
                    });
                }
                
                calendarDays.appendChild(dayEl);
            }
        }
        
        // Previous month button
        prevMonthBtn.addEventListener('click', function() {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            renderCalendar();
        });
        
        // Next month button
        nextMonthBtn.addEventListener('click', function() {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            renderCalendar();
        });
        
        // Initialize calendar
        renderCalendar();
    });

        // Animation on page load for hero section
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                document.querySelector('.hero-content').classList.add('animate-in');
            }, 300);
        });
        
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
            
            // Staggered items (for paragraphs in welcome section)
            document.querySelectorAll('.staggered-item').forEach((el, index) => {
                setTimeout(() => {
                    observer.observe(el);
                }, index * 150);
            });
        });
</script>
<?php include 'TomasChatBot/chatbot_widget.php'; ?>
</body>
</html>