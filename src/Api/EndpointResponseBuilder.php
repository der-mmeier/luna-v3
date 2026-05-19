<?php

declare(strict_types=1);

namespace Luna\Api;

use Luna\Config\Config;
use Luna\Core\AppVersion;
use Luna\Http\Response;
use Luna\Jobs\JobRunner;
use Luna\Repository\JobRepository;
use Luna\Repository\JobRunRepository;
use Luna\Repository\ReportRepository;

final class EndpointResponseBuilder
{
    public function __construct(
        private readonly Config $config,
        private readonly JobRepository $jobs,
        private readonly JobRunRepository $runs,
        private readonly ReportRepository $reports,
        private readonly JobRunner $jobRunner,
    ) {
    }

    public function build(array $endpoint): Response
    {
        return match ((string) $endpoint['source_type']) {
            'version' => Response::json([
                'app' => $this->config->string('APP_NAME', 'Luna V3'),
                'version' => AppVersion::VERSION,
                'environment' => $this->config->string('APP_ENV', 'local'),
                'status' => 'ok',
            ]),
            'mapping_dry_run' => $this->mappingDryRun($endpoint),
            'job_status' => $this->jobStatus($endpoint),
            'latest_report' => $this->latestReport($endpoint),
            default => $this->staticResponse($endpoint),
        };
    }

    private function staticResponse(array $endpoint): Response
    {
        $config = $this->configJson($endpoint);
        if (isset($config['static_response']) && is_array($config['static_response'])) {
            return Response::json($this->sanitize($config['static_response']));
        }

        return Response::json([
            'status' => 'ok',
            'endpoint' => $endpoint['endpoint_key'],
        ]);
    }

    private function mappingDryRun(array $endpoint): Response
    {
        if (empty($endpoint['mapping_set_id'])) {
            return Response::json(['error' => 'mapping_set_missing'], 422);
        }

        $runId = $this->jobRunner->runMappingOnce((int) $endpoint['mapping_set_id'], true, 25);
        $run = $this->runs->findRun($runId);
        $summary = json_decode((string) ($run['summary_json'] ?? '{}'), true);
        $summary = is_array($summary) ? $this->sanitize($summary) : [];
        $preview = $summary['preview_rows'] ?? [];
        if (is_array($preview)) {
            $preview = array_slice($preview, 0, 25);
        }

        return Response::json([
            'run_id' => $runId,
            'status' => $run['status'] ?? 'unknown',
            'summary' => [
                'dry_run' => true,
                'source_count' => (int) ($run['source_count'] ?? 0),
                'transformed_count' => (int) ($run['transformed_count'] ?? 0),
                'written_count' => (int) ($run['written_count'] ?? 0),
                'skipped_count' => (int) ($run['skipped_count'] ?? 0),
                'error_count' => (int) ($run['error_count'] ?? 0),
            ],
            'preview_rows' => is_array($preview) ? $preview : [],
        ]);
    }

    private function jobStatus(array $endpoint): Response
    {
        if (empty($endpoint['job_id'])) {
            return Response::json(['error' => 'job_missing'], 422);
        }

        $job = $this->jobs->find((int) $endpoint['job_id']);
        if ($job === null) {
            return Response::json(['error' => 'job_not_found'], 404);
        }

        return Response::json([
            'job' => [
                'id' => (int) $job['id'],
                'name' => $job['name'],
                'status' => $job['status'],
                'last_run_at' => $job['last_run_at'] ?? null,
            ],
            'runs' => array_map(static fn (array $run): array => [
                'id' => (int) $run['id'],
                'status' => $run['status'],
                'dry_run' => (int) $run['dry_run'] === 1,
                'source_count' => (int) $run['source_count'],
                'transformed_count' => (int) $run['transformed_count'],
                'written_count' => (int) $run['written_count'],
                'error_count' => (int) $run['error_count'],
                'created_at' => $run['created_at'],
            ], array_slice($this->runs->runsForJob((int) $job['id']), 0, 10)),
        ]);
    }

    private function latestReport(array $endpoint): Response
    {
        $report = null;
        if (! empty($endpoint['job_id'])) {
            $report = $this->reports->latestForJob((int) $endpoint['job_id']);
        }
        if ($report === null && ! empty($endpoint['workspace_id'])) {
            $report = $this->reports->latestForWorkspace((int) $endpoint['workspace_id']);
        }
        if ($report === null) {
            return Response::json(['error' => 'report_not_found'], 404);
        }

        return Response::json([
            'id' => (int) $report['id'],
            'subject' => $report['subject'],
            'status' => $report['status'],
            'created_at' => $report['created_at'],
            'body' => substr((string) $report['body'], 0, 4000),
        ]);
    }

    private function configJson(array $endpoint): array
    {
        $config = json_decode((string) ($endpoint['config_json'] ?? ''), true);

        return is_array($config) ? $config : [];
    }

    private function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match('/secret|password|token|api_key|app_key|client_secret|key/i', $key) === 1) {
                $data[$key] = '[masked]';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitize($value);
            }
        }

        return $data;
    }
}
