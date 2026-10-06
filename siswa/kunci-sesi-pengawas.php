<?php
declare(strict_types=1); date_default_timezone_set("Asia/Jakarta"); session_start(); require_once "../config/database.php";
header("Content-Type: application/json; charset=UTF-8"); header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
function jr(bool $ok,string $msg,array $x=[],int $c=200): never { http_response_code($c); echo json_encode(array_merge(["status"=>$ok,"pesan"=>$msg],$x),JSON_UNESCAPED_UNICODE); exit; }
if($_SERVER["REQUEST_METHOD"]!=="POST") jr(false,"Metode tidak diizinkan.",[],405); if(!isset($_SESSION["siswa_id"])) jr(false,"Sesi login siswa tidak ditemukan.",[],401);
$siswa=(int)$_SESSION["siswa_id"]; $sid=(int)($_POST["sesi_id"]??0); $uid=(int)($_POST["ujian_id"]??0); if($sid<=0||$uid<=0) jr(false,"Data sesi ujian tidak lengkap.",[],400);
$conn->begin_transaction(); try {
$st=$conn->prepare("SELECT id,siswa_id,ujian_id,status,batas_waktu,terkunci_pengawas FROM sesi_ujian WHERE id=? LIMIT 1 FOR UPDATE"); if(!$st) throw new Exception("Gagal memeriksa sesi."); $st->bind_param("i",$sid); $st->execute(); $s=$st->get_result()->fetch_assoc(); $st->close(); if(!$s||(int)$s["siswa_id"]!==$siswa||(int)$s["ujian_id"]!==$uid) throw new Exception("Sesi ujian tidak valid."); if($s["status"]!=="mengerjakan") throw new Exception("Ujian sudah selesai."); if(strtotime($s["batas_waktu"])<=time()) throw new Exception("Waktu ujian telah habis.");
if((int)$s["terkunci_pengawas"]===1){$conn->commit();jr(true,"Sesi sudah terkunci.",["terkunci_pengawas"=>true]);}
$st=$conn->prepare("UPDATE kode_pengawas_ujian SET status='batal' WHERE sesi_id=? AND status='aktif'"); if(!$st) throw new Exception("Gagal membatalkan kode lama."); $st->bind_param("i",$sid); $st->execute(); $st->close();
$kode=(string)random_int(100000,999999); $st=$conn->prepare("INSERT INTO kode_pengawas_ujian(sesi_id,kode,dibuat,digunakan,status) VALUES(?,?,NOW(),NULL,'aktif')"); if(!$st) throw new Exception("Gagal menyiapkan kode pengawas."); $st->bind_param("is",$sid,$kode); if(!$st->execute()) throw new Exception("Gagal membuat kode pengawas."); $st->close();
$st=$conn->prepare("UPDATE sesi_ujian SET terkunci_pengawas=1 WHERE id=? AND siswa_id=? AND ujian_id=? AND status='mengerjakan'"); if(!$st) throw new Exception("Gagal menyiapkan penguncian sesi."); $st->bind_param("iii",$sid,$siswa,$uid); if(!$st->execute()||$st->affected_rows!==1) throw new Exception("Sesi gagal dikunci."); $st->close(); $conn->commit(); jr(true,"Sesi berhasil dikunci. Kode pengawas telah dibuat.",["terkunci_pengawas"=>true]);
} catch(Throwable $e){$conn->rollback(); error_log("kunci-sesi-pengawas.php: ".$e->getMessage()); jr(false,$e->getMessage(),[],400);}
?>
