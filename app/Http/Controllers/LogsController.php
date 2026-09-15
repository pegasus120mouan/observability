<?php

namespace App\Http\Controllers;

use App\Enums\LogLevel;
use App\Models\Host;
use App\Models\LogEntry;
use App\Models\LogSource;
use App\Services\LogQuery;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogsController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext, LogQuery $logQuery): View
    {
        $this->authorize('viewAny', LogEntry::class);

        abort_if($tenantContext->organization() === null && ! $request->user()?->isSuperAdmin(), 404);

        return view('logs.index', [
            'entries' => $logQuery->paginate($request),
            'hosts' => Host::query()->orderBy('hostname')->orderBy('id')->get(),
            'sources' => LogSource::query()->with('host')->orderBy('name')->orderBy('id')->get(),
            'levels' => LogLevel::cases(),
            'filters' => $request->only(['q', 'host_id', 'source_id', 'level', 'from', 'to', 'ip', 'user', 'process']),
        ]);
    }

    public function export(Request $request, TenantContext $tenantContext, LogQuery $logQuery): StreamedResponse|Response
    {
        $this->authorize('viewAny', LogEntry::class);

        abort_if($tenantContext->organization() === null && ! $request->user()?->isSuperAdmin(), 404);

        $format = $request->query('format', 'csv') === 'json' ? 'json' : 'csv';
        $entries = $logQuery->export($request);

        if ($format === 'json') {
            $payload = $entries->map(fn (LogEntry $entry): array => $this->row($entry))->values();

            return response($payload->toJson(JSON_PRETTY_PRINT), 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="saha-logs.json"',
            ]);
        }

        return response()->streamDownload(function () use ($entries): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['logged_at', 'host', 'level', 'source', 'process', 'username', 'ip_address', 'message']);

            foreach ($entries as $entry) {
                fputcsv($handle, [
                    $entry->logged_at?->toIso8601String(),
                    $entry->host?->hostname,
                    $entry->level->value,
                    $entry->source,
                    $entry->process,
                    $entry->username,
                    $entry->ip_address,
                    $entry->message,
                ]);
            }

            fclose($handle);
        }, 'saha-logs.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(LogEntry $entry): array
    {
        return [
            'logged_at' => $entry->logged_at?->toIso8601String(),
            'host' => $entry->host?->hostname,
            'level' => $entry->level->value,
            'source' => $entry->source,
            'process' => $entry->process,
            'username' => $entry->username,
            'ip_address' => $entry->ip_address,
            'message' => $entry->message,
            'metadata' => $entry->metadata,
        ];
    }
}
