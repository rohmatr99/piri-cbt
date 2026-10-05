<?php

session_start();

require_once "../config/database.php";


/* =========================
   CEK LOGIN
========================= */

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}


$admin_id = (int)($_SESSION["admin_id"] ?? 0);
$admin_role = $_SESSION["admin_role"] ?? "admin";


/* =========================
   AMBIL SOAL TERPILIH
========================= */

$soal_ids = $_GET["soal_id"] ?? [];

if (!is_array($soal_ids)) {
    $soal_ids = [$soal_ids];
}


/* Bersihkan ID */

$soal_ids = array_map("intval", $soal_ids);

$soal_ids = array_filter(
    $soal_ids,
    function ($id) {
        return $id > 0;
    }
);

$soal_ids = array_values(
    array_unique($soal_ids)
);


if (count($soal_ids) === 0) {
    die("Belum ada soal yang dipilih.");
}


/* =========================
   AMBIL DATA SOAL
========================= */

$soal = [];

$mapel_ids = [];


foreach ($soal_ids as $id) {

    if ($admin_role === "superadmin") {

        $stmt = $conn->prepare("
            SELECT
                s.id,
                s.ujian_id,
                s.nomor,
                s.pertanyaan,
                s.tipe,
                s.bobot,
                u.nama_ujian,
                u.kelas,
                u.mapel_id,
                m.nama AS nama_mapel
            FROM soal s
            INNER JOIN ujian u
                ON u.id = s.ujian_id
            LEFT JOIN mata_pelajaran m
                ON m.id = u.mapel_id
            WHERE s.id = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "i",
            $id
        );

    } else {

        $stmt = $conn->prepare("
            SELECT
                s.id,
                s.ujian_id,
                s.nomor,
                s.pertanyaan,
                s.tipe,
                s.bobot,
                u.nama_ujian,
                u.kelas,
                u.mapel_id,
                m.nama AS nama_mapel
            FROM soal s
            INNER JOIN ujian u
                ON u.id = s.ujian_id
            INNER JOIN admin_mapel am
                ON am.mapel_id = u.mapel_id
               AND am.admin_id = ?
            LEFT JOIN mata_pelajaran m
                ON m.id = u.mapel_id
            WHERE s.id = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "ii",
            $admin_id,
            $id
        );
    }


    $stmt->execute();

    $result = $stmt->get_result();

    $row = $result->fetch_assoc();

    $stmt->close();


    if (!$row) {
        die(
            "Soal ID " .
            $id .
            " tidak ditemukan atau Anda tidak memiliki akses."
        );
    }


    $soal[] = $row;

    $mapel_ids[] = (int)$row["mapel_id"];
}


/* =========================
   PASTIKAN SEMUA SOAL
   BERASAL DARI MAPEL SAMA
========================= */

$mapel_ids = array_values(
    array_unique($mapel_ids)
);


