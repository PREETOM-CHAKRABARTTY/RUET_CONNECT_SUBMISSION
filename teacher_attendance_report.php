
<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit();
}

require_once "config/database.php";

$teacher_id = (int) $_SESSION["user_id"];

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// Fetch only this teacher's course offerings
$offerings = [];

$sql = "SELECT
            co.id AS offering_id,
            co.year,
            co.semester,
            co.academic_session,
            c.course_code,
            c.course_name,
            c.department
        FROM course_offerings co
        INNER JOIN courses c ON co.course_id = c.id
        WHERE co.teacher_id = ?
        ORDER BY co.year ASC, co.semester ASC, c.course_code ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $offerings[] = $row;
}

$stmt->close();

// Selected course offering
$selected_offering_id = 0;
$selected_offering = null;
$report_error = "";

if (isset($_GET["offering_id"]) && ctype_digit((string)$_GET["offering_id"])) {
    $requested_id = (int) $_GET["offering_id"];

    foreach ($offerings as $offering) {
        if ((int)$offering["offering_id"] === $requested_id) {
            $selected_offering_id = $requested_id;
            $selected_offering = $offering;
            break;
        }
    }

    if (!$selected_offering) {
        $report_error = "The selected course offering is invalid or is not assigned to your account.";
    }
}

// Report data
$students = [];
$total_sessions = 0;
$total_students = 0;
$total_present = 0;
$total_absent = 0;
$total_late = 0;
$total_effective_present = 0;
$overall_percentage = 0;

