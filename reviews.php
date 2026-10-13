<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$message = "";

/* Submit a review */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $session_id = (int) ($_POST["session_id"] ?? 0);
    $rating = (int) ($_POST["rating"] ?? 0);
    $teaching_quality = (int) ($_POST["teaching_quality"] ?? 0);
    $communication = (int) ($_POST["communication"] ?? 0);
    $comment = trim($_POST["comment"] ?? "");

    /* Validate ratings */
    if (
        $rating < 1 || $rating > 5 ||
        $teaching_quality < 1 || $teaching_quality > 5 ||
        $communication < 1 || $communication > 5
    ) {

        $message = "Please select ratings between 1 and 5.";

    } else {

        /* Check that the session is conducted and belongs to this user */
        $check = $conn->prepare(
            "SELECT session_id, teacher_id, learner_id
             FROM `session`
             WHERE session_id = ?
             AND status = 'Conducted'
             AND (teacher_id = ? OR learner_id = ?)"
        );

        $check->bind_param("iii", $session_id, $user_id, $user_id);
        $check->execute();

        $session_result = $check->get_result();

        if ($session_result->num_rows == 1) {

            $session = $session_result->fetch_assoc();

            /* Identify the student receiving the review */
            $reviewed_user_id = (
                (int) $session["teacher_id"] === $user_id
            )
                ? (int) $session["learner_id"]
                : (int) $session["teacher_id"];

            /* Prevent duplicate reviews for the same session */
            $duplicate = $conn->prepare(
                "SELECT review_id
                 FROM review
                 WHERE session_id = ?
                 AND reviewer_id = ?"
            );

            $duplicate->bind_param("ii", $session_id, $user_id);
            $duplicate->execute();

            $duplicate_result = $duplicate->get_result();

            if ($duplicate_result->num_rows > 0) {

                $message = "You have already reviewed this session.";

            } else {

                /* Insert review */
                $insert = $conn->prepare(
                    "INSERT INTO review
                    (
                        session_id,
                        reviewer_id,
                        reviewed_user_id,
                        rating,
                        teaching_quality,
                        communication,
                        comment
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)"
                );

                $insert->bind_param(
                    "iiiiiss",
                    $session_id,
                    $user_id,
                    $reviewed_user_id,
                    $rating,
                    $teaching_quality,
                    $communication,
                    $comment
                );

                if ($insert->execute()) {

                    $message = "Review submitted successfully!";

                    /* Notify the student receiving the review */
                    $notificationTitle = "New Review Received";

                    $notificationMessage =
                        "You have received a new review. Overall rating: "
                        . $rating . "/5.";

                    $notification = $conn->prepare(
                        "INSERT INTO notification
                        (
                            user_id,
                            title,
                            message,
                            reference_type,
                            reference_id,
                            is_read
                        )
                        VALUES (?, ?, ?, 'review', ?, 0)"
                    );

                    if ($notification) {

                        $notification->bind_param(
                            "issi",
                            $reviewed_user_id,
                            $notificationTitle,
                            $notificationMessage,
                            $session_id
                        );

                        $notification->execute();
                        $notification->close();
                    }

                } else {

                    $message = "Could not submit the review.";

                }

                $insert->close();
            }

            $duplicate->close();

        } else {

            $message =
                "Only participants of conducted sessions can submit reviews.";

        }

        $check->close();
    }
}


/* Get conducted sessions available for review */
$stmt = $conn->prepare(
    "SELECT
        se.session_id,
        se.session_date,
        sk.skill_name,
        teacher.name AS teacher_name,
        learner.name AS learner_name

     FROM `session` se

     INNER JOIN skill sk
        ON se.skill_id = sk.skill_id

     INNER JOIN `user` teacher
        ON se.teacher_id = teacher.user_id

     INNER JOIN `user` learner
        ON se.learner_id = learner.user_id

     WHERE (se.teacher_id = ? OR se.learner_id = ?)
       AND se.status = 'Conducted'

       AND NOT EXISTS (
           SELECT 1
           FROM review r
           WHERE r.session_id = se.session_id
             AND r.reviewer_id = ?
       )

     ORDER BY se.session_id DESC"
);

$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();

$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reviews - SkillSync</title>

    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

<nav>

    <h2>SkillSync</h2>

    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="session_history.php">Session History</a>
        <a href="notifications.php">Notifications</a>
        <a href="logout.php">Logout</a>
    </div>

</nav>


<div class="container">

    <div class="hero">

        <h1>⭐ Reviews & Ratings</h1>

        <p>
            Share feedback after completing a learning session.
        </p>

    </div>


    <?php if ($message != "") { ?>

        <div class="message">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php } ?>


    <div class="cards">

        <?php if ($result->num_rows > 0) { ?>

            <?php while ($row = $result->fetch_assoc()) { ?>

                <div class="card">

                    <h3>
                        <?php
                        echo htmlspecialchars($row["skill_name"]);
                        ?>
                    </h3>

                    <p>
                        <strong>Teacher:</strong>
                        <?php
                        echo htmlspecialchars($row["teacher_name"]);
                        ?>
                    </p>

                    <p>
                        <strong>Learner:</strong>
                        <?php
                        echo htmlspecialchars($row["learner_name"]);
                        ?>
                    </p>

                    <p>
                        <strong>Date:</strong>
                        <?php
                        echo htmlspecialchars(
                            $row["session_date"] ?? "Not specified"
                        );
                        ?>
                    </p>


                    <form method="POST">

                        <input
                            type="hidden"
                            name="session_id"
                            value="<?php
                                echo (int) $row["session_id"];
                            ?>"
                        >


                        <label>Overall Rating (1–5)</label>

                        <select name="rating" required>
                            <option value="">Select Rating</option>
                            <option value="1">1 - Poor</option>
                            <option value="2">2 - Fair</option>
                            <option value="3">3 - Good</option>
                            <option value="4">4 - Very Good</option>
                            <option value="5">5 - Excellent</option>
                        </select>


                        <label>Teaching Quality (1–5)</label>

                        <select name="teaching_quality" required>
                            <option value="">Select Rating</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                        </select>


                        <label>Communication (1–5)</label>

                        <select name="communication" required>
                            <option value="">Select Rating</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                        </select>


                        <label>Feedback</label>

                        <textarea
                            name="comment"
                            rows="3"
                            maxlength="2000"
                            placeholder="Write your feedback..."
                        ></textarea>


                        <button type="submit">
                            Submit Review
                        </button>

                    </form>

                </div>

            <?php } ?>

        <?php } else { ?>

            <div class="card">

                <h3>No Reviews Pending</h3>

                <p>
                    Reviews will be available after a session
                    is marked Conducted.
                </p>

            </div>

        <?php } ?>

    </div>

</div>

</body>
</html>