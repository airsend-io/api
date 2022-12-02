<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Admin;

use CodeLathe\Core\Data\AdminReports\AbstractAdminReport;
use CodeLathe\Core\Data\AdminReportsDataStore;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Managers\ManagerBase;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\Directories;
use CodeLathe\Core\Utility\JsonOutput;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Request as Request;

/**
 * Class AdminReportsManager
 *
 * To add new reports, just include a new protected method to AdminReportsDataStore, prefixed with 'rep_'
 * No change to this class required
 *
 * @package CodeLathe\Core\Managers\Admin
 */
class AdminReportsManager extends ManagerBase
{

    protected $dataController;

    public function __construct(DataController $dataController)
    {
        $this->dataController = $dataController;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws \CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException
     */
    public function list(Request $request, Response $response): Response
    {
        return JsonOutput::success(200)->withContent('reports', AbstractAdminReport::listReports())->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     * @throws \CodeLathe\Core\Exception\InvalidFailedHttpCodeException
     */
    public function execute(Request $request, Response $response): Response
    {

        $queryParams = $request->getQueryParams();

        $reportId = $queryParams['report'] ?? null;
        if (!$reportId === null) {
            return JsonOutput::error('Report id is required', 400)->write($response);
        }

        $validReportId = count(array_filter(AbstractAdminReport::listReports(), function ($item) use ($reportId) {
            return $item['id'] === $reportId;
        }));
        if (!$validReportId) {
            return JsonOutput::error('Invalid report ID', 400)->write($response);
        }

        $emailToSend = $queryParams['email'] ?? null;
        $limit = $queryParams['limit'] ?? 30;
        $offset = $queryParams['offset'] ?? 0;
        $orderColumn = $queryParams['sort_by'] ?? null;
        $orderDirection = $queryParams['sort_direction'] ?? null;

        $reportClass = preg_replace('/[^\\\\]+$/',$reportId, AbstractAdminReport::class);
        /** @var AbstractAdminReport $reportObject */
        $reportObject = ContainerFacade::get($reportClass);

        if ($emailToSend !== null){

            // if the email to send is set, ignore everything else, and execute in background
            $reportObject->executeInBackground($emailToSend);

            return JsonOutput::success()
                ->withContent('report_name', $reportObject::name())
                ->withContent('report_description', $reportObject::description())
                ->withContent('message', "Report will be sent to email $emailToSend. It can take up to 5 minutes.")
                ->write($response);

        }



        return JsonOutput::success()
            ->withContent('report_name', $reportObject::name())
            ->withContent('report_description', $reportObject::description())
            ->withContent('total_count', $reportObject->countTotal())
            ->withContent('data', $reportObject->execute($limit, $offset, $orderColumn, $orderDirection))
            ->write($response);
    }

    /**
     * @inheritDoc
     */
    protected function dataController(): DataController
    {
        return $this->dataController;
    }
}