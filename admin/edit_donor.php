<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireAdmin();
$page_title = 'Edit Donor';

$id = (int)($_GET['id'] ?? 0);
$donor = $conn->query("SELECT * FROM donors WHERE id=$id")->fetch_assoc();
if (!$donor) { echo "Donor not found."; exit; }

$blood_groups = $conn->query("SELECT * FROM blood_groups");
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = clean($conn, $_POST['name']);
    $age       = (int)$_POST['age'];
    $gender    = clean($conn, $_POST['gender']);
    $bg_id     = (int)$_POST['blood_group_id'];
    $phone     = clean($conn, $_POST['phone']);
    $email     = clean($conn, $_POST['email']);
    $address   = clean($conn, $_POST['address']);
    $last_date = $_POST['last_donation_date'] ?: null;

    $stmt = $conn->prepare("UPDATE donors SET name=?,age=?,gender=?,blood_group_id=?,phone=?,email=?,address=?,last_donation_date=? WHERE id=?");
    $stmt->bind_param("sissssssl", $name, $age, $gender, $bg_id, $phone, $email, $address, $last_date, $id);
    if ($stmt->execute()) redirect('/blood_bank/admin/donors.php?msg=saved');
    else $error = 'Update failed.';
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="d-flex">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
    <h1 class="page-title"><i class="bi bi-pencil me-2 text-danger"></i>Edit Donor</h1>
    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <div class="card" style="max-width:680px;">
        <div class="card-body p-4">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Full Name *</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($donor['name']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Age *</label>
                        <input type="number" name="age" class="form-control" value="<?= $donor['age'] ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Gender</label>
                        <select name="gender" class="form-select">
                            <?php foreach (['Male','Female','Other'] as $g): ?>
                            <option <?= $donor['gender']===$g?'selected':'' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Blood Group *</label>
                        <select name="blood_group_id" class="form-select" required>
                            <?php while ($bg = $blood_groups->fetch_assoc()): ?>
                            <option value="<?= $bg['id'] ?>" <?= $donor['blood_group_id']==$bg['id']?'selected':'' ?>><?= $bg['name'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Phone *</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($donor['phone']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($donor['email']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Address</label>
                        <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($donor['address']) ?></textarea>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold small">Last Donation Date</label>
                        <input type="date" name="last_donation_date" class="form-control" value="<?= $donor['last_donation_date'] ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-danger px-4">Update Donor</button>
                        <a href="/blood_bank/admin/donors.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
