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

$soal_id = isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;


if ($soal_id <= 0) {
    die("ID soal tidak valid.");
}


/* =========================
   AMBIL SOAL + MAPEL ASAL
========================= */

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
    $soal_id
);

$stmt->execute();

$result = $stmt->get_result();

$soal = $result->fetch_assoc();

$stmt->close();


if (!$soal) {
    die("Soal tidak ditemukan.");
}


$mapel_asal_id = (int)$soal["mapel_id"];


/* =========================
   CEK AKSES SOAL ASAL
========================= */

if ($admin_role !== "superadmin") {

    $stmt = $conn->prepare("
        SELECT s.id
        FROM soal s
        INNER JOIN ujian u
            ON u.id = s.ujian_id
        INNER JOIN admin_mapel am
            ON am.mapel_id = u.mapel_id
           AND am.admin_id = ?
        WHERE s.id = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "ii",
        $admin_id,
        $soal_id
    );

    $stmt->execute();

    $aksesSoal = $stmt->get_result()->fetch_assoc();

    $stmt->close();


    if (!$aksesSoal) {
        die("Anda tidak memiliki akses ke soal tersebut.");
    }
}


/* =========================
   AMBIL OPSI
========================= */

$stmt = $conn->prepare("
    SELECT
        kode,
        teks,
        benar
    FROM opsi_soal
    WHERE soal_id = ?
    ORDER BY kode ASC
");

$stmt->bind_param(
    "i",
    $soal_id
);

$stmt->execute();

$resultOpsi = $stmt->get_result();

$opsi = [];

while ($row = $resultOpsi->fetch_assoc()) {
    $opsi[] = $row;
}

$stmt->close();


/* =========================
   AMBIL UJIAN LAIN
   HANYA MAPEL YANG SAMA
========================= */

if ($admin_role === "superadmin") {

    $stmt = $conn->prepare("
        SELECT
            u.id,
            u.nama_ujian,
            u.kelas,
            u.mapel_id
        FROM ujian u
        WHERE u.id <> ?
          AND u.mapel_id = ?
        ORDER BY u.id DESC
    ");

    $stmt->bind_param(
        "ii",
        $soal["ujian_id"],
        $mapel_asal_id
    );

} else {

    $stmt = $conn->prepare("
        SELECT
            u.id,
            u.nama_ujian,
            u.kelas,
            u.mapel_id
        FROM ujian u
        INNER JOIN admin_mapel am
            ON am.mapel_id = u.mapel_id
           AND am.admin_id = ?
        WHERE u.id <> ?
          AND u.mapel_id = ?
        ORDER BY u.id DESC
    ");

    $stmt->bind_param(
        "iii",
        $admin_id,
        $soal["ujian_id"],
        $mapel_asal_id
    );
}

$stmt->execute();

$ujianResult = $stmt->get_result();

$daftarUjian = [];

while ($row = $ujianResult->fetch_assoc()) {
    $daftarUjian[] = $row;
}

$stmt->close();


/* =========================
   NOMOR OTOMATIS
========================= */

$nextNomor = 1;

if (count($daftarUjian) > 0) {

    $ujianPertama = (int)$daftarUjian[0]["id"];

    $stmt = $conn->prepare("
        SELECT
            COALESCE(MAX(nomor), 0) + 1 AS nomor_baru
        FROM soal
        WHERE ujian_id = ?
    ");

    $stmt->bind_param(
        "i",
        $ujianPertama
    );

    $stmt->execute();

    $resultNext = $stmt->get_result();

    $rowNext = $resultNext->fetch_assoc();

    $stmt->close();


    if ($rowNext) {
        $nextNomor = (int)$rowNext["nomor_baru"];
    }
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

<title>Salin Soal</title>

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
    margin-bottom: 20px;
    line-height: 1.7;
}

.pertanyaan {
    font-size: 17px;
    line-height: 1.6;
    margin-bottom: 15px;
}

.opsi {
    padding: 10px;
    background: #f9fafb;
    border-radius: 6px;
    margin-bottom: 8px;
}

.benar {
    background: #dcfce7;
    border-left: 4px solid #16a34a;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 7px;
}

select,
input[type="number"] {
    width: 100%;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 16px;
    margin-bottom: 15px;
    background: white;
}

.tombol {
    display: inline-block;
    padding: 11px 16px;
    border-radius: 8px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    font-size: 15px;
}

.salin {
    background: #16a34a;
    color: white;
}

.salin:disabled {
    background: #9ca3af;
    cursor: not-allowed;
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

.berhasil {
    background: #dcfce7;
    border-left: 4px solid #16a34a;
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
         SOAL
    ========================== -->

    <div class="card">

        <h2>📋 Salin Soal ke Ujian</h2>

        <div class="info">

            <strong>Ujian asal:</strong><br>

            <?= htmlspecialchars(
                $soal["nama_ujian"] ?? "-"
            ) ?>

            <br>

            <strong>Mata Pelajaran:</strong>

            <?= htmlspecialchars(
                $soal["nama_mapel"] ?? "-"
            ) ?>

            <br>

            <strong>Kelas:</strong>

            <?= htmlspecialchars(
                $soal["kelas"] ?? "-"
            ) ?>

            <br>

            <strong>Nomor soal:</strong>

            <?= (int)$soal["nomor"] ?>

        </div>


        <h3>Soal</h3>

        <div class="pertanyaan">

            <?= nl2br(
                htmlspecialchars($soal["pertanyaan"])
            ) ?>

        </div>


        <?php foreach ($opsi as $op): ?>

            <div class="opsi <?= $op["benar"] ? "benar" : "" ?>">

                <strong>
                    <?= htmlspecialchars($op["kode"]) ?>.
                </strong>

                <?= htmlspecialchars($op["teks"]) ?>

                <?php if ($op["benar"]): ?>

                    <strong> ✓ Kunci</strong>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>

    </div>


    <!-- =========================
         TUJUAN SALIN
    ========================== -->

    <div class="card">

        <h3>🎯 Tujuan Salin</h3>


        <?php if (count($daftarUjian) === 0): ?>

            <div class="warning">

                <strong>
                    Belum ada ujian lain pada mata pelajaran
                    <?= htmlspecialchars(
                        $soal["nama_mapel"] ?? "-"
                    ) ?>.
                </strong>

                <br><br>

                Soal hanya dapat disalin ke ujian
                dengan mata pelajaran yang sama.

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
                ← Kembali
            </a>


        <?php else: ?>


            <div class="berhasil">

                Ujian tujuan tersedia.

                <br>

                <strong>
                    Hanya ujian dengan mata pelajaran
                    yang sama yang ditampilkan.
                </strong>

            </div>


            <form
                action="proses-salin-soal.php"
                method="POST"
            >

                <input
                    type="hidden"
                    name="soal_id"
                    value="<?= (int)$soal["id"] ?>"
                >


                <label>
                    Pilih Ujian Tujuan
                </label>


                <select
                    name="ujian_tujuan_id"
                    id="ujian_tujuan_id"
                    required
                    onchange="ubahNomor()"
                >

                    <option value="">
                        -- Pilih Ujian Tujuan --
                    </option>


                    <?php foreach ($daftarUjian as $ujian): ?>

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

                    <?php endforeach; ?>

                </select>


                <label>
                    Nomor Soal di Ujian Tujuan
                </label>


                <input
                    type="number"
                    name="nomor"
                    id="nomor"
                    min="1"
                    value="<?= $nextNomor ?>"
                    required
                >


                <button
                    type="submit"
                    class="tombol salin"
                    id="btnSalin"
                    disabled
                >
                    📋 Salin Soal
                </button>


                <a
                    href="bank-soal.php"
                    class="tombol kembali"
                >
                    ← Kembali
                </a>

            </form>

        <?php endif; ?>

    </div>

</div>


<script>

function ubahNomor() {

    const select =
        document.getElementById("ujian_tujuan_id");

    const tombol =
        document.getElementById("btnSalin");

    if (select.value !== "") {

        tombol.disabled = false;

    } else {

        tombol.disabled = true;

    }

}

</script>

</body>

</html>