if (count($mapel_ids) !== 1) {

    die("
        <div style='
            font-family: Arial, sans-serif;
            max-width: 700px;
            margin: 50px auto;
            padding: 25px;
            border: 1px solid #fecaca;
            border-radius: 10px;
            background: #fff;
        '>

            <h2 style='color: #dc2626;'>
                Soal Berasal dari Mapel Berbeda
            </h2>

            <p>
                Salin banyak soal hanya dapat dilakukan
                jika semua soal yang dipilih berasal dari
                mata pelajaran yang sama.
            </p>

            <p>
                Silakan kembali ke Bank Soal dan pilih
                soal dari satu mata pelajaran saja.
            </p>

            <br>

            <a
                href='bank-soal.php'
                style='
                    display: inline-block;
                    padding: 10px 16px;
                    background: #2563eb;
                    color: white;
                    text-decoration: none;
                    border-radius: 6px;
                '
            >
                ← Kembali ke Bank Soal
            </a>

        </div>
    ");

}


$mapel_id = $mapel_ids[0];

$nama_mapel = $soal[0]["nama_mapel"] ?? "-";


/* =========================
   AMBIL DAFTAR UJIAN
   HANYA MAPEL YANG SAMA
========================= */

if ($admin_role === "superadmin") {

    $stmtUjian = $conn->prepare("
        SELECT
            u.id,
            u.nama_ujian,
            u.kelas,
            u.mapel_id
        FROM ujian u
        WHERE u.mapel_id = ?
        ORDER BY u.id DESC
    ");

    $stmtUjian->bind_param(
        "i",
        $mapel_id
    );

} else {

    $stmtUjian = $conn->prepare("
        SELECT
            u.id,
            u.nama_ujian,
            u.kelas,
            u.mapel_id
        FROM ujian u
        INNER JOIN admin_mapel am
            ON am.mapel_id = u.mapel_id
           AND am.admin_id = ?
        WHERE u.mapel_id = ?
        ORDER BY u.id DESC
    ");

    $stmtUjian->bind_param(
        "ii",
        $admin_id,
        $mapel_id
    );
}


$stmtUjian->execute();

$resultUjian = $stmtUjian->get_result();


if (!$resultUjian) {
    die("Gagal mengambil daftar ujian.");
}

?>

<!DOCTYPE html>

<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Salin Banyak Soal</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f3f4f6;
    color: #111827;
}

.header {
    background: #2563eb;
    color: white;
    padding: 18px;
    font-size: 20px;
    font-weight: bold;
}

.container {
    max-width: 900px;
    margin: 20px auto;
    padding: 0 15px;
}

.card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
}

.info {
    background: #eff6ff;
    border-left: 4px solid #2563eb;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 15px;
    line-height: 1.7;
}

.soal-item {
    padding: 12px;
    background: #f9fafb;
    border-radius: 8px;
    margin-bottom: 10px;
    line-height: 1.5;
}

.nomor {
    font-weight: bold;
    color: #2563eb;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 7px;
}

select {
    width: 100%;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 16px;
    background: white;
}

.tombol {
    display: inline-block;
    padding: 11px 16px;
    margin-top: 15px;
    border: none;
    border-radius: 8px;
    text-decoration: none;
    cursor: pointer;
    font-size: 15px;
}

.salin {
    background: #16a34a;
    color: white;
}

.kembali {
    background: #6b7280;
    color: white;
}

.warning {
    background: #fff7ed;
    border-left: 4px solid #f97316;
    padding: 12px;
    margin-bottom: 15px;
}

</style>

</head>

<body>


<div class="header">
    PIRI CBT — ADMIN
</div>


<div class="container">


    <!-- =========================
         INFORMASI
    ========================== -->

    <div class="card">

        <h2>📋 Salin Banyak Soal</h2>

        <div class="info">

            <strong>
                <?= count($soal) ?>
                soal dipilih
            </strong>

            <br>

            <strong>
                Mata Pelajaran:
            </strong>

            <?= htmlspecialchars($nama_mapel) ?>

            <br>

            Semua soal yang dipilih harus berasal
            dari mata pelajaran yang sama.

        </div>


        <!-- =========================
             DAFTAR SOAL
        ========================== -->

        <h3>Soal yang akan disalin</h3>


        <?php foreach ($soal as $item): ?>

            <div class="soal-item">

                <span class="nomor">
                    Soal <?= (int)$item["nomor"] ?>:
                </span>

                <?= htmlspecialchars(
                    $item["pertanyaan"]
                ) ?>

                <br>

                <small>

                    Ujian asal:
                    <?= htmlspecialchars(
                        $item["nama_ujian"] ?? "-"
                    ) ?>

                </small>

            </div>

        <?php endforeach; ?>

    </div>


    <!-- =========================
         FORM TUJUAN
    ========================== -->

    <div class="card">

        <h3>🎯 Ujian Tujuan</h3>


        <?php if ($resultUjian->num_rows === 0): ?>

            <div class="warning">

                <strong>
                    Belum ada ujian tujuan.
                </strong>

                <br><br>

                Tidak ada ujian lain pada mata pelajaran:

                <strong>
                    <?= htmlspecialchars($nama_mapel) ?>
                </strong>

            </div>


            <a
                href="ujian.php"
                class="tombol salin"
            >
                ➕ Buat Ujian Baru
            </a>

            <a
                href="bank-soal.php"
                class="tombol kembali"
            >
                ← Kembali ke Bank Soal
            </a>


        <?php else: ?>


            <form
                action="proses-salin-banyak-soal.php"
                method="POST"
            >


                <?php foreach ($soal_ids as $id): ?>

                    <input
                        type="hidden"
                        name="soal_ids[]"
                        value="<?= (int)$id ?>"
                    >

                <?php endforeach; ?>


                <label>
                    Pilih Ujian Tujuan
                </label>


                <select
                    name="ujian_tujuan_id"
                    required
                >

                    <option value="">
                        -- Pilih Ujian Tujuan --
                    </option>


                    <?php while ($ujian = $resultUjian->fetch_assoc()): ?>

                        <option
                            value="<?= (int)$ujian["id"] ?>"
                        >

                            <?= htmlspecialchars(
                                $ujian["nama_ujian"]
                            ) ?>

                            -
                            <?= htmlspecialchars(
                                $ujian["kelas"]
                            ) ?>

                        </option>

                    <?php endwhile; ?>

                </select>


                <button
                    type="submit"
                    class="tombol salin"
                >
                    📋 Salin Semua Soal
                </button>


                <a
                    href="bank-soal.php"
                    class="tombol kembali"
                >
                    ← Kembali ke Bank Soal
                </a>

            </form>

        <?php endif; ?>


    </div>

</div>

</body>

</html>