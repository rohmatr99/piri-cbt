<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

$admin_id = (int)($_SESSION["admin_id"] ?? 0);
$admin_role = $_SESSION["admin_role"] ?? "admin";


/* =========================
   AMBIL DATA FORM
========================= */

$soal_ids = $_POST["soal_ids"] ?? [];

$ujian_tujuan_id = isset($_POST["ujian_tujuan_id"])
    ? (int)$_POST["ujian_tujuan_id"]
    : 0;


/* =========================
   VALIDASI ID SOAL
========================= */

if (!is_array($soal_ids)) {
    $soal_ids = [$soal_ids];
}

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
    die("Tidak ada soal yang dipilih.");
}


if ($ujian_tujuan_id <= 0) {
    die("Ujian tujuan belum dipilih.");
}


/* =========================
   CEK UJIAN TUJUAN
   DAN AMBIL MAPEL TUJUAN
========================= */

if ($admin_role === "superadmin") {

    $stmt = $conn->prepare("
        SELECT
            u.id,
            u.nama_ujian,
            u.kelas,
            u.mapel_id,
            m.nama AS nama_mapel
        FROM ujian u
        LEFT JOIN mata_pelajaran m
            ON m.id = u.mapel_id
        WHERE u.id = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "i",
        $ujian_tujuan_id
    );

} else {

    $stmt = $conn->prepare("
        SELECT
            u.id,
            u.nama_ujian,
            u.kelas,
            u.mapel_id,
            m.nama AS nama_mapel
        FROM ujian u
        INNER JOIN admin_mapel am
            ON am.mapel_id = u.mapel_id
           AND am.admin_id = ?
        LEFT JOIN mata_pelajaran m
            ON m.id = u.mapel_id
        WHERE u.id = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "ii",
        $admin_id,
        $ujian_tujuan_id
    );
}


$stmt->execute();

$result = $stmt->get_result();

$ujianTujuan = $result->fetch_assoc();

$stmt->close();


if (!$ujianTujuan) {
    die("Ujian tujuan tidak ditemukan atau Anda tidak memiliki akses.");
}


$mapel_tujuan_id = (int)$ujianTujuan["mapel_id"];


/* =========================
   MULAI TRANSAKSI
========================= */

$conn->begin_transaction();


