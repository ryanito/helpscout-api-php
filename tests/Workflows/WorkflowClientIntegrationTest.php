<?php

declare(strict_types=1);

namespace HelpScout\Api\Tests\Workflows;

use HelpScout\Api\Tests\ApiClientIntegrationTestCase;
use HelpScout\Api\Tests\Payloads\WorkflowPayloads;
use HelpScout\Api\Workflows\RunWorkflowRequest;
use HelpScout\Api\Workflows\Workflow;

/**
 * @group integration
 */
class WorkflowClientIntegrationTest extends ApiClientIntegrationTestCase
{
    public function testGetWorkflows()
    {
        $this->stubWorkflowResponse(200, 1, 10);

        $workflows = $this->client->workflows()->list();

        $this->assertCount(10, $workflows);
        $this->assertInstanceOf(Workflow::class, $workflows[0]);

        $this->verifySingleRequest(
            'https://api.helpscout.net/v2/workflows'
        );
    }

    public function testGetWorkflowsWithEmptyCollection()
    {
        $this->stubWorkflowResponse(200, 1, 0);

        $workflows = $this->client->workflows()->list();

        $this->assertCount(0, $workflows);

        $this->verifySingleRequest(
            'https://api.helpscout.net/v2/workflows'
        );
    }

    public function testGetWorkflowsParsesPageMetadata()
    {
        $this->stubWorkflowResponse(200, 3, 35);

        $workflows = $this->client->workflows()->list();

        $this->assertSame(3, $workflows->getPageNumber());
        $this->assertSame(10, $workflows->getPageSize());
        $this->assertSame(10, $workflows->getPageElementCount());
        $this->assertSame(35, $workflows->getTotalElementCount());
        $this->assertSame(4, $workflows->getTotalPageCount());
    }

    public function testGetWorkflowsLazyLoadsPages()
    {
        $totalElements = 20;

        $this->stubWorkflowResponse(200, 1, $totalElements);
        $this->stubWorkflowResponse(200, 2, $totalElements);

        $workflows = $this->client->workflows()->list()->getPage(2);

        $this->assertCount(10, $workflows);
        $this->assertInstanceOf(Workflow::class, $workflows[0]);

        $this->verifyMultpleRequests([
            ['GET', 'https://api.helpscout.net/v2/workflows'],
            ['GET', 'https://api.helpscout.net/v2/workflows?page=2'],
        ]);
    }

    public function testRunManualWorkflowWithNoConversationsThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Conversations cannot be empty');
        $this->client->workflows()->runWorkflow(123, []);
    }

    public function testRunManualWorkflow()
    {
        $convoIds = range(1, 10);
        $this->client->workflows()->runWorkflow(123, $convoIds);

        $this->verifyRequestWithData(
            'https://api.helpscout.net/v2/workflows/123/run',
            'POST',
            $convoIds
        );
    }

    public function testRunManualWorkflowSplitsBatchWhenTooLarge()
    {
        $convoIds = range(1, 51);
        $this->client->workflows()->runWorkflow(123, $convoIds);

        [$chunk1, $chunk2] = array_chunk($convoIds, RunWorkflowRequest::MAX_BATCH_SIZE);

        $this->verifyMultipleRequestsWithData([
            ['POST', 'https://api.helpscout.net/v2/workflows/123/run', $chunk1],
            ['POST', 'https://api.helpscout.net/v2/workflows/123/run', $chunk2],
        ]);
    }

    public function testUpdateStatusThrowsExceptionWithBadStatus()
    {
        $convoId = 123;
        $status = 'nope';
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Status must be one of "inactive" or "active"');
        $this->client->workflows()->updateStatus($convoId, $status);
    }

    public function testUpdateStatusCallsEndpoint()
    {
        $workflowId = 123;
        $status = 'active';

        $this->client
            ->workflows()
            ->updateStatus($workflowId, $status);

        $this->verifySingleRequest(
            'https://api.helpscout.net/v2/workflows/123',
            'PATCH'
        );
    }

    protected function stubWorkflowResponse(
        int $status,
        int $page,
        int $totalElements,
        array $headers = []
    ): void {
        $this->stubResponse(
            $status,
            WorkflowPayloads::getWorkflows($page, $totalElements),
            $headers
        );
    }
}
