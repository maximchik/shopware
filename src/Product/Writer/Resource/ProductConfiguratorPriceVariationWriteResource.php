<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Api\Write\Field\FloatField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Product\Event\ProductConfiguratorPriceVariationWrittenEvent;

class ProductConfiguratorPriceVariationWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const CONFIGURATOR_SET_ID_FIELD = 'configuratorSetId';
    protected const VARIATION_FIELD = 'variation';
    protected const OPTIONS_FIELD = 'options';
    protected const IS_GROSS_FIELD = 'isGross';

    public function __construct()
    {
        parent::__construct('product_configurator_price_variation');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::CONFIGURATOR_SET_ID_FIELD] = (new IntField('configurator_set_id'))->setFlags(new Required());
        $this->fields[self::VARIATION_FIELD] = (new FloatField('variation'))->setFlags(new Required());
        $this->fields[self::OPTIONS_FIELD] = new LongTextField('options');
        $this->fields[self::IS_GROSS_FIELD] = new IntField('is_gross');
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ProductConfiguratorPriceVariationWrittenEvent
    {
        $uuids = [];
        if ($updates[self::class]) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new ProductConfiguratorPriceVariationWrittenEvent($uuids, $context, $rawData, $errors);

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
