<?php

namespace Pyz\Zed\Stock\Business\Internal\DemoData;

use Generated\Zed\Ide\AutoCompletion;
use SprykerEngine\Shared\Kernel\LocatorLocatorInterface;
use Generated\Shared\Transfer\StockProductTransfer;
use Generated\Shared\Transfer\TypeTransfer;
use SprykerFeature\Zed\Installer\Business\Model\AbstractInstaller;
use SprykerFeature\Zed\Library\Import\Reader\CsvFileReader;
use SprykerFeature\Zed\Stock\Business\StockFacade;
use SprykerFeature\Zed\Stock\Persistence\Propel\SpyStock;
use SprykerFeature\Zed\Stock\Persistence\StockQueryContainer;

class StockInstall extends AbstractInstaller
{
    const SKU = 'sku';
    const QUANTITY = 'quantity';
    const NEVER_OUT_OF_STOCK = 'is_never_out_of_stock';
    const STOCK_TYPE = 'stock_type';

    /**
     * @var AutoCompletion
     */
    protected $locator;

    /**
     * @var StockFacade
     */
    protected $stockFacade;

    /**
     * @var StockQueryContainer
     */
    protected $queryContainer;

    /**
     * @param LocatorLocatorInterface $locator
     * @param StockQueryContainer $queryContainer
     * @param StockFacade $stockFacade
     */
    public function __construct(
        LocatorLocatorInterface $locator,
        StockQueryContainer $queryContainer,
        StockFacade $stockFacade
    ) {
        $this->locator = $locator;
        $this->queryContainer = $queryContainer;
        $this->stockFacade = $stockFacade;
    }

    public function install()
    {
        $query = $this->queryContainer->queryAllStockTypes();
        if ($query->count() > 0) {
            $this->warning('Stock-Data is already installed. Skipping');

            return;
        }

        $this->info('This will install a dummy set of stocks in the demo shop');
        $demoStockProducts = $this->getDemoStockProducts();
        $this->writeStockProduct($demoStockProducts);
    }

    /**
     * @param array $demoStock
     */
    protected function writeStockProduct(array $demoStock)
    {
        foreach ($demoStock as $row) {
            $this->addEntry($row);
        }
    }

    /**
     * @return array
     */
    protected function getDemoStockProducts()
    {
        $reader = new CsvFileReader();

        return $reader->read(__DIR__ . '/demo-stock.csv')->getData();
    }

    /**
     * @param array $row
     */
    protected function addEntry(array $row)
    {
        $stockType = $this->createStockTypeTransfer($row);
        if (!$this->doesStockExist($stockType)) {
            $this->stockFacade->createStockType($stockType);
        }
        $stockProductTransfer = $this->createStockProductTransfer($row, $stockType);
        $hasProduct = $this->stockFacade->hasStockProduct(
            $stockProductTransfer->getSku(),
            $stockProductTransfer->getStockType()
        );

        if ($hasProduct) {
            $idStockProduct = $this->stockFacade->getIdStockProduct(
                $stockProductTransfer->getSku(),
                $stockProductTransfer->getStockType()
            );
            $stockProductTransfer->setIdStockProduct($idStockProduct);
            $this->stockFacade->updateStockProduct($stockProductTransfer);
        } else {
            $this->stockFacade->createStockProduct($stockProductTransfer);
        }
    }

    /**
     * @param array $row
     *
     * @return TypeTransfer
     */
    protected function createStockTypeTransfer(array $row)
    {
        $stockType = new TypeTransfer();
        $stockType->setName($row[self::STOCK_TYPE]);

        return $stockType;
    }

    /**
     * @param array $row
     * @param TypeTransfer $stockType
     * @return StockProductTransfer
     */
    protected function createStockProductTransfer(array $row, TypeTransfer $stockType)
    {
        $transferStockProduct = new StockProductTransfer();
        $transferStockProduct->setSku($row[self::SKU])
            ->setIsNeverOutOfStock($row[self::NEVER_OUT_OF_STOCK])
            ->setQuantity($row[self::QUANTITY])
            ->setStockType($stockType->getName())
        ;

        return $transferStockProduct;
    }

    /**
     * @param TypeTransfer $stockType
     *
     * @return bool
     */
    protected function doesStockExist(TypeTransfer $stockType)
    {
        $stockCount = $this->queryContainer
            ->queryStockByName($stockType->getName())
            ->count()
        ;

        return $stockCount > 0;
    }
}
