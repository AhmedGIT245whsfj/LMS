<?php
if (!isset($_SESSION)) { session_start(); }
define('TITLE', 'Tracks');
define('PAGE', 'tracks');

include('../dbConnection.php');
include('./adminInclude/header.php');

if (!isset($_SESSION['is_admin_login'])) {
  echo "<script> location.href='../index.php'; </script>";
  exit;
}

if (isset($_POST['delete']) && isset($_POST['id'])) {
  $id = (int)$_POST['id'];
  if ($id > 0) {
    $stmt0 = $conn->prepare("SELECT track_img FROM tracks WHERE track_id = ? LIMIT 1");
    $oldImg = null;
    if ($stmt0) {
      $stmt0->bind_param("i", $id);
      $stmt0->execute();
      $r0 = $stmt0->get_result();
      $d0 = $r0 ? $r0->fetch_assoc() : null;
      $oldImg = $d0 ? (string)($d0['track_img'] ?? '') : null;
      $stmt0->close();
    }

    $stmt = $conn->prepare("DELETE FROM tracks WHERE track_id = ? LIMIT 1");
    if ($stmt) {
      $stmt->bind_param("i", $id);
      $stmt->execute();
      $stmt->close();

      $stmt2 = $conn->prepare("UPDATE course SET track_id = NULL WHERE track_id = ?");
      if ($stmt2) {
        $stmt2->bind_param("i", $id);
        $stmt2->execute();
        $stmt2->close();
      }

      if ($oldImg && strpos($oldImg, '/media/track/') === 0) {
        $fs = realpath(__DIR__ . '/..' . $oldImg);
        if ($fs && is_file($fs)) {
          @unlink($fs);
        }
      }

      echo '<meta http-equiv="refresh" content="0;URL=?deleted" />';
      exit;
    }
  }
}

$result = $conn->query("SELECT track_id, track_name, track_desc, track_img FROM tracks ORDER BY track_id ASC");
?>
<div class="col-sm-9 mt-5">
  <p class="bg-dark text-white p-2">List of Tracks</p>
  <?php if ($result && $result->num_rows > 0): ?>
    <table class="table">
      <thead>
        <tr>
          <th scope="col">Track ID</th>
          <th scope="col">Image</th>
          <th scope="col">Name</th>
          <th scope="col">Description</th>
          <th scope="col">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
          <?php $img = (string)($row['track_img'] ?? ''); ?>
          <tr>
            <th scope="row"><?php echo (int)$row['track_id']; ?></th>
            <td>
              <?php if ($img !== ''): ?>
                <img src="<?php echo htmlspecialchars($img); ?>" alt="Track" style="width:90px;height:54px;object-fit:cover;border:1px solid #ddd;border-radius:6px;">
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($row['track_name']); ?></td>
            <td><?php echo htmlspecialchars($row['track_desc']); ?></td>
            <td>
              <form action="edittrack.php" method="POST" class="d-inline">
                <input type="hidden" name="id" value="<?php echo (int)$row['track_id']; ?>">
                <button type="submit" class="btn btn-info mr-3" name="view" value="View">
                  <i class="fas fa-pen"></i>
                </button>
              </form>
              <form action="" method="POST" class="d-inline" onsubmit="return confirm('Delete this track? Courses will be unassigned.');">
                <input type="hidden" name="id" value="<?php echo (int)$row['track_id']; ?>">
                <button type="submit" class="btn btn-secondary" name="delete" value="Delete">
                  <i class="far fa-trash-alt"></i>
                </button>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="alert alert-dark">No tracks found.</div>
  <?php endif; ?>
</div>

</div>
<div><a class="btn btn-danger box" href="./addTrack.php"><i class="fas fa-plus fa-2x"></i></a></div>
</div>

<?php include('./adminInclude/footer.php'); ?>
