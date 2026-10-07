<?php
session_start();
require_once "../config/database.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

function balas($status, $pesan, $tambahan = []) {
    echo json_encode(array_merge(["status"=>$status,"pesan"=>$pesan],$tambahan), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") balas(false,"Metode tidak diizinkan.");
if (!isset($_SESSION["siswa_id"])) balas(false,"Sesi login siswa tidak ditemukan.");

$siswa_id=(int)$_SESSION["siswa_id"];
session_write_close();

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    balas(false,"Koneksi database tidak tersedia.");
}

$data=json_decode(file_get_contents("php://input"),true);
if (!is_array($data)) balas(false,"Data jawaban tidak valid.");

$sesi_id=isset($data["sesi_id"])?(int)$data["sesi_id"]:0;
$soal_id=isset($data["soal_id"])?(int)$data["soal_id"]:0;
$opsi_id=isset($data["opsi_id"])?(int)$data["opsi_id"]:0;
if($sesi_id<=0||$soal_id<=0||$opsi_id<=0) balas(false,"ID jawaban tidak valid.");

$stmt=$conn->prepare("SELECT id,ujian_id,status,terkunci_pengawas,batas_waktu FROM sesi_ujian WHERE id=? AND siswa_id=? LIMIT 1");
if(!$stmt) balas(false,"Gagal memeriksa sesi ujian.");
$stmt->bind_param("ii",$sesi_id,$siswa_id);
if(!$stmt->execute()){ $stmt->close(); balas(false,"Gagal memeriksa sesi ujian."); }
$sesi=$stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$sesi) balas(false,"Sesi ujian tidak ditemukan.");
if($sesi["status"]!=="mengerjakan") balas(false,"Jawaban tidak dapat disimpan karena sesi sudah tidak aktif.");
if((int)$sesi["terkunci_pengawas"]===1) balas(false,"Jawaban belum dapat disimpan karena ujian sedang dikunci pengawas.");
if(empty($sesi["batas_waktu"])||strtotime($sesi["batas_waktu"])===false) balas(false,"Batas waktu sesi tidak valid.");
if(strtotime($sesi["batas_waktu"])<=time()) balas(false,"Waktu ujian telah habis.");

$ujian_id=(int)$sesi["ujian_id"];
$stmt=$conn->prepare("SELECT s.id AS soal_id,o.id AS opsi_id FROM soal s INNER JOIN opsi_soal o ON o.soal_id=s.id AND o.id=? WHERE s.id=? AND s.ujian_id=? LIMIT 1");
if(!$stmt) balas(false,"Gagal memeriksa soal dan pilihan jawaban.");
$stmt->bind_param("iii",$opsi_id,$soal_id,$ujian_id);
if(!$stmt->execute()){ $stmt->close(); balas(false,"Gagal memeriksa soal dan pilihan jawaban."); }
$valid=$stmt->get_result()->fetch_assoc();
$stmt->close();
if(!$valid) balas(false,"Pilihan jawaban tidak valid atau bukan milik soal tersebut.");

$sql="INSERT INTO jawaban (sesi_id,soal_id,opsi_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE opsi_id=VALUES(opsi_id)";
$berhasil=false;
for($attempt=1;$attempt<=3;$attempt++){
    $stmt=$conn->prepare($sql);
    if($stmt){
        $stmt->bind_param("iii",$sesi_id,$soal_id,$opsi_id);
        if($stmt->execute()){ $berhasil=true; $stmt->close(); break; }
        $err=(int)$stmt->errno;
        $stmt->close();
        if(!in_array($err,[1205,1213,2006,2013],true)) break;
    } else {
        if($conn->connect_errno){ @$conn->close(); require "../config/database.php"; }
    }
    if($attempt<3) usleep(100000*$attempt);
}
if(!$berhasil) balas(false,"Jawaban gagal disimpan. Silakan pilih kembali jawaban tersebut.");

balas(true,"Jawaban tersimpan.",["sesi_id"=>$sesi_id,"soal_id"=>$soal_id,"opsi_id"=>$opsi_id]);
?>