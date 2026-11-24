<?php

session_start();

require_once "config/database.php";


/* =========================================================
   LOGIN & ROLE CHECK
========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit;
}


/* =========================================================
   GET COURSE ID
========================================================= */

$course_id = isset($_GET["course_id"])
    ? (int) $_GET["course_id"]
    : 0;

if ($course_id <= 0) {
    header("Location: student_materials.php");
    exit;
}


/* =========================================================
   GET STUDENT INFORMATION
========================================================= */

$student_id = (int) $_SESSION["user_id"];

$student_sql = "
    SELECT
        full_name,
        department,
        academic_year,
        semester
    FROM users
    WHERE id = ?
    AND role = 'student'
";

$student_stmt = $conn->prepare($student_sql);

if (!$student_stmt) {
    die("Something went wrong. Please try again.");
}

$student_stmt->bind_param("i", $student_id);

$student_stmt->execute();

$student_result = $student_stmt->get_result();


if ($student_result->num_rows !== 1) {

    $student_stmt->close();

    session_unset();
    session_destroy();

    header("Location: login.php");
    exit;
}


$student = $student_result->fetch_assoc();

$student_stmt->close();


$full_name = $student["full_name"];
$department = $student["department"];
$academic_year = $student["academic_year"];
$semester = $student["semester"];

$first_name = explode(" ", trim($full_name))[0];


/* =========================================================
   VERIFY COURSE BELONGS TO STUDENT'S SEMESTER
========================================================= */

$course_sql = "
    SELECT DISTINCT
        courses.id,
        courses.course_code,
        courses.course_name,
        courses.course_description,
        courses.department,
        courses.credit

    FROM course_offerings

    INNER JOIN courses
        ON course_offerings.course_id = courses.id

    WHERE courses.id = ?
    AND courses.department = ?
    AND course_offerings.year = ?
    AND course_offerings.semester = ?
";


$course_stmt = $conn->prepare($course_sql);

if (!$course_stmt) {
    die("Something went wrong. Please try again.");
}


$course_stmt->bind_param(
    "isii",
    $course_id,
    $department,
    $academic_year,
    $semester
);


$course_stmt->execute();

$course_result = $course_stmt->get_result();


/*
    If the course does not belong to the student's
    department/year/semester, access is denied.
*/

if ($course_result->num_rows !== 1) {

    $course_stmt->close();

    header("Location: student_materials.php");
    exit;
}


$course = $course_result->fetch_assoc();

$course_stmt->close();


/* =========================================================
   GET COURSE MATERIALS
========================================================= */

$materials = [];


$materials_sql = "
    SELECT
        course_materials.id,
        course_materials.title,
        course_materials.description,
        course_materials.file_path,
        course_materials.material_type,
        course_materials.created_at,
        users.full_name AS uploader_name

    FROM course_materials

    INNER JOIN users
        ON course_materials.uploaded_by = users.id

    WHERE course_materials.course_id = ?

    ORDER BY course_materials.created_at DESC
";


$materials_stmt = $conn->prepare($materials_sql);


if ($materials_stmt) {

    $materials_stmt->bind_param("i", $course_id);

    $materials_stmt->execute();

    $materials_result = $materials_stmt->get_result();


    while ($row = $materials_result->fetch_assoc()) {
        $materials[] = $row;
    }


    $materials_stmt->close();
}


/* =========================================================
   MATERIAL COUNT
========================================================= */

