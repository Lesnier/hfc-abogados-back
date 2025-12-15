<?php

namespace App\Widgets;

use App\Models\Supplier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Widgets\BaseDimmer;

class SuppliersDimmer extends BaseDimmer
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
        $count = Supplier::all()->count();

        return view('vendor.voyager.widgets.metric', array_merge($this->config, [
            'icon'   => 'voyager-bookmark',
            'title'  => "{$count} Proveedores",
            'text'   => 'Ve a Proveedores para verlas todas.',
            'button' => [
                'text' => 'Ver Proveedores',
                'link' => route('voyager.suppliers.index'),
            ],
            'image' => '/fondo_widgets3.jpg',
        ]));
    }
//    public function shouldBeDisplayed()
//    {
//        return Auth::user()->can('browse', Voyager::model('User'));
//    }
}
