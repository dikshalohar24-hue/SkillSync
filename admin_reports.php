<?php
session_start();
require_once "config/db.php";

// Check login
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// Only Admin can access this page
if ((int)$_SESSION["role_id"] !== 2) {
    die("Access Denied! Only Admin can access this page.");
}

$admin_id = (int)$_SESSION["user_id"];
$message = "";

// Helper function
function escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// Update report status
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $report_id = (int)($_POST["report_id"] ?? 0);
    $status = $_POST["status"] ?? "";
    $resolution = trim($_POST["resolution"] ?? "");

    $allowed_statuses = ["Pending", "Resolved", "Rejected"];

    if ($report_id > 0 && in_array($status, $allowed_statuses, true)) {

        $stmt = $conn->prepare(
            "UPDATE report
             SET status = ?, resolution = ?, moderator_id = ?
             WHERE report_id = ?"
        );

        if ($stmt) {
            $stmt->bind_param(
                "ssii",
                $status,
                $resolution,
                $admin_id,
                $report_id
            );

            if ($stmt->execute()) {
                $stmt->close();

                header("Location: admin_reports.php?updated=1");
                exit();
            }

            $stmt->close();
            $message = "Unable to update the report.";
        } else {
            $message = "Unable to prepare the report update.";
        }

    } else {
        $message = "Please select a valid report status.";
    }
}

// Success message
if (isset($_GET["updated"])) {
    $message = "Report updated successfully!";
}

// Dashboard statistics
$stats = [
    "students" => 0,
    "skills" => 0,
    "matches" => 0,
    "sessions" => 0,
    "reviews" => 0,
    "pending_requests" => 0
];

// Total registered users
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM `user`
     WHERE role_id <> 2"
);

if ($result) {
    $stats["students"] = (int)$result->fetch_assoc()["total"];
}

// Total skills
$result = $conn->query(
    "SELECT COUNT(*) AS total FROM skill"
);

if ($result) {
    $stats["skills"] = (int)$result->fetch_assoc()["total"];
}

// Total matches
$result = $conn->query(
    "SELECT COUNT(*) AS total FROM matches"
);

if ($result) {
    $stats["matches"] = (int)$result->fetch_assoc()["total"];
}

// Total sessions
$result = $conn->query(
    "SELECT COUNT(*) AS total FROM `session`"
);

if ($result) {
    $stats["sessions"] = (int)$result->fetch_assoc()["total"];
}

// Total reviews
$result = $conn->query(
    "SELECT COUNT(*) AS total FROM review"
);

if ($result) {
    $stats["reviews"] = (int)$result->fetch_assoc()["total"];
}

// Pending session requests
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM session_request
     WHERE status = 'Pending'"
);

if ($result) {
    $stats["pending_requests"] = (int)$result->fetch_assoc()["total"];
}

