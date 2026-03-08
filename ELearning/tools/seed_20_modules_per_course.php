<?php
declare(strict_types=1);

require_once __DIR__ . '/../dbConnection.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function has_table(mysqli $conn, string $t): bool {
  $st = $conn->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
  $st->bind_param("s", $t);
  $st->execute();
  $r = $st->get_result();
  return (bool)($r && $r->num_rows > 0);
}
function has_col(mysqli $conn, string $t, string $c): bool {
  $st = $conn->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
  $st->bind_param("ss", $t, $c);
  $st->execute();
  $r = $st->get_result();
  return (bool)($r && $r->num_rows > 0);
}
function pick_first_col(mysqli $conn, string $t, array $cands, string $fallback): string {
  foreach ($cands as $c) if (has_col($conn, $t, $c)) return $c;
  return $fallback;
}

try {
  $courseTable = has_table($conn, "course") ? "course" : (has_table($conn, "courses") ? "courses" : "");
  if ($courseTable === "") { echo "ERR: Course table not found\n"; exit(2); }

  $lessonTable = has_table($conn, "lesson") ? "lesson" : (has_table($conn, "lessons") ? "lessons" : "");
  if ($lessonTable === "") { echo "ERR: Lesson table not found (expected lesson/lessons)\n"; exit(3); }

  $courseIdCol   = pick_first_col($conn, $courseTable, ["course_id","courseId","id"], "course_id");
  $courseNameCol = pick_first_col($conn, $courseTable, ["course_name","courseName","name","title"], "course_name");

  $lessonCourseIdCol = pick_first_col($conn, $lessonTable, ["course_id","courseId","course_id_fk"], "course_id");
  $lessonNameCol     = pick_first_col($conn, $lessonTable, ["lesson_name","lessonName","title","name"], "lesson_name");
  $lessonDescCol     = pick_first_col($conn, $lessonTable, ["lesson_desc","lessonDesc","description","desc"], "lesson_desc");

  $lessonLinkCol = null;
  foreach (["lesson_link","lessonLink","lesson_video","video_link","video"] as $c) {
    if (has_col($conn, $lessonTable, $c)) { $lessonLinkCol = $c; break; }
  }
  $createdAtCol = null;
  foreach (["created_at","createdAt","createdOn","created_on","added_on"] as $c) {
    if (has_col($conn, $lessonTable, $c)) { $createdAtCol = $c; break; }
  }

  $modules = [
    "Introduction & Outcomes","Tooling Setup","Project Structure Overview","Core Concepts","Fundamentals Practice",
    "Inputs & Outputs","Data Types & Validation","Control Flow","Functions & Reuse","Working with Files",
    "Working with Databases","Security Basics","Error Handling","Debugging Techniques","Performance Tips",
    "Mini Project - Part 1","Mini Project - Part 2","Testing Basics","Deployment Notes","Final Review & Next Steps",
  ];

  $courses = $conn->query("SELECT {$courseIdCol} AS cid, {$courseNameCol} AS cname FROM {$courseTable} ORDER BY {$courseIdCol} ASC");

  $countSt = $conn->prepare("SELECT COUNT(*) AS cnt FROM {$lessonTable} WHERE {$lessonCourseIdCol} = ?");

  $cols = [$lessonCourseIdCol, $lessonNameCol, $lessonDescCol];
  $place = ["?","?","?"];
  $types = "iss";
  if ($lessonLinkCol !== null) { $cols[] = $lessonLinkCol; $place[] = "?"; $types .= "s"; }
  if ($createdAtCol !== null) { $cols[] = $createdAtCol; $place[] = "?"; $types .= "s"; }

  $insSt = $conn->prepare("INSERT INTO {$lessonTable} (" . implode(",", $cols) . ") VALUES (" . implode(",", $place) . ")");
  $now = date("Y-m-d H:i:s");

  $totalInserted = 0;
  while ($c = $courses->fetch_assoc()) {
    $cid = (string)$c["cid"];
    $cname = (string)($c["cname"] ?? "Course");

    $countSt->bind_param("s", $cid);
    $countSt->execute();
    $existing = (int)(($countSt->get_result()->fetch_assoc())["cnt"] ?? 0);

    if ($existing >= 20) { echo "[SKIP] course_id={$cid} has {$existing}\n"; continue; }

    $toAdd = 20 - $existing;
    echo "[ADD]  course_id={$cid} add {$toAdd} (has {$existing})\n";

    for ($i = $existing; $i < 20; $i++) {
      $n = $i + 1;
      $title = sprintf("Module %02d - %s", $n, $modules[$i] ?? ("Topic ".$n));
      $desc  = "Auto-generated module for: " . $cname;

      $bind = [$cid, $title, $desc];
      if ($lessonLinkCol !== null) $bind[] = "";
      if ($createdAtCol !== null) $bind[] = $now;

      $insSt->bind_param($types, ...$bind);
      $insSt->execute();
      $totalInserted++;
    }
  }

  echo "[DONE] inserted={$totalInserted}\n";

} catch (Throwable $e) {
  echo "FATAL: " . $e->getMessage() . "\n";
  echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
  exit(9);
}
