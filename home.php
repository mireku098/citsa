<?php 
  session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Include database connection and helper files
  	include 'app/db.conn.php';
  	include 'app/helpers/user.php';
  	include 'app/helpers/conversations.php';
    include 'app/helpers/timeAgo.php';
    include 'app/helpers/last_chat.php';
    include 'app/helpers/platform_management.php';

// Get current user data
$user = getUser($_SESSION['user_id'], $pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="nQ3Z1OYKyIL41u1iEHbRdHlb2cHDs8PjDvHC3Slg">

    <!-- Theme Colors -->
    <meta name="theme-color" content="#1E40AF">
    <meta name="msapplication-navbutton-color" content="#1E40AF">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <!-- Primary Meta Tags -->
    <title>Student-Alumni Portal - <?php echo htmlspecialchars(getPlatformSetting('platform_name', $pdo, 'CITSA Connect')); ?></title>
    <meta name="title" content="Student-Alumni Portal - <?php echo htmlspecialchars(getPlatformSetting('platform_name', $pdo, 'CITSA Connect')); ?>">
    <meta name="description" content="Connect with alumni, share experiences, and build your professional network through our student-alumni communication platform.">
    <meta name="keywords" content="Student Portal, Alumni Network, CITSA, UCC, Computer Science, IT, Communication Platform, Professional Networking">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://citsa-connect.org">
    <meta property="og:title" content="Student-Alumni Portal - <?php echo htmlspecialchars(getPlatformSetting('platform_name', $pdo, 'CITSA Connect')); ?>">
    <meta property="og:description" content="Connect with alumni, share experiences, and build your professional network through our student-alumni communication platform.">
    <meta property="og:image" content="https://citsa-connect.org/path/to/your/logo.png">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://citsa-connect.org">
    <meta property="twitter:title" content="Student-Alumni Portal - <?php echo htmlspecialchars(getPlatformSetting('platform_name', $pdo, 'CITSA Connect')); ?>">
    <meta property="twitter:description" content="Connect with alumni, share experiences, and build your professional network through our student-alumni communication platform.">
    <meta property="twitter:image" content="https://citsa-connect.org/path/to/your/logo.png">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Bootstrap Icons CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- AOS Animation -->
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">

      <!-- Cascading stylesheet-->
    <link rel="stylesheet" href="assets/css/styles.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary-color: #1a3c6d;
            --secondary-color: #15325d;
            --accent-color: #2d5a9e;
            --text-dark: #1a3c6d;
            --text-light: #6b7280;
            --bg-light: #f8fafc;
            --white: #ffffff;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-light);
        }

        .main {
            margin-left: 0;
            margin-top: 5px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        body.sidebar-open .main {
            margin-left: 300px;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(26, 60, 109, 0.1);
            transition: all 0.3s ease;
            margin-top: -10px;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(26, 60, 109, 0.15);
        }

        .stats-card {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(26, 60, 109, 0.3);
        }

        .stats-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            opacity: 0.8;
        }

        .pagetitle {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 10px;
        }

        @media (min-width: 992px) {
            .pagetitle {
                margin-top: 10px;
            }
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border: none;
            color: white;
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(26, 60, 109, 0.3);
            color: white;
        }

        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid #e9ecef;
        }

        /* Event Comments System Styles */
        .event-comments-section {
            border-top: 1px solid #dee2e6;
            padding-top: 15px;
        }

        .comments-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }

        .comments-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .comment-item {
            background-color: white;
            border: 1px solid #dee2e6 !important;
            transition: all 0.2s ease;
        }

        .comment-item:hover {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .add-comment-form {
            background-color: white;
            border-radius: 6px;
            padding: 15px;
            border: 1px solid #dee2e6;
        }

        .add-comment-form textarea {
            resize: vertical;
            min-height: 60px;
        }

        .add-comment-form .input-group {
            margin-bottom: 8px;
        }

        .comment-item .btn-outline-danger {
            border-color: #dc3545;
            color: #dc3545;
        }

        .comment-item .btn-outline-danger:hover {
            background-color: #dc3545;
            color: white;
        }

        .comment-item .btn-outline-secondary {
            border-color: #6c757d;
            color: #6c757d;
            background-color: white;
        }

        .comment-item .btn-outline-secondary:hover {
            background-color: #6c757d;
            color: white;
        }

        /* Toast notifications */
        .alert {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        /* Loading states */
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }

        /* Comment Input Styling */
        .comment-input {
            border: 1px solid #dee2e6;
            border-radius: 20px;
            padding: 10px 15px;
            resize: none;
            font-size: 14px;
            line-height: 1.4;
            transition: all 0.2s ease;
            min-height: 40px;
            max-height: 120px;
        }

        .comment-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        .send-btn {
            border-radius: 50%;
            width: 40px;
            height: 40px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .send-btn:hover {
            transform: scale(1.05);
        }

        .add-comment-form {
            background-color: white;
            border-radius: 15px;
            padding: 15px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .comments-container {
                padding: 10px;
            }
            
            .comment-item {
                padding: 10px !important;
            }
            
            .add-comment-form {
                padding: 10px;
            }
            
            .comment-input {
                font-size: 16px; /* Prevent zoom on mobile */
            }
        }
            transition: all 0.3s ease;
        }

        .activity-item:hover {
            background-color: rgba(26, 60, 109, 0.05);
            border-radius: 10px;
            padding-left: 10px;
        }

        .activity-badge {
            margin-right: 15px;
            font-size: 0.8rem;
        }

        .event-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .event-card .card-img-top {
            border-top-left-radius: 0.375rem;
            border-top-right-radius: 0.375rem;
            height: auto !important;
        }

        .event-card .card-img-top img {
            transition: transform 0.3s ease;
        }

        .event-card:hover .card-img-top img {
            transform: scale(1.02);
        }

        .event-card .card-body {
            padding: 1.25rem;
        }

        .event-card .card-footer {
            padding: 0.75rem 1.25rem;
        }

        @media (max-width: 768px) {
            .main {
                margin-left: 0 !important;
                margin-top: 5px !important;
            }
            
            body.sidebar-open .main {
                margin-left: 0 !important;
            }

            .pagetitle {
                margin-bottom: 10px;
            }

            .event-card .card-img-top {
                height: auto !important;
            }

            .event-card .card-body {
                padding: 1rem;
            }

            .event-card .card-footer {
                padding: 0.5rem 1rem;
            }
        }
    </style>
</head>

<body>
    <!-- Include Navigation -->
    <?php include 'nav.php'; ?>

    <!-- ======= Main Content ======= -->
<main id="main" class="main">
        <div class="pagetitle" data-aos="fade-up">
            <div class="container">
                <div class="row">
                    <div class="col-12">
            <h1>Dashboard</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="home.php">Home</a></li>
                    <li class="breadcrumb-item active">Dashboard</li>
                </ol>
            </nav>
                    </div>
                </div>
            </div>
          </div>

        <section class="py-5" data-aos="fade-up" data-aos-delay="100">
            <div class="container">
            <div class="row">
                    <div class="col-12 mb-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5 class="card-title" style="color: var(--primary-color);">Welcome back, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>! 👋</h5>
                            <p class="card-text lead">Welcome to <?php echo htmlspecialchars(getPlatformSetting('platform_name', $pdo, 'CITSA Connect')); ?> - Connect with alumni, share experiences, and build your professional network.</p>
                            <div class="row mt-4">
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <i class="fas fa-users fa-2x text-primary mb-2"></i>
                                        <h6>25 Friends</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <i class="fas fa-comments fa-2x text-success mb-2"></i>
                                        <h6>156 Messages</h6>
                </div>
                    </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <i class="fas fa-graduation-cap fa-2x text-info mb-2"></i>
                                        <h6>1,234 Alumni</h6>
                      </div>
                    </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <i class="fas fa-calendar fa-2x text-warning mb-2"></i>
                                        <h6>8 Events</h6>
                                    </div>
                    </div>
                </div>
              </div>
            </div>
          </div>

                <!-- Stats Cards -->
                <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="stats-card">
                        <i class="bi bi-people"></i>
                        <h3>25</h3>
                        <p class="mb-0">Friends</p>
                        <small class="opacity-75">+12% from last month</small>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="stats-card">
                        <i class="bi bi-chat-left-text"></i>
                        <h3>156</h3>
                        <p class="mb-0">Messages</p>
                        <small class="opacity-75">+8% from last week</small>
                  </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="400">
                    <div class="stats-card">
                        <i class="bi bi-mortarboard"></i>
                        <h3>1,234</h3>
                        <p class="mb-0">Alumni</p>
                        <small class="opacity-75">Active members</small>
                    </div>
                  </div>

                <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="500">
                    <div class="stats-card">
                        <i class="bi bi-calendar-event"></i>
                        <h3>8</h3>
                        <p class="mb-0">Events</p>
                        <small class="opacity-75">This month</small>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="col-lg-8 mb-4" data-aos="fade-up" data-aos-delay="600">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title" style="color: var(--primary-color);">
                                <i class="bi bi-activity me-2"></i>Recent Activity
                            </h5>
                            <div class="activity">
                                <div class="activity-item d-flex">
                                    <div class="activite-label">32 min</div>
                                    <i class='bi bi-circle-fill activity-badge text-success align-self-start'></i>
                                    <div class="activity-content">
                                        New message from <a href="#" class="fw-bold text-dark">John Doe</a>
                                    </div>
                                </div>

                                <div class="activity-item d-flex">
                                    <div class="activite-label">56 min</div>
                                    <i class='bi bi-circle-fill activity-badge text-danger align-self-start'></i>
                                    <div class="activity-content">
                                        New alumni joined: <a href="#" class="fw-bold text-dark">Jane Smith</a>
                                    </div>
                                </div>

                                <div class="activity-item d-flex">
                                    <div class="activite-label">2 hrs</div>
                                    <i class='bi bi-circle-fill activity-badge text-primary align-self-start'></i>
                                    <div class="activity-content">
                                        New event posted: <a href="#" class="fw-bold text-dark">Career Fair 2024</a>
              </div>
            </div>

                                <div class="activity-item d-flex">
                                    <div class="activite-label">1 day</div>
                                    <i class='bi bi-circle-fill activity-badge text-info align-self-start'></i>
                                    <div class="activity-content">
                                        Profile updated by <a href="#" class="fw-bold text-dark">You</a>
          </div>
        </div>

                                <div class="activity-item d-flex">
                                    <div class="activite-label">2 days</div>
                                    <i class='bi bi-circle-fill activity-badge text-warning align-self-start'></i>
                                    <div class="activity-content">
                                        New chat room created: <a href="#" class="fw-bold text-dark">Study Group</a>
                                    </div>
                                </div>
                            </div>
              </div>
            </div>
          </div>

                <!-- Quick Actions -->
                <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="700">
          <div class="card">
            <div class="card-body">
                            <h5 class="card-title" style="color: var(--primary-color);">
                                <i class="bi bi-lightning me-2"></i>Quick Actions
                            </h5>
                            <div class="d-grid gap-3">
                                <a href="friends.php" class="btn btn-primary-custom">
                                    <i class="bi bi-people me-2"></i>Find Friends
                                </a>
                                <a href="private.php" class="btn btn-primary-custom">
                                    <i class="bi bi-chat-dots me-2"></i>Start Chat
                                </a>
                                <a href="profile.php" class="btn btn-primary-custom">
                                    <i class="bi bi-person me-2"></i>Edit Profile
                                </a>
                                <a href="#eventsContainer" class="btn btn-primary-custom">
                                    <i class="bi bi-calendar me-2"></i>View Events
                                </a>
                                <a href="chat_room.php" class="btn btn-primary-custom">
                                    <i class="bi bi-wechat me-2"></i>Join Chat Room
                                </a>
              </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Recent Events Section -->
    <section class="py-5" data-aos="fade-up" data-aos-delay="800">
      <div class="container" id="eventsContainer">
        <div class="row">
          <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
              <h2 class="fw-bold" style="color: var(--primary-color);">
                <i class="bi bi-calendar-event me-3"></i>Recent Events & Announcements
              </h2>

            </div>
          </div>
        </div>
        
        <div class="row" id="recentEventsContainer">
          <!-- Events will be loaded here via AJAX -->
          <div class="col-12 text-center">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading events...</span>
            </div>
          </div>
        </div>
      </div>
    </section>
    </main>

    <?php include 'footer.php'; ?>

 <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    
    <!-- AOS Animation -->
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>

     <!-- Template Main JS File -->
    <script src="assets/js/main.js"></script>

    <script>
        // Initialize AOS
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100
        });



        // Add hover effects to cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Load recent events
            loadRecentEvents();
            
            // Auto-resize comment inputs
            document.addEventListener('input', function(e) {
                if (e.target.classList.contains('comment-input')) {
                    e.target.style.height = 'auto';
                    e.target.style.height = Math.min(e.target.scrollHeight, 120) + 'px';
                }
            });
        });

        // Function to load recent events
        function loadRecentEvents() {
            fetch('get_recent_events.php')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('recentEventsContainer');
                    
                    if (data.length === 0) {
                        container.innerHTML = `
                            <div class="col-12 text-center">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    No events are currently available. Check back later for updates!
                                </div>
                            </div>
                        `;
                        return;
                    }
                    
                    let eventsHTML = '';
                    data.slice(0, 6).forEach(event => {
                        const eventDate = new Date(event.event_date).toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric'
                        });
                        
                        const eventTime = event.event_time ? 
                            `<small class="text-muted d-block"><i class="bi bi-clock me-1"></i>${event.event_time}</small>` : '';
                        
                        const eventImage = event.image_path ? 
                            `<img src="${event.image_path}" alt="Event Image" class="card-img-top" style="width: 100%; height: auto; object-fit: cover; padding: 15px 15px 0 15px;">` : 
                            `<div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px; padding: 15px 15px 0 15px;">
                                <i class="bi bi-calendar-event text-muted" style="font-size: 3rem;"></i>
                            </div>`;
                        
                        eventsHTML += `
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="card event-card">
                                    ${eventImage}
                                    <div class="card-body" style="padding-top: 20px;">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <span class="badge bg-${getEventTypeColor(event.event_type)} text-white">
                                                ${event.event_type.charAt(0).toUpperCase() + event.event_type.slice(1)}
                                            </span>
                                            <small class="text-muted">${eventDate}</small>
                                        </div>
                                        <h5 class="card-title">${event.title}</h5>
                                        ${eventTime}
                                        <p class="card-text">${event.description.substring(0, 100)}${event.description.length > 100 ? '...' : ''}</p>
                                        ${event.location ? `<p class="mb-2"><i class="bi bi-geo-alt me-2 text-primary"></i><small>${event.location}</small></p>` : ''}
                                        
                                        <!-- Comments Section -->
                                        <div class="event-comments-section mt-3">
                                            <div class="d-flex justify-content-center align-items-center mb-2">
                                                <button class="btn btn-sm btn-outline-primary" onclick="toggleComments(${event.event_id})">
                                                    <i class="bi bi-chat-dots me-1"></i>
                                                    <span id="comment-count-${event.event_id}">0</span> Comments
                                                </button>
                                            </div>
                                            
                                            <!-- Comments Container -->
                                            <div id="comments-container-${event.event_id}" class="comments-container" style="display: none;">
                                                <div class="comments-list mb-3" id="comments-list-${event.event_id}">
                                                    <!-- Comments will be loaded here -->
                                                </div>
                                                
                                                <!-- Add Comment Form -->
                                                <div class="add-comment-form">
                                                    <div class="input-group">
                                                        <textarea class="form-control comment-input" id="comment-input-${event.event_id}" 
                                                                  placeholder="Write a comment..." rows="1" maxlength="1000"></textarea>
                                                        <button class="btn btn-primary send-btn" onclick="addComment(${event.event_id})">
                                                            <i class="bi bi-send"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    container.innerHTML = eventsHTML;
                    
                    // Load comment counts for all events after rendering
                    data.slice(0, 6).forEach(event => {
                        loadCommentCount(event.event_id);
                    });
                })
                .catch(error => {
                    console.error('Error loading events:', error);
                    document.getElementById('recentEventsContainer').innerHTML = `
                        <div class="col-12 text-center">
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Unable to load events at this time. Please try again later.
                            </div>
                        </div>
                    `;
                });
        }

        // Function to get event type color
        function getEventTypeColor(eventType) {
            const colors = {
                'event': 'primary',
                'announcement': 'success',
                'meeting': 'warning',
                'workshop': 'info'
            };
            return colors[eventType] || 'secondary';
        }

        // Load comment count for an event
        function loadCommentCount(eventId) {
            fetch(`get_event_comments.php?event_id=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateCommentCount(eventId, data.total_count);
                    } else {
                        updateCommentCount(eventId, 0);
                    }
                })
                .catch(error => {
                    console.error('Error loading comment count:', error);
                    updateCommentCount(eventId, 0);
                });
        }

        // Event Comments System Functions
        
        // Toggle comments visibility
        function toggleComments(eventId) {
            const container = document.getElementById(`comments-container-${eventId}`);
            const isVisible = container.style.display !== 'none';
            
            if (isVisible) {
                container.style.display = 'none';
            } else {
                container.style.display = 'block';
                loadComments(eventId);
            }
        }
        
        // Load comments for an event
        function loadComments(eventId) {
            const commentsList = document.getElementById(`comments-list-${eventId}`);
            const commentCount = document.getElementById(`comment-count-${eventId}`);
            
            // Show loading state
            commentsList.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm text-primary"></div> Loading comments...</div>';
            
            fetch(`get_event_comments.php?event_id=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayComments(eventId, data.comments);
                        updateCommentCount(eventId, data.total_count);
                    } else {
                        commentsList.innerHTML = '<div class="text-muted text-center">No comments yet. Be the first to comment!</div>';
                        updateCommentCount(eventId, 0);
                    }
                })
                .catch(error => {
                    console.error('Error loading comments:', error);
                    commentsList.innerHTML = '<div class="text-danger text-center">Error loading comments. Please try again.</div>';
                });
        }
        
        // Display comments
        function displayComments(eventId, comments) {
            const commentsList = document.getElementById(`comments-list-${eventId}`);
            
            if (comments.length === 0) {
                commentsList.innerHTML = '<div class="text-muted text-center">No comments yet. Be the first to comment!</div>';
                return;
            }
            
            let commentsHTML = '';
            comments.forEach(comment => {
                const timeAgo = formatTimeAgo(comment.created_at);
                const isOwnComment = comment.is_own_comment;
                
                commentsHTML += `
                    <div class="comment-item mb-3 p-3 border rounded" id="comment-${comment.comment_id}">
                                                        <div class="d-flex align-items-start">
                                    <img src="profile/${comment.user.profile_image}" alt="${comment.user.username}" 
                                         class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                                    <div class="flex-grow-1">
                                                                                <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong class="text-primary">@${comment.user.username}</strong>
                                                <small class="text-muted ms-2">${timeAgo}</small>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                ${isOwnComment ? `
                                                    <button class="btn btn-sm btn-outline-danger ms-1" onclick="deleteComment(${comment.comment_id})">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                ` : ''}
                                            </div>
                                        </div>
                                <p class="mb-2 mt-1">${comment.comment_text}</p>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            commentsList.innerHTML = commentsHTML;
        }
        
        // Add a new comment
        function addComment(eventId) {
            const commentInput = document.getElementById(`comment-input-${eventId}`);
            const commentText = commentInput.value.trim();
            
            if (!commentText) {
                alert('Please enter a comment');
                return;
            }
            
            if (commentText.length > 1000) {
                alert('Comment is too long. Maximum 1000 characters allowed.');
                return;
            }
            
            // Disable input and button while posting
            const sendButton = commentInput.nextElementSibling;
            const originalText = sendButton.innerHTML;
            sendButton.disabled = true;
            sendButton.innerHTML = '<div class="spinner-border spinner-border-sm"></div>';
            
            fetch('add_event_comment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    event_id: eventId,
                    comment_text: commentText
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear input
                    commentInput.value = '';
                    
                    // Reload comments to show the new one
                    loadComments(eventId);
                    
                    // Update comment count immediately
                    const currentCount = parseInt(document.getElementById(`comment-count-${eventId}`).textContent);
                    updateCommentCount(eventId, currentCount + 1);
                    
                    // Show success message
                    showToast('Comment posted successfully!', 'success');
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error adding comment:', error);
                alert('Error posting comment. Please try again.');
            })
            .finally(() => {
                // Re-enable input and button
                sendButton.disabled = false;
                sendButton.innerHTML = originalText;
            });
        }
        

        
        // Delete comment
        function deleteComment(commentId) {
            if (!confirm('Are you sure you want to delete this comment?')) {
                return;
            }
            
            fetch('delete_event_comment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    comment_id: commentId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove comment from DOM
                    const commentElement = document.getElementById(`comment-${commentId}`);
                    commentElement.remove();
                    
                    // Update comment count
                    const eventId = getEventIdFromComment(commentId);
                    if (eventId) {
                        const commentCount = document.getElementById(`comment-count-${eventId}`);
                        const currentCount = parseInt(commentCount.textContent);
                        commentCount.textContent = Math.max(0, currentCount - 1);
                    }
                    
                    showToast('Comment deleted successfully!', 'success');
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error deleting comment:', error);
                alert('Error deleting comment. Please try again.');
            });
        }
        
        // Helper functions
        function updateCommentCount(eventId, count) {
            const commentCount = document.getElementById(`comment-count-${eventId}`);
            if (commentCount) {
                commentCount.textContent = count;
            }
        }
        
        function formatTimeAgo(datetime) {
            const now = new Date();
            const commentDate = new Date(datetime);
            const diffMs = now - commentDate;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);
            
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
            if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
            if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
            return commentDate.toLocaleDateString();
        }
        

        
        function getEventIdFromComment(commentId) {
            // Find the parent event container
            const commentElement = document.getElementById(`comment-${commentId}`);
            if (commentElement) {
                const eventContainer = commentElement.closest('.event-card');
                if (eventContainer) {
                    const commentsSection = eventContainer.querySelector('.event-comments-section');
                    if (commentsSection) {
                        const commentsContainer = commentsSection.querySelector('.comments-container');
                        if (commentsContainer) {
                            const id = commentsContainer.id.replace('comments-container-', '');
                            return parseInt(id);
                        }
                    }
                }
            }
            return null;
        }
        

        
        function showToast(message, type = 'info') {
            // Simple toast notification
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(toast);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 3000);
        }
        

    </script>
</body>
</html>

