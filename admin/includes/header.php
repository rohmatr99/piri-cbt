<?php

$page_title = $page_title ?? "PIRI CBT";
$page_description = $page_description ?? "Administrator Panel";

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
        <?= htmlspecialchars($page_title) ?> — PIRI CBT
    </title>

    <style>

        /*
        |--------------------------------------------------------------------------
        | RESET
        |--------------------------------------------------------------------------
        */

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            min-height: 100%;
            width: 100%;
        }

        body {
            font-family:
                Inter,
                "Segoe UI",
                Arial,
                sans-serif;

            background: var(--bg);
            color: var(--text);

            overflow-x: hidden;
        }


        /*
        |--------------------------------------------------------------------------
        | VARIABLES
        |--------------------------------------------------------------------------
        */

        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;

            --sidebar: #0f172a;
            --sidebar-soft: #1e293b;

            --bg: #f1f5f9;
            --card: #ffffff;

            --text: #0f172a;
            --muted: #64748b;

            --border: #e2e8f0;

            --danger: #dc2626;
            --success: #16a34a;

            --sidebar-width: 260px;
        }


        /*
        |--------------------------------------------------------------------------
        | SIDEBAR
        |--------------------------------------------------------------------------
        */

        .sidebar {
            position: fixed;

            top: 0;
            left: 0;

            width: var(--sidebar-width);
            height: 100vh;

            background:
                linear-gradient(
                    180deg,
                    #0f172a 0%,
                    #111827 100%
                );

            color: white;

            padding: 22px 16px;

            z-index: 1000;

            display: flex;
            flex-direction: column;

            box-shadow:
                4px 0 18px
                rgba(15, 23, 42, .12);

            transition:
                transform .22s ease;
        }


        /*
        |--------------------------------------------------------------------------
        | BRAND
        |--------------------------------------------------------------------------
        */

        .brand {
            display: flex;
            align-items: center;

            gap: 12px;

            padding:
                6px 10px 24px;

            border-bottom:
                1px solid
                rgba(255,255,255,.08);

            min-width: 0;
        }

        .brand-logo {
            width: 42px;
            height: 42px;

            flex-shrink: 0;

            border-radius: 12px;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #4f46e5
                );

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 22px;

            box-shadow:
                0 8px 18px
                rgba(37,99,235,.25);
        }

        .brand-text {
            min-width: 0;
        }

        .brand-text strong {
            display: block;

            font-size: 17px;

            letter-spacing: .3px;

            white-space: nowrap;
        }

        .brand-text span {
            display: block;

            margin-top: 3px;

            color: #94a3b8;

            font-size: 11px;

            white-space: nowrap;
        }


        /*
        |--------------------------------------------------------------------------
        | MENU
        |--------------------------------------------------------------------------
        */

        .menu-title {
            margin:
                24px 10px 10px;

            color: #64748b;

            font-size: 10px;
            font-weight: 700;

            letter-spacing: 1.2px;

            text-transform: uppercase;
        }

        .nav {
            display: flex;
            flex-direction: column;

            gap: 5px;

            min-width: 0;
        }

        .nav a {
            display: flex;
            align-items: center;

            gap: 12px;

            padding:
                11px 12px;

            color: #cbd5e1;

            text-decoration: none;

            border-radius: 10px;

            font-size: 14px;

            transition: .18s ease;

            min-width: 0;
        }

        .nav a:hover {
            background:
                var(--sidebar-soft);

            color: white;

            transform:
                translateX(2px);
        }

        .nav a.active {
            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #1d4ed8
                );

            color: white;

            box-shadow:
                0 8px 18px
                rgba(37,99,235,.22);
        }

        .nav-icon {
            width: 22px;

            flex-shrink: 0;

            text-align: center;

            font-size: 18px;
        }


        /*
        |--------------------------------------------------------------------------
        | SIDEBAR BOTTOM
        |--------------------------------------------------------------------------
        */

        .sidebar-bottom {
            margin-top: auto;

            padding-top: 16px;

            border-top:
                1px solid
                rgba(255,255,255,.08);
        }

        .admin-mini {
            display: flex;
            align-items: center;

            gap: 10px;

            margin-bottom: 12px;

            min-width: 0;
        }

        .avatar {
            width: 38px;
            height: 38px;

            flex-shrink: 0;

            border-radius: 50%;

            background: #334155;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 17px;
        }

        .admin-mini > div:last-child {
            min-width: 0;
        }

        .admin-mini strong {
            display: block;

            font-size: 13px;

            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .admin-mini span {
            display: block;

            margin-top: 2px;

            color: #94a3b8;

            font-size: 11px;

            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .logout {
            display: flex;
            align-items: center;
            justify-content: center;

            gap: 8px;

            width: 100%;

            padding:
                10px 12px;

            border-radius: 9px;

            background:
                rgba(220,38,38,.12);

            color: #fca5a5;

            text-decoration: none;

            font-size: 13px;

            transition: .18s ease;
        }

        .logout:hover {
            background: #dc2626;
            color: white;
        }


        /*
        |--------------------------------------------------------------------------
        | MAIN
        |--------------------------------------------------------------------------
        */

        .main {
            width: calc(
                100% - var(--sidebar-width)
            );

            min-height: 100vh;

            margin-left: var(--sidebar-width);

            min-width: 0;
        }


        /*
        |--------------------------------------------------------------------------
        | TOPBAR
        |--------------------------------------------------------------------------
        */

        .topbar {
            min-height: 72px;

            background: white;

            border-bottom:
                1px solid
                var(--border);

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 20px;

            padding:
                14px 32px;

            position: sticky;

            top: 0;

            z-index: 900;
        }

        .page-title {
            min-width: 0;
        }

        .page-title h1 {
            margin: 0;

            font-size: 20px;

            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .page-title p {
            margin:
                4px 0 0;

            color: var(--muted);

            font-size: 12px;

            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .top-user {
            display: flex;
            align-items: center;

            gap: 10px;

            color: #475569;

            font-size: 13px;

            flex-shrink: 0;
        }

        .top-user-icon {
            width: 36px;
            height: 36px;

            flex-shrink: 0;

            border-radius: 50%;

            background: #eff6ff;

            color: var(--primary);

            display: flex;
            align-items: center;
            justify-content: center;
        }


        /*
        |--------------------------------------------------------------------------
        | CONTENT
        |--------------------------------------------------------------------------
        */

        .content {
            width: 100%;

            max-width: 1400px;

            padding:
                28px 32px 40px;

            margin: 0 auto;

            min-width: 0;
        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE HEADER
        |--------------------------------------------------------------------------
        */

        .mobile-header {
            display: none;
        }

        .menu-button {
            width: 40px;
            height: 40px;

            border: 0;

            border-radius: 10px;

            background: #eff6ff;

            color: var(--primary);

            font-size: 20px;

            cursor: pointer;

            display: flex;
            align-items: center;
            justify-content: center;
        }


        /*
        |--------------------------------------------------------------------------
        | OVERLAY
        |--------------------------------------------------------------------------
        */

        .overlay {
            display: none;
        }


        /*
        |--------------------------------------------------------------------------
        | TABLET / LAPTOP KECIL
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1100px) {

            .sidebar {
                transform:
                    translateX(-100%);
            }

            .sidebar.open {
                transform:
                    translateX(0);
            }

            .main {
                width: 100%;
                margin-left: 0;
            }

            .topbar {
                display: none;
            }

            .mobile-header {
                min-height: 62px;

                display: flex;
                align-items: center;
                justify-content: space-between;

                gap: 12px;

                padding:
                    10px 20px;

                background: white;

                border-bottom:
                    1px solid
                    var(--border);

                position: sticky;

                top: 0;

                z-index: 800;
            }

            .mobile-title {
                min-width: 0;

                font-weight: 700;

                font-size: 15px;

                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .overlay.show {
                display: block;

                position: fixed;

                inset: 0;

                background:
                    rgba(15,23,42,.45);

                z-index: 950;
            }

            .content {
                max-width: none;

                padding:
                    24px 24px 35px;
            }

        }


        /*
        |--------------------------------------------------------------------------
        | TABLET
        |--------------------------------------------------------------------------
        */

        @media (max-width: 760px) {

            .mobile-header {
                padding:
                    9px 16px;
            }

            .content {
                padding:
                    20px 16px 30px;
            }

        }


        /*
        |--------------------------------------------------------------------------
        | HP
        |--------------------------------------------------------------------------
        */

        @media (max-width: 480px) {

            .mobile-header {
                min-height: 58px;

                padding:
                    8px 12px;
            }

            .menu-button {
                width: 38px;
                height: 38px;

                border-radius: 9px;

                font-size: 19px;
            }

            .mobile-title {
                font-size: 14px;
            }

            .content {
                padding:
                    16px 12px 25px;
            }

        }


        /*
        |--------------------------------------------------------------------------
        | HP SANGAT KECIL
        |--------------------------------------------------------------------------
        */

        @media (max-width: 360px) {

            .content {
                padding:
                    14px 10px 22px;
            }

        }


        /*
        |--------------------------------------------------------------------------
        | UTILITAS RESPONSIVE
        |--------------------------------------------------------------------------
        */

        img {
            max-width: 100%;
            height: auto;
        }

        input,
        select,
        textarea,
        button {
            max-width: 100%;
        }

        table {
            max-width: 100%;
        }

    </style>

</head>

<body>