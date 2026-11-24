
<?php

session_start();

require_once "config/database.php";

// Teacher access only
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$teacher_id = $_SESSION["user_id"];

// Get teacher's academic information
$stmt = $conn->prepare(
    "SELECT department
     FROM users
     WHERE id = ? AND role = 'teacher'"
);

$stmt->bind_param("i", $teacher_id);
$stmt->execute();

$teacher_result = $stmt->get_result();
$teacher = $teacher_result->fetch_assoc();
$stmt->close();

if (!$teacher) {
    die("Unable to retrieve teacher information.");
}

$department = $teacher["department"];

// Days of the week
$days = [
    "Sunday",
    "Monday",
    "Tuesday",
    "Wednesday",
    "Thursday",
    "Friday",
    "Saturday"
];

// Initialize an empty routine for every day
$weekly_routine = [];

foreach ($days as $day) {
    $weekly_routine[$day] = [];
}

// Fetch routines assigned to this teacher
$sql = "
    SELECT
        cr.id,
        cr.day_of_week,
        cr.start_time,
        cr.end_time,
        cr.room_number,
        c.course_code,
        c.course_name,
        co.year,
        co.semester,
        co.academic_session

    FROM class_routines cr

    JOIN course_offerings co
        ON cr.offering_id = co.id

    JOIN courses c
        ON co.course_id = c.id

    WHERE co.teacher_id = ?

    ORDER BY
        FIELD(
            cr.day_of_week,
            'Sunday', 'Monday', 'Tuesday',
            'Wednesday', 'Thursday', 'Friday', 'Saturday'
        ),
        cr.start_time
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $teacher_id);
$stmt->execute();

$result = $stmt->get_result();

while ($routine = $result->fetch_assoc()) {
    $weekly_routine[$routine["day_of_week"]][] = $routine;
}

$stmt->close();

