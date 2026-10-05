<?php

/* PIRI CBT - Supervisor Unlock v3: anti-race fullscreen handling */
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
| ID UJIAN
|--------------------------------------------------------------------------
*/

if (!isset($_GET["id"]) || (int) $_GET["id"] <= 0) {
    header("Location: dashboard.php");
    exit;
}

$ujian_id = (int) $_GET["id"];


/*
|--------------------------------------------------------------------------
| AMBIL DATA UJIAN
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
    u.id,
    u.nama_ujian,
    u.kelas,
    u.durasi,
    u.minimal_menit,
    u.maks_pelanggaran,
    u.status,
    m.nama AS nama_mapel
	
    FROM ujian u
    JOIN mata_pelajaran m
        ON u.mapel_id = m.id
    WHERE u.id = ?
      AND u.status = 'aktif'
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $ujian_id
);

$stmt->execute();

$ujian = $stmt
    ->get_result()
    ->fetch_assoc();


if (!$ujian) {

    die("Ujian tidak tersedia.");

}

/*
|--------------------------------------------------------------------------
| CEK KELAS SISWA
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["kelas"]) ||
    (string) $_SESSION["kelas"] !== (string) $ujian["kelas"]
) {
    die("Anda tidak memiliki akses ke ujian ini.");
}


/*
|--------------------------------------------------------------------------
| CEK SESI UJIAN
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT *
    FROM sesi_ujian
    WHERE siswa_id = ?
      AND ujian_id = ?
    ORDER BY id DESC
    LIMIT 1
");

$stmt->bind_param(
    "ii",
    $siswa_id,
    $ujian_id
);

$stmt->execute();

$sesi = $stmt
    ->get_result()
    ->fetch_assoc();


/*
|--------------------------------------------------------------------------
| BUAT SESI BARU JIKA BELUM ADA
|--------------------------------------------------------------------------
*/

