<?php

namespace App\Widgets;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Widgets\BaseDimmer;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;

class ConditionEmployerDonutChart extends BaseDimmer
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

        $conditionEmployers = Employee::select('condition', DB::raw('COUNT(*) as count'))
            ->groupBy('condition')
            ->get();

        $labels = ['Empleado', 'Autónomo'];
        $data = array_map(function ($label) use ($conditionEmployers) {
            $item = $conditionEmployers->firstWhere('condition', $label);
            return $item ? $item->count : 0;
        }, $labels);

        $chartData = [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => ['#162e47', '#e3c06d'],
                ]
            ]
        ];

        return view('vendor.voyager.widgets.condition-employer-donut-chart', [
            'chartData' => $chartData
        ]);
    }

     public function shouldBeDisplayed()
   {
        $user = Auth::user();
    return $user->hasRole('admin') || $user->hasRole('lawyer') || $user->hasRole('tech_admin');
   }

}