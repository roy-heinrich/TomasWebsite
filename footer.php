


<?php
require_once 'config.php'; 

// Fetch latest contact info from PostgreSQL
$contactSql = "SELECT * FROM contact_info ORDER BY id DESC LIMIT 1";
$contactStmt = $conn->query($contactSql);
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
?>

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
<?php include 'TomasChatBot/chatbot_widget.php'; ?>
</body>
</html>             
