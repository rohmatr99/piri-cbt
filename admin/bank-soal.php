<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}


/* =========================
   AMBIL FILTER
========================= */

$filter_ujian = isset($_GET["ujian_id"])
    ? (int)$_GET["ujian_id"]
    : 0;

$filter_mapel = isset($_GET["mapel_id"])
    ? (int)$_GET["mapel_id"]
    : 0;

$filter_kelas = isset($_GET["kelas"])
    ? trim($_GET["kelas"])
    : "";


/* =========================
   DATA UNTUK FILTER UJIAN
========================= */

$resultUjian = $conn->query("
    SELECT
        id,
        nama_ujian,
        kelas
    FROM ujian
    ORDER BY nama_ujian ASC
");

if (!$resultUjian) {
    die("Gagal mengambil data ujian: " . $conn->error);
}


/* =========================
   DATA UNTUK FILTER MAPEL
========================= */

$resultMapel = $conn->query("
    SELECT
        id,
        nama
    FROM mata_pelajaran
    ORDER BY nama ASC
");

if (!$resultMapel) {
    die("Gagal mengambil data mata pelajaran: " . $conn->error);
}


/* =========================
   DATA KELAS
========================= */

$resultKelas = $conn->query("
    SELECT DISTINCT kelas
    FROM ujian
    WHERE kelas IS NOT NULL
    AND kelas <> ''
    ORDER BY kelas ASC
");

if (!$resultKelas) {
    die("Gagal mengambil data kelas: " . $conn->error);
}


/* =========================
   QUERY BANK SOAL
========================= */

$sql = "
    SELECT
        s.id,
        s.ujian_id,
        s.nomor,
        s.pertanyaan,
        s.tipe,
        s.bobot,
        u.nama_ujian,
        u.kelas,
        m.nama AS nama_mapel
    FROM soal s

    LEFT JOIN ujian u
        ON s.ujian_id = u.id

    LEFT JOIN mata_pelajaran m
        ON u.mapel_id = m.id

    WHERE 1=1
";


/* =========================
   FILTER UJIAN
========================= */

if ($filter_ujian > 0) {

    $sql .= "
        AND s.ujian_id = " .
        $filter_ujian;
}


/* =========================
   FILTER MAPEL
========================= */

if ($filter_mapel > 0) {

    $sql .= "
        AND u.mapel_id = " .
        $filter_mapel;
}


/* =========================
   FILTER KELAS
========================= */

if ($filter_kelas !== "") {

    $kelas_aman =
        $conn->real_escape_string(
            $filter_kelas
        );

    $sql .= "
        AND u.kelas = '" .
        $kelas_aman .
        "'";
}


/* =========================
   URUTKAN
========================= */

$sql .= "
    ORDER BY s.id DESC
";


$result = $conn->query($sql);

if (!$result) {
    die("Query gagal: " . $conn->error);
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

<title>Bank Soal - PIRI CBT</title>

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
    max-width: 1100px;
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

.tombol {
    display: inline-block;
    padding: 11px 16px;
    border-radius: 8px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    font-size: 15px;
}

.kembali {
    background: #6b7280;
    color: white;
}

.salin-banyak {
    background: #16a34a;
    color: white;
}

.salin-banyak:disabled {
    background: #9ca3af;
    cursor: not-allowed;
}

.filter-grid {
    display: grid;
    grid-template-columns:
        repeat(3, 1fr);
    gap: 15px;
}

.filter-item label {
    display: block;
    font-weight: bold;
    margin-bottom: 7px;
}

.filter-item select {
    width: 100%;
    padding: 11px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: white;
    font-size: 15px;
}

.btn-filter {
    background: #2563eb;
    color: white;
}

.btn-reset {
    background: #6b7280;
    color: white;
}

.tabel-container {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}

th,
td {
    padding: 12px;
    border-bottom: 1px solid #ddd;
    text-align: left;
    vertical-align: top;
}

th {
    background: #f1f3f5;
}

.pertanyaan {
    max-width: 450px;
    line-height: 1.5;
}

.checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.badge {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 6px;
    background: #e5e7eb;
    font-size: 13px;
}

.badge-mapel {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 6px;
    background: #dbeafe;
    color: #1e40af;
    font-size: 13px;
}

.aksi {
    white-space: nowrap;
}

.kelola {
    background: #2563eb;
    color: white;
}

.salin-satu {
    background: #16a34a;
    color: white;
}

.jumlah {
    margin-left: 10px;
    font-weight: bold;
}

.kosong {
    text-align: center;
    padding: 30px;
    color: #777;
}

@media (max-width: 700px) {

    .filter-grid {
        grid-template-columns: 1fr;
    }

}

</style>

</head>

<body>


<div class="header">
    PIRI CBT — ADMIN
</div>


<div class="container">


    <!-- =========================
         JUDUL
    ========================== -->

    <div class="card">

        <h2>📚 Bank Soal</h2>

        <p>
            Pilih satu atau beberapa soal untuk disalin
            ke ujian lain.
        </p>

        <a
            href="ujian.php"
            class="tombol kembali"
        >
            ← Daftar Ujian
        </a>

    </div>


    <!-- =========================
         FILTER
    ========================== -->

    <div class="card">

        <h3>
            🔎 Filter Bank Soal
        </h3>

        <form
            method="GET"
            action="bank-soal.php"
        >

            <div class="filter-grid">


                <!-- UJIAN -->

                <div class="filter-item">

                    <label>
                        Ujian
                    </label>

                    <select name="ujian_id">

                        <option value="0">
                            Semua Ujian
                        </option>

                        <?php while (
                            $u = $resultUjian->fetch_assoc()
                        ): ?>

                            <option
                                value="<?= (int)$u["id"] ?>"
                                <?= $filter_ujian === (int)$u["id"]
                                    ? "selected"
                                    : ""
                                ?>
                            >

                                <?= htmlspecialchars(
                                    $u["nama_ujian"]
                                ) ?>

                                —
                                <?= htmlspecialchars(
                                    $u["kelas"]
                                ) ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>


                <!-- MAPEL -->

                <div class="filter-item">

                    <label>
                        Mata Pelajaran
                    </label>

                    <select name="mapel_id">

                        <option value="0">
                            Semua Mata Pelajaran
                        </option>

                        <?php while (
                            $m = $resultMapel->fetch_assoc()
                        ): ?>

                            <option
                                value="<?= (int)$m["id"] ?>"
                                <?= $filter_mapel === (int)$m["id"]
                                    ? "selected"
                                    : ""
                                ?>
                            >

                                <?= htmlspecialchars(
                                    $m["nama"]
                                ) ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>


                <!-- KELAS -->

                <div class="filter-item">

                    <label>
                        Kelas
                    </label>

                    <select name="kelas">

                        <option value="">
                            Semua Kelas
                        </option>

                        <?php while (
                            $k = $resultKelas->fetch_assoc()
                        ): ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $k["kelas"]
                                ) ?>"
                                <?= $filter_kelas === $k["kelas"]
                                    ? "selected"
                                    : ""
                                ?>
                            >

                                <?= htmlspecialchars(
                                    $k["kelas"]
                                ) ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>

            </div>


            <br>


            <button
                type="submit"
                class="tombol btn-filter"
            >
                🔎 Tampilkan
            </button>


            <a
                href="bank-soal.php"
                class="tombol btn-reset"
            >
                ↻ Reset
            </a>

        </form>

    </div>


    <!-- =========================
         FORM PILIH SOAL
    ========================== -->

    <form
        action="salin-banyak-soal.php"
        method="GET"
        id="formBankSoal"
    >


        <div class="card">

            <button
                type="submit"
                class="tombol salin-banyak"
                id="btnSalin"
                disabled
            >
                📋 Salin Soal Terpilih
            </button>

            <span
                class="jumlah"
                id="jumlahDipilih"
            >
                0 soal dipilih
            </span>

        </div>


        <!-- =========================
             TABEL SOAL
        ========================== -->

        <div class="card">

            <div class="tabel-container">

                <table>

                    <thead>

                        <tr>

                            <th>

                                <input
                                    type="checkbox"
                                    id="pilihSemua"
                                    class="checkbox"
                                >

                            </th>

                            <th>No</th>

                            <th>Pertanyaan</th>

                            <th>Ujian Asal</th>

                            <th>Mapel</th>

                            <th>Kelas</th>

                            <th>Bobot</th>

                            <th>Aksi</th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if ($result->num_rows === 0): ?>

                        <tr>

                            <td
                                colspan="8"
                                class="kosong"
                            >

                                Tidak ada soal
                                yang sesuai dengan filter.

                            </td>

                        </tr>


                    <?php else: ?>


                        <?php while (
                            $row =
                            $result->fetch_assoc()
                        ): ?>

                            <tr>


                                <!-- CHECKBOX -->

                                <td>

                                    <input
                                        type="checkbox"
                                        name="soal_id[]"
                                        value="<?= (int)$row["id"] ?>"
                                        class="checkbox soal-checkbox"
                                    >

                                </td>


                                <!-- NOMOR -->

                                <td>

                                    <?= (int)$row["nomor"] ?>

                                </td>


                                <!-- PERTANYAAN -->

                                <td class="pertanyaan">

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $row["pertanyaan"]
                                        )
                                    ) ?>

                                </td>


                                <!-- UJIAN -->

                                <td>

                                    <?= htmlspecialchars(
                                        $row["nama_ujian"] ?? "-"
                                    ) ?>

                                </td>


                                <!-- MAPEL -->

                                <td>

                                    <span
                                        class="badge-mapel"
                                    >

                                        <?= htmlspecialchars(
                                            $row["nama_mapel"] ?? "-"
                                        ) ?>

                                    </span>

                                </td>


                                <!-- KELAS -->

                                <td>

                                    <span class="badge">

                                        <?= htmlspecialchars(
                                            $row["kelas"] ?? "-"
                                        ) ?>

                                    </span>

                                </td>


                                <!-- BOBOT -->

                                <td>

                                    <?= (int)$row["bobot"] ?>

                                </td>


                                <!-- AKSI -->

                                <td class="aksi">

                                    <a
                                        href="soal.php?ujian_id=<?= (int)$row["ujian_id"] ?>"
                                        class="tombol kelola"
                                    >
                                        Kelola
                                    </a>

                                    <a
                                        href="salin-soal.php?id=<?= (int)$row["id"] ?>"
                                        class="tombol salin-satu"
                                    >
                                        📋 Salin
                                    </a>

                                </td>

                            </tr>

                        <?php endwhile; ?>


                    <?php endif; ?>


                    </tbody>

                </table>

            </div>

        </div>


    </form>

</div>


<script>

const pilihSemua =
    document.getElementById("pilihSemua");

const checkboxes =
    document.querySelectorAll(
        ".soal-checkbox"
    );

const btnSalin =
    document.getElementById("btnSalin");

const jumlahDipilih =
    document.getElementById(
        "jumlahDipilih"
    );


function updateJumlah() {

    const dipilih =
        document.querySelectorAll(
            ".soal-checkbox:checked"
        );

    const jumlah =
        dipilih.length;


    jumlahDipilih.textContent =
        jumlah + " soal dipilih";


    btnSalin.disabled =
        jumlah === 0;

}


/* =========================
   PILIH SEMUA
========================= */

pilihSemua.addEventListener(
    "change",
    function() {

        checkboxes.forEach(
            function(checkbox) {

                checkbox.checked =
                    pilihSemua.checked;

            }
        );

        updateJumlah();

    }
);


/* =========================
   PILIH INDIVIDU
========================= */

checkboxes.forEach(
    function(checkbox) {

        checkbox.addEventListener(
            "change",
            function() {

                const jumlahTerpilih =
                    document.querySelectorAll(
                        ".soal-checkbox:checked"
                    ).length;


                pilihSemua.checked =
                    checkboxes.length > 0 &&
                    jumlahTerpilih ===
                    checkboxes.length;


                updateJumlah();

            }
        );

    }
);

</script>


</body>

</html>