if ($selected_offering) {

    // Count recorded attendance sessions for this offering
    $sql = "SELECT COUNT(*) AS total_sessions
            FROM attendance_sessions
            WHERE offering_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $selected_offering_id);
    $stmt->execute();

    $session_result = $stmt->get_result();
    $session_data = $session_result->fetch_assoc();

    $total_sessions = (int)$session_data["total_sessions"];

    $stmt->close();

    // Fetch every eligible student, including students with no attendance records.
    // Attendance sessions are restricted to the selected course offering.
    $sql = "SELECT
                u.id,
                u.student_id,
                u.full_name,

                COUNT(DISTINCT ats.id) AS total_classes,

                COALESCE(SUM(
                    CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END
                ), 0) AS present_count,

                COALESCE(SUM(
                    CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END
                ), 0) AS absent_count,

                COALESCE(SUM(
                    CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END
                ), 0) AS late_count

            FROM users u

            LEFT JOIN attendance_sessions ats
                ON ats.offering_id = ?

            LEFT JOIN attendance_records ar
                ON ar.session_id = ats.id
                AND ar.student_id = u.id

            WHERE u.role = 'student'
                AND LOWER(TRIM(u.department)) = LOWER(TRIM(?))
                AND u.academic_year = ?
                AND u.semester = ?

            GROUP BY
                u.id,
                u.student_id,
                u.full_name

            ORDER BY u.student_id ASC, u.full_name ASC";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "isii",
        $selected_offering_id,
        $selected_offering["department"],
        $selected_offering["year"],
        $selected_offering["semester"]
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $row["total_classes"] = (int)$row["total_classes"];
        $row["present_count"] = (int)$row["present_count"];
        $row["absent_count"] = (int)$row["absent_count"];
        $row["late_count"] = (int)$row["late_count"];

        // Every two Late records deduct one effective Present.
        $row["effective_present"] = max(
            0,
            $row["present_count"] - intdiv($row["late_count"], 2)
        );

        if ($row["total_classes"] > 0) {
            $row["percentage"] =
                ($row["effective_present"] / $row["total_classes"]) * 100;
        } else {
            $row["percentage"] = 0;
        }

        $students[] = $row;

        $total_present += $row["present_count"];
        $total_absent += $row["absent_count"];
        $total_late += $row["late_count"];
        $total_effective_present += $row["effective_present"];
    }

    $stmt->close();

    $total_students = count($students);

    // Overall course attendance percentage
    $possible_attendances = $total_students * $total_sessions;

    if ($possible_attendances > 0) {
        $overall_percentage =
            ($total_effective_present / $possible_attendances) * 100;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Attendance Report | RUET Connect</title>

    <link rel="stylesheet" href="css/style.css?v=3">

    <style>
        body {
            background: #f5f3f1;
            color: #292526;
        }

        .report-container {
            width: 100%;
            max-width: 1350px;
            margin: 0 auto;
            padding: 35px 30px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #3D1C24;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .back-link:hover {
            text-decoration: underline;
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

        .report-card {
            background: #fff;
            border-radius: 12px;
            padding: 26px;
            margin-bottom: 25px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }

        .report-card h2 {
            color: #3D1C24;
            font-size: 19px;
            margin-bottom: 20px;
        }

        .course-form {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 18px;
            align-items: end;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #51484a;
            margin-bottom: 9px;
        }

        .form-group select {
            width: 100%;
            padding: 12px 13px;
            border: 1px solid #ded8d9;
            border-radius: 7px;
            background: #fff;
            font-size: 14px;
            outline: none;
        }

        .form-group select:focus {
            border-color: #3D1C24;
        }

        .primary-btn {
            border: none;
            border-radius: 7px;
            padding: 13px 22px;
            background: #3D1C24;
            color: #fff;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            white-space: nowrap;
        }

        .primary-btn:hover {
            background: #542833;
        }

        .course-info {
            color: #777;
            font-size: 13px;
            margin-top: -10px;
            margin-bottom: 22px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 17px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.04);
            border-top: 3px solid #DAA520;
        }

        .stat-label {
            color: #777;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .stat-value {
            color: #3D1C24;
            font-size: 27px;
            font-weight: 700;
        }

        .stat-description {
            color: #999;
            font-size: 11px;
            margin-top: 6px;
        }

        .percentage-highlight {
            background: #3D1C24;
            color: #fff;
            border-top: 3px solid #DAA520;
        }

        .percentage-highlight .stat-label,
        .percentage-highlight .stat-value,
        .percentage-highlight .stat-description {
            color: #fff;
        }

        .percentage-highlight .stat-value {
            font-size: 32px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 950px;
        }

        .attendance-table th {
            background: #3D1C24;
            color: #fff;
            text-align: left;
            padding: 14px 12px;
            font-size: 12px;
            white-space: nowrap;
        }

        .attendance-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #eee9e9;
            font-size: 13px;
            color: #51484a;
            white-space: nowrap;
        }

        .attendance-table tbody tr:hover td {
            background: #faf8f7;
        }

        .student-name {
            color: #3D1C24;
            font-weight: 600;
        }

        .percentage {
            font-weight: 700;
            color: #3D1C24;
        }

        .percentage-cell {
            min-width: 110px;
        }

        .progress-track {
            width: 85px;
            height: 6px;
            background: #eee9e9;
            border-radius: 10px;
            margin-top: 7px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #DAA520;
            border-radius: 10px;
        }

        .empty-state {
            text-align: center;
            padding: 35px 15px;
            color: #888;
            font-size: 14px;
        }

        .notice {
            padding: 14px 16px;
            border-radius: 7px;
            background: #fff7e5;
            border: 1px solid #ead49a;
            color: #72550b;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .report-note {
            color: #777;
            font-size: 12px;
            line-height: 1.7;
            margin-top: 18px;
        }

        @media (max-width: 900px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .report-container {
                padding: 25px 15px;
            }

            .report-card {
                padding: 20px 15px;
            }

            .course-form {
                grid-template-columns: 1fr;
            }

            .primary-btn {
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-value {
                font-size: 23px;
            }
        }
    </style>
</head>

<body>

<div class="report-container">

    <a href="teacher_attendance.php" class="back-link">
        &larr; Back to Attendance Management
    </a>

    <div class="page-heading">
        <h1>Attendance Report</h1>
        <p>Monitor overall course attendance and individual student performance.</p>
    </div>

    <!-- Course Selection -->
    <div class="report-card">

        <h2>Select Course Offering</h2>

        <?php if (count($offerings) > 0): ?>

            <form method="GET" action="teacher_attendance_report.php" class="course-form">

                <div class="form-group">
                    <label for="offering_id">Course</label>

                    <select name="offering_id" id="offering_id" required>
                        <option value="">Choose a course</option>

                        <?php foreach ($offerings as $offering): ?>

                            <option
                                value="<?= e($offering["offering_id"]) ?>"
                                <?= $selected_offering_id === (int)$offering["offering_id"] ? "selected" : "" ?>
                            >
                                <?= e($offering["course_code"]) ?>
                                - <?= e($offering["course_name"]) ?>
                                (Year <?= e($offering["year"]) ?>,
                                Semester <?= e($offering["semester"]) ?>)
                                - <?= e($offering["academic_session"]) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <button type="submit" class="primary-btn">
                    Generate Report
                </button>

            </form>

        <?php else: ?>

            <div class="empty-state">
                No course offerings have been assigned to your account.
            </div>

        <?php endif; ?>

    </div>

    <?php if ($report_error !== ""): ?>

        <div class="notice">
            <?= e($report_error) ?>
        </div>

    <?php endif; ?>


    <?php if ($selected_offering): ?>

        <div class="report-card">

            <h2>
                <?= e($selected_offering["course_code"]) ?>
                - <?= e($selected_offering["course_name"]) ?>
            </h2>

            <p class="course-info">
                Academic Session: <?= e($selected_offering["academic_session"]) ?>
                &nbsp; | &nbsp;
                Year: <?= e($selected_offering["year"]) ?>
                &nbsp; | &nbsp;
                Semester: <?= e($selected_offering["semester"]) ?>
            </p>

            <!-- Overall Course Statistics -->
            <div class="stats-grid">

                <div class="stat-card percentage-highlight">
                    <div class="stat-label">Overall Attendance</div>

                    <div class="stat-value">
                        <?= number_format($overall_percentage, 2) ?>%
                    </div>

                    <div class="stat-description">
                        Effective attendance across the class
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Recorded Classes</div>

                    <div class="stat-value">
                        <?= $total_sessions ?>
                    </div>

                    <div class="stat-description">
                        Total attendance sessions
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Total Students</div>

                    <div class="stat-value">
                        <?= $total_students ?>
                    </div>

                    <div class="stat-description">
                        Students in this course offering
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Effective Presents</div>

                    <div class="stat-value">
                        <?= $total_effective_present ?>
                    </div>

                    <div class="stat-description">
                        After applying the Late deduction
                    </div>
                </div>

            </div>

            <div class="report-note">
                Overall attendance is calculated using the total effective
                presents of all students divided by the total possible
                student attendances.
            </div>

        </div>


        <!-- Student-wise Attendance Report -->
        <div class="report-card">

            <h2>Individual Student Attendance</h2>

            <?php if (count($students) > 0): ?>

                <div class="table-wrapper">

                    <table class="attendance-table">

                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Total Classes</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Late</th>
                                <th>Effective Present</th>
                                <th>Attendance %</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($students as $student): ?>

                                <tr>

                                    <td>
                                        <?= e($student["student_id"] ?: "N/A") ?>
                                    </td>

                                    <td class="student-name">
                                        <?= e($student["full_name"]) ?>
                                    </td>

                                    <td>
                                        <?= $student["total_classes"] ?>
                                    </td>

                                    <td>
                                        <?= $student["present_count"] ?>
                                    </td>

                                    <td>
                                        <?= $student["absent_count"] ?>
                                    </td>

                                    <td>
                                        <?= $student["late_count"] ?>
                                    </td>

                                    <td>
                                        <?= $student["effective_present"] ?>
                                    </td>

                                    <td class="percentage-cell">

                                        <span class="percentage">
                                            <?= number_format($student["percentage"], 2) ?>%
                                        </span>

                                        <div class="progress-track">
                                            <div
                                                class="progress-fill"
                                                style="width: <?= min(100, max(0, $student["percentage"])) ?>%;"
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
                    No students were found for this course offering.
                </div>

            <?php endif; ?>

            <div class="report-note">
                <strong>Calculation rule:</strong>
                Every two Late records deduct one Effective Present.
                Students with no recorded attendance sessions currently
                have an attendance percentage of 0%.
            </div>

        </div>

    <?php endif; ?>

</div>

</body>
</html>