if (!$sesi) {

    /*
    |--------------------------------------------------------------------------
    | TOKEN WAJIB UNTUK MEMBUAT SESI BARU
    |--------------------------------------------------------------------------
    |
    | Dashboard mengarahkan siswa ke token-ujian.php.
    | Setelah token benar, halaman tersebut menyimpan otorisasi sementara
    | di PHP session. Tanpa otorisasi ini, URL ujian.php tidak dapat
    | membuat sesi baru.
    |
    */

    $token_verified = false;

    if (
        isset($_SESSION["ujian_token_verified"][$ujian_id]) &&
        is_array($_SESSION["ujian_token_verified"][$ujian_id])
    ) {

        $token_data =
            $_SESSION["ujian_token_verified"][$ujian_id];

        if (
            isset($token_data["siswa_id"]) &&
            (int) $token_data["siswa_id"] === $siswa_id &&
            isset($token_data["verified_at"]) &&
            (time() - (int) $token_data["verified_at"]) <= 300
        ) {
            $token_verified = true;
        }
    }

    if (!$token_verified) {

        header(
            "Location: token-ujian.php?id=" .
            $ujian_id
        );
        exit;

    }

    /*
    | Token sudah digunakan untuk otorisasi pembuatan sesi.
    | Hapus segera agar otorisasi tersebut tidak dapat dipakai ulang.
    */
    unset(
        $_SESSION["ujian_token_verified"][$ujian_id]
    );

    $mulai = date("Y-m-d H:i:s");

    $batas_waktu = date(
        "Y-m-d H:i:s",
        time() + ($ujian["durasi"] * 60)
    );


    $stmt = $conn->prepare("
        INSERT INTO sesi_ujian
        (
            siswa_id,
            ujian_id,
            mulai,
            batas_waktu,
            status
        )
        VALUES (?, ?, ?, ?, 'mengerjakan')
    ");


    $stmt->bind_param(
        "iiss",
        $siswa_id,
        $ujian_id,
        $mulai,
        $batas_waktu
    );


    if (!$stmt->execute()) {

        /*
        | Jika UNIQUE(siswa_id, ujian_id) menolak INSERT karena
        | sesi sudah dibuat oleh request lain, ambil sesi tersebut.
        */
        $stmt_check = $conn->prepare("
            SELECT *
            FROM sesi_ujian
            WHERE siswa_id = ?
              AND ujian_id = ?
            LIMIT 1
        ");

        $stmt_check->bind_param(
            "ii",
            $siswa_id,
            $ujian_id
        );

        $stmt_check->execute();

        $sesi = $stmt_check
            ->get_result()
            ->fetch_assoc();

        $stmt_check->close();

        if (!$sesi) {

            die(
                "Gagal membuat sesi ujian: "
                . $stmt->error
            );

        }

    } else {

        $sesi_id = $conn->insert_id;

        /*
        |----------------------------------------------------------------------
        | Ambil kembali sesi
        |----------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT *
            FROM sesi_ujian
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "i",
            $sesi_id
        );

        $stmt->execute();

        $sesi = $stmt
            ->get_result()
            ->fetch_assoc();

    }

}


/*
|--------------------------------------------------------------------------
| CEK STATUS SESI
|--------------------------------------------------------------------------
*/

if ($sesi["status"] !== "mengerjakan") {

    die(
        "Ujian sudah selesai dan tidak dapat dikerjakan kembali."
    );

}


/*
|--------------------------------------------------------------------------
| HITUNG SISA WAKTU
|--------------------------------------------------------------------------
*/

$sisa_detik =
    strtotime($sesi["batas_waktu"])
    - time();


/*
|--------------------------------------------------------------------------
| JIKA WAKTU SUDAH HABIS
|--------------------------------------------------------------------------
|
| Kita arahkan langsung ke auto-kirim.
| Jangan hanya menampilkan "waktu habis".
|--------------------------------------------------------------------------
*/

if ($sisa_detik <= 0) {

    header("Location: auto-kirim.php");
    exit;

}


/*
|--------------------------------------------------------------------------
| AMBIL SEMUA SOAL
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT *
    FROM soal
    WHERE ujian_id = ?
    ORDER BY RAND(" . (int)$sesi["id"] . ")
");

$stmt->bind_param(
    "i",
    $ujian_id
);

$stmt->execute();

$soal_result = $stmt->get_result();


$soal = [];


while ($row = $soal_result->fetch_assoc()) {

    $soal[] = $row;

}


/*
|--------------------------------------------------------------------------
| AMBIL JAWABAN SISWA
|--------------------------------------------------------------------------
*/

$jawaban_siswa = [];


$stmt = $conn->prepare("
    SELECT
        soal_id,
        opsi_id
    FROM jawaban
    WHERE sesi_id = ?
");

$stmt->bind_param(
    "i",
    $sesi["id"]
);

$stmt->execute();


$result_jawaban =
    $stmt->get_result();


while (
    $row =
    $result_jawaban->fetch_assoc()
) {

    $jawaban_siswa[
        $row["soal_id"]
    ] = $row["opsi_id"];

}
$total_soal = count($soal);

$sudah_dijawab =
    count($jawaban_siswa);

$belum_dijawab =
    $total_soal - $sudah_dijawab;


/*
|--------------------------------------------------------------------------
| AMBIL JUMLAH PELANGGARAN YANG SUDAH TERSIMPAN
|--------------------------------------------------------------------------
|
| Nilai ini berasal dari database berdasarkan sesi ujian yang sama.
| Ketika siswa menutup lalu membuka kembali halaman, counter tidak
| kembali ke 0.
|
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM pelanggaran_ujian
    WHERE sesi_id = ?
");

$stmt->bind_param(
    "i",
    $sesi["id"]
);

$stmt->execute();

$data_pelanggaran =
    $stmt
        ->get_result()
        ->fetch_assoc();

$jumlah_pelanggaran =
    (int) ($data_pelanggaran["total"] ?? 0);

$ujian_terkunci_pengawas =
    (int) ($sesi["terkunci_pengawas"] ?? 0) === 1;


/*
|--------------------------------------------------------------------------
| AMBIL OPSI JAWABAN
|--------------------------------------------------------------------------
*/

$opsi = [];


/* 
|--------------------------------------------------------------------------
| AMBIL OPSI JAWABAN
|--------------------------------------------------------------------------
*/

$opsi = [];

if (count($soal) > 0) {

    $soal_ids = [];

    foreach ($soal as $s) {

        $soal_ids[] =
            (int)$s["id"];

    }


    $id_string =
        implode(",", $soal_ids);


    /*
    |--------------------------------------------------------------------------
    | AMBIL SEMUA OPSI
    |--------------------------------------------------------------------------
    */

    $query = "
        SELECT *
        FROM opsi_soal
        WHERE soal_id IN ($id_string)
        ORDER BY
            soal_id ASC,
            kode ASC
    ";


    $result_opsi =
        $conn->query($query);


    if (!$result_opsi) {

        die(
            "Gagal mengambil pilihan jawaban: " .
            $conn->error
        );

    }


    /*
    |--------------------------------------------------------------------------
    | MASUKKAN OPSI KE ARRAY BERDASARKAN SOAL
    |--------------------------------------------------------------------------
    */

    while (
        $row =
        $result_opsi->fetch_assoc()
    ) {

        $opsi[
            $row["soal_id"]
        ][] = $row;

    }


    /*
    |--------------------------------------------------------------------------
    | ACAK PILIHAN JAWABAN PER SOAL
    |--------------------------------------------------------------------------
    |
    | Menggunakan:
    | - ID sesi
    | - ID soal
    | - ID opsi
    |
    | sehingga urutan pilihan:
    | - berbeda antar sesi
    | - tetap sama ketika refresh
    | - tidak mengubah opsi_id
    | - tidak mengubah nilai benar
    |
    */

    foreach (
        $opsi
        as $soal_id => &$daftarOpsi
    ) {

        usort(
            $daftarOpsi,
            function (
                $a,
                $b
            ) use (
                $sesi,
                $soal_id
            ) {

                $nilaiA = crc32(
                    $sesi["id"] .
                    "-" .
                    $soal_id .
                    "-" .
                    $a["id"]
                );


                $nilaiB = crc32(
                    $sesi["id"] .
                    "-" .
                    $soal_id .
                    "-" .
                    $b["id"]
                );


                return $nilaiA <=> $nilaiB;

            }
        );

    }


    unset($daftarOpsi);

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
<?= htmlspecialchars($ujian["nama_ujian"]) ?>
</title>


<style>
:root{
    --primary:#2563eb;
    --primary-dark:#1d4ed8;
    --success:#16a34a;
    --success-soft:#ecfdf3;
    --danger:#dc2626;
    --danger-soft:#fef2f2;
    --warning:#d97706;
    --warning-soft:#fffbeb;
    --text:#172033;
    --muted:#64748b;
    --border:#e2e8f0;
    --surface:#ffffff;
    --bg:#f5f7fb;
    --shadow:0 8px 28px rgba(15,23,42,.07);
    --radius:16px;
}

*{box-sizing:border-box;}
html{scroll-behavior:smooth;}
body{
    margin:0;
    font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;
    color:var(--text);
    background:linear-gradient(180deg,#eef4ff 0,#f7f9fc 180px,var(--bg) 100%);
    line-height:1.55;
}
button,input{font:inherit;}
button{ -webkit-tap-highlight-color:transparent; }
.container{max-width:980px;margin:0 auto;padding:20px 18px 40px;}

.header{
    background:rgba(255,255,255,.96);
    padding:20px 22px;
    border:1px solid rgba(226,232,240,.9);
    border-radius:20px;
    margin-bottom:14px;
    position:sticky;
    top:10px;
    z-index:10;
    box-shadow:var(--shadow);
}
.header h2{margin:0 0 3px;font-size:clamp(19px,2.5vw,25px);line-height:1.25;letter-spacing:-.02em;}
.mapel{color:var(--muted);font-size:14px;font-weight:600;}

.timer{
    margin-top:14px;
    background:linear-gradient(135deg,#dc2626,#ef4444);
    color:#fff;
    padding:11px 14px;
    border-radius:12px;
    text-align:center;
    font-size:clamp(19px,3vw,23px);
    font-weight:800;
    letter-spacing:.04em;
    box-shadow:0 7px 18px rgba(220,38,38,.18);
}

.status{
    background:var(--surface);
    padding:11px 14px;
    border:1px solid var(--border);
    border-radius:12px;
    margin-bottom:12px;
    text-align:center;
    font-size:13px;
    color:var(--muted);
    box-shadow:0 3px 12px rgba(15,23,42,.035);
}
#status-pelanggaran{border-color:#fde68a!important;box-shadow:none;}

#daftar-soal{margin-top:12px;}
.soal{
    display:none;
    background:var(--surface);
    padding:26px;
    border:1px solid var(--border);
    border-radius:var(--radius);
    margin-bottom:14px;
    box-shadow:var(--shadow);
}
.soal.aktif{display:block;animation:soalMasuk .18s ease-out;}
@keyframes soalMasuk{from{opacity:.5;transform:translateY(5px)}to{opacity:1;transform:none}}
.nomor-soal{
    display:inline-flex;
    align-items:center;
    padding:6px 11px;
    margin-bottom:14px;
    border-radius:999px;
    background:#eff6ff;
    color:var(--primary-dark);
    font-size:13px;
    font-weight:800;
}
.pertanyaan{font-size:17px;line-height:1.7;margin-bottom:22px;}

.opsi{
    display:flex;
    align-items:flex-start;
    gap:11px;
    padding:14px 15px;
    margin-bottom:10px;
    border:1.5px solid var(--border);
    border-radius:12px;
    cursor:pointer;
    background:#fff;
    transition:border-color .15s ease,background .15s ease,transform .15s ease,box-shadow .15s ease;
}
.opsi:hover{background:#f8fbff;border-color:#93c5fd;transform:translateY(-1px);box-shadow:0 5px 14px rgba(37,99,235,.08);}
.opsi:has(input:checked){background:#eff6ff;border-color:#60a5fa;box-shadow:0 5px 15px rgba(37,99,235,.09);}
.opsi input{margin:3px 0 0;accent-color:var(--primary);flex:0 0 auto;}

.navigasi{
    background:var(--surface);
    padding:17px;
    border:1px solid var(--border);
    border-radius:var(--radius);
    margin-bottom:14px;
    box-shadow:var(--shadow);
}
.navigasi h4{margin:0 0 12px;font-size:15px;}
.nomor{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:40px;height:40px;
    margin:3px;
    border-radius:10px;
    background:#eef2f7;
    color:#475569;
    border:1px solid #e2e8f0;
    cursor:pointer;
    font-weight:800;
    transition:.15s ease;
}
.nomor:hover{transform:translateY(-1px);border-color:#93c5fd;}
.nomor.aktif{background:var(--primary);border-color:var(--primary);color:#fff;box-shadow:0 5px 12px rgba(37,99,235,.22);}
.nomor.terjawab{background:var(--success);border-color:var(--success);color:#fff;}
.nomor.aktif.terjawab{background:var(--primary);border-color:var(--primary);}

.tombol{display:flex;gap:10px;margin-bottom:14px;}
.tombol button{
    flex:1;padding:13px 16px;border:1px solid transparent;border-radius:12px;font-size:15px;font-weight:750;cursor:pointer;transition:.15s ease;
}
.tombol button:hover{transform:translateY(-1px);}
.sebelumnya{background:#64748b;color:#fff;}
.berikutnya{background:var(--success);color:#fff;}
.kirim{
    width:100%;padding:15px;border:0;border-radius:12px;background:var(--danger);color:#fff;font-size:16px;font-weight:800;cursor:pointer;box-shadow:0 7px 18px rgba(220,38,38,.16);transition:.15s ease;
}
.kirim:not(:disabled):hover{transform:translateY(-1px);background:#b91c1c;}

#info-minimal-waktu{border-color:#fde68a!important;}


@media (max-width:700px){
    body{background:#f6f8fb;}
    .container{padding:10px 10px 26px;}
    .header{top:0;border-radius:0 0 16px 16px;margin:0 -10px 10px;padding:15px 14px;}
    .header h2{font-size:18px;}
    .mapel{font-size:13px;}
    .timer{margin-top:11px;font-size:20px;padding:10px;}
    .status{font-size:12px;padding:10px 11px;}
    .soal{padding:18px 15px;border-radius:14px;}
    .pertanyaan{font-size:16px;line-height:1.65;}
    .opsi{padding:13px 12px;font-size:15px;}
    .navigasi{padding:14px;border-radius:14px;}
    .nomor{width:38px;height:38px;margin:2px;border-radius:9px;}
    .tombol{position:sticky;bottom:8px;z-index:8;background:rgba(246,248,251,.9);padding:7px 0;margin:0 -2px 12px;backdrop-filter:blur(8px);}
    .tombol button{padding:12px 10px;}
}

@media (max-width:400px){
    .container{padding-left:7px;padding-right:7px;}
    .header{margin-left:-7px;margin-right:-7px;}
    .soal{padding:16px 13px;}
    .nomor{width:36px;height:36px;}
    .tombol button{font-size:14px;}
}

@media (prefers-reduced-motion:reduce){
    *,*::before,*::after{scroll-behavior:auto!important;animation:none!important;transition:none!important;}
}

/* =========================================================
   GATE MULAI UJIAN
========================================================= */

.gate-mulai-ujian {
    position: fixed;
    inset: 0;
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    box-sizing: border-box;
    background: rgba(15, 23, 42, .72);
}

.gate-card {
    width: min(440px, 100%);
    box-sizing: border-box;
    background: #ffffff;
    border-radius: 18px;
    padding: 30px 26px;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,.25);
}

.gate-icon {
    font-size: 42px;
    margin-bottom: 10px;
}

.gate-card h3 {
    margin: 0 0 10px;
    font-size: 24px;
    color: #0f172a;
}

.gate-card p {
    margin: 0 auto 20px;
    max-width: 360px;
    color: #64748b;
    line-height: 1.6;
    font-size: 14px;
}

.btn-mulai-ujian {
    width: 100%;
    border: 0;
    border-radius: 10px;
    padding: 13px 16px;
    background: #2563eb;
    color: #ffffff;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
}

.btn-mulai-ujian:disabled {
    opacity: .7;
    cursor: wait;
}

.gate-status {
    min-height: 20px;
    margin-top: 12px;
    color: #64748b;
    font-size: 13px;
}

/* =========================================================
   KUNCI PENGAWAS
========================================================= */
.lock-overlay {
    position: fixed;
    inset: 0;
    z-index: 110000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15,23,42,.86);
}
.lock-card {
    width: min(460px,100%);
    background:#fff;
    border-radius:20px;
    padding:30px 26px;
    text-align:center;
    box-shadow:0 24px 70px rgba(0,0,0,.30);
}
.lock-icon { font-size:48px; margin-bottom:8px; }
.lock-card h3 { margin:0 0 10px; font-size:24px; color:#0f172a; }
.lock-card p { color:#64748b; line-height:1.6; font-size:14px; margin:0 auto 18px; }
.lock-code {
    width:100%; padding:13px 14px; border:1.5px solid #cbd5e1;
    border-radius:10px; text-align:center; font-size:24px;
    font-weight:800; letter-spacing:.25em; outline:none;
}
.lock-code:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
.btn-unlock, .btn-continue {
    width:100%; border:0; border-radius:10px; padding:13px 16px;
    color:#fff; font-size:16px; font-weight:700; cursor:pointer; margin-top:12px;
}
.btn-unlock { background:#2563eb; }
.btn-continue { background:#16a34a; }
.btn-unlock:disabled, .btn-continue:disabled { opacity:.65; cursor:wait; }
.lock-status { min-height:22px; margin-top:12px; font-size:13px; color:#64748b; }
.lock-help { margin-top:16px!important; font-size:12px!important; color:#94a3b8!important; }

@media (max-width: 480px) {
    .gate-card {
        padding: 25px 20px;
    }

    .gate-card h3 {
        font-size: 21px;
    }
}

.watermark-ujian{position:fixed;left:50%;top:50%;transform:translate(-50%,-50%) rotate(-28deg);z-index:5;pointer-events:none;user-select:none;-webkit-user-select:none;color:rgba(100,116,139,.10);font-size:clamp(18px,3vw,30px);font-weight:800;letter-spacing:.08em;white-space:nowrap;text-align:center;max-width:90vw;}
</style>

</head>


<body>


<!-- LEVEL 13 WATERMARK -->
<div class="watermark-ujian" aria-hidden="true">
    <?= htmlspecialchars((string)($_SESSION["nama"] ?? $_SESSION["nama_siswa"] ?? "SISWA")) ?>
    • ID <?= (int)$siswa_id ?>
    • <?= htmlspecialchars($ujian["nama_ujian"]) ?>
</div>

<div class="container">


<!-- =========================================================
     HEADER
========================================================= -->

<div class="header">

<h2>

<?= htmlspecialchars(
    $ujian["nama_ujian"]
) ?>

</h2>


<div class="mapel">

<?= htmlspecialchars(
    $ujian["nama_mapel"]
) ?>

</div>


<div class="timer">

Waktu tersisa:

<span id="timer">
00:00
</span>

</div>

</div>


<!-- =========================================================
     STATUS
========================================================= -->

<div
    class="status"
    id="status-simpan"
>

Belum ada jawaban disimpan

</div>
<div
    class="status"
    id="status-pelanggaran"
    style="
        background: #fff3cd;
        color: #856404;
        font-weight: bold;
    "
>
    ⚠️ Pelanggaran:
    <?= $jumlah_pelanggaran ?>
</div>


<!-- =========================================================
     MULAI UJIAN / FULLSCREEN GATE
========================================================= -->

<div
    id="gate-mulai-ujian"
    class="gate-mulai-ujian"
>

    <div class="gate-card">

        <div class="gate-icon">🖥️</div>

        <h3>Ujian Siap Dimulai</h3>

        <p id="gate-teks">
            Token ujian benar. Klik tombol di bawah untuk memulai ujian
            dalam mode fullscreen.
        </p>

        <button
            type="button"
            id="btn-mulai-ujian"
            class="btn-mulai-ujian"
        >
            ⛶ Mulai Ujian &amp; Fullscreen
        </button>

        <div
            id="gate-status"
            class="gate-status"
        ></div>

    </div>

</div>

<!-- =========================================================
     KUNCI PENGAWAS
========================================================= -->
<div
    id="overlay-kunci-pengawas"
    class="lock-overlay"
    style="display: <?= $ujian_terkunci_pengawas ? 'flex' : 'none' ?>;"
>
    <div class="lock-card">
        <div class="lock-icon">🔒</div>
        <h3>Ujian Dikunci</h3>
        <p>
            Anda keluar dari mode fullscreen. Ujian dikunci sementara.<br>
            Silakan hubungi pengawas untuk mendapatkan <strong>Kode Pengawas</strong>.
        </p>
        <input
            type="text"
            id="input-kode-pengawas"
            class="lock-code"
            inputmode="numeric"
            autocomplete="off"
            maxlength="6"
            placeholder="6 digit"
        >
        <button type="button" id="btn-buka-kunci" class="btn-unlock">🔑 Buka Kunci</button>
        <button type="button" id="btn-lanjut-ujian" class="btn-continue" style="display:none;">⛶ Lanjutkan Ujian &amp; Fullscreen</button>
        <div id="lock-status" class="lock-status"></div>
        <p class="lock-help">Kode hanya dapat diberikan oleh pengawas ujian.</p>
    </div>
</div>


<!-- =========================================================
     DAFTAR SOAL
========================================================= -->

<div id="daftar-soal">


<?php foreach (
    $soal
    as $index => $s
): ?>


<div
    class="soal
    <?= $index === 0
        ? 'aktif'
        : ''
    ?>"
    id="soal-<?= $index ?>"
>


<div class="nomor-soal">

Soal <?= $index + 1 ?>

</div>


<div class="pertanyaan">

<?= nl2br(
    htmlspecialchars(
        $s["pertanyaan"]
    )
) ?>

</div>


<?php

if (
    isset(
        $opsi[$s["id"]]
    )
):

?>


<?php foreach (
    $opsi[$s["id"]]
    as $indexOpsi => $o
):

    $kodeTampilan = chr(65 + $indexOpsi);

?>


<label class="opsi">


<input

    type="radio"

    name="soal_<?= $s["id"] ?>"

    value="<?= $o["id"] ?>"

    <?= isset(
        $jawaban_siswa[
            $s["id"]
        ]
    )
    &&
    $jawaban_siswa[
        $s["id"]
    ] == $o["id"]

        ? "checked"

        : ""
    ?>

    onchange="
        simpanJawaban(
            <?= $s["id"] ?>,
            <?= $o["id"] ?>
        )
    "

>


<?= $kodeTampilan ?>.

<?= htmlspecialchars(
    $o["teks"]
) ?>


</label>


<?php endforeach; ?>


<?php endif; ?>


</div>


<?php endforeach; ?>


</div>


<!-- =========================================================
     NAVIGASI NOMOR
========================================================= -->

<div class="navigasi">


<h4>
Nomor Soal
</h4>


<?php foreach (
    $soal
    as $index => $s
): ?>


<span

    class="
        nomor

        <?= $index === 0
            ? 'aktif'
            : ''
        ?>

        <?= isset(
            $jawaban_siswa[
                $s["id"]
            ]
        )
            ? 'terjawab'
            : ''
        ?>
    "

    id="nav-<?= $index ?>"

    onclick="
        tampilSoal(
            <?= $index ?>
        )
    "

>

<?= $index + 1 ?>

</span>


<?php endforeach; ?>


</div>


<!-- =========================================================
     TOMBOL SEBELUM / BERIKUTNYA
========================================================= -->

<div class="tombol">


<button
    type="button"
    class="sebelumnya"
    onclick="soalSebelumnya()"
>

← Sebelumnya

</button>


<button
    type="button"
    class="berikutnya"
    onclick="soalBerikutnya()"
>

Berikutnya →

</button>


</div>

<div
    id="info-minimal-waktu"
    class="status"
    style="
        background: #fff3cd;
        color: #856404;
        font-weight: bold;
    "
>
    🔒 Menghitung waktu minimal...
</div>
<!-- =========================================================
     KIRIM MANUAL
========================================================= -->

<form
    action="kirim-ujian.php"
    method="POST"
    onpointerdown="
        tombolKirimDitekan = true;
    "
    onsubmit="
        return konfirmasiKirim();
    "
>


<button
    type="submit"
    class="kirim"
    id="tombol-kirim"
    disabled
    style="
        opacity: 0.5;
        cursor: not-allowed;
    "
>
    🔒 KIRIM UJIAN
</button>

 
</form>


</div>


<script>
/* LEVEL 13 - block copy/paste/shortcuts without counting violations */
(function(){
"use strict";
function editable(t){if(!t)return false;const x=(t.tagName||"").toLowerCase();return x==="input"||x==="textarea"||t.isContentEditable===true;}
function mod(e){return e.ctrlKey||e.metaKey;}
document.addEventListener("contextmenu",e=>e.preventDefault(),true);
document.addEventListener("copy",e=>e.preventDefault(),true);
document.addEventListener("cut",e=>e.preventDefault(),true);
document.addEventListener("paste",e=>{const t=e.target,id=t&&t.id||"";if(editable(t)&&(id==="input-kode-pengawas"||id==="kode-pengawas"))return;e.preventDefault();},true);
document.addEventListener("keydown",function(e){const k=String(e.key||"").toLowerCase(),t=e.target,id=t&&t.id||"";if(editable(t)&&(id==="input-kode-pengawas"||id==="kode-pengawas"))return;if(mod(e)&&["c","v","x","a","u","s","p"].includes(k)){e.preventDefault();e.stopPropagation();return;}if(e.key==="F12"){e.preventDefault();e.stopPropagation();return;}if(mod(e)&&e.shiftKey&&["i","j","c","k"].includes(k)){e.preventDefault();e.stopPropagation();return;}},true);
})();
</script>

<script>
function updateMinimalWaktu() {

    if (
        !tombolKirim ||
        !infoMinimalWaktu
    ) {
        return;
    }

    const sekarang =
        Math.floor(
            Date.now() / 1000
        );

    const sisaMinimal =
        waktuMinimalSelesai - sekarang;

    // lanjutkan kode lama di bawah sini

    /*
    |--------------------------------------------------------------------------
    | Tidak ada minimal waktu
    |--------------------------------------------------------------------------
    */

    if (minimalMenit <= 0) {

        tombolKirim.disabled = false;

        tombolKirim.style.opacity = "1";
        tombolKirim.style.cursor = "pointer";

        tombolKirim.innerText =
            "KIRIM UJIAN";

        infoMinimalWaktu.innerText =
            "✅ Ujian dapat dikirim kapan saja.";

        infoMinimalWaktu.style.background =
            "#d1e7dd";

        infoMinimalWaktu.style.color =
            "#0f5132";

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Minimal waktu belum tercapai
    |--------------------------------------------------------------------------
    */

    if (sisaMinimal > 0) {

        const menit =
            Math.floor(
                sisaMinimal / 60
            );

        const detik =
            sisaMinimal % 60;

        tombolKirim.disabled = true;

        tombolKirim.style.opacity = "0.5";
        tombolKirim.style.cursor = "not-allowed";

        tombolKirim.innerText =
            "🔒 BELUM DAPAT DIKIRIM";

        infoMinimalWaktu.innerText =
            "🔒 KIRIM UJIAN TERSEDIA DALAM " +
            String(menit).padStart(2, "0") +
            ":" +
            String(detik).padStart(2, "0");

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Minimal waktu sudah tercapai
    |--------------------------------------------------------------------------
    */

    tombolKirim.disabled = false;

    tombolKirim.style.opacity = "1";
    tombolKirim.style.cursor = "pointer";

    tombolKirim.innerText =
        "KIRIM UJIAN";

    infoMinimalWaktu.innerText =
        "✅ Minimal waktu telah tercapai. Ujian dapat dikirim.";

    infoMinimalWaktu.style.background =
        "#d1e7dd";

    infoMinimalWaktu.style.color =
        "#0f5132";
}
/*
|--------------------------------------------------------------------------
| TIMER
|--------------------------------------------------------------------------
*/

let sisaDetik =
    <?= (int) $sisa_detik ?>;

let sedangMengirim =
    false;

let tombolKirimDitekan =
    false;

/*
|--------------------------------------------------------------------------
| MINIMAL WAKTU MENGERJAKAN
|--------------------------------------------------------------------------
*/

const minimalMenit =
    <?= (int) $ujian["minimal_menit"] ?>;

const maksimalPelanggaran =
    <?= (int) $ujian["maks_pelanggaran"] ?>;

const mulaiUjian =
    <?= strtotime($sesi["mulai"]) ?>;

const waktuMinimalSelesai =
    mulaiUjian + (minimalMenit * 60);

const tombolKirim =
    document.getElementById(
        "tombol-kirim"
    );

const infoMinimalWaktu =
    document.getElementById(
        "info-minimal-waktu"
    );


/*
|--------------------------------------------------------------------------
| UPDATE TIMER
|--------------------------------------------------------------------------
*/

function updateTimer() {


    /*
    |----------------------------------------------------------------------
    | Jika waktu habis
    |----------------------------------------------------------------------
    */

    if (
        sisaDetik <= 0
    ) {

        document
            .getElementById(
                "timer"
            )
            .innerText =
            "00:00";


        if (
            !sedangMengirim
        ) {

            sedangMengirim =
                true;


            alert(
                "Waktu ujian telah habis. Jawaban akan dikirim otomatis."
            );


            /*
            |------------------------------------------------------------------
            | KIRIM OTOMATIS
            |------------------------------------------------------------------
            */

            window.location.href =
                "auto-kirim.php";

        }


        return;

    }


    /*
    |----------------------------------------------------------------------
    | Hitung menit dan detik
    |----------------------------------------------------------------------
    */

    let menit =
        Math.floor(
            sisaDetik / 60
        );


    let detik =
        sisaDetik % 60;


    document
        .getElementById(
            "timer"
        )
        .innerText =

        String(
            menit
        ).padStart(
            2,
            "0"
        )

        + ":"

        +

        String(
            detik
        ).padStart(
            2,
            "0"
        );


    sisaDetik--;

}


/*
|--------------------------------------------------------------------------
| Jalankan timer
|--------------------------------------------------------------------------
*/

updateTimer();
updateMinimalWaktu();


setInterval(
    updateTimer,
    1000
);

setInterval(
    updateMinimalWaktu,
    1000
);


/*
|--------------------------------------------------------------------------
| SIMPAN JAWABAN
|--------------------------------------------------------------------------
*/

function simpanJawaban(
    soalId,
    opsiId
) {


    document
        .getElementById(
            "status-simpan"
        )
        .innerText =
        "Menyimpan jawaban...";


    fetch(
        "simpan-jawaban.php",
        {

            method: "POST",

            headers: {

                "Content-Type":
                    "application/json"

            },

            body:
                JSON.stringify({

                    sesi_id:
                        <?= (int) $sesi["id"] ?>,

                    soal_id:
                        soalId,

                    opsi_id:
                        opsiId

                })

        }

    )


    .then(
        response =>
            response.json()
    )


    .then(
        data => {


            if (
                data.status
            ) {

                document
                    .getElementById(
                        "status-simpan"
                    )
                    .innerText =
                    data.pesan;


                /*
                |--------------------------------------------------------------
                | Tandai nomor sebagai sudah dijawab
                |--------------------------------------------------------------
                */

                let index =
                    cariIndexSoal(
                        soalId
                    );


                let nav =
                    document
                        .getElementById(
                            "nav-" + index
                        );


                if (nav) {

                    nav.classList
                        .add(
                            "terjawab"
                        );

                }


            } else {

                document
                    .getElementById(
                        "status-simpan"
                    )
                    .innerText =
                    "Gagal menyimpan jawaban.";

            }


        }

    )


    .catch(
        error => {

            console.error(
                error
            );


            document
                .getElementById(
                    "status-simpan"
                )
                .innerText =
                "Gagal menyimpan jawaban.";

        }
    );

}


/*
|--------------------------------------------------------------------------
| CARI INDEX SOAL
|--------------------------------------------------------------------------
*/

function cariIndexSoal(
    soalId
) {


    let semuaSoal =
        document.querySelectorAll(
            ".soal"
        );


    for (
        let i = 0;
        i < semuaSoal.length;
        i++
    ) {


        if (
            semuaSoal[i]
                .querySelector(
                    'input[name="soal_' +
                    soalId +
                    '"]'
                )
        ) {

            return i;

        }

    }


    return 0;

}


/*
|--------------------------------------------------------------------------
| SOAL SEKARANG
|--------------------------------------------------------------------------
*/

let soalSekarang = 0;


/*
|--------------------------------------------------------------------------
| TAMPILKAN SOAL
|--------------------------------------------------------------------------
*/

function tampilSoal(
    index
) {


    let semuaSoal =
        document.querySelectorAll(
            ".soal"
        );


    let semuaNomor =
        document.querySelectorAll(
            ".nomor"
        );


    if (
        index < 0
        ||
        index >=
        semuaSoal.length
    ) {

        return;

    }


    /*
    |----------------------------------------------------------------------
    | Sembunyikan semua soal
    |----------------------------------------------------------------------
    */

    semuaSoal.forEach(
        soal => {

            soal.classList
                .remove(
                    "aktif"
                );

        }
    );


    /*
    |----------------------------------------------------------------------
    | Hilangkan nomor aktif
    |----------------------------------------------------------------------
    */

    semuaNomor.forEach(
        nomor => {

            nomor.classList
                .remove(
                    "aktif"
                );

        }
    );


    /*
    |----------------------------------------------------------------------
    | Tampilkan soal yang dipilih
    |----------------------------------------------------------------------
    */

    semuaSoal[index]
        .classList
        .add(
            "aktif"
        );


    semuaNomor[index]
        .classList
        .add(
            "aktif"
        );


    soalSekarang =
        index;


    /*
    |----------------------------------------------------------------------
    | Scroll ke atas
    |----------------------------------------------------------------------
    */

    window.scrollTo({

        top: 0,

        behavior: "smooth"

    });

}


/*
|--------------------------------------------------------------------------
| SOAL BERIKUTNYA
|--------------------------------------------------------------------------
*/

function soalBerikutnya() {


    let totalSoal =
        document.querySelectorAll(
            ".soal"
        ).length;


    if (
        soalSekarang
        <
        totalSoal - 1
    ) {

        tampilSoal(
            soalSekarang + 1
        );

    }

}


/*
|--------------------------------------------------------------------------
| SOAL SEBELUMNYA
|--------------------------------------------------------------------------
*/

function soalSebelumnya() {


    if (
        soalSekarang > 0
    ) {

        tampilSoal(
            soalSekarang - 1
        );

    }

}


/*
|--------------------------------------------------------------------------
| KONFIRMASI KIRIM MANUAL
|--------------------------------------------------------------------------
*/

function konfirmasiKirim() {
	    const sekarang =
        Math.floor(Date.now() / 1000);

    if (
        sekarang < waktuMinimalSelesai
    ) {

        alert(
            "Ujian belum dapat dikirim.\n\n" +
            "Minimal waktu mengerjakan adalah " +
            minimalMenit +
            " menit."
        );

        return false;
    }

    if (
        sedangMengirim
    ) {

        return false;

    }


    let semuaSoal =
        document.querySelectorAll(
            ".soal"
        );


    let totalSoal =
        semuaSoal.length;


    let sudahDijawab = 0;


    semuaSoal.forEach(
        function(soal) {

            let pilihan =
                soal.querySelector(
                    'input[type="radio"]:checked'
                );


            if (pilihan) {

                sudahDijawab++;

            }

        }
    );


    let belumDijawab =
        totalSoal - sudahDijawab;


    if (
        belumDijawab > 0
    ) {

        alert(
            "Ujian belum dapat dikirim.\n\n" +
            "Total soal      : " +
            totalSoal +
            "\n" +
            "Sudah dijawab   : " +
            sudahDijawab +
            "\n" +
            "Belum dijawab   : " +
            belumDijawab +
            "\n\n" +
            "Silakan jawab semua soal terlebih dahulu."
        );


        return false;

    }


    let yakin = confirm(
    "Semua soal sudah dijawab.\n\n" +
    "Total soal    : " +
    totalSoal +
    "\n" +
    "Sudah dijawab : " +
    sudahDijawab +
    "\n\n" +
    "Apakah Anda yakin ingin mengirim ujian?"
);

if (yakin) {

    sedangMengirim = true;

    return true;

}

tombolKirimDitekan = false;

return false;

}

// =========================================================
// ANTI-CURANG TAHAP 1
// DETEKSI MENINGGALKAN HALAMAN UJIAN
// =========================================================

let jumlahPelanggaran =
    <?= (int) $jumlah_pelanggaran ?>;
let sedangMeninggalkanHalaman = false;
let waktuTerakhirPelanggaran = 0;
let ujianSudahSelesai = false;
let sedangMengecekStatus = false;

/*
|--------------------------------------------------------------------------
| FULLSCREEN - MULAI UJIAN
|--------------------------------------------------------------------------
|
| Fullscreen wajib diminta dari tindakan pengguna. Karena navigasi dari
| halaman token ke ujian akan keluar dari fullscreen, halaman ujian
| menampilkan satu tombol terakhir setelah token berhasil diverifikasi.
|
| Browser yang tidak mendukung Fullscreen API tidak dianggap pelanggaran.
| Setelah fullscreen benar-benar aktif, keluar fullscreen = 1 pelanggaran.
|
*/

let fullscreenPernahAktif = false;
let sedangMemintaFullscreen = false;
let ujianSedangTerkunci = <?= $ujian_terkunci_pengawas ? 'true' : 'false' ?>;
let menungguLanjutSetelahUnlock = false;
let sedangMemprosesKeluarFullscreen = false;
let sedangMengunciSesi = false;


function sedangFullscreen() {

    return !!(
        document.fullscreenElement ||
        document.webkitFullscreenElement
    );

}


function browserMendukungFullscreen() {

    return !!(
        document.fullscreenEnabled &&
        document.documentElement &&
        typeof document.documentElement.requestFullscreen === "function"
    );

}


function tutupGateMulaiUjian() {

    const gate =
        document.getElementById("gate-mulai-ujian");

    if (gate) {
        gate.style.display = "none";
    }

}


function perbaruiTeksGate() {

    const teks =
        document.getElementById("gate-teks");

    const tombol =
        document.getElementById("btn-mulai-ujian");

    if (!teks || !tombol) {
        return;
    }

    if (browserMendukungFullscreen()) {

        teks.innerText =
            "Token ujian benar. Klik tombol di bawah untuk memulai ujian dalam mode fullscreen.";

        tombol.innerText =
            "⛶ Mulai Ujian & Fullscreen";

    } else {

        teks.innerText =
            "Token ujian benar. Browser ini tidak menyediakan fullscreen untuk halaman ujian. Ujian tetap dapat dikerjakan dengan pengamanan lainnya.";

        tombol.innerText =
            "▶ Mulai Ujian";

    }

}


async function prosesMulaiUjianFullscreen() {

    if (
        ujianSudahSelesai ||
        sedangMemintaFullscreen
    ) {
        return;
    }

    const tombol =
        document.getElementById("btn-mulai-ujian");

    const status =
        document.getElementById("gate-status");

    sedangMemintaFullscreen = true;

    if (tombol) {
        tombol.disabled = true;
    }

    if (status) {
        status.innerText =
            "Menyiapkan ujian...";
    }

    if (browserMendukungFullscreen()) {

        try {

            await document.documentElement.requestFullscreen();

            fullscreenPernahAktif = true;

            tutupGateMulaiUjian();

        } catch (error) {

            console.error(
                "Fullscreen tidak dapat diaktifkan:",
                error
            );

            /*
            | Gagal fullscreen bukan pelanggaran. Siswa tetap dapat
            | melanjutkan ujian agar browser yang membatasi fullscreen
            | tidak mengunci siswa secara keliru.
            */

            if (status) {
                status.innerText =
                    "Fullscreen tidak tersedia. Ujian tetap dimulai.";
            }

            setTimeout(
                tutupGateMulaiUjian,
                700
            );

        }

    } else {

        tutupGateMulaiUjian();

    }

    sedangMemintaFullscreen = false;

    if (tombol) {
        tombol.disabled = false;
    }

}


perbaruiTeksGate();


const tombolMulaiUjian =
    document.getElementById("btn-mulai-ujian");

if (tombolMulaiUjian) {

    tombolMulaiUjian.addEventListener(
        "click",
        prosesMulaiUjianFullscreen
    );

}


function prosesPerubahanFullscreen() {

    const aktif =
        sedangFullscreen();

    if (
        aktif ||
        !fullscreenPernahAktif ||
        ujianSudahSelesai ||
        sedangMemintaFullscreen ||
        sedangMemprosesKeluarFullscreen ||
        ujianSedangTerkunci
    ) {
        return;
    }

    /*
    | Desktop maupun HP dapat memicu fullscreenchange dan
    | visibilitychange hampir bersamaan. Satu kejadian fullscreen
    | keluar hanya boleh diproses satu kali.
    |
    | Penting: jangan mensyaratkan visibilityState = visible. Pada
    | sebagian HP, keluar fullscreen justru memicu visibilitychange
    | lebih dahulu. Jika menunggu halaman kembali visible, sesi belum
    | sempat dikunci dan siswa dapat keluar fullscreen berkali-kali.
    */
    sedangMemprosesKeluarFullscreen = true;

    (async function () {

        try {

            /* Catat pelanggaran terlebih dahulu dan tunggu response server. */
            await catatPelanggaran(
                "Keluar fullscreen",
                false
            );

            if (!ujianSudahSelesai) {
                await kunciSesiPengawas();
            }

        } catch (error) {

            console.error(
                "Gagal memproses keluar fullscreen:",
                error
            );

        } finally {

            sedangMemprosesKeluarFullscreen = false;

        }

    })();

}


document.addEventListener(
    "fullscreenchange",
    prosesPerubahanFullscreen
);

document.addEventListener(
    "webkitfullscreenchange",
    prosesPerubahanFullscreen
);


async function kunciSesiPengawas() {

    if (
        ujianSudahSelesai ||
        ujianSedangTerkunci ||
        sedangMengunciSesi
    ) {
        return;
    }

    sedangMengunciSesi = true;
    ujianSedangTerkunci = true;
    tampilkanOverlayKunci();

    try {
        const response = await fetch("kunci-sesi-pengawas.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
            },
            body:
                "sesi_id=<?= (int) $sesi["id"] ?>" +
                "&ujian_id=<?= (int) $ujian_id ?>",
            cache: "no-store",
            credentials: "same-origin"
        });

        const data = await response.json();

        if (data.status !== true) {
            setLockStatus(data.pesan || "Sesi gagal dikunci. Silakan hubungi pengawas.", true);
        }
    } catch (error) {
        console.error("Gagal mengunci sesi pengawas:", error);
        setLockStatus("Koneksi ke server gagal. Ujian tetap dikunci pada tampilan.", true);
    } finally {
        sedangMengunciSesi = false;
    }
}

function tampilkanOverlayKunci() {
    const overlay = document.getElementById("overlay-kunci-pengawas");
    if (overlay) overlay.style.display = "flex";

    /*
    | Setiap penguncian fullscreen harus memulai ulang form Kode Pengawas.
    | Setelah unlock pertama, input dan tombol memang dibuat disabled/hidden.
    | Jika terjadi pelanggaran kedua, keduanya harus diaktifkan kembali.
    */
    const input = document.getElementById("input-kode-pengawas");
    const tombol = document.getElementById("btn-buka-kunci");
    const lanjut = document.getElementById("btn-lanjut-ujian");

    if (input) {
        input.disabled = false;
        input.value = "";
        input.focus();
    }

    if (tombol) {
        tombol.disabled = false;
        tombol.style.display = "block";
    }

    if (lanjut) {
        lanjut.disabled = false;
        lanjut.style.display = "none";
    }

    setLockStatus("");

    document.querySelectorAll("input[type='radio'], textarea, select, #daftar-soal button, .nomor")
        .forEach(function(el) { el.disabled = true; });
}

function setLockStatus(teks, error=false) {
    const el = document.getElementById("lock-status");
    if (el) {
        el.innerText = teks;
        el.style.color = error ? "#dc2626" : "#64748b";
    }
}

async function bukaKunciPengawas() {
    if (ujianSudahSelesai) return;

    const input = document.getElementById("input-kode-pengawas");
    const tombol = document.getElementById("btn-buka-kunci");
    const kode = input ? input.value.trim() : "";

    if (!/^\d{6}$/.test(kode)) {
        setLockStatus("Masukkan kode pengawas 6 digit.", true);
        if (input) input.focus();
        return;
    }

    if (tombol) tombol.disabled = true;
    setLockStatus("Memeriksa kode pengawas...", false);

    try {
        const response = await fetch("buka-kunci-pengawas.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
            },
            body:
                "sesi_id=<?= (int) $sesi["id"] ?>" +
                "&ujian_id=<?= (int) $ujian_id ?>" +
                "&kode=" + encodeURIComponent(kode),
            cache: "no-store",
            credentials: "same-origin"
        });

        const data = await response.json();

        if (data.status !== true) {
            setLockStatus(data.pesan || "Kode pengawas tidak valid.", true);
            if (tombol) tombol.disabled = false;
            return;
        }

        menungguLanjutSetelahUnlock = true;
        setLockStatus("Kode benar. Klik tombol hijau untuk melanjutkan ujian.", false);
        if (input) input.disabled = true;
        if (tombol) tombol.style.display = "none";

        const lanjut = document.getElementById("btn-lanjut-ujian");
        if (lanjut) lanjut.style.display = "block";

    } catch (error) {
        console.error("Gagal membuka kunci:", error);
        setLockStatus("Koneksi ke server gagal. Coba lagi.", true);
        if (tombol) tombol.disabled = false;
    }
}

async function lanjutSetelahUnlock() {
    if (!menungguLanjutSetelahUnlock || ujianSudahSelesai) return;

    const tombol = document.getElementById("btn-lanjut-ujian");
    if (tombol) tombol.disabled = true;

    setLockStatus("Menyiapkan fullscreen...", false);

    if (!browserMendukungFullscreen()) {
        setLockStatus("Browser ini tidak dapat mengaktifkan fullscreen. Silakan hubungi pengawas.", true);
        if (tombol) tombol.disabled = false;
        return;
    }

    try {
        await document.documentElement.requestFullscreen();
        fullscreenPernahAktif = true;
    } catch (error) {
        console.warn("Fullscreen tidak tersedia setelah unlock:", error);
        setLockStatus("Fullscreen gagal diaktifkan. Tekan tombol hijau untuk mencoba lagi.", true);
        if (tombol) tombol.disabled = false;
        return;
    }

    ujianSedangTerkunci = false;
    menungguLanjutSetelahUnlock = false;

    /*
    | Form kode akan di-reset ketika overlay dibuka kembali oleh
    | pelanggaran fullscreen berikutnya.
    */

    const overlay = document.getElementById("overlay-kunci-pengawas");
    if (overlay) overlay.style.display = "none";

    document.querySelectorAll("input[type='radio'], textarea, select, #daftar-soal button, .nomor")
        .forEach(function(el) { el.disabled = false; });

    setLockStatus("");
}

const btnBukaKunci = document.getElementById("btn-buka-kunci");
if (btnBukaKunci) btnBukaKunci.addEventListener("click", bukaKunciPengawas);

const btnLanjutUjian = document.getElementById("btn-lanjut-ujian");
if (btnLanjutUjian) btnLanjutUjian.addEventListener("click", lanjutSetelahUnlock);

const inputKodePengawas = document.getElementById("input-kode-pengawas");
if (inputKodePengawas) {
    inputKodePengawas.addEventListener("input", function() {
        this.value = this.value.replace(/\D/g, "").slice(0, 6);
    });
}

if (ujianSedangTerkunci) {
    tampilkanOverlayKunci();
}


function tampilkanPeringatanPelanggaran(jenis) {

    let dialog =
        document.getElementById(
            "dialog-pelanggaran"
        );

    if (!dialog) {

        dialog =
            document.createElement("div");

        dialog.id =
            "dialog-pelanggaran";

        dialog.style.position =
            "fixed";

        dialog.style.left =
            "16px";

        dialog.style.right =
            "16px";

        dialog.style.bottom =
            "20px";

        dialog.style.zIndex =
            "999999";

        dialog.style.background =
            "#fff7ed";

        dialog.style.border =
            "2px solid #f97316";

        dialog.style.borderRadius =
            "14px";

        dialog.style.padding =
            "16px";

        dialog.style.boxShadow =
            "0 8px 30px rgba(0,0,0,.22)";

        dialog.style.fontFamily =
            "Arial, sans-serif";

        dialog.innerHTML =
            '<div style="font-size:17px;font-weight:bold;color:#c2410c;margin-bottom:8px;">' +
                '⚠️ Peringatan Ujian' +
            '</div>' +

            '<div id="dialog-pelanggaran-text" style="font-size:14px;line-height:1.5;color:#444;">' +
            '</div>' +

            '<button type="button" id="tutup-dialog-pelanggaran" ' +
                'style="margin-top:12px;padding:9px 14px;border:0;border-radius:8px;background:#f97316;color:white;font-weight:bold;">' +
                'Saya Mengerti' +
            '</button>';

        document.body.appendChild(
            dialog
        );

        document
            .getElementById(
                "tutup-dialog-pelanggaran"
            )
            .addEventListener(
                "click",
                function () {

                    dialog.style.display =
                        "none";

                }
            );
    }

    const isi =
        document.getElementById(
            "dialog-pelanggaran-text"
        );

    if (isi) {

        isi.innerHTML =
            "Anda meninggalkan halaman ujian.<br>" +
            "Jenis: <strong>" +
            jenis +
            "</strong><br>" +
            "Pelanggaran: <strong>" +
            jumlahPelanggaran +
            "</strong>.<br>" +
            "Silakan kembali fokus pada ujian.";

    }

    dialog.style.display =
        "block";
}


/*
|--------------------------------------------------------------------------
| HENTIKAN UJIAN DARI SISI CLIENT
|--------------------------------------------------------------------------
|
| Server tetap menjadi sumber kebenaran. Fungsi ini hanya mengunci
| tampilan agar siswa tidak terus mengerjakan setelah sesi selesai.
|
*/

function hentikanUjianDanArahkan(data) {

    if (ujianSudahSelesai) {
        return;
    }

    ujianSudahSelesai = true;
    sedangMengirim = true;
    tombolKirimDitekan = true;

    document
        .querySelectorAll(
            'input[type="radio"], button, textarea, select'
        )
        .forEach(
            function (el) {
                el.disabled = true;
            }
        );

    const hasilId =
        parseInt(
            data.hasil_id || 0,
            10
        );

    if (hasilId > 0) {

        window.location.href =
            "hasil.php?id=" +
            hasilId;

        return;
    }

    /*
    | Jika hasil_id belum tersedia, jangan mengarahkan ke halaman
    | yang salah. Cek ulang setelah server selesai memproses.
    */

    setTimeout(
        function () {

            cekStatusSesi(true);

        },
        1000
    );
}



/*
|--------------------------------------------------------------------------
| HEARTBEAT SESI UJIAN
|--------------------------------------------------------------------------
|
| Memberi tahu server bahwa halaman ujian masih aktif.
| Server mencatat waktu ke kolom sesi_ujian.terakhir_aktif.
|
| Heartbeat TIDAK memperpanjang batas_waktu.
| Identitas siswa tetap diambil dari PHP session oleh heartbeat.php.
|
*/

let sedangHeartbeat = false;

async function kirimHeartbeat() {

    if (
        ujianSudahSelesai ||
        sedangHeartbeat
    ) {

        return;

    }

    sedangHeartbeat = true;

    try {

        const response =
            await fetch(
                "heartbeat.php",
                {
                    method: "POST",
                    headers: {
                        "Content-Type":
                            "application/x-www-form-urlencoded; charset=UTF-8"
                    },
                    body:
                        "sesi_id=<?= (int) $sesi["id"] ?>" +
                        "&ujian_id=<?= (int) $ujian_id ?>",
                    cache: "no-store",
                    credentials: "same-origin"
                }
            );

        const data =
            await response.json();

        /*
        | Jika sesi sudah tidak aktif atau waktu habis,
        | biarkan mekanisme cek-status-sesi yang menangani pengalihan.
        */
        if (
            data.status === "expired" ||
            data.status === "inactive"
        ) {

            cekStatusSesi(true);

        }

    } catch (error) {

        /*
        | Kegagalan heartbeat tidak langsung menghentikan ujian.
        | Browser dapat mengalami gangguan sementara.
        | Server tetap menjadi sumber kebenaran.
        */
        console.error(
            "Heartbeat gagal:",
            error
        );

    } finally {

        sedangHeartbeat = false;

    }
}


/*
|--------------------------------------------------------------------------
| HEARTBEAT AWAL
|--------------------------------------------------------------------------
*/

kirimHeartbeat();


/*
|--------------------------------------------------------------------------
| HEARTBEAT BERKALA
|--------------------------------------------------------------------------
|
| Setiap 10 detik browser memberi tahu server bahwa halaman masih aktif.
|
*/

setInterval(
    function () {

        if (!ujianSudahSelesai) {

            kirimHeartbeat();

        }

    },
    10000
);


/*
|--------------------------------------------------------------------------
| CEK STATUS SESI KE SERVER
|--------------------------------------------------------------------------
|
| Ini adalah pengaman utama untuk kasus sendBeacon:
| browser tidak menerima response dari sendBeacon, sehingga ketika
| siswa kembali ke halaman kita tanyakan status sesi ke server.
|
*/

async function cekStatusSesi(arahKeHasil = false) {

    if (sedangMengecekStatus) {
        return;
    }

    sedangMengecekStatus = true;

    try {

        const response =
            await fetch(
                "cek-status-sesi.php?sesi_id=<?= (int) $sesi["id"] ?>&ujian_id=<?= (int) $ujian_id ?>",
                {
                    method: "GET",
                    cache: "no-store",
                    credentials: "same-origin"
                }
            );

        const data =
            await response.json();

        if (
            data.total_pelanggaran !== undefined
        ) {
            jumlahPelanggaran =
                parseInt(data.total_pelanggaran, 10) ||
                jumlahPelanggaran;

            const statusPelanggaran =
                document.getElementById("status-pelanggaran");

            if (statusPelanggaran) {
                statusPelanggaran.innerText =
                    "⚠️ Pelanggaran: " +
                    jumlahPelanggaran;
            }
        }

        if (
            data.status === true &&
            data.terkunci_pengawas === true
        ) {

            /*
            | Jangan memanggil tampilkanOverlayKunci() berulang-ulang.
            | cekStatusSesi berjalan setiap 5 detik. Jika form di-reset
            | setiap kali pengecekan, kode yang sedang diketik siswa akan
            | terhapus sendiri. Overlay hanya diinisialisasi saat status
            | berubah menjadi terkunci.
            */
            if (!ujianSedangTerkunci) {
                ujianSedangTerkunci = true;
                tampilkanOverlayKunci();
            }

            return;
        }

        if (
            data.status === true &&
            data.waktu_habis === true
        ) {

            if (!ujianSudahSelesai) {

                ujianSudahSelesai = true;
                sedangMengirim = true;

                window.location.href =
                    "auto-kirim.php";

            }

            return;
        }

        if (
            data.status === true &&
            data.sesi_status !== "mengerjakan"
        ) {

            hentikanUjianDanArahkan(
                data
            );

        }

    } catch (error) {

        console.error(
            "Gagal mengecek status sesi:",
            error
        );

    } finally {

        sedangMengecekStatus = false;

    }
}


/*
|--------------------------------------------------------------------------
| KIRIM PELANGGARAN
|--------------------------------------------------------------------------
|
| Saat halaman masih aktif:
|   gunakan fetch agar response auto_submit dapat dibaca.
|
| Saat halaman sedang menuju background:
|   gunakan sendBeacon agar request tetap dapat dikirim.
|
*/

async function kirimPelanggaranKeServer(
    jenis,
    gunakanBeacon = false
) {

    const payload = {
        sesi_id:
            <?= (int) $sesi["id"] ?>,

        ujian_id:
            <?= (int) $ujian_id ?>,

        jenis:
            jenis
    };


    /*
    |--------------------------------------------------------------------------
    | MODE BEACON
    |--------------------------------------------------------------------------
    */

    if (
        gunakanBeacon &&
        navigator.sendBeacon
    ) {

        try {

            /*
            | Endpoint catat-pelanggaran.php membaca JSON dari
            | php://input. Karena sendBeacon tidak menerima response,
            | kirim JSON melalui Blob agar format request tetap cocok.
            */

            const body =
                new Blob(
                    [
                        JSON.stringify(payload)
                    ],
                    {
                        type:
                            "application/json"
                    }
                );

            const berhasil =
                navigator.sendBeacon(
                    "catat-pelanggaran.php",
                    body
                );

            if (berhasil) {
                return {
                    terkirim: true,
                    response: false
                };
            }

        } catch (error) {

            console.error(
                "sendBeacon gagal:",
                error
            );

        }
    }


    /*
    |--------------------------------------------------------------------------
    | MODE FETCH
    |--------------------------------------------------------------------------
    */

    try {

        const response =
            await fetch(
                "catat-pelanggaran.php",
                {
                    method:
                        "POST",

                    headers: {
                        "Content-Type":
                            "application/json"
                    },

                    body:
                        JSON.stringify(
                            payload
                        ),

                    credentials:
                        "same-origin",

                    keepalive:
                        true
                }
            );

        const data =
            await response.json();

        return {
            terkirim: true,
            response: true,
            data: data
        };

    } catch (error) {

        console.error(
            "Gagal mengirim pelanggaran:",
            error
        );

        return {
            terkirim: false,
            response: false
        };

    }
}


/*
|--------------------------------------------------------------------------
| CATAT PELANGGARAN
|--------------------------------------------------------------------------
*/

async function catatPelanggaran(
    jenis,
    gunakanBeacon = false
) {

    if (
        ujianSudahSelesai ||
        sedangMengirim ||
        tombolKirimDitekan
    ) {

        return;

    }


    const sekarang =
        Date.now();


    /*
    | visibilitychange dan blur dapat terjadi hampir bersamaan.
    | Cegah satu kejadian tercatat dua kali.
    */

    if (
        sekarang -
        waktuTerakhirPelanggaran
        < 2000
    ) {

        return;

    }


    waktuTerakhirPelanggaran =
        sekarang;


    jumlahPelanggaran++;


    const statusPelanggaran =
        document.getElementById(
            "status-pelanggaran"
        );

    if (statusPelanggaran) {

        statusPelanggaran.innerText =
            "⚠️ Pelanggaran: " +
            jumlahPelanggaran;

    }


    /*
    | Jika halaman benar-benar menuju background, beacon diprioritaskan.
    | Jika halaman tetap aktif, fetch dipakai agar response server bisa
    | dibaca dan auto_submit dapat ditangani.
    */

    const hasil =
        await kirimPelanggaranKeServer(
            jenis,
            gunakanBeacon
        );


    /*
    | Response hanya tersedia pada mode fetch.
    */

    if (
        hasil.response &&
        hasil.data
    ) {

        if (
            hasil.data.total_pelanggaran !== undefined
        ) {
            jumlahPelanggaran =
                parseInt(hasil.data.total_pelanggaran, 10) ||
                jumlahPelanggaran;

            const statusPelanggaran =
                document.getElementById("status-pelanggaran");

            if (statusPelanggaran) {
                statusPelanggaran.innerText =
                    "⚠️ Pelanggaran: " +
                    jumlahPelanggaran;
            }
        }

        if (
            hasil.data.status === true &&
            hasil.data.auto_submit === true
        ) {

            /* Server sudah menyelesaikan sesi dan membuat hasil.
            | Jangan lanjut ke proses kunci pengawas. */
            hentikanUjianDanArahkan(
                hasil.data
            );

            return;

        }

        if (
            hasil.data.status === false &&
            hasil.data.pesan ===
                "Ujian sudah selesai."
        ) {

            cekStatusSesi(true);

            return;

        }

    }


    if (!ujianSudahSelesai) {

        tampilkanPeringatanPelanggaran(
            jenis
        );

    }
}


/*
|--------------------------------------------------------------------------
| DETEKSI PINDAH TAB / BROWSER KE BACKGROUND
|--------------------------------------------------------------------------
*/

document.addEventListener(
    "visibilitychange",
    function () {

        if (
            sedangMemprosesKeluarFullscreen ||
            sedangMengunciSesi
        ) {
            return;
        }

        if (
            document.visibilityState ===
            "hidden"
        ) {

            sedangMeninggalkanHalaman =
                true;

            /*
            | Beacon dipakai karena halaman mungkin segera dihentikan
            | oleh browser/iPhone.
            */

            catatPelanggaran(
                "Pindah tab",
                false
            );

        } else {

            sedangMeninggalkanHalaman =
                false;

            /*
            | Setelah kembali, tanyakan status sesi ke server.
            | Ini menangani kasus batas maksimum pelanggaran tercapai
            | ketika siswa sedang berada di background.
            */

            cekStatusSesi();

        }

    }
);


/*
|--------------------------------------------------------------------------
| DETEKSI WINDOW KEHILANGAN FOKUS
|--------------------------------------------------------------------------
*/

window.addEventListener(
    "blur",
    function () {

        if (
            ujianSudahSelesai ||
            sedangMengirim ||
            tombolKirimDitekan ||
            sedangMemprosesKeluarFullscreen ||
            sedangMengunciSesi
        ) {

            return;

        }

        /*
        | Blur tidak selalu berarti halaman benar-benar ditinggalkan.
        | Tetap dicatat sebagai pelanggaran sesuai mekanisme sebelumnya,
        | tetapi gunakan fetch agar response server dapat diproses.
        */

        catatPelanggaran(
            "Halaman ditinggalkan",
            false
        );

    }
);

/*
|--------------------------------------------------------------------------
| CADANGAN SAAT HALAMAN BENAR-BENAR DITUTUP / DIBUANG
|--------------------------------------------------------------------------
|
| Pada sebagian browser, pagehide dapat menjadi event terakhir yang
| diterima sebelum dokumen dihentikan. Deduplikasi 2 detik mencegah
| visibilitychange + pagehide dihitung sebagai dua pelanggaran.
|
*/

window.addEventListener(
    "pagehide",
    function () {

        if (
            ujianSudahSelesai ||
            sedangMengirim ||
            tombolKirimDitekan ||
            sedangMemprosesKeluarFullscreen ||
            sedangMengunciSesi
        ) {

            return;

        }

        catatPelanggaran(
            "Halaman ditinggalkan",
            true
        );

    }
);


/*
|--------------------------------------------------------------------------
| CEK STATUS BERKALA
|--------------------------------------------------------------------------
|
| Pengaman tambahan. Tidak mengubah data; hanya membaca status sesi.
| Interval 5 detik cukup ringan dan membantu jika server sudah
| menyelesaikan sesi sementara event visibilitychange tidak tertangkap.
|
*/

setInterval(
    function () {

        if (
            !ujianSudahSelesai &&
            !document.hidden
        ) {

            cekStatusSesi();

        }

    },
    5000
);


/*
|--------------------------------------------------------------------------
| CEK STATUS SAAT HALAMAN PERTAMA KALI SIAP
|--------------------------------------------------------------------------
*/

cekStatusSesi();


</script>


</body>

</html>
