<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

$admin_id = (int)($_SESSION["admin_id"] ?? 0);
$admin_role = $_SESSION["admin_role"] ?? "admin";

$ujian_id = isset($_GET["ujian_id"])
    ? (int) $_GET["ujian_id"]
    : 0;

if ($ujian_id <= 0) {
    die("Ujian tidak valid.");
}


/*
|--------------------------------------------------------------------------
| DATA UJIAN
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        u.id,
        u.nama_ujian,
        u.kelas,
        m.nama AS nama_mapel
    FROM ujian u
    LEFT JOIN mata_pelajaran m
        ON u.mapel_id = m.id
    WHERE u.id = ?
");

$stmt->bind_param("i", $ujian_id);
$stmt->execute();

$ujian = $stmt->get_result()->fetch_assoc();

if (!$ujian) {
    die("Ujian tidak ditemukan.");
}

if ($admin_role !== "superadmin") {
    $stmt = $conn->prepare("
        SELECT u.id
        FROM ujian u
        INNER JOIN admin_mapel am
            ON am.mapel_id = u.mapel_id
           AND am.admin_id = ?
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $admin_id, $ujian_id);
    $stmt->execute();

    if (!$stmt->get_result()->fetch_assoc()) {
        die("Anda tidak memiliki akses ke ujian tersebut.");
    }
}


/*
|--------------------------------------------------------------------------
| NOMOR SOAL BERIKUTNYA
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT MAX(nomor) AS nomor
    FROM soal
    WHERE ujian_id = ?
");

$stmt->bind_param("i", $ujian_id);
$stmt->execute();

$data = $stmt->get_result()->fetch_assoc();

$nomor = ((int)($data["nomor"] ?? 0)) + 1;

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Tambah Soal</title>

<style>

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f2f5f9;
}

.container {
    max-width: 800px;
    margin: auto;
    padding: 20px;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,.08);
}

label {
    display: block;
    font-weight: bold;
    margin-top: 15px;
    margin-bottom: 6px;
}

input,
textarea,
select {
    width: 100%;
    padding: 11px;
    border: 1px solid #ccc;
    border-radius: 8px;
    font-family: Arial, sans-serif;
    box-sizing: border-box;
}

textarea {
    min-height: 120px;
    resize: vertical;
}

.opsi {
    display: grid;
    grid-template-columns: 60px 1fr;
    gap: 10px;
    align-items: center;
}

.opsi label {
    margin: 0;
}

.radio {
    width: auto;
}

.tombol {
    display: inline-block;
    padding: 12px 18px;
    margin-top: 20px;
    border: 0;
    border-radius: 8px;
    color: white;
    text-decoration: none;
    cursor: pointer;
    font-weight: bold;
}

.simpan {
    background: #198754;
}

.kembali {
    background: #6c757d;
}

</style>

</head>

<body>

<div class="container">

<div class="card">

<h2>
Tambah Soal
</h2>

<p>

<strong>Ujian:</strong>
<?= htmlspecialchars($ujian["nama_ujian"]) ?>

<br>

<strong>Mapel:</strong>
<?= htmlspecialchars($ujian["nama_mapel"] ?? "-") ?>

<br>

<strong>Kelas:</strong>
<?= htmlspecialchars($ujian["kelas"]) ?>

</p>


<form
    method="POST"
    action="simpan-soal.php"
>


<input
    type="hidden"
    name="ujian_id"
    value="<?= $ujian_id ?>"
>


<label>
Nomor Soal
</label>

<input
    type="number"
    name="nomor"
    value="<?= $nomor ?>"
    min="1"
    required
>


<label>
Pertanyaan
</label>

<textarea
    name="pertanyaan"
    placeholder="Tuliskan pertanyaan..."
    required
></textarea>


<label>
Bobot Nilai
</label>

<input
    type="number"
    name="bobot"
    value="1"
    min="1"
    required
>


<label>
Pilihan A
</label>

<div class="opsi">

    <input
        type="radio"
        name="kunci"
        value="A"
        class="radio"
        required
    >

    <input
        type="text"
        name="opsi_A"
        placeholder="Jawaban A"
        required
    >

</div>


<label>
Pilihan B
</label>

<div class="opsi">

    <input
        type="radio"
        name="kunci"
        value="B"
    >

    <input
        type="text"
        name="opsi_B"
        placeholder="Jawaban B"
        required
    >

</div>


<label>
Pilihan C
</label>

<div class="opsi">

    <input
        type="radio"
        name="kunci"
        value="C"
    >

    <input
        type="text"
        name="opsi_C"
        placeholder="Jawaban C"
        required
    >

</div>


<label>
Pilihan D
</label>

<div class="opsi">

    <input
        type="radio"
        name="kunci"
        value="D"
    >

    <input
        type="text"
        name="opsi_D"
        placeholder="Jawaban D"
        required
    >

</div>


<button
    type="submit"
    class="tombol simpan"
>
    Simpan Soal
</button>


<a
    href="soal.php?ujian_id=<?= $ujian_id ?>"
    class="tombol kembali"
>
    Batal
</a>


</form>

</div>

</div>

</body>

</html>