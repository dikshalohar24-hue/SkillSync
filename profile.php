<?php

session_start();
require_once "config/db.php";

// Check login
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$message = "";

// Update profile
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"] ?? "");
    $student_id = trim($_POST["student_id"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $semester = (int) ($_POST["semester"] ?? 0);
    $bio = trim($_POST["bio"] ?? "");

    if ($name === "" || $semester < 1 || $semester > 8) {

        $message = "Please enter a valid name and semester.";

    } else {

        $stmt = $conn->prepare(
            "UPDATE `user`
             SET name = ?,
                 student_id = ?,
                 department = ?,
                 semester = ?,
                 bio = ?
             WHERE user_id = ?"
        );

        $stmt->bind_param(
            "sssisi",
            $name,
            $student_id,
            $department,
            $semester,
            $bio,
            $user_id
        );

        if ($stmt->execute()) {

            $_SESSION["name"] = $name;
            $message = "Profile updated successfully.";

        } else {

            $message = "Profile update failed.";

        }

        $stmt->close();
    }
}


// Get current user data
$stmt = $conn->prepare(
    "SELECT name, email, student_id, department, semester, bio
     FROM `user`
     WHERE user_id = ?"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();


// Get average rating and total reviews received
$ratingSql = "
    SELECT
        COUNT(*) AS total_reviews,
        ROUND(AVG(rating), 1) AS average_rating
    FROM review
    WHERE reviewed_user_id = ?
";

$ratingStmt = $conn->prepare($ratingSql);
$ratingStmt->bind_param("i", $user_id);
$ratingStmt->execute();

$ratingResult = $ratingStmt->get_result();
$ratingData = $ratingResult->fetch_assoc();

$totalReviews = (int) ($ratingData["total_reviews"] ?? 0);
$averageRating = $ratingData["average_rating"] !== null
    ? (float) $ratingData["average_rating"]
    : 0;

$ratingStmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile - SkillSync</title>

    <link rel="stylesheet" href="assets/style.css">
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

        <h1>👤 My Profile</h1>

        <p>View and update your student information.</p>

    </div>


    <!-- Rating Summary -->
    <div class="cards">

        <div class="card">

            <h3>⭐ Average Rating</h3>

            <p>
                <strong>
                    <?php
                    echo $totalReviews > 0
                        ? number_format($averageRating, 1) . " / 5"
                        : "No ratings yet";
                    ?>
                </strong>
            </p>

        </div>


        <div class="card">

            <h3>📝 Total Reviews</h3>

            <p>
                <strong>
                    <?php echo $totalReviews; ?>
                </strong>
            </p>

        </div>

    </div>


    <div class="form-box">

        <h2>Update Your Information</h2>


        <?php if ($message != "") { ?>

            <div class="message">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php } ?>


        <?php if ($user) { ?>

            <form method="POST">

                <label>Full Name</label>

                <input
                    type="text"
                    name="name"
                    maxlength="100"
                    value="<?php echo htmlspecialchars($user["name"] ?? ""); ?>"
                    required
                >


                <label>Email</label>

                <input
                    type="email"
                    value="<?php echo htmlspecialchars($user["email"] ?? ""); ?>"
                    disabled
                >


                <label>Student ID</label>

                <input
                    type="text"
                    name="student_id"
                    value="<?php echo htmlspecialchars($user["student_id"] ?? ""); ?>"
                >


                <label>Department</label>

                <input
                    type="text"
                    name="department"
                    value="<?php echo htmlspecialchars($user["department"] ?? ""); ?>"
                >


                <label>Semester</label>

                <select name="semester" required>

                    <option value="">Select Semester</option>

                    <?php for ($i = 1; $i <= 8; $i++) { ?>

                        <option
                            value="<?php echo $i; ?>"
                            <?php
                            echo ((int) ($user["semester"] ?? 0) === $i)
                                ? "selected"
                                : "";
                            ?>
                        >
                            <?php echo $i; ?> Semester
                        </option>

                    <?php } ?>

                </select>


                <label>Bio</label>

                <textarea
                    name="bio"
                    rows="5"
                    maxlength="2000"
                    placeholder="Tell something about yourself"
                ><?php echo htmlspecialchars($user["bio"] ?? ""); ?></textarea>


                <button type="submit">
                    Update Profile
                </button>

            </form>

        <?php } else { ?>

            <p>Unable to load your profile information.</p>

        <?php } ?>

    </div>

</div>

</body>
</html>