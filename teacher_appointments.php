<?php
session_start();

require_once "config/database.php";

/* =========================================================
   Teacher-only access
========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit();
}

$teacher_id = (int) $_SESSION["user_id"];
$teacher_name = $_SESSION["full_name"] ?? "Teacher";

$message = "";
$message_type = "";

/* =========================================================
   Update appointment status and teacher response
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $appointment_id = (int) ($_POST["appointment_id"] ?? 0);
    $status = $_POST["status"] ?? "";
    $teacher_response = trim($_POST["teacher_response"] ?? "");

    $allowed_statuses = [
        "pending",
        "approved",
        "rejected",
        "completed",
        "cancelled"
    ];

    if (
        $appointment_id <= 0 ||
        !in_array($status, $allowed_statuses, true)
    ) {
        $message = "Invalid appointment information.";
        $message_type = "error";
    } else {
        /*
         * Update only appointments belonging to
         * the currently logged-in teacher.
         */
        $update = $conn->prepare(
            "UPDATE appointments
             SET status = ?, teacher_response = ?
             WHERE id = ? AND teacher_id = ?"
        );

        $update->bind_param(
            "ssii",
            $status,
            $teacher_response,
            $appointment_id,
            $teacher_id
        );

        if ($update->execute() && $update->affected_rows >= 0) {
            $message = "Appointment updated successfully.";
            $message_type = "success";
        } else {
            $message = "Unable to update the appointment.";
            $message_type = "error";
        }

        $update->close();
    }
}

/* =========================================================
   Load appointment requests for this teacher
========================================================= */

$appointments_query = $conn->prepare(
    "SELECT
        a.id,
        a.appointment_date,
        a.appointment_time,
        a.purpose,
        a.message,
        a.status,
        a.teacher_response,
        a.created_at,

        u.full_name AS student_name,
        u.email AS student_email,
        u.student_id,
        u.department AS student_department,
        u.academic_year,
        u.semester

     FROM appointments a

     INNER JOIN users u
        ON a.student_id = u.id

     WHERE a.teacher_id = ?

     ORDER BY
        CASE
            WHEN a.status = 'pending' THEN 1
            WHEN a.status = 'approved' THEN 2
            WHEN a.status = 'completed' THEN 3
            WHEN a.status = 'rejected' THEN 4
            ELSE 5
        END,
        a.appointment_date ASC,
        a.appointment_time ASC"
);

$appointments_query->bind_param("i", $teacher_id);
$appointments_query->execute();

