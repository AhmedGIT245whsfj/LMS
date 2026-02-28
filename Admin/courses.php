<?php
if (!isset($_SESSION)) { session_start(); }
define('TITLE', 'Courses');
define('PAGE', 'courses');

include('../dbConnection.php');
include('./adminInclude/header.php');

if (!isset($_SESSION['is_admin_login'])) {
  echo "<script> location.href='../index.php'; </script>";
  exit;
}

if (isset($_POST['delete']) && isset($_POST['id'])) {
  $id = (int)$_POST['id'];
  if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM course WHERE course_id = ? LIMIT 1");
    if ($stmt) {
      $stmt->bind_param("i", $id);
      $stmt->execute();
      $stmt->close();

      // Also remove lessons/modules for this course
      $stmt2 = $conn->prepare("DELETE FROM lesson WHERE course_id = ?");
      if ($stmt2) {
        $stmt2->bind_param("i", $id);
        $stmt2->execute();
        $stmt2->close();
      }

      echo '<meta http-equiv="refresh" content="0;URL=?deleted" />';
      exit;
    }
  }
}

$sql = "
  SELECT c.course_id, c.course_name, c.course_author, c.track_id, t.track_name
  FROM course c
  LEFT JOIN tracks t ON t.track_id = c.track_id
  ORDER BY c.course_id ASC
";
$result = $conn->query($sql);
?>
<div class="col-sm-9 mt-5">
  <p class="bg-dark text-white p-2">List of Courses</p>

  <?php if ($result && $result->num_rows > 0): ?>
    <table class="table">
      <thead>
        <tr>
          <th scope="col">Course ID</th>
          <th scope="col">Name</th>
          <th scope="col">Track</th>
          <th scope="col">Author</th>
          <th scope="col">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr>
            <th scope="row"><?php echo (int)$row['course_id']; ?></th>
            <td><?php echo htmlspecialchars($row['course_name']); ?></td>
            <td><?php echo htmlspecialchars($row['track_name'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($row['course_author']); ?></td>
            <td>
              <form action="editcourse.php" method="POST" class="d-inline">
                <input type="hidden" name="id" value="<?php echo (int)$row['course_id']; ?>">
                <button type="submit" class="btn btn-info mr-3" name="view" value="View"><i class="fas fa-pen"></i></button>
              </form>
              <form action="" method="POST" class="d-inline" onsubmit="return confirm('Delete this course and its modules?');">
                <input type="hidden" name="id" value="<?php echo (int)$row['course_id']; ?>">
                <button type="submit" class="btn btn-secondary" name="delete" value="Delete"><i class="far fa-trash-alt"></i></button>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="alert alert-dark">No courses found.</div>
  <?php endif; ?>
</div>

</div>
<div><a class="btn btn-danger box" href="./addCourse.php"><i class="fas fa-plus fa-2x"></i></a></div>
</div>
<?php include('./adminInclude/footer.php'); ?>
