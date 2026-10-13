<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

/* Count unique learning goals */
$goalStmt = $conn->prepare(
    "SELECT COUNT(DISTINCT skill_id) AS total_goals
     FROM learning_goal
     WHERE user_id = ?"
);

$goalStmt->bind_param("i", $user_id);
$goalStmt->execute();

$goalRow = $goalStmt->get_result()->fetch_assoc();
$totalGoals = (int) ($goalRow["total_goals"] ?? 0);

$goalStmt->close();

/* Find students who can teach the skills the current user wants */
$sql = "
    SELECT DISTINCT
        u.user_id,
        u.name,
        u.department,
        u.semester,
        s.skill_id,
        s.skill_name
    FROM `user` u
    INNER JOIN user_skill us
        ON u.user_id = us.user_id
    INNER JOIN skill s
        ON us.skill_id = s.skill_id
    INNER JOIN learning_goal lg
        ON lg.skill_id = us.skill_id
       AND lg.user_id = ?
    WHERE u.user_id != ?
      AND u.status = 'Active'
    ORDER BY u.name, s.skill_name
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();

$result = $stmt->get_result();

$matches = [];

/* Group matching skills by student */
while ($row = $result->fetch_assoc()) {

    $otherUserId = (int) $row["user_id"];
    $skillId = (int) $row["skill_id"];

    if (!isset($matches[$otherUserId])) {
        $matches[$otherUserId] = [
            "user_id" => $otherUserId,
            "name" => $row["name"],
            "department" => $row["department"],
            "semester" => $row["semester"],
            "skills" => [],
            "skill_ids" => []
        ];
    }

    if (!in_array(
        $skillId,
        $matches[$otherUserId]["skill_ids"],
        true
    )) {
        $matches[$otherUserId]["skill_ids"][] = $skillId;

        $matches[$otherUserId]["skills"][] = [
            "skill_id" => $skillId,
            "skill_name" => $row["skill_name"]
        ];
    }
}

$stmt->close();

/* Calculate match scores */
foreach ($matches as &$match) {

    $matchedSkills = count($match["skills"]);

    if ($totalGoals > 0) {
        $score = ($matchedSkills / $totalGoals) * 100;
        $match["match_score"] = min(100, round($score));
    } else {
        $match["match_score"] = 0;
    }
}

unset($match);

/* Sort highest score first */
usort($matches, function ($a, $b) {
    return $b["match_score"] <=> $a["match_score"];
});

/* Save match scores and reasons */
$saveSql = "
    INSERT INTO `matches`
        (user_id, matched_user_id, match_score, match_reason, status)
    VALUES (?, ?, ?, ?, 'Pending')
    ON DUPLICATE KEY UPDATE
        match_score = VALUES(match_score),
        match_reason = VALUES(match_reason)
";

$saveStmt = $conn->prepare($saveSql);

if ($saveStmt) {

    foreach ($matches as $match) {

        $matchedUserId = (int) $match["user_id"];
        $matchScore = (float) $match["match_score"];

        $skillNames = [];

        foreach ($match["skills"] as $skill) {
            $skillNames[] = $skill["skill_name"];
        }

        $matchReason =
            "Matching learning goals and teaching skills: " .
            implode(", ", $skillNames);

        $saveStmt->bind_param(
            "iids",
            $user_id,
            $matchedUserId,
            $matchScore,
            $matchReason
        );

        $saveStmt->execute();
    }

    $saveStmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Matches - SkillSync</title>

    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

<nav>
    <h2>SkillSync</h2>

    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="profile.php">Profile</a>
        <a href="learning_goals.php">Learning Goals</a>
        <a href="requests.php">Requests</a>
        <a href="notifications.php">Notifications</a>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <div class="hero">
        <h1>🤝 My Matches</h1>
        <p>
            Students who can teach the skills you want to learn.
        </p>
    </div>

    <div class="cards">

        <?php if (count($matches) > 0): ?>

            <?php foreach ($matches as $match): ?>

                <div class="card">

                    <h3>
                        <?php echo htmlspecialchars(
                            $match["name"],
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </h3>

                    <p>
                        <strong>Match Score:</strong>
                        <?php echo (int) $match["match_score"]; ?>%
                    </p>

                    <p>
                        <strong>Can Teach:</strong>

                        <?php
                        $skillNames = [];

                        foreach ($match["skills"] as $skill) {
                            $skillNames[] = $skill["skill_name"];
                        }

                        echo htmlspecialchars(
                            implode(", ", $skillNames),
                            ENT_QUOTES,
                            "UTF-8"
                        );
                        ?>
                    </p>

                    <p>
                        <strong>Department:</strong>
                        <?php echo htmlspecialchars(
                            $match["department"] ?? "Not specified",
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </p>

                    <p>
                        <strong>Semester:</strong>
                        <?php echo htmlspecialchars(
                            $match["semester"] ?? "Not specified",
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </p>

                    <?php
                    $firstSkillId =
                        (int) $match["skills"][0]["skill_id"];
                    ?>

                    <a
                        class="button"
                        href="session_request.php?receiver_id=<?php
                            echo (int) $match["user_id"];
                        ?>&amp;skill_id=<?php
                            echo $firstSkillId;
                        ?>"
                    >
                        Request Session
                    </a>

                </div>

            <?php endforeach; ?>

        <?php elseif ($totalGoals === 0): ?>

            <div class="card">
                <h3>Add Learning Goals</h3>

                <p>
                    You have not added any learning goals yet.
                    Add the skills you want to learn to find suitable students.
                </p>

                <a class="button" href="learning_goals.php">
                    Add Learning Goals
                </a>
            </div>

        <?php else: ?>

            <div class="card">
                <h3>No Matches Found</h3>

                <p>
                    No active students currently teach the skills
                    in your learning goals. Add more learning goals
                    or check again later.
                </p>
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>

<?php
$conn->close();
?>