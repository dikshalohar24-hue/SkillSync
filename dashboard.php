<?php
session_start();
require_once "config/db.php";

// Check login
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION["user_id"];
$name = $_SESSION["name"] ?? "Student";
$role_id = (int)($_SESSION["role_id"] ?? 0);

// Count unread notifications
$unread_notifications = 0;

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM notification
     WHERE user_id = ? AND is_read = 0"
);

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $notification_result = $stmt->get_result();
    $unread_notifications = (int)$notification_result->fetch_assoc()["total"];

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard - SkillSync</title>

    <link rel="stylesheet" href="assets/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f2f5ff;
            font-family: Arial, sans-serif;
            color: #263248;
        }

        nav {
            background: #263b80;
            color: white;
            padding: 16px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        nav h2 {
            margin: 0;
        }

        nav a {
            color: white;
            text-decoration: none;
            margin: 5px 8px;
        }

        nav a:hover {
            text-decoration: underline;
        }

        .container {
            max-width: 1150px;
            margin: 30px auto;
            padding: 0 18px;
        }

        .hero {
            background: white;
            padding: 30px;
            border-radius: 14px;
            margin-bottom: 28px;
            box-shadow: 0 4px 14px #0000000c;
        }

        .hero h1 {
            color: #263b80;
            margin-top: 0;
        }

        .hero p {
            color: #606b80;
            line-height: 1.6;
        }

        .section-title {
            color: #263b80;
            margin-bottom: 20px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            padding: 23px;
            border-radius: 12px;
            box-shadow: 0 4px 12px #0000000c;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .card h3 {
            color: #263b80;
            margin-top: 0;
        }

        .card p {
            color: #606b80;
            line-height: 1.6;
            flex-grow: 1;
        }

        .button {
            display: inline-block;
            background: #263b80;
            color: white;
            padding: 11px 16px;
            border-radius: 7px;
            text-decoration: none;
            margin-top: 10px;
        }

        .button:hover {
            background: #17265b;
        }

        .notification-count {
            display: inline-block;
            background: #dc2626;
            color: white;
            border-radius: 15px;
            padding: 3px 8px;
            font-size: 12px;
            margin-left: 5px;
        }

        .admin-label {
            display: inline-block;
            background: #e0e7ff;
            color: #263b80;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 13px;
            margin-top: 5px;
        }

        @media (max-width: 900px) {
            .cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            nav {
                flex-direction: column;
                align-items: flex-start;
            }

            .cards {
                grid-template-columns: 1fr;
            }

            .hero {
                padding: 22px;
            }
        }
    </style>
</head>

<body>

<nav>
    <h2>SkillSync</h2>

    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="profile.php">Profile</a>
        <a href="notifications.php">Notifications</a>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <div class="hero">

        <h1>
            Welcome, <?php echo htmlspecialchars($name, ENT_QUOTES, "UTF-8"); ?>! 👋
        </h1>

        <h2>SkillSync Student Dashboard</h2>

        <p>
            Learn new skills from other students, share your knowledge,
            and grow together through peer learning.
        </p>

        <?php if ($role_id === 2): ?>
            <span class="admin-label">Administrator</span>
        <?php endif; ?>

    </div>

    <h2 class="section-title">Explore Your Features</h2>

    <div class="cards">

        <div class="card">
            <h3>👤 My Profile</h3>
            <p>View and update your student profile information.</p>
            <a class="button" href="profile.php">Open Profile</a>
        </div>

        <div class="card">
            <h3>💡 Skills I Can Teach</h3>
            <p>Add and manage the skills you want to teach other students.</p>
            <a class="button" href="skills.php">Manage Skills</a>
        </div>

        <div class="card">
            <h3>📚 Learning Goals</h3>
            <p>Select the skills you want to learn from other students.</p>
            <a class="button" href="learning_goals.php">Learning Goals</a>
        </div>

        <div class="card">
            <h3>🕒 My Availability</h3>
            <p>Manage your available days and times for learning sessions.</p>
            <a class="button" href="availability.php">Set Availability</a>
        </div>

        <div class="card">
            <h3>🤝 Find Matches</h3>
            <p>Discover students who can teach skills you want to learn.</p>
            <a class="button" href="matches.php">View Matches</a>
        </div>

        <div class="card">
            <h3>📩 Session Requests</h3>
            <p>View incoming learning requests and manage their status.</p>
            <a class="button" href="requests.php">View Requests</a>
        </div>

        <div class="card">
            <h3>📅 My Sessions</h3>
            <p>View scheduled sessions and manage your learning activities.</p>
            <a class="button" href="sessions.php">View Sessions</a>
        </div>

        <div class="card">
            <h3>🕓 Session History</h3>
            <p>View previous sessions and update their status when appropriate.</p>
            <a class="button" href="session_history.php">Session History</a>
        </div>

        <div class="card">
            <h3>🔄 Reschedule Session</h3>
            <p>Change the date and time of a scheduled session.</p>
            <a class="button" href="reschedule_session.php">Reschedule</a>
        </div>

        <div class="card">
            <h3>⭐ Reviews & Ratings</h3>
            <p>Review completed learning sessions and see feedback features.</p>
            <a class="button" href="reviews.php">Open Reviews</a>
        </div>

        <div class="card">
            <h3>
                🔔 Notifications

                <?php if ($unread_notifications > 0): ?>
                    <span class="notification-count">
                        <?php echo $unread_notifications; ?>
                    </span>
                <?php endif; ?>
            </h3>

            <p>View important updates about requests, sessions, and reviews.</p>
            <a class="button" href="notifications.php">View Notifications</a>
        </div>

        <div class="card">
            <h3>📝 Report a Student</h3>
            <p>Submit a report if you need to bring a student-related concern to the platform.</p>
            <a class="button" href="report.php">Submit Report</a>
        </div>

        <?php if ($role_id === 2): ?>

            <div class="card">
                <h3>🛡️ Admin Reports</h3>
                <p>Review submitted reports and update their resolution status.</p>
                <a class="button" href="admin_reports.php">Manage Reports</a>
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>