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
| VARIABEL
|--------------------------------------------------------------------------
*/

$preview = [];

$hasil_import = [];

$error = "";

$pesan = "";

$nama_file = "";

$total_data = 0;

$jumlah_berhasil = 0;

$jumlah_gagal = 0;


/*
|--------------------------------------------------------------------------
| AMBIL PREVIEW DARI SESSION
|--------------------------------------------------------------------------
*/

if (isset($_SESSION["import_preview"])) {

    $preview =
        $_SESSION["import_preview"];

    $nama_file =
        $_SESSION["import_nama_file"] ?? "";

}


/*
|--------------------------------------------------------------------------
| PROSES FORM
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /*
    |--------------------------------------------------------------------------
    | TOMBOL BACA CSV
    |--------------------------------------------------------------------------
    */

    if (
        isset($_POST["aksi"]) &&
        $_POST["aksi"] === "preview"
    ) {

        $preview = [];

        $error = "";

        $nama_file = "";


        if (
            !isset($_FILES["file_csv"]) ||
            $_FILES["file_csv"]["error"] !== UPLOAD_ERR_OK
        ) {

            $error =
                "Silakan pilih file CSV terlebih dahulu.";

        } else {

            $nama_file =
                $_FILES["file_csv"]["name"];

            $tmp_file =
                $_FILES["file_csv"]["tmp_name"];

            $ukuran_file =
                $_FILES["file_csv"]["size"];


            /*
            |--------------------------------------------------------------------------
            | CEK EKSTENSI
            |--------------------------------------------------------------------------
            */

            $ekstensi =
                strtolower(
                    pathinfo(
                        $nama_file,
                        PATHINFO_EXTENSION
                    )
                );


            if ($ekstensi !== "csv") {

                $error =
                    "File harus berformat CSV.";

            } elseif ($ukuran_file > 5 * 1024 * 1024) {

                $error =
                    "Ukuran file maksimal 5 MB.";

            } else {


                /*
                |--------------------------------------------------------------------------
                | BUKA FILE
                |--------------------------------------------------------------------------
                */

                $handle =
                    fopen(
                        $tmp_file,
                        "r"
                    );


                if ($handle === false) {

                    $error =
                        "File CSV tidak dapat dibaca.";

                } else {


                    /*
                    |--------------------------------------------------------------------------
                    | BACA BARIS PERTAMA
                    |--------------------------------------------------------------------------
                    */

                    $baris_pertama =
                        fgets($handle);


                    if ($baris_pertama === false) {

                        $error =
                            "File CSV kosong.";

                    } else {


                        /*
                        |--------------------------------------------------------------------------
                        | HAPUS BOM UTF-8
                        |--------------------------------------------------------------------------
                        */

                        $baris_pertama =
                            preg_replace(
                                '/^\xEF\xBB\xBF/',
                                '',
                                $baris_pertama
                            );


                        /*
                        |--------------------------------------------------------------------------
                        | TENTUKAN PEMISAH
                        |--------------------------------------------------------------------------
                        */

                        $jumlah_koma =
                            substr_count(
                                $baris_pertama,
                                ","
                            );

                        $jumlah_titik_koma =
                            substr_count(
                                $baris_pertama,
                                ";"
                            );


                        if (
                            $jumlah_titik_koma >
                            $jumlah_koma
                        ) {

                            $pemisah = ";";

                        } else {

                            $pemisah = ",";

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | PARSE HEADER
                        |--------------------------------------------------------------------------
                        */

                        $header =
                            str_getcsv(
                                trim($baris_pertama),
                                $pemisah
                            );


                        /*
                        |--------------------------------------------------------------------------
                        | NORMALISASI HEADER
                        |--------------------------------------------------------------------------
                        */

                        $header =
                            array_map(
                                function ($item) {

                                    return strtolower(
                                        trim(
                                            preg_replace(
                                                '/^\xEF\xBB\xBF/',
                                                '',
                                                $item
                                            )
                                        )
                                    );

                                },
                                $header
                            );


                        /*
                        |--------------------------------------------------------------------------
                        | HEADER WAJIB
                        |--------------------------------------------------------------------------
                        */

                        $header_wajib = [

                            "username",

                            "nama",

                            "kelas",

                            "password",

                            "status"

                        ];


                        $header_lengkap =
                            true;


                        foreach (
                            $header_wajib
                            as $kolom
                        ) {

                            if (
                                !in_array(
                                    $kolom,
                                    $header
                                )
                            ) {

                                $header_lengkap =
                                    false;

                                break;

                            }

                        }


                        if (!$header_lengkap) {

                            $error =
                                "Header CSV harus berisi: "
                                . "username,nama,kelas,password,status";

                        } else {


                            /*
                            |--------------------------------------------------------------------------
                            | BACA DATA
                            |--------------------------------------------------------------------------
                            */

                            while (
                                (
                                    $data =
                                    fgetcsv(
                                        $handle,
                                        0,
                                        $pemisah
                                    )
                                ) !== false
                            ) {


                                /*
                                |--------------------------------------------------------------------------
                                | LEWATI BARIS KOSONG
                                |--------------------------------------------------------------------------
                                */

                                if (
                                    count(
                                        array_filter(
                                            $data,
                                            function ($value) {

                                                return trim(
                                                    $value
                                                ) !== "";

                                            }
                                        )
                                    ) === 0
                                ) {

                                    continue;

                                }


                                /*
                                |--------------------------------------------------------------------------
                                | BENTUK DATA
                                |--------------------------------------------------------------------------
                                */

                                $baris = [];


                                foreach (
                                    $header
                                    as $index => $kolom
                                ) {

                                    $baris[$kolom] =
                                        trim(
                                            $data[$index]
                                            ?? ""
                                        );

                                }


                                $preview[] =
                                    $baris;

                            }

                        }

                    }


                    fclose($handle);

                }

            }

        }


        /*
        |--------------------------------------------------------------------------
        | SIMPAN PREVIEW KE SESSION
        |--------------------------------------------------------------------------
        */

        if (
            $error === "" &&
            count($preview) > 0
        ) {

            $_SESSION["import_preview"] =
                $preview;

            $_SESSION["import_nama_file"] =
                $nama_file;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | TOMBOL IMPORT DATA
    |--------------------------------------------------------------------------
    */

    if (
        isset($_POST["aksi"]) &&
        $_POST["aksi"] === "import"
    ) {


        /*
        |--------------------------------------------------------------------------
        | AMBIL DATA PREVIEW
        |--------------------------------------------------------------------------
        */

        $data_import =
            $_SESSION["import_preview"]
            ?? [];


        $nama_file =
            $_SESSION["import_nama_file"]
            ?? "";


        if (count($data_import) === 0) {

            $error =
                "Tidak ada data yang dapat diimport. "
                . "Silakan baca file CSV terlebih dahulu.";

        } else {


            /*
            |--------------------------------------------------------------------------
            | PROSES DATA SATU PER SATU
            |--------------------------------------------------------------------------
            */

            $username_dalam_file = [];


            foreach (
                $data_import
                as $index => $row
            ) {

                $nomor =
                    $index + 1;


                $username =
                    trim(
                        $row["username"]
                        ?? ""
                    );

                $nama =
                    trim(
                        $row["nama"]
                        ?? ""
                    );

                $kelas =
                    trim(
                        $row["kelas"]
                        ?? ""
                    );

                $password =
                    trim(
                        $row["password"]
                        ?? ""
                    );

                $status =
                    trim(
                        $row["status"]
                        ?? ""
                    );


                /*
                |--------------------------------------------------------------------------
                | VALIDASI DATA KOSONG
                |--------------------------------------------------------------------------
                */

                $kolom_kosong = [];


                if ($username === "") {

                    $kolom_kosong[] =
                        "username";

                }

                if ($nama === "") {

                    $kolom_kosong[] =
                        "nama";

                }

                if ($kelas === "") {

                    $kolom_kosong[] =
                        "kelas";

                }

                if ($password === "") {

                    $kolom_kosong[] =
                        "password";

                }

                if ($status === "") {

                    $kolom_kosong[] =
                        "status";

                }


                if (count($kolom_kosong) > 0) {

                    $jumlah_gagal++;


                    $hasil_import[] = [

                        "nomor" =>
                            $nomor,

                        "username" =>
                            $username,

                        "nama" =>
                            $nama,

                        "kelas" =>
                            $kelas,

                        "alasan" =>
                            "Data belum lengkap: "
                            . implode(
                                ", ",
                                $kolom_kosong
                            )

                    ];


                    continue;

                }


                /*
                |--------------------------------------------------------------------------
                | CEK DUPLIKAT DALAM FILE CSV
                |--------------------------------------------------------------------------
                */

                if (
                    isset(
                        $username_dalam_file[
                            strtolower($username)
                        ]
                    )
                ) {

                    $jumlah_gagal++;


                    $hasil_import[] = [

                        "nomor" =>
                            $nomor,

                        "username" =>
                            $username,

                        "nama" =>
                            $nama,

                        "kelas" =>
                            $kelas,

                        "alasan" =>
                            "Username duplikat di dalam file CSV"

                    ];


                    continue;

                }


                $username_dalam_file[
                    strtolower($username)
                ] = true;


                /*
                |--------------------------------------------------------------------------
                | CEK USERNAME DI DATABASE
                |--------------------------------------------------------------------------
                */

                $stmt_cek =
                    $conn->prepare(
                        "SELECT id
                         FROM siswa
                         WHERE username = ?
                         LIMIT 1"
                    );


                if (!$stmt_cek) {

                    $jumlah_gagal++;


                    $hasil_import[] = [

                        "nomor" =>
                            $nomor,

                        "username" =>
                            $username,

                        "nama" =>
                            $nama,

                        "kelas" =>
                            $kelas,

                        "alasan" =>
                            "Kesalahan database saat memeriksa username"

                    ];


                    continue;

                }


                $stmt_cek->bind_param(
                    "s",
                    $username
                );

                $stmt_cek->execute();

                $hasil_cek =
                    $stmt_cek->get_result();


                $sudah_ada =
                    $hasil_cek->num_rows > 0;


                $stmt_cek->close();


                if ($sudah_ada) {

                    $jumlah_gagal++;


                    $hasil_import[] = [

                        "nomor" =>
                            $nomor,

                        "username" =>
                            $username,

                        "nama" =>
                            $nama,

                        "kelas" =>
                            $kelas,

                        "alasan" =>
                            "Username sudah terdaftar"

                    ];


                    continue;

                }


                /*
                |--------------------------------------------------------------------------
                | INSERT DATA
                |--------------------------------------------------------------------------
                */

                $stmt_insert =
                    $conn->prepare(
                        "INSERT INTO siswa
                        (
                            username,
                            nama,
                            kelas,
                            password,
                            status,
                            created_at
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            NOW()
                        )"
                    );


                if (!$stmt_insert) {

                    $jumlah_gagal++;


                    $hasil_import[] = [

                        "nomor" =>
                            $nomor,

                        "username" =>
                            $username,

                        "nama" =>
                            $nama,

                        "kelas" =>
                            $kelas,

                        "alasan" =>
                            "Gagal menyiapkan data ke database"

                    ];


                    continue;

                }


                $stmt_insert->bind_param(
                    "sssss",
                    $username,
                    $nama,
                    $kelas,
                    $password,
                    $status
                );


                if (
                    $stmt_insert->execute()
                ) {

                    $jumlah_berhasil++;

                } else {

                    $jumlah_gagal++;


                    $hasil_import[] = [

                        "nomor" =>
                            $nomor,

                        "username" =>
                            $username,

                        "nama" =>
                            $nama,

                        "kelas" =>
                            $kelas,

                        "alasan" =>
                            "Gagal menyimpan ke database"

                    ];

                }


                $stmt_insert->close();

            }


            /*
            |--------------------------------------------------------------------------
            | TOTAL DATA
            |--------------------------------------------------------------------------
            */

            $total_data =
                count($data_import);


            /*
            |--------------------------------------------------------------------------
            | HAPUS PREVIEW DARI SESSION
            |--------------------------------------------------------------------------
            */

            unset(
                $_SESSION["import_preview"]
            );

            unset(
                $_SESSION["import_nama_file"]
            );


            /*
            |--------------------------------------------------------------------------
            | PESAN HASIL
            |--------------------------------------------------------------------------
            */

            $pesan =
                "Proses import selesai.";

        }

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

<title>
Import Siswa - PIRI CBT
</title>


<style>

body {

    margin: 0;

    font-family: Arial, sans-serif;

    background: #f2f5f9;

}


.header {

    background: white;

    padding: 18px;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,.08);

}


.container {

    max-width: 1100px;

    margin: auto;

    padding: 20px;

}


.card {

    background: white;

    padding: 25px;

    border-radius: 12px;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.06);

    margin-bottom: 20px;

}


input[type="file"] {

    width: 100%;

    box-sizing: border-box;

    padding: 12px;

    border:
        1px solid #ccc;

    border-radius: 8px;

}


.btn {

    display: inline-block;

    padding: 11px 18px;

    border: none;

    border-radius: 8px;

    cursor: pointer;

    text-decoration: none;

    font-size: 15px;

    margin-top: 10px;

}


.upload {

    background: #0d6efd;

    color: white;

}


.import {

    background: #198754;

    color: white;

}


.kembali {

    background: #6c757d;

    color: white;

}


.error {

    padding: 12px;

    margin-bottom: 15px;

    background: #f8d7da;

    color: #842029;

    border-radius: 8px;

}


.success {

    padding: 12px;

    margin-bottom: 15px;

    background: #d1e7dd;

    color: #0f5132;

    border-radius: 8px;

}


.warning {

    padding: 12px;

    margin-bottom: 15px;

    background: #fff3cd;

    color: #664d03;

    border-radius: 8px;

}


.info {

    background: #cff4fc;

    color: #055160;

    padding: 12px;

    border-radius: 8px;

    margin-bottom: 15px;

    line-height: 1.6;

}


.ringkasan {

    display: flex;

    gap: 15px;

    flex-wrap: wrap;

    margin: 20px 0;

}


.box {

    flex: 1;

    min-width: 180px;

    padding: 20px;

    border-radius: 10px;

    background: #f1f3f5;

}


.box strong {

    display: block;

    font-size: 28px;

    margin-top: 5px;

}


.box.berhasil {

    background: #d1e7dd;

    color: #0f5132;

}


.box.gagal {

    background: #f8d7da;

    color: #842029;

}


table {

    width: 100%;

    border-collapse: collapse;

    margin-top: 20px;

}


th,
td {

    padding: 10px;

    border-bottom:
        1px solid #ddd;

    text-align: left;

}


th {

    background: #f1f3f5;

}


.tabel-gagal th {

    background: #f8d7da;

}


@media (max-width: 700px) {

    .card {

        overflow-x: auto;

    }


    table {

        min-width: 750px;

    }

}

</style>

</head>


<body>


<div class="header">

<strong>
PIRI CBT — ADMIN
</strong>

</div>


<div class="container">


<div class="card">

<h2>
Import Siswa
</h2>


<p>

Selamat datang,
<strong>
<?= htmlspecialchars(
    $_SESSION["admin_nama"]
) ?>
</strong>

</p>


<div class="info">

<strong>Format CSV:</strong>

<br>

Kolom:

<code>
username,nama,kelas,password,status
</code>

<br><br>

CSV dari Excel dengan pemisah
<strong>koma (,)</strong>
atau
<strong>titik koma (;)</strong>
dapat digunakan.

<br><br>

Contoh:

<br>

<code>
24001,Ahmad Fauzan,VIII A,123456,aktif
</code>

</div>


<?php if ($error !== ""): ?>

<div class="error">

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>


<?php if ($pesan !== ""): ?>

<div class="success">

<?= htmlspecialchars($pesan) ?>

</div>

<?php endif; ?>


<?php if ($total_data > 0): ?>

<div class="ringkasan">

<div class="box">

Total Data

<strong>
<?= $total_data ?>
</strong>

</div>


<div class="box berhasil">

Berhasil

<strong>
<?= $jumlah_berhasil ?>
</strong>

</div>


<div class="box gagal">

Gagal

<strong>
<?= $jumlah_gagal ?>
</strong>

</div>

</div>


<?php if ($jumlah_gagal > 0): ?>

<div class="warning">

<strong>
Perhatian:
</strong>

Ada
<?= $jumlah_gagal ?>
data yang tidak berhasil diimport.

Silakan periksa tabel data gagal di bawah ini,
perbaiki data tersebut di Excel,
kemudian import ulang.

</div>


<div class="card">

<h3>
Data yang Gagal Diimport
</h3>


<table class="tabel-gagal">

<thead>

<tr>

<th>No Baris</th>

<th>Username</th>

<th>Nama</th>

<th>Kelas</th>

<th>Alasan</th>

</tr>

</thead>


<tbody>


<?php foreach (
    $hasil_import
    as $gagal
): ?>


<tr>

<td>
<?= htmlspecialchars(
    $gagal["nomor"]
) ?>
</td>


<td>
<?= htmlspecialchars(
    $gagal["username"]
) ?>
</td>


<td>
<?= htmlspecialchars(
    $gagal["nama"]
) ?>
</td>


<td>
<?= htmlspecialchars(
    $gagal["kelas"]
) ?>
</td>


<td>
<?= htmlspecialchars(
    $gagal["alasan"]
) ?>
</td>

</tr>


<?php endforeach; ?>


</tbody>

</table>

</div>

<?php else: ?>

<div class="success">

<strong>
Semua data berhasil diimport.
</strong>

</div>

<?php endif; ?>


<a
    href="siswa.php"
    class="btn kembali"
>
Kembali ke Data Siswa
</a>


<a
    href="import-siswa.php"
    class="btn upload"
>
Import File Lain
</a>


<?php else: ?>


<form
    method="POST"
    enctype="multipart/form-data"
>

<input
    type="hidden"
    name="aksi"
    value="preview"
>


<input
    type="file"
    name="file_csv"
    accept=".csv"
    required
>


<br>


<button
    type="submit"
    class="btn upload"
>
Baca File CSV
</button>


<a
    href="siswa.php"
    class="btn kembali"
>
Kembali
</a>


</form>


<?php endif; ?>


</div>


<?php if (
    count($preview) > 0 &&
    $total_data === 0
): ?>


<div class="card">

<h3>
Preview Data
</h3>


<p>

File:
<strong>
<?= htmlspecialchars($nama_file) ?>
</strong>

</p>


<p>

Jumlah baris:
<strong>
<?= count($preview) ?>
</strong>

</p>


<table>

<thead>

<tr>

<th>No</th>

<th>Username</th>

<th>Nama</th>

<th>Kelas</th>

<th>Password</th>

<th>Status</th>

</tr>

</thead>


<tbody>


<?php

$no = 1;

foreach (
    $preview
    as $row
):

?>


<tr>

<td>
<?= $no++ ?>
</td>


<td>
<?= htmlspecialchars(
    $row["username"]
) ?>
</td>


<td>
<?= htmlspecialchars(
    $row["nama"]
) ?>
</td>


<td>
<?= htmlspecialchars(
    $row["kelas"]
) ?>
</td>


<td>
<?= htmlspecialchars(
    $row["password"]
) ?>
</td>


<td>
<?= htmlspecialchars(
    $row["status"]
) ?>
</td>

</tr>


<?php endforeach; ?>


</tbody>

</table>


<form
    method="POST"
    onsubmit="
        return confirm(
            'Yakin ingin mengimport data siswa ke database?'
        );
    "
>

<input
    type="hidden"
    name="aksi"
    value="import"
>


<button
    type="submit"
    class="btn import"
>
Import Data ke Database
</button>


<a
    href="siswa.php"
    class="btn kembali"
>
Batal
</a>


</form>


</div>


<?php endif; ?>


</div>

</body>

</html>