<?php
session_start();

require_once "config/database.php";

/* Student-only access */
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit();
}

$student_id = (int) $_SESSION["user_id"];
$student_name = $_SESSION["full_name"] ?? "Student";

$message = "";
$message_type = "";

/* Submit appointment request */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $teacher_id = (int) ($_POST["teacher_id"] ?? 0);
    $appointment_date = $_POST["appointment_date"] ?? "";
    $appointment_time = $_POST["appointment_time"] ?? "";
    $purpose = trim($_POST["purpose"] ?? "");
    $student_message = trim($_POST["message"] ?? "");

    if (
        $teacher_id <= 0 ||
        empty($appointment_date) ||
        empty($appointment_time) ||
        empty($purpose)
    ) {
        $message = "Please fill in all required fields.";
        $message_type = "error";
    } elseif ($appointment_date < date("Y-m-d")) {
        $message = "You cannot select a past date.";
        $message_type = "error";
    } else {
        /* Verify that the selected user is a teacher */
        $teacher_check = $conn->prepare(
            "SELECT id FROM users WHERE id = ? AND role = 'teacher'"
        );

        $teacher_check->bind_param("i", $teacher_id);
        $teacher_check->execute();

        $teacher_result = $teacher_check->get_result();

        if ($teacher_result->num_rows === 0) {
            $message = "Invalid teacher selected.";
            $message_type = "error";
        } else {
            $insert = $conn->prepare(
                "INSERT INTO appointments
                (
                    student_id,
                    teacher_id,
                    appointment_date,
                    appointment_time,
                    purpose,
                    message
                )
                VALUES (?, ?, ?, ?, ?, ?)"
            );

            $insert->bind_param(
                "iissss",
                $student_id,
                $teacher_id,
                $appointment_date,
                $appointment_time,
                $purpose,
                $student_message
            );

            if ($insert->execute()) {
                $message = "Appointment request submitted successfully.";
                $message_type = "success";
            } else {
                $message = "Something went wrong. Please try again.";
                $message_type = "error";
            }

            $insert->close();
        }

        $teacher_check->close();
    }
}

/* Load all teachers */
$teachers = $conn->query(
    "SELECT id, full_name, department, email
     FROM users
     WHERE role = 'teacher'
     ORDER BY full_name ASC"
);

/* Load student's appointment requests */
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
        u.full_name AS teacher_name,
        u.department AS teacher_department
     FROM appointments a
     INNER JOIN users u ON a.teacher_id = u.id
     WHERE a.student_id = ?
     ORDER BY a.appointment_date DESC, a.appointment_time DESC"
);

$appointments_query->bind_param("i", $student_id);
$appointments_query->execute();