$appointments = $appointments_query->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Teacher Appointments | RUET Connect</title>

    <style>
        /* =====================================================
           GLOBAL RESET
        ===================================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            background: #f8f5f3;
            color: #2f2024;
            font-family: "Segoe UI", Arial, sans-serif;
        }

        a {
            text-decoration: none;
        }

        /* =====================================================
           HEADER
        ===================================================== */

        .top-header {
            width: 100%;
            background: #ffffff;
            border-bottom: 1px solid #eadfe1;
        }

        .header-inner {
            width: 100%;
            min-height: 82px;
            padding: 18px 5%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
        }

        .brand {
            color: #000000;
            font-size: 26px;
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        .header-right {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 18px;
            color: #3d1c24;
            font-size: 14px;
        }

        .welcome-text {
            font-weight: 500;
        }

        .logout-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 18px;
            border-radius: 8px;
            background: #3d1c24;
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .logout-btn:hover {
            background: #5a2c38;
        }

        /* =====================================================
           NAVIGATION
        ===================================================== */

        .main-nav {
            width: 100%;
            background: #3d1c24;
            padding: 10px 5%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 6px;
        }

        .main-nav a {
            color: #ffffff;
            padding: 11px 16px;
            border-radius: 7px;
            font-size: 14px;
            font-weight: 500;
            transition: 0.2s ease;
        }

        .main-nav a:hover,
        .main-nav a.active {
            background: #daa520;
            color: #3d1c24;
        }

        /* =====================================================
           MAIN CONTENT
        ===================================================== */

        .page-container {
            width: 100%;
            max-width: 1450px;
            margin: 0 auto;
            padding: 45px 5% 60px;
        }

        .page-heading {
            margin-bottom: 30px;
        }

        .page-heading h1 {
            color: #3d1c24;
            font-size: 34px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .page-heading p {
            color: #777070;
            font-size: 15px;
        }

        /* =====================================================
           ALERTS
        ===================================================== */

        .alert {
            padding: 15px 18px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 14px;
            font-weight: 500;
        }

        .alert.success {
            background: #e8f6ed;
            border: 1px solid #b9e4c6;
            color: #176b36;
        }

        .alert.error {
            background: #fdecec;
            border: 1px solid #f1bcbc;
            color: #a32929;
        }

        /* =====================================================
           SUMMARY CARDS
        ===================================================== */

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: #ffffff;
            border: 1px solid #eadfe1;
            border-radius: 15px;
            padding: 23px;
            box-shadow: 0 8px 25px rgba(61, 28, 36, 0.06);
        }

        .summary-card h3 {
            color: #777070;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .summary-card strong {
            color: #3d1c24;
            font-size: 30px;
            font-weight: 700;
        }

        /* =====================================================
           APPOINTMENT LIST
        ===================================================== */

        .appointments-card {
            background: #ffffff;
            border: 1px solid #eadfe1;
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 10px 30px rgba(61, 28, 36, 0.07);
        }

        .appointments-card h2 {
            color: #3d1c24;
            font-size: 23px;
            margin-bottom: 25px;
        }

        .appointment-item {
            border: 1px solid #eadfe1;
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 18px;
            background: #fffdfd;
        }

        .appointment-item:last-child {
            margin-bottom: 0;
        }

        .appointment-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 18px;
        }

        .student-name {
            color: #3d1c24;
            font-size: 19px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .student-meta {
            color: #777070;
            font-size: 13px;
            line-height: 1.7;
        }

        .status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .status.pending {
            background: #fff3cd;
            color: #856404;
        }

        .status.approved {
            background: #d4edda;
            color: #155724;
        }

        .status.rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .status.completed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status.cancelled {
            background: #e2e3e5;
            color: #383d41;
        }

        .appointment-details {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px 25px;
            padding: 18px 0;
            border-top: 1px solid #eee4e6;
            border-bottom: 1px solid #eee4e6;
        }

        .detail-item {
            min-width: 0;
        }

        .detail-label {
            display: block;
            color: #8a7b80;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .detail-value {
            color: #3d1c24;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .message-box {
            margin-top: 18px;
            padding: 15px;
            border-radius: 9px;
            background: #f8f5f3;
            color: #5f5558;
            font-size: 14px;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .message-box strong {
            color: #3d1c24;
        }

        /* =====================================================
           UPDATE FORM
        ===================================================== */

        .update-form {
            margin-top: 20px;
            display: grid;
            grid-template-columns: 220px minmax(0, 1fr) auto;
            gap: 14px;
            align-items: end;
        }

        .form-group {
            min-width: 0;
        }

        .form-group label {
            display: block;
            color: #3d1c24;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 7px;
        }

        .form-group select,
        .form-group textarea {
            width: 100%;
            border: 1px solid #d9cbd0;
            border-radius: 9px;
            background: #ffffff;
            color: #2f2024;
            padding: 12px 13px;
            font-family: inherit;
            font-size: 14px;
        }

        .form-group select {
            min-height: 46px;
        }

        .form-group textarea {
            min-height: 46px;
            resize: vertical;
        }

        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #daa520;
            box-shadow: 0 0 0 3px rgba(218, 165, 32, 0.16);
        }

        .update-btn {
            min-height: 46px;
            padding: 0 20px;
            border: none;
            border-radius: 9px;
            background: #daa520;
            color: #3d1c24;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .update-btn:hover {
            background: #c99416;
            transform: translateY(-1px);
        }

        .empty-state {
            min-height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #777070;
            font-size: 15px;
            padding: 25px;
        }

        /* =====================================================
           RESPONSIVE DESIGN
        ===================================================== */

        @media (max-width: 950px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }

            .update-form {
                grid-template-columns: 1fr;
            }

            .update-btn {
                width: 100%;
            }
        }

        @media (max-width: 700px) {
            .header-inner {
                align-items: flex-start;
                flex-direction: column;
                padding: 20px;
            }

            .header-right {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
            }

            .main-nav {
                justify-content: flex-start;
                padding: 10px;
            }

            .main-nav a {
                padding: 9px 11px;
                font-size: 13px;
            }

            .page-container {
                padding: 30px 18px 45px;
            }

            .page-heading h1 {
                font-size: 28px;
            }

            .appointments-card {
                padding: 20px;
            }

            .appointment-header {
                flex-direction: column;
                gap: 12px;
            }

            .appointment-details {
                grid-template-columns: 1fr;
            }
        }
    </style>

