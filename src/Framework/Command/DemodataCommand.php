<?php declare(strict_types=1);

namespace Shopware\Framework\Command;

use Bezhanov\Faker\Provider\Commerce;
use Faker\Factory;
use Faker\Generator;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Category\Definition\CategoryDefinition;
use Shopware\Api\Context\Definition\ContextRuleDefinition;
use Shopware\Api\Customer\Definition\CustomerDefinition;
use Shopware\Api\Entity\Write\EntityWriterInterface;
use Shopware\Api\Entity\Write\WriteContext;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Api\Product\Definition\ProductManufacturerDefinition;
use Shopware\Context\Rule\Container\AndRule;
use Shopware\Context\Rule\Container\NotRule;
use Shopware\Context\Rule\CurrencyRule;
use Shopware\Context\Rule\OrderAmountRule;
use Shopware\Context\Struct\ShopContext;
use Shopware\Defaults;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DemodataCommand extends ContainerAwareCommand
{
    /**
     * @var Generator
     */
    private $faker;

    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var EntityWriterInterface
     */
    private $writer;

    /**
     * @var array
     */
    private $categories = [];

    public function __construct(?string $name = null, EntityWriterInterface $writer)
    {
        parent::__construct($name);
        $this->writer = $writer;
    }

    protected function configure()
    {
        $this->addOption('products', 'p', InputOption::VALUE_REQUIRED, 'Product count', 500);
        $this->addOption('categories', 'c', InputOption::VALUE_REQUIRED, 'Category count', 10);
        $this->addOption('manufacturers', 'm', InputOption::VALUE_REQUIRED, 'Manufacturer count', 50);
        $this->addOption('customers', null, InputOption::VALUE_REQUIRED, 'Customer count', 200);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->faker = Factory::create('de_DE');
        $this->faker->addProvider(new Commerce($this->faker));

        $this->io->title('Demodata Generator');

        $contextRuleIds = $this->createContextRules();
        $this->createCustomer($input->getOption('customers'));
        $this->createDefaultCustomer();

        $categories = $this->createCategory($input->getOption('categories'));

        $manufacturer = $this->createManufacturer($input->getOption('manufacturers'));
        $this->createProduct(
            $categories,
            $manufacturer,
            $contextRuleIds,
            $input->getOption('products')
        );

        $this->io->newLine();

        $this->io->success('Successfully created demodata.');
    }

    private function getContext()
    {
        return WriteContext::createFromShopContext(
            ShopContext::createDefaultContext()
        );
    }

    private function createCategory($count = 10)
    {
        $payload = [];
        for ($i = 0; $i < $count; ++$i) {
            $payload[] = [
                'id' => $this->faker->uuid,
                'name' => $this->randomDepartment(),
                'parentId' => 'a1abd0ee-0aa6-4fcd-aef7-25b8b84e5943',
            ];
        }
        $parents = $payload;
        foreach ($parents as $category) {
            for ($x = 0; $x < 40; ++$x) {
                $payload[] = [
                    'id' => $this->faker->uuid,
                    'name' => $this->randomDepartment(),
                    'parentId' => $category['id'],
                ];
            }
        }

        $count = count($payload);
        $this->io->section("Generating {$count} categories...");
        $this->io->progressStart($count);

        $chunks = array_chunk($payload, 100);
        foreach ($chunks as $chunk) {
            $this->writer->upsert(CategoryDefinition::class, $chunk, $this->getContext());
            $this->io->progressAdvance(count($chunk));
        }

        $this->io->progressFinish();
        $this->io->comment('Writing to database...');

        return array_column($payload, 'id');
    }

    private function createCustomer($count = 500)
    {
        $number = $this->faker->randomNumber;
        $password = password_hash('shopware', PASSWORD_BCRYPT, ['cost' => 13]);

        $payload = [];
        for ($i = 0; $i < $count; ++$i) {
            $id = $this->faker->uuid;
            $addressId = $this->faker->uuid;
            $firstName = $this->faker->firstName;
            $lastName = $this->faker->lastName;
            $salutation = $this->faker->title;

            $customer = [
                'id' => $id,
                'number' => (string) ($number + $i),
                'salutation' => $salutation,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $this->faker->safeEmail,
                'password' => $password,
                'defaultPaymentMethodId' => 'e84976ac-e9ab-4928-a3dc-c387b66dbaa6',
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'defaultBillingAddressId' => $addressId,
                'defaultShippingAddressId' => $addressId,
                'addresses' => [
                    [
                        'id' => $addressId,
                        'customerId' => $id,
                        'countryId' => 'ffe61e1c-9915-4f95-9701-4a310ab5482d',
                        'salutation' => $salutation,
                        'firstName' => $firstName,
                        'lastName' => $lastName,
                        'street' => $this->faker->streetName,
                        'zipcode' => $this->faker->postcode,
                        'city' => $this->faker->city,
                    ],
                ],
            ];

            $payload[] = $customer;
        }

        $this->io->section(sprintf('Generating %d customers...', count($payload)));
        $this->io->progressStart(count($payload));

        $chunks = array_chunk($payload, 150);
        foreach ($chunks as $chunk) {
            $this->writer->upsert(CustomerDefinition::class, $chunk, $this->getContext());
            $this->io->progressAdvance(count($chunk));
        }

        $this->io->progressFinish();
        $this->io->comment('Writing to database...');
    }

    private function createDefaultCustomer()
    {
        $id = $this->faker->uuid;
        $addressId = $this->faker->uuid;

        $customer = [
            'id' => $id,
            'number' => '1337',
            'salutation' => 'Herr',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => 'test@example.com',
            'password' => password_hash('shopware', PASSWORD_BCRYPT, ['cost' => 13]),
            'defaultPaymentMethodId' => 'e84976ac-e9ab-4928-a3dc-c387b66dbaa6',
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $id,
                    'countryId' => 'ffe61e1c-9915-4f95-9701-4a310ab5482d',
                    'salutation' => 'Herr',
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->writer->upsert(CustomerDefinition::class, [$customer], $this->getContext());
    }

    private function createProduct(array $categories, array $manufacturer, array $contextRules, $count = 500)
    {
        $categoryCount = count($categories) - 1;
        $manufacturerCount = count($manufacturer) - 1;
        $payload = [];

        $size = 100;
        if ($size > $count) {
            $size = $count;
        }

        $this->io->section(sprintf('Generating %d products...', $count));
        $this->io->progressStart($count);

        for ($i = 0; $i < $count; ++$i) {
            $price = mt_rand(1, 1000);

            $payload[] = [
                'id' => $this->faker->uuid,
                'price' => ['gross' => $price, 'net' => $price / 1.19],
                'name' => $this->faker->productName,
                'description' => $this->faker->text(),
                'descriptionLong' => $this->faker->randomHtml(2, 3),
                'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9',
                'manufacturerId' => $manufacturer[random_int(0, $manufacturerCount)],
                'active' => true,
                'categories' => [
                    ['id' => $categories[random_int(0, $categoryCount)]],
                ],
                'stock' => $this->faker->randomNumber(),
                'contextPrices' => $this->createPrices($contextRules),
            ];

            if ($i % $size === 0) {
                $this->writer->upsert(ProductDefinition::class, $payload, $this->getContext());
                $this->io->progressAdvance(count($payload));
                $payload = [];
            }
        }

        $this->io->progressFinish();
    }

    private function createManufacturer($count = 50)
    {
        $this->io->section("Generating {$count} manufacturer...");
        $this->io->progressStart($count);

        $payload = [];
        for ($i = 0; $i < $count; ++$i) {
            $payload[] = [
                'id' => $this->faker->uuid,
                'name' => $this->faker->company,
                'link' => $this->faker->url,
            ];
        }

        $chunks = array_chunk($payload, 100);

        foreach ($chunks as $chunk) {
            $this->writer->upsert(ProductManufacturerDefinition::class, $chunk, $this->getContext());
            $this->io->progressAdvance(count($chunk));
        }

        $this->io->progressFinish();

        return array_column($payload, 'id');
    }

    private function createContextRules(): array
    {
        $payload = [
            [
                'id' => Uuid::uuid4()->toString(),
                'name' => 'High cart value',
                'payload' => new AndRule([
                    new OrderAmountRule(5000, OrderAmountRule::OPERATOR_GTE),
                ]),
            ],
            [
                'id' => Uuid::uuid4()->toString(),
                'name' => 'Other currency',
                'payload' => new NotRule([
                    new CurrencyRule([Defaults::CURRENCY]),
                ]),
            ],
        ];

        $this->writer->insert(ContextRuleDefinition::class, $payload, $this->getContext());

        return array_column($payload, 'id');
    }

    private function createPrices(array $contextRules)
    {
        $prices = [];
        foreach ($contextRules as $ruleId) {
            $gross = random_int(500, 1000);

            $prices[] = [
                'currencyId' => Defaults::CURRENCY,
                'contextRuleId' => $ruleId,
                'quantityStart' => 1,
                'quantityEnd' => 10,
                'price' => ['gross' => $gross, 'net' => $gross / 1.19],
            ];

            $gross = random_int(1, 499);

            $prices[] = [
                'currencyId' => Defaults::CURRENCY,
                'contextRuleId' => $ruleId,
                'quantityStart' => 1,
                'price' => ['gross' => $gross, 'net' => $gross / 1.19],
            ];
        }

        return $prices;
    }

    private function randomDepartment(int $max = 3, bool $fixedAmount = false, bool $unique = true)
    {
        $categoryName = '';
        do {
            $categories = [];

            if (!$fixedAmount) {
                $max = mt_rand(1, $max);
            }

            while (count($categories) < $max) {
                $category = $this->faker->category();
                if (!in_array($category, $categories)) {
                    $categories[] = $category;
                }
            }

            if (count($categories) >= 2) {
                $commaSeparatedCategories = implode(', ', array_slice($categories, 0, -1));
                $categories = [
                    $commaSeparatedCategories,
                    end($categories),
                ];
            }
            $categoryName = implode(' & ', $categories);
        } while (in_array($categoryName, $this->categories) && $unique);
        $categories[] = $categoryName;

        return $categoryName;
    }
}
