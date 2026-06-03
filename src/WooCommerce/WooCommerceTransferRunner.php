<?php

declare(strict_types=1);

namespace Luna\WooCommerce;

use Luna\Connections\ExternalDatabaseConfig;
use Luna\Connections\ExternalPdoConnectionFactory;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\WooCommerceIntegrationRepository;
use PDO;
use RuntimeException;
use Throwable;

final class WooCommerceTransferRunner
{
    /**
     * @param null|callable(array<string, mixed>): PDO $pdoResolver
     */
    public function __construct(
        private readonly WooCommerceIntegrationRepository $repository,
        private readonly ConnectionProfileRepository $connections,
        private readonly ExternalPdoConnectionFactory $pdoFactory,
        private readonly WooCommerceHposValidator $validator,
        private readonly WooCommerceHposOrderReader $reader,
        private readonly WooCommerceTransferWriter $writer,
        private readonly mixed $pdoResolver = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(?int $woocommerceConnectionId = null, ?int $queueId = null, int $limit = 10, bool $retryFailed = false): array
    {
        $queues = $this->repository->pendingQueue($woocommerceConnectionId, $queueId, $limit, $retryFailed);
        $summary = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'orders_written' => 0,
            'addresses_written' => 0,
            'items_written' => 0,
            'item_meta_written' => 0,
            'order_meta_written' => 0,
            'runs' => [],
        ];

        foreach ($queues as $queue) {
            if (! $this->repository->lockQueue((int) $queue['id'], $retryFailed)) {
                continue;
            }

            $summary['processed']++;
            $run = $this->runQueue($queue);
            $summary['runs'][] = $run;
            if ($run['status'] === 'success') {
                $summary['success']++;
            } else {
                $summary['failed']++;
            }
            foreach (['orders_written', 'addresses_written', 'items_written', 'item_meta_written', 'order_meta_written'] as $key) {
                $summary[$key] += (int) ($run[$key] ?? 0);
            }
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $queue
     * @return array<string, mixed>
     */
    private function runQueue(array $queue): array
    {
        $runId = $this->repository->createTransferRun($queue);
        $counts = $this->emptyCounts();
        $summary = [];

        try {
            $connection = $this->repository->findConnection((int) $queue['woocommerce_connection_id']);
            if ($connection === null) {
                throw new RuntimeException('WooCommerce-Anbindung wurde nicht gefunden.');
            }

            $sourcePdo = $this->sourcePdo($connection);
            $validation = $this->validator->validate($sourcePdo);
            $this->repository->updateConnectionValidation((int) $connection['id'], $validation->toArray());
            if (! $validation->transferReady || $validation->tablePrefix === null) {
                throw new RuntimeException(implode(' ', $validation->errors) ?: 'WooCommerce-Validierung ist nicht transferbereit.');
            }

            $sourceOrderId = (string) ($queue['source_order_id'] ?? '');
            $topic = (string) ($queue['topic'] ?? '');

            if ($sourceOrderId === '*') {
                $counts = $this->runInitialImport($sourcePdo, $validation->tablePrefix, $connection);
                $summary['mode'] = 'initial_import';
            } elseif ($topic === 'order.deleted') {
                $marked = $this->writer->markOrderDeleted($connection, (int) $sourceOrderId);
                $counts['skipped_count'] = $marked === 0 ? 1 : 0;
                $summary['mode'] = 'order_deleted';
                $summary['deleted_marker_written'] = $marked;
            } else {
                $counts = $this->runSingleOrderImport($sourcePdo, $validation->tablePrefix, $connection, (int) $sourceOrderId);
                $summary['mode'] = 'single_order_import';
            }

            $this->repository->finishTransferRun($runId, 'success', $counts, $summary);
            $this->repository->markQueueSuccess((int) $queue['id'], $runId);

            return ['run_id' => $runId, 'queue_id' => (int) $queue['id'], 'status' => 'success'] + $counts;
        } catch (Throwable $exception) {
            $counts['error_count'] = max(1, (int) $counts['error_count']);
            $message = $this->safeError($exception->getMessage());
            $this->repository->finishTransferRun($runId, 'failed', $counts, $summary, $message);
            $this->repository->markQueueFailed((int) $queue['id'], $runId, $message);

            return ['run_id' => $runId, 'queue_id' => (int) $queue['id'], 'status' => 'failed', 'error_message' => $message] + $counts;
        }
    }

    /**
     * @param array<string, mixed> $connection
     * @return array<string, int>
     */
    private function runInitialImport(PDO $sourcePdo, string $prefix, array $connection): array
    {
        $counts = $this->emptyCounts();
        $counts['refunds_seen'] = $this->reader->refundCount($sourcePdo, $prefix);
        $orderIds = $this->reader->orderIds($sourcePdo, $prefix);
        $counts['orders_found'] = count($orderIds);

        foreach ($orderIds as $orderId) {
            $order = $this->reader->readOrder($sourcePdo, $prefix, $orderId);
            if ($order === null) {
                $counts['skipped_count']++;
                continue;
            }

            $this->addCounts($counts, $this->writer->writeOrder($order, $connection));
        }

        return $counts;
    }

    /**
     * @param array<string, mixed> $connection
     * @return array<string, int>
     */
    private function runSingleOrderImport(PDO $sourcePdo, string $prefix, array $connection, int $orderId): array
    {
        $counts = $this->emptyCounts();
        $order = $this->reader->readOrder($sourcePdo, $prefix, $orderId);
        if ($order === null) {
            throw new RuntimeException(sprintf('Order %d wurde in HPOS nicht gefunden.', $orderId));
        }

        $counts['orders_found'] = 1;
        $this->addCounts($counts, $this->writer->writeOrder($order, $connection));

        return $counts;
    }

    /**
     * @param array<string, mixed> $connection
     */
    private function sourcePdo(array $connection): PDO
    {
        $profile = $this->connections->find((int) $connection['connection_id']);
        if ($profile === null) {
            throw new RuntimeException('WooCommerce-DB-Connection wurde nicht gefunden.');
        }

        if (is_callable($this->pdoResolver)) {
            return ($this->pdoResolver)($profile);
        }

        return $this->pdoFactory->create(ExternalDatabaseConfig::fromProfile(
            $profile,
            $this->connections->secretsFor((int) $profile['id']),
        ));
    }

    /**
     * @return array<string, int>
     */
    private function emptyCounts(): array
    {
        return [
            'orders_found' => 0,
            'orders_written' => 0,
            'addresses_written' => 0,
            'items_written' => 0,
            'item_meta_written' => 0,
            'order_meta_written' => 0,
            'refunds_seen' => 0,
            'skipped_count' => 0,
            'error_count' => 0,
        ];
    }

    /**
     * @param array<string, int> $target
     * @param array<string, int> $source
     */
    private function addCounts(array &$target, array $source): void
    {
        foreach ($source as $key => $value) {
            $target[$key] = (int) ($target[$key] ?? 0) + $value;
        }
    }

    private function safeError(string $message): string
    {
        $message = preg_replace('/([A-Za-z]:\\\\Users\\\\)[^\\s]+/i', '$1[redacted]', $message) ?? $message;

        return substr($message, 0, 1000);
    }
}
