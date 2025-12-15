<?php

namespace App\Widgets;

use App\Models\Employee;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Widgets\BaseDimmer;

class EmployeesDimmer extends BaseDimmer
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
        $count = Employee::all()->count();

        return view('vendor.voyager.widgets.metric', array_merge($this->config, [
            'icon'   => 'voyager-group',
            'title'  => "{$count} Empleados",
            'text'   => 'Ve a Empleados para verlas todas.',
            'button' => [
                'text' => 'Ver Empleados',
                'link' => route('voyager.employees.index'),
            ],
            'image' => '/fondo_widgets2.jpg',
        ]));
    }
//    public function shouldBeDisplayed()
//    {
//        return Auth::user()->can('browse', Voyager::model('User'));
//    }
}
