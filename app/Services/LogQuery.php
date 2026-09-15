<?php

namespace App\Services;

use App\Enums\LogLevel;
use App\Models\Host;
use App\Models\LogEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LogQuery
{
    /**
     * @return Builder<LogEntry>
     */
    public function filtered(Request $request): Builder
    {
        $query = LogEntry::query()
            ->with(['host', 'logSource'])
            ->orderByDesc('logged_at')
            ->orderByDesc('id');

        if ($request->filled('host_id')) {
            $query->where('host_id', $request->integer('host_id'));
        }

        if ($request->filled('source_id')) {
            $query->where('source_id', $request->integer('source_id'));
        }

        if ($request->filled('level')) {
            $level = LogLevel::tryFrom(strtolower((string) $request->query('level')));

            if ($level !== null) {
                $query->where('level', $level);
            }
        }

        if ($request->filled('from')) {
            $query->where('logged_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('logged_at', '<=', $request->date('to'));
        }

        if ($request->filled('ip')) {
            $query->where('ip_address', $request->string('ip')->toString());
        }

        if ($request->filled('user')) {
            $term = $this->safeSearch($request->string('user')->toString());

            if ($term !== '') {
                $query->where('username', 'like', '%'.$term.'%');
            }
        }

        if ($request->filled('process')) {
            $term = $this->safeSearch($request->string('process')->toString());

            if ($term !== '') {
                $query->where('process', 'like', '%'.$term.'%');
            }
        }

        if ($request->filled('q')) {
            $term = $this->safeSearch($request->string('q')->toString());

            if ($term !== '') {
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('message', 'like', '%'.$term.'%')
                        ->orWhere('source', 'like', '%'.$term.'%')
                        ->orWhere('process', 'like', '%'.$term.'%');
                });
            }
        }

        return $query;
    }

    public function paginate(Request $request, int $perPage = 25): LengthAwarePaginator
    {
        return $this->filtered($request)->paginate($perPage)->withQueryString();
    }

    /**
     * @return Collection<int, LogEntry>
     */
    public function export(Request $request, ?int $limit = null): Collection
    {
        $limit ??= (int) config('platform.logs.export_limit');

        return $this->filtered($request)->limit($limit)->get();
    }

    /**
     * @return Collection<int, LogEntry>
     */
    public function recentForHost(Host $host, int $limit = 8): Collection
    {
        return LogEntry::query()
            ->where('host_id', $host->id)
            ->orderByDesc('logged_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function problemCountSince(\DateTimeInterface $since): int
    {
        return (int) LogEntry::query()
            ->where('logged_at', '>=', $since)
            ->whereIn('level', [
                LogLevel::Error,
                LogLevel::Critical,
                LogLevel::Alert,
                LogLevel::Emergency,
            ])
            ->count();
    }

    private function safeSearch(string $value): string
    {
        return str_replace(['%', '_'], '', $value);
    }
}
