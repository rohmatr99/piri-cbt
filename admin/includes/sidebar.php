<?php

$current_page = basename($_SERVER["PHP_SELF"]);

$admin_nama = $_SESSION["admin_nama"] ?? "Administrator";
$admin_role = $_SESSION["admin_role"] ?? "admin";

?>

<aside class="sidebar" id="sidebar">

    <div class="brand">

        <div class="brand-logo">
            📝
        </div>

        <div class="brand-text">
            <strong>PIRI CBT</strong>
            <span>ADMINISTRATOR PANEL</span>
        </div>

    </div>


    <div class="menu-title">
        Menu Utama
    </div>


    <nav class="nav">

        <a
            href="dashboard.php"
            class="<?= $current_page === 'dashboard.php' ? 'active' : '' ?>"
        >
            <span class="nav-icon">🏠</span>
            <span>Dashboard</span>
        </a>


        <a
            href="siswa.php"
            class="<?= $current_page === 'siswa.php' ? 'active' : '' ?>"
        >
            <span class="nav-icon">👨‍🎓</span>
            <span>Data Siswa</span>
        </a>

		<a href="monitoring-ujian.php" class="<?= $current_page === 'monitoring-ujian.php' ? 'active' : '' ?>">
    <span class="nav-icon">📡</span><span>Monitoring Ujian</span>
</a>
        <a
            href="ujian.php"
            class="<?= $current_page === 'ujian.php' ? 'active' : '' ?>"
        >
            <span class="nav-icon">📝</span>
            <span>Ujian</span>
        </a>


        <a
            href="bank-soal.php"
            class="<?= $current_page === 'bank-soal.php' ? 'active' : '' ?>"
        >
            <span class="nav-icon">📚</span>
            <span>Bank Soal</span>
        </a>
		<a
    href="mata-pelajaran.php"
    class="<?= $current_page === 'mata-pelajaran.php' ? 'active' : '' ?>"
>
    <span class="nav-icon">📖</span>
    <span>Mata Pelajaran</span>
</a>

        <a
            href="hasil-ujian.php"
            class="<?= $current_page === 'hasil-ujian.php' ? 'active' : '' ?>"
        >
            <span class="nav-icon">📊</span>
            <span>Hasil Ujian</span>
        </a>


        <a
            href="pelanggaran.php"
            class="<?= $current_page === 'pelanggaran.php' ? 'active' : '' ?>"
        >
            <span class="nav-icon">⚠️</span>
            <span>Pelanggaran Ujian</span>
        </a>


        <?php if ($admin_role === "superadmin"): ?>

            <a
                href="user-admin.php"
                class="<?= $current_page === 'user-admin.php' ? 'active' : '' ?>"
            >
                <span class="nav-icon">👥</span>
                <span>User Admin</span>
            </a>

        <?php endif; ?>

    </nav>


    <div class="sidebar-bottom">

        <div class="admin-mini">

            <div class="avatar">
                👤
            </div>

            <div>

                <strong>
                    <?= htmlspecialchars($admin_nama) ?>
                </strong>

                <span>
                    <?= $admin_role === "superadmin"
                        ? "Super Administrator"
                        : "Administrator"
                    ?>
                </span>

            </div>

        </div>


        <a
            href="logout.php"
            class="logout"
        >
            🚪 Keluar
        </a>

    </div>

</aside>


<div
    class="overlay"
    id="overlay"
    onclick="tutupSidebar()"
></div>