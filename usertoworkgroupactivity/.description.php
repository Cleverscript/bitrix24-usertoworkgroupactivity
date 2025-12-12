<?php B_PROLOG_INCLUDED === true || die();

use Bitrix\Main\Localization\Loc;

$arActivityDescription = [
	"NAME" => Loc::getMessage("USER_TO_WORKGROUP_ACTIVITY_DESCR_NAME"),
	"DESCRIPTION" => Loc::getMessage("USER_TO_WORKGROUP_ACTIVITY_DESC"),
	"TYPE" => "activity",
	"CLASS" => "UserToWorkgroupActivity",
	"JSCLASS" => "BizProcActivity",
    "CATEGORY" => [
        'ID' => 'document',
        'OWN_ID' => 'crm',
        'OWN_NAME' => 'CRM',
    ]
];