$appointments = $appointments_query->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Appointments | RUET Connect</title>

    <style>
        /* ==============================
           GLOBAL RESET
        ============================== */

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

        /* ==============================
           HEADER
        ============================== */

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

        /* ==============================
           NAVIGATION
        ============================== */

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

        /* ==============================
           MAIN PAGE
        ============================== */

        .appointment-page {
            width: 100%;
            max-width: 1400px;
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

        /* ==============================
           ALERTS
        ============================== */

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

        /* ==============================
           TWO-COLUMN LAYOUT
        ============================== */

        .appointment-layout {
            display: grid;
            grid-template-columns: minmax(300px, 390px) minmax(0, 1fr);
            gap: 30px;
            align-items: start;
        }

        .appointment-card {
            min-width: 0;
            background: #ffffff;
            border: 1px solid #eadfe1;
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 10px 30px rgba(61, 28, 36, 0.07);
        }

        .appointment-card h2 {
            color: #3d1c24;
            font-size: 22px;
            margin-bottom: 25px;
        }

        /* ==============================
           FORM
        ============================== */

        .form-group {
            margin-bottom: 19px;
        }

        .form-group label {
            display: block;
            color: #3d1c24;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            border: 1px solid #d9cbd0;
            border-radius: 9px;
            background: #fffdfd;
            color: #2f2024;
            padding: 13px 14px;
            font-family: inherit;
            font-size: 14px;
            transition: 0.2s ease;
        }

        .form-group input,
        .form-group select {
            min-height: 48px;
        }

        .form-group textarea {
            min-height: 110px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #daa520;
            box-shadow: 0 0 0 3px rgba(218, 165, 32, 0.16);
        }

        .submit-btn {
            width: 100%;
            min-height: 49px;
            border: none;
            border-radius: 9px;
            background: #daa520;
            color: #3d1c24;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .submit-btn:hover {
            background: #c99416;
            transform: translateY(-1px);
        }

        /* ==============================
           APPOINTMENT HISTORY
        ============================== */

        .appointment-item {
            padding: 20px;
            margin-bottom: 16px;
            border: 1px solid #eadfe1;
            border-radius: 13px;
            background: #fffdfd;
        }

        .appointment-item:last-child {
            margin-bottom: 0;
        }

        .appointment-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 15px;
        }

        .appointment-top h3 {
            color: #3d1c24;
            font-size: 17px;
            margin-bottom: 6px;
        }

        .appointment-date {
            color: #777070;
            font-size: 13px;
        }

        .status {
            display: inline-block;
            padding: 6px 11px;
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
            color: #5f5558;
            font-size: 14px;
            line-height: 1.7;
        }

        .appointment-details p {
            margin-bottom: 7px;
            overflow-wrap: anywhere;
        }

        .appointment-details strong {
            color: #3d1c24;
        }

        .teacher-response {
            margin-top: 14px;
            padding: 13px 15px;
            border-left: 4px solid #daa520;
            border-radius: 7px;
            background: #fff8e5;
            color: #5b4a1b;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .empty-state {
            min-height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #777070;
            font-size: 15px;
            padding: 20px;
        }

        /* ==============================
           RESPONSIVE DESIGN
        ============================== */

        @media (max-width: 900px) {
            .appointment-layout {
                grid-template-columns: 1fr;
            }

            .appointment-card {
                width: 100%;
            }
        }

        @media (max-width: 650px) {
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

            .appointment-page {
                padding: 30px 18px 45px;
            }

            .page-heading h1 {
                font-size: 28px;
            }

            .appointment-card {
                padding: 22px;
            }

            .appointment-top {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

    <!-- Header -->
    <header class="top-header">
        <div class="header-inner">

            <div class="brand">
                RUET Connect
            </div>

            <div class="header-right">
                <span class="welcome-text">
                    Welcome, <?php echo htmlspecialchars($student_name); ?>
                </span>

                <a href="logout.php" class="logout-btn">
                    Logout
                </a>
            </div>

        </div>
    </header>

    <!-- Navigation -->
    <nav class="main-nav">
        <a href="student.php">Dashboard</a>
        <a href="student_courses.php">Courses</a>
        <a href="student_materials.php">Materials</a>
        <a href="#">Marks</a>
        <a href="#">Attendance</a>
        <a href="#">Routine</a>
        <a href="appointments.php" class="active">Appointments</a>
    </nav>

    <!-- Main Content -->
    <main class="appointment-page">

        <div class="page-heading">
            <h1>Appointments</h1>
            <p>
                Request an appointment with a teacher and track your appointment status.
            </p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert <?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="appointment-layout">

            <!-- Request Form -->
            <section class="appointment-card">

                <h2>Request an Appointment</h2>

                <form method="POST" action="appointments.php">

                    <div class="form-group">
                        <label for="teacher_id">Select Teacher *</label>

                        <select name="teacher_id" id="teacher_id" required>
                            <option value="">Choose a teacher</option>

                            <?php if ($teachers && $teachers->num_rows > 0): ?>

                                <?php while ($teacher = $teachers->fetch_assoc()): ?>

                                    <option value="<?php echo (int) $teacher["id"]; ?>">
                                        <?php
                                        echo htmlspecialchars($teacher["full_name"]);

                                        if (!empty($teacher["department"])) {
                                            echo " - " . htmlspecialchars($teacher["department"]);
                                        }
                                        ?>
                                    </option>

                                <?php endwhile; ?>

                            <?php else: ?>

                                <option value="" disabled>
                                    No teachers available
                                </option>

                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="appointment_date">Preferred Date *</label>

                        <input
                            type="date"
                            name="appointment_date"
                            id="appointment_date"
                            min="<?php echo date("Y-m-d"); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="appointment_time">Preferred Time *</label>

                        <input
                            type="time"
                            name="appointment_time"
                            id="appointment_time"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="purpose">Purpose *</label>

                        <input
                            type="text"
                            name="purpose"
                            id="purpose"
                            maxlength="255"
                            placeholder="e.g. Discuss assignment"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="message">Message</label>

                        <textarea
                            name="message"
                            id="message"
                            placeholder="Write additional details..."
                        ></textarea>
                    </div>

                    <button type="submit" class="submit-btn">
                        Submit Request
                    </button>

                </form>

            </section>

            <!-- Appointment History -->
            <section class="appointment-card">

                <h2>My Appointment Requests</h2>

                <?php if ($appointments->num_rows > 0): ?>

                    <?php while ($appointment = $appointments->fetch_assoc()): ?>

                        <div class="appointment-item">

                            <div class="appointment-top">

                                <div>
                                    <h3>
                                        <?php echo htmlspecialchars($appointment["teacher_name"]); ?>
                                    </h3>

                                    <div class="appointment-date">
                                        <?php
                                        echo date(
                                            "d M Y",
                                            strtotime($appointment["appointment_date"])
                                        );
                                        ?>

                                        at

                                        <?php
                                        echo date(
                                            "h:i A",
                                            strtotime($appointment["appointment_time"])
                                        );
                                        ?>
                                    </div>
                                </div>

                                <span class="status <?php echo htmlspecialchars($appointment["status"]); ?>">
                                    <?php echo htmlspecialchars($appointment["status"]); ?>
                                </span>

                            </div>

                            <div class="appointment-details">

                                <p>
                                    <strong>Purpose:</strong>
                                    <?php echo htmlspecialchars($appointment["purpose"]); ?>
                                </p>

                                <?php if (!empty($appointment["message"])): ?>
                                    <p>
                                        <strong>Message:</strong>
                                        <?php echo nl2br(htmlspecialchars($appointment["message"])); ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($appointment["teacher_response"])): ?>
                                    <div class="teacher-response">
                                        <strong>Teacher Response:</strong><br>

                                        <?php
                                        echo nl2br(
                                            htmlspecialchars($appointment["teacher_response"])
                                        );
                                        ?>
                                    </div>
                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endwhile; ?>

                <?php else: ?>

                    <div class="empty-state">
                        You have not submitted any appointment requests yet.
                    </div>

                <?php endif; ?>

            </section>

        </div>

    </main>

</body>

</html>

<?php
$appointments_query->close();
$conn->close();
?>