$current_day = date("l");

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Class Routine | RUET Connect</title>

    <link rel="stylesheet" href="css/style.css?v=3">

    <style>

        .routine-container {
            width: min(1100px, 94%);
            margin: 35px auto;
        }

        .routine-heading {
            margin-bottom: 25px;
        }

        .routine-heading h2 {
            color: #3D1C24;
            margin-bottom: 8px;
        }

        .routine-heading p {
            color: #777;
            line-height: 1.6;
        }

        .academic-info {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 25px;
        }

        .info-item {
            background: #fff;
            border: 1px solid #e9e3df;
            border-radius: 7px;
            padding: 12px 17px;
            font-size: 14px;
        }

        .info-item strong {
            color: #3D1C24;
        }

        .day-section {
            background: #fff;
            border: 1px solid #e9e3df;
            border-radius: 10px;
            margin-bottom: 18px;
            overflow: hidden;
            box-shadow: 0 3px 12px rgba(0,0,0,0.04);
        }

        .day-heading {
            background: #3D1C24;
            color: #fff;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .day-heading h3 {
            margin: 0;
            font-size: 17px;
        }

        .today-label {
            background: #DAA520;
            color: #211b0c;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .class-list {
            padding: 15px 20px;
        }

        .class-item {
            display: grid;
            grid-template-columns: 150px 1fr 150px;
            gap: 20px;
            align-items: center;
            padding: 17px 5px;
            border-bottom: 1px solid #eee;
        }

        .class-item:last-child {
            border-bottom: none;
        }

        .class-time {
            color: #3D1C24;
            font-weight: 700;
            font-size: 14px;
            line-height: 1.6;
        }

        .course-details h4 {
            color: #3D1C24;
            margin: 0 0 6px;
            font-size: 16px;
        }

        .course-details p {
            color: #777;
            margin: 0;
            font-size: 13px;
            line-height: 1.6;
        }

        .room-details {
            text-align: right;
            color: #555;
            font-size: 13px;
        }

        .room-details strong {
            display: block;
            color: #3D1C24;
            margin-bottom: 5px;
        }

        .empty-day {
            padding: 20px;
            color: #888;
            font-size: 14px;
        }

        .empty-routine {
            background: #fff;
            border: 1px solid #e9e3df;
            border-radius: 10px;
            padding: 45px 20px;
            text-align: center;
            color: #777;
        }

        .empty-routine h3 {
            color: #3D1C24;
            margin-top: 0;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #3D1C24;
            text-decoration: none;
            font-weight: 600;
        }

        @media(max-width: 700px) {

            .class-item {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .room-details {
                text-align: left;
            }

            .day-heading {
                padding: 14px;
            }

            .class-list {
                padding: 12px 15px;
            }

        }

    </style>

</head>

<body class="dashboard-page">

<header class="dashboard-header">

    <div>
        <h1>RUET Connect</h1>
        <p>Teacher Class Routine</p>
    </div>

    <div class="dashboard-user">

        <div class="user-name">
            <?php
            echo e(explode(" ", trim($_SESSION["full_name"]))[0]);
            ?>
        </div>

        <a href="logout.php" class="dashboard-logout">
            Logout
        </a>

    </div>

</header>

<main class="routine-container">

    <a href="teacher.php" class="back-link">
        &larr; Back to Dashboard
    </a>

    <div class="routine-heading">

        <h2>My Weekly Class Routine</h2>

        <p>
            View your regular weekly teaching schedule.
            Your routine is updated by the administration.
        </p>

    </div>

    <div class="academic-info">

        <div class="info-item">
            <strong>Department:</strong>
            <?php echo e($department); ?>
        </div>

        <div class="info-item">
            <strong>Role:</strong>
            Teacher
        </div>

    </div>

    <?php

    $total_classes = 0;

    foreach ($weekly_routine as $classes) {
        $total_classes += count($classes);
    }

    ?>

    <?php if ($total_classes > 0): ?>

        <?php foreach ($days as $day): ?>

            <section class="day-section">

                <div class="day-heading">

                    <h3>
                        <?php echo e($day); ?>
                    </h3>

                    <?php if ($day === $current_day): ?>
                        <span class="today-label">TODAY</span>
                    <?php endif; ?>

                </div>

                <?php if (!empty($weekly_routine[$day])): ?>

                    <div class="class-list">

                        <?php foreach ($weekly_routine[$day] as $class): ?>

                            <div class="class-item">

                                <div class="class-time">

                                    <?php
                                    echo date(
                                        "h:i A",
                                        strtotime($class["start_time"])
                                    );
                                    ?>

                                    <br>

                                    <span style="color:#999;">
                                        to
                                    </span>

                                    <br>

                                    <?php
                                    echo date(
                                        "h:i A",
                                        strtotime($class["end_time"])
                                    );
                                    ?>

                                </div>

                                <div class="course-details">

                                    <h4>
                                        <?php echo e($class["course_code"]); ?>
                                        -
                                        <?php echo e($class["course_name"]); ?>
                                    </h4>

                                    <p>
                                        Year:
                                        <?php echo e($class["year"]); ?>

                                        &nbsp;|&nbsp;

                                        Semester:
                                        <?php echo e($class["semester"]); ?>

                                        <?php if (!empty($class["academic_session"])): ?>
                                            &nbsp;|&nbsp;
                                            Session:
                                            <?php echo e($class["academic_session"]); ?>
                                        <?php endif; ?>
                                    </p>

                                </div>

                                <div class="room-details">

                                    <strong>
                                        Room:
                                        <?php echo e($class["room_number"]); ?>
                                    </strong>

                                    <span>Scheduled Class</span>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="empty-day">
                        No classes scheduled for this day.
                    </div>

                <?php endif; ?>

            </section>

        <?php endforeach; ?>

    <?php else: ?>

        <div class="empty-routine">

            <h3>No Class Routine Available</h3>

            <p>
                The administration has not published any
                class routines for your assigned courses yet.
            </p>

        </div>

    <?php endif; ?>

</main>

</body>

</html>

<?php
$conn->close();
?>
