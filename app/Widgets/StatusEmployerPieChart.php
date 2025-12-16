<?php

namespace App\Widgets;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Widgets\BaseDimmer;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
class StatusEmployerPieChart extends BaseDimmer
{
    /**
     * The configuration array.
     *
     * @var array
     */
    protected $config = [];

    /**
     * Treat this method as a controller action.
     * Return view() or other content to display.
     */
    public function run()
    {
$statusEmployee = Employee::select('approval_status', DB::raw('COUNT(*) as count'))
            ->groupBy('approval_status')
            ->get();
        $expectedStatuses = ['Revisión'=> '#e3c06d', 'Rechazado' => '#a53d3d', 'Aprobado' => '#2c784f', 'Baja'=>'#c9c7c2'];

        foreach ($expectedStatuses as $statusName => $color) {
            if (!$statusEmployee->contains('approval_status', $statusName)) {
                $statusEmployee->push((object)['approval_status' => $statusName, 'count' => 0]);
            }
        }
        $chartData = [
            'labels' => $statusEmployee->pluck('approval_status')->toArray(),
            'datasets' => [
                [
                    'data' => array_map(function ($label) use ($statusEmployee) {
                        $item = $statusEmployee->firstWhere('approval_status', $label);
                        return $item ? $item->count : 0;
                    }, $statusEmployee->pluck('approval_status')->toArray()),
                    'backgroundColor' => array_map(function ($label) use ($expectedStatuses) {
                        return $expectedStatuses[$label] ?? '#cccccc'; // Default color if status not found
                    }, $statusEmployee->pluck('approval_status')->toArray()),
                ]
            ]
        ];

        return view('vendor.voyager.widgets.status-employer-pie-chart', [
            'chartData' => $chartData
        ]);
    }

     public function shouldBeDisplayed()
   {
        $user = Auth::user();
    return $user->hasRole('admin') || $user->hasRole('lawyer') || $user->hasRole('tech_admin');
   }

}