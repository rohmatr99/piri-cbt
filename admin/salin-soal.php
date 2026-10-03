<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

$soal_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($soal_id <= 0) {
    die("ID soal tidak valid.");
}


/* =========================
   AMBIL SOAL
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
        u.kelas
    FROM soal s
    LEFT JOIN ujian u ON s.ujian_id = u.id
    WHERE s.id = ?
");

$stmt->bind_param("i", $soal_id);
$stmt->execute();

$result = $stmt->get_result();
$soal = $result->fetch_assoc();

if (!$soal) {
    die("Soal tidak ditemukan.");
}


/* =========================
   AMBIL OPSI
========================= */

$stmt = $conn->prepare("
    SELECT kode, teks, benar
    FROM opsi_soal
    WHERE soal_id = ?
    ORDER BY kode ASC
");

$stmt->bind_param("i", $soal_id);
$stmt->execute();

$resultOpsi = $stmt->get_result();

$opsi = [];

while ($row = $resultOpsi->fetch_assoc()) {
    $opsi[] = $row;
}


/* =========================
   AMBIL UJIAN LAIN
========================= */

$stmt = $conn->prepare("
    SELECT id, nama_ujian, kelas
    FROM ujian
    WHERE id <> ?
    ORDER BY id DESC
");

$stmt->bind_param("i", $soal["ujian_id"]);
$stmt->execute();

$ujianResult = $stmt->get_result();

$daftarUjian = [];

while ($row = $ujianResult->fetch_assoc()) {
    $daftarUjian[] = $row;
}


/* =========================
   NOMOR OTOMATIS
========================= */

$nextNomor = 1;

if (count($daftarUjian) > 0) {

    $ujianPertama = (int)$daftarUjian[0]["id"];

    $stmt = $conn->prepare("
        SELECT COALESCE(MAX(nomor), 0) + 1 AS nomor_baru
        FROM soal
        WHERE ujian_id = ?
    ");

    $stmt->bind_param("i", $ujianPertama);
    $stmt->execute();

    $resultNext = $stmt->get_result();
    $rowNext = $resultNext->fetch_assoc();

    if ($rowNext) {
        $nextNomor = (int)$rowNext["nomor_baru"];
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

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

            <?= htmlspecialchars($soal["nama_ujian"] ?? "-") ?>

            <br>

            <strong>Kelas:</strong>

            <?= htmlspecialchars($soal["kelas"] ?? "-") ?>

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

                <strong>Belum ada ujian lain.</strong>

                <br><br>

                Soal ini berasal dari satu-satunya ujian
                yang tersedia.

                <br>

                Silakan buat ujian baru terlebih dahulu
                sebelum menyalin soal.

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

                Silakan pilih ujian yang akan menerima
                salinan soal.

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