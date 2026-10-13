<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION["user_id"];
$message = "";
$error = "";

// Mark one notification or all notifications as read
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (isset($_POST["mark_all"])) {

        $stmt = $conn->prepare(
            "UPDATE notification
             SET is_read = 1
             WHERE user_id = ? AND is_read = 0"
        );

        if ($stmt) {
            $stmt->bind_param("i", $user_id);

            if ($stmt->execute()) {
                $message = "All notifications marked as read.";
            } else {
                $error = "Unable to update notifications.";
            }

            $stmt->close();
        } else {
            $error = "Unable to process your request.";
        }

    } elseif (isset($_POST["notification_id"])) {

        $notification_id = (int)$_POST["notification_id"];

        if ($notification_id > 0) {

            $stmt = $conn->prepare(
                "UPDATE notification
                 SET is_read = 1
                 WHERE notification_id = ?
                 AND user_id = ?
                 AND is_read = 0"
            );

            if ($stmt) {
                $stmt->bind_param(
                    "ii",
                    $notification_id,
                    $user_id
                );

                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $message = "Notification marked as read.";
                } else {
                    $error = "Notification is already read or not found.";
                }

                $stmt->close();
            } else {
                $error = "Unable to process your request.";
            }

        } else {
            $error = "Invalid notification.";
        }
    }
}

// Fetch notification counts
$stmt = $conn->prepare(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END), 0)
            AS unread
     FROM notification
     WHERE user_id = ?"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$counts = $stmt->get_result()->fetch_assoc();

$total_notifications = (int)$counts["total"];
$unread_notifications = (int)$counts["unread"];

$stmt->close();

// Fetch current user's notifications
$stmt = $conn->prepare(
    "SELECT
        notification_id,
        title,
        message,
        reference_type,
        reference_id,
        is_read,
        created_at
     FROM notification
     WHERE user_id = ?
     ORDER BY created_at DESC"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

function escape($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// Determine a related page, if supported
function getNotificationLink($type) {

    switch ($type) {
        case "session_request":
            return "requests.php";

        case "session":
            return "session_history.php";

        case "review":
            return "profile.php";

        default:
            return "";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Notifications - SkillSync</title>

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
            padding: 15px 5%;
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
            max-width: 950px;
            margin: 30px auto;
            padding: 0 18px;
        }

        .hero {
            background: white;
            padding: 25px;
            border-radius: 14px;
            margin-bottom: 20px;
            box-shadow: 0 4px 14px #0000000c;
        }

        .hero h1 {
            color: #263b80;
            margin-top: 0;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 22px;
        }

        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 12px #0000000c;
        }

        .summary-card h3 {
            margin-top: 0;
            color: #555;
            font-size: 15px;
        }

        .summary-card strong {
            font-size: 28px;
            color: #263b80;
        }

        .notification {
            background: white;
            padding: 22px;
            border-radius: 12px;
            margin-bottom: 16px;
            box-shadow: 0 4px 12px #0000000c;
            overflow-wrap: anywhere;
        }

        .unread {
            border-left: 5px solid #2563eb;
        }

        .read {
            border-left: 5px solid #16a34a;
        }

        .notification h3 {
            margin-top: 0;
            color: #263b80;
        }

        .notification small {
            color: #666;
        }

        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            background: #e0e7ff;
            color: #263b80;
        }

        .badge.read-badge {
            background: #dcfce7;
            color: #166534;
        }

        button,
        .related-link {
            display: inline-block;
            background: #263b80;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            margin-top: 10px;
            font-size: 14px;
        }

        button:hover,
        .related-link:hover {
            background: #17265b;
        }

        .mark-all-form {
            margin-bottom: 22px;
        }

        .message {
            background: #dcfce7;
            color: #166534;
            padding: 13px;
            border-radius: 7px;
            margin-bottom: 15px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 13px;
            border-radius: 7px;
            margin-bottom: 15px;
        }

        .empty {
            text-align: center;
            background: white;
            padding: 30px;
            border-radius: 12px;
        }

        @media (max-width: 600px) {
            nav {
                flex-direction: column;
                align-items: flex-start;
            }

            .summary {
                grid-template-columns: 1fr;
            }

            .notification {
                padding: 17px;
            }
        }
    </style>
</head>

<body>

<nav>
    <h2>SkillSync</h2>

    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="matches.php">Matches</a>
        <a href="requests.php">Requests</a>
        <a href="sessions.php">Sessions</a>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <div class="hero">
        <h1>🔔 My Notifications</h1>

        <p>
            Stay updated about your SkillSync learning activities.
        </p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message">
            <?php echo escape($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="error">
            <?php echo escape($error); ?>
        </div>
    <?php endif; ?>

    <div class="summary">

        <div class="summary-card">
            <h3>All Notifications</h3>

            <strong>
                <?php echo $total_notifications; ?>
            </strong>
        </div>

        <div class="summary-card">
            <h3>Unread Notifications</h3>

            <strong>
                <?php echo $unread_notifications; ?>
            </strong>
        </div>

    </div>

    <?php if ($unread_notifications > 0): ?>

        <form
            method="POST"
            action="notifications.php"
            class="mark-all-form"
        >
            <button type="submit" name="mark_all" value="1">
                Mark All as Read
            </button>
        </form>

    <?php endif; ?>

    <h2>Recent Notifications</h2>

    <?php if ($result->num_rows > 0): ?>

        <?php while ($row = $result->fetch_assoc()): ?>

            <div class="notification
                <?php echo (int)$row["is_read"] ? "read" : "unread"; ?>">

                <h3>
                    <?php echo escape($row["title"]); ?>
                </h3>

                <p>
                    <?php echo nl2br(escape($row["message"])); ?>
                </p>

                <p>
                    <strong>Type:</strong>

                    <?php
                    echo escape(
                        $row["reference_type"] ?: "General"
                    );
                    ?>
                </p>

                <p>
                    <strong>Date:</strong>
                    <?php echo escape($row["created_at"]); ?>
                </p>

                <p>
                    <strong>Status:</strong>

                    <?php if ((int)$row["is_read"] === 1): ?>

                        <span class="badge read-badge">Read</span>

                    <?php else: ?>

                        <span class="badge">Unread</span>

                    <?php endif; ?>
                </p>

                <?php
                $related_link = getNotificationLink(
                    $row["reference_type"]
                );
                ?>

                <?php if ($related_link !== ""): ?>

                    <a
                        class="related-link"
                        href="<?php echo escape($related_link); ?>"
                    >
                        View Related Page
                    </a>

                <?php endif; ?>

                <?php if ((int)$row["is_read"] === 0): ?>

                    <form
                        method="POST"
                        action="notifications.php"
                    >
                        <input
                            type="hidden"
                            name="notification_id"
                            value="<?php echo (int)$row["notification_id"]; ?>"
                        >

                        <button type="submit">
                            Mark as Read
                        </button>
                    </form>

                <?php endif; ?>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="empty">
            <h3>No Notifications Yet 🔔</h3>

            <p>
                Your notifications will appear here when
                there are updates to your account.
            </p>
        </div>

    <?php endif; ?>

</div>

</body>
</html>