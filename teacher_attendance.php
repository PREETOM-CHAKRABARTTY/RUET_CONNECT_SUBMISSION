
<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit();
}

require_once "config/database.php";

$teacher_id = (int) $_SESSION["user_id"];
$today = date("Y-m-d");

// Fetch only the course offerings assigned to this teacher
$offerings = [];

$sql = "SELECT
            co.id AS offering_id,
            co.year,
            co.semester,
            co.academic_session,
            c.course_code,
            c.course_name
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

// Fetch previously saved attendance sessions
$sessions = [];

$sql = "SELECT
            ats.id AS session_id,
            ats.attendance_date,
            co.id AS offering_id,
            co.year,
            co.semester,
            co.academic_session,
            c.course_code,
            c.course_name,
            COUNT(ar.id) AS total_records,
            SUM(ar.status = 'present') AS present_count,
            SUM(ar.status = 'absent') AS absent_count,
            SUM(ar.status = 'late') AS late_count
        FROM attendance_sessions ats
        INNER JOIN course_offerings co
            ON ats.offering_id = co.id
        INNER JOIN courses c
            ON co.course_id = c.id
        LEFT JOIN attendance_records ar
            ON ats.id = ar.session_id
        WHERE co.teacher_id = ?
        GROUP BY
            ats.id,
            ats.attendance_date,
            co.id,
            co.year,
            co.semester,
            co.academic_session,
            c.course_code,
            c.course_name
        ORDER BY ats.attendance_date DESC, ats.id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $sessions[] = $row;
}

$stmt->close();

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Attendance Management | RUET Connect</title>

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

        .page-heading {
            margin-bottom: 28px;
        }

        /* Heading and Report Button */
        .heading-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
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

        /* Attendance Report Button */
        .report-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 12px 19px;
            background: #DAA520;
            color: #241c0d;
            text-decoration: none;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 700;
            transition: 0.2s;
            white-space: nowrap;
        }

        .report-btn:hover {
            background: #c49316;
            transform: translateY(-2px);
        }

        .attendance-card {
            background: #fff;
            border-radius: 12px;
            padding: 26px;
            margin-bottom: 28px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }

        .attendance-card h2 {
            color: #3D1C24;
            font-size: 19px;
            margin-bottom: 20px;
        }

        .attendance-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 20px;
            align-items: end;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 9px;
            color: #51484a;
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 12px 13px;
            border: 1px solid #ded8d9;
            border-radius: 7px;
            background: #fff;
            font-size: 14px;
            color: #333;
            outline: none;
        }

        .form-group select:focus,
        .form-group input:focus {
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
            transition: 0.2s;
            white-space: nowrap;
        }

        .primary-btn:hover {
            background: #542833;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        .attendance-table th {
            background: #3D1C24;
            color: #fff;
            text-align: left;
            padding: 14px 15px;
            font-size: 13px;
            font-weight: 600;
        }

        .attendance-table td {
            padding: 15px;
            border-bottom: 1px solid #eee9e9;
            font-size: 13px;
            color: #51484a;
        }

        .attendance-table tr:hover td {
            background: #faf8f7;
        }

        .course-name {
            font-weight: 600;
            color: #3D1C24;
        }

        .session-date {
            font-weight: 600;
            color: #333;
        }

        .edit-btn {
            display: inline-block;
            padding: 8px 14px;
            background: #DAA520;
            color: #241c0d;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
        }

        .edit-btn:hover {
            background: #c49316;
        }

        .empty-state {
            text-align: center;
            padding: 35px 15px;
            color: #888;
            font-size: 14px;
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

        @media (max-width: 750px) {
            .attendance-container {
                padding: 25px 15px;
            }

            .attendance-form {
                grid-template-columns: 1fr;
            }

            .primary-btn {
                width: 100%;
            }

            .attendance-card {
                padding: 20px 15px;
            }
        }

        @media (max-width: 600px) {
            .heading-content {
                align-items: flex-start;
                flex-direction: column;
            }

            .report-btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="attendance-container">

    <a href="teacher.php" class="back-link">
        &larr; Back to Dashboard
    </a>

    <div class="page-heading">

        <div class="heading-content">

            <div>
                <h1>Attendance Management</h1>
                <p>Manage student attendance and review previously recorded sessions.</p>
            </div>

            <a href="teacher_attendance_report.php" class="report-btn">
                <span>📊</span>
                View Attendance Report
            </a>

        </div>

    </div>

    <!-- Create or Edit Attendance -->
    <div class="attendance-card">

        <h2>Take Attendance</h2>

        <?php if (count($offerings) > 0): ?>

            <form action="manage_attendance.php" method="GET" class="attendance-form">

                <div class="form-group">
                    <label for="offering_id">Select Course</label>

                    <select name="offering_id" id="offering_id" required>
                        <option value="">Choose a course</option>

                        <?php foreach ($offerings as $offering): ?>

                            <option value="<?= e($offering["offering_id"]) ?>">
                                <?= e($offering["course_code"]) ?>
                                - <?= e($offering["course_name"]) ?>
                                (Year <?= e($offering["year"]) ?>,
                                Semester <?= e($offering["semester"]) ?>)
                                - <?= e($offering["academic_session"]) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label for="attendance_date">Attendance Date</label>

                    <input
                        type="date"
                        name="attendance_date"
                        id="attendance_date"
                        value="<?= e($today) ?>"
                        max="<?= e($today) ?>"
                        required
                    >
                </div>

                <button type="submit" class="primary-btn">
                    Continue &rarr;
                </button>

            </form>

        <?php else: ?>

            <div class="empty-state">
                No course offerings have been assigned to your account yet.
            </div>

        <?php endif; ?>

    </div>


    <!-- Previous Attendance Sessions -->
    <div class="attendance-card">

        <h2>Previous Attendance Records</h2>

        <?php if (count($sessions) > 0): ?>

            <div class="table-wrapper">

                <table class="attendance-table">

                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Academic Session</th>
                            <th>Date</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Late</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($sessions as $session): ?>

                            <tr>

                                <td>
                                    <div class="course-name">
                                        <?= e($session["course_code"]) ?>
                                    </div>
                                    <?= e($session["course_name"]) ?>
                                </td>

                                <td>
                                    <?= e($session["academic_session"]) ?>
                                    <br>
                                    <small>
                                        Year <?= e($session["year"]) ?>,
                                        Semester <?= e($session["semester"]) ?>
                                    </small>
                                </td>

                                <td class="session-date">
                                    <?= date("d M Y", strtotime($session["attendance_date"])) ?>
                                </td>

                                <td>
                                    <?= (int)$session["present_count"] ?>
                                </td>

                                <td>
                                    <?= (int)$session["absent_count"] ?>
                                </td>

                                <td>
                                    <?= (int)$session["late_count"] ?>
                                </td>

                                <td>
                                    <a
                                        class="edit-btn"
                                        href="manage_attendance.php?offering_id=<?= e($session["offering_id"]) ?>&attendance_date=<?= e($session["attendance_date"]) ?>"
                                    >
                                        Edit
                                    </a>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty-state">
                No attendance sessions have been recorded yet.
                <br>
                Select a course and date above to take your first attendance.
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>
