<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

$admin_id = (int)($_SESSION["admin_id"] ?? 0);
$admin_role = $_SESSION["admin_role"] ?? "admin";

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
if ($id <= 0) {
    die("ID ujian tidak valid.");
}

/* Ambil ujian sekaligus cek hak akses mapel. */
if ($admin_role === "superadmin") {
    $stmt = $conn->prepare("
        SELECT id, nama_ujian, mapel_id, kelas, durasi, minimal_menit,
               maks_pelanggaran, token, tanggal_mulai, tanggal_selesai, status
        FROM ujian
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $id);
} else {
    $stmt = $conn->prepare("
        SELECT u.id, u.nama_ujian, u.mapel_id, u.kelas, u.durasi,
               u.minimal_menit, u.maks_pelanggaran, u.token,
               u.tanggal_mulai, u.tanggal_selesai, u.status
        FROM ujian u
        INNER JOIN admin_mapel am
            ON am.mapel_id = u.mapel_id
           AND am.admin_id = ?
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $admin_id, $id);
}
$stmt->execute();
$ujian = $stmt->get_result()->fetch_assoc();

if (!$ujian) {
    http_response_code(403);
    die("Akses ditolak. Ujian ini bukan mata pelajaran yang ditugaskan kepada Anda.");
}

/* Admin biasa hanya boleh memilih mapel yang memang ditugaskan. */
if ($admin_role === "superadmin") {
    $mapel = $conn->query("
        SELECT id, nama, kode
        FROM mata_pelajaran
        WHERE status = 'aktif'
        ORDER BY nama ASC
    ");
} else {
    $stmtMapel = $conn->prepare("
        SELECT m.id, m.nama, m.kode
        FROM mata_pelajaran m
        INNER JOIN admin_mapel am
            ON am.mapel_id = m.id
           AND am.admin_id = ?
        WHERE m.status = 'aktif'
        ORDER BY m.nama ASC
    ");
    $stmtMapel->bind_param("i", $admin_id);
    $stmtMapel->execute();
    $mapel = $stmtMapel->get_result();
}

if (!$mapel) {
    die("Gagal mengambil data mata pelajaran.");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Ujian</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f4f6f8;
        }

        .container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        h2 {
            margin-top: 0;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input,
        select {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
        }

        .info {
            margin-top: 5px;
            font-size: 13px;
            color: #666;
        }

        .tombol {
            display: inline-block;
            padding: 11px 18px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .simpan {
            background: #198754;
            color: white;
        }

        .kembali {
            background: #6c757d;
            color: white;
            margin-left: 5px;
        }

        .tombol:hover {
            opacity: 0.9;
        }
    </style>
</head>

<body>

<div class="container">

    <h2>✏️ Edit Ujian</h2>

    <form action="update-ujian.php" method="POST">

        <input
            type="hidden"
            name="id"
            value="<?= (int)$ujian["id"] ?>"
        >

        <div class="form-group">
            <label>Nama Ujian</label>

            <input
                type="text"
                name="nama_ujian"
                value="<?= htmlspecialchars($ujian["nama_ujian"]) ?>"
                required
            >
        </div>

        <div class="form-group">
            <label>Mata Pelajaran</label>

            <select name="mapel_id" required>

                <option value="">
                    -- Pilih Mata Pelajaran --
                </option>

                <?php while ($row = $mapel->fetch_assoc()): ?>

                    <option
                        value="<?= (int)$row["id"] ?>"
                        <?= ((int)$row["id"] === (int)$ujian["mapel_id"]) ? "selected" : "" ?>
                    >
                        <?= htmlspecialchars($row["nama"]) ?>
                        (<?= htmlspecialchars($row["kode"]) ?>)
                    </option>

                <?php endwhile; ?>

            </select>
        </div>

        <div class="form-group">
            <label>Kelas</label>

            <input
                type="text"
                name="kelas"
                value="<?= htmlspecialchars($ujian["kelas"]) ?>"
                required
            >
        </div>

        <div class="form-group">
            <label>Durasi Ujian (menit)</label>

            <input
                type="number"
                name="durasi"
                min="1"
                value="<?= (int)$ujian["durasi"] ?>"
                required
            >
        </div>

        <div class="form-group">
            <label>Minimal Waktu Mengerjakan (menit)</label>

            <input
                type="number"
                name="minimal_menit"
                min="0"
                value="<?= (int)$ujian["minimal_menit"] ?>"
                required
            >

            <div class="info">
                Isi 0 jika siswa boleh mengirim ujian kapan saja.
                Nilai tidak boleh lebih besar dari durasi ujian.
            </div>
        </div>

        <div class="form-group">
            <label>Maksimal Pelanggaran</label>

            <input
                type="number"
                name="maks_pelanggaran"
                min="0"
                value="<?= (int)$ujian["maks_pelanggaran"] ?>"
                required
            >

            <div class="info">
                Isi 0 jika tidak ada batas pelanggaran. Jika mencapai batas, ujian akan otomatis dikirim.
            </div>
        </div>

        <div class="form-group">
            <label>Token</label>

            <input
                type="text"
                name="token"
                value="<?= htmlspecialchars($ujian["token"]) ?>"
                required
            >
        </div>

        <div class="form-group">
            <label>Tanggal & Jam Mulai</label>

            <input
                type="datetime-local"
                name="tanggal_mulai"
                value="<?= date(
                    'Y-m-d\TH:i',
                    strtotime($ujian["tanggal_mulai"])
                ) ?>"
                required
            >
        </div>

        <div class="form-group">
            <label>Tanggal & Jam Selesai</label>

            <input
                type="datetime-local"
                name="tanggal_selesai"
                value="<?= date(
                    'Y-m-d\TH:i',
                    strtotime($ujian["tanggal_selesai"])
                ) ?>"
                required
            >
        </div>

        <div class="form-group">
            <label>Status</label>

            <select name="status" required>

                <option
                    value="aktif"
                    <?= $ujian["status"] === "aktif" ? "selected" : "" ?>
                >
                    Aktif
                </option>

                <option
                    value="nonaktif"
                    <?= $ujian["status"] === "nonaktif" ? "selected" : "" ?>
                >
                    Nonaktif
                </option>

            </select>
        </div>

        <button
            type="submit"
            class="tombol simpan"
        >
            💾 Simpan Perubahan
        </button>

        <a
            href="ujian.php"
            class="tombol kembali"
        >
            ↩ Kembali
        </a>

    </form>

</div>

</body>
</html>