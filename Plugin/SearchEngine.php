use Magento\Framework\Api\Search\Document as SearchDocument;
use Magento\Framework\Search\Response\AggregationFactory;

public function __construct(
    LoggerInterface $logger,
    AggregationFactory $aggregationFactory
) {
    $this->logger = $logger;
    $this->aggregationFactory = $aggregationFactory;
}

public function search(RequestInterface $request)
{
    var_dump("SEARCH ENGINE CALLED2");

    $documentData = [
        'entity_id' => '1',
        'status' => 1,
        'visibility' => 4,
        'score' => 1
    ];

    var_dump("DOCUMENT DATA:", $documentData);

    $document = new SearchDocument(
        '1', // ID as string
        $documentData
    );

    $aggregations = $this->aggregationFactory->create([]);

    $response = new QueryResponse(
        [$document],
        $aggregations,
        1 // total count
    );

    return $response;
}
