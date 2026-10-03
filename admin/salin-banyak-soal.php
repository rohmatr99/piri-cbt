<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}


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

foreach ($soal_ids as $id) {

    $stmt = $conn->prepare("
        SELECT
            s.id,
            s.ujian_id,
            s.nomor,
            s.pertanyaan,
            s.tipe,
            s.bobot,
            u.nama_ujian,
            u.kelas
        FROM soal s
        LEFT JOIN ujian u
            ON s.ujian_id = u.id
        WHERE s.id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();

    $row = $result->fetch_assoc();

    if ($row) {
        $soal[] = $row;
    }
}


if (count($soal) === 0) {
    die("Soal tidak ditemukan.");
}


/* =========================
   AMBIL DAFTAR UJIAN
========================= */

$resultUjian = $conn->query("
    SELECT
        id,
        nama_ujian,
        kelas
    FROM ujian
    ORDER BY id DESC
");

if (!$resultUjian) {
    die("Gagal mengambil daftar ujian: " . $conn->error);
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

            Pilih ujian tujuan untuk menyalin
            semua soal tersebut.

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

    </div>

</div>

</body>

</html>