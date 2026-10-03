<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["siswa_id"])) {
    header("Location: login.php");
    exit;
}

$siswa_id = $_SESSION["siswa_id"];
$kelas = $_SESSION["kelas"];

/*
|--------------------------------------------------------------------------
| Ambil ujian yang sesuai dengan kelas siswa
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        u.id,
        u.nama_ujian,
        u.kelas,
        u.durasi,
        u.token,
        u.tanggal_mulai,
        u.tanggal_selesai,
        m.nama AS nama_mapel
    FROM ujian u
    JOIN mata_pelajaran m
        ON u.mapel_id = m.id
    WHERE u.kelas = ?
      AND u.status = 'aktif'
    ORDER BY u.id DESC
");

$stmt->bind_param("s", $kelas);
$stmt->execute();

$ujian_result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Dashboard Siswa - PIRI CBT</title>

<style>

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f2f5f9;
}

.container {
    max-width: 600px;
    margin: 30px auto;
    padding: 20px;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 16px;
    box-shadow: 0 4px 15px rgba(0,0,0,.08);
    margin-bottom: 15px;
}

h1 {
    margin-top: 0;
}

.info {
    line-height: 1.8;
}

.ujian {
    border: 1px solid #e5e7eb;
    padding: 18px;
    border-radius: 12px;
    margin-top: 15px;
}

.ujian h4 {
    margin-top: 0;
    margin-bottom: 8px;
}

.detail {
    color: #555;
    line-height: 1.7;
}

.mulai {
    display: block;
    text-align: center;
    padding: 12px;
    margin-top: 15px;
    background: #198754;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: bold;
}

.logout {
    display: block;
    text-align: center;
    padding: 12px;
    background: #d9534f;
    color: white;
    text-decoration: none;
    border-radius: 8px;
}

</style>

</head>

<body>

<div class="container">

<div class="card">

<h1>
Halo, <?= htmlspecialchars($_SESSION["nama"]) ?>
</h1>

<div class="info">

<strong>Username:</strong>
<?= htmlspecialchars($_SESSION["username"]) ?>

<br>

<strong>Kelas:</strong>
<?= htmlspecialchars($_SESSION["kelas"]) ?>

</div>

</div>


<div class="card">

<h3>Ujian Tersedia</h3>

<?php if ($ujian_result->num_rows > 0): ?>

    <?php while ($ujian = $ujian_result->fetch_assoc()): ?>

        <div class="ujian">

            <h4>
                <?= htmlspecialchars($ujian["nama_ujian"]) ?>
            </h4>

            <div class="detail">

                <strong>Mapel:</strong>
                <?= htmlspecialchars($ujian["nama_mapel"]) ?>

                <br>

                <strong>Kelas:</strong>
                <?= htmlspecialchars($ujian["kelas"]) ?>

                <br>

                <strong>Durasi:</strong>
                <?= htmlspecialchars($ujian["durasi"]) ?> menit

            </div>

            <a
                class="mulai"
                href="ujian.php?id=<?= $ujian["id"] ?>"
            >
                Mulai Ujian
            </a>

        </div>

    <?php endwhile; ?>

<?php else: ?>

    <p>
        Belum ada ujian yang tersedia untuk kelas Anda.
    </p>

<?php endif; ?>

</div>


<a class="logout"
   href="logout.php">

Keluar

</a>

</div>

</body>

</html>