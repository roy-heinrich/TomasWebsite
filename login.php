<?php
require_once 'session.php';

require_once 'config.php'; 

// Handle reset attempts
if (isset($_GET['reset_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    unset($_SESSION['lock_time']);
    exit;
}

// Initialize login attempts counter
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['lock_time'] = 0;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Check if login is locked
    if ($_SESSION['login_attempts'] >= 3 && (time() - $_SESSION['lock_time']) < 30) {
        $_SESSION['lock_remaining'] = 30 - (time() - $_SESSION['lock_time']);
        $_SESSION['notification'] = [
            'type' => 'lock',
            'message' => 'Account locked! Please wait'
        ];
    } else {
        // Reset lock if time expired
        if ($_SESSION['login_attempts'] >= 3) {
            $_SESSION['login_attempts'] = 0;
            unset($_SESSION['lock_time']);
        }

        try {
            // Query database using PDO
            $sql = "SELECT * FROM staff_tbl 
                    WHERE username = :username 
                    AND user_role = :role";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':role', $role);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user['status'] === 'Disable') {
                    $_SESSION['notification'] = [
                        'type' => 'error',
                        'message' => 'Account is inactive!'
                    ];
                } elseif (password_verify($password, $user['password'])) {
              // Successful login
              $_SESSION['login_attempts'] = 0;
              unset($_SESSION['lock_time']);

              // Regenerate session id to prevent fixation and set fingerprint/timestamps
              if (function_exists('session_regenerate_id')) session_regenerate_id(true);

              // Store user data in session
              $_SESSION['user'] = [
                        'id' => $user['id'],
                        'fullname' => $user['fullname'],
                        'email' => $user['email'],
                        'username' => $user['username'],
                        'user_role' => $user['user_role'],
                        'advisory_section' => $user['advisory_section'],
                        'profile_image' => $user['profile_image'],
                         'is_superadmin' => $user['is_superadmin']
                    ];

                    // Set fingerprint and timestamps
                    if (function_exists('session_set_fingerprint')) session_set_fingerprint();

                    // Redirect based on role
                    if ($user['user_role'] === 'Admin') {
                        header('Location: Admin_Dashboard.php');
                        exit;
                    } elseif ($user['user_role'] === 'Teacher') {
                        header('Location: Teacher_Account.php');
                        exit;
                    }
                } else {
                    // Invalid password
                    $_SESSION['login_attempts']++;
                    if ($_SESSION['login_attempts'] >= 3) {
                        $_SESSION['lock_time'] = time();
                        $_SESSION['notification'] = [
                            'type' => 'lock',
                            'message' => 'Account locked! Please wait'
                        ];
                    } else {
                        $_SESSION['notification'] = [
                            'type' => 'error',
                            'message' => 'Invalid credentials! Attempts left: ' . (3 - $_SESSION['login_attempts'])
                        ];
                    }
                }
            } else {
                // User not found
                $_SESSION['login_attempts']++;
                if ($_SESSION['login_attempts'] >= 3) {
                    $_SESSION['lock_time'] = time();
                    $_SESSION['notification'] = [
                        'type' => 'lock',
                        'message' => 'Account locked! Please wait'
                    ];
                } else {
                    $_SESSION['notification'] = [
                        'type' => 'error',
                        'message' => 'Invalid credentials! Attempts left: ' . (3 - $_SESSION['login_attempts'])
                    ];
                }
            }
        } catch (PDOException $e) {
            $_SESSION['notification'] = [
                'type' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Bautista Login Page</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <style>

    body, html {
      height: 100%;
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      overflow: hidden;
    }
    .background-wrapper {
      position: fixed;
      top: 0;
      left: 0;
      height: 100%;
      width: 100%;
      background: url('images/webblue1.webp') no-repeat center center fixed;
      background-size: cover;
      z-index: -2;
    }
    .overlay {
      position: fixed;
      top: 0;
      left: 0;
      height: 100%;
      width: 100%;
      background-color: rgba(0, 0, 0, 0.1);
      z-index: -1;
    }
    .login-container {  
      background-color: rgba(226, 235, 238, 0.95); 
      border-radius: 20px;
      padding: 40px 30px;
      max-width: 420px;
      width: 100%;
      margin: 0 15px; /* Ensures spacing on mobile */
      box-shadow: 0 10px 25px rgba(0,0,0,0.3);
      text-align: center;
      animation: fadeIn 1s ease;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-30px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .login-logo img {
      max-width: 120px;
      margin-bottom: 15px;
      transition: all 0.3s ease;
    }
    .login-header {
      font-weight: bold;
      font-size: 1.8rem;
      margin-bottom: 20px;
      color: #0b5394;
    }
    .form-control, .form-select {
      border-radius: 10px;
      height: 45px;
    }
    .form-control:focus, .form-select:focus {
      box-shadow: 0 0 0 0.2rem rgba(11, 83, 148, 0.25);
      border-color: #0b5394;
    }
    .btn-login {
      background-color: #0b5394;
      color: white;
      border-radius: 50px;
      font-weight: 500;
    }
    .btn-login:hover {
      background-color: #06335f;
      color: white;
    }
    .separator {
      height: 1px;
      background-color: #ccc;
      margin: 20px 0;
    }
    .form-footer {
      font-size: 0.9rem;
      color: #555;
      margin-top: 15px;
    }
    .form-footer a {
      color: #0b5394;
      text-decoration: none;
    }
    .form-footer a:hover {
      text-decoration: underline;
    }

    /* Responsive Enhancements */
    @media (max-width: 576px) {
      .login-container {
        padding: 30px 20px;
         
      }
      .login-logo img {
        max-width: 100px;
      }
    }


.btn-back {
      background-color:rgb(24, 127, 218);
      color: white;
      border-radius: 50px;
      font-weight: 500;
    }
    .btn-back:hover {
      background-color:rgb(17, 116, 216);
      color: white;
    }

     .notification-modal .modal-header-success {
            background-color: #4CAF50;
            color: white;
        }
        .notification-modal .modal-header-error {
            background-color: #f44336;
            color: white;
        }
        .notification-modal .modal-header-lock {
            background-color: #FF9800;
            color: white;
        }
        #countdown {
            font-size: 1.5rem;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
        }
  </style>
</head>
<body>
  <div class="background-wrapper"></div>
  <div class="overlay"></div>
  <div class="d-flex justify-content-center align-items-center vh-100">
    <div class="login-container">
      <div class="login-logo">
        <img src="bautista.png" alt="School Logo">
      </div>

      <div class="login-header">Staff Login</div>

     
        <form method="POST">
        <div class="mb-3">
          <input type="text" class="form-control" name="username" placeholder="Username" required>
        </div>
        <div class="mb-3 position-relative">
          <input type="password" class="form-control" name="password" id="password" placeholder="Password" required>
          <i class='bx bx-show position-absolute end-0 top-50 translate-middle-y me-3' id="togglePassword" style="cursor: pointer;"></i>
        </div>
        <div class="mb-3">
          <select class="form-select" name="role" required>
            <option value="" selected disabled>Select Role</option>
            <option value="Admin">Admin</option>
            <option value="Teacher">Teacher</option>
          </select>
        </div>

        <div class="separator"></div>
        <button type="submit" class="btn btn-login w-100">Login</button>
        <a href="index.php" class="btn btn-back w-100 mt-2">Back</a>
      </form>

      <div class="form-footer">
        <span>Forgot your password? <a href="#" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">Click here</a></span><br>
        <span>Need help? Contact your admin</span>
      </div>
    </div>
  </div>


  <!-- Place this in your HTML body where the login container is -->
<div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="forgotPasswordModalLabel">Reset Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="step1">
          <div class="mb-3">
            <label for="emailInput" class="form-label" >Enter your Email</label>
            <input type="email" class="form-control" id="emailInput" placeholder="email@example.com" required>
          </div>
          <div class="d-flex align-items-center gap-2">
            <input type="text" class="form-control" id="verificationCode" placeholder="Enter Code" required>
            <button class="btn btn-primary" id="sendCodeBtn">Send Code</button>
          </div>
          <div class="mt-2 text-muted small">Code will expire in 5 minutes.</div>
          <button class="btn btn-success w-100 mt-3 rounded-pill" id="verifyCodeBtn">OK</button>
        </div>

        <div id="step2" class="d-none">
          <div class="alert alert-info small mb-3">
            <i class="fas fa-info-circle me-2"></i>
            Password must be at least 10 characters long and include:
            <ul class="mb-0 mt-1">
              <li>1 uppercase letter</li>
              <li>1 number</li>
              <li>1 special character</li>
            </ul>
          </div>
          <div class="mb-3 position-relative">
            <input type="password" class="form-control" id="newPassword" placeholder="Enter Password">
            <i class='bx bx-show position-absolute end-0 top-50 translate-middle-y me-3 toggle-new-pass' style="cursor:pointer;"></i>
          </div>
          <div class="mb-3 position-relative">
            <input type="password" class="form-control" id="confirmPassword" placeholder="Confirm Password">
            <i class='bx bx-show position-absolute end-0 top-50 translate-middle-y me-3 toggle-confirm-pass' style="cursor:pointer;"></i>
          </div>
          <button class="btn btn-primary w-100 rounded-pill" id="resetPasswordBtn">Reset Password</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Alert Modal -->
<div class="modal fade" id="alertModal" tabindex="-1" aria-labelledby="alertModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-header border-0">
        <h5 class="modal-title w-100" id="alertModalLabel">Notification</h5>
      </div>
      <div class="modal-body">
  <div id="alertModalBody" class="fw-bold fs-6"></div>
  <!-- Message will be injected here -->
</div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-primary w-100" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>


 <!-- Notification Modal -->
    <div class="modal fade notification-modal" id="notificationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" id="modalHeader">
                    <h5 class="modal-title" id="modalTitle">Notification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalMessage"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>


      <!-- Lock Modal -->
    <div class="modal fade" id="lockModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header notification-modal modal-header-lock">
                    <h5 class="modal-title">Login Locked</h5>
                </div>
                <div class="modal-body text-center">
                    <p>Too many failed attempts. Please wait:</p>
                    <div id="countdown">30 seconds</div>
                </div>
            </div>
        </div>
    </div>


<!-- Replace your existing loading overlay with this code -->
<div id="loginLoadingOverlay">
  <div class="loading-content">
    <div class="logo-container">
      <img src="images/hdd.png" alt="Loading..." class="logo-main">
    </div>
    
    <div class="loading-dots">
      <div></div>
      <div></div>
      <div></div>
    </div>
    
    <div class="loading-text">Logging in...</div>
  </div>
</div>

<style>
/* New Loading Overlay Styles */
#loginLoadingOverlay {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0, 0, 0, 0.85);
  z-index: 9999;
  align-items: center;
  justify-content: center;
  flex-direction: column;
}

.loading-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.logo-container {
  position: relative;
  width: 120px;
  height: 120px;
  margin-bottom: 25px;
}

.logo-main {
  width: 100%;
  height: 100%;
  object-fit: contain;
  animation: logoFade 2s infinite ease-in-out;
}

.loading-dots {
  display: flex;
  margin-top: 20px;
}

.loading-dots div {
  width: 12px;
  height: 12px;
  margin: 0 5px;
  background-color: #fff;
  border-radius: 50%;
  animation: bounce 1.4s infinite ease-in-out both;
}

.loading-dots div:nth-child(1) { animation-delay: -0.32s; }
.loading-dots div:nth-child(2) { animation-delay: -0.16s; }

.loading-text {
  color: white;
  font-size: 1.2rem;
  font-weight: 500;
  margin-top: 20px;
  letter-spacing: 1px;
}

@keyframes logoFade {
  0%, 100% { 
    opacity: 0.6;
    transform: scale(0.95);
  }
  50% { 
    opacity: 1;
    transform: scale(1);
  }
}

@keyframes bounce {
  0%, 80%, 100% { 
    transform: scale(0);
    opacity: 0.5;
  }
  40% { 
    transform: scale(1);
    opacity: 1;
  }
}
</style>


  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    if (togglePassword && passwordInput) {
      togglePassword.addEventListener('click', function () {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.classList.toggle('bx-show');
        this.classList.toggle('bx-hide');
      });
    }
  </script>



 <script>
        // Handle notifications
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['notification'])): ?>
                <?php
                $notification = $_SESSION['notification'];
                unset($_SESSION['notification']);
                ?>
                
                <?php if ($notification['type'] === 'error' || $notification['type'] === 'success'): ?>
                    const modal = new bootstrap.Modal(document.getElementById('notificationModal'));
                    document.getElementById('modalHeader').className = 
                        'modal-header notification-modal modal-header-<?= $notification['type'] ?>';
                    document.getElementById('modalTitle').textContent = 
                        '<?= $notification['type'] === 'error' ? "Error" : "Success" ?>';
                    document.getElementById('modalMessage').textContent = '<?= $notification['message'] ?>';
                    modal.show();
                <?php elseif ($notification['type'] === 'lock'): ?>
                    const lockModal = new bootstrap.Modal(document.getElementById('lockModal'));
                    lockModal.show();
                    
                    // Start countdown
                    let seconds = <?= $_SESSION['lock_remaining'] ?? 30 ?>;
                    const countdownElement = document.getElementById('countdown');
                    
                    const countdownInterval = setInterval(() => {
                        seconds--;
                        countdownElement.textContent = `${seconds} seconds`;
                        
                        if (seconds <= 0) {
                            clearInterval(countdownInterval);
                            
                            // Reset attempts on the server
                            fetch('<?= $_SERVER['PHP_SELF'] ?>?reset_attempts=true')
                                .then(() => {
                                    lockModal.hide();
                                });
                        }
                    }, 1000);
                <?php endif; ?>
            <?php endif; ?>
        });
  </script>


  <!-- Trigger modal script -->