$material_count = count($materials);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?php echo htmlspecialchars($course["course_code"]); ?>
        - Materials - RUET Connect
    </title>


    <link
        rel="stylesheet"
        href="css/style.css"
    >


    <style>

        /* =====================================================
           PAGE BASE
        ===================================================== */

        html,
        body {

            margin: 0;

            padding: 0;

            width: 100%;

            min-height: 100%;

        }


        body {

            background: #f7f5f3;

            color: #252525;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

        }


        /* =====================================================
           HEADER
        ===================================================== */

        .dashboard-header {

            width: calc(100% - 136px);

            min-height: 78px;

            margin: 8px auto 0;

            padding: 0 44px;

            background: #ffffff;

            display: flex;

            align-items: center;

            justify-content: space-between;

            border: 1px solid #eee7e9;

            box-shadow:
                0 4px 16px
                rgba(61, 28, 36, 0.05);

            box-sizing: border-box;

        }


        .dashboard-brand h1 {

            margin: 0;

            color: #000000;

            font-size: 28px;

            font-weight: 500;

            letter-spacing: 0.2px;

        }


        /* =====================================================
           USER AREA
        ===================================================== */

        .dashboard-user {

            display: flex;

            align-items: center;

            gap: 10px;

            margin: 0;

            padding: 0;

            background: transparent;

        }


        .dashboard-user .user-name {

            display: inline-flex;

            align-items: center;

            min-width: auto;

            height: auto;

            padding: 0;

            background: transparent;

            border: none;

            color: #3D1C24;

            font-size: 16px;

            font-weight: 400;

            white-space: nowrap;

        }


        /* =====================================================
           LOGOUT
        ===================================================== */

        .dashboard-user .dashboard-logout {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            height: 38px;

            padding: 0 18px;

            background: #3D1C24;

            color: #ffffff;

            border: 1px solid #3D1C24;

            border-radius: 6px;

            font-size: 13px;

            font-weight: 500;

            text-decoration: none;

            transition:
                background-color 0.2s ease,
                border-color 0.2s ease,
                transform 0.2s ease;

        }


        .dashboard-user .dashboard-logout:hover {

            background: #DAA520;

            border-color: #DAA520;

            color: #ffffff;

            transform: translateY(-1px);

        }


        /* =====================================================
           NAVIGATION
        ===================================================== */

        .dashboard-nav {

            width: fit-content;

            min-height: 53px;

            margin: 23px auto 0;

            padding: 0;

            background: #3D1C24;

            display: flex;

            align-items: stretch;

            box-shadow:
                0 3px 10px
                rgba(61, 28, 36, 0.06);

        }


        .dashboard-nav a {

            position: relative;

            display: flex;

            align-items: center;

            justify-content: center;

            min-height: 53px;

            padding: 0 17px;

            color: #ffffff;

            font-size: 14px;

            font-weight: 500;

            text-decoration: none;

            transition:
                background-color 0.2s ease,
                color 0.2s ease;

        }


        .dashboard-nav a:hover {

            background: #512632;

            color: #DAA520;

        }


        .dashboard-nav a.active {

            color: #ffffff;

        }


        .dashboard-nav a.active::after {

            content: "";

            position: absolute;

            left: 14px;

            right: 14px;

            bottom: 0;

            height: 3px;

            background: #DAA520;

        }


        /* =====================================================
           MAIN PAGE
        ===================================================== */

        .course-materials-page {

            width: 100%;

            max-width: 1345px;

            margin: 0 auto;

            padding: 68px 0 60px;

            box-sizing: border-box;

        }


        /* =====================================================
           BACK BUTTON
        ===================================================== */

        .back-link {

            display: inline-flex;

            align-items: center;

            margin-bottom: 24px;

            color: #3D1C24;

            font-size: 14px;

            font-weight: 500;

            text-decoration: none;

            transition: color 0.2s ease;

        }


        .back-link:hover {

            color: #DAA520;

        }


        /* =====================================================
           COURSE HEADER
        ===================================================== */

        .course-header-card {

            padding: 30px 32px;

            margin-bottom: 30px;

            background: #ffffff;

            border: 1px solid #e9e3e5;

            border-top: 4px solid #DAA520;

            border-radius: 10px;

            box-shadow:
                0 5px 18px
                rgba(61, 28, 36, 0.06);

        }


        .course-code {

            margin-bottom: 7px;

            color: #DAA520;

            font-size: 14px;

            font-weight: 700;

            letter-spacing: 0.6px;

        }


        .course-title {

            margin: 0 0 9px;

            color: #3D1C24;

            font-size: 28px;

            font-weight: 600;

            line-height: 1.3;

        }


        .course-description {

            margin: 0;

            color: #666;

            font-size: 15px;

            line-height: 1.6;

        }


        /* =====================================================
           MATERIAL SECTION HEADER
        ===================================================== */

        .materials-section-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 18px;

        }


        .materials-section-header h2 {

            margin: 0;

            color: #3D1C24;

            font-size: 22px;

            font-weight: 600;

        }


        .material-count {

            color: #777;

            font-size: 13px;

        }


        /* =====================================================
           MATERIAL LIST
        ===================================================== */

        .materials-list {

            display: flex;

            flex-direction: column;

            gap: 14px;

        }


        /* =====================================================
           MATERIAL CARD
        ===================================================== */

        .material-card {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 25px;

            padding: 20px 24px;

            background: #ffffff;

            border: 1px solid #e9e3e5;

            border-left: 5px solid #DAA520;

            border-radius: 10px;

            box-shadow:
                0 5px 18px
                rgba(61, 28, 36, 0.05);

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease,
                border-color 0.2s ease;

        }


        .material-card:hover {

            transform: translateY(-2px);

            border-color: #d8c5c9;

            box-shadow:
                0 9px 25px
                rgba(61, 28, 36, 0.09);

        }


        /* =====================================================
           MATERIAL INFORMATION
        ===================================================== */

        .material-info {

            min-width: 0;

        }


        .material-type {

            display: inline-block;

            margin-bottom: 6px;

            color: #DAA520;

            font-size: 11px;

            font-weight: 700;

            letter-spacing: 0.7px;

            text-transform: uppercase;

        }


        .material-title {

            margin: 0 0 6px;

            color: #3D1C24;

            font-size: 18px;

            font-weight: 600;

        }


        .material-description {

            margin: 0 0 8px;

            color: #666;

            font-size: 14px;

            line-height: 1.5;

        }


        .material-meta {

            color: #999;

            font-size: 12px;

        }


        /* =====================================================
           MATERIAL ACTION
        ===================================================== */

        .material-action {

            flex-shrink: 0;

        }


        .material-view-btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            min-width: 120px;

            height: 40px;

            padding: 0 18px;

            background: #3D1C24;

            color: #ffffff;

            border: 1px solid #3D1C24;

            border-radius: 6px;

            font-size: 13px;

            font-weight: 500;

            text-decoration: none;

            transition:
                background-color 0.2s ease,
                border-color 0.2s ease,
                transform 0.2s ease;

        }


        .material-view-btn:hover {

            background: #DAA520;

            border-color: #DAA520;

            color: #ffffff;

            transform: translateY(-1px);

        }


        /* =====================================================
           NO MATERIALS
        ===================================================== */

        .no-materials {

            padding: 55px 30px;

            background: #ffffff;

            border: 1px solid #e9e3e5;

            border-top: 4px solid #DAA520;

            border-radius: 10px;

            text-align: center;

            box-shadow:
                0 6px 22px
                rgba(61, 28, 36, 0.06);

        }


        .no-materials h3 {

            margin: 0 0 10px;

            color: #3D1C24;

            font-size: 21px;

            font-weight: 600;

        }


        .no-materials p {

            margin: 0;

            color: #777;

            line-height: 1.6;

        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1450px) {

            .dashboard-header {

                width: calc(100% - 80px);

            }


            .course-materials-page {

                max-width: none;

                margin-left: 50px;

                margin-right: 50px;

            }

        }


        @media (max-width: 900px) {

            .dashboard-header {

                width: calc(100% - 40px);

                padding: 0 25px;

            }


            .course-materials-page {

                margin-left: 30px;

                margin-right: 30px;

                padding-top: 55px;

            }

        }


        @media (max-width: 700px) {

            .dashboard-header {

                width: calc(100% - 30px);

                min-height: 72px;

                padding: 10px 18px;

                flex-direction: column;

                align-items: flex-start;

                justify-content: center;

                gap: 10px;

            }


            .dashboard-user {

                width: 100%;

                justify-content: flex-end;

            }


            .dashboard-nav {

                width: 100%;

                margin-top: 15px;

                overflow-x: auto;

                justify-content: flex-start;

            }


            .dashboard-nav a {

                flex-shrink: 0;

                padding: 0 15px;

            }


            .course-materials-page {

                margin-left: 18px;

                margin-right: 18px;

                padding-top: 45px;

            }


            .course-header-card {

                padding: 25px 22px;

            }


            .course-title {

                font-size: 24px;

            }


            .material-card {

                align-items: flex-start;

                flex-direction: column;

            }


            .material-action {

                width: 100%;

            }


            .material-view-btn {

                width: 100%;

            }

        }


        @media (max-width: 480px) {

            .materials-section-header {

                align-items: flex-start;

                flex-direction: column;

                gap: 5px;

            }

        }

    </style>

