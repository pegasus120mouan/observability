<?php

namespace App\Http\Controllers;

use App\Actions\SaveDashboardWidgetAction;
use App\Enums\DashboardStatMetric;
use App\Enums\DashboardWidgetType;
use App\Http\Requests\StoreDashboardWidgetRequest;
use App\Http\Requests\UpdateDashboardWidgetRequest;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Host;
use App\Support\DashboardCatalog;
use App\Support\MetricCatalog;
use Illuminate\Http\RedirectResponse;

class DashboardWidgetController extends Controller
{
    public function store(StoreDashboardWidgetRequest $request, Dashboard $dashboard, SaveDashboardWidgetAction $action): RedirectResponse
    {
        $this->authorize('update', $dashboard);

        $action->handle($dashboard, $request->validated());

        return redirect()->route('dashboards.edit', $dashboard)->with('status', 'Widget added.');
    }

    public function update(
        UpdateDashboardWidgetRequest $request,
        Dashboard $dashboard,
        DashboardWidget $widget,
        SaveDashboardWidgetAction $action,
    ): RedirectResponse {
        $this->authorize('update', $dashboard);
        abort_if($widget->dashboard_id !== $dashboard->id, 404);

        $action->handle($dashboard, $request->validated(), $widget);

        return redirect()->route('dashboards.edit', $dashboard)->with('status', 'Widget updated.');
    }

    public function destroy(Dashboard $dashboard, DashboardWidget $widget): RedirectResponse
    {
        $this->authorize('update', $dashboard);
        abort_if($widget->dashboard_id !== $dashboard->id, 404);

        $widget->delete();

        return redirect()->route('dashboards.edit', $dashboard)->with('status', 'Widget removed.');
    }

    /**
     * @return array<string, mixed>
     */
    public static function formData(): array
    {
        return [
            'types' => DashboardWidgetType::cases(),
            'statMetrics' => DashboardStatMetric::cases(),
            'catalog' => MetricCatalog::chartable(),
            'ranges' => array_keys(DashboardCatalog::ranges()),
            'widths' => DashboardCatalog::widths(),
            'hosts' => Host::query()->orderBy('hostname')->orderBy('id')->get(),
        ];
    }
}
