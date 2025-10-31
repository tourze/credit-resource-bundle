<?php

declare(strict_types=1);

namespace CreditResourceBundle\Tests\Repository;

use CreditResourceBundle\Entity\ResourcePrice;
use CreditResourceBundle\Enum\FeeCycle;
use CreditResourceBundle\Repository\ResourcePriceRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(ResourcePriceRepository::class)]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ResourcePriceRepositoryTest extends AbstractRepositoryTestCase
{
    private ResourcePriceRepository $repository;

    private ?string $testCurrency = null;

    protected function onSetUp(): void
    {
        $this->repository = self::getService(ResourcePriceRepository::class);
        $this->testCurrency = null;
    }

    public function testSave(): void
    {
        $resourcePrice = $this->createResourcePrice();

        $this->repository->save($resourcePrice, true);

        $this->assertNotNull($resourcePrice->getId());
        $foundPrice = $this->repository->find($resourcePrice->getId());
        $this->assertSame($resourcePrice, $foundPrice);
    }

    public function testRemove(): void
    {
        $resourcePrice = $this->createResourcePrice();
        self::getEntityManager()->persist($resourcePrice);
        self::getEntityManager()->flush();

        $id = $resourcePrice->getId();
        $this->assertNotNull($id);

        $this->repository->remove($resourcePrice, true);

        $foundPrice = $this->repository->find($id);
        $this->assertNull($foundPrice);
    }

    public function testFindValidPricesUsingFindBy(): void
    {
        $validPrice = $this->createResourcePrice('Valid Resource', true);
        $invalidPrice = $this->createResourcePrice('Invalid Resource', false);
        self::getEntityManager()->flush();

        $validPrices = $this->repository->findBy(['valid' => true]);

        $this->assertGreaterThanOrEqual(1, $validPrices);
        $this->assertContains($validPrice, $validPrices);
        $this->assertNotContains($invalidPrice, $validPrices);
    }

    public function testFindByResourceUsingFindBy(): void
    {
        $price1 = $this->createResourcePrice('Resource 1', true, 'resource_1');
        $price2 = $this->createResourcePrice('Resource 2', true, 'resource_1');
        $price3 = $this->createResourcePrice('Resource 3', true, 'resource_2');
        self::getEntityManager()->flush();

        $result = $this->repository->findBy(['resource' => 'resource_1']);

        $this->assertCount(2, $result);
        $this->assertContains($price1, $result);
        $this->assertContains($price2, $result);
        $this->assertNotContains($price3, $result);
    }

    public function testFindByCycleUsingFindBy(): void
    {
        $monthlyPrice = $this->createResourcePrice('Monthly', true, 'resource_1', FeeCycle::TOTAL_BY_MONTH);
        $yearlyPrice = $this->createResourcePrice('Yearly', true, 'resource_2', FeeCycle::TOTAL_BY_YEAR);
        self::getEntityManager()->flush();

        $monthlyPrices = $this->repository->findBy(['cycle' => FeeCycle::TOTAL_BY_MONTH]);

        $this->assertGreaterThanOrEqual(1, $monthlyPrices);
        $this->assertContains($monthlyPrice, $monthlyPrices);
        $this->assertNotContains($yearlyPrice, $monthlyPrices);
    }

    private function createResourcePrice(
        string $title = 'Test Resource',
        bool $valid = true,
        string $resource = 'test_resource',
        FeeCycle $cycle = FeeCycle::TOTAL_BY_MONTH,
    ): ResourcePrice {
        $currency = $this->createCurrency();

        $resourcePrice = new ResourcePrice();
        $resourcePrice->setTitle($title);
        $resourcePrice->setResource($resource);
        $resourcePrice->setPrice('1.00');
        $resourcePrice->setValid($valid);
        $resourcePrice->setCycle($cycle);
        $resourcePrice->setMinAmount(0);
        $resourcePrice->setCurrency($currency);

        $em = self::getEntityManager();
        $em->persist($resourcePrice);

        return $resourcePrice;
    }

    private function createCurrency(): string
    {
        if (null === $this->testCurrency) {
            $suffix = uniqid();
            $this->testCurrency = 'CNY' . $suffix;
        }

        return $this->testCurrency;
    }

    protected function onTearDown(): void
    {
        // Do nothing to avoid base class cleanDatabase call
    }

    /**
     * @return ServiceEntityRepository<ResourcePrice>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }

    public function testFindOneByWithOrderByClause(): void
    {
        $priceB = $this->createResourcePrice('B Resource', true, 'resource_1');
        $priceA = $this->createResourcePrice('A Resource', true, 'resource_2');
        self::getEntityManager()->flush();

        $result = $this->repository->findOneBy([], ['title' => 'ASC']);

        $this->assertSame($priceA, $result);
    }

    public function testFindByWithNullableFieldQuery(): void
    {
        $priceWithRemark = $this->createResourcePrice('With Remark');
        $priceWithRemark->setRemark('Test remark');

        $priceWithoutRemark = $this->createResourcePrice('Without Remark');
        $priceWithoutRemark->setRemark(null);

        self::getEntityManager()->flush();

        $result = $this->repository->findBy(['remark' => null]);

        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertContains($priceWithoutRemark, $result);
        $this->assertNotContains($priceWithRemark, $result);
    }

    public function testFindByMaxAmountNullQuery(): void
    {
        $priceWithMax = $this->createResourcePrice('With Max Amount');
        $priceWithMax->setMaxAmount(1000);

        $priceWithoutMax = $this->createResourcePrice('Without Max Amount');
        $priceWithoutMax->setMaxAmount(null);

        self::getEntityManager()->flush();

        $result = $this->repository->findBy(['maxAmount' => null]);

        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertContains($priceWithoutMax, $result);
        $this->assertNotContains($priceWithMax, $result);
    }

    public function testCountWithNullableFieldQuery(): void
    {
        $priceWithRemark = $this->createResourcePrice('With Remark');
        $priceWithRemark->setRemark('Test remark');

        $priceWithoutRemark = $this->createResourcePrice('Without Remark');
        $priceWithoutRemark->setRemark(null);

        self::getEntityManager()->flush();

        $count = $this->repository->count(['remark' => null]);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindByTopPriceNullQuery(): void
    {
        $priceWithTop = $this->createResourcePrice('With Top Price');
        $priceWithTop->setTopPrice('100.00');

        $priceWithoutTop = $this->createResourcePrice('Without Top Price');
        $priceWithoutTop->setTopPrice(null);

        self::getEntityManager()->flush();

        $result = $this->repository->findBy(['topPrice' => null]);

        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertContains($priceWithoutTop, $result);
        $this->assertNotContains($priceWithTop, $result);
    }

    public function testFindByBottomPriceNullQuery(): void
    {
        $priceWithBottom = $this->createResourcePrice('With Bottom Price');
        $priceWithBottom->setBottomPrice('10.00');

        $priceWithoutBottom = $this->createResourcePrice('Without Bottom Price');
        $priceWithoutBottom->setBottomPrice(null);

        self::getEntityManager()->flush();

        $result = $this->repository->findBy(['bottomPrice' => null]);

        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertContains($priceWithoutBottom, $result);
        $this->assertNotContains($priceWithBottom, $result);
    }

    public function testFindByBillingStrategyNullQuery(): void
    {
        $priceWithStrategy = $this->createResourcePrice('With Strategy');
        $priceWithStrategy->setBillingStrategy('FixedStrategy');

        $priceWithoutStrategy = $this->createResourcePrice('Without Strategy');
        $priceWithoutStrategy->setBillingStrategy(null);

        self::getEntityManager()->flush();

        $result = $this->repository->findBy(['billingStrategy' => null]);

        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertContains($priceWithoutStrategy, $result);
        $this->assertNotContains($priceWithStrategy, $result);
    }

    public function testFindByPriceRulesNullQuery(): void
    {
        $priceWithRules = $this->createResourcePrice('With Rules');
        $priceWithRules->setPriceRules([['min' => 0, 'max' => 100, 'price' => '1.00']]);

        $priceWithoutRules = $this->createResourcePrice('Without Rules');
        $priceWithoutRules->setPriceRules(null);

        self::getEntityManager()->flush();

        $result = $this->repository->findBy(['priceRules' => null]);

        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertContains($priceWithoutRules, $result);
        $this->assertNotContains($priceWithRules, $result);
    }

    public function testFindByFreeQuotaNullQuery(): void
    {
        $priceWithQuota = $this->createResourcePrice('With Quota');
        $priceWithQuota->setFreeQuota(100);

        $priceWithoutQuota = $this->createResourcePrice('Without Quota');
        $priceWithoutQuota->setFreeQuota(null);

        self::getEntityManager()->flush();

        $result = $this->repository->findBy(['freeQuota' => null]);

        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertContains($priceWithoutQuota, $result);
        $this->assertNotContains($priceWithQuota, $result);
    }

    public function testFindByStartTimeNullQuery(): void
    {
        $priceWithStart = $this->createResourcePrice('With Start Time');
        $priceWithStart->setStartTime(new \DateTimeImmutable('2023-01-01'));

        $priceWithoutStart = $this->createResourcePrice('Without Start Time');
        $priceWithoutStart->setStartTime(null);

        self::getEntityManager()->flush();

        $result = $this->repository->findBy(['startTime' => null]);

        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertContains($priceWithoutStart, $result);
        $this->assertNotContains($priceWithStart, $result);
    }

    public function testFindByEndTimeNullQuery(): void
    {
        $priceWithEnd = $this->createResourcePrice('With End Time');
        $priceWithEnd->setEndTime(new \DateTimeImmutable('2023-12-31'));

        $priceWithoutEnd = $this->createResourcePrice('Without End Time');
        $priceWithoutEnd->setEndTime(null);

        self::getEntityManager()->flush();

        $result = $this->repository->findBy(['endTime' => null]);

        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertContains($priceWithoutEnd, $result);
        $this->assertNotContains($priceWithEnd, $result);
    }

    public function testCountByTopPriceNullQuery(): void
    {
        $priceWithTop = $this->createResourcePrice('With Top Price');
        $priceWithTop->setTopPrice('100.00');

        $priceWithoutTop = $this->createResourcePrice('Without Top Price');
        $priceWithoutTop->setTopPrice(null);

        self::getEntityManager()->flush();

        $count = $this->repository->count(['topPrice' => null]);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testCountByBottomPriceNullQuery(): void
    {
        $priceWithBottom = $this->createResourcePrice('With Bottom Price');
        $priceWithBottom->setBottomPrice('10.00');

        $priceWithoutBottom = $this->createResourcePrice('Without Bottom Price');
        $priceWithoutBottom->setBottomPrice(null);

        self::getEntityManager()->flush();

        $count = $this->repository->count(['bottomPrice' => null]);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testCountByBillingStrategyNullQuery(): void
    {
        $priceWithStrategy = $this->createResourcePrice('With Strategy');
        $priceWithStrategy->setBillingStrategy('FixedStrategy');

        $priceWithoutStrategy = $this->createResourcePrice('Without Strategy');
        $priceWithoutStrategy->setBillingStrategy(null);

        self::getEntityManager()->flush();

        $count = $this->repository->count(['billingStrategy' => null]);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testCountByPriceRulesNullQuery(): void
    {
        $priceWithRules = $this->createResourcePrice('With Rules');
        $priceWithRules->setPriceRules([['min' => 0, 'max' => 100, 'price' => '1.00']]);

        $priceWithoutRules = $this->createResourcePrice('Without Rules');
        $priceWithoutRules->setPriceRules(null);

        self::getEntityManager()->flush();

        $count = $this->repository->count(['priceRules' => null]);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testCountByFreeQuotaNullQuery(): void
    {
        $priceWithQuota = $this->createResourcePrice('With Quota');
        $priceWithQuota->setFreeQuota(100);

        $priceWithoutQuota = $this->createResourcePrice('Without Quota');
        $priceWithoutQuota->setFreeQuota(null);

        self::getEntityManager()->flush();

        $count = $this->repository->count(['freeQuota' => null]);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testCountByStartTimeNullQuery(): void
    {
        $priceWithStart = $this->createResourcePrice('With Start Time');
        $priceWithStart->setStartTime(new \DateTimeImmutable('2023-01-01'));

        $priceWithoutStart = $this->createResourcePrice('Without Start Time');
        $priceWithoutStart->setStartTime(null);

        self::getEntityManager()->flush();

        $count = $this->repository->count(['startTime' => null]);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testCountByEndTimeNullQuery(): void
    {
        $priceWithEnd = $this->createResourcePrice('With End Time');
        $priceWithEnd->setEndTime(new \DateTimeImmutable('2023-12-31'));

        $priceWithoutEnd = $this->createResourcePrice('Without End Time');
        $priceWithoutEnd->setEndTime(null);

        self::getEntityManager()->flush();

        $count = $this->repository->count(['endTime' => null]);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    protected function createNewEntity(): object
    {
        $currency = $this->createCurrency();

        $resourcePrice = new ResourcePrice();
        $resourcePrice->setTitle('Test Resource');
        $resourcePrice->setResource('test_resource');
        $resourcePrice->setPrice('1.00');
        $resourcePrice->setValid(true);
        $resourcePrice->setCycle(FeeCycle::TOTAL_BY_MONTH);
        $resourcePrice->setMinAmount(0);
        $resourcePrice->setCurrency($currency);

        return $resourcePrice;
    }
}
