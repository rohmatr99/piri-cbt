<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

$admin_id = (int)($_SESSION["admin_id"] ?? 0);
$admin_role = $_SESSION["admin_role"] ?? "admin";


$ujian_id = (int)($_POST["ujian_id"] ?? 0);
$nomor = (int)($_POST["nomor"] ?? 0);
$pertanyaan = trim($_POST["pertanyaan"] ?? "");
$bobot = (int)($_POST["bobot"] ?? 1);

$kunci = $_POST["kunci"] ?? "";

$opsi_A = trim($_POST["opsi_A"] ?? "");
$opsi_B = trim($_POST["opsi_B"] ?? "");
$opsi_C = trim($_POST["opsi_C"] ?? "");
$opsi_D = trim($_POST["opsi_D"] ?? "");


if (
    $ujian_id <= 0 ||
    $nomor <= 0 ||
    $pertanyaan === "" ||
    $bobot <= 0 ||
    $opsi_A === "" ||
    $opsi_B === "" ||
    $opsi_C === "" ||
    $opsi_D === "" ||
    !in_array($kunci, ["A", "B", "C", "D"])
) {
    die("Data soal belum lengkap.");
}

/* Pastikan ujian boleh dikelola admin */
if ($admin_role !== "superadmin") {
    $stmtAkses = $conn->prepare("
        SELECT u.id
        FROM ujian u
        INNER JOIN admin_mapel am
            ON am.mapel_id = u.mapel_id
           AND am.admin_id = ?
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmtAkses->bind_param("ii", $admin_id, $ujian_id);
    $stmtAkses->execute();

    if (!$stmtAkses->get_result()->fetch_assoc()) {
        die("Anda tidak memiliki akses ke ujian tersebut.");
    }
}


/*
|--------------------------------------------------------------------------
| CEK NOMOR SOAL
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT id
    FROM soal
    WHERE ujian_id = ?
      AND nomor = ?
    LIMIT 1
");

$stmt->bind_param(
    "ii",
    $ujian_id,
    $nomor
);

$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {

    die("Nomor soal tersebut sudah digunakan.");

}


/*
|--------------------------------------------------------------------------
| MULAI TRANSAKSI
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {


    /*
    |--------------------------------------------------------------------------
    | SIMPAN SOAL
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        INSERT INTO soal
        (
            ujian_id,
            nomor,
            pertanyaan,
            tipe,
            bobot
        )
        VALUES (?, ?, ?, 'pilihan_ganda', ?)
    ");

    $stmt->bind_param(
        "iisi",
        $ujian_id,
        $nomor,
        $pertanyaan,
        $bobot
    );

    if (!$stmt->execute()) {
        throw new Exception(
            "Gagal menyimpan soal: "
            . $stmt->error
        );
    }


    $soal_id = $conn->insert_id;


    /*
    |--------------------------------------------------------------------------
    | SIMPAN OPSI
    |--------------------------------------------------------------------------
    */

    $daftar_opsi = [
        "A" => $opsi_A,
        "B" => $opsi_B,
        "C" => $opsi_C,
        "D" => $opsi_D
    ];


    $stmt = $conn->prepare("
        INSERT INTO opsi_soal
        (
            soal_id,
            kode,
            teks,
            benar
        )
        VALUES (?, ?, ?, ?)
    ");


    foreach ($daftar_opsi as $kode => $teks) {

        $benar = ($kode === $kunci) ? 1 : 0;

        $stmt->bind_param(
            "issi",
            $soal_id,
            $kode,
            $teks,
            $benar
        );

        if (!$stmt->execute()) {

            throw new Exception(
                "Gagal menyimpan opsi: "
                . $stmt->error
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | SIMPAN SEMUA
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    header(
        "Location: soal.php?ujian_id="
        . $ujian_id
    );

    exit;


} catch (Exception $e) {


    /*
    |--------------------------------------------------------------------------
    | BATALKAN JIKA GAGAL
    |--------------------------------------------------------------------------
    */

    $conn->rollback();

    die(
        "Gagal menyimpan soal: "
        . htmlspecialchars($e->getMessage())
    );

}

?>