try {

    /* =========================
       NOMOR SOAL BERIKUTNYA
    ========================= */

    $stmtNomor = $conn->prepare("
        SELECT COALESCE(MAX(nomor), 0)
        FROM soal
        WHERE ujian_id = ?
    ");

    $stmtNomor->bind_param(
        "i",
        $ujian_tujuan_id
    );

    $stmtNomor->execute();

    $resultNomor = $stmtNomor->get_result();

    $rowNomor = $resultNomor->fetch_row();

    $nomorBerikutnya = ((int)$rowNomor[0]) + 1;

    $stmtNomor->close();


    /* =========================
       QUERY SOAL ASAL
       SEKALIGUS CEK MAPEL
    ========================= */

    if ($admin_role === "superadmin") {

        $stmtSoal = $conn->prepare("
            SELECT
                s.id,
                s.pertanyaan,
                s.tipe,
                s.bobot,
                u.mapel_id
            FROM soal s
            INNER JOIN ujian u
                ON u.id = s.ujian_id
            WHERE s.id = ?
            LIMIT 1
        ");

    } else {

        $stmtSoal = $conn->prepare("
            SELECT
                s.id,
                s.pertanyaan,
                s.tipe,
                s.bobot,
                u.mapel_id
            FROM soal s
            INNER JOIN ujian u
                ON u.id = s.ujian_id
            INNER JOIN admin_mapel am
                ON am.mapel_id = u.mapel_id
               AND am.admin_id = ?
            WHERE s.id = ?
            LIMIT 1
        ");
    }


    /* =========================
       CEK DUPLIKAT
    ========================= */

    $stmtCekDuplikat = $conn->prepare("
        SELECT
            id,
            nomor
        FROM soal
        WHERE ujian_id = ?
        AND pertanyaan = ?
        LIMIT 1
    ");


    /* =========================
       INSERT SOAL
    ========================= */

    $stmtInsertSoal = $conn->prepare("
        INSERT INTO soal
        (
            ujian_id,
            nomor,
            pertanyaan,
            tipe,
            bobot
        )
        VALUES (?, ?, ?, ?, ?)
    ");


    /* =========================
       QUERY PILIHAN JAWABAN
    ========================= */

    $stmtOpsi = $conn->prepare("
        SELECT
            kode,
            teks,
            benar
        FROM opsi_soal
        WHERE soal_id = ?
        ORDER BY kode ASC
    ");


    /* =========================
       INSERT PILIHAN JAWABAN
    ========================= */

    $stmtInsertOpsi = $conn->prepare("
        INSERT INTO opsi_soal
        (
            soal_id,
            kode,
            teks,
            benar
        )
        VALUES (?, ?, ?, ?)
    ");


    /* =========================
       HITUNG HASIL
    ========================= */

    $jumlahBerhasil = 0;
    $jumlahDuplikat = 0;


    /* =========================
       PROSES SETIAP SOAL
    ========================= */

    foreach ($soal_ids as $soal_id) {


        /* =========================
           AMBIL SOAL ASAL
        ========================= */

        if ($admin_role === "superadmin") {

            $stmtSoal->bind_param(
                "i",
                $soal_id
            );

        } else {

            $stmtSoal->bind_param(
                "ii",
                $admin_id,
                $soal_id
            );
        }


        $stmtSoal->execute();

        $resultSoal = $stmtSoal->get_result();

        $soal = $resultSoal->fetch_assoc();


        if (!$soal) {
            throw new Exception(
                "Soal dengan ID $soal_id tidak ditemukan atau Anda tidak memiliki akses."
            );
        }


        /* =========================
           CEK MAPEL ASAL
        ========================= */

        $mapel_asal_id = (int)$soal["mapel_id"];


        if ($mapel_asal_id !== $mapel_tujuan_id) {

            throw new Exception(
                "Soal ID $soal_id berasal dari mata pelajaran yang berbeda dengan ujian tujuan."
            );
        }


        /* =========================
           CEK SOAL DUPLIKAT
        ========================= */

        $stmtCekDuplikat->bind_param(
            "is",
            $ujian_tujuan_id,
            $soal["pertanyaan"]
        );

        $stmtCekDuplikat->execute();

        $resultDuplikat =
            $stmtCekDuplikat->get_result();

        $duplikat =
            $resultDuplikat->fetch_assoc();


        if ($duplikat) {

            $jumlahDuplikat++;

            continue;
        }


        /* =========================
           INSERT SOAL BARU
        ========================= */

        $stmtInsertSoal->bind_param(
            "iissi",
            $ujian_tujuan_id,
            $nomorBerikutnya,
            $soal["pertanyaan"],
            $soal["tipe"],
            $soal["bobot"]
        );

        $stmtInsertSoal->execute();

        $soalBaruId =
            $conn->insert_id;


        /* =========================
           AMBIL PILIHAN JAWABAN
        ========================= */

        $stmtOpsi->bind_param(
            "i",
            $soal_id
        );

        $stmtOpsi->execute();

        $resultOpsi =
            $stmtOpsi->get_result();


        $jumlahOpsi = 0;


        /* =========================
           SALIN PILIHAN JAWABAN
        ========================= */

        while ($op = $resultOpsi->fetch_assoc()) {

            $kode = $op["kode"];
            $teks = $op["teks"];
            $benar = (int)$op["benar"];

            $stmtInsertOpsi->bind_param(
                "issi",
                $soalBaruId,
                $kode,
                $teks,
                $benar
            );

            $stmtInsertOpsi->execute();

            $jumlahOpsi++;
        }


        /* =========================
           PASTIKAN ADA OPSI
        ========================= */

        if ($jumlahOpsi === 0) {

            throw new Exception(
                "Pilihan jawaban untuk soal ID $soal_id tidak ditemukan."
            );
        }


        /* =========================
           NOMOR BERIKUTNYA
        ========================= */

        $nomorBerikutnya++;

        $jumlahBerhasil++;
    }


    /* =========================
       SIMPAN SEMUA
    ========================= */

    $conn->commit();


    /* =========================
       KEMBALI KE SOAL UJIAN
    ========================= */

    header(
        "Location: soal.php?ujian_id=" .
        $ujian_tujuan_id .
        "&salin_berhasil=" .
        $jumlahBerhasil .
        "&salin_duplikat=" .
        $jumlahDuplikat
    );

    exit;


} catch (Exception $e) {


    /* =========================
       BATALKAN TRANSAKSI
    ========================= */

    $conn->rollback();


    die(
        "Gagal menyalin soal: " .
        htmlspecialchars(
            $e->getMessage()
        )
    );
}