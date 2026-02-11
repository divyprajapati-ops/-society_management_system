<?php

require_once __DIR__ . '/../config/auth.php';

$user = current_user();
$role = $user['role'] ?? null;

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Society App</title>
    <link rel="stylesheet" href="<?php echo e(app_base_url() . '/frontend/assets/css/style.css'); ?>">
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <div class="brand">Society Management</div>
        <div class="topbar-right">
            <?php if ($user): ?>
                <div class="user-chip">
                    <div class="user-name"><?php echo e($user['name'] ?? ''); ?></div>
                    <div class="user-role"><?php echo e($role ?? ''); ?></div>
                </div>
                <a class="btn btn-light" href="<?php echo e(app_base_url() . '/backend/auth/logout.php'); ?>">Logout</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="container">
    <?php if ($user): ?>
        <nav class="nav">
            <?php if ($role === 'society_admin'): ?>
                <a href="<?php echo e(app_base_url() . '/backend/admin/dashboard.php'); ?>">Dashboard</a>
                <a href="<?php echo e(app_base_url() . '/backend/admin/society_fund.php'); ?>">Society Fund</a>
                <a href="<?php echo e(app_base_url() . '/backend/admin/buildings.php'); ?>">Buildings</a>
                <a href="<?php echo e(app_base_url() . '/backend/admin/meetings.php'); ?>">Meetings</a>
                <a href="<?php echo e(app_base_url() . '/backend/admin/notes.php'); ?>">Notes</a>
                <a href="<?php echo e(app_base_url() . '/backend/admin/users.php'); ?>">Users & Roles</a>
            <?php elseif ($role === 'society_pramukh'): ?>
                <a href="<?php echo e(app_base_url() . '/backend/pramukh/dashboard.php'); ?>">Dashboard</a>
                <a href="<?php echo e(app_base_url() . '/backend/pramukh/society_fund.php'); ?>">Society Fund</a>
                <a href="<?php echo e(app_base_url() . '/backend/pramukh/meetings.php'); ?>">Meetings</a>
                <a href="<?php echo e(app_base_url() . '/backend/pramukh/notes.php'); ?>">Notes</a>
                <a href="<?php echo e(app_base_url() . '/backend/pramukh/maintenance.php'); ?>">Maintenance</a>
            <?php elseif ($role === 'building_admin'): ?>
                <a href="<?php echo e(app_base_url() . '/backend/building/dashboard.php'); ?>">Dashboard</a>
                <a href="<?php echo e(app_base_url() . '/backend/building/fund.php'); ?>">Building Fund</a>
                <a href="<?php echo e(app_base_url() . '/backend/building/meetings.php'); ?>">Meetings</a>
                <a href="<?php echo e(app_base_url() . '/backend/building/members.php'); ?>">Members</a>
                <a href="<?php echo e(app_base_url() . '/backend/building/maintenance.php'); ?>">Maintenance</a>
                <a href="<?php echo e(app_base_url() . '/backend/building/reports.php'); ?>">Reports</a>
                <a href="<?php echo e(app_base_url() . '/backend/building/notes.php'); ?>">Notes</a>
            <?php elseif ($role === 'member'): ?>
                <a href="<?php echo e(app_base_url() . '/backend/member/dashboard.php'); ?>">Dashboard</a>
                <a href="<?php echo e(app_base_url() . '/backend/member/fund.php'); ?>">Building Fund</a>
                <a href="<?php echo e(app_base_url() . '/backend/member/meetings.php'); ?>">Meetings</a>
                <a href="<?php echo e(app_base_url() . '/backend/member/maintenance.php'); ?>">My Maintenance</a>
                <a href="<?php echo e(app_base_url() . '/backend/member/notes.php'); ?>">Notes</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
