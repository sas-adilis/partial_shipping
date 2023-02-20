<?php
$sql = [
    'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'partial_shipping_order` (
        `id_partial_shipping_order` int(11) NOT NULL AUTO_INCREMENT,
        `id_order` int(11) NOT NULL,
        `id_order_from` int(11) NOT NULL,
        `date_add` datetime NOT NULL,
        UNIQUE KEY `id_partial_shipping_order` (`id_partial_shipping_order`)
    ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;'
];


foreach ($sql as $query) {
    if (!Db::getInstance()->execute($query)) {
        return false;
    }
}