<?php

date_default_timezone_set("Asia/Jakarta");

session_start();

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| CEK LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["siswa_id"])) {
    header("Location: login.php");
    exit;
}

$siswa_id = (int) $_SESSION["siswa_id"];


/*
|--------------------------------------------------------------------------
| AMBIL SESI UJIAN AKTIF TERBARU
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        su.*,
        u.minimal_menit
    FROM sesi_ujian su
    JOIN ujian u
        ON su.ujian_id = u.id
    WHERE su.siswa_id = ?
      AND su.status = 'mengerjakan'
    ORDER BY su.id DESC
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
$minimal_menit = (int) $sesi["minimal_menit"];


/*
|--------------------------------------------------------------------------
| CEK WAKTU SERVER
|--------------------------------------------------------------------------
*/

$sekarang = time();
$mulai = strtotime($sesi["mulai"]);
$batas = strtotime($sesi["batas_waktu"]);

if ($mulai === false || $batas === false) {
    die("Waktu sesi ujian tidak valid.");
}


/*
|--------------------------------------------------------------------------
| ATURAN:
|
| 1. Jika waktu ujian SUDAH HABIS:
|    - langsung proses hasil
|    - jangan menolak pengiriman
|
| 2. Jika waktu ujian BELUM HABIS:
|    - cek minimal waktu
|    - jika belum tercapai, tolak
|    - jika sudah tercapai, lanjutkan
|--------------------------------------------------------------------------
*/

$waktuSudahHabis = ($sekarang >= $batas);

if (!$waktuSudahHabis) {

    $waktuMinimalSelesai =
        $mulai + ($minimal_menit * 60);

    if ($sekarang < $waktuMinimalSelesai) {

        $sisaDetik =
            $waktuMinimalSelesai - $sekarang;

        $sisaMenit =
            floor($sisaDetik / 60);

        $sisaDetikTampil =
            $sisaDetik % 60;

        die(
            "Ujian belum dapat dikirim.<br><br>" .
            "Minimal waktu mengerjakan adalah " .
            $minimal_menit .
            " menit.<br><br>" .
            "Silakan tunggu " .
            $sisaMenit .
            " menit " .
            $sisaDetikTampil .
            " detik lagi."
        );
    }
}


/*
|--------------------------------------------------------------------------
| HITUNG JUMLAH SOAL
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
| HITUNG JUMLAH SOAL YANG DIJAWAB
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
| HITUNG JAWABAN BENAR
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM jawaban j
    JOIN opsi_soal o
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
| HITUNG JAWABAN SALAH
|--------------------------------------------------------------------------
*/

$jumlah_salah =
    $jumlah_dijawab - $jumlah_benar;


/*
|--------------------------------------------------------------------------
| HITUNG NILAI
|--------------------------------------------------------------------------
*/

$nilai = 0;

if ($jumlah_soal > 0) {

    $nilai =
        ($jumlah_benar / $jumlah_soal) * 100;
}


/*
|--------------------------------------------------------------------------
| CEK APAKAH HASIL SUDAH ADA
|--------------------------------------------------------------------------
|
| Ini penting supaya hasil tidak dibuat dua kali apabila:
| - siswa klik kirim lebih dari sekali
| - timer dan tombol kirim terjadi hampir bersamaan
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

$hasil_lama =
    $stmt->get_result()->fetch_assoc();


if ($hasil_lama) {

    $hasil_id =
        (int) $hasil_lama["id"];

} else {

    /*
    |--------------------------------------------------------------------------
    | SIMPAN HASIL UJIAN
    |--------------------------------------------------------------------------
    */

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

        die(
            "Gagal menyimpan hasil ujian: " .
            $stmt->error
        );
    }

    $hasil_id =
        (int) $conn->insert_id;
}


/*
|--------------------------------------------------------------------------
| TENTUKAN STATUS SESI
|--------------------------------------------------------------------------
|
| Jika waktu habis:
|   status = habis
|
| Jika dikirim sebelum waktu habis:
|   status = selesai
|--------------------------------------------------------------------------
*/

$status_akhir =
    $waktuSudahHabis
        ? "habis"
        : "selesai";


/*
|--------------------------------------------------------------------------
| TANDAI SESI SELESAI
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    UPDATE sesi_ujian
    SET
        status = ?,
        selesai = NOW()
    WHERE id = ?
      AND siswa_id = ?
      AND status = 'mengerjakan'
");

$stmt->bind_param(
    "sii",
    $status_akhir,
    $sesi_id,
    $siswa_id
);

$stmt->execute();


/*
|--------------------------------------------------------------------------
| SIMPAN ID HASIL KE SESSION
|--------------------------------------------------------------------------
*/

$_SESSION["hasil_ujian_id"] =
    $hasil_id;


/*
|--------------------------------------------------------------------------
| ARAHKAN KE HALAMAN HASIL
|--------------------------------------------------------------------------
*/

header(
    "Location: hasil.php?id=" .
    $hasil_id
);

exit;

?>
