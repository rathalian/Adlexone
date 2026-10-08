<?php
declare(strict_types=1);

/**
 * Legacy manage=items entry. Field and item-type screens now live separately.
 */
$option = (string) ($_GET['option'] ?? '');
$fieldOptions = [
    'new_custom_field',
    'add_custom_field',
    'modify_custom_field',
    'modify_menu_values',
    'add_update_menu_value',
    'update_menu_value_filter',
    'modify_menu_value_filters',
    'delete_menu_value',
    'update_custom_field',
    'delete_custom_field',
    'manage_fields',
    'manage_fields_types',
    'field_type_search',
    'search',
    'new_multilevel_menu_relationship',
    'add_multilevel_menu',
    'update_multilevel_menu',
    'show_multilevel_menu',
    'show_multilevel_menu_items',
    'update_multilevel_menu_items',
];
$typeOptions = [
    'new_item_type',
    'add_item_type',
    'modify_item_type',
    'update_item_type',
    'manage_item_types',
    'delete_item_type',
];

if (in_array($option, $typeOptions, true)) {
    $_GET['manage'] = 'item-types';
    include __DIR__ . '/item_types.php';
    return;
}

$_GET['manage'] = 'fields';
if ($option === '') {
    $_GET['option'] = 'manage_fields';
}
include __DIR__ . '/fields.php';
