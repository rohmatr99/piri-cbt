<?php
require __DIR__ . "/includes/auth.php";
require __DIR__ . "/../config/database.php";

$admin_id = (int) ($_SESSION["admin_id"] ?? 0);
$admin_role = $_SESSION["admin_role"] ?? "admin";

if ($admin_role === "superadmin") {
    $sql = "
        SELECT u.id, u.nama_ujian, u.kelas, u.durasi, u.minimal_menit,
               u.token, u.tanggal_mulai, u.tanggal_selesai, u.status,
               m.nama AS nama_mapel
        FROM ujian u
        LEFT JOIN mata_pelajaran m ON u.mapel_id = m.id
        ORDER BY u.id DESC
    ";
    $result = $conn->query($sql);
} else {
    $stmt = $conn->prepare("
        SELECT u.id, u.nama_ujian, u.kelas, u.durasi, u.minimal_menit,
               u.token, u.tanggal_mulai, u.tanggal_selesai, u.status,
               m.nama AS nama_mapel
        FROM ujian u
        INNER JOIN admin_mapel am
            ON am.mapel_id = u.mapel_id
           AND am.admin_id = ?
        LEFT JOIN mata_pelajaran m ON u.mapel_id = m.id
        ORDER BY u.id DESC
    ");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
}

if (!$result) {
    die("Query gagal: " . $conn->error);
}

$page_title = "Manajemen Ujian";
$page_description = "Kelola daftar ujian CBT";

require __DIR__ . "/includes/header.php";
require __DIR__ . "/includes/sidebar.php";
?>

<style>
.ujian-card{background:white;padding:20px;border-radius:14px;box-shadow:0 4px 14px rgba(15,23,42,.05);border:1px solid var(--border)}
.ujian-atas{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:18px}
.ujian-atas h2{margin:0}.ujian-atas p{margin:6px 0 0;color:var(--muted)}
.ujian-tombol{display:inline-block;padding:10px 13px;color:white;text-decoration:none;border-radius:8px;font-size:13px;font-weight:700;margin-left:5px}
.ujian-bank{background:#6f42c1}.ujian-tambah{background:#198754}
.ujian-table-wrap{overflow-x:auto}.ujian-table{width:100%;border-collapse:collapse;min-width:1050px}
.ujian-table th,.ujian-table td{padding:12px;border-bottom:1px solid var(--border);text-align:left;vertical-align:top}
.ujian-table th{background:#f1f5f9}
.ujian-status{display:inline-block;padding:5px 10px;border-radius:20px;font-size:12px;font-weight:700}
.ujian-status-aktif{background:#d1e7dd;color:#0f5132}.ujian-status-nonaktif{background:#f8d7da;color:#842029}
.ujian-status-draft{background:#fff3cd;color:#664d03}.ujian-status-selesai{background:#e2e3e5;color:#41464b}
.ujian-aksi{display:flex;flex-wrap:wrap;gap:5px}.ujian-btn{display:inline-block;padding:7px 9px;color:white;text-decoration:none;border-radius:7px;font-size:12px;font-weight:700}
.ujian-soal{background:#198754}.ujian-edit{background:#0d6efd}.ujian-hapus{background:#dc3545}
.ujian-kosong{text-align:center!important;padding:30px!important;color:#777}.ujian-kembali{display:inline-block;margin-top:15px;padding:10px 15px;background:#64748b;color:white;text-decoration:none;border-radius:8px}
@media(max-width:700px){.ujian-card{padding:15px}.ujian-atas{flex-direction:column;align-items:stretch}.ujian-tombol{margin:4px 0 0;text-align:center}}
</style>

<main class="main">
<header class="topbar">
    <div class="page-title"><h1><?= htmlspecialchars($page_title) ?></h1><p><?= htmlspecialchars($page_description) ?></p></div>
    <div class="top-user"><div class="top-user-icon">👤</div><span><?= htmlspecialchars($_SESSION["admin_nama"] ?? "Administrator") ?></span></div>
</header>
<header class="mobile-header">
    <button type="button" class="menu-button" onclick="bukaSidebar()">☰</button>
    <div class="mobile-title">PIRI CBT — Admin</div><div style="width:40px;"></div>
</header>
<section class="content">
<div class="ujian-card">
    <div class="ujian-atas">
        <div><h2>Manajemen Ujian</h2><p>Kelola daftar ujian CBT.</p></div>
        <div>
            <a href="bank-soal.php" class="ujian-tombol ujian-bank">📚 Bank Soal</a>
            <a href="tambah-ujian.php" class="ujian-tombol ujian-tambah">+ Tambah Ujian</a>
        </div>
    </div>
    <div class="ujian-table-wrap">
        <table class="ujian-table">
            <thead><tr>
                <th>No</th><th>Nama Ujian</th><th>Mata Pelajaran</th><th>Kelas</th>
                <th>Durasi</th><th>Minimal</th><th>Token</th><th>Status</th><th>Aksi</th>
            </tr></thead>
            <tbody>
            <?php if ($result->num_rows === 0): ?>
                <tr><td colspan="9" class="ujian-kosong">Belum ada data ujian.</td></tr>
            <?php else: ?>
                <?php $no=1; while($row=$result->fetch_assoc()): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row["nama_ujian"]) ?></td>
                    <td><?= htmlspecialchars($row["nama_mapel"] ?? "-") ?></td>
                    <td><?= htmlspecialchars($row["kelas"]) ?></td>
                    <td><?= (int)$row["durasi"] ?> menit</td>
                    <td><?= (int)$row["minimal_menit"] ?> menit</td>
                    <td><?= htmlspecialchars($row["token"]) ?></td>
                    <td><span class="ujian-status ujian-status-<?= htmlspecialchars($row["status"]) ?>"><?= strtoupper(htmlspecialchars($row["status"])) ?></span></td>
                    <td><div class="ujian-aksi">
                        <a href="soal.php?ujian_id=<?= (int)$row["id"] ?>" class="ujian-btn ujian-soal">📝 Soal</a>
                        <a href="edit-ujian.php?id=<?= (int)$row["id"] ?>" class="ujian-btn ujian-edit">✏️ Edit</a>
                        <a href="hapus-ujian.php?id=<?= (int)$row["id"] ?>" class="ujian-btn ujian-hapus" onclick="return confirm('Yakin ingin menghapus ujian ini?');">🗑️ Hapus</a>
                    </div></td>
                </tr>
                <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <a href="dashboard.php" class="ujian-kembali">← Kembali ke Dashboard</a>
</div>
</section>
</main>
<?php require __DIR__ . "/includes/footer.php"; ?>
