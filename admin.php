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
        $message = "<div class='alert error'>Please select a course and at least one program.</div>";
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
            $message = "<div class='alert success'>Successfully saved for " . count($selected_programs) . " programs!</div>";
        } catch (Exception $e) {
            $db->rollBack();
            $message = "<div class='alert error'>Database Error: " . $e->getMessage() . "</div>";
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
    <title>Admin Interface</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="admin-container">
        <header class="main-header">
            <h2>Course Offering</h2>
            <?= $message ?>
        </header>

        <form method="POST" onsubmit="return validateForm()" class="grid-layout">
            
            <div class="card config-panel">
                <div class="form-item">
                    <label>Offering Department</label>
                    <select name="dept_code" required>
                        <option value="">-- Select --</option>
                        <?php foreach($depts as $d): ?>
                            <option value="<?= htmlspecialchars($d['dept_code']) ?>"><?= htmlspecialchars($d['dept_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-item">
                    <label>Semester</label>
                    <select name="semester" required>
                        <?php for($i=1; $i<=8; $i++) echo "<option value='$i'>S$i</option>"; ?>
                    </select>
                </div>

                <div class="form-item">
                    <label>Course Name</label>
                    <input list="courseList" id="courseInput" oninput="syncCourse()" placeholder="Search..." required>
                    <datalist id="courseList">
                        <?php foreach($courses as $c): ?>
                            <option data-id="<?= $c['course_id'] ?>" value="<?= htmlspecialchars($c['course_name']) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <input type="hidden" name="course_id" id="hiddenCourseId">
                </div>

                <div class="form-item">
                    <label>Course Type</label>
                    <select name="basket_id">
                        <option value="">-- Select --</option>
                        <?php foreach($baskets as $b): ?>
                            <option value="<?= $b['basket_id'] ?>"><?= htmlspecialchars($b['basket_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="spacer"></div>
                <button type="submit" name="save_offering" class="save-btn">Save</button>
            </div>

            <div class="card matrix-panel">
                <div class="pane-header">
                    <label>Target Programs</label>
                    <input type="text" id="progSearch" onkeyup="filterProgs()" placeholder="Filter...">
                </div>
                
                <div class="list-container" id="progList">
                    <?php foreach($progs as $p): ?>
                        <label class="list-row">
                            <input type="checkbox" name="program_ids[]" value="<?= $p['program_id'] ?>" onchange="updateRow(this)">
                            <div class="check-ui"></div>
                            <span class="prog-text"><?= htmlspecialchars($p['program_name']) ?></span>
                        </label>
                    <?php endforeach; ?>
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
        document.querySelectorAll('.list-row').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(val) ? "flex" : "none";
        });
    }

    function updateRow(checkbox) {
        checkbox.closest('.list-row').classList.toggle('selected', checkbox.checked);
    }

    function validateForm() {
        if (!document.getElementById('hiddenCourseId').value) {
            alert("Select a valid course."); return false;
        }
        if (document.querySelectorAll('input[name="program_ids[]"]:checked').length === 0) {
            alert("Select at least one program."); return false;
        }
        return true;
    }
    </script>
</body>
</html>