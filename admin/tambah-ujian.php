<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}


/* Ambil mata pelajaran */
$resultMapel = $conn->query("
    SELECT id, nama
    FROM mata_pelajaran
    ORDER BY nama ASC
");

if (!$resultMapel) {
    die("Gagal mengambil mata pelajaran: " . $conn->error);
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Tambah Ujian - PIRI CBT</title>

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
    max-width: 700px;
    margin: 25px auto;
    padding: 0 15px;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,.08);
}

h2 {
    margin-top: 0;
}

label {
    display: block;
    margin-top: 15px;
    margin-bottom: 7px;
    font-weight: bold;
}

input,
select {
    width: 100%;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 16px;
}

.tombol {
    display: inline-block;
    padding: 11px 16px;
    margin-top: 20px;
    border: none;
    border-radius: 8px;
    text-decoration: none;
    cursor: pointer;
    font-size: 15px;
}

.simpan {
    background: #16a34a;
    color: white;
}

.kembali {
    background: #6b7280;
    color: white;
}

.info {
    margin-top: 6px;
    font-size: 13px;
    color: #6b7280;
}

</style>

</head>

<body>

<div class="header">
    PIRI CBT — ADMIN
</div>


<div class="container">

<div class="card">

    <h2>➕ Tambah Ujian</h2>

    <form
        action="simpan-ujian.php"
        method="POST"
    >

        <label>
            Nama Ujian
        </label>

        <input
            type="text"
            name="nama_ujian"
            placeholder="Contoh: PAI VIII Semester 1"
            required
        >


        <label>
            Mata Pelajaran
        </label>

        <select
            name="mapel_id"
            required
        >

            <option value="">
                -- Pilih Mata Pelajaran --
            </option>

            <?php while ($mapel = $resultMapel->fetch_assoc()): ?>

                <option
                    value="<?= (int)$mapel["id"] ?>"
                >
                    <?= htmlspecialchars($mapel["nama"]) ?>
                </option>

            <?php endwhile; ?>

        </select>


        <label>
            Kelas
        </label>

        <input
            type="text"
            name="kelas"
            placeholder="Contoh: VIII A"
            required
        >


        <label>
            Durasi (menit)
        </label>

        <input
            type="number"
            name="durasi"
            min="1"
            value="60"
            required
        >


        <label>
            Minimal Waktu Mengerjakan (menit)
        </label>

        <input
            type="number"
            name="minimal_menit"
            min="0"
            value="0"
            required
        >

        <div class="info">
            Isi 0 jika siswa boleh mengirim ujian kapan saja.
        </div>


        <label>
            Maksimal Pelanggaran
        </label>

        <input
            type="number"
            name="maks_pelanggaran"
            min="0"
            value="0"
            required
        >

        <div class="info">
            Isi 0 jika tidak ada batas pelanggaran. Jika mencapai batas, ujian akan otomatis dikirim.
        </div>


        <label>
            Token
        </label>

        <input
            type="text"
            name="token"
            placeholder="Contoh: PAI123"
            required
        >


        <label>
            Tanggal Mulai
        </label>

        <input
            type="datetime-local"
            name="tanggal_mulai"
            required
        >


        <label>
            Tanggal Selesai
        </label>

        <input
            type="datetime-local"
            name="tanggal_selesai"
            required
        >


        <label>
            Status
        </label>

        <select
            name="status"
            required
        >

            <option value="aktif">
                Aktif
            </option>

            <option value="nonaktif">
                Nonaktif
            </option>

        </select>


        <button
            type="submit"
            class="tombol simpan"
        >
            💾 Simpan Ujian
        </button>


        <a
            href="ujian.php"
            class="tombol kembali"
        >
            ← Kembali
        </a>

    </form>

</div>

</div>

</body>

</html>