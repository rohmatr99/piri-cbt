<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../config/database.php";

// Ambil filter
$ujian_id = isset($_GET["ujian_id"]) ? (int) $_GET["ujian_id"] : 0;
$kelas = isset($_GET["kelas"]) ? trim($_GET["kelas"]) : "";
$siswa_id = isset($_GET["siswa_id"]) ? (int) $_GET["siswa_id"] : 0;

// Query dasar
$sql = "
    SELECT
        h.id,
        s.nama AS nama_siswa,
        s.username,
        s.kelas,
        u.nama_ujian,
        h.jumlah_soal,
        h.jumlah_dijawab,
        h.jumlah_benar,
        h.jumlah_salah,
        h.nilai
    FROM hasil_ujian h
    JOIN siswa s ON h.siswa_id = s.id
    JOIN ujian u ON h.ujian_id = u.id
    WHERE 1=1
";

$params = [];
$types = "";

// Filter ujian
if ($ujian_id > 0) {
    $sql .= " AND h.ujian_id = ?";
    $types .= "i";
    $params[] = $ujian_id;
}

// Filter kelas
if ($kelas !== "") {
    $sql .= " AND s.kelas = ?";
    $types .= "s";
    $params[] = $kelas;
}

// Filter siswa
if ($siswa_id > 0) {
    $sql .= " AND h.siswa_id = ?";
    $types .= "i";
    $params[] = $siswa_id;
}

$sql .= " ORDER BY s.nama ASC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Query gagal: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();

// Nama file
$filename = "hasil-ujian-" . date("Y-m-d-H-i-s") . ".csv";

// Header download
header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Buka output
$output = fopen("php://output", "w");

// BOM UTF-8 agar Excel membaca huruf Indonesia dengan benar
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Header kolom
fputcsv($output, [
    "No",
    "Nama Siswa",
    "Username",
    "Kelas",
    "Ujian",
    "Jumlah Soal",
    "Dijawab",
    "Benar",
    "Salah",
    "Nilai"
], ";");

// Isi data
$no = 1;

while ($row = $result->fetch_assoc()) {

    fputcsv($output, [
        $no,
        $row["nama_siswa"],
        $row["username"],
        $row["kelas"],
        $row["nama_ujian"],
        $row["jumlah_soal"],
        $row["jumlah_dijawab"],
        $row["jumlah_benar"],
        $row["jumlah_salah"],
        $row["nilai"]
    ], ";");

    $no++;
}

fclose($output);

$stmt->close();

exit;