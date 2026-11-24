
<?php

session_start();

if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'student'
) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";

$student_id = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Fetch logged-in student's academic information
|--------------------------------------------------------------------------
*/

$student_sql = "SELECT
                    department,
                    academic_year,
                    semester
                FROM users
                WHERE id = ?
                  AND role = 'student'";

$student_stmt = $conn->prepare($student_sql);
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();

$student_result = $student_stmt->get_result();
$student = $student_result->fetch_assoc();

$student_stmt->close();

if (!$student) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$department = $student["department"];
$academic_year = (int) $student["academic_year"];
$semester = (int) $student["semester"];

/*
|--------------------------------------------------------------------------
| Fetch marks files for the student's current courses
|--------------------------------------------------------------------------
*/

$marks_sql = "SELECT DISTINCT
                  mf.id,
                  mf.title,
                  mf.description,
                  mf.file_path,
                  mf.file_type,
                  mf.uploaded_at,

                  c.course_code,
                  c.course_name,

                  u.full_name AS teacher_name

              FROM marks_files mf

              INNER JOIN courses c
                  ON mf.course_id = c.id

              INNER JOIN users u
                  ON mf.teacher_id = u.id

              INNER JOIN course_offerings co
                  ON c.id = co.course_id

              WHERE co.year = ?
                AND co.semester = ?
                AND c.department = ?

              ORDER BY c.course_code ASC,
                       mf.uploaded_at DESC";

$marks_stmt = $conn->prepare($marks_sql);

$marks_stmt->bind_param(
    "iis",
    $academic_year,
    $semester,
    $department
);

$marks_stmt->execute();

$marks_result = $marks_stmt->get_result();

$marks_files = [];

while ($marks_file = $marks_result->fetch_assoc()) {
    $marks_files[] = $marks_file;
}

$marks_stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Marks | RUET Connect</title>

    <link rel="stylesheet" href="css/style.css">

    <style>

        body {
            margin: 0;
            background: #f8f5f2;
            color: #292929;
            font-family: Arial, sans-serif;
        }

        .marks-page {
            min-height: 100vh;
        }

        .marks-header {
            background: #3D1C24;
            color: white;
            padding: 24px 5%;
        }

        .marks-header-content {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;

            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .marks-header h1 {
            margin: 0;
            font-size: 30px;
        }

        .marks-header p {
            margin: 8px 0 0;
            color: #f0dca4;
        }

        .dashboard-button {
            display: inline-block;
            background: #DAA520;
            color: #3D1C24;
            text-decoration: none;
            padding: 12px 18px;
            border-radius: 8px;
            font-weight: 700;
            white-space: nowrap;
            transition: background 0.2s ease;
        }

        .dashboard-button:hover {
            background: #c49318;
        }

        .marks-container {
            width: 90%;
            max-width: 1200px;
            margin: 35px auto;
        }

        .marks-card {
            background: white;
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 28px;
            box-shadow: 0 8px 25px rgba(61, 28, 36, 0.08);
        }

        .marks-card h2 {
            margin-top: 0;
            color: #3D1C24;
        }

        .marks-card p {
            line-height: 1.6;
        }

        .marks-table-wrapper {
            overflow-x: auto;
        }

        .marks-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        .marks-table th,
        .marks-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .marks-table th {
            background: #3D1C24;
            color: white;
        }

        .marks-table tr:hover {
            background: #fffaf0;
        }

        .file-type {
            display: inline-block;
            background: #f4e7bd;
            color: #3D1C24;
            padding: 5px 9px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
        }

        .view-button {
            display: inline-block;
            background: #DAA520;
            color: #3D1C24;
            text-decoration: none;
            padding: 8px 13px;
            border-radius: 7px;
            font-weight: 700;
            white-space: nowrap;
        }

        .view-button:hover {
            background: #c49318;
        }

        .empty-state {
            padding: 30px;
            text-align: center;
            color: #777;
            background: #fafafa;
            border-radius: 10px;
        }

        .student-info {
            color: #666;
            margin-bottom: 22px;
        }

        @media (max-width: 700px) {

            .marks-header-content {
                align-items: flex-start;
                flex-direction: column;
            }

            .marks-header h1 {
                font-size: 24px;
            }

            .marks-container {
                width: 94%;
            }

            .marks-card {
                padding: 20px;
            }

            .dashboard-button {
                margin-top: 5px;
            }
        }

    </style>

</head>

<body>

<div class="marks-page">

    <header class="marks-header">

        <div class="marks-header-content">

            <div>
                <h1>My Marks</h1>
                <p>View marks files published for your current courses.</p>
            </div>

            <a href="student.php" class="dashboard-button">
                ← Back to Dashboard
            </a>

        </div>

    </header>

    <main class="marks-container">

        <section class="marks-card">

            <h2>Published Marks</h2>

            <p class="student-info">
                These marks files are available for your current academic year,
                semester, and department.
            </p>

            <?php if (empty($marks_files)): ?>

                <div class="empty-state">
                    No marks have been published for your current courses yet.
                </div>

            <?php else: ?>

                <div class="marks-table-wrapper">

                    <table class="marks-table">

                        <thead>

                            <tr>
                                <th>Course</th>
                                <th>Marks File</th>
                                <th>Teacher</th>
                                <th>File Type</th>
                                <th>Uploaded At</th>
                                <th>Action</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($marks_files as $file): ?>

                                <tr>

                                    <td>
                                        <strong>
                                            <?php
                                            echo htmlspecialchars(
                                                $file["course_code"]
                                            );
                                            ?>
                                        </strong>
                                        <br>

                                        <?php
                                        echo htmlspecialchars(
                                            $file["course_name"]
                                        );
                                        ?>
                                    </td>

                                    <td>

                                        <strong>
                                            <?php
                                            echo htmlspecialchars(
                                                $file["title"]
                                            );
                                            ?>
                                        </strong>

                                        <?php if (!empty($file["description"])): ?>

                                            <br>

                                            <small>
                                                <?php
                                                echo htmlspecialchars(
                                                    $file["description"]
                                                );
                                                ?>
                                            </small>

                                        <?php endif; ?>

                                    </td>

                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $file["teacher_name"]
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <span class="file-type">
                                            <?php
                                            echo strtoupper(
                                                htmlspecialchars(
                                                    $file["file_type"]
                                                )
                                            );
                                            ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $file["uploaded_at"]
                                        );
                                        ?>
                                    </td>

                                    <td>

                                        <a
                                            href="<?php echo htmlspecialchars($file["file_path"]); ?>"
                                            class="view-button"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            View / Download
                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>

</body>

</html>
