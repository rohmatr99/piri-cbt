<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["siswa_id"])) { header("Location: login.php"); exit; }
$siswa_id = (int) $_SESSION["siswa_id"];
$kelas = $_SESSION["kelas"];

$stmt = $conn->prepare("SELECT u.id,u.nama_ujian,u.kelas,u.durasi,u.tanggal_mulai,u.tanggal_selesai,m.nama AS nama_mapel,s.status AS status_sesi FROM ujian u JOIN mata_pelajaran m ON u.mapel_id=m.id LEFT JOIN sesi_ujian s ON s.ujian_id=u.id AND s.siswa_id=? WHERE u.kelas=? AND u.status='aktif' ORDER BY u.id DESC");
$stmt->bind_param("is", $siswa_id, $kelas);
$stmt->execute();
$ujian_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Siswa - PIRI CBT</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:linear-gradient(135deg,#eef5ff,#f8fafc,#eefbf4);color:#1f2937}.container{max-width:680px;margin:30px auto;padding:20px}.card{background:rgba(255,255,255,.96);padding:25px;border-radius:18px;box-shadow:0 8px 25px rgba(15,23,42,.08);margin-bottom:18px;border:1px solid #e2e8f0}h1{margin:0 0 10px;font-size:26px}h3{margin-top:0}.info{line-height:1.8;color:#475569}.ujian{border:1px solid #e2e8f0;padding:20px;border-radius:15px;margin-top:15px;background:#fff}.ujian h4{margin:0 0 10px;font-size:18px}.detail{color:#64748b;line-height:1.8}.status,.mulai,.lanjutkan,.selesai{display:block;width:100%;text-align:center;border-radius:10px;font-weight:bold}.status{margin-top:15px;padding:11px 13px}.status-belum{background:#ecfdf5;color:#047857}.status-mengerjakan{background:#eff6ff;color:#1d4ed8}.status-selesai{background:#f1f5f9;color:#475569}.mulai,.lanjutkan,.selesai{padding:13px 15px;margin-top:15px;text-decoration:none;border:none}.mulai{background:#198754;color:white}.lanjutkan{background:#2563eb;color:white}.selesai{background:#e2e8f0;color:#64748b;cursor:not-allowed}.logout{display:block;text-align:center;padding:13px;background:#dc3545;color:white;text-decoration:none;border-radius:10px;font-weight:bold}.empty{text-align:center;color:#64748b;padding:20px 5px}
</style></head><body><div class="container">
<div class="card"><h1>Halo, <?= htmlspecialchars($_SESSION["nama"]) ?> 👋</h1><div class="info"><strong>Username:</strong> <?= htmlspecialchars($_SESSION["username"]) ?><br><strong>Kelas:</strong> <?= htmlspecialchars($_SESSION["kelas"]) ?></div></div>
<div class="card"><h3>📝 Ujian</h3><p style="color:#64748b">Daftar ujian yang tersedia untuk kelas Anda.</p>
<?php if ($ujian_result->num_rows > 0): ?>
<?php while ($ujian=$ujian_result->fetch_assoc()): ?>
<?php $status_sesi=$ujian["status_sesi"]; $sudah_dikerjakan=in_array($status_sesi,["selesai","habis"],true); $sedang_mengerjakan=($status_sesi==="mengerjakan"); ?>
<div class="ujian"><h4><?= htmlspecialchars($ujian["nama_ujian"]) ?></h4><div class="detail"><strong>Mapel:</strong> <?= htmlspecialchars($ujian["nama_mapel"]) ?><br><strong>Kelas:</strong> <?= htmlspecialchars($ujian["kelas"]) ?><br><strong>Durasi:</strong> <?= htmlspecialchars($ujian["durasi"]) ?> menit</div>
<?php if ($sudah_dikerjakan): ?><div class="status status-selesai">✓ Sudah Dikerjakan</div><div class="selesai">Ujian sudah selesai dan tidak dapat dikerjakan kembali.</div>
<?php elseif ($sedang_mengerjakan): ?><div class="status status-mengerjakan">▶ Sedang Dikerjakan</div><a class="lanjutkan" href="ujian.php?id=<?= (int)$ujian["id"] ?>">Lanjutkan Ujian</a>
<?php else: ?><div class="status status-belum">● Belum Dikerjakan</div><a class="mulai" href="token-ujian.php?id=<?= (int)$ujian["id"] ?>">Mulai Ujian</a><?php endif; ?></div>
<?php endwhile; ?><?php else: ?><div class="empty">Belum ada ujian yang tersedia untuk kelas Anda.</div><?php endif; ?></div>
<a class="logout" href="logout.php">Keluar</a></div></body></html>