</head>

<body>

    <!-- =====================================================
         HEADER
    ===================================================== -->

    <header class="top-header">

        <div class="header-inner">

            <div class="brand">
                RUET Connect
            </div>

            <div class="header-right">

                <span class="welcome-text">
                    Welcome, <?php echo htmlspecialchars($teacher_name); ?>
                </span>

                <a href="logout.php" class="logout-btn">
                    Logout
                </a>

            </div>

        </div>

    </header>


    <!-- =====================================================
         NAVIGATION
    ===================================================== -->

    <nav class="main-nav">

        <a href="teacher.php">
            Dashboard
        </a>

        <a href="teacher_courses.php">
            Courses
        </a>

        <a href="teacher_materials.php">
            Materials
        </a>

        <a href="#">
            Marks
        </a>

        <a href="#">
            Attendance
        </a>

        <a href="#">
            Routine
        </a>

        <a href="teacher_appointments.php" class="active">
            Appointments
        </a>

    </nav>


    <!-- =====================================================
         MAIN CONTENT
    ===================================================== -->

    <main class="page-container">

        <div class="page-heading">

            <h1>
                Student Appointments
            </h1>

            <p>
                Review and manage appointment requests from your students.
            </p>

        </div>


        <?php if (!empty($message)): ?>

            <div class="alert <?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php endif; ?>


        <?php
        /*
         * Calculate summary values.
         * The result set is first stored in an array so it can
         * be displayed after calculating the summary.
         */

        $appointment_rows = [];

        while ($row = $appointments->fetch_assoc()) {
            $appointment_rows[] = $row;
        }

        $pending_count = 0;
        $approved_count = 0;
        $completed_count = 0;

        foreach ($appointment_rows as $row) {
            if ($row["status"] === "pending") {
                $pending_count++;
            }

            if ($row["status"] === "approved") {
                $approved_count++;
            }

            if ($row["status"] === "completed") {
                $completed_count++;
            }
        }
        ?>


        <!-- Summary -->
        <section class="summary-grid">

            <div class="summary-card">
                <h3>Pending Requests</h3>
                <strong><?php echo $pending_count; ?></strong>
            </div>

            <div class="summary-card">
                <h3>Approved Appointments</h3>
                <strong><?php echo $approved_count; ?></strong>
            </div>

            <div class="summary-card">
                <h3>Completed Appointments</h3>
                <strong><?php echo $completed_count; ?></strong>
            </div>

        </section>


        <!-- Appointment Requests -->
        <section class="appointments-card">

            <h2>
                Appointment Requests
            </h2>


            <?php if (count($appointment_rows) > 0): ?>

                <?php foreach ($appointment_rows as $appointment): ?>

                    <article class="appointment-item">

                        <div class="appointment-header">

                            <div>

                                <div class="student-name">
                                    <?php
                                    echo htmlspecialchars(
                                        $appointment["student_name"]
                                    );
                                    ?>
                                </div>

                                <div class="student-meta">

                                    <?php
                                    echo htmlspecialchars(
                                        $appointment["student_email"]
                                    );
                                    ?>

                                    <?php if (!empty($appointment["student_id"])): ?>
                                        <br>
                                        Student ID:
                                        <?php
                                        echo htmlspecialchars(
                                            $appointment["student_id"]
                                        );
                                        ?>
                                    <?php endif; ?>

                                </div>

                            </div>

                            <span class="status <?php echo htmlspecialchars($appointment["status"]); ?>">
                                <?php echo htmlspecialchars($appointment["status"]); ?>
                            </span>

                        </div>


                        <!-- Appointment Information -->
                        <div class="appointment-details">

                            <div class="detail-item">

                                <span class="detail-label">
                                    Date
                                </span>

                                <div class="detail-value">
                                    <?php
                                    echo date(
                                        "d M Y",
                                        strtotime($appointment["appointment_date"])
                                    );
                                    ?>
                                </div>

                            </div>


                            <div class="detail-item">

                                <span class="detail-label">
                                    Time
                                </span>

                                <div class="detail-value">
                                    <?php
                                    echo date(
                                        "h:i A",
                                        strtotime($appointment["appointment_time"])
                                    );
                                    ?>
                                </div>

                            </div>


                            <div class="detail-item">

                                <span class="detail-label">
                                    Department
                                </span>

                                <div class="detail-value">
                                    <?php
                                    echo htmlspecialchars(
                                        $appointment["student_department"] ?: "Not specified"
                                    );
                                    ?>
                                </div>

                            </div>


                            <div class="detail-item">

                                <span class="detail-label">
                                    Academic Information
                                </span>

                                <div class="detail-value">

                                    <?php
                                    if (
                                        !empty($appointment["academic_year"]) &&
                                        !empty($appointment["semester"])
                                    ) {
                                        echo "Year " .
                                            htmlspecialchars($appointment["academic_year"]) .
                                            ", Semester " .
                                            htmlspecialchars($appointment["semester"]);
                                    } else {
                                        echo "Not specified";
                                    }
                                    ?>

                                </div>

                            </div>


                            <div class="detail-item">

                                <span class="detail-label">
                                    Purpose
                                </span>

                                <div class="detail-value">
                                    <?php
                                    echo htmlspecialchars(
                                        $appointment["purpose"]
                                    );
                                    ?>
                                </div>

                            </div>


                            <div class="detail-item">

                                <span class="detail-label">
                                    Submitted On
                                </span>

                                <div class="detail-value">
                                    <?php
                                    echo date(
                                        "d M Y, h:i A",
                                        strtotime($appointment["created_at"])
                                    );
                                    ?>
                                </div>

                            </div>

                        </div>


                        <!-- Student Message -->
                        <?php if (!empty($appointment["message"])): ?>

                            <div class="message-box">

                                <strong>
                                    Student Message:
                                </strong>

                                <br>

                                <?php
                                echo nl2br(
                                    htmlspecialchars($appointment["message"])
                                );
                                ?>

                            </div>

                        <?php endif; ?>


                        <!-- Teacher Response -->
                        <?php if (!empty($appointment["teacher_response"])): ?>

                            <div class="message-box">

                                <strong>
                                    Previous Teacher Response:
                                </strong>

                                <br>

                                <?php
                                echo nl2br(
                                    htmlspecialchars($appointment["teacher_response"])
                                );
                                ?>

                            </div>

                        <?php endif; ?>


                        <!-- Update Form -->
                        <form
                            method="POST"
                            action="teacher_appointments.php"
                            class="update-form"
                        >

                            <input
                                type="hidden"
                                name="appointment_id"
                                value="<?php echo (int) $appointment["id"]; ?>"
                            >

                            <div class="form-group">

                                <label for="status_<?php echo (int) $appointment["id"]; ?>">
                                    Update Status
                                </label>

                                <select
                                    name="status"
                                    id="status_<?php echo (int) $appointment["id"]; ?>"
                                    required
                                >

                                    <option
                                        value="pending"
                                        <?php
                                        echo $appointment["status"] === "pending"
                                            ? "selected"
                                            : "";
                                        ?>
                                    >
                                        Pending
                                    </option>

                                    <option
                                        value="approved"
                                        <?php
                                        echo $appointment["status"] === "approved"
                                            ? "selected"
                                            : "";
                                        ?>
                                    >
                                        Approved
                                    </option>

                                    <option
                                        value="rejected"
                                        <?php
                                        echo $appointment["status"] === "rejected"
                                            ? "selected"
                                            : "";
                                        ?>
                                    >
                                        Rejected
                                    </option>

                                    <option
                                        value="completed"
                                        <?php
                                        echo $appointment["status"] === "completed"
                                            ? "selected"
                                            : "";
                                        ?>
                                    >
                                        Completed
                                    </option>

                                    <option
                                        value="cancelled"
                                        <?php
                                        echo $appointment["status"] === "cancelled"
                                            ? "selected"
                                            : "";
                                        ?>
                                    >
                                        Cancelled
                                    </option>

                                </select>

                            </div>


                            <div class="form-group">

                                <label for="response_<?php echo (int) $appointment["id"]; ?>">
                                    Response / Meeting Details
                                </label>

                                <textarea
                                    name="teacher_response"
                                    id="response_<?php echo (int) $appointment["id"]; ?>"
                                    placeholder="Write a response or meeting location..."
                                ><?php
                                echo htmlspecialchars(
                                    $appointment["teacher_response"] ?? ""
                                );
                                ?></textarea>

                            </div>


                            <button
                                type="submit"
                                class="update-btn"
                            >
                                Update
                            </button>

                        </form>

                    </article>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty-state">

                    No student appointment requests are available yet.

                </div>

            <?php endif; ?>

        </section>

    </main>

</body>

</html>

<?php
$appointments_query->close();
$conn->close();
?>
