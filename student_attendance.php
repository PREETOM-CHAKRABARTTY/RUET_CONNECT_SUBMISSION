
<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit();
}

require_once "config/database.php";

$student_id = (int)$_SESSION["user_id"];

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// Fetch all attendance records belonging to the logged-in student.
$sql = "SELECT
            ar.status,
            ats.attendance_date,
            co.id AS offering_id,
            co.year,
            co.semester,
            co.academic_session,
            c.course_code,
            c.course_name
        FROM attendance_records ar
        INNER JOIN attendance_sessions ats
            ON ar.session_id = ats.id
        INNER JOIN course_offerings co
            ON ats.offering_id = co.id
        INNER JOIN courses c
            ON co.course_id = c.id
        WHERE ar.student_id = ?
        ORDER BY ats.attendance_date DESC, ats.id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();

$result = $stmt->get_result();

$course_attendance = [];
$attendance_history = [];

while ($row = $result->fetch_assoc()) {

    $offering_id = (int)$row["offering_id"];
    $status = $row["status"];

    // Initialize course statistics.
    if (!isset($course_attendance[$offering_id])) {

        $course_attendance[$offering_id] = [
            "course_code" => $row["course_code"],
            "course_name" => $row["course_name"],
            "academic_session" => $row["academic_session"],
            "year" => $row["year"],
            "semester" => $row["semester"],
            "present" => 0,
            "absent" => 0,
            "late" => 0,
            "total" => 0
        ];
    }

    // Count original attendance statuses.
    $course_attendance[$offering_id][$status]++;
    $course_attendance[$offering_id]["total"]++;

    // Keep individual records for the history section.
    $attendance_history[] = $row;
}

$stmt->close();

// Calculate overall attendance statistics.
$overall_total = 0;
$overall_present = 0;
$overall_absent = 0;
$overall_late = 0;
$overall_effective_present = 0;

foreach ($course_attendance as $offering_id => &$course) {

    $course["effective_present"] = max(
        0,
        $course["present"] - (int)floor($course["late"] / 2)
    );

    $course["percentage"] = $course["total"] > 0
        ? ($course["effective_present"] / $course["total"]) * 100
        : 0;

    $overall_total += $course["total"];
    $overall_present += $course["present"];
    $overall_absent += $course["absent"];
    $overall_late += $course["late"];
    $overall_effective_present += $course["effective_present"];
}

unset($course);