</head>


<body>


    <!-- =====================================================
         HEADER
    ===================================================== -->

    <header class="dashboard-header">


        <div class="dashboard-brand">

            <h1>
                RUET Connect
            </h1>

        </div>


        <div class="dashboard-user">

            <span class="user-name">

                Welcome,
                <?php echo htmlspecialchars($first_name); ?>

            </span>


            <a
                href="logout.php"
                class="dashboard-logout"
            >
                Logout
            </a>

        </div>


    </header>


    <!-- =====================================================
         NAVIGATION
    ===================================================== -->

    <nav class="dashboard-nav">


        <a href="student.php">
            Dashboard
        </a>


        <a href="student_courses.php">
            My Courses
        </a>


        <a
            href="student_materials.php"
            class="active"
        >
            Materials
        </a>


        <a href="marks.html">
            Marks
        </a>


        <a href="attendance.html">
            Attendance
        </a>


        <a href="routine.html">
            Routine
        </a>


    </nav>


    <!-- =====================================================
         MAIN CONTENT
    ===================================================== -->

    <main class="course-materials-page">


        <!-- BACK TO COURSE LIST -->

        <a
            href="student_materials.php"
            class="back-link"
        >
            ← Back to Course Materials
        </a>


        <!-- =================================================
             COURSE INFORMATION
        ================================================== -->

        <section class="course-header-card">


            <div class="course-code">

                <?php echo htmlspecialchars(
                    $course["course_code"]
                ); ?>

            </div>


            <h2 class="course-title">

                <?php echo htmlspecialchars(
                    $course["course_name"]
                ); ?>

            </h2>


            <?php if (!empty($course["course_description"])): ?>

                <p class="course-description">

                    <?php echo htmlspecialchars(
                        $course["course_description"]
                    ); ?>

                </p>

            <?php endif; ?>


        </section>


        <!-- =================================================
             MATERIAL SECTION
        ================================================== -->

        <section>


            <div class="materials-section-header">


                <h2>
                    Course Materials
                </h2>


                <span class="material-count">

                    <?php echo $material_count; ?>

                    <?php echo $material_count === 1
                        ? "Material"
                        : "Materials"; ?>

                </span>


            </div>


            <?php if (empty($materials)): ?>


                <!-- NO MATERIALS -->

                <div class="no-materials">


                    <h3>
                        No Materials Available
                    </h3>


                    <p>
                        No learning materials have been uploaded
                        for this course yet.
                    </p>


                </div>


            <?php else: ?>


                <!-- MATERIAL LIST -->

                <div class="materials-list">


                    <?php foreach ($materials as $material): ?>


                        <article class="material-card">


                            <div class="material-info">


                                <div class="material-type">

                                    <?php echo htmlspecialchars(
                                        $material["material_type"]
                                    ); ?>

                                </div>


                                <h3 class="material-title">

                                    <?php echo htmlspecialchars(
                                        $material["title"]
                                    ); ?>

                                </h3>


                                <?php if (
                                    !empty($material["description"])
                                ): ?>

                                    <p class="material-description">

                                        <?php echo htmlspecialchars(
                                            $material["description"]
                                        ); ?>

                                    </p>

                                <?php endif; ?>


                                <div class="material-meta">

                                    Uploaded by
                                    <?php echo htmlspecialchars(
                                        $material["uploader_name"]
                                    ); ?>

                                    ·

                                    <?php echo date(
                                        "d M Y",
                                        strtotime(
                                            $material["created_at"]
                                        )
                                    ); ?>

                                </div>


                            </div>


                            <div class="material-action">


                                <a
                                    href="<?php echo htmlspecialchars(
                                        $material["file_path"]
                                    ); ?>"
                                    class="material-view-btn"
                                    target="_blank"
                                >
                                    View / Download
                                </a>


                            </div>


                        </article>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </section>


    </main>


</body>

</html>
