<?php B_PROLOG_INCLUDED === true || die();

use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use Bitrix\Bizproc\FieldType;
use Bitrix\Main\ErrorCollection;
use Bitrix\Main\SystemException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Bizproc\Activity\BaseActivity;
use Bitrix\Bizproc\Activity\PropertiesDialog;
use Bitrix\Socialnetwork\WorkgroupTable;
use Bitrix\Socialnetwork\UserToGroupTable;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Im\Model\ChatTable;
use Bitrix\Main\UserTable;

class CBPUserToWorkgroupActivity extends BaseActivity
{
    const DEPENDENCE_MODULES = ['socialnetwork', 'im'];

	public function __construct($name)
	{
		parent::__construct($name);

        foreach (self::DEPENDENCE_MODULES as $mid) {
            if (!Loader::includeModule($mid)) {
                throw new SystemException(
                    Loc::getMessage('USER_TO_WORKGROUP_ACTIVITY_MODULE_NOT_INCLUDED', [
                        '#MODULE_ID#' => $mid,
                    ])
                );
            }
        }

        $this->arProperties = [
            'userId' => null,
            'workgroupId' => null,
            'chatId' => null,
            'role' => null,
        ];
	}

	protected static function getFileName(): string
	{
		return __FILE__;
	}

	protected function internalExecute(): ErrorCollection
	{
		$errors = parent::internalExecute();

        try {
            $userId = (int) preg_replace("/[^0-9]/", '', $this->userId);

            if (empty($userId)) {
                throw new SystemException(Loc::getMessage('USER_TO_WORKGROUP_ACTIVITY_USER_ID_EMPTY'));
            }

            if (empty((int) $this->workgroupId)) {
                throw new SystemException(Loc::getMessage('USER_TO_WORKGROUP_ACTIVITY_WORKGROUP_ID_EMPTY'));
            }

            $arRelations = UserToGroupTable::query()
                ->setSelect([
                    'ID',
                    'GROUP_NAME' => 'GROUP.NAME',
                ])
                ->where('ROLE', $this->role)
                ->where('USER_ID', $userId)
                ->where('GROUP_ID', (int) $this->workgroupId)
                ->registerRuntimeField(
                    new Reference(
                        'GROUP',
                        WorkgroupTable::class,
                        Join::on('this.GROUP_ID', 'ref.ID'),
                        ['join_type' => Join::TYPE_INNER]
                    )
                )
                ->fetch();

            if (!empty($arRelations)) {
                throw new \Exception(Loc::getMessage(
                    'USER_TO_WORKGROUP_USER_EXIST_WORKGROUP',
                    [
                        '#GROUP#' => $arRelations['GROUP_NAME'],
                        '#ROLE#' => $this->role,
                    ]
                ));
            }

            $addResultId = CSocNetUserToGroup::Add(
                array(
                    "USER_ID" => $userId,
                    "GROUP_ID" => (int) $this->workgroupId,
                    "ROLE" => $this->role,
                    "INITIATED_BY_TYPE" => SONET_INITIATED_BY_USER,
                    "INITIATED_BY_USER_ID" => CurrentUser::get()->getId(),
                    "MESSAGE" => false,
                )
            );

            if (!empty($addResultId) && !empty($this->chatId)) {
                $row = UserTable::query()
                    ->where('ID', $userId)
                    ->setSelect(['LOGIN', 'NAME', 'LAST_NAME', 'SECOND_NAME'])
                    ->fetch();

                $fio = trim(implode(' ', [$row['LAST_NAME'], $row['NAME'], $row['SECOND_NAME']]))?: $row['LOGIN'];

                \CIMChat::AddMessage([
                    "TO_CHAT_ID" => (int) $this->chatId,
                    "AUTHOR_ID" => (int) CurrentUser::get()->getId(),
                    "MESSAGE" => Loc::getMessage('USER_TO_WORKGROUP_ACTIVITY_CHAT_MESS', ['#FULL_NAME#' => $fio]),
                    "SYSTEM" => 'Y'
                ]);
            }

        } catch (\Throwable $e) {
            $errors->setError(
                new Error($e->getMessage())
            );
        }

		return $errors;
	}

    protected static function getGroups(): array
    {
        $rows = WorkgroupTable::query()
            ->setSelect(['ID', 'NAME'])
            ->where('ACTIVE', 'Y')
            ->fetchAll();

        if (empty($rows)) {
            return [];
        }

        return array_column($rows, 'NAME', 'ID');
    }

    protected static function getChats(): array
    {
        $rows = ChatTable::query()
            ->setSelect(['ID', 'TITLE'])
            ->where('TYPE', 'C')
            ->where('ENTITY_TYPE', 'SONET_GROUP')
            ->fetchAll();

        if (empty($rows)) {
            return [];
        }

        return array_column($rows, 'TITLE', 'ID');
    }

    public static function getPropertiesDialogMap(?PropertiesDialog $dialog = null): array
    {
        $map = [
            'userId' => [
                'Name' => Loc::getMessage('USER_TO_WORKGROUP_ACTIVITY_USER_ID'),
                'FieldName' => 'userId',
                'Type' => FieldType::STRING,
                'Required' => true,
                'Default' => '',
                'Options' => [],
            ],

            'workgroupId' => [
                'Name' => Loc::getMessage('USER_TO_WORKGROUP_ACTIVITY_WORKGROUP_ID'),
                'FieldName' => 'workgroupId',
                'Type' => FieldType::SELECT,
                'Required' => true,
                'Options' => self::getGroups(),
                'Default' => '',
            ],

            'chatId' => [
                'Name' => Loc::getMessage('USER_TO_WORKGROUP_ACTIVITY_CHAT_ID'),
                'FieldName' => 'chatId',
                'Type' => FieldType::SELECT,
                'Required' => true,
                'Options' => self::getChats(),
                'Default' => '',
            ],

            'role' => [
                'Name' => Loc::getMessage('USER_TO_WORKGROUP_ACTIVITY_WORKGROUP_USER_ROLE'),
                'FieldName' => 'role',
                'Type' => FieldType::SELECT,
                'Options' => [
                    SONET_ROLES_MODERATOR => Loc::getMessage('USER_TO_WORKGROUP_ACTIVITY_ROLE_MODERATOR'),
                    SONET_ROLES_USER => Loc::getMessage('USER_TO_WORKGROUP_ACTIVITY_ROLE_USER'),
                    SONET_ROLES_BAN => Loc::getMessage('USER_TO_WORKGROUP_ACTIVITY_ROLE_BAN'),
                    SONET_ROLES_REQUEST => Loc::getMessage('USER_TO_WORKGROUP_ACTIVITY_ROLE_REQUEST'),
                ],
                'Required' => true,
                'Default' => SONET_ROLES_USER
            ],
        ];

        return $map;
    }
}
