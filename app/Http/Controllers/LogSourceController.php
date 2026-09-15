<?php

namespace App\Http\Controllers;

use App\Enums\LogSourceStatus;
use App\Models\LogSource;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LogSourceController extends Controller
{
    public function index(TenantContext $tenantContext): View
    {
        $this->authorize('viewAny', LogSource::class);

        abort_if($tenantContext->organization() === null && ! request()->user()?->isSuperAdmin(), 404);

        $sources = LogSource::query()
            ->with('host')
            ->withCount('entries')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20);

        return view('logs.sources', [
            'sources' => $sources,
        ]);
    }

    public function pause(LogSource $logSource): RedirectResponse
    {
        $this->authorize('update', $logSource);

        $logSource->forceFill(['status' => LogSourceStatus::Paused])->save();

        return back()->with('status', 'Log source paused. New entries from this source will be skipped.');
    }

    public function resume(LogSource $logSource): RedirectResponse
    {
        $this->authorize('update', $logSource);

        $logSource->forceFill(['status' => LogSourceStatus::Active])->save();

        return back()->with('status', 'Log source resumed.');
    }
}