$overall_percentage = $overall_total > 0
    ? ($overall_effective_present / $overall_total) * 100
    : 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Attendance | RUET Connect</title>

    <link rel="stylesheet" href="css/style.css?v=3">

    <style>

        body {
            background: #f5f3f1;
            color: #292526;
        }

        .attendance-container {
            width: 100%;
            max-width: 1250px;
            margin: 0 auto;
            padding: 35px 30px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 22px;
            color: #3D1C24;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .page-heading {
            margin-bottom: 28px;
        }

        .page-heading h1 {
            color: #3D1C24;
            font-size: 29px;
            margin-bottom: 8px;
        }

        .page-heading p {
            color: #777;
            font-size: 14px;
        }

        .overview-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }

        .overview-card {
            background: #fff;
            padding: 23px;
            border-radius: 11px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }

        .overview-card small {
            display: block;
            color: #888;
            font-size: 12px;
            margin-bottom: 12px;
        }

        .overview-card h2 {
            font-size: 27px;
            color: #3D1C24;
        }

        .overview-card p {
            font-size: 12px;
            color: #888;
            margin-top: 7px;
        }

        .section-card {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 27px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }

        .section-card h2 {
            color: #3D1C24;
            font-size: 19px;
            margin-bottom: 20px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
        }

        .attendance-table th {
            background: #3D1C24;
            color: #fff;
            padding: 14px 13px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
        }

        .attendance-table td {
            padding: 15px 13px;
            border-bottom: 1px solid #eee9e9;
            font-size: 13px;
            color: #51484a;
        }

        .attendance-table tbody tr:hover td {
            background: #faf8f7;
        }

        .course-code {
            font-weight: 700;
            color: #3D1C24;
        }

        .percentage {
            font-weight: 700;
            color: #3D1C24;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-present {
            background: #e7f4eb;
            color: #287343;
        }

        .status-absent {
            background: #fae8e8;
            color: #a83232;
        }

        .status-late {
            background: #fff3d7;
            color: #89620b;
        }

        .progress-track {
            width: 100%;
            min-width: 75px;
            height: 7px;
            background: #eee9e9;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 6px;
        }

        .progress-fill {
            height: 100%;
            background: #DAA520;
            border-radius: 10px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 15px;
            color: #888;
            font-size: 14px;
        }

        .calculation-note {
            background: #faf7ef;
            border-left: 4px solid #DAA520;
            padding: 14px 16px;
            color: #66552c;
            font-size: 13px;
            line-height: 1.7;
            margin-bottom: 22px;
        }

        @media(max-width: 900px) {
            .overview-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media(max-width: 600px) {
            .attendance-container {
                padding: 25px 15px;
            }

            .overview-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .overview-card {
                padding: 16px;
            }

            .overview-card h2 {
                font-size: 22px;
            }

            .section-card {
                padding: 18px 14px;
            }
        }

    </style>

</head>

<body>

<div class="attendance-container">

    <a href="student.php" class="back-link">
        &larr; Back to Dashboard
    </a>

    <div class="page-heading">
        <h1>My Attendance</h1>
        <p>Track your attendance performance across your courses.</p>
    </div>


    <!-- Overall Attendance Overview -->

    <div class="overview-grid">

        <div class="overview-card">
            <small>Total Classes</small>
            <h2><?= $overall_total ?></h2>
            <p>Recorded sessions</p>
        </div>

        <div class="overview-card">
            <small>Present</small>
            <h2><?= $overall_present ?></h2>
            <p>Original present records</p>
        </div>

        <div class="overview-card">
            <small>Absent</small>
            <h2><?= $overall_absent ?></h2>
            <p>Original absent records</p>
        </div>

        <div class="overview-card">
            <small>Attendance Percentage</small>
            <h2><?= number_format($overall_percentage, 2) ?>%</h2>
            <p>After late deductions</p>
        </div>

    </div>


    <!-- Course-wise Attendance -->

    <div class="section-card">

        <h2>Course-wise Attendance Summary</h2>

        <div class="calculation-note">
            <strong>Attendance calculation:</strong><br>
            Every 2 late records deduct 1 effective present.
            An odd remaining late record carries forward.

            <br>

            Effective Present =
            Present - FLOOR(Late / 2)

            <br>

            Attendance Percentage =
            (Effective Present / Total Classes) × 100
        </div>

        <?php if (count($course_attendance) > 0): ?>

            <div class="table-wrapper">

                <table class="attendance-table">

                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Session</th>
                            <th>Total</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Late</th>
                            <th>Effective Present</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($course_attendance as $course): ?>

                            <tr>

                                <td>
                                    <div class="course-code">
                                        <?= e($course["course_code"]) ?>
                                    </div>

                                    <?= e($course["course_name"]) ?>
                                </td>

                                <td>
                                    <?= e($course["academic_session"]) ?>
                                    <br>
                                    Year <?= e($course["year"]) ?>,
                                    Semester <?= e($course["semester"]) ?>
                                </td>

                                <td><?= $course["total"] ?></td>

                                <td><?= $course["present"] ?></td>

                                <td><?= $course["absent"] ?></td>

                                <td><?= $course["late"] ?></td>

                                <td>
                                    <strong>
                                        <?= $course["effective_present"] ?>
                                    </strong>
                                </td>

                                <td>

                                    <span class="percentage">
                                        <?= number_format($course["percentage"], 2) ?>%
                                    </span>

                                    <div class="progress-track">
                                        <div
                                            class="progress-fill"
                                            style="width: <?= min(100, max(0, $course["percentage"])) ?>%;"
                                        ></div>
                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty-state">
                No attendance records are available yet.
            </div>

        <?php endif; ?>

    </div>


    <!-- Individual Attendance History -->

    <div class="section-card">

        <h2>Attendance History</h2>

        <?php if (count($attendance_history) > 0): ?>

            <div class="table-wrapper">

                <table class="attendance-table">

                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Date</th>
                            <th>Academic Session</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($attendance_history as $record): ?>

                            <tr>

                                <td>
                                    <div class="course-code">
                                        <?= e($record["course_code"]) ?>
                                    </div>

                                    <?= e($record["course_name"]) ?>
                                </td>

                                <td>
                                    <?= date(
                                        "d M Y",
                                        strtotime($record["attendance_date"])
                                    ) ?>
                                </td>

                                <td>
                                    <?= e($record["academic_session"]) ?>
                                </td>

                                <td>

                                    <span class="status status-<?= e($record["status"]) ?>">
                                        <?= e($record["status"]) ?>
                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty-state">
                Your attendance history will appear here once your teachers
                start recording attendance.
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>
