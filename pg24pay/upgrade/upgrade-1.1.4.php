<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_4($module)
{
    include_once _PS_MODULE_DIR_ . 'pg24pay/core/pg24pay_install.php';
    $install = new Pg24payInstall();
    $install->insertOrderState();

    return true;
}
