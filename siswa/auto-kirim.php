<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["siswa_id"])) {
    header("Location: login.php");
    exit;
}

$siswa_id = $_SESSION["siswa_id"];

/*
|--------------------------------------------------------------------------
| Cari sesi ujian yang masih dikerjakan
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT *
    FROM sesi_ujian
    WHERE siswa_id = ?
      AND status = 'mengerjakan'
    ORDER BY id DESC
    LIMIT 1
");

$stmt->bind_param("i", $siswa_id);
$stmt->execute();

$sesi = $stmt->get_result()->fetch_assoc();

if (!$sesi) {
    die("Sesi ujian tidak ditemukan.");
}

$sesi_id = (int) $sesi["id"];
$ujian_id = (int) $sesi["ujian_id"];


/*
|--------------------------------------------------------------------------
| Pastikan waktu memang sudah habis
|--------------------------------------------------------------------------
*/

$batas_waktu = strtotime($sesi["batas_waktu"]);

if (time() < $batas_waktu) {
    die("Waktu ujian belum habis.");
}


/*
|--------------------------------------------------------------------------
| Hitung jumlah soal
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM soal
    WHERE ujian_id = ?
");

$stmt->bind_param("i", $ujian_id);
$stmt->execute();

$data = $stmt->get_result()->fetch_assoc();

$jumlah_soal = (int) $data["total"];


/*
|--------------------------------------------------------------------------
| Hitung jumlah soal yang dijawab
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM jawaban
    WHERE sesi_id = ?
");

$stmt->bind_param("i", $sesi_id);
$stmt->execute();

$data = $stmt->get_result()->fetch_assoc();

$jumlah_dijawab = (int) $data["total"];


/*
|--------------------------------------------------------------------------
| Hitung jumlah jawaban benar
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM jawaban j
    INNER JOIN opsi_soal o
        ON j.opsi_id = o.id
    WHERE j.sesi_id = ?
      AND o.benar = 1
");

$stmt->bind_param("i", $sesi_id);
$stmt->execute();

$data = $stmt->get_result()->fetch_assoc();

$jumlah_benar = (int) $data["total"];


/*
|--------------------------------------------------------------------------
| Hitung jumlah salah
|--------------------------------------------------------------------------
*/

$jumlah_salah = $jumlah_dijawab - $jumlah_benar;


/*
|--------------------------------------------------------------------------
| Hitung nilai
|--------------------------------------------------------------------------
*/

$nilai = 0;

if ($jumlah_soal > 0) {
    $nilai = ($jumlah_benar / $jumlah_soal) * 100;
}


/*
|--------------------------------------------------------------------------
| Cek apakah hasil sudah pernah dibuat
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT id
    FROM hasil_ujian
    WHERE sesi_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $sesi_id);
$stmt->execute();

$hasil_lama = $stmt->get_result()->fetch_assoc();


/*
|--------------------------------------------------------------------------
| Jika belum ada hasil, simpan hasil
|--------------------------------------------------------------------------
*/

if (!$hasil_lama) {

    $stmt = $conn->prepare("
        INSERT INTO hasil_ujian
        (
            sesi_id,
            siswa_id,
            ujian_id,
            jumlah_soal,
            jumlah_dijawab,
            jumlah_benar,
            jumlah_salah,
            nilai
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "iiiiiiid",
        $sesi_id,
        $siswa_id,
        $ujian_id,
        $jumlah_soal,
        $jumlah_dijawab,
        $jumlah_benar,
        $jumlah_salah,
        $nilai
    );

    if (!$stmt->execute()) {
        die("Gagal menyimpan hasil ujian: " . $stmt->error);
    }

    $hasil_id = $conn->insert_id;

} else {

    $hasil_id = (int) $hasil_lama["id"];

}


/*
|--------------------------------------------------------------------------
| Ubah status sesi menjadi habis
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    UPDATE sesi_ujian
    SET
        status = 'habis',
        selesai = NOW()
    WHERE id = ?
      AND siswa_id = ?
");

$stmt->bind_param(
    "ii",
    $sesi_id,
    $siswa_id
);

$stmt->execute();


/*
|--------------------------------------------------------------------------
| Simpan ID hasil ke session
|--------------------------------------------------------------------------
*/

$_SESSION["hasil_ujian_id"] = $hasil_id;


/*
|--------------------------------------------------------------------------
| Tampilkan halaman hasil
|--------------------------------------------------------------------------
*/

header("Location: hasil.php?id=" . $hasil_id);
exit;

?>