// Fetch all reports
$sql = "SELECT
            r.report_id,
            r.reason,
            r.description,
            r.status,
            r.resolution,
            r.created_at,
            reporter.name AS reporter_name,
            reported.name AS reported_name
        FROM report r
        INNER JOIN `user` reporter
            ON r.reporter_id = reporter.user_id
        INNER JOIN `user` reported
            ON r.reported_user_id = reported.user_id
        ORDER BY r.created_at DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Reports - SkillSync</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f2f5ff;
            margin: 0;
            padding: 20px;
            color: #252b40;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
        }

        h1 {
            text-align: center;
            color: #263b80;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 25px;
        }

        .back {
            display: inline-block;
            margin: 5px 0 20px;
            color: #263b80;
            text-decoration: none;
            font-weight: bold;
        }

        .back:hover {
            text-decoration: underline;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin: 20px 0 35px;
        }

        .stat-card {
            background: white;
            padding: 22px 15px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 3px 12px #00000010;
            border-top: 4px solid #5268c9;
        }

        .stat-card h3 {
            margin: 0 0 12px;
            font-size: 15px;
            color: #555;
        }

        .stat-card .number {
            font-size: 30px;
            font-weight: bold;
            color: #263b80;
        }

        h2 {
            color: #263b80;
            margin-bottom: 20px;
        }

        .message {
            background: #e1f7e8;
            color: #176c35;
            padding: 13px;
            border-radius: 7px;
            margin: 15px 0;
        }

        .error {
            background: #ffe6e6;
            color: #a12626;
            padding: 13px;
            border-radius: 7px;
            margin: 15px 0;
        }

        .report-card {
            background: white;
            padding: 22px;
            margin-bottom: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 10px #00000010;
            overflow-wrap: anywhere;
        }

        .report-card h3 {
            color: #263b80;
            margin-top: 0;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            background: #fff0ce;
            color: #805700;
            font-size: 13px;
            font-weight: bold;
        }

        .status.Resolved {
            background: #d9f7e4;
            color: #176c35;
        }

        .status.Rejected {
            background: #ffe1e1;
            color: #a12626;
        }

        label {
            display: block;
            font-weight: bold;
            margin-top: 12px;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 11px;
            margin: 8px 0;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-family: Arial, sans-serif;
        }

        textarea {
            resize: vertical;
        }

        button {
            background: #263b80;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 8px;
        }

        button:hover {
            background: #17265b;
        }

        .empty {
            text-align: center;
            background: white;
            padding: 30px;
            border-radius: 10px;
        }

        @media (max-width: 700px) {
            body {
                padding: 12px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .stat-card {
                padding: 17px 10px;
            }

            .stat-card .number {
                font-size: 25px;
            }

            .report-card {
                padding: 16px;
            }
        }

        @media (max-width: 380px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <h1>SkillSync Admin Dashboard</h1>

    <p class="subtitle">
        Manage platform activity and student reports
    </p>

    <a class="back" href="dashboard.php">
        &larr; Back to Dashboard
    </a>

    <?php if ($message !== ""): ?>
        <div class="<?php echo isset($_GET["updated"]) ? "message" : "error"; ?>">
            <?php echo escape($message); ?>
        </div>
    <?php endif; ?>

    <h2>Platform Overview</h2>

    <div class="stats-grid">

        <div class="stat-card">
            <h3>Registered Students</h3>
            <div class="number">
                <?php echo $stats["students"]; ?>
            </div>
        </div>

        <div class="stat-card">
            <h3>Total Skills</h3>
            <div class="number">
                <?php echo $stats["skills"]; ?>
            </div>
        </div>

        <div class="stat-card">
            <h3>Total Matches</h3>
            <div class="number">
                <?php echo $stats["matches"]; ?>
            </div>
        </div>

        <div class="stat-card">
            <h3>Total Sessions</h3>
            <div class="number">
                <?php echo $stats["sessions"]; ?>
            </div>
        </div>

        <div class="stat-card">
            <h3>Total Reviews</h3>
            <div class="number">
                <?php echo $stats["reviews"]; ?>
            </div>
        </div>

        <div class="stat-card">
            <h3>Pending Requests</h3>
            <div class="number">
                <?php echo $stats["pending_requests"]; ?>
            </div>
        </div>

    </div>

    <h2>Student Reports</h2>

    <?php if ($result && $result->num_rows > 0): ?>

        <?php while ($row = $result->fetch_assoc()): ?>

            <div class="report-card">

                <h3>
                    Report #<?php echo escape($row["report_id"]); ?>
                </h3>

                <p>
                    <strong>Reported By:</strong>
                    <?php echo escape($row["reporter_name"]); ?>
                </p>

                <p>
                    <strong>Reported Student:</strong>
                    <?php echo escape($row["reported_name"]); ?>
                </p>

                <p>
                    <strong>Reason:</strong>
                    <?php echo escape($row["reason"]); ?>
                </p>

                <p>
                    <strong>Description:</strong><br>
                    <?php echo nl2br(escape($row["description"])); ?>
                </p>

                <p>
                    <strong>Date:</strong>
                    <?php echo escape($row["created_at"]); ?>
                </p>

                <p>
                    <strong>Current Status:</strong>
                    <span class="status <?php echo escape($row["status"]); ?>">
                        <?php echo escape($row["status"]); ?>
                    </span>
                </p>

                <form method="POST" action="admin_reports.php">

                    <input
                        type="hidden"
                        name="report_id"
                        value="<?php echo (int)$row["report_id"]; ?>"
                    >

                    <label>Update Status</label>

                    <select name="status" required>
                        <option value="Pending"
                            <?php echo $row["status"] === "Pending" ? "selected" : ""; ?>>
                            Pending
                        </option>

                        <option value="Resolved"
                            <?php echo $row["status"] === "Resolved" ? "selected" : ""; ?>>
                            Resolved
                        </option>

                        <option value="Rejected"
                            <?php echo $row["status"] === "Rejected" ? "selected" : ""; ?>>
                            Rejected
                        </option>
                    </select>

                    <label>Resolution Details</label>

                    <textarea
                        name="resolution"
                        rows="3"
                        placeholder="Enter action taken or resolution details"
                    ><?php echo escape($row["resolution"]); ?></textarea>

                    <button type="submit">
                        Update Report
                    </button>

                </form>

            </div>

        <?php endwhile; ?>

    <?php elseif ($result): ?>

        <div class="empty">
            <h3>No reports found</h3>
            <p>Student reports will appear here when submitted.</p>
        </div>

    <?php else: ?>

        <div class="error">
            Unable to load reports. Please check the database connection.
        </div>

    <?php endif; ?>

</div>

</body>
</html>