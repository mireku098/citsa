<?php
session_start();
require_once '../app/db.conn.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Get admin info
$admin_id = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT * FROM admins WHERE admin_id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

// Get events with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM events");
$total_events = $stmt->fetch()['total'];
$total_pages = ceil($total_events / $limit);

$stmt = $pdo->prepare("SELECT e.*, u.username as creator_name, u.first_name, u.last_name FROM events e JOIN users u ON e.created_by = u.user_id ORDER BY e.created_at DESC LIMIT ? OFFSET ?");
$stmt->bindParam(1, $limit, PDO::PARAM_INT);
$stmt->bindParam(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$events = $stmt->fetchAll();

// Get upcoming events count
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM events WHERE event_date >= ?");
$stmt->execute([$today]);
$upcoming_events = $stmt->fetch()['total'];

// Get past events count
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM events WHERE event_date < ?");
$stmt->execute([$today]);
$past_events = $stmt->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events Management - CITSA Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1a3c6d;
            --secondary-color: #15325d;
            --accent-color: #2d5a9e;
        }

        body {
            background-color: #f8fafc;
        }

        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            width: 280px;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
        }

        .sidebar .nav-link {
            color: rgba(255,255,255,0.9);
            border-radius: 12px;
            margin: 4px 0;
            padding: 12px 20px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.15);
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
        }

        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(26, 60, 109, 0.1);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            border-radius: 20px 20px 0 0 !important;
            border: none;
            padding: 20px 25px;
        }

        .table th {
            background: #f8fafc;
            border: none;
            font-weight: 600;
            color: var(--primary-color);
            padding: 18px 15px;
        }

        .table td {
            vertical-align: middle;
            padding: 15px;
            border-color: #e9ecef;
        }

        .table tbody tr:hover {
            background-color: rgba(26, 60, 109, 0.05);
        }

        .badge-upcoming {
            background: linear-gradient(135deg, #0891b2, #06b6d4);
            color: white;
        }

        .badge-past {
            background: linear-gradient(135deg, #6b7280, #9ca3af);
            color: white;
        }

        .badge-today {
            background: linear-gradient(135deg, #059669, #10b981);
            color: white;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border: none;
            color: white;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(26, 60, 109, 0.3);
            color: white;
        }

        .stats-card {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .event-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 10px;
        }

        .event-date {
            font-weight: 600;
            color: var(--primary-color);
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <img src="../assets/img/logo.png" alt="CITSA Logo" height="60" class="mb-2">
                        <h5 class="text-white">CITSA Admin</h5>
                        <small class="text-white-50">Computer Science & IT</small>
                    </div>
                    
                    <ul class="nav flex-column px-3">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="bi bi-speedometer2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="bi bi-people"></i>
                                Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="chat_rooms.php">
                                <i class="bi bi-chat-dots"></i>
                                Chat Rooms
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="clubs.php">
                                <i class="bi bi-collection"></i>
                                Clubs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="club_requests.php">
                                <i class="bi bi-clock-history"></i>
                                Club Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="club_members.php">
                                <i class="bi bi-people-fill"></i>
                                Club Members
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="administrators.php">
                                <i class="bi bi-person-badge"></i>
                                Administrators
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="events.php">
                                <i class="bi bi-calendar-event"></i>
                                Events
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="platform.php">
                                <i class="bi bi-gear-wide-connected"></i>
                                Platform
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i>
                                Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="main-content">
                <!-- Page Title -->
                <div class="mb-4">
                    <h1 class="h2">Events Management</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Admin</a></li>
                            <li class="breadcrumb-item active">Events</li>
                        </ol>
                    </nav>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    Event created successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="stats-icon">
                                    <i class="bi bi-calendar-event"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo number_format($total_events); ?></h3>
                                    <p class="mb-0">Total Events</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="stats-icon">
                                    <i class="bi bi-calendar-check"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo number_format($upcoming_events); ?></h3>
                                    <p class="mb-0">Upcoming Events</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="stats-icon">
                                    <i class="bi bi-calendar-x"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo number_format($past_events); ?></h3>
                                    <p class="mb-0">Past Events</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Events Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-calendar-event me-2"></i>
                            All Events
                        </h5>
                        <a href="add_event.php" class="btn btn-primary-custom">
                            <i class="bi bi-calendar-plus me-2"></i>Add Event
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Creator</th>
                                        <th>Date & Time</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events as $event): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if ($event['image']): ?>
                                                <img src="../uploads/events/<?php echo $event['image']; ?>" class="event-image me-3" alt="Event">
                                                <?php else: ?>
                                                <div class="event-image me-3 bg-secondary d-flex align-items-center justify-content-center">
                                                    <i class="bi bi-calendar-event text-white"></i>
                                                </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($event['title']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($event['category']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($event['first_name'] . ' ' . $event['last_name']); ?></div>
                                                    <small class="text-muted">@<?php echo htmlspecialchars($event['creator_name']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="event-date"><?php echo date('M d, Y', strtotime($event['event_date'])); ?></div>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($event['event_time'])); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($event['location']); ?></td>
                                        <td>
                                            <?php
                                            $event_date = $event['event_date'];
                                            $today = date('Y-m-d');
                                            if ($event_date > $today) {
                                                $status_class = 'upcoming';
                                                $status_text = 'Upcoming';
                                            } elseif ($event_date == $today) {
                                                $status_class = 'today';
                                                $status_text = 'Today';
                                            } else {
                                                $status_class = 'past';
                                                $status_text = 'Past';
                                            }
                                            ?>
                                            <span class="badge badge-<?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($event['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="edit_event.php?id=<?php echo $event['event_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteEvent(<?php echo $event['event_id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Events pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function deleteEvent(eventId) {
            if (confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete_event.php';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'event_id';
                input.value = eventId;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
