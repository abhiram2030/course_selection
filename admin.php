<?php
try {
    $db = new PDO('sqlite:course_selection');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection Error: " . $e->getMessage());
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_offering'])) {
    $course_id = $_POST['course_id'];
    $semester = $_POST['semester'];
    $off_dept = $_POST['dept_code'];
    $basket_id = !empty($_POST['basket_id']) ? $_POST['basket_id'] : null;
    $selected_programs = $_POST['program_ids'] ?? [];

    if (empty($course_id) || empty($selected_programs)) {
        $message = "<div class='error'>Select a course and at least one program.</div>";
    } else {
        try {
            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO course_offerings 
                (course_id, program_id, semester, basket_id, offering_department, status) 
                VALUES (?, ?, ?, ?, ?, 'ACTIVE')");
            
            foreach ($selected_programs as $p_id) {
                $stmt->execute([$course_id, $p_id, $semester, $basket_id, $off_dept]);
            }
            $db->commit();
            $message = "<div class='success'>Successfully saved for " . count($selected_programs) . " programs!</div>";
        } catch (Exception $e) {
            $db->rollBack();
            $message = "<div class='error'>Database Error: " . $e->getMessage() . "</div>";
        }
    }
}

$depts = $db->query("SELECT dept_name, dept_code FROM departments ORDER BY dept_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$progs = $db->query("SELECT program_id, program_name FROM programs ORDER BY program_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$courses = $db->query("SELECT course_id, course_name FROM courses ORDER BY course_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$baskets = $db->query("SELECT basket_id, basket_name FROM course_baskets ORDER BY basket_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Two Column Layout</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="admin-container">
        <header class="main-header">
            <h2>Course Selection</h2>
            <?= $message ?>
        </header>

        <form method="POST" onsubmit="return validateForm()" class="two-column-grid">
            <div class="column details-column">
                <div class="card">
                    <label>Offering Department</label>
                    <select name="dept_code" required>
                        <option value="">-- Select Department --</option>
                        <?php foreach($depts as $d): ?>
                            <option value="<?= htmlspecialchars($d['dept_code']) ?>"><?= htmlspecialchars($d['dept_name']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label>Semester</label>
                    <select name="semester" required>
                        <?php for($i=1; $i<=8; $i++) echo "<option value='$i'>S$i</option>"; ?>
                    </select>

                    <label>Course Name</label>
                    <input list="courseList" id="courseInput" oninput="syncCourse()" placeholder="Search & Select Course..." required>
                    <datalist id="courseList">
                        <?php foreach($courses as $c): ?>
                            <option data-id="<?= $c['course_id'] ?>" value="<?= htmlspecialchars($c['course_name']) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <input type="hidden" name="course_id" id="hiddenCourseId">

                    <label>Course Type</label>
                    <select name="basket_id">
                        <option value="">-- Select --</option>
                        <?php foreach($baskets as $b): ?>
                            <option value="<?= $b['basket_id'] ?>"><?= htmlspecialchars($b['basket_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="save_offering" class="btn-primary">Save</button>
            </div>

            <div class="column selection-column">
                <div class="card full-height">
                    <label>Programs</label>
                    <div class="search-bar">
                        <input type="text" id="progSearch" onkeyup="filterProgs()" placeholder="Search programs...">
                    </div>
                    <div class="program-list" id="progList">
                        <?php foreach($progs as $p): ?>
                            <label class="program-row" onclick="highlightRow(this)">
                                <input type="checkbox" name="program_ids[]" value="<?= $p['program_id'] ?>">
                                <div class="check-box"></div>
                                <span><?= htmlspecialchars($p['program_name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
    function syncCourse() {
        const input = document.getElementById('courseInput');
        const list = document.getElementById('courseList');
        const hidden = document.getElementById('hiddenCourseId');
        const opt = Array.from(list.options).find(o => o.value === input.value);
        hidden.value = opt ? opt.getAttribute('data-id') : "";
    }

    function filterProgs() {
        let val = document.getElementById('progSearch').value.toLowerCase();
        document.querySelectorAll('.program-row').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(val) ? "flex" : "none";
        });
    }

    function highlightRow(label) {
        const checkbox = label.querySelector('input');
        if(checkbox.checked) {
            label.classList.add('is-selected');
        } else {
            label.classList.remove('is-selected');
        }
    }

    function validateForm() {
        if (!document.getElementById('hiddenCourseId').value) {
            alert("Please select a valid course."); return false;
        }
        if (document.querySelectorAll('input[name="program_ids[]"]:checked').length === 0) {
            alert("Please select at least one recipient program."); return false;
        }
        return true;
    }
    </script>
</body>
</html>
 