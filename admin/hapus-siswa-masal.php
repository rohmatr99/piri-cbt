<?php

session_start();

require_once "../config/database.php";

/*
|--------------------------------------------------------------------------
| CEK LOGIN ADMIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| HANYA SUPERADMIN
|--------------------------------------------------------------------------
*/

if (($_SESSION["admin_role"] ?? "") !== "superadmin") {
    header("Location: dashboard.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| CEK DATA POST
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] !== "POST" ||
    !isset($_POST["siswa_id"]) ||
    !is_array($_POST["siswa_id"])
) {
    header("Location: siswa.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| BERSIHKAN ID SISWA
|--------------------------------------------------------------------------
*/

$siswa_ids = [];

foreach ($_POST["siswa_id"] as $id) {

    $id = (int) $id;

    if ($id > 0) {
        $siswa_ids[] = $id;
    }
}

$siswa_ids = array_values(
    array_unique($siswa_ids)
);

if (count($siswa_ids) === 0) {
    header("Location: siswa.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| MULAI TRANSAKSI
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    $berhasil = 0;

    foreach ($siswa_ids as $siswa_id) {

        /*
        |--------------------------------------------------------------------------
        | CEK DATA SISWA
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT id
            FROM siswa
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $siswa_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            continue;
        }

        $stmt->close();

        /*
        |--------------------------------------------------------------------------
        | AMBIL SEMUA SESI SISWA
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT id
            FROM sesi_ujian
            WHERE siswa_id = ?
        ");

        $stmt->bind_param("i", $siswa_id);
        $stmt->execute();

        $result_sesi = $stmt->get_result();

        $sesi_ids = [];

        while ($sesi = $result_sesi->fetch_assoc()) {
            $sesi_ids[] = (int) $sesi["id"];
        }

        $stmt->close();

        /*
        |--------------------------------------------------------------------------
        | HAPUS HASIL UJIAN
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            DELETE FROM hasil_ujian
            WHERE siswa_id = ?
        ");

        $stmt->bind_param("i", $siswa_id);
        $stmt->execute();
        $stmt->close();

        /*
        |--------------------------------------------------------------------------
        | HAPUS JAWABAN DAN PELANGGARAN
        | berdasarkan sesi ujian
        |--------------------------------------------------------------------------
        */

        if (count($sesi_ids) > 0) {

            foreach ($sesi_ids as $sesi_id) {

                /*
                | Hapus jawaban
                */

                $stmt = $conn->prepare("
                    DELETE FROM jawaban
                    WHERE sesi_id = ?
                ");

                $stmt->bind_param("i", $sesi_id);
                $stmt->execute();
                $stmt->close();

                /*
                | Hapus pelanggaran
                */

                $stmt = $conn->prepare("
                    DELETE FROM pelanggaran_ujian
                    WHERE sesi_id = ?
                ");

                $stmt->bind_param("i", $sesi_id);
                $stmt->execute();
                $stmt->close();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | HAPUS SESI UJIAN
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            DELETE FROM sesi_ujian
            WHERE siswa_id = ?
        ");

        $stmt->bind_param("i", $siswa_id);
        $stmt->execute();
        $stmt->close();

        /*
        |--------------------------------------------------------------------------
        | HAPUS DATA SISWA
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            DELETE FROM siswa
            WHERE id = ?
        ");

        $stmt->bind_param("i", $siswa_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $berhasil++;
        }

        $stmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | SIMPAN PERUBAHAN
    |--------------------------------------------------------------------------
    */

    $conn->commit();

    /*
    |--------------------------------------------------------------------------
    | PESAN HASIL
    |--------------------------------------------------------------------------
    */

    $_SESSION["pesan_siswa"] =
        $berhasil . " siswa berhasil dihapus.";

    $_SESSION["tipe_pesan_siswa"] =
        "success";

    header("Location: siswa.php");
    exit;

} catch (Exception $e) {

    /*
    |--------------------------------------------------------------------------
    | BATALKAN SEMUA PERUBAHAN
    |--------------------------------------------------------------------------
    */

    $conn->rollback();

    $_SESSION["pesan_siswa"] =
        "Penghapusan gagal: " . $e->getMessage();

    $_SESSION["tipe_pesan_siswa"] =
        "error";

    header("Location: siswa.php");
    exit;
}