<script>

function showAlert(message, type = 'info', options = {}) {
  const alertBody = document.getElementById('alertModalBody');
  const alertModalEl = document.getElementById('alertModal');
  const okButton = alertModalEl.querySelector('.modal-footer .btn');

  // Reset classes
  alertBody.className = 'fw-bold fs-6';
  okButton.className = 'btn w-100'; // Reset button classes

  // Apply colors based on alert type
  if (type === 'success') {
    alertBody.classList.add('text-success');
    okButton.classList.add('btn-success');
  } else if (type === 'error') {
    alertBody.classList.add('text-danger');
    okButton.classList.add('btn-danger');
  } else {
    alertBody.classList.add('text-secondary');
    okButton.classList.add('btn-secondary');
  }

  alertBody.innerText = message;

  // Control visibility of the OK button
  const footer = alertModalEl.querySelector('.modal-footer');
  footer.classList.toggle('d-none', options.hideButton || false);

  // Show the modal
  const alertModal = new bootstrap.Modal(alertModalEl);
  alertModal.show();
}



  let countdown;
  document.getElementById('sendCodeBtn').addEventListener('click', function () {
  const email = document.getElementById('emailInput').value;
  const btn = this;

  if (!email) {
    showAlert("Please enter an email.", 'error');
    return;
  }

  // Add spinner
  btn.disabled = true;
  const originalText = btn.innerHTML;
  btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Sending...`;

  fetch('send_code.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showAlert("Code sent!", 'success');

      let seconds = 300;
      const countdown = setInterval(() => {
        seconds--;
        btn.innerText = `Wait ${seconds}s`;
        if (seconds <= 0) {
          clearInterval(countdown);
          btn.disabled = false;
          btn.innerHTML = originalText;
        }
      }, 1000);
    } else {
      btn.disabled = false;
      btn.innerHTML = originalText;
      showAlert(data.message || "Failed to send code.", 'error');
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerHTML = originalText;
    showAlert("Something went wrong.", 'error');
  });
});


  document.getElementById('verifyCodeBtn').addEventListener('click', function () {
    const btn = this;
    const email = document.getElementById('emailInput').value;
    const code = document.getElementById('verificationCode').value;

    // Simple validation
    if (!email || !code) return showAlert('Please enter both email and code.', 'error');

    // Set loading state
    btn.disabled = true;
    const originalText = btn.innerHTML;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Verifying...`;

    fetch('verify_code.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, code })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        document.getElementById('step1').classList.add('d-none');
        document.getElementById('step2').classList.remove('d-none');
      } else {
        showAlert("Invalid or expired code.", 'error');
      }
    })
    .catch(() => {
      showAlert('Network error. Please try again.', 'error');
    })
    .finally(() => {
      // restore
      btn.disabled = false;
      btn.innerHTML = originalText;
    });
  });

  document.getElementById('resetPasswordBtn').addEventListener('click', function () {
    const btn = this;
    const email = document.getElementById('emailInput').value;
    const password = document.getElementById('newPassword').value;
    const confirm = document.getElementById('confirmPassword').value;

    if (password !== confirm) return showAlert("Passwords do not match.", 'error');

    const pattern = /^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]).{10,}$/;
    if (!pattern.test(password)) return showAlert("Password does not meet requirements.", 'error');

    // Set loading state
    btn.disabled = true;
    const originalText = btn.innerHTML;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Resetting...`;

    fetch('reset_password.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        showAlert(data.message, 'success');

        // Wait for user to click OK, then redirect to login
        const alertModalEl = document.getElementById('alertModal');
        alertModalEl.addEventListener('hidden.bs.modal', function handler() {
          window.location.href = 'login.php';
          alertModalEl.removeEventListener('hidden.bs.modal', handler); // prevent multiple triggers
        });

      } else {
        showAlert(data.message || "Reset failed.", 'error');
      }
    })
    .catch(() => {
      showAlert('Network error. Please try again.', 'error');
    })
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = originalText;
    });

  });

  document.querySelector('.toggle-new-pass').addEventListener('click', function() {
    const field = document.getElementById('newPassword');
    field.type = field.type === 'password' ? 'text' : 'password';
    this.classList.toggle('bx-show');
    this.classList.toggle('bx-hide');
  });

  document.querySelector('.toggle-confirm-pass').addEventListener('click', function() {
    const field = document.getElementById('confirmPassword');
    field.type = field.type === 'password' ? 'text' : 'password';
    this.classList.toggle('bx-show');
    this.classList.toggle('bx-hide');
  });



  document.querySelector('form').addEventListener('submit', function(e) {
  // Show loading overlay
  document.getElementById('loginLoadingOverlay').style.display = 'flex';
});
</script>


</body>
</html>   