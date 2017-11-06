<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\EmarketingVoucherCodesWrittenEvent;

class EmarketingVoucherCodesWriteResource extends WriteResource
{
    protected const VOUCHERID_FIELD = 'voucherID';
    protected const USERID_FIELD = 'userID';
    protected const CODE_FIELD = 'code';
    protected const CASHED_FIELD = 'cashed';

    public function __construct()
    {
        parent::__construct('s_emarketing_voucher_codes');

        $this->fields[self::VOUCHERID_FIELD] = new IntField('voucherID');
        $this->fields[self::USERID_FIELD] = new IntField('userID');
        $this->fields[self::CODE_FIELD] = (new StringField('code'))->setFlags(new Required());
        $this->fields[self::CASHED_FIELD] = (new IntField('cashed'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): EmarketingVoucherCodesWrittenEvent
    {
        $uuids = [];
        if ($updates[self::class]) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new EmarketingVoucherCodesWrittenEvent($uuids, $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            if (!array_key_exists($class, $updates) || count($updates[$class]) === 0) {
                continue;
            }

            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
